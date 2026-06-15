<?php
require_once dirname(__DIR__) . '/config.php';
requireRole('parent');
$lang   = $_SESSION['lang'] ?? 'en';
$isRtl  = $lang === 'ar';
$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$pageTitle = ($isRtl ? 'الجدول الدراسي — ' : 'Class Schedule — ') . APP_NAME;

$children = $pdo->prepare(
    "SELECT s.*, m.name_en as mosque_en, m.name_ar as mosque_ar, m.governorate
     FROM students s JOIN mosques m ON m.id=s.mosque_id
     WHERE s.parent_id=? AND s.is_active=1 ORDER BY s.full_name"
);
$children->execute([$userId]);
$children = $children->fetchAll();

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<h1 style="font-size:1.5rem;font-weight:800;margin:0 0 4px">📅 <?= $isRtl?'الجدول الدراسي':'Class Schedule' ?></h1>
<p style="color:var(--gray-500);font-size:13px;margin:0 0 24px"><?= $isRtl?'جدول برامج القرآن لأطفالك':'Weekly Quran program schedule for your children' ?></p>

<?php if (empty($children)): ?>
<div style="text-align:center;padding:60px;color:var(--gray-300)">
  <div style="font-size:48px">📅</div>
  <div style="margin-top:12px"><?= $isRtl?'لا يوجد أطفال مسجّلون':'No children registered' ?></div>
  <a href="/parent/children.php" class="btn btn-primary" style="margin-top:16px"><?= $isRtl?'أضف طفلاً':'Add Child' ?></a>
</div>
<?php else: ?>

<!-- Weekly schedule view -->
<?php
$days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];
$daysAr = ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة'];
?>

<?php foreach ($children as $child):
  $progs = $pdo->prepare(
      "SELECT mp.days, mp.time_start, mp.time_end, mp.program_type, mp.name_en, mp.name_ar,
              u.full_name as teacher_name
       FROM program_enrollments pe
       JOIN mosque_programs mp ON mp.id=pe.program_id
       LEFT JOIN users u ON u.id=mp.teacher_id
       WHERE pe.student_id=? AND pe.status='active'"
  );
  $progs->execute([$child['id']]);
  $programs = $progs->fetchAll();

  // Map days to programs
  $dayMap = [];
  foreach ($programs as $p) {
      foreach (explode(',', $p['days']) as $d) {
          $dayMap[trim($d)] = $p;
      }
  }
?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px;margin-bottom:20px">
  <div style="font-weight:700;font-size:15px;color:var(--green-dark);margin-bottom:16px">
    <?= strtoupper(substr($child['full_name'],0,1)) ?>
    <?= h($child['full_name']) ?>
    <span style="font-size:12px;color:var(--gray-500);font-weight:400;margin-<?=$isRtl?'right':'left'?>:8px">
      🕌 <?= h($isRtl?$child['mosque_ar']:$child['mosque_en']) ?>
    </span>
  </div>

  <?php if (empty($programs)): ?>
  <div style="padding:16px;background:var(--warning-pale);border-radius:10px;font-size:13px;color:var(--warning)">
    ⚠️ <?= $isRtl?'لم يُسجَّل في أي برنامج بعد':'Not enrolled in any program yet' ?>
    <a href="/parent/children.php" style="color:var(--green-main);font-weight:700;margin-<?=$isRtl?'right':'left'?>:8px"><?=$isRtl?'الذهاب للأطفال':'Go to Children'?></a>
  </div>
  <?php else: ?>
  <!-- Weekly grid -->
  <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:6px">
    <?php foreach ($days as $i => $day): ?>
    <div>
      <div style="text-align:center;font-size:11px;font-weight:700;color:var(--gray-500);margin-bottom:6px;padding:4px;background:var(--gray-50);border-radius:6px">
        <?= $isRtl?$daysAr[$i]:substr($day,0,3) ?>
      </div>
      <?php if (isset($dayMap[$day])): $p=$dayMap[$day]; ?>
      <div style="background:var(--green-pale);border:1px solid var(--green-light);border-radius:8px;padding:8px 6px;text-align:center">
        <div style="font-size:10px;font-weight:700;color:var(--green-dark)"><?= substr($p['time_start'],0,5) ?></div>
        <div style="font-size:10px;color:var(--green-main)">–<?= substr($p['time_end'],0,5) ?></div>
        <?php if ($p['teacher_name']): ?>
        <div style="font-size:9px;color:var(--gray-500);margin-top:3px">👨‍🏫 <?= h(explode(' ',$p['teacher_name'])[0]) ?></div>
        <?php else: ?>
        <div style="font-size:9px;color:var(--warning)">⏳ TBD</div>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div style="background:var(--gray-50);border-radius:8px;padding:8px 6px;text-align:center;min-height:52px">
        <div style="font-size:10px;color:var(--gray-300)">—</div>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <!-- Saturday = rest -->
  <div style="margin-top:8px;font-size:11px;color:var(--gray-400);text-align:center">
    🌙 <?= $isRtl?'السبت — يوم راحة':'Saturday — Day off' ?>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; endif; ?>

</main>
</div>
<?php include '../includes/footer.php'; ?>
