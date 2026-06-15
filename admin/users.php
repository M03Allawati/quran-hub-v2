<?php
require_once __DIR__ . '/../config.php';
requireRole('admin');
$lang=$_SESSION['lang']??'en'; $isRtl=$lang==='ar';
$pdo=getPDO(); $pageTitle=($isRtl?'إدارة المستخدمين — ':'Manage Users — ').APP_NAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action=$_POST['action']??''; $id=(int)($_POST['id']??0);
    if($action==='delete'&&$id&&$id!==$_SESSION['user_id']){
        $pdo->prepare("DELETE FROM notifications WHERE user_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM conversation_participants WHERE user_id=?")->execute([$id]);
        $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$id]);
        setFlash('success',$isRtl?'تم حذف المستخدم':'User deleted');
    } elseif($action==='toggle'&&$id){
        $pdo->prepare("UPDATE users SET is_active=1-is_active WHERE id=?")->execute([$id]);
        setFlash('success',$isRtl?'تم تحديث الحالة':'Updated');
    } elseif($action==='create'){
        // PASSWORD_MIN: enforce minimum password strength
        if (strlen($_POST['password'] ?? '') < 8) { setFlash('danger','Password must be at least 8 characters'); header('Location: /admin/users.php'); exit; }
        $hash=password_hash($_POST['password'],PASSWORD_BCRYPT,['cost'=>12]);
        $role=$_POST['role'];
        $mosqueId=(int)$_POST['mosque_id'];
        $pdo->prepare("INSERT INTO users (full_name,username,email,password_hash,role,mosque_id,governorate,phone,is_active) VALUES (?,?,?,?,?,?,?,?,1)")
            ->execute([$_POST['full_name'],$_POST['username'],$_POST['email'],$hash,$role,$mosqueId,$_POST['governorate']??'',$_POST['phone']??'']);
        $newUserId=(int)$pdo->lastInsertId();
        // If mosque_admin — assign to mosque
        if($role==='mosque_admin' && $mosqueId){
            // Revert any existing mosque admin to teacher
            $cur=$pdo->prepare("SELECT admin_id FROM mosques WHERE id=?");
            $cur->execute([$mosqueId]); $cur=$cur->fetch();
            if($cur&&$cur['admin_id']){
                $pdo->prepare("UPDATE users SET role='teacher' WHERE id=? AND role='mosque_admin'")->execute([$cur['admin_id']]);
            }
            $pdo->prepare("UPDATE mosques SET admin_id=? WHERE id=?")->execute([$newUserId,$mosqueId]);
        }
        setFlash('success',$isRtl?'تم إنشاء المستخدم':'User created');
    } elseif($action==='reset_password'){
        if (strlen($_POST['new_password'] ?? '') < 8) { setFlash('danger','Password must be at least 8 characters'); header('Location: /admin/users.php'); exit; }
        $hash=password_hash($_POST['new_password'],PASSWORD_BCRYPT,['cost'=>12]);
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash,$id]);
        setFlash('success',$isRtl?'تم تغيير كلمة المرور':'Password reset');
    }
    header('Location: /admin/users.php'); exit;
}

$roleFil=trim($_GET['role']??''); $search=trim($_GET['search']??'');
$where=['1=1']; $params=[];
if($roleFil){$where[]='u.role=?';$params[]=$roleFil;}
if($search){$where[]='(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';$params[]="%$search%";$params[]="%$search%";$params[]="%$search%";}
$ws=implode(' AND ',$where);
$users=$pdo->prepare("SELECT u.*,m.name_en as mosque_name FROM users u LEFT JOIN mosques m ON u.mosque_id=m.id WHERE $ws ORDER BY u.created_at DESC");
$users->execute($params); $users=$users->fetchAll();
$mosques=$pdo->query("SELECT id,name_en,governorate FROM mosques WHERE is_active=1 ORDER BY governorate,name_en")->fetchAll();
$govs=$pdo->query("SELECT DISTINCT governorate FROM mosques ORDER BY governorate")->fetchAll(PDO::FETCH_COLUMN);
$roleCounts=[];
foreach($pdo->query("SELECT role,COUNT(*) as cnt FROM users GROUP BY role")->fetchAll() as $r) $roleCounts[$r['role']]=$r['cnt'];
include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:1.5rem;font-weight:800;margin:0">👥 <?=$isRtl?'إدارة المستخدمين':'Manage Users'?></h1>
    <p style="color:var(--gray-500);font-size:13px;margin:4px 0 0"><?=count($users)?> <?=$isRtl?'مستخدم':'users'?></p>
  </div>
  <button onclick="document.getElementById('createModal').style.display='flex'" class="btn btn-primary">
    + <?=$isRtl?'مستخدم جديد':'New User'?>
  </button>
