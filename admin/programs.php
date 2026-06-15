<?php
require_once __DIR__ . '/../config.php';
requireRole('admin');
$lang=$_SESSION['lang']??'en'; $isRtl=$lang==='ar';
$pdo=getPDO(); $pageTitle=($isRtl?'إدارة البرامج — ':'Programs — ').APP_NAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action=$_POST['action']??''; $progId=(int)($_POST['program_id']??0); $appId=(int)($_POST['app_id']??0);

    if($action==='approve_teacher'){
        $teacherId=(int)$_POST['teacher_id'];
        $pdo->prepare("UPDATE mosque_programs SET teacher_id=? WHERE id=? AND teacher_id IS NULL")->execute([$teacherId,$progId]);
        $pdo->prepare("UPDATE teacher_program_applications SET status='approved',reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'],$appId]);
        $pdo->prepare("UPDATE teacher_program_applications SET status='rejected',reviewed_by=?,reviewed_at=NOW() WHERE program_id=? AND id!=? AND status='pending'")->execute([$_SESSION['user_id'],$progId,$appId]);
        $pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'success')")->execute([$teacherId,'Application Approved!','Your program application has been approved.']);
        setFlash('success',$isRtl?'✅ تم قبول المعلم':'✅ Teacher approved');
        header('Location: /admin/programs.php?tab=applications'); exit;
    }
    if($action==='reject_teacher'){
        $teacherId=(int)$_POST['teacher_id'];
        $pdo->prepare("UPDATE teacher_program_applications SET status='rejected',reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'],$appId]);
        $pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'warning')")->execute([$teacherId,'Application Update','Your application was not accepted at this time.']);
        setFlash('info',$isRtl?'تم رفض الطلب':'Rejected');
        header('Location: /admin/programs.php?tab=applications'); exit;
    }
    if($action==='remove_teacher'&&$progId){
        $pdo->prepare("UPDATE mosque_programs SET teacher_id=NULL WHERE id=?")->execute([$progId]);
        setFlash('info',$isRtl?'تم إزالة المعلم':'Teacher removed');
        header('Location: /admin/programs.php'); exit;
    }
    if($action==='delete_slot'&&$progId){
        $pdo->prepare("DELETE FROM program_enrollments WHERE program_id=?")->execute([$progId]);
        $pdo->prepare("DELETE FROM teacher_program_applications WHERE program_id=?")->execute([$progId]);
        $pdo->prepare("DELETE FROM mosque_programs WHERE id=?")->execute([$progId]);
        setFlash('success',$isRtl?'تم حذف البرنامج':'Program deleted');
        header('Location: /admin/programs.php'); exit;
    }
}

$tab=trim($_GET['tab']??'programs'); $govFil=trim($_GET['gov']??'');
$progWhere=$govFil?"AND m.governorate=?":""; $progParams=$govFil?[$govFil]:[];

