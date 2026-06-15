<?php
require_once __DIR__ . '/config.php';
$lang  = $_SESSION['lang'] ?? 'en';
$isRtl = $lang === 'ar';
if (isLoggedIn()) { header('Location: /dashboard.php'); exit; }
$pdo = getPDO();

$regRole = $_GET['role'] ?? ($_POST['role'] ?? '');
$roleSet = isset($_GET['role_set']) || isset($_POST['role']);
$error   = '';

// Load governorates
$govs = $pdo->query(
    "SELECT DISTINCT governorate FROM mosques WHERE is_active=1 AND governorate IS NOT NULL ORDER BY governorate"
)->fetchAll(PDO::FETCH_COLUMN);

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $fn     = trim($_POST['full_name']    ?? '');
    $fnar   = trim($_POST['full_name_ar'] ?? '');
    $un     = trim($_POST['username']     ?? '');
    $em     = trim($_POST['email']        ?? '');
    $pw     = $_POST['password']   ?? '';
    $pw2    = $_POST['password2']  ?? '';
    $ph     = trim($_POST['phone'] ?? '');
    $gov    = trim($_POST['governorate']  ?? '');
    $mid    = (int)($_POST['mosque_id']   ?? 0);
    $dob    = $_POST['dob']    ?? '';
    $gnd    = $_POST['gender'] ?? 'male';
    $role   = $_POST['role']   ?? '';
    $progId = (int)($_POST['program_id']  ?? 0);

    $errs = [];
    if (!$fn)                                        $errs[] = $isRtl?'الاسم الكامل مطلوب':'Full name required';
    if (strlen($un) < 4)                             $errs[] = $isRtl?'اسم المستخدم 4 أحرف على الأقل':'Username min 4 chars';
    if (!filter_var($em, FILTER_VALIDATE_EMAIL))     $errs[] = $isRtl?'البريد غير صحيح':'Invalid email';
    if (strlen($pw) < 8)                             $errs[] = $isRtl?'كلمة المرور 8 أحرف على الأقل':'Password min 8 chars';
    if ($pw !== $pw2)                                $errs[] = $isRtl?'كلمتا المرور غير متطابقتين':'Passwords do not match';
    if (!$gov)                                       $errs[] = $isRtl?'اختر المحافظة':'Select governorate';
    if (!$mid)                                       $errs[] = $isRtl?'اختر المسجد':'Select mosque';
    if ($role === 'student' && !$dob)                $errs[] = $isRtl?'تاريخ الميلاد مطلوب':'Date of birth required';
    if ($role === 'teacher' && !$progId)             $errs[] = $isRtl?'اختر برنامجاً للتدريس':'Select a program to teach';
    if (!in_array($role, ['student','parent','teacher'])) $errs[] = 'Invalid role';

    // ── Age Validation — Slot A (Student) must be 12–99 years old ──────
    if ($role === 'student' && $dob) {
        $dobDate = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$dobDate || $dobDate === false) {
            $errs[] = $isRtl ? '⚠️ تاريخ الميلاد غير صحيح' : '⚠️ Invalid date of birth format.';
        } else {
            $age = $dobDate->diff(new DateTime())->y;
            // Minimum age check
            if ($age < 12) {
                $errs[] = $isRtl
                    ? "⚠️ الطلاب في Slot A (أيام الأحد والثلاثاء والخميس) يجب أن يكونوا 12 سنة أو أكثر. عمرك الحالي: {$age} سنة. للأطفال بين 5-12 سنة: يرجى التسجيل عبر حساب ولي أمر."
                    : "⚠️ Slot A students (Sun/Tue/Thu) must be 12 years or older. Current age: {$age}. Children aged 5–12 must be registered by a Parent account in Slot B (Mon/Wed/Fri).";
            }
            // Maximum age sanity check — no year 2 AD dates
            if ($age > 100) {
                $errs[] = $isRtl
                    ? '⚠️ تاريخ الميلاد غير منطقي. يرجى إدخال تاريخ صحيح.'
                    : '⚠️ Date of birth is not valid. Please enter a realistic date.';
            }
            // Future date check
            if ($dobDate > new DateTime()) {
                $errs[] = $isRtl
                    ? '⚠️ تاريخ الميلاد لا يمكن أن يكون في المستقبل.'
                    : '⚠️ Date of birth cannot be in the future.';
            }
        }
    }

    if (!$errs) {
        $chk = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=?");
        $chk->execute([$un, $em]);
        if ($chk->fetch()) $errs[] = $isRtl?'اسم المستخدم أو البريد مستخدم':'Username or email taken';
    }

    $idPath = null;
    if (!$errs && isset($_FILES['id_card']) && $_FILES['id_card']['error'] === 0) {
        $allowed = ['image/jpeg','image/png','image/jpg','application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $_FILES['id_card']['tmp_name']);
        finfo_close($finfo);
        if (!in_array($realMime, $allowed))       $errs[] = 'Invalid file type';
        elseif ($_FILES['id_card']['size'] > MAX_FILE_SIZE)        $errs[] = 'File too large (max 5MB)';
        else {
            $ext = pathinfo($_FILES['id_card']['name'], PATHINFO_EXTENSION);
            $fname = 'id_' . uniqid() . '.' . $ext;
            @mkdir(UPLOAD_DIR . 'ids/', 0755, true);
            move_uploaded_file($_FILES['id_card']['tmp_name'], UPLOAD_DIR . 'ids/' . $fname);
            $idPath = 'ids/' . $fname;
        }
    }

    if (!$errs) {
        $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);

        $pdo->prepare(
            "INSERT INTO users (full_name,full_name_ar,username,email,password_hash,role,mosque_id,governorate,phone,id_card_path,is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,1)"
        )->execute([$fn,$fnar,$un,$em,$hash,$role,$mid,$gov,$ph,$idPath]);
        $uid = (int)$pdo->lastInsertId();

        if ($role === 'student') {
            // Calculate age to confirm Slot A assignment
            $dobDate = DateTime::createFromFormat('Y-m-d', $dob);
            $age = $dobDate->diff(new DateTime())->y;

            $pdo->prepare(
                "INSERT INTO students (user_id,full_name,full_name_ar,date_of_birth,gender,parent_id,mosque_id,email,student_type,slot,is_active)
                 VALUES (?,?,?,?,?,0,?,?,'student','A',1)"
            )->execute([$uid,$fn,$fnar,$dob,$gnd,$mid,$em]);
            $sid = (int)$pdo->lastInsertId();

            // Auto-enroll ONLY in Slot A (Sun/Tue/Thu 4-5PM, target_type='student')
            $progs = $pdo->prepare("SELECT id, teacher_id, name_en FROM mosque_programs WHERE mosque_id=? AND is_active=1 AND target_type='student' AND slot='A'");
            $progs->execute([$mid]);
            foreach ($progs->fetchAll() as $prog) {
                $pdo->prepare("INSERT IGNORE INTO program_enrollments (program_id,student_id) VALUES (?,?)")
                    ->execute([$prog['id'], $sid]);
                if ($prog['teacher_id']) {
                    $pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'info')")
                        ->execute([$prog['teacher_id'], "New Student: $fn", "$fn joined: {$prog['name_en']}"]);
                }
            }
            // Also enroll in Slot A classes only for attendance tracking
            $cls = $pdo->prepare("SELECT id FROM classes WHERE mosque_id=? AND slot='A' AND is_active=1");
            $cls->execute([$mid]);
            foreach ($cls->fetchAll() as $cl) {
                $pdo->prepare("INSERT IGNORE INTO enrollments (student_id,class_id,status) VALUES (?,?,'active')")
                    ->execute([$sid, $cl['id']]);
            }

        } elseif ($role === 'teacher') {
            if ($progId) {
                // Get program info
                $progInfo = $pdo->prepare("SELECT * FROM mosque_programs WHERE id=? AND teacher_id IS NULL AND is_active=1");
                $progInfo->execute([$progId]); $progInfo = $progInfo->fetch();

                if ($progInfo) {
                    // DIRECT assignment — no pending approval needed
                    $pdo->prepare("UPDATE mosque_programs SET teacher_id=? WHERE id=?")->execute([$uid, $progId]);
                    // Also update classes table
                    $pdo->prepare("UPDATE classes SET teacher_id=? WHERE mosque_id=? AND teacher_id IS NULL LIMIT 1")
                        ->execute([$uid, $progInfo['mosque_id']]);
                    // Notify admins
                    $admins = $pdo->query("SELECT id FROM users WHERE role='admin' AND is_active=1 LIMIT 3")->fetchAll();
                    foreach ($admins as $a) {
                        $pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'info')")
                            ->execute([$a['id'],"New Teacher: $fn","$fn assigned to Slot {$progInfo['slot']} in $gov"]);
                    }
                    // Notify mosque admin if exists
                    $mosqueAdmin = $pdo->prepare("SELECT admin_id FROM mosques WHERE id=?");
                    $mosqueAdmin->execute([$progInfo['mosque_id']]); $mosqueAdmin = $mosqueAdmin->fetch();
                    if ($mosqueAdmin && $mosqueAdmin['admin_id']) {
                        $pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'info')")
                            ->execute([$mosqueAdmin['admin_id'],"New Teacher: $fn","$fn joined Slot {$progInfo['slot']}"]);
                    }
                }
            }
        }

        // Welcome email
        $html = "<html><body style='font-family:Arial;padding:20px'>
