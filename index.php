<?php
require_once __DIR__ . '/config.php';
requireLogin();
$lang = $_SESSION['lang'] ?? 'en';
$isRtl = $lang === 'ar';
$pageTitle = APP_NAME . ' — Home';

// Load mosques for display
$pdo = getPDO();
$mosques = $pdo->query("SELECT * FROM mosques WHERE is_active=1 LIMIT 5")->fetchAll();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalClasses  = $pdo->query("SELECT COUNT(*) FROM classes WHERE is_active=1")->fetchColumn();
$totalMosques  = $pdo->query("SELECT COUNT(*) FROM mosques WHERE is_active=1")->fetchColumn();

include 'includes/header.php';
?>

<!-- HERO -->
<section class="hero pattern-bg">
  <div class="hero-content">
    <div class="hero-badge">
      ✨ <?= $isRtl ? 'النظام الرقمي لمراكز القرآن الكريم' : 'Digital Platform for Quranic Education' ?>
    </div>
    <h1><?= $isRtl ? 'مركز القرآن <span>الرقمي</span>' : 'Digital Quran <span>Center Hub</span>' ?></h1>
    <p class="hero-ar">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</p>
    <p>
      <?= $isRtl
        ? 'نظام متكامل لإدارة مراكز تحفيظ القرآن الكريم في المساجد والمراكز التعليمية في سلطنة عُمان. يربط الآباء والمعلمين والإداريين في منصة واحدة.'
        : 'A comprehensive management system for Quranic education centers in Oman\'s mosques. Connecting parents, teachers, and administrators in one secure digital platform.'
      ?>
    </p>
    <div class="hero-buttons">
      <?php if (isLoggedIn()): ?>
        <a href="/dashboard.php" class="btn-hero-primary">
          📊 <?= $isRtl ? 'لوحة التحكم' : 'Go to Dashboard' ?>
        </a>
      <?php else: ?>
        <a href="/register.php" class="btn-hero-primary">
          🚀 <?= $isRtl ? 'ابدأ الآن' : 'Get Started' ?>
        </a>
        <a href="/login.php" class="btn-hero-outline">
          🔑 <?= $isRtl ? 'تسجيل الدخول' : 'Login' ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- STATS BAR -->
<section style="background:var(--white);padding:2rem;border-bottom:1px solid var(--gray-100)">
  <div style="max-width:900px;margin:0 auto;display:grid;grid-template-columns:repeat(3,1fr);gap:2rem;text-align:center">
    <div>
      <div style="font-size:2.2rem;font-weight:800;color:var(--green-main)"><?= $totalStudents ?>+</div>
      <div style="color:var(--gray-500);font-size:.9rem"><?= $isRtl ? 'طالب مسجل' : 'Enrolled Students' ?></div>
    </div>
    <div>
      <div style="font-size:2.2rem;font-weight:800;color:var(--gold-dark)"><?= $totalClasses ?>+</div>
      <div style="color:var(--gray-500);font-size:.9rem"><?= $isRtl ? 'فصل دراسي' : 'Active Classes' ?></div>
    </div>
    <div>
      <div style="font-size:2.2rem;font-weight:800;color:var(--green-main)"><?= $totalMosques ?></div>
      <div style="color:var(--gray-500);font-size:.9rem"><?= $isRtl ? 'مسجد ومركز' : 'Mosques & Centers' ?></div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section style="padding:4rem 2rem;background:var(--gray-50)">
  <div style="max-width:1100px;margin:0 auto">
    <h2 style="text-align:center;color:var(--green-dark);font-size:1.8rem;margin-bottom:.5rem">
      <?= $isRtl ? 'ميزات النظام' : 'System Features' ?>
    </h2>
    <p style="text-align:center;color:var(--gray-500);margin-bottom:3rem">
      <?= $isRtl ? 'كل ما تحتاجه لإدارة مركز القرآن الكريم' : 'Everything you need to manage a Quran center' ?>
    </p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem">
      <?php
      $features = [
        ['🎓','Digital Enrollment','التسجيل الرقمي','Online student registration with ID verification','تسجيل الطلاب إلكترونياً مع التحقق من الهوية'],
        ['✅','Attendance Tracking','تتبع الحضور','Real-time attendance logging for every class','تسجيل الحضور اليومي لكل الفصول بشكل فوري'],
        ['📈','Progress Monitoring','مراقبة التقدم','Track Surah memorization and Tajweed levels','متابعة حفظ السور ومستوى التجويد'],
        ['👨‍👩‍👧','Parent Dashboard','لوحة الوالدين','Parents monitor children\'s journey in real-time','الآباء يتابعون تقدم أطفالهم لحظة بلحظة'],
        ['📄','Report Generation','إنشاء التقارير','Export PDF and CSV reports for any period','تصدير تقارير PDF و CSV لأي فترة زمنية'],
        ['🌐','Bilingual Interface','واجهة ثنائية اللغة','Full Arabic RTL and English LTR support','دعم كامل للعربية والإنجليزية'],
      ];
      foreach ($features as $f): ?>
      <div class="card" style="transition:var(--transition)" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
        <div class="card-body" style="text-align:center;padding:2rem 1.5rem">
          <div style="font-size:2.5rem;margin-bottom:1rem"><?= $f[0] ?></div>
          <h3 style="font-size:1rem;font-weight:700;color:var(--green-dark);margin-bottom:.5rem">
            <?= $isRtl ? $f[2] : $f[1] ?>
          </h3>
          <p style="color:var(--gray-500);font-size:.88rem;line-height:1.7">
            <?= $isRtl ? $f[4] : $f[3] ?>
          </p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- MOSQUES SECTION -->
