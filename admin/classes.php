<?php
require_once __DIR__ . '/../config.php';
requireRole('admin');
$lang = $_SESSION['lang'] ?? 'en';
$isRtl = $lang === 'ar';
$pdo = getPDO();
$pageTitle = ($isRtl ? 'إدارة الفصول — ' : 'Manage Classes — ') . APP_NAME;

// Handle create/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if ($_POST['action'] === 'create') {
        $pdo->prepare("INSERT INTO classes (name_en,name_ar,subject,teacher_id,mosque_id,level,schedule_day,time_start,time_end,max_students) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$_POST['name_en'],$_POST['name_ar'],$_POST['subject'],(int)$_POST['teacher_id'],(int)$_POST['mosque_id'],$_POST['level'],implode(',',$_POST['days']??[]),$_POST['time_start'],$_POST['time_end'],(int)$_POST['max_students']]);
        setFlash('success', $isRtl ? 'تم إنشاء الفصل بنجاح!' : 'Class created successfully!');
    } elseif ($_POST['action'] === 'delete') {
        $pdo->prepare("UPDATE classes SET is_active=0 WHERE id=?")->execute([(int)$_POST['id']]);
        setFlash('success', $isRtl ? 'تم حذف الفصل' : 'Class deleted');
    }
    header('Location: /admin/classes.php');
    exit;
}

$classes = $pdo->query("SELECT c.*, u.full_name as teacher_name, m.name_en as mosque_name, COUNT(e.id) as enrolled FROM classes c JOIN users u ON c.teacher_id=u.id JOIN mosques m ON c.mosque_id=m.id LEFT JOIN enrollments e ON c.id=e.class_id AND e.status='active' WHERE c.is_active=1 GROUP BY c.id ORDER BY c.created_at DESC")->fetchAll();
$teachers = $pdo->query("SELECT id, full_name FROM users WHERE role='teacher' AND is_active=1 ORDER BY full_name")->fetchAll();
$mosques  = $pdo->query("SELECT id, name_en FROM mosques WHERE is_active=1 ORDER BY name_en")->fetchAll();

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<div class="page-header flex-center justify-between">
  <div>
    <h1 class="page-title">📚 <?= $isRtl ? 'إدارة الفصول' : 'Manage Classes' ?></h1>
    <p class="page-subtitle"><?= count($classes) ?> <?= $isRtl ? 'فصل نشط' : 'active classes' ?></p>
  </div>
  <button onclick="document.getElementById('createModal').style.display='flex'" class="btn btn-primary">
    + <?= $isRtl ? 'فصل جديد' : 'New Class' ?>
  </button>
</div>

<!-- Classes table -->
<div class="card">
  <div class="table-wrapper">
    <table>
      <thead><tr>
        <th><?= $isRtl ? 'اسم الفصل' : 'Class Name' ?></th>
        <th><?= $isRtl ? 'المادة' : 'Subject' ?></th>
        <th><?= $isRtl ? 'المعلم' : 'Teacher' ?></th>
        <th><?= $isRtl ? 'المسجد' : 'Mosque' ?></th>
        <th><?= $isRtl ? 'المستوى' : 'Level' ?></th>
        <th><?= $isRtl ? 'الجدول' : 'Schedule' ?></th>
        <th><?= $isRtl ? 'الطلاب' : 'Students' ?></th>
        <th><?= $isRtl ? 'إجراء' : 'Action' ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($classes as $c): ?>
      <tr>
        <td><strong><?= h($c['name_en']) ?></strong><?php if($c['name_ar']): ?><br><small style="font-family:var(--font-ar);color:var(--gray-500)"><?= h($c['name_ar']) ?></small><?php endif; ?></td>
        <td><span class="badge badge-green"><?= h($c['subject']) ?></span></td>
        <td><?= h($c['teacher_name']) ?></td>
        <td><?= h($c['mosque_name']) ?></td>
        <td><span class="badge badge-<?= $c['level']==='Advanced'?'red':($c['level']==='Intermediate'?'blue':'gray') ?>"><?= h($c['level']) ?></span></td>
        <td style="font-size:.82rem"><?= h(str_replace(',', ', ', $c['schedule_day'])) ?><br><span style="color:var(--green-main)"><?= substr($c['time_start'],0,5) ?>–<?= substr($c['time_end'],0,5) ?></span></td>
        <td><span class="badge badge-blue"><?= $c['enrolled'] ?>/<?= $c['max_students'] ?></span></td>
        <td>
          <form method="POST" onsubmit="return confirm('<?= $isRtl ? 'هل أنت متأكد؟' : 'Are you sure?' ?>
        <?= csrfField() ?>')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">🗑</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create Modal -->
<div id="createModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:1rem">
  <div style="background:#fff;border-radius:var(--radius-lg);width:100%;max-width:600px;max-height:90vh;overflow-y:auto">
    <div style="padding:1.5rem;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center">
      <h3 style="color:var(--green-dark)">➕ <?= $isRtl ? 'إنشاء فصل جديد' : 'Create New Class' ?></h3>
      <button onclick="document.getElementById('createModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer">×</button>
    </div>
    <form method="POST" style="padding:1.5rem">
        <?= csrfField() ?>
      <input type="hidden" name="action" value="create">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Class Name (EN)</label>
          <input type="text" name="name_en" class="form-control" required placeholder="e.g. Quran Memorization - Beginners">
        </div>
        <div class="form-group">
          <label class="form-label">اسم الفصل (AR)</label>
          <input type="text" name="name_ar" class="form-control" placeholder="مثال: تحفيظ القرآن - مبتدئون" dir="rtl">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Subject</label>
          <select name="subject" class="form-control form-select" required>
            <?php foreach(['Quran Memorization','Tajweed','Fiqh','Dua & Dhikr','Arabic','Tafsir','Hadith'] as $s): ?>
            <option><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Level</label>
          <select name="level" class="form-control form-select">
            <option>Beginner</option><option>Intermediate</option><option>Advanced</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Teacher</label>
          <select name="teacher_id" class="form-control form-select" required>
            <?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= h($t['full_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Mosque</label>
          <select name="mosque_id" class="form-control form-select" required>
            <?php foreach($mosques as $m): ?><option value="<?= $m['id'] ?>"><?= h($m['name_en']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Schedule Days</label>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem">
          <?php foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $d): ?>
          <label style="display:flex;align-items:center;gap:.3rem;cursor:pointer;padding:.3rem .7rem;border:1px solid var(--gray-300);border-radius:var(--radius-sm);font-size:.85rem">
            <input type="checkbox" name="days[]" value="<?= $d ?>"> <?= $d ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Time Start</label>
          <input type="time" name="time_start" class="form-control" required value="16:00">
        </div>
        <div class="form-group">
          <label class="form-label">Time End</label>
          <input type="time" name="time_end" class="form-control" required value="17:00">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Max Students</label>
        <input type="number" name="max_students" class="form-control" value="20" min="1" max="100">
      </div>
      <div style="display:flex;gap:.75rem;justify-content:flex-end">
        <button type="button" onclick="document.getElementById('createModal').style.display='none'" class="btn btn-outline">Cancel</button>
        <button type="submit" class="btn btn-primary">✅ Create Class</button>
      </div>
    </form>
  </div>
</div>

</main>
</div>
<?php include '../includes/footer.php'; ?>
