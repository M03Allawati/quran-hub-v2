<?php
require_once dirname(__DIR__) . '/config.php';
requireRole('parent');
$lang   = $_SESSION['lang'] ?? 'en';
$isRtl  = $lang === 'ar';
$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$pageTitle = ($isRtl ? 'تقدم أطفالي — ' : "Children's Progress — ") . APP_NAME;

$children = $pdo->prepare("SELECT s.*, m.name_en as mosque_en, m.name_ar as mosque_ar FROM students s JOIN mosques m ON m.id=s.mosque_id WHERE s.parent_id=? AND s.is_active=1 ORDER BY s.full_name");
$children->execute([$userId]);
$children = $children->fetchAll();

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<h1 style="font-size:1.5rem;font-weight:800;margin:0 0 4px">📖 <?= $isRtl?'تقدم الحفظ':"Memorization Progress" ?></h1>
<p style="color:var(--gray-500);font-size:13px;margin:0 0 24px"><?= $isRtl?'تابع تقدم أطفالك في حفظ القرآن':'Track your children\'s Quran memorization' ?></p>

<?php if (empty($children)): ?>
<div style="text-align:center;padding:60px;color:var(--gray-300)">
  <div style="font-size:48px">📖</div>
  <div style="margin-top:12px"><?= $isRtl?'لا يوجد أطفال مسجّلون':'No children registered' ?></div>
  <a href="/parent/children.php" class="btn btn-primary" style="margin-top:16px"><?= $isRtl?'أضف طفلاً':'Add Child' ?></a>
</div>
<?php else: foreach ($children as $child):
  $progress = $pdo->prepare("SELECT p.*, c.name_en as class_en, c.name_ar as class_ar FROM progress p LEFT JOIN classes c ON c.id=p.class_id WHERE p.student_id=? ORDER BY p.surah_number");
  $progress->execute([$child['id']]);
  $progress = $progress->fetchAll();
  $completed = array_filter($progress, fn($p) => $p['memorization_pct'] == 100);
  $avgTajweed = count($progress) > 0 ? round(array_sum(array_column($progress,'tajweed_level'))/count($progress),1) : 0;
?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px;margin-bottom:20px">
  <!-- Child header -->
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid var(--gray-50)">
    <div style="width:44px;height:44px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--green-dark);font-size:1.1rem">
      <?= strtoupper(substr($child['full_name'],0,1)) ?>
    </div>
    <div style="flex:1">
      <div style="font-weight:700;font-size:15px;color:var(--green-dark)"><?= h($child['full_name']) ?></div>
      <div style="font-size:12px;color:var(--gray-500)">🕌 <?= h($isRtl?$child['mosque_ar']:$child['mosque_en']) ?></div>
    </div>
    <div style="display:flex;gap:16px">
      <div style="text-align:center">
        <div style="font-size:1.5rem;font-weight:800;color:var(--green-main)"><?= count($completed) ?></div>
        <div style="font-size:11px;color:var(--gray-500)"><?= $isRtl?'سور مكتملة':'Surahs done' ?></div>
      </div>
      <div style="text-align:center">
        <div style="font-size:1.5rem;font-weight:800;color:var(--gold-dark)"><?= $avgTajweed ?></div>
        <div style="font-size:11px;color:var(--gray-500)"><?= $isRtl?'متوسط التجويد':'Avg Tajweed' ?></div>
      </div>
    </div>
  </div>

  <?php if (empty($progress)): ?>
  <div style="text-align:center;padding:24px;color:var(--gray-300)">
    <div style="font-size:2.5rem">📖</div>
    <div style="margin-top:8px;font-size:13px"><?= $isRtl?'لم يُسجَّل تقدم بعد. سيحدّثه المعلم.':'No progress yet. Teacher will update.' ?></div>
  </div>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px">
    <?php foreach ($progress as $p):
      $pct = (int)$p['memorization_pct'];
      $evalColors = ['Excellent'=>'var(--success)','Good'=>'var(--info)','Needs Improvement'=>'var(--warning)','Repeat'=>'var(--danger)'];
      $stars = str_repeat('⭐',(int)$p['tajweed_level']).str_repeat('☆',5-(int)$p['tajweed_level']);
    ?>
    <div style="border:1px solid var(--gray-100);border-radius:10px;padding:12px;background:<?=$pct==100?'var(--success-pale)':'#fff'?>">
      <div style="font-weight:700;font-size:14px;color:var(--green-dark);margin-bottom:2px"><?= h($p['surah_name_ar']) ?></div>
      <div style="font-size:11px;color:var(--gray-400);margin-bottom:6px"><?= h($p['surah_name_en']) ?></div>
      <div style="height:6px;background:var(--gray-100);border-radius:3px;overflow:hidden;margin-bottom:6px">
        <div style="height:100%;width:<?=$pct?>%;background:<?=$pct==100?'var(--success)':'var(--green-main)'?>;border-radius:3px"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:11px">
        <span><?=$stars?></span>
        <span style="color:<?=$evalColors[$p['evaluation']]??'var(--gray-500)'?>;font-weight:600"><?=$pct?>%</span>
      </div>
      <?php if ($p['notes']): ?>
      <div style="font-size:11px;color:var(--gray-500);margin-top:4px;font-style:italic"><?=h($p['notes'])?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; endif; ?>

</main>
</div>
<?php include '../includes/footer.php'; ?>
