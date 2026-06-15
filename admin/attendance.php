<?php
require_once __DIR__ . '/../config.php';
requireRole('admin');
$lang = $_SESSION['lang'] ?? 'en';
$isRtl = $lang === 'ar';
$pdo = getPDO();
$pageTitle = ($isRtl ? 'سجلات الحضور — ' : 'Attendance Records — ') . APP_NAME;

// ATT_DATE_VALIDATE
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$dateTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
$classFilter = (int)($_GET['class'] ?? 0);

$sql = "SELECT a.*, s.full_name as student_name, s.gender, c.name_en as class_name, u.full_name as recorded_by_name FROM attendance a JOIN students s ON a.student_id=s.id JOIN classes c ON a.class_id=c.id JOIN users u ON a.recorded_by=u.id WHERE a.date BETWEEN ? AND ?";
$params = [$dateFrom, $dateTo];
if ($classFilter) { $sql .= " AND a.class_id=?"; $params[] = $classFilter; }
$sql .= " ORDER BY a.date DESC, s.full_name";
$st = $pdo->prepare($sql); $st->execute($params);
$records = $st->fetchAll();

// Summary stats
$summary = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
foreach ($records as $r) $summary[$r['status']] = ($summary[$r['status']]??0)+1;
$total = array_sum($summary);
$rate  = $total > 0 ? round($summary['present']/$total*100) : 0;

$classes = $pdo->query("SELECT id,name_en FROM classes WHERE is_active=1 ORDER BY name_en")->fetchAll();

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<div class="page-header">
  <h1 class="page-title">✅ <?= $isRtl ? 'سجلات الحضور' : 'Attendance Records' ?></h1>
  <p class="page-subtitle"><?= $isRtl ? 'مراقبة حضور جميع الطلاب' : 'Monitor attendance across all classes' ?></p>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body">
    <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin:0;flex:1;min-width:160px">
        <label class="form-label"><?= $isRtl ? 'من تاريخ' : 'Date From' ?></label>
        <input type="date" name="from" class="form-control" value="<?= h($dateFrom) ?>">
      </div>
      <div class="form-group" style="margin:0;flex:1;min-width:160px">
        <label class="form-label"><?= $isRtl ? 'إلى تاريخ' : 'Date To' ?></label>
        <input type="date" name="to" class="form-control" value="<?= h($dateTo) ?>">
      </div>
      <div class="form-group" style="margin:0;flex:2;min-width:200px">
        <label class="form-label"><?= $isRtl ? 'الفصل' : 'Class' ?></label>
        <select name="class" class="form-control form-select">
          <option value="0"><?= $isRtl ? 'جميع الفصول' : 'All Classes' ?></option>
          <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classFilter==$c['id']?'selected':'' ?>><?= h($c['name_en']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><button type="submit" class="btn btn-primary">🔍 <?= $isRtl ? 'بحث' : 'Filter' ?></button></div>
    </form>
  </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid" style="margin-bottom:1.5rem">
  <div class="stat-card"><div class="stat-icon green">✅</div><div><div class="stat-value" style="color:var(--success)"><?= $summary['present'] ?></div><div class="stat-label"><?= $isRtl?'حاضر':'Present' ?></div></div></div>
  <div class="stat-card"><div class="stat-icon red">❌</div><div><div class="stat-value" style="color:var(--danger)"><?= $summary['absent'] ?></div><div class="stat-label"><?= $isRtl?'غائب':'Absent' ?></div></div></div>
  <div class="stat-card"><div class="stat-icon gold">⏰</div><div><div class="stat-value" style="color:var(--warning)"><?= $summary['late'] ?></div><div class="stat-label"><?= $isRtl?'متأخر':'Late' ?></div></div></div>
  <div class="stat-card"><div class="stat-icon blue">📋</div><div><div class="stat-value" style="color:var(--info)"><?= $rate ?>%</div><div class="stat-label"><?= $isRtl?'نسبة الحضور':'Attendance Rate' ?></div></div></div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">📋 <?= $isRtl ? 'السجلات التفصيلية' : 'Detailed Records' ?> (<?= count($records) ?>)</div>
    <a href="/admin/reports.php?type=attendance&from=<?= $dateFrom ?>&to=<?= $dateTo ?>" class="btn btn-gold btn-sm">📄 <?= $isRtl?'تصدير':'Export' ?></a>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr>
        <th><?= $isRtl?'التاريخ':'Date' ?></th>
        <th><?= $isRtl?'الطالب':'Student' ?></th>
        <th><?= $isRtl?'الفصل':'Class' ?></th>
        <th><?= $isRtl?'الحالة':'Status' ?></th>
        <th><?= $isRtl?'سجّله':'Recorded By' ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($records as $r): ?>
      <tr>
        <td style="font-size:.88rem;font-weight:600"><?= date('d M Y', strtotime($r['date'])) ?></td>
        <td>
          <span style="display:flex;align-items:center;gap:.5rem">
            <span style="width:28px;height:28px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-size:.8rem">
              <?= $r['gender']==='male'?'👦':'👧' ?>
            </span>
            <?= h($r['student_name']) ?>
          </span>
        </td>
        <td style="font-size:.85rem"><?= h($r['class_name']) ?></td>
        <td>
          <span class="badge badge-<?= $r['status']==='present'?'green':($r['status']==='absent'?'red':($r['status']==='late'?'warning':'blue')) ?>">
            <?= match($r['status']) {
              'present' => $isRtl?'حاضر':'Present',
              'absent'  => $isRtl?'غائب':'Absent',
              'late'    => $isRtl?'متأخر':'Late',
              'excused' => $isRtl?'معذور':'Excused',
              default   => $r['status']
            } ?>
          </span>
        </td>
        <td style="font-size:.82rem;color:var(--gray-500)"><?= h($r['recorded_by_name']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$records): ?>
      <tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--gray-500)"><?= $isRtl?'لا توجد سجلات في هذه الفترة':'No records found for this period' ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</main>
</div>
<?php include '../includes/footer.php'; ?>
