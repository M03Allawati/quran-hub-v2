<?php
require_once dirname(__DIR__) . '/config.php';
requireRole('mosque_admin');
$lang=$_SESSION['lang']??'en'; $isRtl=$lang==='ar';
$pdo=getPDO(); $userId=$_SESSION['user_id'];
$pageTitle=($isRtl?'لوحة مدير المسجد — ':'Mosque Dashboard — ').APP_NAME;

// Get mosque this admin manages
$mosque=$pdo->prepare("SELECT * FROM mosques WHERE admin_id=? AND is_active=1 LIMIT 1");
$mosque->execute([$userId]); $mosque=$mosque->fetch();
if(!$mosque){ 
    // Fallback: use mosque_id from user
    $mosque=$pdo->prepare("SELECT * FROM mosques WHERE id=? LIMIT 1");
    $mosque->execute([$_SESSION['user']['mosque_id']??0]); $mosque=$mosque->fetch();
}
if(!$mosque){ setFlash('danger','No mosque assigned to your account.'); header('Location: /logout.php'); exit; }

$mid=$mosque['id'];

// Use prepared statements properly
$stmt=$pdo->prepare("SELECT COUNT(*) FROM mosque_programs WHERE mosque_id=? AND is_active=1"); $stmt->execute([$mid]);
$stats=['programs'=>$stmt->fetchColumn()];
$stmt=$pdo->prepare("SELECT COUNT(DISTINCT teacher_id) FROM mosque_programs WHERE mosque_id=? AND teacher_id IS NOT NULL"); $stmt->execute([$mid]);
$stats['teachers']=$stmt->fetchColumn();
$stmt=$pdo->prepare("SELECT COUNT(*) FROM students WHERE mosque_id=? AND is_active=1"); $stmt->execute([$mid]);
$stats['students']=$stmt->fetchColumn();
$stmt=$pdo->prepare("SELECT COUNT(*) FROM students WHERE mosque_id=? AND is_active=1 AND student_type='child'"); $stmt->execute([$mid]);
$stats['children']=$stmt->fetchColumn();
$stmt=$pdo->prepare("SELECT COUNT(*) FROM students WHERE mosque_id=? AND is_active=1 AND student_type='student'"); $stmt->execute([$mid]);
$stats['adult_students']=$stmt->fetchColumn();
$stmt=$pdo->prepare("SELECT COUNT(*) FROM private_program_requests WHERE mosque_id=? AND status='pending'"); $stmt->execute([$mid]);
$stats['private']=$stmt->fetchColumn();

// Today's attendance
$stmt=$pdo->prepare("SELECT COUNT(*) FROM attendance a JOIN classes c ON c.id=a.class_id WHERE c.mosque_id=? AND a.date=CURDATE() AND a.status='present'"); $stmt->execute([$mid]);
$stats['today_att']=$stmt->fetchColumn();

