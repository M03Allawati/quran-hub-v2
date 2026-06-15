<?php
require_once __DIR__ . '/../config.php';
requireRole('teacher');
$lang = $_SESSION['lang'] ?? 'en';
$isRtl = $lang === 'ar';
$pdo = getPDO();
$userId = $_SESSION['user_id'];
$pageTitle = ($isRtl?'فصولي — ':'My Classes — ') . APP_NAME;

$classes = $pdo->prepare("SELECT c.*, m.name_en as mosque_name, COUNT(e.id) as enrolled FROM classes c JOIN mosques m ON c.mosque_id=m.id LEFT JOIN enrollments e ON c.id=e.class_id AND e.status='active' WHERE c.teacher_id=? GROUP BY c.id ORDER BY c.time_start");
$classes->execute([$userId]);
$classes = $classes->fetchAll();

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<div class="page-header">
  <h1 class="page-title">📚 <?= $isRtl?'فصولي':'My Classes' ?></h1>
  <p class="page-subtitle"><?= count($classes) ?> <?= $isRtl?'فصل دراسي':'classes assigned' ?></p>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.5rem">
<?php foreach ($classes as $c): ?>
<?php
$students = $pdo->prepare("SELECT s.* FROM students s JOIN enrollments e ON s.id=e.student_id WHERE e.class_id=? AND e.status='active' ORDER BY s.full_name");
$students->execute([$c['id']]);
$students = $students->fetchAll();
?>
<div class="card">
  <div style="background:linear-gradient(135deg,var(--green-dark),var(--green-main));padding:1.2rem 1.4rem">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
      <div>
        <div style="color:rgba(255,255,255,.7);font-size:.78rem;text-transform:uppercase;letter-spacing:.06em"><?= h($c['subject']) ?></div>
        <div style="color:#fff;font-weight:700;font-size:1rem;margin-top:.2rem"><?= h($c['name_en']) ?></div>
        <?php if($c['name_ar']): ?><div style="color:rgba(255,255,255,.75);font-family:var(--font-ar);font-size:.9rem"><?= h($c['name_ar']) ?></div><?php endif; ?>
      </div>
      <span class="badge badge-gold"><?= h($c['level']) ?></span>
    </div>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem">
      <div style="background:var(--gray-50);padding:.75rem;border-radius:var(--radius-sm);text-align:center">
        <div style="font-size:1.3rem;font-weight:800;color:var(--green-dark)"><?= $c['enrolled'] ?></div>
        <div style="font-size:.78rem;color:var(--gray-500)"><?= $isRtl?'طالب':'Students' ?></div>
      </div>
      <div style="background:var(--gray-50);padding:.75rem;border-radius:var(--radius-sm);text-align:center">
        <div style="font-size:.88rem;font-weight:700;color:var(--green-dark)"><?= substr($c['time_start'],0,5) ?></div>
        <div style="font-size:.78rem;color:var(--gray-500)"><?= substr($c['time_end'],0,5) ?> GMT+4</div>
      </div>
    </div>
    <div style="font-size:.83rem;color:var(--gray-500);margin-bottom:1rem">
      📅 <?= h(str_replace(',',', ',$c['schedule_day'])) ?><br>
      🕌 <?= h($c['mosque_name']) ?>
    </div>
    <!-- Mini student list -->
    <?php if ($students): ?>
    <div style="font-size:.82rem;font-weight:600;color:var(--gray-700);margin-bottom:.4rem"><?= $isRtl?'الطلاب:':'Students:' ?></div>
    <div style="display:flex;flex-wrap:wrap;gap:.3rem">
      <?php foreach ($students as $st): ?>
      <span style="background:var(--green-pale);color:var(--green-dark);padding:.2rem .6rem;border-radius:99px;font-size:.77rem">
        <?= $st['gender']==='male'?'👦':'👧' ?> <?= h(explode(' ',$st['full_name'])[0]) ?>
      </span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div style="display:flex;gap:.5rem;margin-top:1rem">
      <a href="/teacher/attendance.php?class=<?= $c['id'] ?>" class="btn btn-primary btn-sm" style="flex:1;justify-content:center">✅ <?= $isRtl?'حضور':'Attendance' ?></a>
      <a href="/teacher/progress.php?class=<?= $c['id'] ?>" class="btn btn-outline btn-sm" style="flex:1;justify-content:center">📈 <?= $isRtl?'تقدم':'Progress' ?></a>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php if (empty($classes)): ?>
<div class="card" style="text-align:center;padding:3rem;grid-column:1/-1">
  <div style="font-size:2.5rem;margin-bottom:1rem">📚</div>
  <p style="color:var(--gray-500)"><?= $isRtl?'لم يتم تعيين أي فصول لك بعد':'No classes assigned to you yet' ?></p>
</div>
<?php endif; ?>
</div>

</main>
</div>
<?php include '../includes/footer.php'; ?>
