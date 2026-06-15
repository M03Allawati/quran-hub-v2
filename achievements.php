<?php
require_once __DIR__ . '/config.php';
requireLogin();
$lang   = $_SESSION['lang'] ?? 'en';
$isRtl  = $lang === 'ar';
$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'];
$pageTitle = ($isRtl ? 'الإنجازات والتميز — ' : 'Achievements — ') . APP_NAME;

function awardPoints(PDO $pdo, int $studentId, int $pts): void {
    $pdo->prepare("INSERT INTO student_points (student_id, total_points, level) VALUES (?,?,1)
        ON DUPLICATE KEY UPDATE total_points = total_points + ?,
        level = CASE WHEN total_points+? < 50 THEN 1 WHEN total_points+? < 150 THEN 2
                     WHEN total_points+? < 350 THEN 3 WHEN total_points+? < 700 THEN 4 ELSE 5 END")
        ->execute([$studentId, $pts, $pts, $pts, $pts, $pts, $pts]);
}

function checkBadges(PDO $pdo, int $studentId): void {
    $badges = $pdo->query("SELECT * FROM badges WHERE is_active=1")->fetchAll();
    $have   = array_column((function($p,$sid){ $s=$p->prepare('SELECT badge_id FROM student_badges WHERE student_id=?'); $s->execute([$sid]); return $s->fetchAll(); })($pdo,$studentId), 'badge_id');
    foreach ($badges as $b) {
        if (in_array($b['id'], $have)) continue;
        $ok = false;
        switch ($b['condition_type']) {
            case 'surah_completed':
                $c = $pdo->prepare("SELECT COUNT(*) FROM progress WHERE student_id=? AND memorization_pct=100");
                $c->execute([$studentId]); $ok = $c->fetchColumn() >= $b['condition_value']; break;
            case 'tajweed_5':
                $c = $pdo->prepare("SELECT COUNT(*) FROM progress WHERE student_id=? AND tajweed_level='5'");
                $c->execute([$studentId]); $ok = $c->fetchColumn() >= 1; break;
            case 'streak_30':
                $c = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id=? AND status='present' AND date >= DATE_SUB(CURDATE(),INTERVAL 30 DAY)");
                $c->execute([$studentId]); $ok = $c->fetchColumn() >= 30; break;
            case 'excellent_x10':
                $c = $pdo->prepare("SELECT COUNT(*) FROM progress WHERE student_id=? AND evaluation='Excellent'");
                $c->execute([$studentId]); $ok = $c->fetchColumn() >= 10; break;
            case 'juz30_complete':
                $juz30 = [78,79,80,81,82,83,84,85,86,87,88,89,90,91,92,93,94,95,96,97,98,99,100,101,102,103,104,105,106,107,108,109,110,111,112,113,114];
                $ph = implode(',', array_fill(0, count($juz30), '?'));
                $c = $pdo->prepare("SELECT COUNT(*) FROM progress WHERE student_id=? AND surah_number IN ($ph) AND memorization_pct=100");
                $c->execute(array_merge([$studentId], $juz30)); $ok = $c->fetchColumn() >= count($juz30); break;
            case 'messages_sent':
                $c = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id=?");
                $c->execute([$studentId]); $ok = $c->fetchColumn() >= $b['condition_value']; break;
        }
        if ($ok) {
            $pdo->prepare("INSERT IGNORE INTO student_badges (student_id,badge_id) VALUES (?,?)")->execute([$studentId,$b['id']]);
            if ($b['points'] > 0) awardPoints($pdo, $studentId, $b['points']);
        }
    }
}

// Get relevant students
$myStudents = [];
if ($role === 'parent') {
    $s = $pdo->prepare("SELECT id, full_name, full_name_ar FROM students WHERE parent_id=? AND is_active=1");
    $s->execute([$userId]); $myStudents = $s->fetchAll();
} elseif ($role === 'teacher') {
    $s = $pdo->prepare("SELECT DISTINCT s.id, s.full_name, s.full_name_ar FROM students s JOIN enrollments e ON e.student_id=s.id JOIN classes c ON c.id=e.class_id WHERE c.teacher_id=? AND e.status='active'");
    $s->execute([$userId]); $myStudents = $s->fetchAll();
} elseif ($role === 'student') {
    $s = $pdo->prepare("SELECT id, full_name, full_name_ar FROM students WHERE user_id=? AND is_active=1");
    $s->execute([$userId]); $myStudents = $s->fetchAll();
} elseif ($role === 'admin') {
    $myStudents = $pdo->query("SELECT id, full_name, full_name_ar FROM students WHERE is_active=1 ORDER BY full_name LIMIT 30")->fetchAll();
}

foreach ($myStudents as $s) { checkBadges($pdo, $s['id']); }

$allBadges = $pdo->query("SELECT * FROM badges WHERE is_active=1 ORDER BY points")->fetchAll();
$govs      = $pdo->query("SELECT DISTINCT governorate FROM mosques ORDER BY governorate")->fetchAll(PDO::FETCH_COLUMN);

$tab     = $_GET['tab'] ?? 'badges';
$lbGov   = trim($_GET['gov'] ?? '');

// Leaderboard
$lbWhere  = '1=1';
$lbParams = [];
if ($lbGov) { $lbWhere = 'm.governorate=?'; $lbParams[] = $lbGov; }
$lb = $pdo->prepare("SELECT s.id, s.full_name, s.full_name_ar,
    m.name_en as mosque_en, m.name_ar as mosque_ar, m.governorate,
    COALESCE(sp.total_points,0) as total_points, COALESCE(sp.level,1) as level,
    (SELECT COUNT(*) FROM student_badges sb WHERE sb.student_id=s.id) as badge_count,
    (SELECT COUNT(*) FROM progress p WHERE p.student_id=s.id AND p.memorization_pct=100) as surahs_done
    FROM students s LEFT JOIN student_points sp ON sp.student_id=s.id
    LEFT JOIN mosques m ON m.id=s.mosque_id
    WHERE s.is_active=1 AND $lbWhere
    ORDER BY total_points DESC LIMIT 50");
$lb->execute($lbParams);
$leaderboard = $lb->fetchAll();

$levelNames = [1=>($isRtl?'مبتدئ':'Beginner'),2=>($isRtl?'متقدم':'Intermediate'),3=>($isRtl?'محترف':'Proficient'),4=>($isRtl?'خبير':'Expert'),5=>($isRtl?'حافظ':'Hafiz')];
$levelColors = [1=>'#94a3b8',2=>'#3b82f6',3=>'#8b5cf6',4=>'#f59e0b',5=>'#2D6A4F'];
$medals = ['🥇','🥈','🥉'];

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">

<h1 style="font-size:1.5rem;font-weight:800;margin:0 0 4px">🏆 <?= $isRtl?'الإنجازات والتميز':'Achievements & Leaderboard' ?></h1>
<p style="color:var(--gray-500);font-size:13px;margin:0 0 20px"><?= $isRtl?'الأوسمة والنقاط ولوحة الشرف الوطنية':'Badges, points & national honour board' ?></p>

<!-- Tabs -->
<div style="display:flex;background:var(--gray-50);border-radius:12px;padding:4px;margin-bottom:24px;width:fit-content">
  <?php foreach ([['badges','🏅 '.($isRtl?'الأوسمة':'Badges')],['leaderboard','🏆 '.($isRtl?'لوحة الشرف':'Leaderboard')]] as [$k,$l]): ?>
  <a href="?tab=<?= $k ?>" style="padding:8px 20px;border-radius:10px;font-size:14px;text-decoration:none;background:<?= $tab===$k?'#fff':'transparent' ?>;font-weight:<?= $tab===$k?700:400 ?>;color:<?= $tab===$k?'var(--green-dark)':'var(--gray-500)' ?>;box-shadow:<?= $tab===$k?'var(--shadow-sm)':'' ?>;transition:all .2s">
    <?= $l ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'badges'): ?>
<!-- BADGES TAB -->
<?php if (empty($myStudents)): ?>
<div style="text-align:center;padding:60px;color:var(--gray-300)">
  <div style="font-size:48px">🏅</div>
  <div style="margin-top:12px"><?= $isRtl?'لا توجد بيانات':'No student data available' ?></div>
</div>
<?php else: foreach ($myStudents as $student):
  $eBadges = $pdo->prepare("SELECT b.*, sb.earned_at FROM student_badges sb JOIN badges b ON b.id=sb.badge_id WHERE sb.student_id=? ORDER BY sb.earned_at DESC");
  $eBadges->execute([$student['id']]); $earned = $eBadges->fetchAll();
  $earnedIds = array_column($earned,'id');
  $pts = $pdo->prepare("SELECT total_points, level FROM student_points WHERE student_id=?");
  $pts->execute([$student['id']]); $ptRow = $pts->fetch() ?: ['total_points'=>0,'level'=>1];
  $thresholds = [0,50,150,350,700,9999];
  $pct = $ptRow['level'] < 5 ? min(100, round(($ptRow['total_points'] - $thresholds[$ptRow['level']-1]) / ($thresholds[$ptRow['level']] - $thresholds[$ptRow['level']-1]) * 100)) : 100;
?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:var(--radius-lg);padding:20px;margin-bottom:20px">
  <!-- Student header -->
  <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--gray-50)">
    <div style="width:50px;height:50px;border-radius:50%;background:<?= $levelColors[$ptRow['level']] ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;font-weight:700;flex-shrink:0">
      <?= strtoupper(substr($student['full_name'],0,1)) ?>
    </div>
    <div style="flex:1">
      <div style="font-weight:700;font-size:16px;color:var(--green-dark)"><?= h($isRtl&&$student['full_name_ar']?$student['full_name_ar']:$student['full_name']) ?></div>
      <div style="font-size:12px;color:var(--gray-500)"><?= $levelNames[$ptRow['level']] ?> · <?= $ptRow['total_points'] ?> <?= $isRtl?'نقطة':'pts' ?></div>
    </div>
    <div style="text-align:center">
      <div style="font-size:1.6rem;font-weight:800;color:var(--gold-dark)"><?= count($earned) ?>/<?= count($allBadges) ?></div>
      <div style="font-size:11px;color:var(--gray-500)"><?= $isRtl?'وسام':'badges' ?></div>
    </div>
  </div>
  <!-- Level progress -->
  <div style="margin-bottom:16px">
    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--gray-500);margin-bottom:4px">
      <span><?= $isRtl?'المستوى':'Level' ?> <?= $ptRow['level'] ?> — <?= $levelNames[$ptRow['level']] ?></span>
      <span><?= $isRtl?'المستوى':'Level' ?> <?= min(5,$ptRow['level']+1) ?></span>
    </div>
    <div style="height:8px;background:var(--gray-100);border-radius:4px;overflow:hidden">
      <div style="height:100%;width:<?= $pct ?>%;background:<?= $levelColors[$ptRow['level']] ?>;border-radius:4px;transition:width .5s"></div>
    </div>
  </div>
  <!-- Badges grid -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px">
    <?php foreach ($allBadges as $badge):
      $isEarned = in_array($badge['id'], $earnedIds);
      $earnedRow = $isEarned ? array_values(array_filter($earned, fn($e)=>$e['id']==$badge['id']))[0] : null;
    ?>
    <div style="border:2px solid <?= $isEarned?$badge['color']:'var(--gray-100)' ?>;border-radius:12px;padding:14px 12px;text-align:center;opacity:<?= $isEarned?1:0.45 ?>;transition:all .2s;background:<?= $isEarned?'rgba(212,160,23,.05)':'#fff' ?>">
      <div style="font-size:2rem;margin-bottom:6px"><?= $badge['icon'] ?></div>
      <div style="font-weight:700;font-size:12px;color:var(--green-dark);margin-bottom:3px"><?= h($isRtl?$badge['name_ar']:$badge['name_en']) ?></div>
      <div style="font-size:11px;color:var(--gray-500);margin-bottom:6px;line-height:1.4"><?= h(mb_substr($badge['description_ar']??'',0,55)) ?></div>
      <span style="font-size:11px;font-weight:700;color:var(--gold-dark)">⭐ <?= $badge['points'] ?> pts</span>
      <?php if ($isEarned && $earnedRow): ?>
      <div style="margin-top:6px;font-size:10px;color:var(--success);font-weight:600">✅ <?= date('Y/m/d', strtotime($earnedRow['earned_at'])) ?></div>
      <?php else: ?>
      <div style="margin-top:6px;font-size:10px;color:var(--gray-300)">🔒 <?= $isRtl?'لم تحصل عليه':'Not earned' ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; endif; ?>

