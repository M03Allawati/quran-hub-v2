<?php
require_once dirname(__DIR__) . '/config.php';
requireRole('student');
$lang=$_SESSION['lang']??'en'; $isRtl=$lang==='ar';
$pdo=getPDO(); $userId=$_SESSION['user_id'];
$pageTitle=($isRtl?'تقدمي — ':'My Progress — ').APP_NAME;

$student=$pdo->prepare("SELECT s.*,m.name_en as mosque_en,m.name_ar as mosque_ar FROM students s JOIN mosques m ON m.id=s.mosque_id WHERE s.user_id=? AND s.is_active=1 LIMIT 1");
$student->execute([$userId]); $student=$student->fetch();
if(!$student){ header('Location: /student/dashboard.php'); exit; }
$sid=$student['id'];

$progress=$pdo->prepare("SELECT p.*,c.name_en as class_en,u.full_name as teacher_name FROM progress p LEFT JOIN classes c ON c.id=p.class_id LEFT JOIN users u ON u.id=c.teacher_id WHERE p.student_id=? ORDER BY p.surah_number");
$progress->execute([$sid]); $progress=$progress->fetchAll();

$totalSurahs=count($progress);
$done=count(array_filter($progress,fn($p)=>$p['memorization_pct']==100));
$avgPct=$totalSurahs>0?round(array_sum(array_column($progress,'memorization_pct'))/$totalSurahs):0;
$avgTaj=$totalSurahs>0?round(array_sum(array_column($progress,'tajweed_level'))/$totalSurahs,1):0;

$evalColors=['Excellent'=>['#D1FAE5','#065F46'],'Good'=>['#DBEAFE','#1D4ED8'],'Needs Improvement'=>['#FEF3C7','#92400E'],'Repeat'=>['#FEE2E2','#991B1B']];

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<h1 style="font-size:1.5rem;font-weight:800;margin:0 0 4px">📈 <?=$isRtl?'تقدمي في الحفظ':'My Quran Progress'?></h1>
<p style="color:var(--gray-500);font-size:13px;margin:0 0 20px">🕌 <?=h($isRtl?$student['mosque_ar']:$student['mosque_en'])?></p>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px">
  <?php foreach([
    ['📖',$totalSurahs,$isRtl?'السور المسجّلة':'Surahs Recorded','#EDE9FE','#4C1D95'],
    ['✅',$done,$isRtl?'مكتملة 100%':'Completed (100%)','#D1FAE5','#065F46'],
    ['📊',$avgPct.'%',$isRtl?'متوسط الحفظ':'Avg Memorized','#DBEAFE','#1D4ED8'],
    ['⭐',$avgTaj,$isRtl?'متوسط التجويد':'Avg Tajweed','#FEF3C7','#92400E'],
  ] as [$icon,$val,$label,$bg,$color]):?>
  <div style="background:<?=$bg?>;border-radius:12px;padding:16px;text-align:center">
    <div style="font-size:1.4rem"><?=$icon?></div>
    <div style="font-size:1.5rem;font-weight:800;color:<?=$color?>"><?=$val?></div>
    <div style="font-size:10px;color:var(--gray-600);margin-top:2px"><?=$label?></div>
  </div>
  <?php endforeach;?>
</div>

<!-- Overall progress bar -->
<?php if($totalSurahs>0):?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:16px 20px;margin-bottom:16px">
  <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:600;margin-bottom:8px">
    <span><?=$isRtl?'إجمالي التقدم':'Overall Progress'?></span>
    <span style="color:var(--green-main)"><?=$avgPct?>%</span>
  </div>
  <div style="height:12px;background:var(--gray-100);border-radius:6px;overflow:hidden">
    <div style="height:100%;width:<?=$avgPct?>%;background:linear-gradient(90deg,var(--green-dark),var(--green-main));border-radius:6px"></div>
  </div>
  <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--gray-400);margin-top:6px">
    <span><?=$done?> / <?=$totalSurahs?> <?=$isRtl?'سورة مكتملة':'surahs complete'?></span>
    <span><?=str_repeat('⭐',(int)round($avgTaj))?> <?=$isRtl?'مستوى التجويد':'tajweed level'?></span>
  </div>
</div>
<?php endif;?>

<!-- Surah list -->
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;overflow:hidden">
  <div style="padding:14px 18px;background:var(--gray-50);border-bottom:1px solid var(--gray-100)">
    <span style="font-weight:700;color:var(--green-dark)">📖 <?=$isRtl?'تفاصيل كل سورة':'Surah Details'?></span>
  </div>

  <?php if(empty($progress)):?>
  <div style="padding:60px;text-align:center;color:var(--gray-300)">
    <div style="font-size:3rem">📖</div>
    <div style="margin-top:12px;font-size:15px"><?=$isRtl?'لا يوجد تقدم مسجّل بعد':'No progress recorded yet'?></div>
    <p style="font-size:13px;margin-top:8px;color:var(--gray-400)"><?=$isRtl?'سيُضاف تقدمك من قِبَل معلمك':'Your teacher will add your progress'?></p>
  </div>
  <?php else:?>
  <div style="padding:16px;display:flex;flex-direction:column;gap:10px">
    <?php foreach($progress as $p):
      $pct=(int)$p['memorization_pct'];
      $barC=$pct==100?'var(--success)':($pct>=70?'var(--green-main)':($pct>=40?'var(--warning)':'var(--danger)'));
      $ec=$evalColors[$p['evaluation']]??['#F3F4F6','#374151'];
    ?>
    <div style="border:1px solid var(--gray-100);border-radius:12px;padding:14px;background:<?=$pct==100?'#F0FDF4':'#fff'?>">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
        <div>
          <span style="font-weight:800;font-size:14px;color:var(--green-dark)"><?=h($p['surah_name_ar'])?></span>
          <span style="font-size:11px;color:var(--gray-400);margin-right:6px"><?=h($p['surah_name_en'])?></span>
          <div style="font-size:11px;color:var(--gray-400);margin-top:2px">
            <?=$isRtl?'آية':'Ayah'?> <?=$p['ayah_from']?>–<?=$p['ayah_to']?>
            <?php if($p['teacher_name']):?> · 👨‍🏫 <?=h($p['teacher_name'])?><?php endif;?>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:6px">
          <span style="background:<?=$ec[0]?>;color:<?=$ec[1]?>;padding:2px 10px;border-radius:99px;font-size:10px;font-weight:700"><?=h($p['evaluation'])?></span>
          <?php if($pct==100):?><span style="font-size:14px">🏆</span><?php endif;?>
        </div>
      </div>
      <div style="height:8px;background:var(--gray-100);border-radius:4px;overflow:hidden;margin-bottom:5px">
        <div style="height:100%;width:<?=$pct?>%;background:<?=$barC?>;border-radius:4px"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:11px">
        <span style="color:var(--gray-500)"><?=str_repeat('⭐',(int)$p['tajweed_level'])?><?=str_repeat('☆',5-(int)$p['tajweed_level'])?></span>
        <span style="font-weight:700;color:<?=$barC?>"><?=$pct?>%</span>
      </div>
      <?php if($p['notes']):?>
      <div style="font-size:11px;color:var(--gray-500);margin-top:6px;font-style:italic;border-top:1px solid var(--gray-50);padding-top:5px">
        📝 <?=h($p['notes'])?>
      </div>
      <?php endif;?>
    </div>
    <?php endforeach;?>
  </div>
  <?php endif;?>
</div>

</main>
</div>
<?php include '../includes/footer.php'; ?>
