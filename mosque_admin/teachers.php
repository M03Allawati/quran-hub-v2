<?php
require_once dirname(__DIR__) . '/config.php';
requireRole('mosque_admin');
$lang=$_SESSION['lang']??'en'; $isRtl=$lang==='ar';
$pdo=getPDO(); $userId=$_SESSION['user_id'];
$pageTitle=($isRtl?'معلمو المسجد — ':'Mosque Teachers — ').APP_NAME;

$mosque=$pdo->prepare("SELECT * FROM mosques WHERE admin_id=? LIMIT 1");
$mosque->execute([$userId]); $mosque=$mosque->fetch();
if(!$mosque){ $mosque=$pdo->prepare("SELECT * FROM mosques WHERE id=? LIMIT 1"); $mosque->execute([$_SESSION['user']['mosque_id']??0]); $mosque=$mosque->fetch(); }
if(!$mosque){ header('Location: /dashboard.php'); exit; }
$mid=$mosque['id'];

$teachers=$pdo->query("SELECT DISTINCT u.id,u.full_name,u.full_name_ar,u.email,u.phone,u.is_active,
    (SELECT COUNT(*) FROM mosque_programs mp WHERE mp.mosque_id=$mid AND mp.teacher_id=u.id) as programs,
    (SELECT COUNT(DISTINCT pe.student_id) FROM program_enrollments pe JOIN mosque_programs mp ON mp.id=pe.program_id WHERE mp.mosque_id=$mid AND mp.teacher_id=u.id AND pe.status='active') as students,
    mp2.name_en as prog_name, mp2.slot, mp2.days, mp2.time_start, mp2.time_end
    FROM users u
    JOIN mosque_programs mp2 ON mp2.teacher_id=u.id AND mp2.mosque_id=$mid
    WHERE u.role='teacher' AND u.is_active=1
    ORDER BY u.full_name")->fetchAll();

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<h1 style="font-size:1.5rem;font-weight:800;margin:0 0 4px">👨‍🏫 <?=$isRtl?'معلمو المسجد':'Mosque Teachers'?></h1>
<p style="color:var(--gray-500);font-size:13px;margin:0 0 20px">🕌 <?=h($isRtl?$mosque['name_ar']:$mosque['name_en'])?> · <?=count($teachers)?> <?=$isRtl?'معلم':'teachers'?></p>

<?php if(empty($teachers)):?>
<div style="text-align:center;padding:60px;background:#fff;border-radius:14px;border:1px solid var(--gray-100);color:var(--gray-300)">
  <div style="font-size:48px">👨‍🏫</div>
  <div style="margin-top:12px;font-size:15px"><?=$isRtl?'لا يوجد معلمون مرتبطون بمسجدك بعد':'No teachers assigned to your mosque yet'?></div>
  <p style="font-size:13px;margin-top:8px;color:var(--gray-300)"><?=$isRtl?'سيظهر المعلمون هنا بعد قبول طلباتهم':'Teachers will appear here once their applications are approved'?></p>
  <a href="/mosque_admin/programs.php?tab=applications" class="btn btn-primary" style="margin-top:16px"><?=$isRtl?'مراجعة الطلبات':'Review Applications'?></a>
</div>
<?php else:?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px">
  <?php foreach($teachers as $t):?>
  <div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
      <div style="width:48px;height:48px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--green-dark);font-size:1.2rem;flex-shrink:0">
        <?=strtoupper(substr($t['full_name'],0,1))?>
      </div>
      <div style="flex:1">
        <div style="font-weight:700;font-size:14px;color:var(--green-dark)"><?=h($t['full_name'])?></div>
        <?php if($t['full_name_ar']):?><div style="font-size:12px;color:var(--gray-500)"><?=h($t['full_name_ar'])?></div><?php endif;?>
        <?php if($t['email']):?><div style="font-size:11px;color:var(--gray-400)">📧 <?=h($t['email'])?></div><?php endif;?>
        <?php if($t['phone']):?><div style="font-size:11px;color:var(--gray-400)">📞 <?=h($t['phone'])?></div><?php endif;?>
      </div>
    </div>
    <div style="background:var(--green-pale);border-radius:10px;padding:10px 12px;font-size:12px">
      <div style="font-weight:600;color:var(--green-dark);margin-bottom:4px">📚 <?=h($t['prog_name']??'')?></div>
      <div style="color:var(--gray-600)">📅 <?=str_replace(',', ' / ', $t['days']??'')?></div>
      <div style="color:var(--gray-600)">⏰ <?=substr($t['time_start']??'',0,5)?>–<?=substr($t['time_end']??'',0,5)?></div>
    </div>
    <div style="display:flex;gap:12px;margin-top:12px">
      <div style="flex:1;text-align:center;background:var(--info-pale);border-radius:8px;padding:8px">
        <div style="font-size:1.2rem;font-weight:800;color:var(--info)"><?=$t['students']?></div>
        <div style="font-size:10px;color:var(--gray-500)"><?=$isRtl?'طالب':'Students'?></div>
      </div>
      <div style="flex:1;text-align:center;background:var(--green-pale);border-radius:8px;padding:8px">
        <div style="font-size:1.2rem;font-weight:800;color:var(--green-main)"><?=$t['programs']?></div>
        <div style="font-size:10px;color:var(--gray-500)"><?=$isRtl?'برنامج':'Programs'?></div>
      </div>
    </div>
  </div>
  <?php endforeach;?>
</div>
<?php endif;?>

</main>
</div>
<?php include '../includes/footer.php'; ?>
