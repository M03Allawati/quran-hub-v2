<?php
require_once dirname(__DIR__) . '/config.php';
requireRole('parent');
$lang=$_SESSION['lang']??'en'; $isRtl=$lang==='ar';
$pdo=getPDO(); $userId=$_SESSION['user_id'];
$pageTitle=($isRtl?'أطفالي — ':'My Children — ').APP_NAME;

$parentMosque=$_SESSION['user']['mosque_id']??0;
$parentGov=$_SESSION['user']['governorate']??'';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action=$_POST['action']??'';

    if($action==='add_child'){
        $fn=trim($_POST['full_name']??''); $fnar=trim($_POST['full_name_ar']??'');
        $dob=$_POST['dob']??''; $gnd=$_POST['gender']??'male';
        $mid=(int)($_POST['mosque_id']??$parentMosque);

        // ── التحقق من العمر: يجب أن يكون بين 5 و 12 سنة ──────────
        $ageError='';
        if($dob){
            $age=(new DateTime($dob))->diff(new DateTime())->y;
            if($age < 5){
                $ageError=$isRtl?'⚠️ عمر الطفل يجب أن يكون 5 سنوات أو أكثر للتسجيل في برنامج Slot B (الأطفال)':'⚠️ Child must be at least 5 years old for Slot B (Children) program.';
            } elseif($age > 12){
                $ageError=$isRtl?'⚠️ عمر الطفل يجب أن يكون 12 سنة أو أقل لبرنامج الأطفال. للطلاب الأكبر سناً يرجى التسجيل كـ Student في Slot A':'⚠️ Child must be 12 years old or younger for Slot B. Older students should register as Student in Slot A.';
            }
        }

        if($ageError){
            setFlash('danger',$ageError);
            header('Location: /parent/children.php'); exit;
        }

        if($fn&&$dob&&$mid){
            $pdo->prepare("INSERT INTO students (full_name,full_name_ar,date_of_birth,gender,parent_id,mosque_id,student_type,slot,is_active) VALUES (?,?,?,?,?,?,'child','B',1)")
                ->execute([$fn,$fnar,$dob,$gnd,$userId,$mid]);
            $childId=(int)$pdo->lastInsertId();
            // Auto-enroll ONLY in Slot B (target_type='child')
            $progs=$pdo->prepare("SELECT id,teacher_id,name_en FROM mosque_programs WHERE mosque_id=? AND is_active=1 AND target_type='child'");
            $progs->execute([$mid]);
            foreach($progs->fetchAll() as $p){
                $pdo->prepare("INSERT IGNORE INTO program_enrollments (program_id,student_id) VALUES (?,?)")->execute([$p['id'],$childId]);
                if($p['teacher_id']){
                    $pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'info')")->execute([$p['teacher_id'],'New Child',"$fn joined {$p['name_en']}"]);
                }
            }
            // Also enroll in classes table for attendance tracking (Slot B only)
            $cls=$pdo->prepare("SELECT c.id FROM classes c
                JOIN mosque_programs mp ON mp.mosque_id=c.mosque_id AND mp.teacher_id=c.teacher_id
                WHERE c.mosque_id=? AND c.is_active=1 AND mp.target_type='child' LIMIT 1");
            $cls->execute([$mid]);
            foreach($cls->fetchAll() as $cl){
                $pdo->prepare("INSERT IGNORE INTO enrollments (student_id,class_id,status) VALUES (?,?,'active')")->execute([$childId,$cl['id']]);
            }
            setFlash('success',$isRtl?"✅ تم تسجيل {$fn}! (العمر: {$age} سنوات — Slot B)":"✅ {$fn} registered! (Age: {$age} — Slot B)");
        } else { setFlash('danger',$isRtl?'يرجى تعبئة جميع الحقول':'Fill all fields'); }
        header('Location: /parent/children.php'); exit;
    }

    if($action==='delete_child'){
        $childId=(int)$_POST['child_id'];
        // Verify belongs to this parent
        $chk=$pdo->prepare("SELECT id FROM students WHERE id=? AND parent_id=?");
        $chk->execute([$childId,$userId]);
        if($chk->fetch()){
            // SOFT DELETE — نوقف التسجيل فقط، البيانات تبقى محفوظة
            $pdo->prepare("UPDATE program_enrollments SET status='dropped' WHERE student_id=?")->execute([$childId]);
            $pdo->prepare("UPDATE enrollments SET status='dropped' WHERE student_id=?")->execute([$childId]);
            $pdo->prepare("UPDATE students SET is_active=0 WHERE id=?")->execute([$childId]);
            setFlash('success',$isRtl?'✅ تم إلغاء تسجيل الطفل. السجلات محفوظة.':'✅ Child deactivated. Records preserved.');
        }
        header('Location: /parent/children.php'); exit;
    }

    if($action==='request_private'){
        $childId=(int)$_POST['child_id'];
        $teacherId=(int)$_POST['teacher_id'];
        $days=$_POST['preferred_days']??'Sunday,Tuesday,Thursday';
        $time=$_POST['preferred_time']??'16:00';
        $notes=trim($_POST['notes']??'');
        $chk=$pdo->prepare("SELECT id,mosque_id FROM students WHERE id=? AND parent_id=?");
        $chk->execute([$childId,$userId]);
        $child=$chk->fetch();
        if($child){
            // Check not already requested
            $existing=$pdo->prepare("SELECT id FROM private_program_requests WHERE student_id=? AND status IN ('pending','active')");
            $existing->execute([$childId]);
            if($existing->fetch()){
                setFlash('warning',$isRtl?'لديك طلب قائم بالفعل':'Already has an active request');
            } else {
                $pdo->prepare("INSERT INTO private_program_requests (parent_id,student_id,teacher_id,mosque_id,preferred_days,preferred_time,notes,status) VALUES (?,?,?,?,?,?,?,'pending')")
                    ->execute([$userId,$childId,$teacherId?$teacherId:null,$child['mosque_id'],$days,$time.':00',$notes]);
                // Notify teacher if selected
                if($teacherId){
                    $cName=$pdo->prepare("SELECT full_name FROM students WHERE id=?"); $cName->execute([$childId]); $cName=$cName->fetchColumn();
                    $pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'info')")
                        ->execute([$teacherId,"Private Program Request","Parent requesting private Quran session for $cName"]);
                }
                setFlash('success',$isRtl?'✅ تم إرسال طلب البرنامج الخاص':'✅ Private program request sent!');
            }
        }
        header('Location: /parent/children.php'); exit;
    }
}