<div style='max-width:520px;margin:0 auto;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)'>
<div style='background:linear-gradient(135deg,#1B4332,#40916C);padding:24px;text-align:center;color:#fff'>
<h2 style='margin:0;color:#F4D03F'>&#128332; Welcome to Digital Quran Hub!</h2></div>
<div style='padding:24px'>
<p>Hello <b>" . htmlspecialchars($fn) . "</b>,</p>
<p>Account created. Username: <b>" . htmlspecialchars($un) . "</b></p>
<p>Role: <b>" . ucfirst($role) . "</b> &nbsp;|&nbsp; Governorate: <b>" . htmlspecialchars($gov) . "</b></p>
<a href='" . BASE_URL . "/login.php' style='display:inline-block;background:#2D6A4F;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;margin-top:12px'>Login Now</a>
</div></div></body></html>";
        $pdo->prepare("INSERT INTO email_queue (to_email,to_name,subject,body_html) VALUES (?,?,?,?)")
            ->execute([$em,$fn,'Welcome to Digital Quran Hub',$html]);

        $pdo->prepare("INSERT INTO audit_log (user_id,action,table_name,record_id) VALUES (?,?,?,?)")
            ->execute([$uid,'register','users',$uid]);

        setFlash('success', $isRtl
            ? ($role === 'student'
                ? "🎉 تم إنشاء حسابك! أهلاً {$fn} — تم تسجيلك في Slot A (الأحد · الثلاثاء · الخميس 4-5 م) للطلاب 12+ سنة."
                : "🎉 تم إنشاء حسابك! أهلاً $fn")
            : ($role === 'student'
                ? "🎉 Account created! Welcome {$fn} — Enrolled in Slot A (Sun · Tue · Thu 4–5 PM) for students 12+ years."
                : "🎉 Account created! Welcome, $fn."));
        header('Location: /login.php'); exit;
    }
    $error = implode('<br>', $errs);
}

