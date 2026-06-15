<?php
require_once __DIR__ . '/../config.php';
requireRole('student');
$lang   = $_SESSION['lang'] ?? 'en';
$isRtl  = $lang === 'ar';
$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$pageTitle = ($isRtl ? 'لوحتي — ' : 'My Dashboard — ') . APP_NAME;

// Get student record linked to this user
$student = $pdo->prepare("SELECT s.*, m.name_ar as mosque_ar, m.name_en as mosque_en
    FROM students s JOIN mosques m ON m.id = s.mosque_id
    WHERE s.user_id = ? AND s.is_active = 1 LIMIT 1");
$student->execute([$userId]);
$student = $student->fetch();

// If no student record yet — shouldn't happen after register, but just in case
if (!$student) {
    // Don't redirect to /dashboard.php — that causes a loop for students
    session_destroy();
    header('Location: /login.php?error=no_student_record');
    exit;
}
$sid = $student['id'];

// Active course (what teacher is currently teaching this student)
$activeCourse=$pdo->prepare("
    SELECT fc.name_en,fc.name_ar,fc.icon,fc.description_en,fc.description_ar,
           mp.name_en as prog_en,mp.name_ar as prog_ar,mp.days,mp.time_start,mp.time_end,mp.slot,
           u.full_name as teacher_name,
           wl.topic_en as week_topic_en,wl.topic_ar as week_topic_ar,
           wl.week_start,wl.week_end,wl.surah_name_ar,wl.surah_name_en,
           wl.ayah_from,wl.ayah_to,wl.homework,wl.objectives
    FROM program_enrollments pe
    JOIN mosque_programs mp ON mp.id=pe.program_id
    JOIN teacher_course_selections tcs ON tcs.program_id=mp.id AND tcs.is_active=1
    JOIN fixed_courses fc ON fc.id=tcs.course_id
    JOIN users u ON u.id=mp.teacher_id
    LEFT JOIN weekly_lessons wl ON wl.program_id=mp.id
        AND wl.week_start<=CURDATE() AND wl.week_end>=CURDATE()
    WHERE pe.student_id=? AND pe.status='active'
    ORDER BY pe.id ASC LIMIT 1");
$activeCourse->execute([$sid]); $activeCourse=$activeCourse->fetch();

// My classes
$classes = $pdo->prepare(
    "SELECT c.*, u.full_name as teacher_name, u.full_name_ar as teacher_ar,
            e.enrolled_at, e.status as enroll_status
     FROM enrollments e
     JOIN classes c ON c.id = e.class_id
     JOIN users u ON u.id = c.teacher_id
     WHERE e.student_id = ? AND e.status = 'active'
     ORDER BY c.schedule_day"
);
$classes->execute([$sid]);
$myClasses = $classes->fetchAll();

// Attendance stats
$att = $pdo->prepare(
    "SELECT status, COUNT(*) as cnt
     FROM attendance WHERE student_id = ?
     GROUP BY status"
);
$att->execute([$sid]);
$attStats = [];
foreach ($att->fetchAll() as $row) $attStats[$row['status']] = $row['cnt'];
$totalAtt = array_sum($attStats);
$presentPct = $totalAtt > 0 ? round(($attStats['present'] ?? 0) / $totalAtt * 100) : 0;

// Progress summary
$progress = $pdo->prepare(
    "SELECT p.* FROM progress p
     WHERE p.student_id = ?
     ORDER BY p.surah_number"
);
$progress->execute([$sid]);
$myProgress = $progress->fetchAll();
$completedSurahs = array_filter($myProgress, fn($p) => $p['memorization_pct'] == 100);
$totalPoints     = $pdo->prepare("SELECT total_points, level FROM student_points WHERE student_id=?");
$totalPoints->execute([$sid]);
$pts = $totalPoints->fetch() ?: ['total_points' => 0, 'level' => 1];

// Notifications
$notifs = $pdo->prepare(
    "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0
     ORDER BY created_at DESC LIMIT 5"
);
$notifs->execute([$userId]);
$unreadNotifs = $notifs->fetchAll();

// Recent attendance (last 10)
$recentAtt = $pdo->prepare(
    "SELECT a.date, a.status, c.name_ar, c.name_en
     FROM attendance a JOIN classes c ON c.id = a.class_id
     WHERE a.student_id = ?
     ORDER BY a.date DESC LIMIT 10"
);
$recentAtt->execute([$sid]);
$recentAtt = $recentAtt->fetchAll();

// Badges
$badges = $pdo->prepare(
    "SELECT b.name_ar, b.name_en, b.icon, b.color, sb.earned_at
     FROM student_badges sb JOIN badges b ON b.id = sb.badge_id
     WHERE sb.student_id = ? ORDER BY sb.earned_at DESC LIMIT 6"
);
$badges->execute([$sid]);
$myBadges = $badges->fetchAll();

$levelNames = [1=>'مبتدئ',2=>'متقدم',3=>'محترف',4=>'خبير',5=>'حافظ'];

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

  <!-- Welcome bar -->
  <div style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));border-radius:var(--radius-lg);padding:24px 28px;color:#fff;margin-bottom:28px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
    <div style="width:56px;height:56px;border-radius:50%;background:var(--gold-main);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;color:#1B4332;flex-shrink:0">
      <?= strtoupper(substr($student['full_name'],0,1)) ?>
    </div>
    <div style="flex:1">
      <div style="font-size:1.3rem;font-weight:800;color:var(--gold-light)">
        <?= $isRtl ? 'أهلاً، ' : 'Welcome, ' ?><?= h($isRtl && $student['full_name_ar'] ? $student['full_name_ar'] : $student['full_name']) ?> 👋
      </div>
      <div style="opacity:.8;font-size:.9rem;margin-top:4px">
        🕌 <?= h($isRtl ? $student['mosque_ar'] : $student['mosque_en']) ?>
        &nbsp;•&nbsp; <?= $isRtl?'المستوى':'Level' ?> <?= $pts['level'] ?> — <?= $levelNames[$pts['level']] ?>
      </div>
    </div>
    <div style="display:flex;gap:16px;flex-wrap:wrap">
      <?php foreach ([
        ['⭐', $pts['total_points'], $isRtl?'نقطة':'pts'],
        ['📖', count($completedSurahs), $isRtl?'سورة':'surahs'],
        ['✅', $presentPct.'%', $isRtl?'حضور':'attend.'],
        ['🏅', count($myBadges), $isRtl?'وسام':'badges'],
      ] as [$icon, $val, $label]): ?>
      <div style="text-align:center;min-width:56px">
        <div style="font-size:1.4rem"><?= $icon ?></div>
        <div style="font-size:1.3rem;font-weight:800;line-height:1.1"><?= $val ?></div>
        <div style="font-size:.75rem;opacity:.7"><?= $label ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

  <!-- Active Course Card -->
  <?php if($activeCourse):
    $slotBg=$activeCourse['slot']==='A'?'linear-gradient(135deg,#1D4ED8,#3B82F6)':'linear-gradient(135deg,#92400E,#D97706)';
  ?>
  <div style="background:<?=$slotBg?>;border-radius:14px;padding:20px;color:#fff;grid-column:1/-1">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
      <div>
        <div style="font-size:11px;opacity:.7;margin-bottom:4px;font-weight:600;text-transform:uppercase">
          <?=$isRtl?'دورتي الحالية':'My Current Course'?> <?=$activeCourse['slot']==='A'?'🎓':'👶'?>
        </div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
          <span style="font-size:2rem"><?=$activeCourse['icon']?></span>
          <div>
            <div style="font-weight:800;font-size:1.2rem"><?=h($isRtl?$activeCourse['name_ar']:$activeCourse['name_en'])?></div>
            <div style="font-size:12px;opacity:.8"><?=h($isRtl?$activeCourse['prog_ar']:$activeCourse['prog_en'])?></div>
          </div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;font-size:12px;opacity:.85">
          <span>👨‍🏫 <?=h($activeCourse['teacher_name'])?></span>
          <span>📅 <?=str_replace(',',' · ',$activeCourse['days'])?></span>
          <span>⏰ <?=substr($activeCourse['time_start'],0,5)?>–<?=substr($activeCourse['time_end'],0,5)?></span>
        </div>
      </div>
      <?php if($activeCourse['week_topic_ar']||$activeCourse['week_topic_en']):?>
      <div style="background:rgba(255,255,255,.15);border-radius:12px;padding:14px;min-width:200px">
        <div style="font-size:10px;opacity:.7;font-weight:700;text-transform:uppercase;margin-bottom:6px">📅 <?=$isRtl?'درس هذا الأسبوع':'This Week\'s Lesson'?></div>
        <div style="font-weight:700;font-size:14px"><?=h($isRtl?$activeCourse['week_topic_ar']:$activeCourse['week_topic_en'])?></div>
        <?php if($activeCourse['surah_name_ar']):?>
        <div style="font-size:11px;opacity:.85;margin-top:3px">📖 <?=h($activeCourse['surah_name_ar'])?><?=$activeCourse['ayah_from']?' (آية '.$activeCourse['ayah_from'].'–'.$activeCourse['ayah_to'].')':''?></div>
        <?php endif;?>
        <?php if($activeCourse['homework']):?>
        <div style="font-size:11px;opacity:.85;margin-top:3px">📋 <?=h($activeCourse['homework'])?></div>
        <?php endif;?>
      </div>
      <?php else:?>
      <div style="background:rgba(255,255,255,.1);border-radius:12px;padding:14px;text-align:center;opacity:.7">
        <div style="font-size:1.5rem">📅</div>
        <div style="font-size:11px;margin-top:4px"><?=$isRtl?'لم يُضف درس هذا الأسبوع بعد':'No lesson added this week yet'?></div>
      </div>
      <?php endif;?>
    </div>
  </div>
  <?php elseif(!empty($myClasses)):?>
  <div style="background:#fff;border:1px dashed var(--gray-100);border-radius:14px;padding:20px;text-align:center;color:var(--gray-300);grid-column:1/-1">
    <div style="font-size:2rem">📚</div>
    <div style="margin-top:8px;font-size:13px"><?=$isRtl?'لم يختر معلمك دورة بعد — سيظهر هنا عند الاختيار':'Your teacher hasn\'t selected a course yet'?></div>
  </div>
  <?php endif;?>

  <!-- My Classes -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">📚 <?= $isRtl?'فصلي ودورتي':'My Class & Course' ?></h3>
      <a href="/student/classes.php" class="btn btn-outline btn-sm"><?=$isRtl?'التفاصيل':'Details'?></a>
    </div>
    <div class="card-body" style="padding:0">
      <?php if (empty($myClasses)): ?>
      <div style="padding:24px;text-align:center;color:var(--gray-300)">
        <div style="font-size:2.5rem">📚</div>
        <div style="margin-top:8px;font-size:13px"><?= $isRtl?'لا توجد فصول مسجّلة بعد':'No classes enrolled yet' ?></div>
        <a href="/student/programs.php" class="btn btn-primary btn-sm" style="margin-top:12px"><?= $isRtl?'عرض البرامج':'View Programs' ?></a>
      </div>
      <?php else: foreach ($myClasses as $cls): ?>
      <div style="padding:14px 16px;border-bottom:1px solid var(--gray-50)">
        <div style="font-weight:700;font-size:13px;color:var(--green-dark)">🎓 <?=$isRtl?'برنامج الطلاب — Slot A':'Students Program — Slot A'?></div>
        <div style="font-size:12px;color:var(--gray-500);margin-top:3px">
          👨‍🏫 <?= h($isRtl&&($cls['teacher_ar']??'')?$cls['teacher_ar']:$cls['teacher_name']) ?>
          &nbsp;·&nbsp; ⏰ <?= substr($cls['time_start'],0,5) ?>–<?= substr($cls['time_end'],0,5) ?>
          &nbsp;·&nbsp; 📅 <?= h($cls['schedule_day']) ?>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Attendance -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">✅ <?= $isRtl?'الحضور':'Attendance' ?></h3>
    </div>
    <div class="card-body">
      <!-- Big circle -->
      <div style="text-align:center;margin-bottom:20px">
        <div style="position:relative;width:100px;height:100px;margin:0 auto">
          <svg viewBox="0 0 36 36" style="width:100px;height:100px;transform:rotate(-90deg)">
            <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--gray-100)" stroke-width="3"/>
            <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--green-main)" stroke-width="3"
              stroke-dasharray="<?= $presentPct ?> <?= 100-$presentPct ?>"
              stroke-linecap="round"/>
          </svg>
          <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center">
            <div style="font-size:1.4rem;font-weight:800;color:var(--green-dark)"><?= $presentPct ?>%</div>
            <div style="font-size:10px;color:var(--gray-500)"><?= $isRtl?'حضور':'present' ?></div>
          </div>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px">
        <?php foreach ([
          ['present','✅',$isRtl?'حاضر':'Present','var(--success-pale)','var(--success)'],
          ['absent','❌',$isRtl?'غائب':'Absent','var(--danger-pale)','var(--danger)'],
          ['late','⏰',$isRtl?'متأخر':'Late','var(--warning-pale)','var(--warning)'],
          ['excused','📋',$isRtl?'بعذر':'Excused','var(--info-pale)','var(--info)'],
        ] as [$key,$icon,$label,$bg,$color]): ?>
        <div style="background:<?=$bg?>;border-radius:8px;padding:10px;text-align:center">
          <div style="font-size:1.2rem"><?= $icon ?></div>
          <div style="font-size:1.1rem;font-weight:700;color:<?=$color?>"><?= $attStats[$key] ?? 0 ?></div>
          <div style="font-size:11px;color:var(--gray-500)"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <!-- Recent attendance list -->
      <div style="font-size:12px;font-weight:700;color:var(--gray-500);margin-bottom:8px"><?= $isRtl?'آخر الحصص:':'Recent:' ?></div>
      <?php foreach (array_slice($recentAtt, 0, 5) as $r):
        $statusColors = ['present'=>'var(--success)','absent'=>'var(--danger)','late'=>'var(--warning)','excused'=>'var(--info)'];
        $statusIcons  = ['present'=>'✅','absent'=>'❌','late'=>'⏰','excused'=>'📋'];
      ?>
      <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--gray-50);font-size:12px">
        <span style="color:var(--gray-700)"><?= h($isRtl&&$r['name_ar']?$r['name_ar']:$r['name_en']) ?></span>
        <span><?= date('m/d', strtotime($r['date'])) ?></span>
        <span style="color:<?= $statusColors[$r['status']] ?? '' ?>"><?= $statusIcons[$r['status']] ?? '' ?> <?= h($r['status']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Quran Progress -->
  <div class="card" style="grid-column:1/-1">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
      <h3 class="card-title">📖 <?= $isRtl?'تقدم الحفظ':'Memorization Progress' ?></h3>
      <span style="font-size:13px;color:var(--gray-500)"><?= count($completedSurahs) ?>/114 <?= $isRtl?'سورة':'surahs' ?></span>
    </div>
    <div class="card-body">
      <?php if (empty($myProgress)): ?>
      <div style="text-align:center;padding:30px;color:var(--gray-300)">
        <div style="font-size:2.5rem">📖</div>
        <div style="margin-top:8px"><?= $isRtl?'لم يُسجَّل تقدم بعد. سيحدّثه معلمك.':'No progress yet. Your teacher will update it.' ?></div>
      </div>
      <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px">
        <?php foreach ($myProgress as $p):
          $pct = (int)$p['memorization_pct'];
          $evalColors = ['Excellent'=>'var(--success)','Good'=>'var(--info)','Needs Improvement'=>'var(--warning)','Repeat'=>'var(--danger)'];
          $evalColor  = $evalColors[$p['evaluation']] ?? 'var(--gray-500)';
          $stars      = str_repeat('⭐', (int)$p['tajweed_level']) . str_repeat('☆', 5-(int)$p['tajweed_level']);
        ?>
        <div style="border:1px solid var(--gray-100);border-radius:10px;padding:12px 14px;background:<?= $pct==100?'var(--success-pale)':'#fff' ?>">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <div style="font-weight:700;font-size:14px;color:var(--green-dark)"><?= h($p['surah_name_ar'] ?? 'Surah '.$p['surah_number']) ?></div>
            <span style="font-size:11px;color:<?= $evalColor ?>;font-weight:700"><?= $pct ?>%</span>
          </div>
          <div style="font-size:11px;color:var(--gray-500);margin-bottom:6px"><?= h($p['surah_name_en'] ?? '') ?></div>
          <div style="height:5px;background:var(--gray-100);border-radius:3px;overflow:hidden;margin-bottom:6px">
            <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct==100?'var(--success)':'var(--green-main)' ?>;border-radius:3px;transition:width .5s"></div>
          </div>
          <div style="font-size:11px;color:var(--gray-500);display:flex;justify-content:space-between">
            <span><?= $stars ?></span>
            <span style="color:<?= $evalColor ?>"><?= h($p['evaluation']) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Badges -->
  <?php if (!empty($myBadges)): ?>
  <div class="card">
    <div class="card-header"><h3 class="card-title">🏅 <?= $isRtl?'أوسمتي':'My Badges' ?></h3></div>
    <div class="card-body">
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <?php foreach ($myBadges as $b): ?>
        <div style="text-align:center;padding:10px 12px;border:2px solid <?= h($b['color']) ?>;border-radius:12px;min-width:70px"
             title="<?= h($b['name_ar']) ?>">
          <div style="font-size:1.8rem"><?= h($b['icon']) ?></div>
          <div style="font-size:10px;font-weight:600;color:var(--green-dark);margin-top:3px"><?= h($isRtl?$b['name_ar']:$b['name_en']) ?></div>
        </div>
        <?php endforeach; ?>
        <a href="/achievements.php" style="display:flex;align-items:center;justify-content:center;padding:10px 14px;border:2px dashed var(--gray-100);border-radius:12px;color:var(--gray-500);font-size:12px;text-decoration:none;min-width:70px">
          <?= $isRtl?'المزيد':'More' ?> →
        </a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Notifications -->
  <?php if (!empty($unreadNotifs)): ?>
  <div class="card">
    <div class="card-header"><h3 class="card-title">🔔 <?= $isRtl?'إشعارات جديدة':'Notifications' ?></h3></div>
    <div class="card-body" style="padding:0">
      <?php foreach ($unreadNotifs as $n): ?>
      <div style="padding:12px 16px;border-bottom:1px solid var(--gray-50)">
        <div style="font-weight:600;font-size:13px;color:var(--green-dark)"><?= h($n['title']) ?></div>
        <div style="font-size:12px;color:var(--gray-500);margin-top:3px"><?= h(mb_substr($n['message'],0,100)) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  </div><!-- end grid -->
</main>
</div>
<?php include '../includes/footer.php'; ?>
