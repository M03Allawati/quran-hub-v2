<?php
require_once '/var/www/html/config.php';
requireRole('student');
$lang   = $_SESSION['lang'] ?? 'en';
$isRtl  = $lang === 'ar';
$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$pageTitle = ($isRtl ? 'مساجد محافظتي — ' : 'My Governorate Mosques — ') . APP_NAME;

// Get student's mosque & governorate
$student = $pdo->prepare(
    "SELECT s.*, m.id as mosque_id, m.governorate, m.name_en as mosque_en, m.name_ar as mosque_ar
     FROM students s
     JOIN mosques m ON m.id = s.mosque_id
     WHERE s.user_id = ? LIMIT 1"
);
$student->execute([$userId]);
$student = $student->fetch();
$myGov = $student['governorate'] ?? '';

// All mosques in student's governorate
$mosques = [];
if ($myGov) {
    $m = $pdo->prepare("SELECT * FROM mosques WHERE governorate = ? ORDER BY is_grand DESC, name_en");
    $m->execute([$myGov]);
    $mosques = $m->fetchAll();
}

include '/var/www/html/includes/header.php';
?>
<div class="layout">
<?php include '/var/www/html/includes/sidebar.php'; ?>
<main class="main-content">

  <div style="margin-bottom:24px">
    <h1 style="font-size:1.5rem;font-weight:800;margin:0 0 4px">🕌 <?= $isRtl?'مساجد محافظتي':'My Governorate Mosques' ?></h1>
    <p style="color:var(--gray-500);font-size:13px;margin:0">
      📍 <?= h($myGov ?: ($isRtl?'غير محدد':'Not set')) ?> &nbsp;·&nbsp;
      <?= count($mosques) ?> <?= $isRtl?'مسجد':'mosques' ?>
    </p>
  </div>

  <!-- My mosque highlight -->
  <?php if ($student): ?>
  <div style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));color:#fff;border-radius:14px;padding:18px 22px;margin-bottom:22px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div style="font-size:2.5rem">🕌</div>
    <div style="flex:1">
      <div style="font-size:11px;opacity:.75;margin-bottom:2px"><?= $isRtl?'مسجدك المسجّل:':'Your registered mosque:' ?></div>
      <div style="font-weight:800;font-size:17px;color:var(--gold-light)"><?= h($isRtl?$student['mosque_ar']:$student['mosque_en']) ?></div>
      <div style="font-size:12px;opacity:.8;margin-top:2px">📍 <?= h($myGov) ?></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (empty($mosques)): ?>
  <div style="text-align:center;padding:60px;color:var(--gray-300)">
    <div style="font-size:48px">🕌</div>
    <div style="margin-top:12px;font-size:15px"><?= $isRtl?'لا توجد مساجد في محافظتك':'No mosques found in your governorate' ?></div>
  </div>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px">
    <?php foreach ($mosques as $m):
      $isMyMosque = ($student && $m['id'] == $student['mosque_id']);
    ?>
    <div style="background:#fff;border:<?= $isMyMosque?'2px solid var(--green-main)':'1px solid var(--gray-100)' ?>;border-radius:14px;padding:18px;transition:all .2s;position:relative"
         onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='var(--shadow-md)'"
         onmouseout="this.style.transform='';this.style.boxShadow=''">

      <?php if ($isMyMosque): ?>
      <div style="position:absolute;top:12px;<?= $isRtl?'left':'right' ?>:12px;background:var(--green-main);color:#fff;font-size:10px;padding:3px 10px;border-radius:99px;font-weight:700">
        ✓ <?= $isRtl?'مسجدك':'Your Mosque' ?>
      </div>
      <?php endif; ?>

      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
        <span style="font-size:26px">🕌</span>
        <div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end;margin-<?= $isRtl?'left':'right' ?>:8px">
          <?php if ($m['is_grand']): ?>
          <span style="font-size:10px;background:var(--gold-pale);color:var(--gold-dark);padding:2px 7px;border-radius:99px;font-weight:600"><?= $isRtl?'جامع كبير':'Grand' ?></span>
          <?php endif; ?>
          <?php if ($m['is_historic']): ?>
          <span style="font-size:10px;background:#ede9fe;color:#5b21b6;padding:2px 7px;border-radius:99px;font-weight:600"><?= $isRtl?'أثري':'Historic' ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div style="font-weight:700;font-size:14px;color:var(--green-dark);margin-bottom:3px">
        <?= h($isRtl ? $m['name_ar'] : $m['name_en']) ?>
      </div>
      <div style="font-size:11px;color:var(--gray-300);margin-bottom:10px">
        <?= h($isRtl ? $m['name_en'] : $m['name_ar']) ?>
      </div>

      <div style="font-size:12px;color:var(--gray-700);display:flex;flex-direction:column;gap:3px">
        <?php if ($m['wilayat']): ?>
        <span>📍 <?= h($m['wilayat']) ?></span>
        <?php endif; ?>
        <?php if ($m['location']): ?>
        <span style="color:var(--gray-500)">🏘️ <?= h($m['location']) ?></span>
        <?php endif; ?>
        <?php if ($m['capacity']): ?>
        <span>👥 <?= number_format($m['capacity']) ?> <?= $isRtl?'مصلّي':'capacity' ?></span>
        <?php endif; ?>
        <?php if ($m['phone']): ?>
        <span>📞 <?= h($m['phone']) ?></span>
        <?php endif; ?>
        <?php if ($m['established_year']): ?>
        <span>📅 <?= $m['established_year'] ?></span>
        <?php endif; ?>
      </div>

      <?php if (!empty($m['latitude']) && !empty($m['longitude'])): ?>
      <a href="https://maps.google.com/?q=<?= $m['latitude'] ?>,<?= $m['longitude'] ?>" target="_blank"
         style="display:inline-block;margin-top:10px;font-size:11px;color:var(--info);font-weight:600;text-decoration:none">
        🗺️ <?= $isRtl?'على الخريطة':'View on Maps' ?> →
      </a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</main>
</div>
<?php include '/var/www/html/includes/footer.php'; ?>
