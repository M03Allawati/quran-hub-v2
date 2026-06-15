<?php
require_once __DIR__ . '/../config.php';
requireLogin();
$lang   = $_SESSION['lang'] ?? 'en';
$isRtl  = $lang === 'ar';
$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'];
$pageTitle = ($isRtl ? 'الإعلانات — ' : 'Announcements — ') . APP_NAME;

// ── Helper: build announcement email HTML ────────────────────
function buildAnnouncementEmail(string $toName, string $title, string $body, string $mosqueAr=''): string {
    $url = BASE_URL . '/admin/announcements.php';
    return "<!DOCTYPE html><html dir='rtl' lang='ar'><head><meta charset='UTF-8'>
<style>body{font-family:'Segoe UI',Arial,sans-serif;background:#f5f5f5;direction:rtl;margin:0}
.wrap{max-width:600px;margin:30px auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.1)}
.hdr{background:linear-gradient(135deg,#1B4332,#40916C);padding:30px 32px;color:#fff}
.hdr h1{margin:0;font-size:20px;color:#F4D03F}.hdr p{margin:6px 0 0;opacity:.8;font-size:13px}
.body{padding:28px 32px}
.ann-box{background:#f0f9f4;border:1px solid #74C69D;border-radius:10px;padding:20px 24px;margin:16px 0}
.ann-title{font-size:18px;font-weight:700;color:#1B4332;margin-bottom:10px}
.ann-body{color:#374151;font-size:14px;line-height:1.7}
.btn{display:inline-block;background:#2D6A4F;color:#fff!important;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;margin-top:20px}
.footer{padding:14px 32px;background:#f8f9fa;text-align:center;font-size:11px;color:#9ca3af;border-top:1px solid #e5e7eb}
</style></head><body><div class='wrap'>
<div class='hdr'><h1>🕌 إعلان جديد من مركز القرآن</h1><p>$mosqueAr</p></div>
<div class='body'><p style='color:#374151'>مرحباً $toName،</p>
<div class='ann-box'><div class='ann-title'>$title</div><div class='ann-body'>$body</div></div>
<a href='$url' class='btn'>عرض الإعلان</a></div>
<div class='footer'>🕌 Digital Quran Center Hub — سلطنة عُمان</div>
</div></body></html>";
}

// ── Handle Admin POST ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $titleEn   = trim($_POST['title_en'] ?? '');
        $titleAr   = trim($_POST['title_ar'] ?? '');
        $bodyEn    = trim($_POST['body_en'] ?? '');
        $bodyAr    = trim($_POST['body_ar'] ?? '');
        $audience  = $_POST['audience'] ?? 'all';
        $mosqueId  = $_POST['mosque_id'] ? (int)$_POST['mosque_id'] : null;
        $sendEmail = isset($_POST['send_email']) ? 1 : 0;
        $isPinned  = isset($_POST['is_pinned']) ? 1 : 0;
        $expiresAt = $_POST['expires_at'] ?: null;

        if (!$titleAr || !$bodyAr) {
            setFlash('danger', $isRtl?'العنوان والمحتوى بالعربية مطلوبان':'Arabic title and body are required');
        } else {
            $pdo->prepare(
                "INSERT INTO announcements (created_by,mosque_id,title_en,title_ar,body_en,body_ar,audience,send_email,is_pinned,expires_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            )->execute([$userId,$mosqueId,$titleEn,$titleAr,$bodyEn,$bodyAr,$audience,$sendEmail,$isPinned,$expiresAt]);
            $annId = $pdo->lastInsertId();

            // Notify via in-app notifications
            $targetQuery = "SELECT u.id, u.email, u.full_name FROM users u WHERE u.is_active=1";
            if ($audience === 'teachers') $targetQuery .= " AND u.role='teacher'";
            elseif ($audience === 'parents') $targetQuery .= " AND u.role='parent'";
            if ($mosqueId) $targetQuery .= " AND u.mosque_id=$mosqueId";

            $targets = $pdo->query($targetQuery)->fetchAll();
            $mosque = $mosqueId ? $pdo->prepare("SELECT name_ar,name_en FROM mosques WHERE id=?")->execute([$mosqueId]) : null;

            foreach ($targets as $t) {
                // In-app notification
                $pdo->prepare(
                    "INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'info')"
                )->execute([$t['id'], $titleAr, mb_substr($bodyAr,0,200)]);

                // Email queue
                if ($sendEmail) {
                    $html = buildAnnouncementEmail($t['full_name'], $titleAr, $bodyAr);
                    $pdo->prepare(
                        "INSERT INTO email_queue (to_email,to_name,subject,body_html) VALUES (?,?,?,?)"
                    )->execute([$t['email'], $t['full_name'], "📢 $titleAr", $html]);
                }
            }

            // Update email count
            $pdo->prepare("UPDATE announcements SET email_sent_count=? WHERE id=?")
                ->execute([count($targets), $annId]);

            // Audit log
            $pdo->prepare("INSERT INTO audit_log (user_id,action,table_name,record_id) VALUES (?,?,?,?)")
                ->execute([$userId,'create_announcement','announcements',$annId]);

            setFlash('success', $isRtl?"تم إرسال الإعلان لـ ".count($targets)." مستخدم":"Announcement sent to ".count($targets)." users");
        }
        header('Location: /admin/announcements.php'); exit;
    }

    if ($action === 'toggle_pin') {
        $annId = (int)$_POST['ann_id'];
        $pdo->prepare("UPDATE announcements SET is_pinned = 1-is_pinned WHERE id=?")->execute([$annId]);
        header('Location: /admin/announcements.php'); exit;
    }

    if ($action === 'delete') {
        $annId = (int)$_POST['ann_id'];
        $pdo->prepare("UPDATE announcements SET is_active=0 WHERE id=?")->execute([$annId]);
        header('Location: /admin/announcements.php'); exit;
    }
}

// ── Load data ────────────────────────────────────────────────
$query = "SELECT a.*, u.full_name as author, m.name_ar as mosque_ar, m.name_en as mosque_en
          FROM announcements a
          LEFT JOIN users u ON u.id=a.created_by
          LEFT JOIN mosques m ON m.id=a.mosque_id
          WHERE a.is_active=1";
if ($role !== 'admin') {
    $query .= " AND (a.audience='all' OR a.audience='{$role}s')
                AND (a.expires_at IS NULL OR a.expires_at > NOW())";
}
$query .= " ORDER BY a.is_pinned DESC, a.created_at DESC LIMIT 50";
$anns = $pdo->query($query)->fetchAll();
$mosques = $pdo->query("SELECT id, name_en, name_ar FROM mosques WHERE is_active=1 ORDER BY name_en")->fetchAll();

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:1.5rem;font-weight:800;margin:0">📢 <?= $isRtl?'الإعلانات':'Announcements' ?></h1>
      <p style="color:var(--gray-500);font-size:13px;margin:4px 0 0"><?= $isRtl?'إعلانات تصل لجميع المستخدمين عبر البريد والإشعارات':'Announcements sent to all users via email & notifications' ?></p>
    </div>
    <?php if ($role === 'admin'): ?>
    <button onclick="document.getElementById('newAnnModal').style.display='flex'"
      class="btn btn-primary">
      + <?= $isRtl?'إعلان جديد':'New Announcement' ?>
    </button>
    <?php endif; ?>
  </div>

  <!-- Announcements list -->
  <div style="display:flex;flex-direction:column;gap:14px">
    <?php if (empty($anns)): ?>
    <div style="text-align:center;padding:60px;color:var(--gray-300)">
      <div style="font-size:48px">📢</div>
      <div style="margin-top:12px"><?= $isRtl?'لا توجد إعلانات':'No announcements yet' ?></div>
    </div>
    <?php else: foreach ($anns as $ann): ?>
    <div style="background:#fff;border:1px solid var(--gray-100);border-radius:var(--radius-md);padding:20px 22px;border-<?= $isRtl?'right':'left' ?>:4px solid var(--green-main)">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
        <div style="flex:1">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap">
            <?php if ($ann['is_pinned']): ?><span style="font-size:12px;background:var(--gold-pale);color:var(--gold-dark);padding:2px 8px;border-radius:99px;font-weight:600">📌 <?= $isRtl?'مثبّت':'Pinned' ?></span><?php endif; ?>
            <span style="font-size:11px;background:var(--green-pale);color:var(--green-dark);padding:2px 8px;border-radius:99px;font-weight:600"><?= h($ann['audience'] === 'all' ? ($isRtl?'للجميع':'Everyone') : ucfirst($ann['audience'])) ?></span>
            <?php if ($ann['mosque_ar']): ?><span style="font-size:11px;color:var(--gray-500)">🕌 <?= h($isRtl?$ann['mosque_ar']:$ann['mosque_en']) ?></span><?php endif; ?>
          </div>
          <h3 style="margin:0 0 8px;font-size:16px;font-weight:700;color:var(--green-dark)"><?= h($isRtl&&$ann['title_ar']?$ann['title_ar']:$ann['title_en']) ?></h3>
          <p style="margin:0;font-size:14px;color:var(--gray-700);line-height:1.6"><?= nl2br(h(mb_substr($isRtl&&$ann['body_ar']?$ann['body_ar']:$ann['body_en'],0,300))) ?></p>
        </div>
        <?php if ($role === 'admin'): ?>
        <div style="display:flex;gap:6px;flex-shrink:0">
          <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="toggle_pin"><input type="hidden" name="ann_id" value="<?= $ann['id'] ?>">
            <button type="submit" style="padding:5px 10px;border-radius:8px;border:1px solid var(--gray-100);background:#fff;cursor:pointer;font-size:13px" title="<?= $isRtl?'تثبيت':'Pin' ?>">📌</button>
          </form>
          <form method="POST" onsubmit="return confirm('<?= $isRtl?'حذف هذا الإعلان؟':'Delete this announcement?' ?>
        <?= csrfField() ?>')">
            <input type="hidden" name="action" value="delete"><input type="hidden" name="ann_id" value="<?= $ann['id'] ?>">
            <button type="submit" style="padding:5px 10px;border-radius:8px;border:1px solid var(--danger);background:#fff;cursor:pointer;font-size:13px;color:var(--danger)">🗑</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <div style="margin-top:12px;font-size:11px;color:var(--gray-300);display:flex;gap:14px;flex-wrap:wrap">
        <span>✍️ <?= h($ann['author']) ?></span>
        <span>📅 <?= date('Y/m/d H:i', strtotime($ann['created_at'])) ?></span>
        <?php if ($ann['email_sent_count']): ?><span>📧 <?= $ann['email_sent_count'] ?> <?= $isRtl?'مستلم':'recipients' ?></span><?php endif; ?>
        <?php if ($ann['expires_at']): ?><span>⏳ <?= $isRtl?'ينتهي':'Expires' ?>: <?= date('Y/m/d', strtotime($ann['expires_at'])) ?></span><?php endif; ?>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</main>
</div>

<!-- New Announcement Modal -->
<?php if ($role === 'admin'): ?>
<div id="newAnnModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:var(--radius-lg);padding:28px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lg)">
    <h3 style="margin:0 0 20px;color:var(--green-dark)">📢 <?= $isRtl?'إعلان جديد':'New Announcement' ?></h3>
    <form method="POST">
        <?= csrfField() ?>
      <input type="hidden" name="action" value="create">
      <div style="display:grid;gap:14px">
        <div>
          <label style="font-size:12px;color:var(--gray-500);display:block;margin-bottom:4px">العنوان بالعربية *</label>
          <input name="title_ar" required placeholder="عنوان الإعلان..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:14px;font-family:inherit">
        </div>
        <div>
          <label style="font-size:12px;color:var(--gray-500);display:block;margin-bottom:4px">Title in English</label>
          <input name="title_en" placeholder="Announcement title..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:14px;font-family:inherit">
        </div>
        <div>
          <label style="font-size:12px;color:var(--gray-500);display:block;margin-bottom:4px">المحتوى بالعربية *</label>
          <textarea name="body_ar" required rows="4" placeholder="محتوى الإعلان..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:14px;font-family:inherit;resize:vertical"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label style="font-size:12px;color:var(--gray-500);display:block;margin-bottom:4px"><?= $isRtl?'الجمهور المستهدف':'Audience' ?></label>
            <select name="audience" style="width:100%;padding:9px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:14px;font-family:inherit;background:#fff">
              <option value="all"><?= $isRtl?'الجميع':'Everyone' ?></option>
              <option value="teachers"><?= $isRtl?'المعلمون':'Teachers' ?></option>
              <option value="parents"><?= $isRtl?'أولياء الأمور':'Parents' ?></option>
            </select>
          </div>
          <div>
            <label style="font-size:12px;color:var(--gray-500);display:block;margin-bottom:4px"><?= $isRtl?'المسجد (اختياري)':'Mosque (optional)' ?></label>
            <select name="mosque_id" style="width:100%;padding:9px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:14px;font-family:inherit;background:#fff">
              <option value=""><?= $isRtl?'كل المساجد':'All mosques' ?></option>
              <?php foreach ($mosques as $m): ?>
              <option value="<?= $m['id'] ?>"><?= h($isRtl?$m['name_ar']:$m['name_en']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label style="font-size:12px;color:var(--gray-500);display:block;margin-bottom:4px"><?= $isRtl?'تاريخ الانتهاء (اختياري)':'Expiry date (optional)' ?></label>
          <input type="date" name="expires_at" style="padding:9px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:14px;font-family:inherit">
        </div>
        <div style="display:flex;gap:20px">
          <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
            <input type="checkbox" name="send_email" checked> <?= $isRtl?'إرسال بريد إلكتروني':'Send email notification' ?>
          </label>
          <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
            <input type="checkbox" name="is_pinned"> <?= $isRtl?'تثبيت الإعلان':'Pin announcement' ?>
          </label>
        </div>
        <div style="display:flex;gap:10px">
          <button type="submit" class="btn btn-primary" style="flex:1"><?= $isRtl?'إرسال الإعلان':'Send Announcement' ?></button>
          <button type="button" onclick="document.getElementById('newAnnModal').style.display='none'" class="btn btn-secondary">
            <?= $isRtl?'إلغاء':'Cancel' ?>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
