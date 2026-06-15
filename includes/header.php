<?php
// includes/header.php - Shared across all pages
if (!defined('APP_NAME')) { require_once __DIR__ . '/../config.php'; }
$flash = getFlash();
$notifCount = isLoggedIn() ? unreadNotifCount() : 0;
$lang = $_SESSION['lang'] ?? 'en';
$isRtl = $lang === 'ar';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($pageTitle ?? APP_NAME) ?></title>
  <meta name="description" content="Digital Quran Center Hub - Managing Quranic Education in Oman">
  <link rel="stylesheet" href="/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    <?php if($isRtl): ?>
    .sidebar { border-right: none; border-left: 1px solid var(--gray-100); }
    .sidebar-link.active { border-left: none; border-right: 3px solid var(--green-main); }
    .alert { border-left: none; border-right: 4px solid; }
    .alert-success { border-right-color: var(--success); }
    .alert-danger  { border-right-color: var(--danger); }
    .alert-warning { border-right-color: var(--warning); }
    .alert-info    { border-right-color: var(--info); }
    <?php endif; ?>
  </style>
</head>
<body class="<?= $isRtl ? 'rtl' : '' ?>">

<!-- NAVBAR -->
<nav class="navbar">
  <a href="/index.php" class="navbar-brand">
    <div class="logo-icon">🕌</div>
    <span><?= $isRtl ? APP_NAME_AR : APP_NAME ?></span>
  </a>

  <ul class="navbar-nav">
    <li><a href="/index.php"><i class="fas fa-home"></i> <?= $isRtl ? 'الرئيسية' : 'Home' ?></a></li>
    <li><a href="/mosques.php"><i class="fas fa-mosque"></i> <?= $isRtl ? 'المساجد' : 'Mosques' ?></a></li>

    <?php if (isLoggedIn()): ?>
      <li><a href="/dashboard.php"><i class="fas fa-th-large"></i> <?= $isRtl ? 'لوحة التحكم' : 'Dashboard' ?></a></li>

      <?php
      // Messages unread count
      $msgUnread = 0;
      try {
          $mu = getPDO()->prepare(
              "SELECT COUNT(*) FROM messages m
               JOIN conversation_participants cp ON cp.conversation_id=m.conversation_id AND cp.user_id=?
               WHERE m.sender_id!=? AND m.created_at > COALESCE(cp.last_read_at,'2000-01-01') AND m.is_deleted=0"
          );
          $mu->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
          $msgUnread = (int)$mu->fetchColumn();
      } catch (\Throwable $e) {}
      ?>
      <li>
        <a href="/messages.php" style="position:relative">
          <i class="fas fa-comments"></i>
          <?php if ($msgUnread > 0): ?>
          <span style="position:absolute;top:-6px;<?= $isRtl?'left':'right' ?>:-8px;background:var(--gold-main);color:#fff;border-radius:99px;font-size:10px;font-weight:700;padding:1px 5px;min-width:16px;text-align:center"><?= $msgUnread ?></span>
          <?php endif; ?>
        </a>
      </li>

      <?php if ($notifCount > 0): ?>
      <li class="notif-badge">
        <a href="/notifications.php">
          <i class="fas fa-bell"></i>
          <span class="badge"><?= $notifCount ?></span>
        </a>
      </li>
      <?php endif; ?>

      <li>
        <a href="/logout.php" style="color:rgba(255,255,255,.7)">
          <i class="fas fa-sign-out-alt"></i> <?= $isRtl ? 'خروج' : 'Logout' ?>
        </a>
      </li>
    <?php else: ?>
      <li><a href="/login.php"><i class="fas fa-sign-in-alt"></i> <?= $isRtl ? 'دخول' : 'Login' ?></a></li>
      <li><a href="/register.php" class="btn-gold"><i class="fas fa-user-plus"></i> <?= $isRtl ? 'تسجيل' : 'Register' ?></a></li>
    <?php endif; ?>

    <li>
      <form method="POST" action="/lang.php" style="display:inline">
        <?= csrfField() ?>
        <input type="hidden" name="lang" value="<?= $isRtl ? 'en' : 'ar' ?>">
        <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI']) ?>">
        <button type="submit" class="lang-toggle">
          <?= $isRtl ? '🇬🇧 EN' : '🇴🇲 عربي' ?>
        </button>
      </form>
    </li>
  </ul>
</nav>

<?php if ($flash): ?>
<div class="alert alert-<?= h($flash['type']) ?>" style="margin:0;border-radius:0;padding:.9rem 2rem">
  <i class="fas fa-<?= $flash['type']==='success'?'check-circle':($flash['type']==='danger'?'times-circle':'info-circle') ?>"></i>
  <?= h($flash['msg']) ?>
</div>
<?php endif; ?>