</div>

<!-- Role stats -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <?php foreach(['admin'=>['⚙️',$isRtl?'مدير':'Admin'],'mosque_admin'=>['🕌',$isRtl?'مدير مسجد':'Mosque Admin'],'teacher'=>['👨‍🏫',$isRtl?'معلم':'Teacher'],'parent'=>['👨‍👩‍👧',$isRtl?'ولي':'Parent'],'student'=>['🎓',$isRtl?'طالب':'Student']] as $role=>[$icon,$label]):?>
  <a href="?role=<?=$role?>" style="padding:6px 14px;background:<?=$roleFil===$role?'var(--green-main)':'#fff'?>;color:<?=$roleFil===$role?'#fff':'var(--dark)'?>;border:1px solid var(--gray-100);border-radius:99px;font-size:13px;text-decoration:none;font-weight:600">
    <?=$icon?> <?=$label?> <span style="opacity:.7">(<?=$roleCounts[$role]??0?>)</span>
  </a>
  <?php endforeach;?>
  <?php if($roleFil):?><a href="?" style="padding:6px 14px;background:var(--gray-50);color:var(--gray-500);border:1px solid var(--gray-100);border-radius:99px;font-size:13px;text-decoration:none">✕ <?=$isRtl?'إلغاء':'Clear'?></a><?php endif;?>
</div>

<!-- Search -->
<form method="GET" style="background:#fff;border:1px solid var(--gray-100);border-radius:12px;padding:10px 14px;margin-bottom:16px;display:flex;gap:8px">
  <?php if($roleFil):?><input type="hidden" name="role" value="<?=h($roleFil)?>"><?php endif;?>
  <input name="search" value="<?=h($search)?>" placeholder="🔍 <?=$isRtl?'اسم أو بريد أو username...':'Name, email or username...'?>" style="flex:1;padding:7px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:13px;font-family:inherit">
  <button type="submit" class="btn btn-primary btn-sm"><?=$isRtl?'بحث':'Search'?></button>
</form>