$children=$pdo->prepare("SELECT s.*,m.name_en as mosque_en,m.name_ar as mosque_ar,m.governorate,m.id as mid FROM students s JOIN mosques m ON m.id=s.mosque_id WHERE s.parent_id=? AND s.is_active=1 ORDER BY s.full_name");
$children->execute([$userId]); $children=$children->fetchAll();

// Available teachers in parent's governorate
$availTeachers=$pdo->prepare("SELECT u.id,u.full_name,u.full_name_ar,m.name_en as mosque_en FROM users u JOIN mosques m ON m.id=u.mosque_id WHERE u.role='teacher' AND u.is_active=1 AND u.governorate=? ORDER BY u.full_name");
$availTeachers->execute([$parentGov]); $availTeachers=$availTeachers->fetchAll();

$govMosques=$pdo->prepare("SELECT id,name_en,name_ar,wilayat FROM mosques WHERE governorate=? AND is_active=1 ORDER BY name_en");
$govMosques->execute([$parentGov]); $govMosques=$govMosques->fetchAll();
if(empty($govMosques)) $govMosques=$pdo->query("SELECT id,name_en,name_ar,wilayat FROM mosques WHERE is_active=1 ORDER BY name_en")->fetchAll();

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:1.5rem;font-weight:800;margin:0">👶 <?=$isRtl?'أطفالي':'My Children'?></h1>
    <p style="color:var(--gray-500);font-size:13px;margin:4px 0 0"><?=$isRtl?'إدارة تسجيل أطفالك':'Manage your children\'s registration'?></p>
  </div>
  <button onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-primary">+ <?=$isRtl?'إضافة طفل':'Add Child'?></button>
</div>

<?php if(empty($children)):?>
<div style="text-align:center;padding:60px;background:#fff;border-radius:14px;border:1px solid var(--gray-100)">
  <div style="font-size:48px">👶</div>
  <h3 style="color:var(--green-dark);margin:12px 0 6px"><?=$isRtl?'لم تسجّل أي أطفال':'No children yet'?></h3>
  <p style="color:var(--gray-500);margin-bottom:16px"><?=$isRtl?'سجّل طفلك في مركز القرآن':'Register your child in a Quran center'?></p>
  <button onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-primary">+ <?=$isRtl?'تسجيل طفل':'Register Child'?></button>
