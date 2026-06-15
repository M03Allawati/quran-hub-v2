<?php
require_once __DIR__ . '/config.php';
requireLogin();

$lang = $_SESSION['lang'] ?? 'en';
$isRtl = $lang === 'ar';
$role = $_SESSION['role'];
$pdo = getPDO();
$userId = $_SESSION['user_id'];

// Student gets their own dashboard
if ($role === 'student') {
    header('Location: /student/dashboard.php'); exit;
}
// Mosque admin gets their own dashboard
if ($role === 'mosque_admin') {
    header('Location: /mosque_admin/dashboard.php'); exit;
}

// ── Admin Dashboard ─────────────────────────────────────────
if ($role === 'admin') {
    $mosqueId = $_SESSION['user']['mosque_id'];
    $stats = [
        'students' => $pdo->query("SELECT COUNT(*) FROM students WHERE is_active=1")->fetchColumn(),
        'classes'  => $pdo->query("SELECT COUNT(*) FROM mosque_programs WHERE is_active=1 AND teacher_id IS NOT NULL")->fetchColumn(),
        'teachers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher' AND is_active=1")->fetchColumn(),
        'parents'  => $pdo->query("SELECT COUNT(*) FROM users WHERE role='parent' AND is_active=1")->fetchColumn(),
    ];
    // Recent registrations
    $recent = $pdo->query("SELECT u.full_name, u.role, u.created_at, m.name_en as mosque FROM users u LEFT JOIN mosques m ON u.mosque_id=m.id ORDER BY u.created_at DESC LIMIT 8")->fetchAll();
    // Attendance today
    $todayAtt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date=CURDATE()");
    $todayAtt->execute();
    $attToday = $todayAtt->fetchColumn();
    // Classes overview
    $classStats = $pdo->query("SELECT c.name_en, c.subject, c.level, u.full_name as teacher, (SELECT COUNT(*) FROM enrollments e WHERE e.class_id=c.id AND e.status='active') as enrolled FROM classes c LEFT JOIN users u ON c.teacher_id=u.id ORDER BY enrolled DESC LIMIT 6")->fetchAll();

// ── Teacher Dashboard ────────────────────────────────────────
} elseif ($role === 'teacher') {
    // Detect teacher's slot type (A=students, B=children)
    $mySlot = $pdo->prepare("SELECT slot, target_type, name_en, name_ar, id as prog_id FROM mosque_programs WHERE teacher_id=? AND is_active=1 LIMIT 1");
    $mySlot->execute([$userId]); $mySlot = $mySlot->fetch();
    $teacherTargetType = $mySlot['target_type'] ?? 'student';
    $teacherSlot = $mySlot['slot'] ?? 'A';
    $teacherProgId = $mySlot['prog_id'] ?? 0;

    // Active course
    $activeCourseT = $pdo->prepare("SELECT fc.*,tcs.start_date as activated FROM fixed_courses fc JOIN teacher_course_selections tcs ON tcs.course_id=fc.id WHERE tcs.teacher_id=? AND tcs.program_id=? AND tcs.is_active=1 LIMIT 1");
    $activeCourseT->execute([$userId,$teacherProgId]); $activeCourseT=$activeCourseT->fetch();

    // This week's lesson
    $thisWeekLesson = $pdo->prepare("SELECT * FROM weekly_lessons WHERE teacher_id=? AND week_start<=CURDATE() AND week_end>=CURDATE() ORDER BY id DESC LIMIT 1");
    $thisWeekLesson->execute([$userId]); $thisWeekLesson=$thisWeekLesson->fetch();

    $myClasses = $pdo->prepare("SELECT c.*,mp.slot,mp.target_type,mp.days,
           fc.name_en as course_en,fc.name_ar as course_ar,fc.icon as course_icon,
           (SELECT COUNT(*) FROM enrollments e WHERE e.class_id=c.id AND e.status='active') as enrolled
           FROM classes c
           LEFT JOIN mosque_programs mp ON mp.mosque_id=c.mosque_id AND mp.teacher_id=c.teacher_id AND mp.slot=c.slot
           LEFT JOIN teacher_course_selections tcs ON tcs.program_id=mp.id AND tcs.is_active=1
           LEFT JOIN fixed_courses fc ON fc.id=tcs.course_id
           WHERE c.teacher_id=? AND c.is_active=1
           ORDER BY c.slot");
    $myClasses->execute([$userId]);
    $myClasses = $myClasses->fetchAll();

    $totalStudents = $pdo->prepare("SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN classes c ON e.class_id=c.id WHERE c.teacher_id=? AND e.status='active'");
    $totalStudents->execute([$userId]);
    $totalStudents = $totalStudents->fetchColumn();

    $todayAtt = $pdo->prepare("SELECT COUNT(*) FROM attendance a JOIN classes c ON a.class_id=c.id WHERE c.teacher_id=? AND a.date=CURDATE()");
    $todayAtt->execute([$userId]);
    $todayAtt = $todayAtt->fetchColumn();

    // Student cards data — one card per student
    $studentCards = $pdo->prepare("SELECT s.id, s.full_name, s.full_name_ar, s.student_type,
        COUNT(DISTINCT p.surah_number) as total_surahs,
        COALESCE(AVG(p.memorization_pct),0) as avg_pct,
        COALESCE(AVG(p.tajweed_level),0) as avg_tajweed,
        (SELECT COUNT(*) FROM attendance a2 JOIN classes c2 ON c2.id=a2.class_id AND c2.teacher_id=?
            WHERE a2.student_id=s.id AND a2.status='present') as present_cnt,
        (SELECT COUNT(*) FROM attendance a3 JOIN classes c3 ON c3.id=a3.class_id AND c3.teacher_id=?
            WHERE a3.student_id=s.id) as total_att,
        (SELECT p2.surah_name_ar FROM progress p2 JOIN classes c4 ON c4.id=p2.class_id AND c4.teacher_id=?
            WHERE p2.student_id=s.id ORDER BY p2.updated_at DESC LIMIT 1) as last_surah,
        (SELECT p3.evaluation FROM progress p3 JOIN classes c5 ON c5.id=p3.class_id AND c5.teacher_id=?
            WHERE p3.student_id=s.id ORDER BY p3.updated_at DESC LIMIT 1) as last_eval
        FROM students s
        JOIN enrollments e ON e.student_id=s.id
        JOIN classes c ON c.id=e.class_id AND c.teacher_id=?
        LEFT JOIN progress p ON p.student_id=s.id AND p.class_id=c.id
        WHERE s.is_active=1
        GROUP BY s.id, s.full_name, s.full_name_ar, s.student_type ORDER BY s.full_name");
    $studentCards->execute([$userId,$userId,$userId,$userId,$userId]);
    $studentCards = $studentCards->fetchAll();

// ── Parent Dashboard ─────────────────────────────────────────
} elseif ($role === 'parent') {
    $children = $pdo->prepare("SELECT s.*, m.name_en as mosque FROM students s JOIN mosques m ON s.mosque_id=m.id WHERE s.parent_id=? AND s.is_active=1");
    $children->execute([$userId]);
    $children = $children->fetchAll();

    $childrenIds = array_column($children, 'id');
    $attStats = [];
    $progressData = [];
    $activeCourses = [];
    foreach ($childrenIds as $cid) {
        $at = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM attendance WHERE student_id=? GROUP BY status");
        $at->execute([$cid]);
        $attStats[$cid] = $at->fetchAll(PDO::FETCH_KEY_PAIR);

        $pr = $pdo->prepare("SELECT p.*, c.name_en as class_name FROM progress p JOIN classes c ON p.class_id=c.id WHERE p.student_id=? ORDER BY p.updated_at DESC LIMIT 5");
        $pr->execute([$cid]);
        $progressData[$cid] = $pr->fetchAll();

        // Active course for this child
        $ac = $pdo->prepare("
            SELECT fc.name_en,fc.name_ar,fc.icon,mp.days,mp.time_start,mp.time_end,mp.slot,
                   u.full_name as teacher_name,
                   wl.topic_en as week_topic_en,wl.topic_ar as week_topic_ar,
                   wl.surah_name_ar,wl.ayah_from,wl.ayah_to,wl.homework
            FROM program_enrollments pe
            JOIN mosque_programs mp ON mp.id=pe.program_id
            JOIN teacher_course_selections tcs ON tcs.program_id=mp.id AND tcs.is_active=1
            JOIN fixed_courses fc ON fc.id=tcs.course_id
            JOIN users u ON u.id=mp.teacher_id
            LEFT JOIN weekly_lessons wl ON wl.program_id=mp.id
                AND wl.week_start<=CURDATE() AND wl.week_end>=CURDATE()
            WHERE pe.student_id=? AND pe.status='active' LIMIT 1");
        $ac->execute([$cid]);
        $activeCourses[$cid] = $ac->fetch();
    }
}

$pageTitle = ($isRtl ? 'لوحة التحكم — ' : 'Dashboard — ') . APP_NAME;
include 'includes/header.php';
?>

<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">

<?php // ─── ADMIN VIEW ────────────────────────────────────── ?>
<?php if ($role === 'admin'): ?>

<div class="page-header flex-center justify-between">
  <div>
    <h1 class="page-title">📊 <?= $isRtl ? 'لوحة تحكم المسؤول' : 'Admin Dashboard' ?></h1>
    <p class="page-subtitle"><?= $isRtl ? 'نظرة عامة على النظام — ' : 'System overview — ' ?><?= date('l, d M Y') ?></p>
  </div>
  <div style="display:flex;gap:.75rem;flex-wrap:wrap">
    <a href="/admin/classes.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <?= $isRtl ? 'فصل جديد' : 'New Class' ?></a>
    <a href="/admin/reports.php" class="btn btn-gold btn-sm">📄 <?= $isRtl ? 'التقارير' : 'Reports' ?></a>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon green">🎓</div><div><div class="stat-value"><?= $stats['students'] ?></div><div class="stat-label"><?= $isRtl ? 'إجمالي الطلاب' : 'Total Students' ?></div></div></div>
  <div class="stat-card"><div class="stat-icon gold">📚</div><div><div class="stat-value"><?= $stats['classes'] ?></div><div class="stat-label"><?= $isRtl ? 'برامج نشطة' : 'Active Programs' ?></div></div></div>
  <div class="stat-card"><div class="stat-icon blue">📖</div><div><div class="stat-value"><?= $stats['teachers'] ?></div><div class="stat-label"><?= $isRtl ? 'المعلمون' : 'Teachers' ?></div></div></div>
  <div class="stat-card"><div class="stat-icon green">👨‍👩‍👧</div><div><div class="stat-value"><?= $stats['parents'] ?></div><div class="stat-label"><?= $isRtl ? 'أولياء الأمور' : 'Parents' ?></div></div></div>
</div>

<div class="grid-2" style="gap:1.5rem;margin-bottom:1.5rem">
  <!-- Classes table -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">📚 <?= $isRtl ? 'الفصول الدراسية' : 'Classes Overview' ?></div>
      <a href="/admin/classes.php" class="btn btn-outline btn-sm"><?= $isRtl ? 'الكل' : 'View All' ?></a>
    </div>
    <div class="table-wrapper">
      <table>
        <thead><tr>
          <th><?= $isRtl ? 'الفصل' : 'Class' ?></th>
          <th><?= $isRtl ? 'المادة' : 'Subject' ?></th>
          <th><?= $isRtl ? 'المعلم' : 'Teacher' ?></th>
          <th><?= $isRtl ? 'الطلاب' : 'Students' ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($classStats as $cs): ?>
        <tr>
          <td><strong><?= h($cs['name_en']) ?></strong></td>
          <td><span class="badge badge-green"><?= h($cs['subject'] ?? '—') ?></span></td>
          <td><?= h($cs['teacher'] ?? '—') ?></td>
          <td><span class="badge badge-blue"><?= $cs['enrolled'] ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent users -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">👥 <?= $isRtl ? 'آخر المستخدمين' : 'Recent Users' ?></div>
      <a href="/admin/users.php" class="btn btn-outline btn-sm"><?= $isRtl ? 'الكل' : 'View All' ?></a>
    </div>
    <div class="table-wrapper">
      <table>
        <thead><tr>
          <th><?= $isRtl ? 'الاسم' : 'Name' ?></th>
          <th><?= $isRtl ? 'الدور' : 'Role' ?></th>
          <th><?= $isRtl ? 'تاريخ التسجيل' : 'Registered' ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($recent as $u): ?>
        <tr>
          <td><strong><?= h($u['full_name']) ?></strong></td>
          <td>
            <span class="badge <?= $u['role']==='admin'?'badge-red':($u['role']==='teacher'?'badge-blue':'badge-green') ?>">
              <?= h($u['role']) ?>
            </span>
          </td>
          <td style="font-size:.82rem;color:var(--gray-500)"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Attendance today banner -->
<div class="card" style="background:linear-gradient(135deg,var(--green-dark),var(--green-main));color:#fff;border:none">
  <div class="card-body" style="display:flex;align-items:center;gap:2rem;flex-wrap:wrap">
    <div style="font-size:3rem">✅</div>
    <div>
      <div style="font-size:1.8rem;font-weight:800"><?= $attToday ?></div>
      <div style="opacity:.8"><?= $isRtl ? 'سجلات الحضور اليوم' : 'Attendance records logged today' ?></div>
    </div>
    <div style="margin-left:auto">
      <a href="/admin/attendance.php" class="btn btn-gold"><?= $isRtl ? 'عرض الحضور' : 'View Attendance' ?></a>
    </div>
  </div>
</div>

<?php // ─── TEACHER VIEW ──────────────────────────────────── ?>
<?php elseif ($role === 'teacher'): ?>

<div class="page-header">
  <h1 class="page-title">📖 <?= $isRtl ? 'لوحة تحكم المعلم' : 'Teacher Dashboard' ?></h1>
  <p class="page-subtitle">
    <?= $isRtl ? 'مرحباً ' : 'Welcome, ' ?><?= h($_SESSION['user']['full_name']) ?> — <?= date('l, d M Y') ?>
    <?php if($mySlot):?>
    &nbsp;·&nbsp;
    <span style="background:<?=$teacherTargetType==='child'?'#FEF3C7':'#DBEAFE'?>;color:<?=$teacherTargetType==='child'?'#92400E':'#1D4ED8'?>;padding:2px 10px;border-radius:99px;font-size:12px;font-weight:700">
      <?=$teacherTargetType==='child'?'👶 '.($isRtl?'معلم الأطفال':'Children Teacher'):'🎓 '.($isRtl?'معلم الطلاب':'Students Teacher')?>
      · Slot <?=$teacherSlot?>
    </span>
    <?php endif;?>
  </p>
</div>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon green">📚</div><div><div class="stat-value"><?= count($myClasses) ?></div><div class="stat-label"><?= $isRtl ? 'برامجي' : 'My Program' ?></div></div></div>
  <div class="stat-card"><div class="stat-icon blue"><?=$teacherTargetType==='child'?'👶':'🎓'?></div><div><div class="stat-value"><?= $totalStudents ?></div><div class="stat-label"><?= $teacherTargetType==='child'?($isRtl?'أطفالي':'My Children'):($isRtl?'طلابي':'My Students') ?></div></div></div>
  <div class="stat-card"><div class="stat-icon gold">✅</div><div><div class="stat-value"><?= $todayAtt ?></div><div class="stat-label"><?= $isRtl ? 'حضور اليوم' : "Today's Attendance" ?></div></div></div>
</div>

<!-- Active Course + This Week Banner -->
<?php if($activeCourseT||$thisWeekLesson):
  $isChildT=$teacherTargetType==='child';
  $tGrad=$isChildT?'linear-gradient(135deg,#92400E,#D97706)':'linear-gradient(135deg,#1D4ED8,#4F46E5)';
?>
<div style="background:<?=$tGrad?>;border-radius:14px;padding:18px 22px;color:#fff;margin-bottom:20px;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px">
  <?php if($activeCourseT):?>
  <div>
    <div style="font-size:10px;opacity:.7;font-weight:600;text-transform:uppercase;margin-bottom:6px"><?=$isRtl?'دورتي الحالية':'My Active Course'?></div>
    <div style="display:flex;align-items:center;gap:10px">
      <span style="font-size:2rem"><?=$activeCourseT['icon']?></span>
      <div>
        <div style="font-weight:800;font-size:1.1rem"><?=h($isRtl?$activeCourseT['name_ar']:$activeCourseT['name_en'])?></div>
        <div style="font-size:11px;opacity:.8"><?=h($isRtl?$activeCourseT['description_ar']:$activeCourseT['description_en'])?></div>
      </div>
    </div>
  </div>
  <?php endif;?>
  <?php if($thisWeekLesson):?>
  <div style="background:rgba(255,255,255,.15);border-radius:10px;padding:12px 16px;min-width:220px">
    <div style="font-size:10px;opacity:.7;font-weight:600;text-transform:uppercase;margin-bottom:5px">📅 <?=$isRtl?'درس هذا الأسبوع':'This Week\'s Lesson'?></div>
    <div style="font-weight:700;font-size:13px"><?=h($isRtl?$thisWeekLesson['topic_ar']:$thisWeekLesson['topic_en'])?></div>
    <?php if($thisWeekLesson['surah_name_ar']):?><div style="font-size:11px;opacity:.85;margin-top:2px">📖 <?=h($thisWeekLesson['surah_name_ar'])?><?=$thisWeekLesson['ayah_from']?' (آية '.$thisWeekLesson['ayah_from'].'–'.$thisWeekLesson['ayah_to'].')':''?></div><?php endif;?>
    <?php if($thisWeekLesson['homework']):?><div style="font-size:11px;opacity:.8;margin-top:2px">📋 <?=h($thisWeekLesson['homework'])?></div><?php endif;?>
  </div>
  <?php else:?>
  <a href="/teacher/programs.php?tab=mine" style="background:rgba(255,255,255,.15);border-radius:10px;padding:12px 16px;color:#fff;text-decoration:none;display:flex;align-items:center;gap:8px;font-size:12px;font-weight:600;border:1px solid rgba(255,255,255,.3)">
    ➕ <?=$isRtl?'أضف درس هذا الأسبوع':'Add This Week\'s Lesson'?>
  </a>
  <?php endif;?>
</div>
<?php endif;?>

<div class="grid-2" style="gap:1.5rem">
  <div class="card">
    <div class="card-header">
      <div class="card-title">📚 <?= $isRtl ? 'برنامجي' : 'My Program' ?></div>
      <a href="/teacher/programs.php" class="btn btn-outline btn-sm"><?=$isRtl?'التفاصيل':'Details'?></a>
    </div>
    <div style="padding:12px">
      <?php foreach($myClasses as $cls):
        $isChild=($cls['target_type']??'')===('child');
        $slotBg=$isChild?'#FEF3C7':'#DBEAFE';
        $slotColor=$isChild?'#92400E':'#1D4ED8';
      ?>
      <div style="border:1px solid var(--gray-100);border-radius:10px;padding:12px;margin-bottom:8px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px">
          <div>
            <span style="background:<?=$slotBg?>;color:<?=$slotColor?>;padding:1px 8px;border-radius:99px;font-size:10px;font-weight:700">
              <?=$isChild?'👶':'🎓'?> Slot <?=$cls['slot']??'A'?>
            </span>
            <div style="font-weight:700;font-size:13px;color:var(--green-dark);margin-top:4px"><?=h($cls['name_en'])?></div>
          </div>
          <span style="background:var(--green-pale);color:var(--green-dark);padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600"><?=$cls['enrolled']?> <?=$isRtl?'طالب':'students'?></span>
        </div>
        <?php if($cls['course_en']):?>
        <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:<?=$slotColor?>;margin-bottom:4px">
          <span><?=$cls['course_icon']?></span>
          <span><?=h($isRtl?$cls['course_ar']:$cls['course_en'])?></span>
        </div>
        <?php endif;?>
        <div style="font-size:11px;color:var(--gray-400)">⏰ <?=substr($cls['time_start'],0,5)?>–<?=substr($cls['time_end'],0,5)?></div>
      </div>
      <?php endforeach;?>
      <?php if(empty($myClasses)):?>
      <div style="text-align:center;padding:24px;color:var(--gray-300)"><div style="font-size:2rem">📚</div><p style="font-size:13px;margin-top:8px"><?=$isRtl?'لا توجد فصول':'No classes yet'?></p></div>
      <?php endif;?>
      <a href="/teacher/attendance.php" class="btn btn-primary" style="width:100%;text-align:center;margin-top:4px">✅ <?=$isRtl?'سجّل الحضور':'Mark Attendance'?></a>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title"><?=$teacherTargetType==='child'?'👶':' 🎓'?> <?= $teacherTargetType==='child'?($isRtl?'أطفالي':'My Children'):($isRtl?'طلابي':'My Students') ?></div>
      <a href="/teacher/progress.php" class="btn btn-outline btn-sm"><?= $isRtl ? 'التقدم' : 'Progress' ?></a>
    </div>
    <?php if(empty($studentCards)): ?>
    <div style="padding:2rem;text-align:center;color:var(--gray-300)">
      <div style="font-size:2rem">🎓</div>
      <p style="font-size:13px;margin-top:8px"><?= $isRtl ? 'لا يوجد طلاب بعد' : 'No students yet' ?></p>
    </div>
    <?php else: foreach($studentCards as $sc):
      $avgPct = round($sc['avg_pct']);
      $attPct = $sc['total_att']>0 ? round($sc['present_cnt']/$sc['total_att']*100) : 0;
      $barColor = $avgPct>=80?'var(--success)':($avgPct>=50?'var(--green-main)':($avgPct>=30?'var(--warning)':'var(--danger)'));
      $attColor = $attPct>=80?'var(--success)':($attPct>=60?'var(--warning)':'var(--danger)');
      $evalBg = ['Excellent'=>'#D1FAE5','Good'=>'#DBEAFE','Needs Improvement'=>'#FEF3C7','Repeat'=>'#FEE2E2'];
      $evalTc = ['Excellent'=>'#065F46','Good'=>'#1D4ED8','Needs Improvement'=>'#92400E','Repeat'=>'#991B1B'];
    ?>
    <div style="padding:14px 18px;border-bottom:1px solid var(--gray-50)">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:38px;height:38px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-weight:800;color:var(--green-dark);font-size:1rem;flex-shrink:0">
            <?= strtoupper(substr($sc['full_name'],0,1)) ?>
          </div>
          <div>
            <div style="font-weight:700;font-size:13px;color:var(--dark)"><?= h($sc['full_name']) ?></div>
            <div style="font-size:10px;color:var(--gray-500)">
              <?= $sc['total_surahs'] ?> <?= $isRtl?'سورة':'surahs'?> ·
              ⭐ <?= round($sc['avg_tajweed'],1) ?> ·
              <?php if($sc['last_surah']): ?>
              <?= $isRtl?'آخر:':'Last:' ?> <?= h($sc['last_surah']) ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <a href="/teacher/student_profile.php?id=<?= $sc['id'] ?>"
           style="background:var(--green-pale);color:var(--green-dark);padding:5px 12px;border-radius:99px;font-size:11px;font-weight:700;text-decoration:none;white-space:nowrap">
          👤 <?= $isRtl?'الملف':'Profile'?> →
        </a>
      </div>
      <!-- Memorization bar -->
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
        <span style="font-size:10px;color:var(--gray-500);min-width:60px"><?= $isRtl?'الحفظ':'Memory'?></span>
        <div style="flex:1;height:6px;background:var(--gray-100);border-radius:3px;overflow:hidden">
          <div style="height:100%;width:<?= $avgPct ?>%;background:<?= $barColor ?>;border-radius:3px"></div>
        </div>
        <span style="font-size:10px;font-weight:700;color:<?= $barColor ?>;min-width:30px"><?= $avgPct ?>%</span>
      </div>
      <!-- Attendance bar -->
      <div style="display:flex;align-items:center;gap:8px">
        <span style="font-size:10px;color:var(--gray-500);min-width:60px"><?= $isRtl?'الحضور':'Attend'?></span>
        <div style="flex:1;height:6px;background:var(--gray-100);border-radius:3px;overflow:hidden">
          <div style="height:100%;width:<?= $attPct ?>%;background:<?= $attColor ?>;border-radius:3px"></div>
        </div>
        <span style="font-size:10px;font-weight:700;color:<?= $attColor ?>;min-width:30px"><?= $attPct ?>%</span>
      </div>
      <?php if($sc['last_eval']): ?>
      <div style="margin-top:6px">
        <span style="background:<?= $evalBg[$sc['last_eval']]??'#F3F4F6' ?>;color:<?= $evalTc[$sc['last_eval']]??'#374151' ?>;padding:1px 8px;border-radius:99px;font-size:10px;font-weight:700">
          <?= h($sc['last_eval']) ?>
        </span>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php // ─── PARENT VIEW ───────────────────────────────────── ?>
<?php elseif ($role === 'parent'): ?>

<div class="page-header flex-center justify-between">
  <div>
    <h1 class="page-title">👨‍👩‍👧 <?= $isRtl ? 'لوحة تحكم ولي الأمر' : 'Parent Dashboard' ?></h1>
    <p class="page-subtitle"><?= $isRtl ? 'تابع تقدم أطفالك' : 'Track your children\'s Quranic journey' ?></p>
  </div>
  <a href="/parent/children.php" class="btn btn-primary btn-sm">+ <?= $isRtl ? 'إضافة طفل' : 'Add Child' ?></a>
</div>

<?php if (empty($children)): ?>
<div class="card" style="text-align:center;padding:3rem">
  <div style="font-size:3rem;margin-bottom:1rem">👶</div>
  <h3 style="color:var(--green-dark)"><?= $isRtl ? 'لم تسجّل أي أطفال بعد' : 'No children registered yet' ?></h3>
  <p style="color:var(--gray-500);margin:.75rem 0 1.5rem"><?= $isRtl ? 'سجّل طفلك في أحد المراكز' : 'Register your child at a center' ?></p>
  <a href="/parent/children.php" class="btn btn-primary"><?= $isRtl ? 'تسجيل طفل' : 'Register a Child' ?></a>
</div>
<?php else: ?>

<?php foreach ($children as $child):
  $cAC = $activeCourses[$child['id']] ?? null;
  $slotBg = $cAC && $cAC['slot']==='A' ? 'linear-gradient(135deg,#1D4ED8,#3B82F6)' : 'linear-gradient(135deg,#92400E,#D97706)';
?>
<div class="card" style="margin-bottom:1.5rem">
  <div class="card-header">
    <div class="card-title">
      <?=$cAC&&$cAC['slot']==='B'?'👶':'🎓'?> <?= h($child['full_name']) ?>
      <?php if ($child['full_name_ar']): ?><span style="color:var(--gray-500);font-weight:400;font-family:var(--font-ar)"> — <?= h($child['full_name_ar']) ?></span><?php endif; ?>
    </div>
    <div style="display:flex;gap:.5rem">
      <span class="badge badge-<?= $child['gender']==='male'?'blue':'green' ?>"><?= $child['gender'] ?></span>
      <span class="badge badge-gold"><?= h($child['mosque']) ?></span>
    </div>
  </div>
  <div class="card-body">

    <!-- Active Course Banner -->
    <?php if($cAC):?>
    <div style="background:<?=$slotBg?>;border-radius:12px;padding:14px 18px;color:#fff;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <div>
        <div style="font-size:10px;opacity:.7;font-weight:600;text-transform:uppercase;margin-bottom:4px"><?=$isRtl?'الدورة الحالية':'Current Course'?></div>
        <div style="display:flex;align-items:center;gap:8px">
          <span style="font-size:1.4rem"><?=$cAC['icon']?></span>
          <div>
            <div style="font-weight:700;font-size:14px"><?=h($isRtl?$cAC['name_ar']:$cAC['name_en'])?></div>
            <div style="font-size:11px;opacity:.8">👨‍🏫 <?=h($cAC['teacher_name'])?> · 📅 <?=str_replace(',',' · ',$cAC['days'])?> · ⏰ <?=substr($cAC['time_start'],0,5)?>–<?=substr($cAC['time_end'],0,5)?></div>
          </div>
        </div>
      </div>
      <?php if($cAC['week_topic_ar']||$cAC['week_topic_en']):?>
      <div style="background:rgba(255,255,255,.15);border-radius:10px;padding:10px 14px">
        <div style="font-size:10px;opacity:.7;font-weight:600;margin-bottom:4px">📅 <?=$isRtl?'درس هذا الأسبوع':'This Week'?></div>
        <div style="font-weight:700;font-size:13px"><?=h($isRtl?$cAC['week_topic_ar']:$cAC['week_topic_en'])?></div>
        <?php if($cAC['surah_name_ar']):?><div style="font-size:11px;opacity:.85;margin-top:2px">📖 <?=h($cAC['surah_name_ar'])?></div><?php endif;?>
        <?php if($cAC['homework']):?><div style="font-size:11px;opacity:.85;margin-top:2px">📋 <?=h($cAC['homework'])?></div><?php endif;?>
      </div>
      <?php endif;?>
    </div>
    <?php else:?>
    <div style="background:var(--gray-50);border-radius:10px;padding:12px;text-align:center;color:var(--gray-400);font-size:12px;margin-bottom:16px">
      <?=$isRtl?'لم يختر المعلم دورة بعد':'Teacher hasn\'t selected a course yet'?>
    </div>
    <?php endif;?>

    <?php
    $att = $attStats[$child['id']];
    $present = $att['present'] ?? 0;
    $absent  = $att['absent'] ?? 0;
    $late    = $att['late'] ?? 0;
    $total   = array_sum($att);
    $pct     = $total > 0 ? round($present / $total * 100) : 0;
    ?>
    <div class="grid-2" style="gap:1.5rem">
      <!-- Attendance -->
      <div>
        <div style="font-weight:700;margin-bottom:.75rem;color:var(--green-dark)">✅ <?= $isRtl ? 'الحضور' : 'Attendance' ?></div>
        <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);gap:.75rem;margin-bottom:1rem">
          <div class="stat-card" style="padding:1rem;flex-direction:column;text-align:center;gap:.25rem">
            <div class="stat-value" style="font-size:1.4rem;color:var(--success)"><?= $present ?></div>
            <div class="stat-label"><?= $isRtl ? 'حاضر' : 'Present' ?></div>
          </div>
          <div class="stat-card" style="padding:1rem;flex-direction:column;text-align:center;gap:.25rem">
            <div class="stat-value" style="font-size:1.4rem;color:var(--danger)"><?= $absent ?></div>
            <div class="stat-label"><?= $isRtl ? 'غائب' : 'Absent' ?></div>
          </div>
          <div class="stat-card" style="padding:1rem;flex-direction:column;text-align:center;gap:.25rem">
            <div class="stat-value" style="font-size:1.4rem;color:var(--warning)"><?= $late ?></div>
            <div class="stat-label"><?= $isRtl ? 'متأخر' : 'Late' ?></div>
          </div>
        </div>
        <div style="font-size:.85rem;margin-bottom:.4rem;color:var(--gray-500)"><?= $isRtl ? 'نسبة الحضور:' : 'Attendance rate:' ?> <strong style="color:var(--green-main)"><?= $pct ?>%</strong></div>
        <div class="progress-bar-wrapper">
          <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
        </div>
      </div>

      <!-- Progress -->
      <div>
        <div style="font-weight:700;margin-bottom:.75rem;color:var(--green-dark)">📖 <?= $isRtl ? 'تقدم الحفظ' : 'Memorization Progress' ?></div>
        <?php foreach ($progressData[$child['id']] as $pg): ?>
        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem;padding:.6rem;background:var(--green-pale);border-radius:var(--radius-sm)">
          <div style="flex:1">
            <div style="font-size:.88rem;font-weight:600"><?= h($pg['surah_name_ar']) ?> — <?= h($pg['surah_name_en']) ?></div>
            <div style="font-size:.78rem;color:var(--gray-500)"><?= $isRtl ? 'التجويد' : 'Tajweed' ?>: <?= str_repeat('⭐', (int)$pg['tajweed_level']) ?></div>
          </div>
          <div style="text-align:center;min-width:50px">
            <div style="font-weight:800;color:var(--green-dark)"><?= $pg['memorization_pct'] ?>%</div>
            <span class="badge badge-<?= $pg['evaluation']==='Excellent'?'green':($pg['evaluation']==='Good'?'blue':'warning') ?>" style="font-size:.72rem">
              <?= h($pg['evaluation']) ?>
            </span>
          </div>
          <div class="progress-bar-wrapper" style="width:60px">
            <div class="progress-bar-fill <?= $pg['memorization_pct']<50?'gold':'' ?>" style="width:<?= $pg['memorization_pct'] ?>%"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

</main>
</div>
<?php include 'includes/footer.php'; ?>
