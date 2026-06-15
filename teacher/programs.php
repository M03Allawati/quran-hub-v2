<?php
require_once dirname(__DIR__) . '/config.php';
requireRole('teacher');
$lang=$_SESSION['lang']??'en'; $isRtl=$lang==='ar';
$pdo=getPDO(); $userId=$_SESSION['user_id'];
$pageTitle=($isRtl?'برامجي — ':'My Programs — ').APP_NAME;
$userGov=$_SESSION['user']['governorate']??'';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action=$_POST['action']??'';
    if($action==='apply'){
        $progId=(int)$_POST['program_id'];
        $prog=$pdo->prepare("SELECT * FROM mosque_programs WHERE id=? AND teacher_id IS NULL AND is_active=1");
        $prog->execute([$progId]); $prog=$prog->fetch();
        if($prog){
            $pdo->prepare("UPDATE mosque_programs SET teacher_id=? WHERE id=?")->execute([$userId,$progId]);
            $cls=$pdo->prepare("SELECT id FROM classes WHERE mosque_id=? AND slot=? AND is_active=1 LIMIT 1");
            $cls->execute([$prog['mosque_id'],$prog['slot']]); $cls=$cls->fetch();
            if($cls){ $pdo->prepare("UPDATE classes SET teacher_id=? WHERE id=?")->execute([$userId,$cls['id']]); }
            else { $pdo->prepare("INSERT INTO classes (name_en,name_ar,subject,level,mosque_id,teacher_id,slot,schedule_day,time_start,time_end,max_students,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,1)")->execute([$prog['name_en'],$prog['name_ar'],'Quran Memorization','Beginner',$prog['mosque_id'],$userId,$prog['slot'],$prog['slot']==='A'?'Sunday':'Monday',$prog['time_start'],$prog['time_end'],$prog['max_students']]); }
            setFlash('success','✅ Assigned directly!');
        } else { setFlash('danger','Slot not available'); }
        header('Location: /teacher/programs.php?tab=mine'); exit;
    }
    if($action==='withdraw'){ $pdo->prepare("DELETE FROM teacher_program_applications WHERE teacher_id=? AND program_id=? AND status='pending'")->execute([$userId,(int)$_POST['program_id']]); setFlash('info','Withdrawn'); header('Location: /teacher/programs.php?tab=apply'); exit; }
    if($action==='save_lesson'){
        $progId=(int)$_POST['prog_id'];
        $type=$_POST['lesson_type']??'weekly';
        $startDate=$_POST['start_date'];
        $endDate=$type==='weekly'?date('Y-m-d',strtotime($startDate.' +6 days')):$_POST['end_date'];
        $levelId=(int)($_POST['level_id']??0)?:null;
        $surahNum=(int)($_POST['surah_number']??0)?:null;
        $sn=['الفاتحة'=>1,'البقرة'=>2];
        $allSurahs=[[1,'Al-Fatihah','الفاتحة'],[2,'Al-Baqarah','البقرة'],[3,'Ali Imran','آل عمران'],[36,'Ya-Sin','يس'],[55,'Ar-Rahman','الرحمن'],[56,'Al-Waqiah','الواقعة'],[67,'Al-Mulk','الملك'],[78,'An-Naba','النبأ'],[87,'Al-Ala','الأعلى'],[93,'Ad-Duha','الضحى'],[94,'Ash-Sharh','الشرح'],[108,'Al-Kawthar','الكوثر'],[112,'Al-Ikhlas','الإخلاص'],[113,'Al-Falaq','الفلق'],[114,'An-Nas','الناس']];
        $surahNameEn=$surahNameAr='';
        foreach($allSurahs as $s){ if($s[0]==$surahNum){$surahNameEn=$s[1];$surahNameAr=$s[2];break;} }
        if($type==='weekly'){
            $pdo->prepare("INSERT INTO weekly_lessons (program_id,teacher_id,level_id,week_start,week_end,surah_number,surah_name_en,surah_name_ar,ayah_from,ayah_to,topic_en,topic_ar,objectives,notes,homework,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'planned')")->execute([$progId,$userId,$levelId,$startDate,$endDate,$surahNum,$surahNameEn,$surahNameAr,(int)($_POST['ayah_from']??0)?:null,(int)($_POST['ayah_to']??0)?:null,$_POST['topic_en']??'',$_POST['topic_ar']??'',$_POST['objectives']??'',$_POST['notes']??'',$_POST['homework']??'']);
        } else {
            $pdo->prepare("INSERT INTO lesson_plans (program_id,teacher_id,level_id,plan_type,start_date,end_date,title_en,title_ar,description,status) VALUES (?,?,?,'monthly',?,?,?,?,?,'active')")->execute([$progId,$userId,$levelId,$startDate,$endDate,$_POST['topic_en']??'',$_POST['topic_ar']??'',$_POST['notes']??'']);
        }
        setFlash('success','✅ Lesson saved');
        header("Location: /teacher/programs.php?tab=mine&prog=$progId"); exit;
    }
    if($action==='update_lesson_status'){
    $allowedStatus = ['planned','completed','cancelled'];
    if (!in_array($_POST['status'] ?? '', $allowedStatus)) { setFlash('danger','Invalid status'); header('Location: /teacher/programs.php'); exit; } $pdo->prepare("UPDATE weekly_lessons SET status=? WHERE id=? AND teacher_id=?")->execute([$_POST['status'],(int)$_POST['lesson_id'],$userId]); header('Location: /teacher/programs.php'); exit; }
    if($action==='delete_lesson'){ $pdo->prepare("DELETE FROM weekly_lessons WHERE id=? AND teacher_id=?")->execute([(int)$_POST['lesson_id'],$userId]); header('Location: /teacher/programs.php'); exit; }
    if($action==='accept_private'){ $reqId=(int)$_POST['req_id']; $pdo->prepare("UPDATE private_program_requests SET status='accepted',teacher_id=? WHERE id=?")->execute([$userId,$reqId]); $req=$pdo->prepare("SELECT ppr.*,s.full_name as child_name FROM private_program_requests ppr JOIN students s ON s.id=ppr.student_id WHERE ppr.id=?"); $req->execute([$reqId]); $req=$req->fetch(); if($req){ $pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'success')")->execute([$req['parent_id'],'Private Program Accepted!',"Your request for {$req['child_name']} has been accepted."]); } setFlash('success','✅ Accepted!'); header('Location: /teacher/programs.php?tab=private'); exit; }
    if($action==='reject_private'){ $pdo->prepare("UPDATE private_program_requests SET status='rejected' WHERE id=?")->execute([(int)$_POST['req_id']]); setFlash('info','Rejected'); header('Location: /teacher/programs.php?tab=private'); exit; }
}

