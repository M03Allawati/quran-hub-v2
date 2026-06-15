<?php
require_once __DIR__ . '/config.php';
requireLogin();
$lang  = $_SESSION['lang'] ?? 'en';
$isRtl = $lang === 'ar';
$pdo   = getPDO();
$pageTitle = ($isRtl ? 'مساجد عُمان — ' : 'Oman Mosques — ') . APP_NAME;

$govFilter = trim($_GET['gov'] ?? '');
$search    = trim($_GET['search'] ?? '');
$grand     = isset($_GET['grand']) ? (int)$_GET['grand'] : -1;
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 24;

$where  = ['1=1'];
$params = [];
if ($govFilter) { $where[] = 'm.governorate = ?'; $params[] = $govFilter; }
if ($grand >= 0) { $where[] = 'm.is_grand = ?'; $params[] = $grand; }
if ($search)    { $where[] = '(m.name_en LIKE ? OR m.name_ar LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereStr = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM mosques m WHERE $whereStr");
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$stmt = $pdo->prepare("SELECT m.* FROM mosques m WHERE $whereStr ORDER BY m.is_grand DESC, m.name_en LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, ($page-1)*$perPage]));
$mosques = $stmt->fetchAll();

$govStats = $pdo->query("SELECT governorate as name_en, COUNT(*) as mosque_count, SUM(is_grand) as grand_count FROM mosques GROUP BY governorate ORDER BY mosque_count DESC")->fetchAll();

$totals = $pdo->query("SELECT COUNT(*) as total_mosques, SUM(is_grand) as grand_mosques, SUM(is_historic) as historic_mosques FROM mosques")->fetch();

include 'includes/header.php';
?>
<div class="pattern-bg" style="padding:40px 2rem 32px;text-align:center;color:#fff">
  <h1 style="font-size:2rem;font-weight:800;margin:0 0 8px;color:var(--gold-light)">🕌 <?= $isRtl?'مساجد سلطنة عُمان':'Oman Mosque Explorer' ?></h1>
  <p style="opacity:.85;margin:0"><?= $isRtl?'مساجد من كل محافظات عُمان الـ 11':'Mosques across all 11 governorates of Oman' ?></p>
</div>

<div style="background:var(--gold-pale);border-bottom:1px solid var(--gold-light);padding:14px 2rem">
  <div style="max-width:1100px;margin:0 auto;display:flex;gap:32px;justify-content:center;flex-wrap:wrap;text-align:center">
    <?php foreach ([
      ['🕌',$totals['total_mosques']??0,$isRtl?'إجمالي المساجد':'Total Mosques'],
      ['👑',$totals['grand_mosques']??0,$isRtl?'الجوامع الكبرى':'Grand Mosques'],
      ['🏛️',$totals['historic_mosques']??0,$isRtl?'أثرية':'Historic'],
      ['🗺️',count($govStats),$isRtl?'محافظة':'Governorates'],
    ] as [$icon,$val,$label]): ?>
    <div>
      <div style="font-size:1.5rem"><?= $icon ?></div>
      <div style="font-size:1.4rem;font-weight:800;color:var(--green-dark)"><?= number_format((int)$val) ?></div>
      <div style="font-size:.8rem;color:var(--gray-700)"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div style="max-width:1100px;margin:0 auto;padding:24px 2rem">
  <div style="display:grid;grid-template-columns:220px 1fr;gap:20px;align-items:start">

    <!-- Governorates list -->
    <div style="background:#fff;border-radius:var(--radius-lg);border:1px solid var(--gray-100);padding:14px">
      <h4 style="margin:0 0 10px;font-size:13px;font-weight:700;color:var(--green-dark)">🗺️ <?= $isRtl?'المحافظات':'Governorates' ?></h4>
      <a href="?" style="display:flex;justify-content:space-between;align-items:center;padding:7px 10px;border-radius:8px;font-size:13px;text-decoration:none;color:var(--green-dark);background:<?= !$govFilter?'var(--green-pale)':'' ?>;margin-bottom:3px">
        <span><?= $isRtl?'الكل':'All' ?></span>
        <span style="font-size:11px;color:var(--gray-500)"><?= $totalCount ?></span>
      </a>
      <?php foreach ($govStats as $g): $active = $govFilter === $g['name_en']; ?>
      <a href="?gov=<?= urlencode($g['name_en']) ?>"
         style="display:flex;justify-content:space-between;align-items:center;padding:7px 10px;border-radius:8px;font-size:12px;text-decoration:none;color:var(--dark);background:<?= $active?'var(--green-pale)':'' ?>;margin-bottom:2px">
        <span><?= h($g['name_en']) ?></span>
        <span style="font-size:11px;color:var(--gray-500)"><?= $g['mosque_count'] ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Mosque grid -->
    <div>
      <!-- Filters -->
      <form method="GET" style="background:#fff;border-radius:var(--radius-lg);border:1px solid var(--gray-100);padding:14px 18px;margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <?php if ($govFilter): ?><input type="hidden" name="gov" value="<?= h($govFilter) ?>"><?php endif; ?>
        <div style="flex:1;min-width:160px">
          <input name="search" value="<?= h($search) ?>" placeholder="🔍 <?= $isRtl?'ابحث عن مسجد...':'Search mosque...' ?>"
            style="width:100%;padding:8px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:14px;font-family:inherit">
        </div>
        <select name="grand" style="padding:8px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:13px;font-family:inherit;background:#fff">
          <option value="-1"><?= $isRtl?'الكل':'All types' ?></option>
          <option value="1" <?= $grand===1?'selected':'' ?>><?= $isRtl?'جوامع كبرى':'Grand only' ?></option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><?= $isRtl?'بحث':'Search' ?></button>
        <a href="<?= $govFilter?'?gov='.urlencode($govFilter):'?' ?>" class="btn btn-secondary btn-sm"><?= $isRtl?'إعادة':'Reset' ?></a>
      </form>

      <p style="font-size:13px;color:var(--gray-500);margin-bottom:14px"><?= $isRtl?"$totalCount مسجد":"$totalCount mosque".($totalCount!==1?'s':'') ?></p>

      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px">
        <?php if (empty($mosques)): ?>
        <div style="grid-column:1/-1;text-align:center;padding:50px;color:var(--gray-300)">
          <div style="font-size:40px">🕌</div>
          <div style="margin-top:10px"><?= $isRtl?'لا توجد نتائج':'No mosques found' ?></div>
        </div>
        <?php else: foreach ($mosques as $m): ?>
        <div style="background:#fff;border:1px solid var(--gray-100);border-radius:12px;padding:16px;transition:all .2s"
             onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,.08)'"
             onmouseout="this.style.transform='';this.style.boxShadow=''">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
            <span style="font-size:26px">🕌</span>
            <div style="display:flex;gap:3px">
              <?php if ($m['is_grand']): ?>
              <span style="font-size:10px;background:var(--gold-pale);color:var(--gold-dark);padding:2px 7px;border-radius:99px;font-weight:600"><?= $isRtl?'كبير':'Grand' ?></span>
              <?php endif; ?>
              <?php if ($m['is_historic']): ?>
              <span style="font-size:10px;background:#ede9fe;color:#5b21b6;padding:2px 7px;border-radius:99px;font-weight:600"><?= $isRtl?'أثري':'Historic' ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div style="font-weight:700;font-size:14px;color:var(--green-dark);margin-bottom:2px"><?= h($isRtl?$m['name_ar']:$m['name_en']) ?></div>
          <div style="font-size:11px;color:var(--gray-300);margin-bottom:8px"><?= h($isRtl?$m['name_en']:$m['name_ar']) ?></div>
          <div style="font-size:12px;color:var(--gray-700);display:flex;flex-direction:column;gap:3px">
            <?php if ($m['governorate']): ?><span>📍 <?= h($m['governorate']) ?><?= $m['wilayat']?' — '.h($m['wilayat']):'' ?></span><?php endif; ?>
            <?php if ($m['location']): ?><span style="color:var(--gray-500)">🏘️ <?= h($m['location']) ?></span><?php endif; ?>
            <?php if ($m['capacity']): ?><span>👥 <?= number_format($m['capacity']) ?></span><?php endif; ?>
            <?php if ($m['phone']): ?><span>📞 <?= h($m['phone']) ?></span><?php endif; ?>
          </div>
          <?php if (!empty($m['latitude']) && !empty($m['longitude'])): ?>
          <a href="https://maps.google.com/?q=<?= $m['latitude'] ?>,<?= $m['longitude'] ?>" target="_blank"
             style="display:inline-block;margin-top:8px;font-size:11px;color:var(--info);font-weight:600;text-decoration:none">
            🗺️ <?= $isRtl?'على الخريطة':'Maps' ?> →
          </a>
          <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
      </div>

      <?php if ($totalPages > 1): ?>
      <div style="display:flex;justify-content:center;gap:6px;margin-top:20px">
        <?php for ($p=1;$p<=$totalPages;$p++):
          $url = '?' . http_build_query(array_merge($_GET, ['page'=>$p]));
          $active = $p===$page;
        ?>
        <a href="<?= $url ?>" style="width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:8px;border:1px solid <?= $active?'var(--green-main)':'var(--gray-100)' ?>;background:<?= $active?'var(--green-main)':'#fff' ?>;color:<?= $active?'#fff':'var(--dark)' ?>;font-size:13px;text-decoration:none"><?= $p ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