// Programs with teachers + active courses
$programs=$pdo->prepare("
    SELECT mp.*, u.full_name as teacher_name, u.full_name_ar as teacher_name_ar,
           u.slot as teacher_slot,
           fc.name_en as course_en, fc.name_ar as course_ar, fc.icon as course_icon,
           (SELECT COUNT(*) FROM program_enrollments pe WHERE pe.program_id=mp.id AND pe.status='active') as enrolled,
           (SELECT COUNT(*) FROM attendance a JOIN classes c ON c.id=a.class_id AND c.teacher_id=mp.teacher_id WHERE a.date=CURDATE() AND a.status='present') as today_present
    FROM mosque_programs mp
    LEFT JOIN users u ON u.id=mp.teacher_id
    LEFT JOIN teacher_course_selections tcs ON tcs.program_id=mp.id AND tcs.is_active=1
    LEFT JOIN fixed_courses fc ON fc.id=tcs.course_id
    WHERE mp.mosque_id=? AND mp.is_active=1
    ORDER BY mp.slot");
$programs->execute([$mid]); $programs=$programs->fetchAll();

// Recent enrollments
$recent=$pdo->prepare("SELECT s.full_name,s.student_type,pe.enrolled_at,mp.name_en as prog,mp.slot FROM program_enrollments pe JOIN students s ON s.id=pe.student_id JOIN mosque_programs mp ON mp.id=pe.program_id WHERE mp.mosque_id=? ORDER BY pe.enrolled_at DESC LIMIT 8");
$recent->execute([$mid]); $recent=$recent->fetchAll();

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<!-- Mosque header -->
<div style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));border-radius:16px;padding:24px;margin-bottom:24px;color:#fff">
  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div style="font-size:3rem">🕌</div>
    <div style="flex:1">
      <h1 style="font-size:1.4rem;font-weight:800;margin:0 0 4px;color:#fff"><?=h($isRtl?$mosque['name_ar']:$mosque['name_en'])?></h1>
      <div style="color:rgba(255,255,255,.8);font-size:13px">📍 <?=h($mosque['governorate'])?><?=$mosque['wilayat']?' — '.h($mosque['wilayat']):''?></div>
      <?php if($mosque['is_grand']):?><span style="background:rgba(255,255,255,.2);color:#fff;padding:2px 10px;border-radius:99px;font-size:11px;font-weight:700;margin-top:4px;display:inline-block">⭐ <?=$isRtl?'جامع كبير':'Grand Mosque'?></span><?php endif;?>
    </div>
    <?php if($stats['pending']>0):?>
    <a href="/mosque_admin/programs.php?tab=applications" style="background:rgba(255,255,255,.2);color:#fff;padding:10px 18px;border-radius:10px;text-decoration:none;font-weight:700;font-size:13px;border:1px solid rgba(255,255,255,.4)">
      ⏳ <?=$stats['pending']?> <?=$isRtl?'طلب معلق':'Pending'?>
    </a>
    <?php endif;?>
  </div>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:24px">
  <?php foreach([
    ['📚',$stats['programs'],$isRtl?'البرامج':'Programs','#EDE9FE','#4C1D95'],
    ['👨‍🏫',$stats['teachers'],$isRtl?'المعلمون':'Teachers','#DBEAFE','#1D4ED8'],
    ['🎓',$stats['adult_students'],$isRtl?'الطلاب':'Students (A)','#D1FAE5','#065F46'],
    ['👶',$stats['children'],$isRtl?'الأطفال':'Children (B)','#FEF3C7','#92400E'],
    ['✅',$stats['today_att'],$isRtl?'حضور اليوم':'Today\'s Att.','#D1FAE5','#065F46'],
  ] as [$icon,$val,$label,$bg,$color]):?>
  <div style="background:<?=$bg?>;border-radius:12px;padding:16px;display:flex;align-items:center;gap:12px">
    <span style="font-size:1.6rem"><?=$icon?></span>
    <div><div style="font-size:1.5rem;font-weight:800;color:<?=$color?>"><?=$val?></div><div style="font-size:11px;color:<?=$color?>;opacity:.8"><?=$label?></div></div>
  </div>
  <?php endforeach;?>
</div>