$allProgs=$pdo->prepare("SELECT mp.*,m.name_en as mosque_en,m.name_ar as mosque_ar,m.governorate,
    u.full_name as teacher_name,
    (SELECT COUNT(*) FROM program_enrollments pe WHERE pe.program_id=mp.id AND pe.status='active') as enrolled,
    (SELECT COUNT(*) FROM teacher_program_applications tpa WHERE tpa.program_id=mp.id AND tpa.status='pending') as pending_apps
    FROM mosque_programs mp JOIN mosques m ON m.id=mp.mosque_id LEFT JOIN users u ON u.id=mp.teacher_id
    WHERE mp.is_active=1 $progWhere ORDER BY m.governorate,m.name_en,mp.slot");
$allProgs->execute($progParams); $allProgs=$allProgs->fetchAll();

$pendingApps=$pdo->query("SELECT tpa.*,u.full_name as teacher_name,u.email as teacher_email,u.phone as teacher_phone,
    mp.name_en as prog_en,mp.name_ar as prog_ar,mp.slot,mp.days,mp.time_start,mp.time_end,
    m.name_en as mosque_en,m.name_ar as mosque_ar,m.governorate
    FROM teacher_program_applications tpa JOIN users u ON u.id=tpa.teacher_id
    JOIN mosque_programs mp ON mp.id=tpa.program_id JOIN mosques m ON m.id=mp.mosque_id
    WHERE tpa.status='pending' ORDER BY tpa.applied_at ASC")->fetchAll();

$stats=['total'=>count($allProgs),'assigned'=>count(array_filter($allProgs,fn($p)=>$p['teacher_id'])),'available'=>count(array_filter($allProgs,fn($p)=>!$p['teacher_id'])),'pending'=>count($pendingApps),'students'=>array_sum(array_column($allProgs,'enrolled'))];
$govs=$pdo->query("SELECT DISTINCT governorate FROM mosques ORDER BY governorate")->fetchAll(PDO::FETCH_COLUMN);
include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:1.5rem;font-weight:800;margin:0">📚 <?=$isRtl?'إدارة البرامج':'Programs Management'?></h1>
    <p style="color:var(--gray-500);font-size:13px;margin:4px 0 0"><?=$isRtl?'كل برامج المساجد في عُمان':'All mosque Quran programs across Oman'?></p>
  </div>
  <?php if($stats['pending']>0):?>
  <a href="?tab=applications" style="background:var(--warning-pale);color:var(--warning);padding:8px 16px;border-radius:10px;text-decoration:none;font-weight:700;font-size:13px">
    ⏳ <?=$stats['pending']?> <?=$isRtl?'طلب معلقة':'Pending'?>
  </a>
  <?php endif;?>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:20px">
  <?php foreach([['📚',$stats['total'],$isRtl?'إجمالي':'Total','var(--green-pale)','var(--green-dark)'],['✅',$stats['assigned'],$isRtl?'مع معلم':'Assigned','var(--success-pale)','var(--success)'],['🔓',$stats['available'],$isRtl?'شاغرة':'Available','var(--gold-pale)','var(--gold-dark)'],['⏳',$stats['pending'],$isRtl?'طلبات':'Pending','var(--warning-pale)','var(--warning)'],['👥',$stats['students'],$isRtl?'طلاب':'Students','var(--info-pale)','var(--info)']] as [$icon,$val,$label,$bg,$color]):?>
  <div style="background:<?=$bg?>;border-radius:12px;padding:14px;text-align:center">
    <div style="font-size:1.5rem"><?=$icon?></div>
    <div style="font-size:1.4rem;font-weight:800;color:<?=$color?>"><?=number_format((int)$val)?></div>
    <div style="font-size:11px;color:var(--gray-700)"><?=$label?></div>
  </div>
  <?php endforeach;?>
</div>

<!-- Tabs -->
<div style="display:flex;background:var(--gray-50);border-radius:12px;padding:4px;margin-bottom:20px;width:fit-content">
  <?php foreach([['programs','📋 '.($isRtl?'البرامج':'Programs')],['applications','⏳ '.($isRtl?'الطلبات':'Applications').' ('.count($pendingApps).')']] as [$k,$l]):?>
  <a href="?tab=<?=$k?>&gov=<?=urlencode($govFil)?>" style="padding:8px 16px;border-radius:10px;font-size:13px;text-decoration:none;background:<?=$tab===$k?'#fff':'transparent'?>;font-weight:<?=$tab===$k?700:400?>;color:<?=$tab===$k?'var(--green-dark)':'var(--gray-500)'?>;transition:all .2s"><?=$l?></a>
  <?php endforeach;?>
</div>

<?php if($tab==='programs'):?>
<!-- Filter -->
<form method="GET" style="display:flex;gap:8px;margin-bottom:14px">
  <input type="hidden" name="tab" value="programs">
  <select name="gov" onchange="this.form.submit()" style="padding:7px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:13px;font-family:inherit;background:#fff">
    <option value=""><?=$isRtl?'كل المحافظات':'All Governorates'?></option>
    <?php foreach($govs as $g):?><option value="<?=h($g)?>" <?=$govFil===$g?'selected':''?>><?=h($g)?></option><?php endforeach;?>
  </select>
</form>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead><tr style="background:linear-gradient(135deg,var(--green-dark),var(--green-main))">
      <?php foreach([['left',$isRtl?'البرنامج':'Program'],['left',$isRtl?'المسجد':'Mosque'],['center',$isRtl?'الجدول':'Schedule'],['center',$isRtl?'المعلم':'Teacher'],['center',$isRtl?'الطلاب':'Students'],['center',$isRtl?'إجراء':'Action']] as $h):?>
      <th style="padding:11px 14px;color:#fff;text-align:<?=$h[0]?>;font-weight:600"><?=$h[1]?></th>
      <?php endforeach;?>
    </tr></thead>
    <tbody>
      <?php foreach($allProgs as $p):?>
      <tr style="border-top:1px solid var(--gray-50);<?=$p['teacher_id']?'':'background:#fffbeb'?>">
        <td style="padding:11px 14px">
          <div style="font-weight:700;color:var(--green-dark)"><?=h($isRtl?$p['name_ar']:$p['name_en'])?></div>
          <span style="background:var(--green-pale);color:var(--green-dark);padding:1px 6px;border-radius:99px;font-size:10px"><?=h($p['program_type'])?></span>
          <span style="background:var(--info-pale);color:var(--info);padding:1px 6px;border-radius:99px;font-size:10px;margin-<?=$isRtl?'right':'left'?>:3px">Slot <?=$p['slot']?></span>
        </td>
        <td style="padding:11px 14px">
          <div style="font-size:13px"><?=h($isRtl?$p['mosque_ar']:$p['mosque_en'])?></div>
          <div style="font-size:11px;color:var(--gray-500)">📍 <?=h($p['governorate'])?></div>
        </td>
        <td style="padding:11px 14px;text-align:center;font-size:12px">
          <div><?=str_replace(',','<br>',$p['days'])?></div>
          <div style="color:var(--gray-500)"><?=substr($p['time_start'],0,5)?>–<?=substr($p['time_end'],0,5)?></div>
        </td>
        <td style="padding:11px 14px;text-align:center">
          <?php if($p['teacher_id']):?>
          <div style="font-weight:600;color:var(--success);font-size:13px"><?=h($p['teacher_name'])?></div>
          <form method="POST" style="margin-top:4px" onsubmit="return confirm('Remove teacher?')">
        <?= csrfField() ?>
            <input type="hidden" name="action" value="remove_teacher"><input type="hidden" name="program_id" value="<?=$p['id']?>">
            <button type="submit" style="font-size:10px;background:none;border:1px solid var(--danger);color:var(--danger);border-radius:6px;padding:2px 8px;cursor:pointer"><?=$isRtl?'إزالة':'Remove'?></button>
          </form>
          <?php else:?>
          <span style="color:var(--warning);font-weight:600;font-size:12px">⚠️ <?=$isRtl?'شاغر':'Available'?></span>
          <?php if($p['pending_apps']>0):?><div style="font-size:11px;color:var(--info)">⏳ <?=$p['pending_apps']?> <?=$isRtl?'طلب':'app'?></div><?php endif;?>
          <?php endif;?>
        </td>
        <td style="padding:11px 14px;text-align:center;font-weight:700;color:var(--green-main)"><?=$p['enrolled']?>/<?=$p['max_students']?></td>
        <td style="padding:11px 14px;text-align:center">
          <form method="POST" onsubmit="return confirm('<?=$isRtl?'حذف هذا البرنامج نهائياً؟':'Delete this program permanently?'?>
        <?= csrfField() ?>')">
            <input type="hidden" name="action" value="delete_slot"><input type="hidden" name="program_id" value="<?=$p['id']?>">
            <button type="submit" style="background:var(--danger);color:#fff;border:none;border-radius:99px;padding:.3rem .75rem;font-size:11px;font-weight:600;cursor:pointer">🗑 <?=$isRtl?'حذف':'Delete'?></button>
          </form>
        </td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>

<?php elseif($tab==='applications'):?>
<?php if(empty($pendingApps)):?>
<div style="text-align:center;padding:60px;color:var(--gray-300)"><div style="font-size:48px">✅</div><div style="margin-top:12px"><?=$isRtl?'لا توجد طلبات معلقة':'No pending applications'?></div></div>
<?php else: foreach($pendingApps as $app):?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px;margin-bottom:12px">
  <div style="display:grid;grid-template-columns:1fr auto;gap:16px;align-items:start">
    <div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
        <div style="width:42px;height:42px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--green-dark);font-size:1.1rem;flex-shrink:0"><?=strtoupper(substr($app['teacher_name'],0,1))?></div>
        <div>
          <div style="font-weight:700;font-size:15px;color:var(--green-dark)"><?=h($app['teacher_name'])?></div>
          <div style="font-size:12px;color:var(--gray-500)"><?=h($app['teacher_email'])?><?=$app['teacher_phone']?' · '.h($app['teacher_phone']):''?></div>
        </div>
      </div>
      <div style="background:var(--gray-50);border-radius:10px;padding:12px;font-size:13px">
        <div style="font-weight:600;color:var(--green-dark);margin-bottom:4px">🕌 <?=h($isRtl?$app['mosque_ar']:$app['mosque_en'])?> — <?=h($app['governorate'])?></div>
        <div>📚 <?=h($isRtl?$app['prog_ar']:$app['prog_en'])?> (Slot <?=$app['slot']?>)</div>
        <div style="color:var(--gray-500);margin-top:3px">📅 <?=str_replace(',',' / ',$app['days'])?> · ⏰ <?=substr($app['time_start'],0,5)?>–<?=substr($app['time_end'],0,5)?></div>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px;min-width:130px">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="approve_teacher">
        <input type="hidden" name="app_id" value="<?=$app['id']?>">
        <input type="hidden" name="teacher_id" value="<?=$app['teacher_id']?>">
        <input type="hidden" name="program_id" value="<?=$app['program_id']?>">
        <button type="submit" class="btn btn-primary w-full">✅ <?=$isRtl?'قبول':'Approve'?></button>
      </form>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="reject_teacher">
        <input type="hidden" name="app_id" value="<?=$app['id']?>">
        <input type="hidden" name="teacher_id" value="<?=$app['teacher_id']?>">
        <button type="submit" class="btn btn-secondary w-full" style="color:var(--danger)">❌ <?=$isRtl?'رفض':'Reject'?></button>
      </form>
    </div>
  </div>
</div>
<?php endforeach; endif; ?>
<?php endif; ?>
</main>
</div>
<?php include '../includes/footer.php'; ?>
