<?php
require_once dirname(__DIR__) . '/config.php';
requireRole('parent');
$lang   = $_SESSION['lang'] ?? 'en';
$isRtl  = $lang === 'ar';
$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$pageTitle = ($isRtl ? 'حضور أطفالي — ' : "Children's Attendance — ") . APP_NAME;

$children = $pdo->prepare("SELECT s.*, m.name_en as mosque_en, m.name_ar as mosque_ar FROM students s JOIN mosques m ON m.id=s.mosque_id WHERE s.parent_id=? AND s.is_active=1 ORDER BY s.full_name");
$children->execute([$userId]);
$children = $children->fetchAll();

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<h1 style="font-size:1.5rem;font-weight:800;margin:0 0 4px">✅ <?= $isRtl?'حضور أطفالي':"Children's Attendance" ?></h1>
<p style="color:var(--gray-500);font-size:13px;margin:0 0 24px"><?= $isRtl?'سجل حضور وغياب أطفالك':'View attendance records for your children' ?></p>

<?php if (empty($children)): ?>
<div style="text-align:center;padding:60px;color:var(--gray-300)">
  <div style="font-size:48px">👶</div>
  <div style="margin-top:12px"><?= $isRtl?'لا يوجد أطفال مسجّلون':'No children registered' ?></div>
  <a href="/parent/children.php" class="btn btn-primary" style="margin-top:16px"><?= $isRtl?'أضف طفلاً':'Add Child' ?></a>
</div>
<?php else: foreach ($children as $child):
  $stats = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
  $attRows = $pdo->prepare("SELECT a.date, a.status, c.name_en, c.name_ar FROM attendance a JOIN classes c ON c.id=a.class_id WHERE a.student_id=? ORDER BY a.date DESC LIMIT 30");
  $attRows->execute([$child['id']]);
  $attRows = $attRows->fetchAll();
  foreach ($attRows as $r) { $stats[$r['status']] = ($stats[$r['status']]??0) + 1; }
  $total = array_sum($stats);
  $pct   = $total > 0 ? round($stats['present']/$total*100) : 0;
?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px;margin-bottom:20px">
  <!-- Child header -->
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--gray-50)">
    <div style="width:44px;height:44px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--green-dark);font-size:1.1rem">
      <?= strtoupper(substr($child['full_name'],0,1)) ?>
    </div>
    <div>
      <div style="font-weight:700;font-size:15px;color:var(--green-dark)"><?= h($child['full_name']) ?></div>
      <div style="font-size:12px;color:var(--gray-500)">🕌 <?= h($isRtl?$child['mosque_ar']:$child['mosque_en']) ?></div>
    </div>
    <div style="margin-<?=$isRtl?'right':'left'?>:auto;text-align:center">
      <div style="font-size:1.8rem;font-weight:800;color:<?=$pct>=80?'var(--success)':($pct>=60?'var(--warning)':'var(--danger)')?>"><?=$pct?>%</div>
      <div style="font-size:11px;color:var(--gray-500)"><?=$isRtl?'نسبة الحضور':'Attendance'?></div>
    </div>
  </div>

  <!-- Stats -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:16px">
    <?php foreach ([
      ['present','✅',$isRtl?'حاضر':'Present','var(--success-pale)','var(--success)'],
      ['absent','❌',$isRtl?'غائب':'Absent','var(--danger-pale)','var(--danger)'],
      ['late','⏰',$isRtl?'متأخر':'Late','var(--warning-pale)','var(--warning)'],
      ['excused','📋',$isRtl?'بعذر':'Excused','var(--info-pale)','var(--info)'],
    ] as [$key,$icon,$label,$bg,$color]): ?>
    <div style="background:<?=$bg?>;border-radius:10px;padding:10px;text-align:center">
      <div style="font-size:1.2rem"><?=$icon?></div>
      <div style="font-size:1.3rem;font-weight:800;color:<?=$color?>"><?=$stats[$key]?></div>
      <div style="font-size:11px;color:var(--gray-500)"><?=$label?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Recent records -->
  <?php if (!empty($attRows)): ?>
  <div style="font-size:12px;font-weight:700;color:var(--gray-500);margin-bottom:8px"><?=$isRtl?'آخر السجلات:':'Recent Records:'?></div>
  <div style="background:var(--gray-50);border-radius:10px;overflow:hidden">
    <?php $colors=['present'=>'var(--success)','absent'=>'var(--danger)','late'=>'var(--warning)','excused'=>'var(--info)'];
    $icons=['present'=>'✅','absent'=>'❌','late'=>'⏰','excused'=>'📋'];
    foreach (array_slice($attRows,0,8) as $r): ?>
    <div style="display:flex;justify-content:space-between;padding:9px 14px;border-bottom:1px solid var(--gray-100);font-size:13px">
      <span style="color:var(--gray-700)"><?=h($isRtl&&$r['name_ar']?$r['name_ar']:$r['name_en'])?></span>
      <span style="color:var(--gray-500)"><?=date('D, M j',strtotime($r['date']))?></span>
      <span style="color:<?=$colors[$r['status']]?>;font-weight:600"><?=$icons[$r['status']]?> <?=ucfirst($r['status'])?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div style="text-align:center;padding:20px;color:var(--gray-300);font-size:13px"><?=$isRtl?'لا توجد سجلات بعد':'No records yet'?></div>
  <?php endif; ?>
</div>
<?php endforeach; endif; ?>

</main>
</div>
<?php include '../includes/footer.php'; ?>