$pageTitle = ($isRtl ? 'تسجيل — ' : 'Register — ') . APP_NAME;
include 'includes/header.php';
?>
<div style="min-height:calc(100vh - 64px);background:var(--gray-50);padding:2rem">
<div style="max-width:700px;margin:0 auto">

<?php if (!$roleSet): ?>
<!-- Step 1: Choose role -->
<div style="text-align:center;margin-bottom:28px">
  <h2 style="font-size:1.6rem;font-weight:800;color:var(--green-dark);margin-bottom:8px">
    🕌 <?= $isRtl?'إنشاء حساب جديد':'Create New Account' ?>
  </h2>
  <p style="color:var(--gray-500);margin-bottom:28px"><?= $isRtl?'اختر نوع حسابك:':'Choose your account type:' ?></p>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;max-width:540px;margin:0 auto">
    <?php foreach ([
      ['student','🎓',$isRtl?'طالب':'Student',     $isRtl?'سجّل في برامج الحفظ':'Enroll in programs','var(--green-main)'],
      ['teacher','👨‍🏫',$isRtl?'معلم':'Teacher',   $isRtl?'قدّم لتدريس برنامج':'Apply to teach','var(--info)'],
      ['parent', '👨‍👩‍👧',$isRtl?'ولي أمر':'Parent',$isRtl?'تابع أطفالك':'Track children','var(--gold-dark)'],
    ] as [$r,$icon,$label,$desc,$color]): ?>
    <a href="?role=<?=$r?>&role_set=1"
       style="display:block;background:#fff;border:2px solid <?=$color?>;border-radius:16px;padding:28px 16px;text-align:center;text-decoration:none;transition:all .2s"
       onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='var(--shadow-md)'"
       onmouseout="this.style.transform='';this.style.boxShadow=''">
      <div style="font-size:3rem;margin-bottom:10px"><?=$icon?></div>
      <div style="font-weight:800;color:var(--green-dark);font-size:16px;margin-bottom:4px"><?=$label?></div>
      <div style="font-size:12px;color:var(--gray-500);line-height:1.4"><?=$desc?></div>
    </a>
    <?php endforeach; ?>
  </div>
  <p style="margin-top:24px;color:var(--gray-500);font-size:.9rem">
    <?=$isRtl?'لديك حساب؟':'Have an account?'?>
    <a href="/login.php" style="color:var(--green-main);font-weight:700"><?=$isRtl?'دخول':'Login'?></a>
  </p>