<section style="padding:4rem 2rem;background:var(--white)">
  <div style="max-width:1100px;margin:0 auto">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem">
      <div>
        <h2 style="color:var(--green-dark);font-size:1.6rem;margin-bottom:.3rem">
          🕌 <?= $isRtl ? 'اكتشف المساجد والمراكز' : 'Explore Mosques & Centers' ?>
        </h2>
        <p style="color:var(--gray-500)"><?= $isRtl ? 'مراكز تحفيظ القرآن في سلطنة عُمان' : 'Quran learning centers across Oman' ?></p>
      </div>
      <a href="/mosques.php" class="btn btn-outline"><?= $isRtl ? 'عرض الكل' : 'View All' ?> →</a>
    </div>

    <div class="mosques-grid" style="padding:0">
      <?php foreach ($mosques as $m): ?>
      <div class="mosque-card">
        <div class="mosque-card-img">🕌</div>
        <div class="mosque-card-body">
          <div class="mosque-name"><?= h($m['name_en']) ?></div>
          <div class="mosque-name-ar"><?= h($m['name_ar']) ?></div>
          <div class="mosque-meta">
            <i class="fas fa-map-marker-alt" style="color:var(--green-main)"></i>
            <?= h($m['location']) ?> — <?= h($m['wilayat']) ?>
          </div>
          <?php if($m['phone']): ?>
          <div class="mosque-meta">
            <i class="fas fa-phone" style="color:var(--gold-dark)"></i>
            <?= h($m['phone']) ?>
          </div>
          <?php endif; ?>
          <a href="/register.php?mosque=<?= $m['id'] ?>" class="btn btn-primary btn-sm mt-2">
            <?= $isRtl ? 'سجّل هنا' : 'Register Here' ?>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ROLES SECTION -->
<section style="padding:4rem 2rem;background:var(--green-dark)">
  <div style="max-width:900px;margin:0 auto;text-align:center">
    <h2 style="color:var(--white);font-size:1.7rem;margin-bottom:.5rem">
      <?= $isRtl ? 'نظام الوصول حسب الدور' : 'Role-Based Access System' ?>
    </h2>
    <p style="color:rgba(255,255,255,.65);margin-bottom:3rem">
      <?= $isRtl ? 'لوحة تحكم مخصصة لكل دور' : 'Dedicated dashboard for every role' ?>
    </p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.5rem">
      <?php
      $roles=[
        ['🛡️','Admin','المسؤول','Full system control: manage mosques, users, classes, reports','تحكم كامل: إدارة المساجد والمستخدمين والفصول والتقارير','#2D6A4F'],
        ['📖','Teacher','المعلم','Mark attendance, update Quran progress, manage classes','تسجيل الحضور، تحديث التقدم، إدارة الفصول','#1B4332'],
        ['👨‍👩‍👧','Parent','ولي الأمر','View child\'s attendance, memorization progress, schedules','متابعة الحضور والحفظ والجدول الدراسي','#40916C'],
      ];
      foreach($roles as $r): ?>
      <div style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:var(--radius-lg);padding:2rem;backdrop-filter:blur(8px)">
        <div style="font-size:2.5rem;margin-bottom:1rem"><?= $r[0] ?></div>
        <h3 style="color:var(--gold-light);font-size:1.1rem;margin-bottom:.5rem"><?= $isRtl ? $r[2] : $r[1] ?></h3>
        <p style="color:rgba(255,255,255,.7);font-size:.85rem;line-height:1.7"><?= $isRtl ? $r[4] : $r[3] ?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:2.5rem">
      <a href="/login.php" class="btn-hero-primary"><?= $isRtl ? 'ابدأ الآن' : 'Login to Your Account' ?></a>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