<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead><tr style="background:linear-gradient(135deg,var(--green-dark),var(--green-main))">
      <th style="padding:11px 14px;color:#fff;text-align:<?=$isRtl?'right':'left'?>;font-weight:600"><?=$isRtl?'المستخدم':'User'?></th>
      <th style="padding:11px 14px;color:#fff;text-align:<?=$isRtl?'right':'left'?>;font-weight:600"><?=$isRtl?'الدور / المسجد':'Role / Mosque'?></th>
      <th style="padding:11px 14px;color:#fff;text-align:center;font-weight:600"><?=$isRtl?'المحافظة':'Gov'?></th>
      <th style="padding:11px 14px;color:#fff;text-align:center;font-weight:600"><?=$isRtl?'الحالة':'Status'?></th>
      <th style="padding:11px 14px;color:#fff;text-align:center;font-weight:600"><?=$isRtl?'إجراء':'Action'?></th>
    </tr></thead>
    <tbody>
      <?php foreach($users as $u):
        $roleColors=['admin'=>'#DC2626','mosque_admin'=>'#6D28D9','teacher'=>'#2563EB','parent'=>'#D97706','student'=>'#059669'];
        $roleLabels=['admin'=>($isRtl?'مدير':'Admin'),'mosque_admin'=>($isRtl?'مدير مسجد':'Mosque Admin'),'teacher'=>($isRtl?'معلم':'Teacher'),'parent'=>($isRtl?'ولي أمر':'Parent'),'student'=>($isRtl?'طالب':'Student')];
      ?>
      <tr style="border-bottom:1px solid var(--gray-50)" onmouseover="this.style.background='#f9f9f9'" onmouseout="this.style.background=''">
        <td style="padding:11px 14px">
          <div style="font-weight:700;color:var(--dark)"><?=h($u['full_name'])?></div>
          <div style="font-size:11px;color:var(--gray-500)">@<?=h($u['username'])?> · <?=h($u['email'])?></div>
          <?php if($u['phone']):?><div style="font-size:11px;color:var(--gray-400)">📞 <?=h($u['phone'])?></div><?php endif;?>
        </td>
        <td style="padding:11px 14px">
          <span style="background:<?=$roleColors[$u['role']]??'#6B7280'?>22;color:<?=$roleColors[$u['role']]??'#6B7280'?>;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700"><?=$roleLabels[$u['role']]??$u['role']?></span>
          <?php if($u['mosque_name']):?><div style="font-size:11px;color:var(--gray-500);margin-top:3px">🕌 <?=h($u['mosque_name'])?></div><?php endif;?>
        </td>
        <td style="padding:11px 14px;text-align:center;font-size:12px;color:var(--gray-500)"><?=h($u['governorate']??'—')?></td>
        <td style="padding:11px 14px;text-align:center">
          <span style="background:<?=$u['is_active']?'var(--success-pale)':'var(--danger-pale)'?>;color:<?=$u['is_active']?'var(--success)':'var(--danger)'?>;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700">
            <?=$u['is_active']?($isRtl?'نشط':'Active'):($isRtl?'معطّل':'Inactive')?>
          </span>
        </td>
        <td style="padding:11px 14px;text-align:center">
          <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap">
            <!-- Reset password -->
            <button onclick="showResetPwd(<?=$u['id']?>, '<?=addslashes($u['full_name'])?>')" class="btn btn-secondary btn-sm" style="font-size:11px">
              🔑 <?=$isRtl?'كلمة مرور':'Pwd'?>
            </button>
            <!-- Toggle -->
            <form method="POST" style="display:inline">
        <?= csrfField() ?>
              <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$u['id']?>">
              <button type="submit" class="btn btn-secondary btn-sm" style="font-size:11px"><?=$u['is_active']?($isRtl?'تعطيل':'Disable'):($isRtl?'تفعيل':'Enable')?></button>
            </form>
            <!-- Delete (not self) -->
            <?php if($u['id']!==$_SESSION['user_id']&&$u['role']!=='admin'):?>
            <form method="POST" style="display:inline" onsubmit="return confirm('<?=$isRtl?'حذف هذا المستخدم؟':'Delete this user?'?>
        <?= csrfField() ?>')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$u['id']?>">
              <button type="submit" style="background:var(--danger);color:#fff;border:none;border-radius:99px;padding:.3rem .75rem;font-size:11px;font-weight:600;cursor:pointer">🗑 <?=$isRtl?'حذف':'Del'?></button>
            </form>
            <?php endif;?>
          </div>
        </td>
      </tr>
      <?php endforeach;?>
      <?php if(empty($users)):?>
      <tr><td colspan="5" style="padding:40px;text-align:center;color:var(--gray-300)"><div style="font-size:36px">👥</div><div style="margin-top:8px"><?=$isRtl?'لا توجد نتائج':'No results'?></div></td></tr>
      <?php endif;?>
    </tbody>
  </table>
</div>
</main>
</div>

