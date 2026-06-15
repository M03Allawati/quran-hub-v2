<?php
require_once __DIR__ . '/config.php';
requireLogin();
$lang=$_SESSION['lang']??'en'; $isRtl=$lang==='ar';
$pdo=getPDO(); $userId=$_SESSION['user_id'];
$role=$_SESSION['role'];
$pageTitle=($isRtl?'ملفي الشخصي — ':'My Profile — ').APP_NAME;

$govs=['Muscat','Dhofar','Musandam','Al Buraimi','Al Batinah North','Al Batinah South','Al Dhahirah','Al Dakhiliyah','Al Sharqiyah North','Al Sharqiyah South','Al Wusta'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action=$_POST['action']??'';
    if($action==='update_profile'){
        $fn=trim($_POST['full_name']??'');
        $fnar=trim($_POST['full_name_ar']??'');
        $ph=trim($_POST['phone']??'');
        $gov=$_POST['governorate']??'';
        $errs=[];
        if(!$fn) $errs[]=$isRtl?'الاسم مطلوب':'Name required';
        if(!$gov) $errs[]=$isRtl?'المحافظة مطلوبة':'Governorate required';
        if(!$errs){
            $pdo->prepare("UPDATE users SET full_name=?,full_name_ar=?,phone=?,governorate=? WHERE id=?")
                ->execute([$fn,$fnar,$ph,$gov,$userId]);
            // Update session
            $_SESSION['user']['full_name']=$fn;
            $_SESSION['user']['full_name_ar']=$fnar;
            $_SESSION['user']['governorate']=$gov;
            setFlash('success',$isRtl?'✅ تم تحديث الملف الشخصي':'✅ Profile updated!');
        } else {
            setFlash('danger',implode('<br>',$errs));
        }
        header('Location: /profile.php'); exit;
    }
    if($action==='change_password'){
        $curr=$_POST['current_password']??'';
        $new=$_POST['new_password']??'';
        $conf=$_POST['confirm_password']??'';
        $user=$pdo->prepare("SELECT password_hash FROM users WHERE id=?");
        $user->execute([$userId]); $user=$user->fetch();
        if(!password_verify($curr,$user['password_hash'])){
            setFlash('danger',$isRtl?'كلمة المرور الحالية غير صحيحة':'Current password is incorrect');
        } elseif(strlen($new)<8){
            setFlash('danger',$isRtl?'كلمة المرور الجديدة 8 أحرف على الأقل':'New password min 8 chars');
        } elseif($new!==$conf){
            setFlash('danger',$isRtl?'كلمتا المرور غير متطابقتين':'Passwords do not match');
        } else {
            $hash=password_hash($new,PASSWORD_BCRYPT,['cost'=>12]);
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash,$userId]);
            setFlash('success',$isRtl?'✅ تم تغيير كلمة المرور':'✅ Password changed!');
        }
        header('Location: /profile.php'); exit;
    }
}

$user=$pdo->prepare("SELECT * FROM users WHERE id=?");
$user->execute([$userId]); $user=$user->fetch();

$mosque=null;
if($user['mosque_id']){
    $mosque=$pdo->prepare("SELECT name_en,name_ar,governorate,wilayat FROM mosques WHERE id=?");
    $mosque->execute([$user['mosque_id']]); $mosque=$mosque->fetch();
}

// Teacher slot info
$teacherProg=null;
if($role==='teacher'){
    $tp=$pdo->prepare("SELECT mp.*,m.name_en as mosque_en FROM mosque_programs mp JOIN mosques m ON m.id=mp.mosque_id WHERE mp.teacher_id=? AND mp.is_active=1 LIMIT 1");
    $tp->execute([$userId]); $teacherProg=$tp->fetch();
}

$roleColors=['admin'=>'#DC2626','mosque_admin'=>'#6D28D9','teacher'=>'#2563EB','parent'=>'#D97706','student'=>'#059669'];
$roleLabels=['admin'=>'Admin','mosque_admin'=>'Mosque Admin','teacher'=>'Teacher','parent'=>'Parent','student'=>'Student'];
$roleColor=$roleColors[$role]??'#4C1D95';

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">

<h1 style="font-size:1.5rem;font-weight:800;margin:0 0 20px">👤 <?=$isRtl?'ملفي الشخصي':'My Profile'?></h1>

<div style="display:grid;grid-template-columns:300px 1fr;gap:16px;align-items:start">

