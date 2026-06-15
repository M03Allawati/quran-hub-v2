<?php
require_once dirname(__DIR__) . '/config.php';
requireRole('student');
$lang=$_SESSION['lang']??'en'; $isRtl=$lang==='ar';
$pdo=getPDO(); $userId=$_SESSION['user_id'];
$pageTitle=($isRtl?'فصلي ودورتي — ':'My Class & Course — ').APP_NAME;

$student=$pdo->prepare("SELECT s.*,m.name_en as mosque_en,m.name_ar as mosque_ar FROM students s JOIN mosques m ON m.id=s.mosque_id WHERE s.user_id=? AND s.is_active=1 LIMIT 1");
$student->execute([$userId]); $student=$student->fetch();
if(!$student){ header('Location: /student/dashboard.php'); exit; }
$sid=$student['id'];

// Get enrolled program with active course + teacher + this week's lesson + monthly plan
$enrolled=$pdo->prepare("
    SELECT mp.*,
           u.full_name as teacher_name, u.full_name_ar as teacher_ar, u.phone as teacher_phone,
           fc.name_en as course_en, fc.name_ar as course_ar, fc.icon as course_icon,
           fc.description_en as course_desc_en, fc.description_ar as course_desc_ar,
           tcs.start_date as course_start,
           wl.topic_en as week_topic_en, wl.topic_ar as week_topic_ar,
           wl.week_start, wl.week_end,
           wl.surah_name_ar, wl.surah_name_en, wl.ayah_from, wl.ayah_to,
           wl.objectives, wl.homework, wl.notes as lesson_notes,
           lp.title_en as plan_en, lp.title_ar as plan_ar, lp.description as plan_desc,
           lp.start_date as plan_start, lp.end_date as plan_end
    FROM program_enrollments pe
    JOIN mosque_programs mp ON mp.id=pe.program_id
    LEFT JOIN users u ON u.id=mp.teacher_id
    LEFT JOIN teacher_course_selections tcs ON tcs.program_id=mp.id AND tcs.is_active=1
    LEFT JOIN fixed_courses fc ON fc.id=tcs.course_id
    LEFT JOIN weekly_lessons wl ON wl.program_id=mp.id
        AND wl.week_start<=CURDATE() AND wl.week_end>=CURDATE()
    LEFT JOIN lesson_plans lp ON lp.program_id=mp.id
        AND lp.start_date<=CURDATE() AND lp.end_date>=CURDATE()
    WHERE pe.student_id=? AND pe.status='active' AND mp.is_active=1
    ORDER BY mp.slot LIMIT 1");
$enrolled->execute([$sid]); $enrolled=$enrolled->fetch();

$days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$daysAr=['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<h1 style="font-size:1.5rem;font-weight:800;margin:0 0 4px">📖 <?=$isRtl?'فصلي ودورتي':'My Class & Course'?></h1>
<p style="color:var(--gray-500);font-size:13px;margin:0 0 20px">🕌 <?=h($isRtl?$student['mosque_ar']:$student['mosque_en'])?></p>

<?php if($enrolled):
  $isChild=$enrolled['slot']==='B';
  $slotGrad=$isChild?'linear-gradient(135deg,#92400E,#D97706)':'linear-gradient(135deg,#1D4ED8,#4F46E5)';
  $slotIcon=$isChild?'👶':'🎓';
?>

<!-- Program + Active Course Banner -->
<div style="background:<?=$slotGrad?>;border-radius:16px;padding:24px;color:#fff;margin-bottom:20px">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px">
    <div style="flex:1">
      <div style="font-size:11px;opacity:.7;font-weight:600;text-transform:uppercase;margin-bottom:8px">
        <?=$slotIcon?> Slot <?=$enrolled['slot']?> · <?=$isChild?($isRtl?'برنامج الأطفال':'Children Program'):($isRtl?'برنامج الطلاب':'Students Program')?>
        &nbsp;·&nbsp; 📅 <?=str_replace(',',' · ',$enrolled['days'])?> &nbsp;·&nbsp; ⏰ <?=substr($enrolled['time_start'],0,5)?>–<?=substr($enrolled['time_end'],0,5)?>
      </div>
      <?php if($enrolled['course_en']):?>
      <div style="display:flex;align-items:center;gap:12px">
        <span style="font-size:2.5rem"><?=$enrolled['course_icon']?></span>
        <div>
          <div style="font-size:11px;opacity:.7;margin-bottom:2px"><?=$isRtl?'دورتي الحالية':'My Current Course'?></div>
          <div style="font-weight:800;font-size:1.3rem"><?=h($isRtl?$enrolled['course_ar']:$enrolled['course_en'])?></div>
          <div style="font-size:12px;opacity:.8;margin-top:2px"><?=h($isRtl?$enrolled['course_desc_ar']:$enrolled['course_desc_en'])?></div>
        </div>
      </div>
      <?php else:?>
      <div style="font-weight:700;font-size:1rem;opacity:.8">
        ⏳ <?=$isRtl?'معلمك لم يختر دورة بعد — تابع قريباً':'Your teacher hasn\'t selected a course yet'?>
      </div>
      <?php endif;?>
    </div>

    <?php if($enrolled['teacher_name']):?>
    <div style="background:rgba(255,255,255,.15);border-radius:12px;padding:14px 18px;text-align:center">
      <div style="font-size:10px;opacity:.7;margin-bottom:5px;text-transform:uppercase;font-weight:600"><?=$isRtl?'معلمك':'Your Teacher'?></div>
      <div style="font-size:1.8rem;margin-bottom:4px">👨‍🏫</div>
      <div style="font-weight:700;font-size:13px"><?=h($enrolled['teacher_name'])?></div>
      <?php if($enrolled['teacher_phone']):?><div style="font-size:11px;opacity:.8;margin-top:2px">📱 <?=h($enrolled['teacher_phone'])?></div><?php endif;?>
    </div>
    <?php endif;?>
  </div>
</div>

<!-- This Week's Lesson -->
<?php if($enrolled['week_topic_en']||$enrolled['week_topic_ar']):?>
<div style="background:#fff;border:2px solid #86EFAC;border-radius:14px;padding:20px;margin-bottom:16px">
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
    <span style="background:#D1FAE5;color:#065F46;padding:4px 12px;border-radius:99px;font-size:11px;font-weight:700">🟢 <?=$isRtl?'درس هذا الأسبوع':'This Week\'s Lesson'?></span>
    <span style="font-size:12px;color:var(--gray-400)"><?=date('d M',strtotime($enrolled['week_start']))?> – <?=date('d M Y',strtotime($enrolled['week_end']))?></span>
  </div>
  <div style="font-weight:800;font-size:16px;color:var(--green-dark);margin-bottom:12px">
    <?=h($isRtl?$enrolled['week_topic_ar']:$enrolled['week_topic_en'])?>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px">
    <?php if($enrolled['surah_name_ar']):?>
    <div style="background:#F0FDF4;border-radius:8px;padding:10px;border:1px solid #BBF7D0">
      <div style="font-size:10px;color:#065F46;font-weight:700;margin-bottom:3px">📖 <?=$isRtl?'السورة':'Surah'?></div>
      <div style="font-weight:700;font-size:13px;color:var(--green-dark)"><?=h($enrolled['surah_name_ar'])?></div>
      <?php if($enrolled['ayah_from']):?><div style="font-size:11px;color:var(--gray-500)"><?=$isRtl?'آية':'Ayah'?> <?=$enrolled['ayah_from']?>–<?=$enrolled['ayah_to']?></div><?php endif;?>
    </div>
    <?php endif;?>
    <?php if($enrolled['objectives']):?>
    <div style="background:#EFF6FF;border-radius:8px;padding:10px;border:1px solid #BFDBFE">
      <div style="font-size:10px;color:#1D4ED8;font-weight:700;margin-bottom:3px">🎯 <?=$isRtl?'أهداف الدرس':'Objectives'?></div>
      <div style="font-size:12px;color:var(--gray-700)"><?=h($enrolled['objectives'])?></div>
    </div>
    <?php endif;?>
    <?php if($enrolled['homework']):?>
    <div style="background:#FFFBEB;border-radius:8px;padding:10px;border:2px solid #FDE68A">
      <div style="font-size:10px;color:#92400E;font-weight:700;margin-bottom:3px">📋 <?=$isRtl?'الواجب المنزلي':'Homework'?></div>
      <div style="font-size:13px;color:#92400E;font-weight:600"><?=h($enrolled['homework'])?></div>
    </div>
    <?php endif;?>
    <?php if($enrolled['lesson_notes']):?>
    <div style="background:var(--gray-50);border-radius:8px;padding:10px">
      <div style="font-size:10px;color:var(--gray-400);font-weight:700;margin-bottom:3px">💬 <?=$isRtl?'ملاحظات المعلم':'Teacher Notes'?></div>
      <div style="font-size:12px;color:var(--gray-600)"><?=h($enrolled['lesson_notes'])?></div>
    </div>
    <?php endif;?>
  </div>
</div>
<?php else:?>
<div style="background:var(--gray-50);border:1px dashed var(--gray-200);border-radius:12px;padding:20px;text-align:center;color:var(--gray-400);margin-bottom:16px">
  <div style="font-size:2rem">📅</div>
  <div style="font-size:13px;margin-top:8px"><?=$isRtl?'لم يُضف معلمك درس هذا الأسبوع بعد':'Your teacher hasn\'t added this week\'s lesson yet'?></div>
</div>
<?php endif;?>

<!-- Monthly Plan -->
<?php if($enrolled['plan_en']||$enrolled['plan_ar']):?>
<div style="background:#fff;border:1px solid #BFDBFE;border-radius:14px;padding:18px;margin-bottom:16px">
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
    <span style="background:#DBEAFE;color:#1D4ED8;padding:4px 12px;border-radius:99px;font-size:11px;font-weight:700">🗓️ <?=$isRtl?'الخطة الشهرية':'Monthly Plan'?></span>
    <span style="font-size:12px;color:var(--gray-400)"><?=date('d M',strtotime($enrolled['plan_start']))?> – <?=date('d M Y',strtotime($enrolled['plan_end']))?></span>
  </div>
  <div style="font-weight:700;font-size:14px;color:var(--green-dark);margin-bottom:8px"><?=h($isRtl?$enrolled['plan_ar']:$enrolled['plan_en'])?></div>
  <?php if($enrolled['plan_desc']):?>
  <div style="font-size:12px;color:var(--gray-600);white-space:pre-line;line-height:1.8;background:var(--gray-50);border-radius:8px;padding:10px"><?=h($enrolled['plan_desc'])?></div>
  <?php endif;?>
</div>
<?php endif;?>

<!-- Weekly Schedule -->
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:16px">
  <div style="font-weight:700;font-size:13px;color:var(--green-dark);margin-bottom:12px">📅 <?=$isRtl?'أيام الدراسة هذا الأسبوع':'Class Days This Week'?></div>
  <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px">
    <?php
    $programDays=array_map('trim',explode(',',$enrolled['days']));
    foreach($days as $i=>$day):
      $hasClass=in_array($day,$programDays);
      $isToday=date('l')===$day;
      $bg=$isToday?($isChild?'#D97706':'#1D4ED8'):($hasClass?($isChild?'#FEF3C7':'#DBEAFE'):'#F9FAFB');
      $color=$isToday?'#fff':($hasClass?($isChild?'#92400E':'#1D4ED8'):'#9CA3AF');
    ?>
    <div style="background:<?=$bg?>;border-radius:10px;padding:10px 6px;text-align:center;<?=$isToday?'border:2px solid '.($isChild?'#D97706':'#1D4ED8').';':''?>">
      <div style="font-size:10px;font-weight:700;color:<?=$color?>"><?=$isRtl?$daysAr[$i]:substr($day,0,3)?></div>
      <?php if($hasClass):?>
      <div style="font-size:8px;color:<?=$color?>;margin-top:3px;font-weight:600"><?=substr($enrolled['time_start'],0,5)?></div>
      <div style="width:6px;height:6px;border-radius:50%;background:<?=$color?>;margin:4px auto 0"></div>
      <?php else:?>
      <div style="font-size:10px;color:<?=$color?>;margin-top:6px">—</div>
      <?php endif;?>
      <?php if($isToday):?><div style="font-size:8px;color:<?=$color?>;margin-top:2px;font-weight:700"><?=$isRtl?'اليوم':'Today'?></div><?php endif;?>
    </div>
    <?php endforeach;?>
  </div>
</div>

<?php else:?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:60px;text-align:center;color:var(--gray-300)">
  <div style="font-size:3rem">📖</div>
  <div style="margin-top:12px;font-size:15px"><?=$isRtl?'لم تُسجَّل في أي برنامج بعد':'Not enrolled in any program yet'?></div>
  <a href="/student/programs.php" class="btn btn-primary" style="margin-top:16px;display:inline-block"><?=$isRtl?'عرض البرامج':'View Programs'?></a>
</div>
<?php endif;?>

</main>
</div>
<?php include '../includes/footer.php'; ?>
