<?php
require_once '/var/www/html/config.php';
requireRole('student');
$lang   = $_SESSION['lang'] ?? 'en';
$isRtl  = $lang === 'ar';
$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$pageTitle = ($isRtl ? 'برامجي — ' : 'My Programs — ') . APP_NAME;

// Get student record
$student = $pdo->prepare(
    "SELECT s.*, m.governorate, m.name_en as mosque_en, m.name_ar as mosque_ar
     FROM students s JOIN mosques m ON m.id=s.mosque_id
     WHERE s.user_id=? LIMIT 1"
);
$student->execute([$userId]);
$student = $student->fetch();
$sid    = $student['id']          ?? 0;
$myGov  = $student['governorate'] ?? '';
$myMid  = $student['mosque_id']   ?? 0;

// My enrolled programs
$myProgs = $pdo->prepare(
    "SELECT pe.*, mp.name_en, mp.name_ar, mp.slot, mp.days, mp.time_start, mp.time_end,
            mp.program_type, mp.max_students,
            m.name_en as mosque_en, m.name_ar as mosque_ar, m.governorate,
            u.full_name as teacher_name,
            (SELECT COUNT(*) FROM program_enrollments pe2 WHERE pe2.program_id=mp.id AND pe2.status='active') as enrolled
     FROM program_enrollments pe
     JOIN mosque_programs mp ON mp.id=pe.program_id
     JOIN mosques m ON m.id=mp.mosque_id
     LEFT JOIN users u ON u.id=mp.teacher_id
     WHERE pe.student_id=?
     ORDER BY mp.slot"
);
$myProgs->execute([$sid]);
$myProgs = $myProgs->fetchAll();
$myProgIds = array_column($myProgs, 'program_id');

// Available programs in my mosque — Slot A only for students
$availProgs = $pdo->prepare(
    "SELECT mp.*,
            u.full_name as teacher_name,
            (SELECT COUNT(*) FROM program_enrollments pe WHERE pe.program_id=mp.id AND pe.status='active') as enrolled
     FROM mosque_programs mp
     LEFT JOIN users u ON u.id=mp.teacher_id
     WHERE mp.mosque_id=? AND mp.is_active=1 AND mp.target_type='student'
     ORDER BY mp.slot"
);
$availProgs->execute([$myMid]);
$availProgs = $availProgs->fetchAll();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'enroll' && $sid) {
        $progId = (int)$_POST['program_id'];
        if ($progId) {
            $pdo->prepare("INSERT IGNORE INTO program_enrollments (program_id,student_id,status) VALUES (?,?,'active')")
                ->execute([$progId,$sid]);

            // Notify teacher
            $prog = $pdo->prepare("SELECT teacher_id, name_en FROM mosque_programs WHERE id=?");
            $prog->execute([$progId]);
            $prog = $prog->fetch();
            if ($prog && $prog['teacher_id']) {
                $pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'info')")
                    ->execute([$prog['teacher_id'],"New Student",$student['full_name']." joined: ".$prog['name_en']]);
            }
            setFlash('success', $isRtl?'✅ تم التسجيل في البرنامج':'✅ Enrolled successfully!');
        }
        header('Location: /student/programs.php'); exit;
    }

    if ($action === 'drop' && $sid) {
        $progId = (int)$_POST['program_id'];
        $pdo->prepare("UPDATE program_enrollments SET status='dropped' WHERE program_id=? AND student_id=?")
            ->execute([$progId,$sid]);
        setFlash('info', $isRtl?'تم الانسحاب من البرنامج':'Dropped from program');
        header('Location: /student/programs.php'); exit;
    }
}

include '/var/www/html/includes/header.php';
?>
<div class="layout">
<?php include '/var/www/html/includes/sidebar.php'; ?>
<main class="main-content">

<h1 style="font-size:1.5rem;font-weight:800;margin:0 0 4px">📚 <?= $isRtl?'برامج القرآن':'Quran Programs' ?></h1>
<p style="color:var(--gray-500);font-size:13px;margin:0 0 24px">
  🕌 <?= h($isRtl?$student['mosque_ar']:$student['mosque_en']) ?> &nbsp;·&nbsp; 📍 <?= h($myGov) ?>
</p>

<!-- My Programs -->
<h2 style="font-size:1rem;font-weight:700;color:var(--green-dark);margin:0 0 14px">
  ✅ <?= $isRtl?'برامجي المسجّلة':'My Enrolled Programs' ?> (<?= count($myProgs) ?>)
</h2>

<?php if (empty($myProgs)): ?>
<div style="background:var(--gold-pale);border:1px solid var(--gold-light);border-radius:12px;padding:20px;margin-bottom:24px;text-align:center">
  <div style="font-size:2rem">📚</div>
  <div style="margin-top:8px;font-size:14px;color:var(--gold-dark);font-weight:600"><?= $isRtl?'لم تسجّل في أي برنامج بعد':'Not enrolled in any program yet' ?></div>
  <div style="font-size:12px;color:var(--gray-500);margin-top:4px"><?= $isRtl?'اختر برنامجاً من القائمة أدناه':'Choose a program from below' ?></div>
