<?php
require_once dirname(__DIR__) . '/config.php';
requireLogin();
$role=$_SESSION['role'];
if(!in_array($role,['teacher','admin','mosque_admin'])){ header('Location: /dashboard.php'); exit; }
$lang=$_SESSION['lang']??'en'; $isRtl=$lang==='ar';
$pdo=getPDO(); $userId=$_SESSION['user_id'];

$studentId=(int)($_GET['id']??0);
if(!$studentId){ header('Location: /dashboard.php'); exit; }

// Access control
if($role==='teacher'){
    $student=$pdo->prepare("SELECT s.*,m.name_en as mosque_name,m.name_ar as mosque_name_ar,u.full_name as user_name,u.email
        FROM students s LEFT JOIN mosques m ON m.id=s.mosque_id LEFT JOIN users u ON u.id=s.user_id
        JOIN enrollments e ON e.student_id=s.id JOIN classes c ON c.id=e.class_id AND c.teacher_id=?
        WHERE s.id=? AND s.is_active=1 LIMIT 1");
    $student->execute([$userId,$studentId]);
} elseif($role==='mosque_admin'){
    $mosqueId=$_SESSION['user']['mosque_id']??0;
    $student=$pdo->prepare("SELECT s.*,m.name_en as mosque_name,m.name_ar as mosque_name_ar,u.full_name as user_name,u.email
        FROM students s LEFT JOIN mosques m ON m.id=s.mosque_id LEFT JOIN users u ON u.id=s.user_id
        WHERE s.id=? AND s.mosque_id=? AND s.is_active=1 LIMIT 1");
    $student->execute([$studentId,$mosqueId]);
} else {
    $student=$pdo->prepare("SELECT s.*,m.name_en as mosque_name,m.name_ar as mosque_name_ar,u.full_name as user_name,u.email
        FROM students s LEFT JOIN mosques m ON m.id=s.mosque_id LEFT JOIN users u ON u.id=s.user_id
        WHERE s.id=? AND s.is_active=1 LIMIT 1");
    $student->execute([$studentId]);
}
$student=$student->fetch();
if(!$student){ setFlash('danger','Student not found'); header('Location: /dashboard.php'); exit; }

$isChild=$student['student_type']==='child';
$pageTitle=h($student['full_name']).' — '.APP_NAME;

// DELETE
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete'){
    $did=(int)($_POST['id']??$studentId);
    // SOFT DELETE — بيانات الطالب تبقى محفوظة، فقط نعطله
    // نوقف تسجيله في البرامج والصفوف
    $pdo->prepare("UPDATE program_enrollments SET status='dropped' WHERE student_id=?")->execute([$did]);
    $pdo->prepare("UPDATE enrollments SET status='dropped' WHERE student_id=?")->execute([$did]);
    // نعطل حساب المستخدم إن كان موجوداً (لا نحذفه)
    if($student['user_id']){
        $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$student['user_id']]);
    }
    // نعطل سجل الطالب — كل بيانات الحضور والتقدم تبقى سليمة
    $pdo->prepare("UPDATE students SET is_active=0 WHERE id=?")->execute([$did]);
    setFlash('success','✅ '.($isRtl?'تم إلغاء تسجيل الطالب وتعطيل حسابه. بياناته محفوظة.':'Student deactivated. All records preserved.'));
    header('Location: '.($role==='admin'?'/admin/students.php':($role==='mosque_admin'?'/mosque_admin/students.php':'/teacher/programs.php'))); exit;
}

