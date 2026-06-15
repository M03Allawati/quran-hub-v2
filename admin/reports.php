<?php
require_once __DIR__ . '/../config.php';
requireRole('admin');
$lang = $_SESSION['lang'] ?? 'en';
$isRtl = $lang === 'ar';
$pdo = getPDO();


// DATE_VALIDATE: Validate and sanitize date inputs
function validateDate($d) {
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $d)) return date("Y-m-d");
    $dt = DateTime::createFromFormat("Y-m-d", $d);
    if (!$dt) return date("Y-m-d");
    // Max range: 2 years back, 1 month forward
    $min = new DateTime("-2 years");
    $max = new DateTime("+1 month");
    if ($dt < $min) return $min->format("Y-m-d");
    if ($dt > $max) return $max->format("Y-m-d");
    return $d;
}
// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $type = $_GET['type'] ?? 'attendance';
    $from = validateDate($_GET['from'] ?? date('Y-m-01'));
    $to   = validateDate($_GET['to']   ?? date('Y-m-d'));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="quran_hub_' . $type . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

    if ($type === 'attendance') {
        fputcsv($out, ['Date','Student','Gender','Class','Status','Recorded By']);
        $rows = $pdo->prepare("SELECT a.date, s.full_name as student, s.gender, c.name_en as class, a.status, u.full_name as recorded_by FROM attendance a JOIN students s ON a.student_id=s.id JOIN classes c ON a.class_id=c.id JOIN users u ON a.recorded_by=u.id WHERE a.date BETWEEN ? AND ? ORDER BY a.date DESC");
        $rows->execute([$from, $to]);
        foreach ($rows->fetchAll() as $r) fputcsv($out, $r);
    } elseif ($type === 'progress') {
        fputcsv($out, ['Student','Class','Surah (AR)','Surah (EN)','Tajweed Level','Memorization %','Evaluation','Last Updated']);
        $rows = $pdo->query("SELECT s.full_name, c.name_en, p.surah_name_ar, p.surah_name_en, p.tajweed_level, p.memorization_pct, p.evaluation, p.updated_at FROM progress p JOIN students s ON p.student_id=s.id JOIN classes c ON p.class_id=c.id ORDER BY s.full_name");
        foreach ($rows->fetchAll() as $r) fputcsv($out, $r);
    } elseif ($type === 'students') {
        fputcsv($out, ['Name','Name (AR)','Gender','DOB','Parent','Mosque','Enrolled Date','Active']);
        $rows = $pdo->query("SELECT s.full_name, s.full_name_ar, s.gender, s.date_of_birth, u.full_name, m.name_en, s.enrollment_date, IF(s.is_active,'Yes','No') FROM students s JOIN users u ON s.parent_id=u.id JOIN mosques m ON s.mosque_id=m.id ORDER BY s.full_name");
        foreach ($rows->fetchAll() as $r) fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

$pageTitle = ($isRtl ? 'التقارير — ' : 'Reports — ') . APP_NAME;
$dateFrom = validateDate($_GET['from'] ?? date('Y-m-01'));
$dateTo   = validateDate($_GET['to']   ?? date('Y-m-d'));

// Attendance summary per class
$attByClass = $pdo->query("SELECT c.name_en, COUNT(a.id) as total, SUM(a.status='present') as present, SUM(a.status='absent') as absent, SUM(a.status='late') as late, ROUND(SUM(a.status='present')/COUNT(a.id)*100) as rate FROM attendance a JOIN classes c ON a.class_id=c.id GROUP BY c.id ORDER BY rate DESC")->fetchAll();

// Progress summary
$progSummary = $pdo->query("SELECT evaluation, COUNT(*) as cnt FROM progress GROUP BY evaluation")->fetchAll(PDO::FETCH_KEY_PAIR);

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<div class="page-header">
  <h1 class="page-title">📄 <?= $isRtl ? 'التقارير' : 'Reports' ?></h1>
  <p class="page-subtitle"><?= $isRtl ? 'تصدير وتحليل بيانات النظام' : 'Export and analyze system data' ?></p>
</div>

<!-- Date range -->
<div class="card mb-3">
  <div class="card-body">
    <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin:0;flex:1;min-width:160px">
        <label class="form-label"><?= $isRtl?'من':'From' ?></label>
        <input type="date" name="from" class="form-control" value="<?= h($dateFrom) ?>">
      </div>
      <div class="form-group" style="margin:0;flex:1;min-width:160px">
        <label class="form-label"><?= $isRtl?'إلى':'To' ?></label>
        <input type="date" name="to" class="form-control" value="<?= h($dateTo) ?>">
      </div>
      <div><button type="submit" class="btn btn-primary">🔍 <?= $isRtl?'تحديث':'Update' ?></button></div>
    </form>
  </div>
</div>

<!-- Export Cards -->
<div class="grid-3" style="margin-bottom:2rem">
  <?php
  $exports = [
    ['📋','attendance','Attendance Report','تقرير الحضور','Download complete attendance records in CSV','تحميل سجلات الحضور كاملة'],
    ['📈','progress','Progress Report','تقرير التقدم','Download all student memorization progress','تحميل تقدم حفظ جميع الطلاب'],
    ['🎓','students','Students Report','تقرير الطلاب','Download complete student registry','تحميل قائمة الطلاب الكاملة'],
  ];
  foreach ($exports as $e): ?>
  <div class="card" style="text-align:center">
    <div class="card-body" style="padding:2rem">
      <div style="font-size:2.5rem;margin-bottom:.75rem"><?= $e[0] ?></div>
      <h3 style="color:var(--green-dark);margin-bottom:.4rem"><?= $isRtl?$e[3]:$e[2] ?></h3>
      <p style="color:var(--gray-500);font-size:.85rem;margin-bottom:1.25rem"><?= $isRtl?$e[5]:$e[4] ?></p>
      <a href="/admin/reports.php?export=csv&type=<?= $e[1] ?>&from=<?= $dateFrom ?>&to=<?= $dateTo ?>" class="btn btn-gold w-full">
        ⬇️ <?= $isRtl?'تحميل CSV':'Download CSV' ?>
      </a>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Attendance by Class -->
<div class="grid-2" style="gap:1.5rem">
  <div class="card">
    <div class="card-header"><div class="card-title">📊 <?= $isRtl?'الحضور حسب الفصل':'Attendance by Class' ?></div></div>
    <div class="table-wrapper">
      <table>
        <thead><tr>
          <th><?= $isRtl?'الفصل':'Class' ?></th>
          <th><?= $isRtl?'الحاضرون':'Present' ?></th>
          <th><?= $isRtl?'الغائبون':'Absent' ?></th>
          <th><?= $isRtl?'نسبة الحضور':'Rate' ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($attByClass as $ac): ?>
        <tr>
          <td><strong><?= h($ac['name_en']) ?></strong></td>
          <td><span class="badge badge-green"><?= $ac['present'] ?></span></td>
          <td><span class="badge badge-red"><?= $ac['absent'] ?></span></td>
          <td>
            <div style="display:flex;align-items:center;gap:.5rem">
              <div class="progress-bar-wrapper" style="flex:1;height:8px">
                <div class="progress-bar-fill" style="width:<?= $ac['rate'] ?>%"></div>
              </div>
              <span style="font-size:.82rem;font-weight:700;color:var(--green-dark)"><?= $ac['rate'] ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Progress Summary -->
  <div class="card">
    <div class="card-header"><div class="card-title">🏆 <?= $isRtl?'ملخص التقييمات':'Evaluation Summary' ?></div></div>
    <div class="card-body">
      <?php
      $evals = [
        'Excellent'         => ['badge-green', '⭐⭐⭐', $isRtl?'ممتاز':'Excellent'],
        'Good'              => ['badge-blue',  '⭐⭐',   $isRtl?'جيد':'Good'],
        'Needs Improvement' => ['badge-warning','⭐',    $isRtl?'يحتاج تحسين':'Needs Improvement'],
        'Repeat'            => ['badge-red',   '🔄',    $isRtl?'إعادة':'Repeat'],
      ];
      $totalProg = array_sum($progSummary) ?: 1;
      foreach ($evals as $key => [$badge, $stars, $label]): ?>
      <div style="margin-bottom:1.1rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.35rem">
          <div>
            <?= $stars ?> <span class="badge <?= $badge ?>"><?= $label ?></span>
          </div>
          <strong><?= $progSummary[$key] ?? 0 ?></strong>
        </div>
        <div class="progress-bar-wrapper">
          <div class="progress-bar-fill <?= in_array($badge,['badge-warning','badge-red'])?'gold':'' ?>"
            style="width:<?= round(($progSummary[$key]??0)/$totalProg*100) ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

</main>
</div>
<?php include '../includes/footer.php'; ?>