<!-- LEFT: Profile Card -->
<div>
  <div style="background:linear-gradient(135deg,#4C1D95,#6D28D9);border-radius:16px;padding:24px;text-align:center;color:#fff;margin-bottom:12px">
    <div style="width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;margin:0 auto 12px">
      <?=strtoupper(substr($user['full_name'],0,1))?>
    </div>
    <div style="font-weight:800;font-size:1.1rem"><?=h($user['full_name'])?></div>
    <?php if($user['full_name_ar']):?><div style="opacity:.8;font-size:13px;margin-top:2px"><?=h($user['full_name_ar'])?></div><?php endif;?>
    <div style="margin-top:8px">
      <span style="background:rgba(255,255,255,.2);padding:3px 12px;border-radius:99px;font-size:12px;font-weight:700">
        <?=$roleLabels[$role]??$role?>
      </span>
    </div>
    <div style="font-size:12px;opacity:.75;margin-top:8px">@<?=h($user['username'])?></div>
    <div style="font-size:12px;opacity:.75"><?=h($user['email'])?></div>
  </div>

  <!-- Info Card -->
  <div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:16px;margin-bottom:12px">
    <div style="font-size:11px;font-weight:700;color:var(--gray-400);text-transform:uppercase;margin-bottom:10px">Details</div>
    <?php foreach([
      ['📍',$isRtl?'المحافظة':'Governorate',$user['governorate']??'—'],
      ['📱',$isRtl?'الهاتف':'Phone',$user['phone']??'—'],
      ['🗓️',$isRtl?'تاريخ التسجيل':'Member Since',date('d M Y',strtotime($user['created_at']))],
    ] as [$icon,$label,$val]):?>
    <div style="display:flex;gap:8px;align-items:center;padding:6px 0;border-bottom:1px solid var(--gray-50)">
      <span style="font-size:14px"><?=$icon?></span>
      <div style="flex:1">
        <div style="font-size:10px;color:var(--gray-400)"><?=$label?></div>
        <div style="font-size:13px;font-weight:600;color:var(--green-dark)"><?=h($val)?></div>
      </div>
    </div>
    <?php endforeach;?>
    <?php if($mosque):?>
    <div style="display:flex;gap:8px;align-items:center;padding:6px 0;border-bottom:1px solid var(--gray-50)">
      <span>🕌</span>
      <div style="flex:1">
        <div style="font-size:10px;color:var(--gray-400)"><?=$isRtl?'المسجد':'Mosque'?></div>
        <div style="font-size:13px;font-weight:600;color:var(--green-dark)"><?=h($isRtl?$mosque['name_ar']:$mosque['name_en'])?></div>
        <div style="font-size:11px;color:var(--gray-400)"><?=h($mosque['wilayat'])?></div>
      </div>
    </div>
    <?php endif;?>
    <?php if($teacherProg):?>
    <div style="display:flex;gap:8px;align-items:center;padding:6px 0">
      <span><?=$teacherProg['slot']==='A'?'🎓':'👶'?></span>
      <div style="flex:1">
        <div style="font-size:10px;color:var(--gray-400)">Slot <?=$teacherProg['slot']?></div>
        <div style="font-size:13px;font-weight:600;color:var(--green-dark)"><?=h($isRtl?$teacherProg['name_ar']:$teacherProg['name_en'])?></div>
        <div style="font-size:11px;color:var(--gray-400)"><?=$teacherProg['target_type']==='child'?'👶 Children':'🎓 Students'?></div>
      </div>
    </div>
    <?php endif;?>
  </div>
</div>

<!-- RIGHT: Edit Forms -->
<div>
  <!-- Edit Profile -->
  <div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px;margin-bottom:14px">
    <h3 style="font-size:14px;font-weight:700;color:var(--green-dark);margin:0 0 16px">✏️ <?=$isRtl?'تعديل المعلومات':'Edit Profile'?></h3>
    <form method="POST">
        <?= csrfField() ?>
      <input type="hidden" name="action" value="update_profile">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label"><?=$isRtl?'الاسم الكامل (إنجليزي)':'Full Name (English)'?> *</label>
          <input type="text" name="full_name" class="form-control" value="<?=h($user['full_name'])?>" required>
        </div>
        <div class="form-group">
          <label class="form-label"><?=$isRtl?'الاسم الكامل (عربي)':'Full Name (Arabic)'?></label>
          <input type="text" name="full_name_ar" class="form-control" dir="rtl" value="<?=h($user['full_name_ar']??'')?>">
        </div>
        <div class="form-group">
          <label class="form-label">📱 <?=$isRtl?'رقم الهاتف':'Phone Number'?></label>
          <input type="tel" name="phone" class="form-control" value="<?=h($user['phone']??'')?>" placeholder="+968 XXXXXXXX">
        </div>
        <div class="form-group">
          <label class="form-label">📍 <?=$isRtl?'المحافظة':'Governorate'?> *</label>
          <select name="governorate" class="form-control form-select" required>
            <option value="">-- <?=$isRtl?'اختر':'Select'?> --</option>
            <?php foreach($govs as $g):?>
            <option value="<?=$g?>" <?=$user['governorate']===$g?'selected':''?>><?=$g?></option>
            <?php endforeach;?>
          </select>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;margin-top:8px">
        <button type="submit" class="btn btn-primary">💾 <?=$isRtl?'حفظ التغييرات':'Save Changes'?></button>
      </div>
    </form>
  </div>

  <!-- Change Password -->
  <div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px">
    <h3 style="font-size:14px;font-weight:700;color:var(--green-dark);margin:0 0 16px">🔐 <?=$isRtl?'تغيير كلمة المرور':'Change Password'?></h3>
    <form method="POST">
        <?= csrfField() ?>
      <input type="hidden" name="action" value="change_password">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label"><?=$isRtl?'كلمة المرور الحالية':'Current Password'?></label>
          <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label"><?=$isRtl?'كلمة المرور الجديدة':'New Password'?></label>
          <input type="password" name="new_password" class="form-control" minlength="8" required>
        </div>
        <div class="form-group">
          <label class="form-label"><?=$isRtl?'تأكيد كلمة المرور':'Confirm Password'?></label>
          <input type="password" name="confirm_password" class="form-control" required>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;margin-top:8px">
        <button type="submit" class="btn btn-secondary">🔐 <?=$isRtl?'تغيير كلمة المرور':'Change Password'?></button>
      </div>
    </form>
  </div>
</div>

</div><!-- end grid -->
</main>
</div>
<?php include 'includes/footer.php'; ?>
