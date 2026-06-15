<?php
require_once __DIR__ . '/../config.php';
requireRole('admin');
$lang=$_SESSION['lang']??'en'; $isRtl=$lang==='ar';
$pdo=getPDO(); $pageTitle=($isRtl?'إدارة الطلاب — ':'Manage Students — ').APP_NAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action=$_POST['action']??''; $id=(int)($_POST['id']??0);
    if($action==='delete'&&$id){
        // Get user_id before deletion
        $sInfo=$pdo->prepare("SELECT user_id FROM students WHERE id=?"); $sInfo->execute([$id]); $sInfo=$sInfo->fetch();
        $pdo->prepare("DELETE FROM program_enrollments WHERE student_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM enrollments WHERE student_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM attendance WHERE student_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM progress WHERE student_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM student_badges WHERE student_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM student_points WHERE student_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM students WHERE id=?")->execute([$id]);
        // Also delete user account if linked
        if($sInfo&&$sInfo['user_id']){
            $pdo->prepare("DELETE FROM notifications WHERE user_id=?")->execute([$sInfo['user_id']]);
            $pdo->prepare("DELETE FROM users WHERE id=? AND role IN ('student','child')")->execute([$sInfo['user_id']]);
        }
        setFlash('success',$isRtl?'✅ تم حذف الطالب وجميع بياناته':'✅ Student and all data deleted');
    } elseif($action==='toggle'&&$id){
        $pdo->prepare("UPDATE students SET is_active=1-is_active WHERE id=?")->execute([$id]);
        setFlash('success',$isRtl?'تم تحديث الحالة':'Updated');
    }
    header('Location: /admin/students.php'); exit;
}

$search=trim($_GET['search']??''); $govFil=trim($_GET['gov']??''); $typeFil=trim($_GET['type']??'');
$where=['1=1']; $params=[];
if($search){$where[]='(s.full_name LIKE ? OR s.full_name_ar LIKE ?)';$params[]="%$search%";$params[]="%$search%";}
if($govFil){$where[]='m.governorate=?';$params[]=$govFil;}
if($typeFil){$where[]='s.student_type=?';$params[]=$typeFil;}
$ws=implode(' AND ',$where);