</div>
<?php else: foreach ($myProgs as $p): ?>
<div style="background:#fff;border:2px solid var(--green-main);border-radius:14px;padding:18px 20px;margin-bottom:12px">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
    <div style="flex:1">
      <div style="font-weight:800;font-size:15px;color:var(--green-dark)">
        🎓 <?= $isRtl?'برنامج الطلاب — Slot A':'Students Program — Slot A' ?>
      </div>
      <div style="font-size:12px;color:var(--gray-400);margin-top:2px"><?= h($isRtl?$p['name_ar']:$p['name_en']) ?></div>
      <div style="font-size:12px;color:var(--gray-500);margin-top:4px">
        🕌 <?= h($isRtl?$p['mosque_ar']:$p['mosque_en']) ?>
        <?php if ($p['teacher_name']): ?>
        &nbsp;·&nbsp; 👨‍🏫 <?= h($p['teacher_name']) ?>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;font-size:12px">
        <span style="background:var(--green-pale);color:var(--green-dark);padding:2px 8px;border-radius:99px;font-weight:600"><?=h($p['program_type'])?></span>
        <span>📅 <?= str_replace(',', ' / ', $p['days']) ?></span>
        <span>⏰ <?= substr($p['time_start'],0,5) ?>–<?= substr($p['time_end'],0,5) ?></span>
        <span>👥 <?= $p['enrolled'] ?>/<?= $p['max_students'] ?></span>
      </div>
    </div>
    <form method="POST" onsubmit="return confirm('<?= $isRtl?'الانسحاب من البرنامج؟':'Drop this program?' ?>
        <?= csrfField() ?>')">
      <input type="hidden" name="action" value="drop">
      <input type="hidden" name="program_id" value="<?=$p['program_id']?>">
      <button type="submit" class="btn btn-secondary btn-sm" style="color:var(--danger);font-size:12px">
        🚪 <?= $isRtl?'انسحاب':'Drop' ?>
      </button>
    </form>
  </div>
</div>
<?php endforeach; endif; ?>

<!-- Available programs -->
<h2 style="font-size:1rem;font-weight:700;color:var(--green-dark);margin:24px 0 14px">
  🔍 <?= $isRtl?'البرامج المتاحة في مسجدي':'Available Programs in My Mosque' ?>
</h2>

<?php foreach ($availProgs as $p):
  $enrolled = in_array($p['id'], $myProgIds);
  $full = $p['enrolled'] >= $p['max_students'];
?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:18px 20px;margin-bottom:10px;opacity:<?= $full&&!$enrolled?0.7:1 ?>">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div style="flex:1">
      <div style="font-weight:700;font-size:14px;color:var(--green-dark)">
        🎓 <?= $isRtl?'برنامج الطلاب — Slot A':'Students Program — Slot A' ?>
      </div>
      <div style="font-size:11px;color:var(--gray-400)"><?= h($isRtl?$p['name_ar']:$p['name_en']) ?></div>
      <div style="font-size:12px;color:var(--gray-500);margin-top:3px">
        📅 <?= str_replace(',', ' / ', $p['days']) ?> &nbsp;·&nbsp;
        ⏰ <?= substr($p['time_start'],0,5) ?>–<?= substr($p['time_end'],0,5) ?>
        <?php if ($p['teacher_name']): ?>
        &nbsp;·&nbsp; 👨‍🏫 <?= h($p['teacher_name']) ?>
        <?php else: ?>
        &nbsp;·&nbsp; <span style="color:var(--warning);font-size:11px">⏳ <?= $isRtl?'في انتظار معلم':'Awaiting teacher' ?></span>
        <?php endif; ?>
      </div>
      <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;font-size:11px">
        <span style="background:var(--green-pale);color:var(--green-dark);padding:2px 8px;border-radius:99px;font-weight:600"><?=h($p['program_type'])?></span>
        <span style="background:var(--info-pale);color:var(--info);padding:2px 8px;border-radius:99px">Slot <?=$p['slot']?></span>
        <span style="color:var(--gray-500)">👥 <?=$p['enrolled']?>/<?=$p['max_students']?></span>
        <?php if ($full && !$enrolled): ?>
        <span style="background:var(--danger-pale);color:var(--danger);padding:2px 8px;border-radius:99px;font-weight:600"><?=$isRtl?'ممتلئ':'Full'?></span>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($enrolled): ?>
    <span style="background:var(--success-pale);color:var(--success);padding:6px 14px;border-radius:99px;font-size:12px;font-weight:700">✅ <?=$isRtl?'مسجّل':'Enrolled'?></span>
    <?php elseif (!$full): ?>
    <form method="POST">
        <?= csrfField() ?>
      <input type="hidden" name="action" value="enroll">
      <input type="hidden" name="program_id" value="<?=$p['id']?>">
      <button type="submit" class="btn btn-primary btn-sm">➕ <?=$isRtl?'تسجيل':'Enroll'?></button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

</main>
</div>
<?php include '/var/www/html/includes/footer.php'; ?>
