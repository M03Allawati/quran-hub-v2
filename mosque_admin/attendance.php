<?php
require_once dirname(__DIR__) . '/config.php';
requireRole('mosque_admin');
$lang=$_SESSION['lang']??'en'; $isRtl=$lang==='ar';
$pdo=getPDO(); $userId=$_SESSION['user_id'];
$pageTitle=($isRtl?'حضور المسجد — ':'Mosque Attendance — ').APP_NAME;

$mosque=$pdo->prepare("SELECT * FROM mosques WHERE admin_id=? LIMIT 1");
$mosque->execute([$userId]); $mosque=$mosque->fetch();
if(!$mosque){ $mosque=$pdo->prepare("SELECT * FROM mosques WHERE id=? LIMIT 1"); $mosque->execute([$_SESSION['user']['mosque_id']??0]); $mosque=$mosque->fetch(); }
if(!$mosque){ header('Location: /dashboard.php'); exit; }
$mid=$mosque['id'];

$dateFil=$_GET['date']??date('Y-m-d');

$att=$pdo->query("SELECT a.*,s.full_name,s.full_name_ar,s.student_type,c.name_en as class_en,c.subject FROM attendance a JOIN students s ON s.id=a.student_id LEFT JOIN classes c ON c.id=a.class_id WHERE s.mosque_id=$mid AND a.date='$dateFil' ORDER BY a.status,s.full_name")->fetchAll();

$summary=['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
foreach($att as $r) $summary[$r['status']]++;

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:1.5rem;font-weight:800;margin:0">✅ <?=$isRtl?'سجل الحضور':'Attendance'?></h1>
    <p style="color:var(--gray-500);font-size:13px;margin:4px 0 0">🕌 <?=h($isRtl?$mosque['name_ar']:$mosque['name_en'])?></p>
  </div>
  <form method="GET" style="display:flex;gap:8px;align-items:center">
    <input type="date" name="date" value="<?=h($dateFil)?>" class="form-control" style="width:auto;font-size:13px">
    <button type="submit" class="btn btn-primary btn-sm"><?=$isRtl?'عرض':'View'?></button>
  </form>
</div>

<!-- Summary -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px">
  <?php foreach([['✅',$summary['present'],$isRtl?'حاضر':'Present','var(--success-pale)','var(--success)'],['❌',$summary['absent'],$isRtl?'غائب':'Absent','var(--danger-pale)','var(--danger)'],['⏰',$summary['late'],$isRtl?'متأخر':'Late','var(--warning-pale)','var(--warning)'],['📋',$summary['excused'],$isRtl?'بعذر':'Excused','var(--info-pale)','var(--info)']] as [$icon,$val,$label,$bg,$color]):?>
  <div style="background:<?=$bg?>;border-radius:12px;padding:14px;text-align:center">
    <div style="font-size:1.5rem"><?=$icon?></div>
    <div style="font-size:1.5rem;font-weight:800;color:<?=$color?>"><?=$val?></div>
    <div style="font-size:11px;color:var(--gray-700)"><?=$label?></div>
  </div>
  <?php endforeach;?>
</div>

<?php if(empty($att)):?>
<div style="text-align:center;padding:60px;background:#fff;border-radius:14px;border:1px solid var(--gray-100);color:var(--gray-300)">
  <div style="font-size:48px">📋</div>
  <div style="margin-top:12px"><?=$isRtl?'لا توجد سجلات حضور لهذا اليوم':'No attendance records for this date'?></div>
</div>
<?php else:?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead><tr style="background:linear-gradient(135deg,var(--green-dark),var(--green-main))">
      <th style="padding:11px 14px;color:#fff;text-align:<?=$isRtl?'right':'left'?>;font-weight:600"><?=$isRtl?'الطالب':'Student'?></th>
      <th style="padding:11px 14px;color:#fff;text-align:<?=$isRtl?'right':'left'?>;font-weight:600"><?=$isRtl?'الفصل':'Class'?></th>
      <th style="padding:11px 14px;color:#fff;text-align:center;font-weight:600"><?=$isRtl?'الحالة':'Status'?></th>
    </tr></thead>
    <tbody>
      <?php foreach($att as $r):
        $sc=['present'=>['var(--success)','✅',$isRtl?'حاضر':'Present'],'absent'=>['var(--danger)','❌',$isRtl?'غائب':'Absent'],'late'=>['var(--warning)','⏰',$isRtl?'متأخر':'Late'],'excused'=>['var(--info)','📋',$isRtl?'بعذر':'Excused']];
        $s=$sc[$r['status']];
      ?>
      <tr style="border-bottom:1px solid var(--gray-50)">
        <td style="padding:11px 14px">
          <div style="font-weight:600"><?=h($r['full_name'])?></div>
          <span style="font-size:10px;background:<?=($r['student_type']??'student')==='child'?'#DBEAFE':'#EDE9FE'?>;color:<?=($r['student_type']??'student')==='child'?'#1D4ED8':'#4C1D95'?>;padding:1px 6px;border-radius:99px"><?=($r['student_type']??'student')==='child'?'👶':' 🎓'?></span>
        </td>
        <td style="padding:11px 14px;font-size:12px;color:var(--gray-500)"><?=h($r['class_en']??'—')?></td>
        <td style="padding:11px 14px;text-align:center"><span style="color:<?=$s[0]?>;font-weight:700"><?=$s[1]?> <?=$s[2]?></span></td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>
<?php endif;?>
</main>
</div>
<?php include '../includes/footer.php'; ?>