<?php else: ?>
<!-- LEADERBOARD TAB -->
<div style="display:flex;gap:12px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
  <label style="font-weight:600;font-size:13px"><?= $isRtl?'فلتر:':'Filter:' ?></label>
  <form method="GET" style="display:flex;gap:8px;align-items:center">
    <input type="hidden" name="tab" value="leaderboard">
    <select name="gov" onchange="this.form.submit()" style="padding:7px 12px;border:1px solid var(--gray-100);border-radius:8px;font-size:13px;font-family:inherit;background:#fff">
      <option value=""><?= $isRtl?'كل المحافظات':'All Governorates' ?></option>
      <?php foreach ($govs as $g): ?>
      <option value="<?= h($g) ?>" <?= $lbGov===$g?'selected':'' ?>><?= h($g) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<div style="background:#fff;border:1px solid var(--gray-100);border-radius:var(--radius-lg);overflow:hidden">
  <?php if (empty($leaderboard)): ?>
  <div style="padding:60px;text-align:center;color:var(--gray-300)">
    <div style="font-size:40px">🏆</div>
    <div style="margin-top:12px"><?= $isRtl?'لا توجد بيانات':'No data yet' ?></div>
  </div>
  <?php else: foreach ($leaderboard as $i => $entry): ?>
  <div style="display:flex;align-items:center;gap:14px;padding:14px 20px;border-bottom:1px solid var(--gray-50)">
    <div style="width:36px;text-align:center;font-size:1.2rem;font-weight:800;flex-shrink:0">
      <?= $i < 3 ? $medals[$i] : "<span style='color:var(--gray-300);font-size:14px'>#".($i+1)."</span>" ?>
    </div>
    <div style="width:42px;height:42px;border-radius:50%;background:<?= $levelColors[$entry['level']] ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1.1rem;flex-shrink:0">
      <?= strtoupper(substr($entry['full_name'],0,1)) ?>
    </div>
    <div style="flex:1">
      <div style="font-weight:700;font-size:14px;color:var(--green-dark)"><?= h($isRtl&&$entry['full_name_ar']?$entry['full_name_ar']:$entry['full_name']) ?></div>
      <div style="font-size:11px;color:var(--gray-500);display:flex;gap:10px;flex-wrap:wrap;margin-top:2px">
        <span>🕌 <?= h($isRtl?($entry['mosque_ar']??$entry['mosque_en']):$entry['mosque_en']) ?></span>
        <span>📍 <?= h($entry['governorate']??'—') ?></span>
        <span>🏅 <?= $entry['badge_count'] ?> <?= $isRtl?'وسام':'badges' ?></span>
        <span>📖 <?= $entry['surahs_done'] ?> <?= $isRtl?'سورة':'surahs' ?></span>
      </div>
    </div>
    <div style="text-align:center;min-width:60px">
      <div style="font-size:1.3rem;font-weight:800;color:<?= $i<3?'var(--gold-dark)':'var(--green-main)' ?>"><?= number_format($entry['total_points']) ?></div>
      <div style="font-size:10px;color:var(--gray-300)"><?= $isRtl?'نقطة':'pts' ?></div>
    </div>
  </div>
  <?php endforeach; endif; ?>
</div>
<?php endif; ?>

</main>
</div>
<?php include 'includes/footer.php'; ?>
