<?php
require_once dirname(__DIR__) . '/config.php';
requireRole('mosque_admin');
$lang=$_SESSION['lang']??'en'; $isRtl=$lang==='ar';
$pdo=getPDO(); $userId=$_SESSION['user_id'];
$pageTitle=($isRtl?'طلاب المسجد — ':'Mosque Students — ').APP_NAME;

$mosque=$pdo->prepare("SELECT * FROM mosques WHERE admin_id=? LIMIT 1");
$mosque->execute([$userId]); $mosque=$mosque->fetch();
if(!$mosque){ $mosque=$pdo->prepare("SELECT * FROM mosques WHERE id=? LIMIT 1"); $mosque->execute([$_SESSION['user']['mosque_id']??0]); $mosque=$mosque->fetch(); }
if(!$mosque){ header('Location: /dashboard.php'); exit; }
$mid=$mosque['id'];

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action=$_POST['action']??'';
    $id=(int)($_POST['id']??0);
    if($action==='delete'&&$id){
        // Verify student belongs to this mosque
        $chk=$pdo->prepare("SELECT user_id FROM students WHERE id=? AND mosque_id=?");
        $chk->execute([$id,$mid]); $chk=$chk->fetch();
        if($chk!==false){
            $pdo->prepare("DELETE FROM program_enrollments WHERE student_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM enrollments WHERE student_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM attendance WHERE student_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM progress WHERE student_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM student_badges WHERE student_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM student_points WHERE student_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM students WHERE id=?")->execute([$id]);
            if($chk['user_id']){
                $pdo->prepare("DELETE FROM notifications WHERE user_id=?")->execute([$chk['user_id']]);
                $pdo->prepare("DELETE FROM users WHERE id=? AND role='student'")->execute([$chk['user_id']]);
            }
            setFlash('success',$isRtl?'✅ تم حذف الطالب وجميع بياناته':'✅ Student and all data deleted');
        }
        header('Location: /mosque_admin/students.php'); exit;
    }
}

$search=trim($_GET['search']??''); $progFil=(int)($_GET['program']??0);
$where=["s.mosque_id=$mid","s.is_active=1"]; $params=[];
if($search){$where[]="s.full_name LIKE ?"; $params[]="%$search%";}

