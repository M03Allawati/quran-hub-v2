<?php
require_once __DIR__ . '/../config.php';
requireRole('admin');
$lang   = $_SESSION['lang'] ?? 'en';
$isRtl  = $lang === 'ar';
$pdo    = getPDO();
$pageTitle = ($isRtl ? 'مساجد عُمان — ' : 'Oman Mosques — ') . APP_NAME;

// Handle assign mosque admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'assign_admin') {
        $mosqueId    = (int)$_POST['mosque_id'];
        $adminUserId = (int)$_POST['admin_user_id'];

        // Get current admin to revert their role
        $cur = $pdo->prepare("SELECT admin_id FROM mosques WHERE id=?");
        $cur->execute([$mosqueId]); $cur = $cur->fetch();
        if ($cur && $cur['admin_id']) {
            $pdo->prepare("UPDATE users SET role='teacher' WHERE id=? AND role='mosque_admin'")->execute([$cur['admin_id']]);
        }

        // Assign new admin or clear
        $pdo->prepare("UPDATE mosques SET admin_id=? WHERE id=?")->execute([$adminUserId ?: null, $mosqueId]);
        if ($adminUserId) {
            $pdo->prepare("UPDATE users SET role='mosque_admin', mosque_id=? WHERE id=?")->execute([$mosqueId, $adminUserId]);
        }
        setFlash('success', $adminUserId
            ? ($isRtl ? '✅ تم تعيين مدير المسجد' : '✅ Mosque admin assigned')
            : ($isRtl ? '✅ تم إزالة مدير المسجد' : '✅ Mosque admin removed'));
        header('Location: /admin/mosques.php'); exit;
    }
}

$govFilter = trim($_GET['gov'] ?? '');
$search    = trim($_GET['search'] ?? '');
$grand     = isset($_GET['grand']) ? (int)$_GET['grand'] : -1;
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 24;