$students=$pdo->prepare("SELECT s.*,m.name_en as mosque_en,m.name_ar as mosque_ar,m.governorate,
    u.full_name as parent_name,
    (SELECT COUNT(*) FROM program_enrollments pe WHERE pe.student_id=s.id AND pe.status='active') as prog_count,
    (SELECT COUNT(*) FROM progress p WHERE p.student_id=s.id AND p.memorization_pct=100) as surahs_done
    FROM students s JOIN mosques m ON m.id=s.mosque_id LEFT JOIN users u ON u.id=s.parent_id
    WHERE $ws ORDER BY m.governorate,s.full_name");
$students->execute($params); $students=$students->fetchAll();
$govs=$pdo->query("SELECT DISTINCT governorate FROM mosques ORDER BY governorate")->fetchAll(PDO::FETCH_COLUMN);
include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:1.5rem;font-weight:800;margin:0">🎓 <?=$isRtl?'إدارة الطلاب':'Manage Students'?></h1>
    <p style="color:var(--gray-500);font-size:13px;margin:4px 0 0"><?=count($students)?> <?=$isRtl?'طالب':'students'?></p>
  </div>
</div>
<form method="GET" style="background:#fff;border:1px solid var(--gray-100);border-radius:12px;padding:12px 16px;margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
  <input name="search" value="<?=h($search)?>" placeholder="🔍 <?=$isRtl?'بحث...':'Search...'?>" style="flex:1;min-width:140px;padding:7px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:13px;font-family:inherit">
  <select name="gov" style="padding:7px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:13px;font-family:inherit;background:#fff">
    <option value=""><?=$isRtl?'كل المحافظات':'All Gov'?></option>
    <?php foreach($govs as $g):?><option value="<?=h($g)?>" <?=$govFil===$g?'selected':''?>><?=h($g)?></option><?php endforeach;?>
  </select>
  <select name="type" style="padding:7px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:13px;font-family:inherit;background:#fff">
    <option value=""><?=$isRtl?'الكل':'All'?></option>
    <option value="student" <?=$typeFil==='student'?'selected':''?>><?=$isRtl?'طالب':'Student'?></option>
    <option value="child" <?=$typeFil==='child'?'selected':''?>><?=$isRtl?'طفل':'Child'?></option>
  </select>
  <button type="submit" class="btn btn-primary btn-sm"><?=$isRtl?'بحث':'Search'?></button>
  <a href="?" class="btn btn-secondary btn-sm"><?=$isRtl?'كل':'All'?></a>
</form>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead><tr style="background:linear-gradient(135deg,var(--green-dark),var(--green-main))">
      <?php foreach([[$isRtl?'الطالب':'Student'],[$isRtl?'المسجد':'Mosque'],[$isRtl?'النوع':'Type','center'],[$isRtl?'البرامج':'Programs','center'],[$isRtl?'السور':'Surahs','center'],[$isRtl?'الحالة':'Status','center'],[$isRtl?'إجراء':'Action','center']] as $h):?>
      <th style="padding:11px 14px;color:#fff;text-align:<?=$h[1]??($isRtl?'right':'left')?>;font-weight:600"><?=$h[0]?></th>
      <?php endforeach;?>
    </tr></thead>
    <tbody>
      <?php foreach($students as $s):?>
      <tr style="border-bottom:1px solid var(--gray-50)" onmouseover="this.style.background='#f9f9f9'" onmouseout="this.style.background=''">
        <td style="padding:11px 14px">
          <div style="font-weight:700;color:var(--dark)"><?=h($s['full_name'])?></div>
          <?php if($s['full_name_ar']):?><div style="font-size:11px;color:var(--gray-500)"><?=h($s['full_name_ar'])?></div><?php endif;?>
          <?php if($s['parent_name']):?><div style="font-size:11px;color:var(--info)">👤 <?=h($s['parent_name'])?></div><?php endif;?>
        </td>
        <td style="padding:11px 14px">
          <div><?=h($isRtl?$s['mosque_ar']:$s['mosque_en'])?></div>
          <div style="font-size:11px;color:var(--gray-500)">📍 <?=h($s['governorate'])?></div>
        </td>
        <td style="padding:11px 14px;text-align:center">
          <span style="background:<?=($s['student_type']??'student')==='child'?'#DBEAFE':'#EDE9FE'?>;color:<?=($s['student_type']??'student')==='child'?'#1D4ED8':'#4C1D95'?>;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700">
            <?=($s['student_type']??'student')==='child'?($isRtl?'👶 طفل':'👶 Child'):($isRtl?'🎓 طالب':'🎓 Student')?>
          </span>
        </td>
        <td style="padding:11px 14px;text-align:center;font-weight:700;color:var(--green-main)"><?=$s['prog_count']?></td>
        <td style="padding:11px 14px;text-align:center;font-weight:700;color:var(--green-main)"><?=$s['surahs_done']?></td>
        <td style="padding:11px 14px;text-align:center">
          <span style="background:<?=$s['is_active']?'var(--success-pale)':'var(--danger-pale)'?>;color:<?=$s['is_active']?'var(--success)':'var(--danger)'?>;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700">
            <?=$s['is_active']?($isRtl?'نشط':'Active'):($isRtl?'معطّل':'Inactive')?>
          </span>
        </td>
        <td style="padding:11px 14px;text-align:center">
          <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap">
            <a href="/teacher/student_profile.php?id=<?=$s['id']?>" style="background:#4C1D95;color:#fff;border-radius:99px;padding:.3rem .75rem;font-size:11px;font-weight:600;text-decoration:none">👤 <?=$isRtl?'ملف':'Profile'?></a>
            <form method="POST" style="display:inline">
        <?= csrfField() ?>
              <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$s['id']?>">
              <button type="submit" class="btn btn-secondary btn-sm" style="font-size:11px"><?=$s['is_active']?($isRtl?'تعطيل':'Disable'):($isRtl?'تفعيل':'Enable')?></button>
            </form>
            <form method="POST" style="display:inline" onsubmit="return confirm('<?=$isRtl?'⚠️ حذف الطالب وجميع بياناته نهائياً؟':'⚠️ Delete student and ALL their data permanently?'?>
        <?= csrfField() ?>')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$s['id']?>">
              <button type="submit" style="background:var(--danger);color:#fff;border:none;border-radius:99px;padding:.3rem .75rem;font-size:11px;font-weight:600;cursor:pointer">🗑 <?=$isRtl?'حذف':'Del'?></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach;?>
      <?php if(empty($students)):?>
      <tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-300)"><div style="font-size:36px">🎓</div><div style="margin-top:8px"><?=$isRtl?'لا توجد نتائج':'No results'?></div></td></tr>
      <?php endif;?>
    </tbody>
  </table>
</div>
</main>
</div>
<?php include '../includes/footer.php'; ?>