<!-- Programs Report -->
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px;margin-bottom:20px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h3 style="font-size:14px;font-weight:700;color:var(--green-dark);margin:0">📊 <?=$isRtl?'تقرير البرامج والمعلمين':'Programs & Teachers Report'?></h3>
    <a href="/mosque_admin/programs.php" style="font-size:12px;color:#4C1D95;text-decoration:none;font-weight:600"><?=$isRtl?'إدارة البرامج':'Manage Programs'?> →</a>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
  <?php foreach($programs as $prog):
    $slotBg=$prog['slot']==='A'?'#EFF6FF':'#FFFBEB';
    $slotBorder=$prog['slot']==='A'?'#BFDBFE':'#FDE68A';
    $slotColor=$prog['slot']==='A'?'#1D4ED8':'#92400E';
    $slotIcon=$prog['slot']==='A'?'🎓':'👶';
  ?>
  <div style="background:<?=$slotBg?>;border:1px solid <?=$slotBorder?>;border-radius:12px;padding:16px">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
      <span style="font-size:1.3rem"><?=$slotIcon?></span>
      <div>
        <div style="font-weight:700;font-size:13px;color:<?=$slotColor?>">Slot <?=$prog['slot']?> — <?=h($isRtl?$prog['name_ar']:$prog['name_en'])?></div>
        <div style="font-size:11px;color:var(--gray-500)"><?=str_replace(',',' · ',$prog['days'])?> · <?=substr($prog['time_start'],0,5)?>–<?=substr($prog['time_end'],0,5)?></div>
      </div>
    </div>

    <?php if($prog['teacher_name']):?>
    <div style="background:rgba(255,255,255,.7);border-radius:8px;padding:10px;margin-bottom:8px">
      <div style="font-size:10px;color:var(--gray-400);font-weight:700;text-transform:uppercase;margin-bottom:4px">👨‍🏫 Teacher</div>
      <div style="font-weight:600;font-size:13px;color:var(--green-dark)"><?=h($prog['teacher_name'])?></div>
      <?php if($prog['course_en']):?>
      <div style="display:flex;align-items:center;gap:5px;margin-top:4px">
        <span><?=$prog['course_icon']?></span>
        <div>
          <div style="font-size:11px;font-weight:600;color:<?=$slotColor?>"><?=h($isRtl?$prog['course_ar']:$prog['course_en'])?></div>
          <div style="font-size:10px;color:var(--gray-400)"><?=$isRtl?'الدورة الحالية':'Active Course'?></div>
        </div>
      </div>
      <?php else:?>
      <div style="font-size:11px;color:var(--warning);margin-top:4px">⚠️ <?=$isRtl?'لم يختر دورة بعد':'No course selected yet'?></div>
      <?php endif;?>
    </div>
    <?php else:?>
    <div style="background:rgba(255,255,255,.7);border-radius:8px;padding:10px;margin-bottom:8px;text-align:center;color:var(--gray-400);font-size:12px">
      ⚠️ <?=$isRtl?'لا يوجد معلم معيّن':'No teacher assigned'?>
    </div>
    <?php endif;?>

    <div style="display:flex;justify-content:space-between;align-items:center">
      <span style="font-size:12px;color:var(--gray-500)">👥 <?=$prog['enrolled']?>/<?=$prog['max_students']?> <?=$isRtl?'طالب':'enrolled'?></span>
      <span style="font-size:12px;color:var(--success)">✅ <?=$prog['today_present']?> <?=$isRtl?'اليوم':'today'?></span>
    </div>
  </div>
  <?php endforeach;?>
  </div>
</div>

<!-- Recent Enrollments -->
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px">
  <h3 style="font-size:14px;font-weight:700;color:var(--green-dark);margin:0 0 14px">🆕 <?=$isRtl?'أحدث التسجيلات':'Recent Enrollments'?></h3>
  <?php if(empty($recent)):?>
  <div style="text-align:center;padding:20px;color:var(--gray-300);font-size:13px"><?=$isRtl?'لا توجد تسجيلات بعد':'No enrollments yet'?></div>
  <?php else:?>
  <div style="display:flex;flex-direction:column;gap:6px">
    <?php foreach($recent as $r):
      $isChild=$r['student_type']==='child';
    ?>
    <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;background:var(--gray-50);border-radius:8px">
      <span style="font-size:1.1rem"><?=$isChild?'👶':'🎓'?></span>
      <div style="flex:1">
        <div style="font-weight:600;font-size:13px;color:var(--green-dark)"><?=h($r['full_name'])?></div>
        <div style="font-size:11px;color:var(--gray-400)"><?=h($r['prog'])?> · Slot <?=$r['slot']?></div>
      </div>
      <div style="font-size:11px;color:var(--gray-400)"><?=date('d M',strtotime($r['enrolled_at']))?></div>
    </div>
    <?php endforeach;?>
  </div>
  <?php endif;?>
</div>

<!-- Quick actions -->
<div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
  <a href="/mosque_admin/programs.php" class="btn btn-primary">📚 <?=$isRtl?'إدارة البرامج':'Manage Programs'?></a>
  <a href="/mosque_admin/teachers.php" class="btn btn-secondary">👨‍🏫 <?=$isRtl?'المعلمون':'Teachers'?></a>
  <a href="/mosque_admin/students.php" class="btn btn-secondary">🎓 <?=$isRtl?'الطلاب':'Students'?></a>
  <a href="/mosque_admin/attendance.php" class="btn btn-secondary">✅ <?=$isRtl?'الحضور':'Attendance'?></a>
</div>

</main>
</div>
<?php include '../includes/footer.php'; ?>
