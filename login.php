<?php
require_once __DIR__ . '/config.php';
$lang = $_SESSION['lang'] ?? 'en';
$isRtl = $lang === 'ar';

if (isLoggedIn()) { header('Location: /dashboard.php'); exit; }

$error = '';

// Rate limiting — max 5 attempts per 15 minutes
$ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
$attKey = "login_attempts_" . md5($ip);
$lockKey = "login_locked_" . md5($ip);
if (!isset($_SESSION[$attKey])) $_SESSION[$attKey] = 0;
if (!isset($_SESSION[$lockKey])) $_SESSION[$lockKey] = 0;
$isLocked = ($_SESSION[$lockKey] > time());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($isLocked) {
        $error = 'Too many login attempts. Please wait 15 minutes.';
    } elseif (!$username || !$password) {
        $error = $isRtl ? 'يرجى إدخال اسم المستخدم وكلمة المرور' : 'Please enter username and password';
    } else {
        $pdo = getPDO();
        $st  = $pdo->prepare("SELECT * FROM users WHERE (username=? OR email=?) AND is_active=1 LIMIT 1");
        $st->execute([$username, $username]);
        $user = $st->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true); // Prevent session fixation
            $_SESSION[$attKey] = 0; $_SESSION[$lockKey] = 0; // Reset rate limit
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['user']     = $user;
            // update last login
            $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
            setFlash('success', $isRtl ? 'مرحباً بعودتك، ' . $user['full_name'] : 'Welcome back, ' . $user['full_name'] . '!');
            header('Location: /dashboard.php');
            exit;
        } else {
            $_SESSION[$attKey]++;
            $error = $isRtl ? 'اسم المستخدم أو كلمة المرور غير صحيحة' : 'Invalid username or password';
            if ($_SESSION[$attKey] >= 5) { $_SESSION[$lockKey] = time() + 900; }
        }
    }
}

$pageTitle = ($isRtl ? 'تسجيل الدخول — ' : 'Login — ') . APP_NAME;
include 'includes/header.php';
?>

<div style="min-height:calc(100vh - 64px);display:flex;align-items:center;justify-content:center;padding:2rem;background:var(--gray-50)">
  <div style="width:100%;max-width:440px">

    <!-- Card -->
    <div class="card shadow">
      <!-- Header -->
      <div style="background:linear-gradient(135deg,var(--green-dark),var(--green-main));padding:2.5rem;text-align:center">
        <div style="font-size:3rem;margin-bottom:.75rem">🔑</div>
        <h1 style="color:#fff;font-size:1.4rem;font-weight:700;margin-bottom:.3rem">
          <?= $isRtl ? 'تسجيل الدخول' : 'Secure Login' ?>
        </h1>
        <p style="color:rgba(255,255,255,.75);font-size:.88rem">
          <?= $isRtl ? 'ادخل بيانات حسابك للمتابعة' : 'Enter your credentials to continue' ?>
        </p>
      </div>

      <div class="card-body" style="padding:2rem">
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
        <?= csrfField() ?>
          <div class="form-group">
            <label class="form-label">
              <i class="fas fa-user" style="color:var(--green-main)"></i>
              <?= $isRtl ? 'اسم المستخدم أو البريد الإلكتروني' : 'Username or Email' ?>
            </label>
            <input type="text" name="username" class="form-control"
              placeholder="<?= $isRtl ? 'أدخل اسم المستخدم' : 'Enter username or email' ?>"
              value="<?= h($_POST['username'] ?? '') ?>" required autofocus>
          </div>

          <div class="form-group">
            <label class="form-label">
              <i class="fas fa-lock" style="color:var(--green-main)"></i>
              <?= $isRtl ? 'كلمة المرور' : 'Password' ?>
            </label>
            <div style="position:relative">
              <input type="password" name="password" id="pwd" class="form-control"
                placeholder="<?= $isRtl ? 'أدخل كلمة المرور' : 'Enter password' ?>" required>
              <button type="button" onclick="togglePwd()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-500)">
                <i class="fas fa-eye" id="eyeIcon"></i>
              </button>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-full btn-lg" style="margin-top:.5rem">
            <?= $isRtl ? 'دخول' : 'Login' ?> →
          </button>
        </form>

        <div style="text-align:center;margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--gray-100)">
          <p style="color:var(--gray-500);font-size:.9rem">
            <?= $isRtl ? 'ليس لديك حساب؟' : "Don't have an account?" ?>
            <a href="/register.php" style="color:var(--green-main);font-weight:700">
              <?= $isRtl ? 'سجّل الآن' : 'Register Now' ?>
            </a>
          </p>
        </div>

        
      </div>
    </div>
  </div>
</div>

<script>
function togglePwd(){
  const p=document.getElementById('pwd'), i=document.getElementById('eyeIcon');
  p.type = p.type==='password'?'text':'password';
  i.className = p.type==='password'?'fas fa-eye':'fas fa-eye-slash';
}
</script>
<?php include 'includes/footer.php'; ?>