// Active course + this week's lesson
$activeCourse=$pdo->prepare("
    SELECT fc.*,fc.id as course_id,mp.id as prog_id,mp.slot,mp.target_type,mp.days,mp.time_start,mp.time_end,
           u.full_name as teacher_name,
           wl.topic_en,wl.topic_ar,wl.week_start,wl.week_end,
           wl.surah_name_ar,wl.surah_name_en,wl.ayah_from,wl.ayah_to,
           wl.objectives,wl.homework,wl.notes as lesson_notes,wl.status as lesson_status
    FROM program_enrollments pe
    JOIN mosque_programs mp ON mp.id=pe.program_id
    JOIN teacher_course_selections tcs ON tcs.program_id=mp.id AND tcs.is_active=1
    JOIN fixed_courses fc ON fc.id=tcs.course_id
    JOIN users u ON u.id=mp.teacher_id
    LEFT JOIN weekly_lessons wl ON wl.program_id=mp.id
        AND wl.week_start<=CURDATE() AND wl.week_end>=CURDATE()
    WHERE pe.student_id=? AND pe.status='active' LIMIT 1");
$activeCourse->execute([$studentId]); $activeCourse=$activeCourse->fetch();

// All progress records
$progress=$pdo->prepare("SELECT * FROM progress WHERE student_id=? ORDER BY updated_at DESC");
$progress->execute([$studentId]); $progress=$progress->fetchAll();

// Attendance stats
$attStats=$pdo->prepare("SELECT COUNT(*) as total,
    SUM(status='present') as present,SUM(status='absent') as absent,
    SUM(status='late') as late,SUM(status='excused') as excused
    FROM attendance WHERE student_id=?");
$attStats->execute([$studentId]); $attStats=$attStats->fetch();

// Last 30 days attendance
$recentAtt=$pdo->prepare("SELECT a.date,a.status FROM attendance a
    WHERE a.student_id=? AND a.date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) ORDER BY a.date ASC");
$recentAtt->execute([$studentId]); $recentAtt=$recentAtt->fetchAll();
$attByDate=[]; foreach($recentAtt as $a) $attByDate[$a['date']]=$a['status'];

// Recent lessons (last 5)
$recentLessons=$pdo->prepare("SELECT wl.* FROM weekly_lessons wl
    JOIN program_enrollments pe ON pe.program_id=wl.program_id
    WHERE pe.student_id=? AND pe.status='active' ORDER BY wl.week_start DESC LIMIT 5");
$recentLessons->execute([$studentId]); $recentLessons=$recentLessons->fetchAll();

// Stats
$totalRecs=count($progress);
$doneRecs=count(array_filter($progress,fn($p)=>$p['memorization_pct']==100));
$avgPct=$totalRecs>0?round(array_sum(array_column($progress,'memorization_pct'))/$totalRecs):0;
$avgTajweed=$totalRecs>0?round(array_sum(array_column($progress,'tajweed_level'))/$totalRecs,1):0;
$attPct=$attStats['total']>0?round($attStats['present']/$attStats['total']*100):0;

// Colors by slot
$slotColor=$isChild?'#D97706':'#4F46E5';
$slotBg=$isChild?'#FEF3C7':'#EEF2FF';
$slotTxt=$isChild?'#78350F':'#1E3A8A';
$slotGrad=$isChild?'linear-gradient(135deg,#78350F,#D97706)':'linear-gradient(135deg,#1E3A8A,#4F46E5)';

$evalBg=['Excellent'=>'#D1FAE5','Good'=>'#DBEAFE','Needs Improvement'=>'#FEF3C7','Repeat'=>'#FEE2E2'];
$evalTc=['Excellent'=>'#065F46','Good'=>'#1D4ED8','Needs Improvement'=>'#92400E','Repeat'=>'#991B1B'];

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content" style="max-width:1000px">

<!-- Back -->
<a href="javascript:history.back()" style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--gray-400);text-decoration:none;margin-bottom:16px">
  ← <?=$isRtl?'رجوع':'Back'?>
</a>

<?php $flash=getFlash(); if($flash):?><div class="alert alert-<?=$flash['type']?>" style="margin-bottom:14px"><?=h($flash['msg'])?></div><?php endif;?>

<!-- ══ HEADER CARD ══ -->
<div style="background:<?=$slotGrad?>;border-radius:16px;padding:22px 24px;margin-bottom:16px;color:#fff">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px">
    <div style="display:flex;align-items:center;gap:16px">
      <!-- Avatar -->
      <div style="width:56px;height:56px;border-radius:14px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.5rem;flex-shrink:0;border:2px solid rgba(255,255,255,.3)">
        <?=strtoupper(substr($student['full_name'],0,1))?>
      </div>
      <div>
        <h1 style="font-size:1.2rem;font-weight:800;color:#fff;margin:0"><?=h($student['full_name'])?></h1>
        <?php if($student['full_name_ar']):?><div style="font-size:12px;opacity:.8;margin-top:1px"><?=h($student['full_name_ar'])?></div><?php endif;?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
          <span style="background:rgba(255,255,255,.18);padding:2px 10px;border-radius:99px;font-size:11px">🕌 <?=h($student['mosque_name'])?></span>
          <?php if($activeCourse):?>
          <span style="background:rgba(255,255,255,.18);padding:2px 10px;border-radius:99px;font-size:11px"><?=$activeCourse['icon']?> <?=h($isRtl?$activeCourse['name_ar']:$activeCourse['name_en'])?></span>
          <?php endif;?>
          <span style="background:rgba(255,255,255,.18);padding:2px 10px;border-radius:99px;font-size:11px"><?=$isChild?'👶 Child':'🎓 Student'?></span>
        </div>
      </div>
    </div>
    <!-- Actions -->
    <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end">
      <?php if($activeCourse && $role==='teacher'):?>
      <a href="/teacher/course.php?id=<?=$activeCourse['course_id']?>&prog=<?=$activeCourse['prog_id']?>&tab=progress&student=<?=$studentId?>"
         style="background:rgba(255,255,255,.2);color:#fff;padding:7px 14px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:600;border:1px solid rgba(255,255,255,.3);white-space:nowrap">
        📈 <?=$isRtl?'تحديث التقدم':'Update Progress'?>
      </a>
      <a href="/teacher/course.php?id=<?=$activeCourse['course_id']?>&prog=<?=$activeCourse['prog_id']?>&tab=attendance"
         style="background:rgba(255,255,255,.2);color:#fff;padding:7px 14px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:600;border:1px solid rgba(255,255,255,.3);white-space:nowrap">
        ✅ <?=$isRtl?'تسجيل الحضور':'Mark Attendance'?>
      </a>
      <?php endif;?>
      <form method="POST" onsubmit="return confirm('<?=$isRtl?'⚠️ سيتم تعطيل الطالب وإلغاء تسجيله. بياناته ستبقى محفوظة. هل أنت متأكد؟':'⚠️ Student will be deactivated. All records preserved. Confirm?'?>
        <?= csrfField() ?>')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?=$studentId?>">
        <button type="submit" style="background:rgba(220,38,38,.25);color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:8px;padding:7px 14px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap">
          🚫 <?=$isRtl?'تعطيل الطالب':'Deactivate'?>
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ══ STATS ROW ══ -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px">
  <?php foreach([
    ['📊',$avgPct.'%',$isRtl?'متوسط التقدم':'Avg Progress','#EEF2FF','#4F46E5'],
    ['✅',$attPct.'%',$isRtl?'نسبة الحضور':'Attendance','#F0FDF4','#059669'],
    ['⭐',$avgTajweed,$isRtl?'متوسط التجويد':'Avg Tajweed','#FFFBEB','#D97706'],
    ['📅',$attStats['total']??0,$isRtl?'إجمالي الجلسات':'Total Sessions','#F5F3FF','#7C3AED'],
  ] as [$icon,$val,$label,$bg,$color]):?>
  <div style="background:<?=$bg?>;border-radius:12px;padding:14px;text-align:center;border:1px solid <?=$bg?>">
    <div style="font-size:1.2rem;margin-bottom:4px"><?=$icon?></div>
    <div style="font-size:1.4rem;font-weight:800;color:<?=$color?>"><?=$val?></div>
    <div style="font-size:10px;color:var(--gray-500);margin-top:2px"><?=$label?></div>
  </div>
  <?php endforeach;?>
</div>

<!-- ══ THIS WEEK'S LESSON ══ -->
<?php if($activeCourse && ($activeCourse['topic_en']||$activeCourse['topic_ar'])):?>
<div style="background:#fff;border:2px solid #86EFAC;border-radius:14px;padding:18px;margin-bottom:16px">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
    <span style="font-size:1.3rem"><?=$activeCourse['icon']?></span>
    <div>
      <div style="font-size:11px;font-weight:700;color:#059669;text-transform:uppercase">🟢 <?=$isRtl?'درس هذا الأسبوع':'This Week\'s Lesson'?></div>
      <div style="font-weight:800;font-size:14px;color:#111"><?=h($isRtl?$activeCourse['topic_ar']:$activeCourse['topic_en'])?></div>
    </div>
    <div style="margin-right:auto;font-size:11px;color:var(--gray-400)"><?=date('d M',strtotime($activeCourse['week_start']))?> – <?=date('d M',strtotime($activeCourse['week_end']))?></div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px">
    <?php if($activeCourse['surah_name_ar']):?>
    <div style="background:#F0FDF4;border-radius:8px;padding:10px;border:1px solid #BBF7D0">
      <div style="font-size:10px;color:#059669;font-weight:700;margin-bottom:2px">📖 <?=$isRtl?'السورة':'Surah'?></div>
      <div style="font-weight:600;font-size:13px"><?=h($activeCourse['surah_name_ar'])?><?=$activeCourse['ayah_from']?' (آية '.$activeCourse['ayah_from'].'–'.$activeCourse['ayah_to'].')':''?></div>
    </div>
    <?php endif;?>
    <?php if($activeCourse['objectives']):?>
    <div style="background:#EFF6FF;border-radius:8px;padding:10px;border:1px solid #BFDBFE">
      <div style="font-size:10px;color:#1D4ED8;font-weight:700;margin-bottom:2px">🎯 <?=$isRtl?'الأهداف':'Objectives'?></div>
      <div style="font-size:12px"><?=h($activeCourse['objectives'])?></div>
    </div>
    <?php endif;?>
    <?php if($activeCourse['homework']):?>
    <div style="background:#FFFBEB;border-radius:8px;padding:10px;border:2px solid #FDE68A">
      <div style="font-size:10px;color:#D97706;font-weight:700;margin-bottom:2px">📋 <?=$isRtl?'الواجب':'Homework'?></div>
      <div style="font-weight:600;font-size:13px;color:#92400E"><?=h($activeCourse['homework'])?></div>
    </div>
    <?php endif;?>
    <?php if($activeCourse['lesson_notes']):?>
    <div style="background:#F9FAFB;border-radius:8px;padding:10px">
      <div style="font-size:10px;color:var(--gray-400);font-weight:700;margin-bottom:2px">💬 <?=$isRtl?'ملاحظات':'Notes'?></div>
      <div style="font-size:12px"><?=h($activeCourse['lesson_notes'])?></div>
    </div>
    <?php endif;?>
  </div>
</div>
<?php elseif($activeCourse):?>
<div style="background:#F9FAFB;border:1px dashed #E5E7EB;border-radius:12px;padding:14px;margin-bottom:16px;display:flex;align-items:center;gap:10px;color:var(--gray-400)">
  <span style="font-size:1.5rem"><?=$activeCourse['icon']?></span>
  <div>
    <div style="font-weight:600;font-size:13px;color:#111"><?=h($isRtl?$activeCourse['name_ar']:$activeCourse['name_en'])?></div>
    <div style="font-size:12px"><?=$isRtl?'لم يُضف المعلم درس هذا الأسبوع بعد':'No lesson added for this week yet'?></div>
  </div>
</div>
<?php endif;?>

<!-- ══ MAIN GRID: Progress + Attendance ══ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px">

  <!-- Progress Records -->
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:14px;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center">
      <div>
        <div style="font-weight:700;font-size:13px;color:#111">
          <?=$activeCourse?$activeCourse['icon']:'📖'?> <?=$isRtl?'سجلات التقدم':'Progress Records'?>
        </div>
        <div style="font-size:10px;color:var(--gray-400);margin-top:1px"><?=$totalRecs?> <?=$isRtl?'سجل':'records'?> · <?=$doneRecs?> ✅ <?=$isRtl?'مكتمل':'done'?></div>
      </div>
      <?php if($activeCourse && $role==='teacher'):?>
      <a href="/teacher/course.php?id=<?=$activeCourse['course_id']?>&prog=<?=$activeCourse['prog_id']?>&tab=progress&student=<?=$studentId?>"
         style="background:<?=$slotBg?>;color:<?=$slotTxt?>;padding:5px 10px;border-radius:8px;text-decoration:none;font-size:11px;font-weight:700">
        + <?=$isRtl?'إضافة':'Add'?>
      </a>
      <?php endif;?>
    </div>
    <div style="padding:12px 16px;max-height:340px;overflow-y:auto">
    <?php if(empty($progress)):?>
    <div style="text-align:center;padding:30px;color:var(--gray-300)">
      <div style="font-size:2rem">📖</div>
      <div style="margin-top:8px;font-size:12px"><?=$isRtl?'لا يوجد تقدم مسجّل بعد':'No progress recorded yet'?></div>
    </div>
    <?php else: foreach($progress as $p):
      $pct=(int)$p['memorization_pct'];
      $barColor=$pct===100?'#059669':($pct>=70?'#10B981':($pct>=40?'#F59E0B':'#EF4444'));
    ?>
    <div style="padding:10px;border:1px solid #F3F4F6;border-radius:10px;margin-bottom:6px;background:<?=$pct===100?'#F0FDF4':'#FAFAFA'?>">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px">
        <div>
          <?php if($p['surah_name_ar']):?>
          <div style="font-weight:700;font-size:13px;color:#111"><?=h($p['surah_name_ar'])?></div>
          <div style="font-size:10px;color:var(--gray-400)"><?=h($p['surah_name_en'])?><?=$p['ayah_from']?' · آية '.$p['ayah_from'].'–'.$p['ayah_to']:''?></div>
          <?php else:?>
          <div style="font-weight:600;font-size:12px;color:#111">
            <?=$p['notes']?h(substr($p['notes'],0,40)):($isRtl?'سجل تقدم':'Progress Entry')?>
          </div>
          <?php endif;?>
        </div>
        <span style="background:<?=$evalBg[$p['evaluation']]??'#F3F4F6'?>;color:<?=$evalTc[$p['evaluation']]??'#374151'?>;padding:1px 8px;border-radius:99px;font-size:10px;font-weight:700;flex-shrink:0;margin-right:4px"><?=$p['evaluation']?></span>
      </div>
      <!-- Progress bar -->
      <div style="height:5px;background:#E5E7EB;border-radius:3px;overflow:hidden;margin-bottom:5px">
        <div style="height:100%;width:<?=$pct?>%;background:<?=$barColor?>;border-radius:3px"></div>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div style="font-size:11px"><?=str_repeat('⭐',(int)$p['tajweed_level'])?><?=str_repeat('☆',max(0,5-(int)$p['tajweed_level']))?></div>
        <div style="font-size:11px;font-weight:700;color:<?=$barColor?>"><?=$pct?>%</div>
      </div>
      <div style="font-size:10px;color:var(--gray-400);margin-top:3px"><?=date('d M Y',strtotime($p['updated_at']))?></div>
    </div>
    <?php endforeach; endif;?>
    </div>
  </div>

  <!-- Attendance -->
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:14px;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #F3F4F6">
      <div style="font-weight:700;font-size:13px;color:#111">✅ <?=$isRtl?'سجل الحضور':'Attendance Record'?></div>
      <div style="font-size:10px;color:var(--gray-400);margin-top:1px"><?=$isRtl?'آخر 30 يوم':'Last 30 days'?></div>
    </div>
    <div style="padding:14px 18px">
      <!-- Stats pills -->
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:14px">
        <?php foreach([
          ['✅',$attStats['present']??0,'Present','#F0FDF4','#059669'],
          ['❌',$attStats['absent']??0,'Absent','#FEF2F2','#DC2626'],
          ['⏰',$attStats['late']??0,'Late','#FFFBEB','#D97706'],
          ['📋',$attStats['excused']??0,'Excused','#EFF6FF','#2563EB'],
        ] as [$icon,$val,$label,$bg,$color]):?>
        <div style="background:<?=$bg?>;border-radius:8px;padding:8px 4px;text-align:center">
          <div style="font-size:14px"><?=$icon?></div>
          <div style="font-size:1.1rem;font-weight:800;color:<?=$color?>"><?=$val?></div>
          <div style="font-size:9px;color:var(--gray-400)"><?=$label?></div>
        </div>
        <?php endforeach;?>
      </div>
      <!-- Attendance rate bar -->
      <div style="margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:4px">
          <span style="font-weight:600;color:#111"><?=$isRtl?'نسبة الحضور':'Attendance Rate'?></span>
          <span style="font-weight:700;color:<?=$attPct>=80?'#059669':($attPct>=60?'#D97706':'#DC2626')?>"><?=$attPct?>%</span>
        </div>
        <div style="height:8px;background:#E5E7EB;border-radius:4px;overflow:hidden">
          <div style="height:100%;width:<?=$attPct?>%;background:<?=$attPct>=80?'#059669':($attPct>=60?'#F59E0B':'#EF4444')?>;border-radius:4px;transition:width .5s"></div>
        </div>
      </div>
      <!-- 30-day mini calendar -->
      <div style="font-size:11px;font-weight:600;color:var(--gray-400);margin-bottom:8px"><?=$isRtl?'آخر 30 يوم':'Last 30 days'?></div>
      <div style="display:flex;flex-wrap:wrap;gap:3px">
        <?php
        $attColors=['present'=>'#059669','absent'=>'#DC2626','late'=>'#D97706','excused'=>'#2563EB'];
        for($i=29;$i>=0;$i--){
          $d=date('Y-m-d',strtotime("-$i days"));
          $st=$attByDate[$d]??null;
          $isToday=$d===date('Y-m-d');
          $bg=$st?($attColors[$st]??'#E5E7EB'):'#F3F4F6';
          $border=$isToday?'2px solid #4F46E5':'1px solid transparent';
        ?>
        <div style="width:20px;height:20px;border-radius:4px;background:<?=$bg?>;border:<?=$border?>;display:flex;align-items:center;justify-content:center;font-size:8px;color:#fff;font-weight:700" title="<?=$d?><?=$st?' — '.ucfirst($st):''?>">
          <?=(int)date('d',strtotime($d))?>
        </div>
        <?php } ?>
      </div>
      <!-- Legend -->
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px">
        <?php foreach(['present'=>'#059669','absent'=>'#DC2626','late'=>'#D97706','excused'=>'#2563EB'] as $s=>$c):?>
        <span style="display:flex;align-items:center;gap:3px;font-size:10px;color:var(--gray-500)"><span style="width:10px;height:10px;border-radius:2px;background:<?=$c?>;display:inline-block"></span><?=ucfirst($s)?></span>
        <?php endforeach;?>
      </div>
    </div>
  </div>
</div>

<!-- ══ RECENT LESSONS ══ -->
<?php if(!empty($recentLessons)):?>
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:14px;overflow:hidden">
  <div style="padding:14px 18px;border-bottom:1px solid #F3F4F6">
    <div style="font-weight:700;font-size:13px;color:#111">📅 <?=$isRtl?'آخر الدروس الأسبوعية':'Recent Weekly Lessons'?></div>
  </div>
  <div style="padding:12px 16px;display:flex;flex-direction:column;gap:6px">
  <?php foreach($recentLessons as $wl):
    $isTW=date('Y-m-d')>=$wl['week_start']&&date('Y-m-d')<=$wl['week_end'];
    $sc=['planned'=>['#FFFBEB','#D97706','⏳'],'in_progress'=>['#EFF6FF','#2563EB','🔵'],'completed'=>['#F0FDF4','#059669','✅']];
    $s=$sc[$wl['status']]??['#F9FAFB','#6B7280','?'];
  ?>
  <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 12px;border-radius:10px;border:1px solid <?=$isTW?'#86EFAC':'#F3F4F6'?>;background:<?=$isTW?'#F0FDF4':'#FAFAFA'?>">
    <span style="background:<?=$s[0]?>;color:<?=$s[1]?>;padding:4px 8px;border-radius:6px;font-size:11px;font-weight:700;flex-shrink:0"><?=$s[2]?></span>
    <div style="flex:1;min-width:0">
      <div style="font-weight:700;font-size:13px;color:#111"><?=h($isRtl?$wl['topic_ar']:$wl['topic_en'])?></div>
      <div style="font-size:11px;color:var(--gray-400);margin-top:2px"><?=date('d M',strtotime($wl['week_start']))?> – <?=date('d M Y',strtotime($wl['week_end']))?><?=$isTW?' 🟢 '.($isRtl?'هذا الأسبوع':'This week'):''?></div>
      <?php if($wl['surah_name_ar']):?><div style="font-size:11px;color:var(--gray-600);margin-top:2px">📖 <?=h($wl['surah_name_ar'])?><?=$wl['ayah_from']?' (آية '.$wl['ayah_from'].'–'.$wl['ayah_to'].')':''?></div><?php endif;?>
      <?php if($wl['homework']):?><div style="font-size:11px;color:#D97706;margin-top:2px">📋 <?=h($wl['homework'])?></div><?php endif;?>
    </div>
  </div>
  <?php endforeach;?>
  </div>
</div>
<?php endif;?>

</main>
</div>
<?php include '../includes/footer.php'; ?>
