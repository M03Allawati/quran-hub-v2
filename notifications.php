<?php
require_once __DIR__ . '/config.php';
requireLogin();
$lang = $_SESSION['lang'] ?? 'en';
$isRtl = $lang === 'ar';
$pdo = getPDO();
$userId = $_SESSION['user_id'];
$pageTitle = ($isRtl?'الإشعارات — ':'Notifications — ') . APP_NAME;

// Mark all as read
if ($_GET['markread'] ?? false) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$userId]);
    header('Location: /notifications.php'); exit;
}

$notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
$notifs->execute([$userId]);
$notifs = $notifs->fetchAll();

// Mark as read on view
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$userId]);

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">

<div class="page-header flex-center justify-between">
  <div>
    <h1 class="page-title">🔔 <?= $isRtl?'الإشعارات':'Notifications' ?></h1>
    <p class="page-subtitle"><?= count($notifs) ?> <?= $isRtl?'إشعار':'notifications' ?></p>
  </div>
  <?php if ($notifs): ?>
  <a href="/notifications.php?markread=1" class="btn btn-outline btn-sm"><?= $isRtl?'تحديد الكل كمقروء':'Mark all as read' ?></a>
  <?php endif; ?>
</div>

<?php if (empty($notifs)): ?>
<div class="card" style="text-align:center;padding:4rem">
  <div style="font-size:3rem;margin-bottom:1rem">🔔</div>
  <h3 style="color:var(--green-dark)"><?= $isRtl?'لا توجد إشعارات':'No notifications yet' ?></h3>
  <p style="color:var(--gray-500)"><?= $isRtl?'ستظهر الإشعارات هنا عند وجود تحديثات':'Notifications will appear here when there are updates' ?></p>
</div>
<?php else: ?>
<div class="card">
  <div style="padding:.5rem 0">
    <?php foreach ($notifs as $n): ?>
    <?php
    $icon = match($n['type']) {
      'success' => '✅', 'warning' => '⚠️', 'alert' => '🚨', default => 'ℹ️'
    };
    $bgClass = match($n['type']) {
      'success' => 'success', 'warning' => 'warning', 'alert' => 'danger', default => 'info'
    };
    ?>
    <div style="display:flex;align-items:flex-start;gap:1rem;padding:1.1rem 1.5rem;border-bottom:1px solid var(--gray-100);<?= !$n['is_read']?'background:var(--green-pale)':'' ?>">
      <div style="font-size:1.4rem;margin-top:.1rem"><?= $icon ?></div>
      <div style="flex:1">
        <div style="font-weight:700;color:var(--green-dark);margin-bottom:.2rem"><?= h($n['title']) ?></div>
        <div style="color:var(--gray-700);font-size:.9rem"><?= h($n['message']) ?></div>
        <div style="font-size:.78rem;color:var(--gray-500);margin-top:.3rem">
          <?= date('d M Y, H:i', strtotime($n['created_at'])) ?>
          <?php if(!$n['is_read']): ?> <span class="badge badge-green" style="font-size:.7rem">NEW</span><?php endif; ?>
        </div>
      </div>
      <span class="badge badge-<?= $n['type']==='success'?'green':($n['type']==='warning'?'warning':($n['type']==='alert'?'red':'blue')) ?>">
        <?= ucfirst($n['type']) ?>
      </span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

</main>
</div>
<?php include 'includes/footer.php'; ?>
