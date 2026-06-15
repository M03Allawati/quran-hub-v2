<?php
require_once __DIR__ . '/config.php';
requireLogin();
$lang = $_SESSION['lang'] ?? 'en';
$isRtl = $lang === 'ar';
$pageTitle = ($isRtl ? 'المساجد والمراكز — ' : 'Mosques & Centers — ') . APP_NAME;
$pdo = getPDO();
$mosques = $pdo->query("SELECT m.*, COUNT(DISTINCT c.id) as class_count, COUNT(DISTINCT s.id) as student_count FROM mosques m LEFT JOIN classes c ON m.id=c.mosque_id AND c.is_active=1 LEFT JOIN students s ON m.id=s.mosque_id AND s.is_active=1 WHERE m.is_active=1 GROUP BY m.id ORDER BY m.name_en")->fetchAll();
include 'includes/header.php';
?>

<div style="background:linear-gradient(135deg,var(--green-dark),var(--green-main));padding:3rem 2rem;text-align:center;color:#fff">
  <h1 style="font-size:2rem;font-weight:800;margin-bottom:.5rem">🕌 <?= $isRtl ? 'اكتشف المساجد والمراكز' : 'Explore Mosques & Centers' ?></h1>
  <p style="opacity:.8"><?= $isRtl ? 'مراكز تحفيظ القرآن الكريم في سلطنة عُمان' : 'Quranic learning centers across the Sultanate of Oman' ?></p>
</div>

<div class="mosques-grid" style="max-width:1200px;margin:0 auto;padding:2.5rem 2rem">
  <?php foreach ($mosques as $m): ?>
  <div class="mosque-card">
    <div class="mosque-card-img">🕌</div>
    <div class="mosque-card-body">
      <div class="mosque-name"><?= h($isRtl ? $m['name_ar'] : $m['name_en']) ?></div>
      <div class="mosque-name-ar" style="<?= $isRtl ? 'font-size:.9rem;color:var(--gray-500)' : 'font-family:var(--font-ar)' ?>">
        <?= h($isRtl ? $m['name_en'] : $m['name_ar']) ?>
      </div>
      <div class="mosque-meta mt-1">
        <i class="fas fa-map-marker-alt" style="color:var(--green-main)"></i>
        <?= h($m['location']) ?> | <?= h($m['wilayat']) ?>
      </div>
      <?php if($m['phone']): ?>
      <div class="mosque-meta"><i class="fas fa-phone" style="color:var(--gold-dark)"></i> <?= h($m['phone']) ?></div>
      <?php endif; ?>
      <div style="display:flex;gap:.75rem;margin-top:.85rem">
        <div class="stat-card" style="padding:.5rem .9rem;gap:.4rem;flex:1">
          <span style="font-size:1rem">📚</span>
          <div><div style="font-weight:700;font-size:.95rem"><?= $m['class_count'] ?></div><div style="font-size:.72rem;color:var(--gray-500)"><?= $isRtl ? 'فصول' : 'Classes' ?></div></div>
        </div>
        <div class="stat-card" style="padding:.5rem .9rem;gap:.4rem;flex:1">
          <span style="font-size:1rem">🎓</span>
          <div><div style="font-weight:700;font-size:.95rem"><?= $m['student_count'] ?></div><div style="font-size:.72rem;color:var(--gray-500)"><?= $isRtl ? 'طلاب' : 'Students' ?></div></div>
        </div>
      </div>
      <a href="/register.php?mosque=<?= $m['id'] ?>" class="btn btn-primary w-full mt-2">
        <?= $isRtl ? 'سجّل في هذا المركز' : 'Register at This Center' ?>
      </a>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php include 'includes/footer.php'; ?>