$where  = ['1=1'];
$params = [];
if ($govFilter) { $where[] = 'm.governorate = ?'; $params[] = $govFilter; }
if ($grand >= 0) { $where[] = 'm.is_grand = ?';   $params[] = $grand; }
if ($search)    { $where[] = '(m.name_en LIKE ? OR m.name_ar LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereStr = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM mosques m WHERE $whereStr");
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$stmt = $pdo->prepare("SELECT m.*, u.full_name as admin_name FROM mosques m LEFT JOIN users u ON u.id=m.admin_id WHERE $whereStr ORDER BY m.is_grand DESC, m.governorate, m.name_en LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, ($page-1)*$perPage]));
$mosques = $stmt->fetchAll();

// Users available as mosque admins (teachers or existing mosque_admins)
$availAdmins = $pdo->query("SELECT id, full_name, role, mosque_id FROM users WHERE role IN ('teacher','mosque_admin') AND is_active=1 ORDER BY full_name")->fetchAll();

$govStats = $pdo->query("SELECT governorate, COUNT(*) as cnt, SUM(is_grand) as grand_cnt FROM mosques GROUP BY governorate ORDER BY cnt DESC")->fetchAll();
$totals   = $pdo->query("SELECT COUNT(*) as total, SUM(is_grand) as grand, SUM(is_historic) as historic FROM mosques")->fetch();

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:1.5rem;font-weight:800;margin:0">🕌 <?= $isRtl?'مساجد عُمان':'Oman Mosques' ?></h1>
      <p style="color:var(--gray-500);font-size:13px;margin:4px 0 0"><?= $isRtl?'11 محافظة — كل مساجد سلطنة عُمان':'All mosques across 11 governorates' ?></p>
    </div>
  </div>

  <!-- Stats -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
    <?php foreach ([
      ['🕌', $totals['total']??0,   $isRtl?'إجمالي المساجد':'Total Mosques',   'var(--green-pale)',  'var(--green-dark)'],
      ['👑', $totals['grand']??0,   $isRtl?'الجوامع الكبرى':'Grand Mosques',   'var(--gold-pale)',   'var(--gold-dark)'],
      ['🏛️', $totals['historic']??0,$isRtl?'المساجد الأثرية':'Historic',        '#ede9fe',            '#5b21b6'],
      ['🗺️', count($govStats),      $isRtl?'محافظة':'Governorates',            'var(--info-pale)',   'var(--info)'],
    ] as [$icon,$val,$label,$bg,$color]): ?>
    <div style="background:<?=$bg?>;border-radius:12px;padding:14px 16px;text-align:center">
      <div style="font-size:1.6rem"><?=$icon?></div>
      <div style="font-size:1.5rem;font-weight:800;color:<?=$color?>"><?=number_format((int)$val)?></div>
      <div style="font-size:.78rem;color:var(--gray-700)"><?=$label?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="display:grid;grid-template-columns:200px 1fr;gap:16px;align-items:start">

    <!-- Governorates sidebar -->
    <div style="background:#fff;border:1px solid var(--gray-100);border-radius:12px;padding:12px">
      <div style="font-size:12px;font-weight:700;color:var(--green-dark);margin-bottom:10px;text-transform:uppercase;letter-spacing:.05em">
        🗺️ <?=$isRtl?'المحافظات':'Governorates'?>
      </div>
      <a href="?" style="display:flex;justify-content:space-between;padding:7px 10px;border-radius:8px;font-size:13px;text-decoration:none;color:var(--green-dark);font-weight:600;background:<?=!$govFilter?'var(--green-pale)':''?>;margin-bottom:3px">
        <span><?=$isRtl?'الكل':'All'?></span>
        <span style="font-size:11px;background:var(--green-main);color:#fff;padding:1px 7px;border-radius:99px"><?=$totalCount?></span>
      </a>
      <?php foreach ($govStats as $g): $active = $govFilter===$g['governorate']; ?>
      <a href="?gov=<?=urlencode($g['governorate'])?>"
         style="display:flex;justify-content:space-between;align-items:center;padding:6px 10px;border-radius:8px;font-size:12px;text-decoration:none;color:var(--dark);background:<?=$active?'var(--green-pale)':'transparent'?>;margin-bottom:1px;transition:background .15s"
         onmouseover="if('<?=$active?>'!='1')this.style.background='var(--gray-50)'"
         onmouseout="if('<?=$active?>'!='1')this.style.background='transparent'">
        <span><?=h($g['governorate'])?></span>
        <span style="font-size:10px;color:var(--gray-500)"><?=$g['cnt']?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Main content -->
    <div>
      <!-- Filters -->
      <form method="GET" style="background:#fff;border:1px solid var(--gray-100);border-radius:12px;padding:12px 16px;margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <?php if ($govFilter): ?><input type="hidden" name="gov" value="<?=h($govFilter)?>"><?php endif; ?>
        <input name="search" value="<?=h($search)?>" placeholder="🔍 <?=$isRtl?'ابحث عن مسجد...':'Search mosque...'?>"
          style="flex:1;min-width:160px;padding:7px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:13px;font-family:inherit">
        <select name="grand" style="padding:7px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:13px;font-family:inherit;background:#fff">
          <option value="-1"><?=$isRtl?'الكل':'All types'?></option>
          <option value="1" <?=$grand===1?'selected':''?>><?=$isRtl?'جوامع كبرى':'Grand only'?></option>
          <option value="0" <?=$grand===0?'selected':''?>><?=$isRtl?'عادية':'Regular'?></option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><?=$isRtl?'بحث':'Search'?></button>
        <a href="<?=$govFilter?'?gov='.urlencode($govFilter):'?'?>" class="btn btn-secondary btn-sm"><?=$isRtl?'إعادة':'Reset'?></a>
        <span style="font-size:12px;color:var(--gray-500);white-space:nowrap"><?=$totalCount?> <?=$isRtl?'مسجد':'mosques'?></span>
      </form>

      <!-- Grid -->
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:12px">
        <?php if (empty($mosques)): ?>
        <div style="grid-column:1/-1;text-align:center;padding:50px;color:var(--gray-300)">
          <div style="font-size:40px">🕌</div>
          <div style="margin-top:8px"><?=$isRtl?'لا توجد نتائج':'No mosques found'?></div>
        </div>
        <?php else: foreach ($mosques as $m): ?>
        <div style="background:#fff;border:1px solid var(--gray-100);border-radius:12px;padding:14px;transition:all .2s"
             onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='var(--shadow-sm)'"
             onmouseout="this.style.transform='';this.style.boxShadow=''">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
            <span style="font-size:22px">🕌</span>
            <div style="display:flex;gap:3px;flex-wrap:wrap;justify-content:flex-end">
              <?php if ($m['is_grand']): ?>
              <span style="font-size:10px;background:var(--gold-pale);color:var(--gold-dark);padding:1px 7px;border-radius:99px;font-weight:600"><?=$isRtl?'كبير':'Grand'?></span>
              <?php endif; ?>
              <?php if ($m['is_historic']): ?>
              <span style="font-size:10px;background:#ede9fe;color:#5b21b6;padding:1px 7px;border-radius:99px;font-weight:600"><?=$isRtl?'أثري':'Historic'?></span>
              <?php endif; ?>
            </div>
          </div>
          <div style="font-weight:700;font-size:13px;color:var(--green-dark);margin-bottom:2px"><?=h($isRtl?$m['name_ar']:$m['name_en'])?></div>
          <?php if($isRtl && $m['name_en']): ?><div style="font-size:11px;color:var(--gray-300);margin-bottom:8px"><?=h($m['name_en'])?></div><?php endif; ?>
          <div style="font-size:11px;color:var(--gray-700);display:flex;flex-direction:column;gap:2px">
            <?php if ($m['governorate']): ?><span>📍 <?=h($m['governorate'])?><?=$m['wilayat']?' — '.h($m['wilayat']):''?></span><?php endif; ?>
            <?php if ($m['capacity']): ?><span>👥 <?=number_format($m['capacity'])?></span><?php endif; ?>
            <?php if ($m['phone']): ?><span>📞 <?=h($m['phone'])?></span><?php endif; ?>
          </div>
          <?php if (!empty($m['latitude']) && !empty($m['longitude'])): ?>
          <a href="https://maps.google.com/?q=<?=$m['latitude']?>,<?=$m['longitude']?>" target="_blank"
             style="display:inline-block;margin-top:8px;font-size:11px;color:var(--info);font-weight:600;text-decoration:none">
            🗺️ <?=$isRtl?'على الخريطة':'Maps'?> →
          </a>
          <?php endif; ?>
          <!-- Mosque admin assignment -->
          <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--gray-50)">
            <?php if ($m['admin_name']): ?>
            <div style="font-size:11px;color:var(--success);font-weight:600;margin-bottom:4px">⚙️ <?=h($m['admin_name'])?></div>
            <div style="display:flex;gap:4px;margin-bottom:4px">
              <button onclick="showAssignAdmin(<?=$m['id']?>, '<?=addslashes($isRtl?$m['name_ar']:$m['name_en'])?>')"
                style="font-size:10px;background:var(--green-pale);color:var(--green-dark);border:1px solid var(--green-light);border-radius:6px;padding:2px 7px;cursor:pointer;font-weight:600">
                ✏️ <?=$isRtl?'تغيير':'Change'?>
              </button>
              <form method="POST" style="display:inline" onsubmit="return confirm('<?=$isRtl?'إزالة مدير المسجد؟':'Remove mosque admin?'?>
        <?= csrfField() ?>')">
                <input type="hidden" name="action" value="assign_admin">
                <input type="hidden" name="mosque_id" value="<?=$m['id']?>">
                <input type="hidden" name="admin_user_id" value="">
                <button type="submit" style="font-size:10px;background:var(--danger-pale);color:var(--danger);border:1px solid var(--danger);border-radius:6px;padding:2px 7px;cursor:pointer;font-weight:600">
                  🗑 <?=$isRtl?'إزالة':'Remove'?>
                </button>
              </form>
            </div>
            <?php else: ?>
            <div style="font-size:11px;color:var(--warning);margin-bottom:4px">⚠️ <?=$isRtl?'لا يوجد مدير':'No admin assigned'?></div>
            <?php endif; ?>
            <button onclick="showAssignAdmin(<?=$m['id']?>, '<?=addslashes($isRtl?$m['name_ar']:$m['name_en'])?>')"
              style="font-size:10px;background:var(--green-pale);color:var(--green-dark);border:1px solid var(--green-light);border-radius:6px;padding:3px 8px;cursor:pointer;font-weight:600">
              ⚙️ <?=$isRtl?'تعيين مدير':'Assign Admin'?>
            </button>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div style="display:flex;justify-content:center;gap:6px;margin-top:20px">
        <?php for ($p=1;$p<=$totalPages;$p++):
          $url = '?' . http_build_query(array_merge($_GET,['page'=>$p]));
          $active = $p===$page;
        ?>
        <a href="<?=$url?>" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;border:1px solid <?=$active?'var(--green-main)':'var(--gray-100)'?>;background:<?=$active?'var(--green-main)':'#fff'?>;color:<?=$active?'#fff':'var(--dark)'?>;font-size:13px;text-decoration:none"><?=$p?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

</main>
</div>
<?php include '../includes/footer.php'; ?>

<!-- Assign Mosque Admin Modal -->
<div id="assignAdminModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;padding:28px;width:100%;max-width:420px;box-shadow:var(--shadow-lg)">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px">
      <h3 style="margin:0;color:var(--green-dark)">⚙️ <?=$isRtl?'تعيين مدير للمسجد':'Assign Mosque Admin'?></h3>
      <button onclick="document.getElementById('assignAdminModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer">✕</button>
    </div>
    <p id="assignMosqueName" style="color:var(--gray-500);font-size:13px;margin-bottom:16px;font-weight:600"></p>
    <form method="POST">
        <?= csrfField() ?>
      <input type="hidden" name="action" value="assign_admin">
      <input type="hidden" name="mosque_id" id="assignMosqueId">
      <div class="form-group">
        <label class="form-label">👤 <?=$isRtl?'اختر المدير':'Select Admin'?></label>
        <select name="admin_user_id" class="form-control form-select">
          <option value=""><?=$isRtl?'-- بدون مدير --':'-- No admin --'?></option>
          <?php foreach($availAdmins as $a):?>
          <option value="<?=$a['id']?>"><?=h($a['full_name'])?> (<?=h($a['role'])?>)</option>
          <?php endforeach;?>
        </select>
        <p class="form-hint"><?=$isRtl?'سيتم تغيير دور المستخدم إلى mosque_admin تلقائياً':'User role will be changed to mosque_admin automatically'?></p>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary" style="flex:1">✅ <?=$isRtl?'تعيين':'Assign'?></button>
        <button type="button" onclick="document.getElementById('assignAdminModal').style.display='none'" class="btn btn-secondary"><?=$isRtl?'إلغاء':'Cancel'?></button>
      </div>
    </form>
  </div>
</div>
<script>
function showAssignAdmin(mosqueId, mosqueName) {
    document.getElementById('assignMosqueId').value = mosqueId;
    document.getElementById('assignMosqueName').textContent = '🕌 ' + mosqueName;
    document.getElementById('assignAdminModal').style.display = 'flex';
}
</script>