</div>
<?php else: foreach($children as $child):
  $progs=$pdo->prepare("SELECT mp.name_en,mp.name_ar,mp.slot,mp.days,mp.time_start,mp.time_end,u.full_name as teacher FROM program_enrollments pe JOIN mosque_programs mp ON mp.id=pe.program_id LEFT JOIN users u ON u.id=mp.teacher_id WHERE pe.student_id=? AND pe.status='active' ORDER BY mp.slot");
  $progs->execute([$child['id']]); $progs=$progs->fetchAll();

  $privateReq=$pdo->prepare("SELECT ppr.*,u.full_name as teacher_name FROM private_program_requests ppr LEFT JOIN users u ON u.id=ppr.teacher_id WHERE ppr.student_id=? ORDER BY ppr.created_at DESC LIMIT 1");
  $privateReq->execute([$child['id']]); $privateReq=$privateReq->fetch();

  $attStats=$pdo->prepare("SELECT status,COUNT(*) as cnt FROM attendance WHERE student_id=? GROUP BY status");
  $attStats->execute([$child['id']]); $attMap=[];
  foreach($attStats->fetchAll() as $r) $attMap[$r['status']]=$r['cnt'];
  $total=array_sum($attMap); $pct=$total>0?round(($attMap['present']??0)/$total*100):0;

  $surahsDone=(int)$pdo->prepare("SELECT COUNT(*) FROM progress WHERE student_id=? AND memorization_pct=100")->execute([$child['id']])?$pdo->query("SELECT COUNT(*) FROM progress WHERE student_id={$child['id']} AND memorization_pct=100")->fetchColumn():0;