</div>

<?php else:
$isStudent = $regRole === 'student';
$isTeacher = $regRole === 'teacher';
$icons  = ['student'=>'🎓','teacher'=>'👨‍🏫','parent'=>'👨‍👩‍👧'];
$titles = [
    'student'=>[$isRtl?'تسجيل طالب':'Student Registration',    $isRtl?'سجّل واختر برنامج القرآن':'Register & choose your program'],
    'teacher'=>[$isRtl?'تسجيل معلم':'Teacher Registration',    $isRtl?'سجّل وتقدّم لبرنامج':'Register & apply to a program'],
    'parent' =>[$isRtl?'تسجيل ولي أمر':'Parent Registration',  $isRtl?'أنشئ حسابك لمتابعة أطفالك':'Track your children'],
];
?>
<div class="card shadow">
  <div style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));padding:1.8rem 2rem;text-align:center">
    <div style="font-size:2.5rem"><?=$icons[$regRole]??'👤'?></div>
    <h1 style="color:#fff;font-size:1.3rem;font-weight:700;margin:.5rem 0 .2rem"><?=$titles[$regRole][0]?></h1>
    <p style="color:rgba(255,255,255,.75);font-size:.85rem;margin:0"><?=$titles[$regRole][1]?></p>
  </div>

  <div class="card-body" style="padding:1.8rem 2rem">
    <?php if ($error): ?><div class="alert alert-danger"><?=$error?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
        <?= csrfField() ?>
    <input type="hidden" name="role" value="<?=h($regRole)?>">

    <!-- Basic Info -->
    <div style="font-size:12px;font-weight:700;color:var(--green-dark);text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px">
      👤 <?=$isRtl?'المعلومات الأساسية':'Basic Information'?>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label"><?=$isRtl?'الاسم بالإنجليزية':'Full Name (EN)'?> *</label>
        <input type="text" name="full_name" class="form-control" placeholder="Ahmed Al-Battashi" value="<?=h($_POST['full_name']??'')?>" required>
      </div>
      <div class="form-group">
        <label class="form-label"><?=$isRtl?'الاسم بالعربية':'Full Name (AR)'?></label>
        <input type="text" name="full_name_ar" class="form-control" placeholder="أحمد البطاشي" dir="rtl" value="<?=h($_POST['full_name_ar']??'')?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label"><?=$isRtl?'اسم المستخدم':'Username'?> *</label>
        <input type="text" name="username" class="form-control" placeholder="ahmed123" value="<?=h($_POST['username']??'')?>" required>
      </div>
      <div class="form-group">
        <label class="form-label"><?=$isRtl?'رقم الهاتف':'Phone'?></label>
        <input type="tel" name="phone" class="form-control" placeholder="+968 9XXX XXXX" value="<?=h($_POST['phone']??'')?>">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">📧 <?=$isRtl?'البريد الإلكتروني':'Email'?> *</label>
      <input type="email" name="email" class="form-control" placeholder="example@gmail.com" value="<?=h($_POST['email']??'')?>" required>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">🔒 <?=$isRtl?'كلمة المرور':'Password'?> *</label>
        <input type="password" name="password" class="form-control" placeholder="<?=$isRtl?'8 أحرف على الأقل':'Min 8 chars'?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">🔒 <?=$isRtl?'تأكيد كلمة المرور':'Confirm Password'?> *</label>
        <input type="password" name="password2" class="form-control" placeholder="<?=$isRtl?'أعد الكتابة':'Repeat'?>" required>
      </div>
    </div>

    <?php if ($isStudent): ?>
    <!-- Slot A Info Banner -->
    <div style="background:linear-gradient(135deg,#1E3A8A,#4F46E5);border-radius:12px;padding:14px 18px;margin-bottom:16px;color:#fff">
      <div style="font-weight:700;font-size:13px;margin-bottom:6px">🎓 <?=$isRtl?'سيتم تسجيلك في:':'You will be enrolled in:'?> <span style="background:rgba(255,255,255,.2);padding:2px 10px;border-radius:99px">Slot A</span></div>
      <div style="font-size:12px;opacity:.85">📅 <?=$isRtl?'الأحد · الثلاثاء · الخميس':'Sunday · Tuesday · Thursday'?> &nbsp;·&nbsp; ⏰ 4:00 – 5:00 PM</div>
      <div style="font-size:11px;opacity:.7;margin-top:4px">⚠️ <?=$isRtl?'هذا البرنامج للطلاب الذين أعمارهم 12 سنة فأكثر. الأطفال بين 5-12 يُسجَّلون عبر حساب ولي الأمر في Slot B.':'This program is for students aged 12 and above. Children aged 5–12 must be registered by a Parent account in Slot B.'?></div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">📅 <?=$isRtl?'تاريخ الميلاد':'Date of Birth'?> * <small style="color:#6B7280">(<?=$isRtl?'يجب 12 سنة أو أكثر':'Must be 12+ years old'?>)</small></label>
        <input type="date" name="dob" class="form-control" value="<?=h($_POST['dob']??'')?>"
               min="<?=date('Y-m-d',strtotime('-100 years'))?>"
               max="<?=date('Y-m-d',strtotime('-12 years'))?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">👤 <?=$isRtl?'الجنس':'Gender'?> *</label>
        <select name="gender" class="form-control form-select">
          <option value="male" <?=($_POST['gender']??'male')==='male'?'selected':''?>><?=$isRtl?'ذكر':'Male'?></option>
          <option value="female" <?=($_POST['gender']??'')==='female'?'selected':''?>><?=$isRtl?'أنثى':'Female'?></option>
        </select>
      </div>
    </div>
    <?php endif; ?>

    <!-- Governorate & Mosque -->
    <div style="font-size:12px;font-weight:700;color:var(--green-dark);text-transform:uppercase;letter-spacing:.05em;margin:20px 0 12px">
      🗺️ <?=$isRtl?'المحافظة والمسجد':'Governorate & Mosque'?>
    </div>

    <div class="form-group">
      <label class="form-label">📍 <?=$isRtl?'اختر محافظتك':'Select Your Governorate'?> *</label>
      <select name="governorate" id="govSelect" class="form-control form-select" required onchange="loadMosques(this.value)">
        <option value=""><?=$isRtl?'-- اختر المحافظة --':'-- Select Governorate --'?></option>
        <?php foreach ($govs as $g): ?>
        <option value="<?=h($g)?>" <?=($_POST['governorate']??'')===$g?'selected':''?>><?=h($g)?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group" id="mosqueDiv" style="display:none">
      <label class="form-label">🕌 <?=$isRtl?'اختر المسجد':'Select Mosque'?> *</label>
      <select name="mosque_id" id="mosqueSelect" class="form-control form-select" required onchange="isTeacher && loadPrograms(this.value)">
        <option value=""><?=$isRtl?'-- اختر المسجد --':'-- Select Mosque --'?></option>
      </select>
    </div>

    <?php if ($isTeacher): ?>
    <div id="programsDiv" style="display:none">
      <div style="font-size:12px;font-weight:700;color:var(--green-dark);text-transform:uppercase;letter-spacing:.05em;margin:20px 0 6px">
        📚 <?= $isRtl?'اختر اليام التي تريد التدريس فيها':'Select Your Teaching Slot' ?>
      </div>
      <p style="font-size:12px;color:var(--gray-500);margin-bottom:10px">
        <?= $isRtl
          ?'🎓 Slot A (أحد/ثلاثاء/خميس) — للطلاب العاديين &nbsp;|&nbsp; 👶 Slot B (اثنين/أربعاء/جمعة) — للأطفال. ستُعيَّن مباشرة بدون انتظار موافقة.'
          :'🎓 Slot A (Sun/Tue/Thu) — for Students &nbsp;|&nbsp; 👶 Slot B (Mon/Wed/Fri) — for Children. You will be assigned directly, no approval needed.' ?>
      </p>
      <div id="programsList"></div>
    </div>
    <?php endif; ?>

    <div style="margin-top:16px">
      <label class="form-label">🪪 <?=$isRtl?'رفع بطاقة الهوية (اختياري)':'Upload ID Card (optional)'?></label>
      <input type="file" name="id_card" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
      <p class="form-hint">JPG / PNG / PDF — max 5MB</p>
    </div>

    <button type="submit" class="btn btn-primary w-full btn-lg" style="margin-top:1.5rem">
      ✅ <?=$isRtl?'إنشاء الحساب':'Create Account'?>
    </button>
    <p style="text-align:center;color:var(--gray-500);font-size:.88rem;margin-top:1.2rem">
      <?=$isRtl?'لديك حساب؟':'Have an account?'?>
      <a href="/login.php" style="color:var(--green-main);font-weight:700"><?=$isRtl?'دخول':'Login'?></a>
      &nbsp;|&nbsp;
      <a href="/register.php" style="color:var(--gray-400)"><?=$isRtl?'تغيير النوع':'Change type'?></a>
    </p>
    </form>
  </div>