$tab=$_GET['tab']??'mine';
$selectedProg=(int)($_GET['prog']??0);

$myProgs=$pdo->prepare("SELECT mp.*,m.name_en as mosque_en,m.name_ar as mosque_ar,m.governorate,(SELECT COUNT(DISTINCT pe.student_id) FROM program_enrollments pe WHERE pe.program_id=mp.id AND pe.status='active') as enrolled FROM mosque_programs mp JOIN mosques m ON m.id=mp.mosque_id WHERE mp.teacher_id=? AND mp.is_active=1 ORDER BY mp.slot");
$myProgs->execute([$userId]); $myProgs=$myProgs->fetchAll();
if(!$selectedProg && !empty($myProgs)) $selectedProg=$myProgs[0]['id'];
$selProg=null; foreach($myProgs as $p){ if($p['id']==$selectedProg){$selProg=$p;break;} }

$progStudents=[];
if($selectedProg){
    $st=$pdo->prepare("SELECT s.*,
        (SELECT COUNT(*) FROM attendance a WHERE a.student_id=s.id AND a.status='present') as present_cnt,
        (SELECT COUNT(*) FROM attendance a WHERE a.student_id=s.id) as total_att,
        (SELECT COUNT(*) FROM progress p WHERE p.student_id=s.id AND p.memorization_pct=100) as surahs_done,
        (SELECT AVG(p2.memorization_pct) FROM progress p2 WHERE p2.student_id=s.id) as avg_pct
        FROM program_enrollments pe JOIN students s ON s.id=pe.student_id
        WHERE pe.program_id=? AND pe.status='active' ORDER BY s.full_name");
    $st->execute([$selectedProg]); $progStudents=$st->fetchAll();
}
$isChild=($selProg['target_type']??'student')==='child';

// Fixed courses for this slot
$fixedCourses=[];
if($selProg){
    $fc=$pdo->prepare("SELECT fc.*,tcs.id as is_selected FROM fixed_courses fc LEFT JOIN teacher_course_selections tcs ON tcs.course_id=fc.id AND tcs.teacher_id=? AND tcs.program_id=? AND tcs.is_active=1 WHERE fc.slot=? AND fc.is_active=1 ORDER BY fc.sort_order");
    $fc->execute([$userId,$selectedProg,$selProg['slot']]); $fixedCourses=$fc->fetchAll();
}

$weeklyLessons=[];$monthlyPlans=[];
if($selectedProg){
    $wl=$pdo->prepare("SELECT wl.*,cl.name_en as level_name,cl.name_ar as level_name_ar FROM weekly_lessons wl LEFT JOIN course_levels cl ON cl.id=wl.level_id WHERE wl.program_id=? AND wl.teacher_id=? ORDER BY wl.week_start DESC LIMIT 12");
    $wl->execute([$selectedProg,$userId]); $weeklyLessons=$wl->fetchAll();
    $mp=$pdo->prepare("SELECT lp.*,cl.name_en as level_name FROM lesson_plans lp LEFT JOIN course_levels cl ON cl.id=lp.level_id WHERE lp.program_id=? AND lp.teacher_id=? ORDER BY lp.start_date DESC LIMIT 6");
    $mp->execute([$selectedProg,$userId]); $monthlyPlans=$mp->fetchAll();
}

$levels=[];
if($selProg){ $lv=$pdo->prepare("SELECT * FROM course_levels WHERE program_type=? ORDER BY level_number"); $lv->execute([$selProg['program_type']]); $levels=$lv->fetchAll(); }

$myStudents=[];
if($myProgs){ $pids=array_column($myProgs,'id'); $ph=implode(',',array_fill(0,count($pids),'?')); $st=$pdo->prepare("SELECT s.*,pe.program_id,mp.name_en as prog_en,mp.slot,(SELECT COUNT(*) FROM attendance a WHERE a.student_id=s.id AND a.status='present') as present_cnt,(SELECT COUNT(*) FROM attendance a WHERE a.student_id=s.id) as total_att,(SELECT COUNT(*) FROM progress p WHERE p.student_id=s.id AND p.memorization_pct=100) as surahs_done FROM program_enrollments pe JOIN students s ON s.id=pe.student_id JOIN mosque_programs mp ON mp.id=pe.program_id WHERE pe.program_id IN ($ph) AND pe.status='active' ORDER BY mp.slot,s.full_name"); $st->execute($pids); $myStudents=$st->fetchAll(); }

$availWhere=$userGov?"AND m.governorate=?":""; $availParams=$userGov?[$userGov]:[];
$avail=$pdo->prepare("SELECT mp.*,m.name_en as mosque_en,m.name_ar as mosque_ar,m.governorate,(SELECT COUNT(*) FROM program_enrollments pe WHERE pe.program_id=mp.id AND pe.status='active') as enrolled FROM mosque_programs mp JOIN mosques m ON m.id=mp.mosque_id WHERE mp.teacher_id IS NULL AND mp.is_active=1 $availWhere ORDER BY m.governorate,mp.slot");
$avail->execute($availParams); $availProgs=$avail->fetchAll();

$privateReqs=$pdo->prepare("SELECT ppr.*,s.full_name as child_name,s.date_of_birth,u.full_name as parent_name,u.phone as parent_phone,m.name_en as mosque_en FROM private_program_requests ppr JOIN students s ON s.id=ppr.student_id JOIN users u ON u.id=ppr.parent_id JOIN mosques m ON m.id=ppr.mosque_id WHERE (ppr.teacher_id=? OR ppr.teacher_id IS NULL) AND ppr.status='pending' AND m.governorate=? ORDER BY ppr.created_at ASC");
$privateReqs->execute([$userId,$userGov]); $privateReqs=$privateReqs->fetchAll();

$statusColors=['planned'=>['#FEF3C7','#92400E','⏳'],'in_progress'=>['#DBEAFE','#1D4ED8','🔵'],'completed'=>['#D1FAE5','#065F46','✅']];
$slotColors=['A'=>['#DBEAFE','#1D4ED8','🎓'],'B'=>['#FEF3C7','#92400E','👶']];
$allSurahs=[[1,'Al-Fatihah','الفاتحة'],[2,'Al-Baqarah','البقرة'],[3,'Ali Imran','آل عمران'],[36,'Ya-Sin','يس'],[55,'Ar-Rahman','الرحمن'],[56,'Al-Waqiah','الواقعة'],[67,'Al-Mulk','الملك'],[78,'An-Naba','النبأ'],[87,'Al-Ala','الأعلى'],[93,'Ad-Duha','الضحى'],[94,'Ash-Sharh','الشرح'],[108,'Al-Kawthar','الكوثر'],[112,'Al-Ikhlas','الإخلاص'],[113,'Al-Falaq','الفلق'],[114,'An-Nas','الناس']];

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<h1 style="font-size:1.5rem;font-weight:800;margin:0 0 4px">📚 <?=$isRtl?'برامجي':'Quran Programs'?></h1>
<p style="color:var(--gray-500);font-size:13px;margin:0 0 20px">📍 <?=h($userGov?:($isRtl?'غير محدد':'Not set'))?></p>

<div style="display:flex;background:var(--gray-50);border-radius:12px;padding:4px;margin-bottom:20px;width:fit-content;flex-wrap:wrap;gap:2px">
  <?php foreach([['mine','📋 '.($isRtl?'برامجي':'My Programs'),count($myProgs)],['students','👥 '.($isRtl?'طلابي':'My Students'),count($myStudents)],['apply','➕ '.($isRtl?'تقدّم':'Apply'),count($availProgs)],['private','🌟 '.($isRtl?'خاص':'Private'),count($privateReqs)]] as [$k,$l,$cnt]):?>
  <a href="?tab=<?=$k?>" style="padding:8px 14px;border-radius:10px;font-size:12px;text-decoration:none;background:<?=$tab===$k?'#fff':'transparent'?>;font-weight:<?=$tab===$k?700:400?>;color:<?=$tab===$k?'var(--green-dark)':'var(--gray-500)'?>">
    <?=$l?> <span style="background:<?=$tab===$k?'var(--green-pale)':'#e5e7eb'?>;color:<?=$tab===$k?'var(--green-dark)':'#6b7280'?>;padding:1px 6px;border-radius:99px;font-size:10px"><?=$cnt?></span>
  </a>
  <?php endforeach;?>
</div>

<?php if($tab==='mine'): ?>
<?php if(empty($myProgs)):?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:60px;text-align:center">
  <div style="font-size:3rem">📚</div>
  <div style="margin-top:12px;font-size:15px;font-weight:600;color:var(--gray-500)"><?=$isRtl?'لم يتم تعيينك في برنامج':'Not assigned to any program'?></div>
  <a href="?tab=apply" class="btn btn-primary" style="margin-top:16px;display:inline-block">➕ Apply</a>
</div>
<?php else:?>
<div style="max-width:860px">
<?php if($selProg):?>
<?php
$isChildP=$selProg['target_type']==='child';
$slotGradP=$isChildP?'linear-gradient(135deg,#78350F,#D97706)':'linear-gradient(135deg,#1E3A8A,#4F46E5)';
$slotColorP=$isChildP?'#D97706':'#4F46E5';
$slotBgP=$isChildP?'#FEF3C7':'#EEF2FF';
$slotTxtP=$isChildP?'#78350F':'#1E3A8A';
?>
<div>

  <!-- ① Program Header Card -->
  <div style="background:<?=$slotGradP?>;border-radius:16px;padding:0;margin-bottom:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.12)">
    <!-- Top strip -->
    <div style="padding:16px 20px 14px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
        <div>
          <div style="font-size:10px;font-weight:700;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px">
            <?=$isChildP?'👶 Children Program':'🎓 Students Program'?> · Slot <?=$selProg['slot']?>
          </div>
          <div style="font-weight:800;font-size:1.1rem;color:#fff;line-height:1.2"><?=h($isRtl?$selProg['mosque_ar']:$selProg['mosque_en'])?></div>
          <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:3px">
            📅 <?=str_replace(',',' · ',$selProg['days'])?>  ·  ⏰ <?=substr($selProg['time_start'],0,5)?>–<?=substr($selProg['time_end'],0,5)?>
          </div>
        </div>
        <div style="text-align:right">
          <div style="background:rgba(255,255,255,.15);border-radius:10px;padding:8px 14px;display:inline-block">
            <div style="font-size:1.4rem;font-weight:800;color:#fff"><?=$selProg['enrolled']?></div>
            <div style="font-size:10px;color:rgba(255,255,255,.7)"><?=$isChildP?'Children':'Students'?></div>
          </div>
        </div>
      </div>
    </div>
    <!-- Bottom action bar -->
    <div style="background:rgba(0,0,0,.15);padding:10px 20px;display:flex;gap:8px;flex-wrap:wrap">
      <?php
      // Find active course for quick link
      $activeForProg=null;
      foreach($fixedCourses as $fc){ if($fc['is_selected']){$activeForProg=$fc;break;} }
      ?>
      <?php if($activeForProg):?>
      <a href="/teacher/course.php?id=<?=$activeForProg['id']?>&prog=<?=$selProg['id']?>&tab=attendance"
         style="display:flex;align-items:center;gap:6px;background:rgba(255,255,255,.18);color:#fff;padding:6px 14px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:600;border:1px solid rgba(255,255,255,.25)">
        ✅ <?=$isRtl?'تسجيل الحضور':'Mark Attendance'?>
      </a>
      <a href="/teacher/course.php?id=<?=$activeForProg['id']?>&prog=<?=$selProg['id']?>&tab=progress"
         style="display:flex;align-items:center;gap:6px;background:rgba(255,255,255,.18);color:#fff;padding:6px 14px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:600;border:1px solid rgba(255,255,255,.25)">
        📈 <?=$isRtl?'تحديث التقدم':'Update Progress'?>
      </a>
      <a href="/teacher/course.php?id=<?=$activeForProg['id']?>&prog=<?=$selProg['id']?>&tab=lessons"
         style="display:flex;align-items:center;gap:6px;background:rgba(255,255,255,.18);color:#fff;padding:6px 14px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:600;border:1px solid rgba(255,255,255,.25)">
        📅 <?=$isRtl?'إضافة درس':'Add Lesson'?>
      </a>
      <?php else:?>
      <span style="color:rgba(255,255,255,.6);font-size:12px">⚠️ <?=$isRtl?'اختر دورة من الكورسات أدناه':'Select a course below to get started'?></span>
      <?php endif;?>
    </div>
  </div>

  <?php if(!empty($progStudents)):?>
  <div style="background:#fff;border:1px solid var(--gray-100);border-radius:12px;padding:16px;margin-bottom:14px">
    <div style="font-weight:700;font-size:13px;color:var(--green-dark);margin-bottom:10px">👥 Students (<?=count($progStudents)?>)</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px">
      <?php foreach($progStudents as $st): $attPct=$st['total_att']>0?round($st['present_cnt']/$st['total_att']*100):0;?>
      <a href="/teacher/student_profile.php?id=<?=$st['id']?>" style="background:var(--gray-50);border-radius:10px;padding:10px;text-decoration:none;border:1px solid var(--gray-100)">
        <div style="display:flex;align-items:center;gap:7px;margin-bottom:6px">
          <div style="width:30px;height:30px;border-radius:50%;background:#4C1D95;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0"><?=strtoupper(substr($st['full_name'],0,1))?></div>
          <div><div style="font-weight:600;font-size:12px;color:var(--green-dark)"><?=h($st['full_name'])?></div><div style="font-size:10px;color:var(--gray-400)"><?=$st['surahs_done']?> surahs ✅</div></div>
        </div>
        <div style="height:4px;background:var(--gray-200);border-radius:2px;overflow:hidden"><div style="height:100%;width:<?=$attPct?>%;background:<?=$attPct>=80?'var(--success)':'var(--warning)'?>;border-radius:2px"></div></div>
        <div style="font-size:10px;color:var(--gray-400);margin-top:2px"><?=$attPct?>% attend</div>
      </a>
      <?php endforeach;?>
    </div>
  </div>
  <?php endif;?>

  <!-- Fixed Courses shown in left panel only -->
  <!-- Lessons & Progress are managed inside each Course page -->

  <!-- Students Progress Overview Table -->
  <!-- ② Students / Children Cards -->
  <?php if(!empty($progStudents)):?>
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:14px;margin-bottom:14px;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center">
      <div style="font-weight:700;font-size:13px;color:#111">
        <?=$isChildP?'👶':'🎓'?> <?=$isChildP?($isRtl?'الأطفال المسجّلون':'Enrolled Children'):($isRtl?'الطلاب المسجّلون':'Enrolled Students')?>
        <span style="background:<?=$slotBgP?>;color:<?=$slotTxtP?>;padding:1px 8px;border-radius:99px;font-size:11px;font-weight:700;margin-<?=$isRtl?'right':'left'?>:6px"><?=count($progStudents)?></span>
      </div>
    </div>
    <div style="padding:12px 16px;display:flex;flex-direction:column;gap:8px">
    <?php foreach($progStudents as $st):
      $attPct=$st['total_att']>0?round($st['present_cnt']/$st['total_att']*100):0;
      $avgPct=round($st['avg_pct']??0);
      $attColor=$attPct>=80?'#059669':($attPct>=60?'#D97706':'#DC2626');
      $progColor=$avgPct>=80?'#059669':($avgPct>=50?'#2563EB':'#D97706');
    ?>
    <div style="display:flex;align-items:center;gap:12px;padding:10px 12px;background:#F9FAFB;border-radius:10px;border:1px solid #F3F4F6">
      <!-- Avatar -->
      <div style="width:38px;height:38px;border-radius:10px;background:<?=$slotBgP?>;color:<?=$slotTxtP?>;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px;flex-shrink:0"><?=strtoupper(substr($st['full_name'],0,1))?></div>
      <!-- Name -->
      <div style="flex:1;min-width:0">
        <div style="font-weight:700;font-size:13px;color:#111;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=h($st['full_name'])?></div>
        <?php if($st['full_name_ar']??''):?><div style="font-size:10px;color:#9CA3AF"><?=h($st['full_name_ar'])?></div><?php endif;?>
      </div>
      <!-- Attendance pill -->
      <div style="text-align:center;min-width:52px">
        <div style="font-size:12px;font-weight:700;color:<?=$attColor?>"><?=$attPct?>%</div>
        <div style="font-size:9px;color:#9CA3AF"><?=$isRtl?'حضور':'Attend'?></div>
      </div>
      <!-- Progress bar -->
      <div style="min-width:70px">
        <div style="height:5px;background:#E5E7EB;border-radius:3px;overflow:hidden;margin-bottom:3px">
          <div style="height:100%;width:<?=$avgPct?>%;background:<?=$progColor?>;border-radius:3px"></div>
        </div>
        <div style="font-size:9px;font-weight:700;color:<?=$progColor?>;text-align:center"><?=$avgPct?>%</div>
      </div>
      <!-- Profile button -->
      <a href="/teacher/student_profile.php?id=<?=$st['id']?>" style="background:<?=$slotBgP?>;color:<?=$slotTxtP?>;padding:5px 10px;border-radius:8px;text-decoration:none;font-size:11px;font-weight:700;white-space:nowrap;flex-shrink:0">👤</a>
    </div>
    <?php endforeach;?>
    </div>
  </div>
  <?php else:?>
  <div style="background:#F9FAFB;border:2px dashed #E5E7EB;border-radius:14px;padding:30px;text-align:center;margin-bottom:14px;color:#9CA3AF">
    <div style="font-size:2rem;margin-bottom:8px"><?=$isChildP?'👶':'🎓'?></div>
    <div style="font-size:13px"><?=$isRtl?'لا يوجد طلاب مسجّلون بعد':'No students enrolled yet'?></div>
  </div>
  <?php endif;?>

  <!-- ③ Courses Grid -->
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:14px;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #F3F4F6">
      <div style="font-weight:700;font-size:13px;color:#111">📚 <?=$isRtl?'الدورات — انقر لإدارة كل دورة':'Courses — Click to manage'?></div>
      <div style="font-size:11px;color:#9CA3AF;margin-top:2px"><?=$isRtl?'كل دورة لها دروسها وحضورها وتقدمها الخاص':'Each course has its own lessons, attendance & progress'?></div>
    </div>
    <div style="padding:12px 16px;display:flex;flex-direction:column;gap:6px">
    <?php foreach($fixedCourses as $fc):
      $isAct=$fc['is_selected'];
    ?>
    <a href="/teacher/course.php?id=<?=$fc['id']?>&prog=<?=$selProg['id']?>"
       style="display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:10px;border:2px solid <?=$isAct?$slotColorP:'#F3F4F6'?>;background:<?=$isAct?$slotBgP:'#FAFAFA'?>;text-decoration:none;transition:.15s">
      <span style="font-size:1.5rem;flex-shrink:0"><?=$fc['icon']?></span>
      <div style="flex:1">
        <div style="font-weight:<?=$isAct?700:600?>;font-size:13px;color:<?=$isAct?$slotTxtP:'#374151'?>"><?=h($isRtl?$fc['name_ar']:$fc['name_en'])?></div>
        <div style="font-size:10px;color:#9CA3AF;margin-top:1px"><?=$isRtl?'اضغط لإضافة درس أو حضور أو تقدم':'Tap to add lessons · attendance · progress'?></div>
      </div>
      <?php if($isAct):?>
      <span style="background:<?=$slotColorP?>;color:#fff;padding:3px 10px;border-radius:99px;font-size:10px;font-weight:700;flex-shrink:0">✅ <?=$isRtl?'الحالية':'Active'?></span>
      <?php else:?>
      <span style="color:#D1D5DB;font-size:16px;flex-shrink:0">›</span>
      <?php endif;?>
    </a>
    <?php endforeach;?>
    </div>
  </div>
<?php endif;?>
</div>
<?php endif;?>

<?php elseif($tab==='students'): ?>
<?php if(empty($myStudents)):?><div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:60px;text-align:center;color:var(--gray-300)"><div style="font-size:3rem">👥</div><div style="margin-top:12px">No students yet</div></div>
<?php else:?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px">
  <?php foreach($myStudents as $s): $attPct=$s['total_att']>0?round($s['present_cnt']/$s['total_att']*100):0; $sc=$slotColors[$s['slot']]??['#F3F4F6','#374151','📖'];?>
  <div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:16px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
      <div style="width:42px;height:42px;border-radius:50%;background:<?=$sc[0]?>;color:<?=$sc[1]?>;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.1rem;flex-shrink:0"><?=strtoupper(substr($s['full_name'],0,1))?></div>
      <div style="flex:1"><div style="font-weight:700;font-size:14px;color:var(--green-dark)"><?=h($s['full_name'])?></div><span style="background:<?=$sc[0]?>;color:<?=$sc[1]?>;padding:1px 7px;border-radius:99px;font-size:10px;font-weight:700"><?=$sc[2]?> Slot <?=$s['slot']?></span></div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px"><span style="font-size:10px;color:var(--gray-500);min-width:55px">Attendance</span><div style="flex:1;height:5px;background:var(--gray-100);border-radius:3px;overflow:hidden"><div style="height:100%;width:<?=$attPct?>%;background:<?=$attPct>=80?'var(--success)':'var(--warning)'?>;border-radius:3px"></div></div><span style="font-size:10px;font-weight:700;color:<?=$attPct>=80?'var(--success)':'var(--warning)'?>"><?=$attPct?>%</span></div>
    <div style="font-size:11px;color:var(--gray-400);margin-bottom:10px">📖 <?=$s['surahs_done']?> surahs done</div>
    <a href="/teacher/student_profile.php?id=<?=$s['id']?>" style="display:block;background:#4C1D95;color:#fff;text-align:center;padding:7px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:600">👤 View Profile</a>
  </div>
  <?php endforeach;?>
</div>
<?php endif;?>

<?php elseif($tab==='apply'): ?>
<?php if(!$userGov):?><div class="alert alert-warning">Update your governorate in profile</div>
<?php else:?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px">
  <h3 style="font-size:14px;font-weight:700;color:var(--green-dark);margin:0 0 4px">🎯 Available Slots</h3>
  <p style="font-size:12px;color:var(--gray-500);margin:0 0 14px">🎓 Slot A = Students (12+) &nbsp;|&nbsp; 👶 Slot B = Children (5-12). Direct assignment — no approval needed.</p>
  <?php if(empty($availProgs)):?><div style="text-align:center;padding:30px;color:var(--gray-300)"><div style="font-size:2.5rem">✅</div><div style="margin-top:8px">All slots filled in your governorate</div></div>
  <?php else: foreach($availProgs as $p): $sc=$slotColors[$p['slot']]??['#F3F4F6','#374151','📖'];?>
  <div style="background:var(--gray-50);border:2px solid <?=$sc[0]?>;border-radius:12px;padding:14px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div>
      <div style="display:flex;align-items:center;gap:8px"><span style="font-size:1.3rem"><?=$sc[2]?></span><div><div style="font-weight:700;font-size:13px;color:<?=$sc[1]?>">Slot <?=$p['slot']?> — <?=h($isRtl?$p['mosque_ar']:$p['mosque_en'])?></div><div style="font-size:11px;color:var(--gray-500)">📅 <?=str_replace(',',' / ',$p['days'])?> · ⏰ <?=substr($p['time_start'],0,5)?>–<?=substr($p['time_end'],0,5)?></div></div></div>
      <div style="display:flex;gap:6px;margin-top:6px">
        <span style="background:<?=$sc[0]?>;color:<?=$sc[1]?>;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700"><?=$sc[2]?> <?=$p['target_type']==='child'?'For Children (5-12)':'For Students (12+)'?></span>
        <span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700">✅ Available</span>
      </div>
    </div>
    <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="apply"><input type="hidden" name="program_id" value="<?=$p['id']?>"><button type="submit" class="btn btn-primary btn-sm">➕ Join</button></form>
  </div>
  <?php endforeach; endif;?>
</div>
<?php endif;?>

<?php elseif($tab==='private'): ?>
<?php if(empty($privateReqs)):?><div style="text-align:center;padding:60px;color:var(--gray-300)"><div style="font-size:3rem">🌟</div><div style="margin-top:12px">No private requests</div></div>
<?php else: foreach($privateReqs as $req):?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px;margin-bottom:12px">
  <div style="display:grid;grid-template-columns:1fr auto;gap:16px;align-items:start">
    <div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px"><div style="width:40px;height:40px;border-radius:50%;background:#FEF3C7;display:flex;align-items:center;justify-content:center;font-size:1.2rem">👶</div><div><div style="font-weight:700;font-size:14px;color:var(--green-dark)"><?=h($req['child_name'])?></div><div style="font-size:12px;color:var(--gray-500)"><?=$isRtl?'ولي الأمر:':'Parent:'?> <?=h($req['parent_name'])?></div></div></div>
      <div style="background:var(--gray-50);border-radius:8px;padding:10px;font-size:12px">🕌 <?=h($req['mosque_en'])?></div>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px">
      <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="accept_private"><input type="hidden" name="req_id" value="<?=$req['id']?>"><button type="submit" class="btn btn-primary btn-sm">✅ Accept</button></form>
      <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="reject_private"><input type="hidden" name="req_id" value="<?=$req['id']?>"><button type="submit" class="btn btn-secondary btn-sm" style="color:var(--danger)">❌ Reject</button></form>
    </div>
  </div>
</div>
<?php endforeach; endif;?>
<?php endif;?>

</main>
</div>
<script>
function setLT(type){
  document.getElementById('ltInput').value=type;
  document.getElementById('edDiv').style.display=type==='monthly'?'block':'none';
  document.getElementById('btn-w').style.background=type==='weekly'?'#4C1D95':'transparent';
  document.getElementById('btn-w').style.color=type==='weekly'?'#fff':'var(--gray-500)';
  document.getElementById('btn-m').style.background=type==='monthly'?'#4C1D95':'transparent';
  document.getElementById('btn-m').style.color=type==='monthly'?'#fff':'var(--gray-500)';
}
</script>
<?php include '../includes/footer.php'; ?>