$students=$pdo->prepare("SELECT s.*,
    (SELECT COUNT(*) FROM program_enrollments pe WHERE pe.student_id=s.id AND pe.status='active') as prog_count,
    (SELECT COUNT(*) FROM attendance a WHERE a.student_id=s.id AND a.status='present') as present_cnt,
    (SELECT COUNT(*) FROM attendance a WHERE a.student_id=s.id) as total_att,
    (SELECT COUNT(*) FROM progress p WHERE p.student_id=s.id AND p.memorization_pct=100) as surahs_done
    FROM students s WHERE ".implode(' AND ',$where)." ORDER BY s.full_name");
$students->execute($params); $students=$students->fetchAll();

$programs=$pdo->query("SELECT id,name_en,name_ar,slot FROM mosque_programs WHERE mosque_id=$mid AND is_active=1 ORDER BY slot")->fetchAll();

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<h1 style="font-size:1.5rem;font-weight:800;margin:0 0 4px">🎓 <?=$isRtl?'طلاب المسجد':'Mosque Students'?></h1>
<p style="color:var(--gray-500);font-size:13px;margin:0 0 20px">🕌 <?=h($isRtl?$mosque['name_ar']:$mosque['name_en'])?> · <?=count($students)?> <?=$isRtl?'طالب':'students'?></p>

<form method="GET" style="background:#fff;border:1px solid var(--gray-100);border-radius:12px;padding:10px 14px;margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
  <input name="search" value="<?=h($search)?>" placeholder="🔍 <?=$isRtl?'بحث...':'Search...'?>" style="flex:1;min-width:140px;padding:7px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:13px;font-family:inherit">
  <select name="program" style="padding:7px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:13px;font-family:inherit;background:#fff">
    <option value=""><?=$isRtl?'كل البرامج':'All Programs'?></option>
    <?php foreach($programs as $p):?><option value="<?=$p['id']?>" <?=$progFil==$p['id']?'selected':''?>><?=h($isRtl?$p['name_ar']:$p['name_en'])?></option><?php endforeach;?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm"><?=$isRtl?'بحث':'Search'?></button>
  <a href="?" class="btn btn-secondary btn-sm"><?=$isRtl?'كل':'All'?></a>
</form>

<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead><tr style="background:linear-gradient(135deg,var(--green-dark),var(--green-main))">
      <?php foreach([[$isRtl?'الطالب':'Student',$isRtl?'right':'left'],[$isRtl?'النوع':'Type','center'],[$isRtl?'البرامج':'Programs','center'],[$isRtl?'الحضور':'Attend','center'],[$isRtl?'السور':'Surahs','center'],[$isRtl?'الإجراءات':'Actions','center']] as [$h,$a]):?>
      <th style="padding:11px 14px;color:#fff;text-align:<?=$a?>;font-weight:600"><?=$h?></th>
      <?php endforeach;?>
    </tr></thead>
    <tbody>
      <?php foreach($students as $s):
        $att=$s['total_att']>0?round($s['present_cnt']/$s['total_att']*100):0;
        $attC=$att>=80?'var(--success)':($att>=60?'var(--warning)':'var(--danger)');
      ?>
      <tr style="border-bottom:1px solid var(--gray-50)">
        <td style="padding:11px 14px">
          <div style="font-weight:700;color:var(--dark)"><?=h($s['full_name'])?></div>
          <?php if($s['full_name_ar']):?><div style="font-size:11px;color:var(--gray-500)"><?=h($s['full_name_ar'])?></div><?php endif;?>
        </td>
        <td style="padding:11px 14px;text-align:center">
          <span style="background:<?=($s['student_type']??'student')==='child'?'#DBEAFE':'#EDE9FE'?>;color:<?=($s['student_type']??'student')==='child'?'#1D4ED8':'#4C1D95'?>;padding:2px 10px;border-radius:99px;font-size:11px;font-weight:700">
            <?=($s['student_type']??'student')==='child'?'👶':' 🎓'?>
          </span>
        </td>
        <td style="padding:11px 14px;text-align:center;font-weight:700;color:var(--green-main)"><?=$s['prog_count']?></td>
        <td style="padding:11px 14px;text-align:center"><span style="font-weight:700;color:<?=$attC?>"><?=$att?>%</span></td>
        <td style="padding:11px 14px;text-align:center;font-weight:700;color:var(--green-main)"><?=$s['surahs_done']?></td>
        <td style="padding:11px 14px;text-align:center">
          <div style="display:flex;gap:4px;justify-content:center">
            <a href="/teacher/student_profile.php?id=<?=$s['id']?>" style="background:#4C1D95;color:#fff;border-radius:99px;padding:.3rem .75rem;font-size:11px;font-weight:600;text-decoration:none">👤</a>
            <form method="POST" style="display:inline" onsubmit="return confirm('<?=$isRtl?'⚠️ حذف الطالب وجميع بياناته؟':'⚠️ Delete student and ALL data?'?>
        <?= csrfField() ?>')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?=$s['id']?>">
              <button type="submit" style="background:#FEE2E2;color:#DC2626;border:1px solid #DC2626;border-radius:99px;padding:.3rem .75rem;font-size:11px;font-weight:600;cursor:pointer">🗑</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach;?>
      <?php if(empty($students)):?><tr><td colspan="5" style="padding:40px;text-align:center;color:var(--gray-300)"><div style="font-size:36px">🎓</div><div style="margin-top:8px"><?=$isRtl?'لا توجد نتائج':'No students'?></div></td></tr><?php endif;?>
    </tbody>
  </table>
</div>
</main>
</div>
<?php include '../includes/footer.php'; ?>