<!-- Create User Modal -->
<div id="createModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;padding:28px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lg)">
    <div style="display:flex;justify-content:space-between;margin-bottom:20px">
      <h3 style="margin:0;color:var(--green-dark)">+ <?=$isRtl?'مستخدم جديد':'New User'?></h3>
      <button onclick="document.getElementById('createModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--gray-500)">✕</button>
    </div>
    <form method="POST">
        <?= csrfField() ?>
      <input type="hidden" name="action" value="create">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label"><?=$isRtl?'الاسم':'Full Name'?> *</label>
          <input type="text" name="full_name" class="form-control" placeholder="Ahmed Al-Battashi" required>
        </div>
        <div class="form-group">
          <label class="form-label"><?=$isRtl?'اسم المستخدم':'Username'?> *</label>
          <input type="text" name="username" class="form-control" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">📧 <?=$isRtl?'البريد':'Email'?> *</label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label"><?=$isRtl?'الدور':'Role'?> *</label>
          <select name="role" class="form-control form-select" required onchange="toggleMosqueField(this.value)">
            <option value="mosque_admin"><?=$isRtl?'مدير مسجد':'Mosque Admin'?> 🕌</option>
            <option value="teacher"><?=$isRtl?'معلم':'Teacher'?></option>
            <option value="parent"><?=$isRtl?'ولي أمر':'Parent'?></option>
            <option value="student"><?=$isRtl?'طالب':'Student'?></option>
            <option value="admin"><?=$isRtl?'مدير عام':'Super Admin'?></option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">📞 <?=$isRtl?'الهاتف':'Phone'?></label>
          <input type="tel" name="phone" class="form-control" placeholder="+968 9XXX XXXX">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">🕌 <?=$isRtl?'المسجد':'Mosque'?> <span id="mosqueReq" style="color:var(--danger);font-size:10px">* <?=$isRtl?'مطلوب لمدير المسجد':'Required for Mosque Admin'?></span></label>
        <select name="mosque_id" class="form-control form-select" onchange="this.form.governorate.value=this.options[this.selectedIndex].dataset.gov||''">
          <option value="0"><?=$isRtl?'-- اختر --':'-- Select --'?></option>
          <?php foreach($mosques as $m):?>
          <option value="<?=$m['id']?>" data-gov="<?=h($m['governorate'])?>"><?=h($m['name_en'])?> (<?=h($m['governorate'])?>)</option>
          <?php endforeach;?>
        </select>
      </div>
      <input type="hidden" name="governorate" value="">
      <div class="form-group">
        <label class="form-label">🔒 <?=$isRtl?'كلمة المرور':'Password'?> *</label>
        <input type="password" name="password" class="form-control" placeholder="Min 8 chars" required>
      </div>
      <div style="display:flex;gap:8px;margin-top:4px">
        <button type="submit" class="btn btn-primary" style="flex:1">✅ <?=$isRtl?'إنشاء':'Create'?></button>
        <button type="button" onclick="document.getElementById('createModal').style.display='none'" class="btn btn-secondary"><?=$isRtl?'إلغاء':'Cancel'?></button>
      </div>
    </form>
  </div>
</div>

<!-- Reset Password Modal -->
<div id="resetModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;padding:28px;width:100%;max-width:380px;box-shadow:var(--shadow-lg)">
    <h3 style="margin:0 0 16px;color:var(--green-dark)">🔑 <?=$isRtl?'إعادة تعيين كلمة المرور':'Reset Password'?></h3>
    <p id="resetName" style="color:var(--gray-500);font-size:13px;margin-bottom:16px"></p>
    <form method="POST">
        <?= csrfField() ?>
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="id" id="resetUserId">
      <div class="form-group">
        <label class="form-label"><?=$isRtl?'كلمة المرور الجديدة':'New Password'?> *</label>
        <input type="password" name="new_password" class="form-control" placeholder="Min 8 chars" required>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary" style="flex:1">✅ <?=$isRtl?'حفظ':'Save'?></button>
        <button type="button" onclick="document.getElementById('resetModal').style.display='none'" class="btn btn-secondary"><?=$isRtl?'إلغاء':'Cancel'?></button>
      </div>
    </form>
  </div>
</div>

<script>
function showResetPwd(id, name) {
    document.getElementById('resetUserId').value = id;
    document.getElementById('resetName').textContent = name;
    document.getElementById('resetModal').style.display = 'flex';
}
function toggleMosqueField(role){
    const req = document.getElementById('mosqueReq');
    if(req) req.style.display = role==='mosque_admin'?'inline':'none';
}
// Init on load
document.addEventListener('DOMContentLoaded',function(){
    const roleEl = document.querySelector('[name="role"]');
    if(roleEl) toggleMosqueField(roleEl.value);
});
</script>
<?php include '../includes/footer.php'; ?>