</div>

<script>
const isRtl     = <?=$isRtl?'true':'false'?>;
const isStudent = <?=$isStudent?'true':'false'?>;
const isTeacher = <?=$isTeacher?'true':'false'?>;
const savedGov  = <?=json_encode($_POST['governorate']??'', JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
const savedMid  = <?=(int)($_POST['mosque_id']??0)?>;
const savedProg = <?=(int)($_POST['program_id']??0)?>;

async function loadMosques(gov) {
    const mosqueDiv    = document.getElementById('mosqueDiv');
    const mosqueSelect = document.getElementById('mosqueSelect');
    const progsDiv     = document.getElementById('programsDiv');
    if (!gov) { mosqueDiv.style.display='none'; if(progsDiv)progsDiv.style.display='none'; return; }

    const res  = await fetch('/api_helper.php?action=mosques&gov=' + encodeURIComponent(gov));
    const data = await res.json();
    mosqueSelect.innerHTML = `<option value="">${isRtl?'-- اختر المسجد --':'-- Select Mosque --'}</option>`;
    data.forEach(m => {
        const o = document.createElement('option');
        o.value = m.id;
        o.textContent = (isRtl ? m.name_ar : m.name_en) + ' — ' + m.wilayat;
        mosqueSelect.appendChild(o);
    });
    mosqueDiv.style.display = 'block';
    if (progsDiv) progsDiv.style.display = 'none';
    // Only teachers load programs
    if (savedMid) { mosqueSelect.value = savedMid; if(isTeacher) loadPrograms(savedMid); }
}

async function loadPrograms(mosqueId) {
    // Students don't need to pick a program slot
    if (!isTeacher) return;
    const progsDiv  = document.getElementById('programsDiv');
    const progsList = document.getElementById('programsList');
    if (!progsDiv || !progsList || !mosqueId) return;

    const res  = await fetch('/api_helper.php?action=programs&mosque_id=' + mosqueId + '&type=teacher');
    const data = await res.json();

    if (!data.length) {
        progsList.innerHTML = `<div style="padding:16px;text-align:center;color:var(--gray-300);border:1px dashed var(--gray-100);border-radius:10px">
            <div style="font-size:2rem">📚</div>
            <div style="margin-top:6px;font-size:13px">${isRtl?'لا توجد slots شاغرة في هذا المسجد':'No available slots in this mosque'}</div></div>`;
    } else {
        progsList.innerHTML = data.map(p => {
            const days = p.days.split(',').join(' · ');
            const chk  = savedProg == p.id ? 'checked' : '';
            const isChild = p.target_type === 'child';
            const slotColor = isChild ? '#FEF3C7' : '#EDE9FE';
            const slotTextColor = isChild ? '#92400E' : '#4C1D95';
            const audienceBg = isChild ? '#FEF3C7' : '#DBEAFE';
            const audienceColor = isChild ? '#92400E' : '#1D4ED8';
            const audienceLabel = isChild ? '👶 ' + (isRtl?'للأطفال (5–12)':'For Children (5–12)') : '🎓 ' + (isRtl?'للطلاب (12+)':'For Students (12+)');
            const slotIcon = isChild ? '👶' : '🎓';
            return `
            <label style="display:flex;align-items:flex-start;gap:14px;padding:16px;border:2px solid ${slotColor};border-radius:14px;margin-bottom:8px;cursor:pointer;background:#fff;transition:all .15s"
                   onmouseover="this.style.borderColor='${slotTextColor}';this.style.background='${slotColor}22'"
                   onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='${slotColor}';this.style.background='#fff'}">
              <input type="radio" name="program_id" value="${p.id}" style="margin-top:6px;accent-color:${slotTextColor};width:18px;height:18px;flex-shrink:0" ${chk} onchange="styleProgram(this)">
              <div style="flex:1">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                  <span style="font-size:1.4rem">${slotIcon}</span>
                  <div>
                    <div style="font-weight:800;font-size:14px;color:${slotTextColor}">Slot ${p.slot}</div>
                    <div style="font-size:11px;color:#6b7280">${days}</div>
                  </div>
                </div>
                <div style="font-size:12px;color:#6b7280;margin-bottom:8px">⏰ ${p.time_start.substring(0,5)}–${p.time_end.substring(0,5)}</div>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <span style="background:${audienceBg};color:${audienceColor};padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700">${audienceLabel}</span>
                  <span style="background:#D1FAE5;color:#065F46;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700">✅ ${isRtl?'متاح':'Available'}</span>
                  <span style="background:#F3F4F6;color:#6b7280;padding:3px 10px;border-radius:99px;font-size:11px">👥 ${p.enrolled}/20</span>
                </div>
              </div>
            </label>`;
        }).join('');
    }
    progsDiv.style.display = 'block';
    if (savedProg) {
        document.querySelectorAll('input[name="program_id"]').forEach(r => {
            if(r.value==savedProg){r.checked=true;styleProgram(r);}
        });
    }
}

function styleProgram(radio) {
    document.querySelectorAll('input[name="program_id"]').forEach(r => {
        const l = r.closest('label');
        l.style.borderColor = r.checked ? 'var(--green-main)' : 'var(--gray-100)';
        l.style.background  = r.checked ? 'var(--green-pale)' : '#fff';
    });
}

window.addEventListener('load', () => {
    if (savedGov) { document.getElementById('govSelect').value = savedGov; loadMosques(savedGov); }
});

window.addEventListener('load', () => {
    if (savedGov) { document.getElementById('govSelect').value = savedGov; loadMosques(savedGov); }
});
</script>
<?php endif; ?>
</div>
</div>
<?php include 'includes/footer.php'; ?>
