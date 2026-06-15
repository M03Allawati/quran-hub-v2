<?php
require_once dirname(__DIR__) . '/config.php';
requireRole('student');
$lang=$_SESSION['lang']??'en'; $isRtl=$lang==='ar';
$pdo=getPDO(); $userId=$_SESSION['user_id'];
$pageTitle=($isRtl?'حضوري — ':'My Attendance — ').APP_NAME;

$student=$pdo->prepare("SELECT s.*,m.name_en as mosque_en,m.name_ar as mosque_ar FROM students s JOIN mosques m ON m.id=s.mosque_id WHERE s.user_id=? AND s.is_active=1 LIMIT 1");
$student->execute([$userId]); $student=$student->fetch();
if(!$student){ header('Location: /student/dashboard.php'); exit; }
$sid=$student['id'];

$monthFilter=$_GET['month']??date('Y-m');

// Stats
$stats=$pdo->prepare("SELECT COUNT(*) as total, SUM(status='present') as present, SUM(status='absent') as absent, SUM(status='late') as late, SUM(status='excused') as excused FROM attendance WHERE student_id=?");
$stats->execute([$sid]); $stats=$stats->fetch();
$attPct=$stats['total']>0?round($stats['present']/$stats['total']*100):0;

// Monthly records
$records=$pdo->prepare("SELECT a.*,c.name_en as class_en,c.subject FROM attendance a LEFT JOIN classes c ON c.id=a.class_id WHERE a.student_id=? AND DATE_FORMAT(a.date,'%Y-%m')=? ORDER BY a.date DESC");
$records->execute([$sid,$monthFilter]); $records=$records->fetchAll();

// Calendar data for current month
$calData=[];
foreach($records as $r) $calData[$r['date']]=$r['status'];

$statColors=['present'=>'var(--success)','absent'=>'var(--danger)','late'=>'var(--warning)','excused'=>'var(--info)'];
$statIcons=['present'=>'✅','absent'=>'❌','late'=>'⏰','excused'=>'📋'];
$statLabels=['present'=>($isRtl?'حاضر':'Present'),'absent'=>($isRtl?'غائب':'Absent'),'late'=>($isRtl?'متأخر':'Late'),'excused'=>($isRtl?'بعذر':'Excused')];

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<h1 style="font-size:1.5rem;font-weight:800;margin:0 0 4px">✅ <?=$isRtl?'سجل حضوري':'My Attendance'?></h1>
<p style="color:var(--gray-500);font-size:13px;margin:0 0 20px">🕌 <?=h($isRtl?$student['mosque_ar']:$student['mosque_en'])?></p>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:20px">
  <?php foreach([
    ['✅',$stats['present']??0,$isRtl?'حاضر':'Present','#D1FAE5','#065F46'],
    ['❌',$stats['absent']??0,$isRtl?'غائب':'Absent','#FEE2E2','#DC2626'],
    ['⏰',$stats['late']??0,$isRtl?'متأخر':'Late','#FEF3C7','#D97706'],
    ['📋',$stats['excused']??0,$isRtl?'بعذر':'Excused','#DBEAFE','#2563EB'],
    ['📊',$attPct.'%',$isRtl?'نسبة الحضور':'Rate','#EDE9FE','#4C1D95'],
  ] as [$icon,$val,$label,$bg,$color]):?>
  <div style="background:<?=$bg?>;border-radius:12px;padding:14px;text-align:center">
    <div style="font-size:1.3rem"><?=$icon?></div>
    <div style="font-size:1.4rem;font-weight:800;color:<?=$color?>"><?=$val?></div>
    <div style="font-size:10px;color:var(--gray-600);margin-top:2px"><?=$label?></div>
  </div>
  <?php endforeach;?>
</div>

<!-- Attendance rate bar -->
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:16px 20px;margin-bottom:16px">
  <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:600;margin-bottom:6px">
    <span><?=$isRtl?'معدل الحضور الكلي':'Overall Attendance Rate'?></span>
    <span style="color:<?=$attPct>=80?'var(--success)':($attPct>=60?'var(--warning)':'var(--danger)')?>"><?=$attPct?>%</span>
  </div>
  <div style="height:10px;background:var(--gray-100);border-radius:5px;overflow:hidden">
    <div style="height:100%;width:<?=$attPct?>%;background:<?=$attPct>=80?'var(--success)':($attPct>=60?'var(--warning)':'var(--danger)')?>;border-radius:5px;transition:width .5s"></div>
  </div>
  <div style="font-size:11px;color:var(--gray-400);margin-top:6px">
    <?=$stats['total']?> <?=$isRtl?'جلسة إجمالية':'total sessions'?>
  </div>
</div>

<!-- Month filter + records -->
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;overflow:hidden">
  <div style="padding:14px 18px;background:var(--gray-50);border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center">
    <span style="font-weight:700;color:var(--green-dark)">📅 <?=$isRtl?'سجل الشهر':'Monthly Records'?></span>
    <form method="GET" style="display:flex;gap:8px;align-items:center">
      <input type="month" name="month" value="<?=h($monthFilter)?>" class="form-control" style="width:auto;font-size:12px" onchange="this.form.submit()">
    </form>
  </div>

  <?php if(empty($records)):?>
  <div style="padding:40px;text-align:center;color:var(--gray-300)">
    <div style="font-size:2.5rem">📋</div>
    <div style="margin-top:10px"><?=$isRtl?'لا توجد سجلات لهذا الشهر':'No records for this month'?></div>
  </div>
  <?php else:?>
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead><tr style="background:linear-gradient(135deg,var(--green-dark),var(--green-main))">
      <th style="padding:10px 16px;color:#fff;text-align:<?=$isRtl?'right':'left'?>;font-weight:600"><?=$isRtl?'التاريخ':'Date'?></th>
      <th style="padding:10px 16px;color:#fff;text-align:<?=$isRtl?'right':'left'?>;font-weight:600"><?=$isRtl?'الفصل':'Class'?></th>
      <th style="padding:10px 16px;color:#fff;text-align:center;font-weight:600"><?=$isRtl?'الحالة':'Status'?></th>
    </tr></thead>
    <tbody>
    <?php foreach($records as $r):?>
    <tr style="border-bottom:1px solid var(--gray-50)">
      <td style="padding:10px 16px">
        <div style="font-weight:600"><?=date($isRtl?'Y/m/d':'d M Y',strtotime($r['date']))?></div>
        <div style="font-size:10px;color:var(--gray-400)"><?=date('l',strtotime($r['date']))?></div>
      </td>
      <td style="padding:10px 16px;color:var(--gray-500);font-size:12px"><?=h($r['class_en']??'—')?></td>
      <td style="padding:10px 16px;text-align:center">
        <span style="background:<?=$statColors[$r['status']]?>22;color:<?=$statColors[$r['status']]?>;padding:3px 12px;border-radius:99px;font-size:11px;font-weight:700">
          <?=$statIcons[$r['status']]?> <?=$statLabels[$r['status']]?>
        </span>
      </td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table>
  <?php endif;?>
</div>

</main>
</div>
<?php include '../includes/footer.php'; ?>