?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;margin-bottom:16px;overflow:hidden">
  <!-- Header -->
  <div style="background:linear-gradient(135deg,var(--green-dark),var(--green-mid));padding:16px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
    <div style="width:46px;height:46px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem;font-weight:800;flex-shrink:0"><?=strtoupper(substr($child['full_name'],0,1))?></div>
    <div style="flex:1">
      <div style="font-weight:800;font-size:15px;color:#fff"><?=h($child['full_name'])?></div>
      <div style="font-size:12px;color:rgba(255,255,255,.8)">🕌 <?=h($isRtl?$child['mosque_ar']:$child['mosque_en'])?> · 📍 <?=h($child['governorate'])?><?=$child['date_of_birth']?' · 🎂 '.date('Y',strtotime($child['date_of_birth'])):''?></div>
    </div>
    <div style="display:flex;gap:12px">
      <?php foreach([['✅',$pct.'%',$isRtl?'حضور':'Attend'],['📖',$surahsDone,$isRtl?'سورة':'Surahs'],['📚',count($progs),$isRtl?'برنامج':'Programs']] as [$icon,$val,$label]):?>
      <div style="text-align:center"><div><?=$icon?></div><div style="font-size:1rem;font-weight:800;color:#fff"><?=$val?></div><div style="font-size:10px;color:rgba(255,255,255,.7)"><?=$label?></div></div>
      <?php endforeach;?>
    </div>
    <!-- Delete button -->
    <form method="POST" onsubmit="return confirm('<?=$isRtl?'حذف هذا الطفل نهائياً؟':'Delete this child permanently?'?>
        <?= csrfField() ?>')">
      <input type="hidden" name="action" value="delete_child"><input type="hidden" name="child_id" value="<?=$child['id']?>">
      <button type="submit" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.4);border-radius:99px;padding:5px 12px;font-size:11px;cursor:pointer;font-weight:600">🗑 <?=$isRtl?'حذف':'Delete'?></button>
    </form>
  </div>

  <div style="padding:16px 20px">
    <!-- Programs -->
    <div style="font-size:13px;font-weight:700;color:var(--green-dark);margin-bottom:10px">📚 <?=$isRtl?'البرامج المسجّلة':'Enrolled Programs'?></div>
    <?php if(empty($progs)):?>
    <div style="padding:12px;background:var(--warning-pale);border-radius:10px;font-size:13px;color:var(--warning);margin-bottom:12px">⚠️ <?=$isRtl?'لم يسجّل في أي برنامج بعد':'No programs enrolled yet'?></div>
    <?php else: foreach($progs as $p):?>
    <div style="border:1px solid var(--green-light);background:var(--green-pale);border-radius:10px;padding:10px 14px;margin-bottom:6px">
      <div style="font-weight:600;font-size:13px;color:var(--green-dark)"><?=h($isRtl?$p['name_ar']:$p['name_en'])?></div>
      <div style="font-size:12px;color:var(--gray-500);margin-top:3px">
        📅 <?=str_replace(',',' / ',$p['days'])?> · ⏰ <?=substr($p['time_start'],0,5)?>–<?=substr($p['time_end'],0,5)?>
        <?php if($p['teacher']):?>· 👨‍🏫 <?=h($p['teacher'])?><?php else:?> · <span style="color:var(--warning)">⏳ <?=$isRtl?'في انتظار معلم':'Awaiting teacher'?></span><?php endif;?>
      </div>
    </div>
    <?php endforeach; endif;?>

    <!-- Private Program Section -->
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--gray-50)">
      <div style="font-size:13px;font-weight:700;color:var(--green-dark);margin-bottom:8px">
        🌟 <?=$isRtl?'برنامج خاص (معلم خاص)':'Private Program (Private Teacher)'?>
        <span style="font-size:11px;font-weight:400;color:var(--gray-500);margin-<?=$isRtl?'right':'left'?>:8px"><?=$isRtl?'للأطفال الصغار — اهتمام شخصي':'For young children — personal attention'?></span>
      </div>
      <?php if($privateReq):
        $statusColors=['pending'=>'var(--warning)','accepted'=>'var(--success)','rejected'=>'var(--danger)','active'=>'var(--success)'];
        $statusLabels=['pending'=>($isRtl?'قيد المراجعة':'Pending'),'accepted'=>($isRtl?'مقبول':'Accepted'),'rejected'=>($isRtl?'مرفوض':'Rejected'),'active'=>($isRtl?'نشط':'Active')];
      ?>
      <div style="border:1px solid var(--gray-100);border-radius:10px;padding:12px 14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--dark)"><?=$isRtl?'طلب برنامج خاص':'Private Program Request'?></div>
          <?php if($privateReq['teacher_name']):?><div style="font-size:12px;color:var(--gray-500)">👨‍🏫 <?=h($privateReq['teacher_name'])?></div><?php endif;?>
          <div style="font-size:12px;color:var(--gray-500)">📅 <?=str_replace(',',' / ',$privateReq['preferred_days'])?> · ⏰ <?=substr($privateReq['preferred_time'],0,5)?></div>
        </div>
        <span style="color:<?=$statusColors[$privateReq['status']]?>;font-weight:700;font-size:13px">● <?=$statusLabels[$privateReq['status']]?></span>
      </div>
      <?php else:?>
      <button onclick="showPrivateForm(<?=$child['id']?>)" class="btn btn-secondary btn-sm" style="font-size:12px">
        ➕ <?=$isRtl?'طلب معلم خاص':'Request Private Teacher'?>
      </button>
      <?php endif;?>
    </div>

    <!-- Action links -->
    <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
      <a href="/parent/attendance.php" class="btn btn-secondary btn-sm">✅ <?=$isRtl?'الحضور':'Attendance'?></a>
      <a href="/parent/progress.php" class="btn btn-secondary btn-sm">📖 <?=$isRtl?'التقدم':'Progress'?></a>
      <a href="/parent/classes.php" class="btn btn-secondary btn-sm">📅 <?=$isRtl?'الجدول':'Schedule'?></a>
    </div>
  </div>
</div>
<?php endforeach; endif;?>

</main>
</div>

<!-- Add Child Modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;padding:28px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lg)">
    <div style="display:flex;justify-content:space-between;margin-bottom:20px">
      <h3 style="margin:0;color:var(--green-dark)">👶 <?=$isRtl?'تسجيل طفل جديد':'Register New Child'?></h3>
      <button onclick="document.getElementById('addModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer">✕</button>
    </div>
    <form method="POST">
        <?= csrfField() ?>
      <input type="hidden" name="action" value="add_child">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label"><?=$isRtl?'الاسم بالإنجليزية':'Full Name (EN)'?> *</label>
          <input type="text" name="full_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label"><?=$isRtl?'الاسم بالعربية':'Full Name (AR)'?></label>
          <input type="text" name="full_name_ar" class="form-control" dir="rtl">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">📅 <?=$isRtl?'تاريخ الميلاد':'Date of Birth'?> *</label>
          <input type="date" name="dob" class="form-control" max="<?=date('Y-m-d',strtotime('-3 years'))?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">👤 <?=$isRtl?'الجنس':'Gender'?></label>
          <select name="gender" class="form-control form-select">
            <option value="male"><?=$isRtl?'ذكر':'Male'?></option>
            <option value="female"><?=$isRtl?'أنثى':'Female'?></option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">🕌 <?=$isRtl?'المسجد':'Mosque'?> *</label>
        <select name="mosque_id" class="form-control form-select" required>
          <option value=""><?=$isRtl?'-- اختر --':'-- Select --'?></option>
          <?php foreach($govMosques as $m):?>
          <option value="<?=$m['id']?>" <?=$m['id']==$parentMosque?'selected':''?>><?=h($isRtl?$m['name_ar']:$m['name_en'])?> — <?=h($m['wilayat'])?></option>
          <?php endforeach;?>
        </select>
        <p class="form-hint">⚡ <?=$isRtl?'سيُسجَّل تلقائياً في برامج المسجد':'Auto-enrolled in mosque programs'?></p>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary" style="flex:1">✅ <?=$isRtl?'تسجيل':'Register'?></button>
        <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn btn-secondary"><?=$isRtl?'إلغاء':'Cancel'?></button>
      </div>
    </form>
  </div>
</div>

<!-- Private Program Modal -->
<div id="privateModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;padding:28px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lg)">
    <div style="display:flex;justify-content:space-between;margin-bottom:20px">
      <h3 style="margin:0;color:var(--green-dark)">🌟 <?=$isRtl?'طلب برنامج خاص':'Request Private Program'?></h3>
      <button onclick="document.getElementById('privateModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer">✕</button>
    </div>
    <p style="font-size:13px;color:var(--gray-500);margin-bottom:16px">
      <?=$isRtl?'برنامج خاص مع معلم متخصص لطفلك — اهتمام شخصي وجدول مرن':'A private session with a dedicated teacher — personal attention & flexible schedule'?>
    </p>
    <form method="POST">
        <?= csrfField() ?>
      <input type="hidden" name="action" value="request_private">
      <input type="hidden" name="child_id" id="privateChildId">
      <div class="form-group">
        <label class="form-label">👨‍🏫 <?=$isRtl?'اختر معلماً (اختياري)':'Select Teacher (optional)'?></label>
        <select name="teacher_id" class="form-control form-select">
          <option value=""><?=$isRtl?'أي معلم متاح':'Any available teacher'?></option>
          <?php foreach($availTeachers as $t):?>
          <option value="<?=$t['id']?>"><?=h($t['full_name'])?> — <?=h($t['mosque_en'])?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">📅 <?=$isRtl?'الأيام المفضّلة':'Preferred Days'?></label>
        <select name="preferred_days" class="form-control form-select">
          <option value="Sunday,Tuesday,Thursday"><?=$isRtl?'الأحد / الثلاثاء / الخميس':'Sun / Tue / Thu'?></option>
          <option value="Monday,Wednesday,Friday"><?=$isRtl?'الاثنين / الأربعاء / الجمعة':'Mon / Wed / Fri'?></option>
          <option value="Sunday,Tuesday,Thursday,Monday,Wednesday,Friday"><?=$isRtl?'كل الأيام':'All Days'?></option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">⏰ <?=$isRtl?'الوقت المفضّل':'Preferred Time'?></label>
        <input type="time" name="preferred_time" class="form-control" value="16:00">
      </div>
      <div class="form-group">
        <label class="form-label">📝 <?=$isRtl?'ملاحظات (اختياري)':'Notes (optional)'?></label>
        <textarea name="notes" class="form-control" rows="2" placeholder="<?=$isRtl?'أي ملاحظات خاصة...':'Any special notes...'?>"></textarea>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary" style="flex:1">✅ <?=$isRtl?'إرسال الطلب':'Send Request'?></button>
        <button type="button" onclick="document.getElementById('privateModal').style.display='none'" class="btn btn-secondary"><?=$isRtl?'إلغاء':'Cancel'?></button>
      </div>
    </form>
  </div>
</div>

<script>
function showPrivateForm(childId) {
    document.getElementById('privateChildId').value = childId;
    document.getElementById('privateModal').style.display = 'flex';
}
</script>
<?php include '../includes/footer.php'; ?>
