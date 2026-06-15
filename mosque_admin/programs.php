<?php
require_once dirname(__DIR__) . '/config.php';
requireRole('mosque_admin');
$lang=$_SESSION['lang']??'en'; $isRtl=$lang==='ar';
$pdo=getPDO(); $userId=$_SESSION['user_id'];
$pageTitle=($isRtl?'برامج المسجد — ':'Mosque Programs — ').APP_NAME;

$mosque=$pdo->prepare("SELECT * FROM mosques WHERE admin_id=? LIMIT 1");
$mosque->execute([$userId]); $mosque=$mosque->fetch();
if(!$mosque){ $mosque=$pdo->prepare("SELECT * FROM mosques WHERE id=? LIMIT 1"); $mosque->execute([$_SESSION['user']['mosque_id']??0]); $mosque=$mosque->fetch(); }
if(!$mosque){ header('Location: /dashboard.php'); exit; }
$mid=$mosque['id'];

$templates=$pdo->query("SELECT * FROM program_templates WHERE is_active=1 ORDER BY sort_order")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action=$_POST['action']??'';
    $progId=(int)($_POST['program_id']??0);
    $appId=(int)($_POST['app_id']??0);

    if($action==='add_program'){
        $slot=$_POST['slot']??'';
        $templateId=(int)($_POST['template_id']??0);
        $isCustom=$_POST['is_custom']??'0';
        // Validate slot
        if(!in_array($slot,['A','B'])){ setFlash('danger',$isRtl?'اختر Slot أولاً':'Please select Slot A or B first'); header('Location: /mosque_admin/programs.php?tab=add'); exit; }
        // Only block if active program with teacher already exists
        $existing=$pdo->prepare("SELECT id FROM mosque_programs WHERE mosque_id=? AND slot=? AND is_active=1 AND teacher_id IS NOT NULL");
        $existing->execute([$mid,$slot]);
        if($existing->fetch()){ setFlash('danger',$isRtl?'يوجد معلم مرتبط بهذا الـ Slot — أزل المعلم أولاً':'A teacher is assigned to this slot — remove teacher first'); header('Location: /mosque_admin/programs.php?tab=programs'); exit; }
        if($isCustom==='1'){
            $nameEn=trim($_POST['custom_name_en']??''); $nameAr=trim($_POST['custom_name_ar']??'');
            if(!$nameEn){ setFlash('danger','Program name required'); header('Location: /mosque_admin/programs.php?tab=add'); exit; }
            $days=$slot==='A'?'Sunday,Tuesday,Thursday':'Monday,Wednesday,Friday';
            $pdo->prepare("INSERT INTO mosque_programs (mosque_id,name_en,name_ar,program_type,slot,days,time_start,time_end,max_students,is_active) VALUES (?,?,?,'Custom',?,?,'16:00:00','17:00:00',20,1)
                ON DUPLICATE KEY UPDATE name_en=VALUES(name_en),name_ar=VALUES(name_ar),program_type='Custom',days=VALUES(days),is_active=1,teacher_id=NULL")->execute([$mid,$nameEn,$nameAr,$slot,$days]);
        } else {
            $tpl=null; foreach($templates as $t){ if($t['id']==$templateId){$tpl=$t;break;} }
            if(!$tpl){ setFlash('danger','Invalid template'); header('Location: /mosque_admin/programs.php?tab=add'); exit; }
            $days=$slot==='A'?'Sunday,Tuesday,Thursday':'Monday,Wednesday,Friday';
            $pdo->prepare("INSERT INTO mosque_programs (mosque_id,name_en,name_ar,program_type,slot,days,time_start,time_end,max_students,is_active) VALUES (?,?,?,?,?,?,'16:00:00','17:00:00',20,1)
                ON DUPLICATE KEY UPDATE name_en=VALUES(name_en),name_ar=VALUES(name_ar),program_type=VALUES(program_type),days=VALUES(days),is_active=1,teacher_id=NULL")->execute([$mid,$tpl['name_en'],$tpl['name_ar'],$tpl['program_type'],$slot,$days]);
        }
        setFlash('success',$isRtl?'✅ تم إضافة البرنامج':'✅ Program added');
        header('Location: /mosque_admin/programs.php?tab=programs'); exit;
    }
    if($action==='approve_teacher'){
        $teacherId=(int)$_POST['teacher_id'];
        $pdo->prepare("UPDATE mosque_programs SET teacher_id=? WHERE id=? AND mosque_id=? AND teacher_id IS NULL")->execute([$teacherId,$progId,$mid]);
        $pdo->prepare("UPDATE teacher_program_applications SET status='approved',reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$userId,$appId]);
        $pdo->prepare("UPDATE teacher_program_applications SET status='rejected',reviewed_by=?,reviewed_at=NOW() WHERE program_id=? AND id!=? AND status='pending'")->execute([$userId,$progId,$appId]);
        // Also update classes table so teacher dashboard shows the class
        $pdo->prepare("UPDATE classes SET teacher_id=? WHERE mosque_id=? AND teacher_id IS NULL LIMIT 1")->execute([$teacherId,$mid]);
        $pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'success')")->execute([$teacherId,'Application Approved!','Your program application has been approved.']);
        setFlash('success',$isRtl?'✅ تم قبول المعلم':'✅ Teacher approved');
        header('Location: /mosque_admin/programs.php?tab=applications'); exit;
    }
    if($action==='reject_teacher'){
        $teacherId=(int)$_POST['teacher_id'];
        $pdo->prepare("UPDATE teacher_program_applications SET status='rejected',reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$userId,$appId]);
        $pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'warning')")->execute([$teacherId,'Application Update','Your application was not accepted.']);
        setFlash('info',$isRtl?'تم رفض الطلب':'Rejected');
        header('Location: /mosque_admin/programs.php?tab=applications'); exit;
    }
    if($action==='remove_teacher'&&$progId){ $pdo->prepare("UPDATE mosque_programs SET teacher_id=NULL WHERE id=? AND mosque_id=?")->execute([$progId,$mid]); setFlash('info','Teacher removed'); header('Location: /mosque_admin/programs.php'); exit; }
    if($action==='delete_program'&&$progId){ $pdo->prepare("UPDATE mosque_programs SET is_active=0 WHERE id=? AND mosque_id=?")->execute([$progId,$mid]); setFlash('info','Deleted'); header('Location: /mosque_admin/programs.php'); exit; }
}

$tab=$_GET['tab']??'programs';
$programs=$pdo->query("SELECT mp.*,u.full_name as teacher_name,(SELECT COUNT(*) FROM program_enrollments pe WHERE pe.program_id=mp.id AND pe.status='active') as enrolled,(SELECT COUNT(*) FROM teacher_program_applications tpa WHERE tpa.program_id=mp.id AND tpa.status='pending') as pending_apps FROM mosque_programs mp LEFT JOIN users u ON u.id=mp.teacher_id WHERE mp.mosque_id=$mid AND mp.is_active=1 ORDER BY mp.slot")->fetchAll();
$pendingApps=$pdo->query("SELECT tpa.*,u.full_name as teacher_name,u.email as teacher_email,mp.name_en as prog_en,mp.name_ar as prog_ar,mp.slot,mp.days,mp.time_start,mp.time_end FROM teacher_program_applications tpa JOIN users u ON u.id=tpa.teacher_id JOIN mosque_programs mp ON mp.id=tpa.program_id WHERE mp.mosque_id=$mid AND tpa.status='pending' ORDER BY tpa.applied_at ASC")->fetchAll();
$takenSlots=array_column($programs,'slot');
$templateIcons=['Memorization'=>'📖','Tajweed'=>'🎵','Recitation'=>'🔊','Kids'=>'👶','Tafseer'=>'📚','Converts'=>'🌙','Custom'=>'⚙️'];
$levelColors=['Beginner'=>['#D1FAE5','#065F46'],'Intermediate'=>['#DBEAFE','#1D4ED8'],'Advanced'=>['#EDE9FE','#4C1D95'],'All Levels'=>['#FEF3C7','#92400E']];
include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:1.5rem;font-weight:800;margin:0">📚 <?=$isRtl?'برامج المسجد':'Mosque Programs'?></h1>
    <p style="color:var(--gray-500);font-size:13px;margin:4px 0 0">🕌 <?=h($isRtl?$mosque['name_ar']:$mosque['name_en'])?></p>
  </div>
  <a href="?tab=add" style="background:var(--green-main);color:#fff;padding:9px 18px;border-radius:99px;text-decoration:none;font-size:13px;font-weight:700">➕ <?=$isRtl?'إضافة':'Add Program'?></a>
</div>

<div style="display:flex;background:var(--gray-50);border-radius:12px;padding:4px;margin-bottom:20px;width:fit-content;gap:2px">
  <?php foreach([['programs','📋 '.($isRtl?'البرامج':'Programs')],['add','➕ '.($isRtl?'إضافة':'Add')],['applications','⏳ Apps ('.count($pendingApps).')']] as [$k,$l]):?>
  <a href="?tab=<?=$k?>" style="padding:8px 16px;border-radius:10px;font-size:13px;text-decoration:none;background:<?=$tab===$k?'#fff':'transparent'?>;font-weight:<?=$tab===$k?700:400?>;color:<?=$tab===$k?'var(--green-dark)':'var(--gray-500)'?>"><?=$l?></a>
  <?php endforeach;?>
</div>

<?php if($tab==='programs'): ?>
<?php if(empty($programs)):?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:60px;text-align:center">
  <div style="font-size:3rem">📚</div>
  <div style="margin-top:12px;font-size:15px;font-weight:600;color:var(--gray-500)"><?=$isRtl?'لا توجد برامج بعد':'No programs yet'?></div>
  <a href="?tab=add" class="btn btn-primary" style="margin-top:16px;display:inline-block">➕ <?=$isRtl?'إضافة برنامج':'Add Program'?></a>
</div>
<?php else:?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px">
  <?php foreach($programs as $p):
    $icon=$templateIcons[$p['program_type']]??'📖';
  ?>
  <div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
      <div style="display:flex;align-items:center;gap:10px">
        <div style="font-size:1.8rem"><?=$icon?></div>
        <div>
          <div style="font-weight:800;font-size:14px;color:var(--green-dark)"><?=h($isRtl?$p['name_ar']:$p['name_en'])?></div>
          <div style="display:flex;gap:5px;margin-top:3px;flex-wrap:wrap">
            <span style="background:var(--green-pale);color:var(--green-dark);padding:1px 8px;border-radius:99px;font-size:10px;font-weight:700">Slot <?=$p['slot']?></span>
            <span style="background:#DBEAFE;color:#1D4ED8;padding:1px 8px;border-radius:99px;font-size:10px;font-weight:700"><?=h($p['program_type'])?></span>
          </div>
        </div>
      </div>
      <form method="POST" onsubmit="return confirm('Delete?')">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="delete_program">
        <input type="hidden" name="program_id" value="<?=$p['id']?>">
        <button type="submit" style="background:none;border:none;color:var(--gray-300);cursor:pointer;font-size:1rem">🗑</button>
      </form>
    </div>
    <div style="background:var(--gray-50);border-radius:8px;padding:8px 12px;font-size:12px;margin-bottom:12px">
      <div style="color:var(--gray-600)">📅 <?=str_replace(',',' · ',$p['days'])?></div>
      <div style="color:var(--gray-600);margin-top:2px">⏰ <?=substr($p['time_start'],0,5)?>–<?=substr($p['time_end'],0,5)?></div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center">
      <div>
        <?php if($p['teacher_id']):?>
        <div style="font-size:12px;color:var(--success);font-weight:700">👨‍🏫 <?=h($p['teacher_name'] ?? '')?></div>
        <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="remove_teacher"><input type="hidden" name="program_id" value="<?=$p['id']?>"><button type="submit" style="font-size:10px;background:none;border:1px solid var(--danger);color:var(--danger);border-radius:6px;padding:1px 7px;cursor:pointer">Remove</button></form>
        <?php else:?>
        <div style="font-size:12px;color:var(--warning);font-weight:700">⚠️ <?=$isRtl?'لا يوجد معلم':'No teacher'?></div>
        <?php if($p['pending_apps']>0):?><div style="font-size:10px;color:var(--info)">⏳ <?=$p['pending_apps']?> app</div><?php endif;?>
        <?php endif;?>
      </div>
      <div style="text-align:center">
        <div style="font-size:1.2rem;font-weight:800;color:var(--green-main)"><?=$p['enrolled']?></div>
        <div style="font-size:10px;color:var(--gray-400)">students</div>
      </div>
    </div>
  </div>
  <?php endforeach;?>
</div>
<?php endif;?>

<?php elseif($tab==='add'): ?>
<div style="max-width:680px">

  <div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px;margin-bottom:14px">
    <h3 style="font-size:14px;font-weight:700;color:var(--green-dark);margin:0 0 12px">📅 Step 1 — Choose Slot</h3>
    <?php if(count($takenSlots)>=2):?>
    <div style="background:#FEF3C7;border:1px solid #FCD34D;border-radius:10px;padding:14px;text-align:center">
      <div style="font-size:1.5rem;margin-bottom:6px">⚠️</div>
      <div style="font-weight:700;font-size:13px;color:#92400E"><?=$isRtl?'كلا الـ Slots محجوزان':'Both Slots are Taken'?></div>
      <div style="font-size:12px;color:#B45309;margin-top:4px"><?=$isRtl?'احذف برنامجاً أولاً لإضافة برنامج جديد':'Delete an existing program first, then add a new one'?></div>
      <a href="?tab=programs" class="btn btn-secondary btn-sm" style="margin-top:10px;display:inline-block">← <?=$isRtl?'عرض البرامج':'View Programs'?></a>
    </div>
    <?php else:?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <?php foreach(['A'=>['Sun · Tue · Thu','أحد · ثلاثاء · خميس'],'B'=>['Mon · Wed · Fri','اثنين · أربعاء · جمعة']] as $slot=>[$de,$da]):
        $taken=in_array($slot,$takenSlots);?>
      <label style="cursor:<?=$taken?'not-allowed':'pointer'?>;opacity:<?=$taken?.45:1?>">
        <input type="radio" name="_slot" value="<?=$slot?>" <?=$taken?'disabled':''?> onclick="setSlot('<?=$slot?>')" style="display:none">
        <div id="slot-<?=$slot?>" style="border:2px solid #e5e7eb;border-radius:12px;padding:14px;text-align:center">
          <div style="font-size:1.3rem;font-weight:800;color:<?=$taken?'#9ca3af':'#4C1D95'?>">Slot <?=$slot?></div>
          <div style="font-size:11px;color:#6b7280;margin-top:3px"><?=$isRtl?$da:$de?></div>
          <div style="font-size:10px;margin-top:3px;color:<?=$taken?'#dc2626':'#9ca3af'?>"><?=$taken?'Taken':'4:00–5:00 PM'?></div>
        </div>
      </label>
      <?php endforeach;?>
    </div>
    <?php endif;?>
  </div>

  <div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px;margin-bottom:14px">
    <h3 style="font-size:14px;font-weight:700;color:var(--green-dark);margin:0 0 4px">🎯 Step 2 — Choose Program Type</h3>
    <p style="font-size:12px;color:#6b7280;margin:0 0 14px">
      <?php if(!in_array('A',$takenSlots) && !in_array('B',$takenSlots)): ?>
      Select a slot first to see matching programs
      <?php elseif(!in_array('A',$takenSlots)): ?>
      <span style="background:#DBEAFE;color:#1D4ED8;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:700">🎓 Slot A — Student Programs</span>
      <?php else: ?>
      <span style="background:#FEF3C7;color:#92400E;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:700">👶 Slot B — Children Programs</span>
      <?php endif; ?>
    </p>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:8px" id="tplGrid">
      <?php foreach($templates as $t):
        $ic=$templateIcons[$t['program_type']]??'📖';
        $lc=$levelColors[$t['level']]??['#f3f4f6','#374151'];
        $tSlot=$t['recommended_slot']??'A';
        $tAudience=$t['target_audience']??'student';
      ?>
      <div onclick="selectTpl(<?=$t['id']?>,this)"
           id="tpl-<?=$t['id']?>"
           data-slot="<?=$tSlot?>"
           data-audience="<?=$tAudience?>"
           style="border:2px solid #e5e7eb;border-radius:12px;padding:12px;cursor:pointer;text-align:center">
        <div style="font-size:1.8rem;margin-bottom:5px"><?=$ic?></div>
        <div style="font-weight:700;font-size:12px;color:#1f2937"><?=h($isRtl?$t['name_ar']:$t['name_en'])?></div>
        <div style="font-size:10px;color:#9ca3af;margin:2px 0"><?=h($isRtl?$t['name_en']:$t['name_ar'])?></div>
        <span style="background:<?=$lc[0]?>;color:<?=$lc[1]?>;padding:1px 7px;border-radius:99px;font-size:9px;font-weight:700"><?=$t['level']?></span>
        <div style="font-size:9px;margin-top:3px;color:<?=$tAudience==='child'?'#92400E':'#1D4ED8'?>;font-weight:600">
          <?=$tAudience==='child'?'👶 Children':'🎓 Students'?>
        </div>
      </div>
      <?php endforeach;?>
      <div onclick="selectTpl('custom',this)" id="tpl-custom" data-slot="both" style="border:2px dashed #d1d5db;border-radius:12px;padding:12px;cursor:pointer;text-align:center">
        <div style="font-size:1.8rem;margin-bottom:5px">⚙️</div>
        <div style="font-weight:700;font-size:12px;color:#4b5563">Custom</div>
        <div style="font-size:10px;color:#9ca3af;margin-top:3px">Create your own</div>
      </div>
    </div>
  </div>

  <div id="tplPreview" style="display:none;background:#F0FDF4;border:1px solid #86EFAC;border-radius:12px;padding:14px;margin-bottom:14px">
    <div style="font-size:13px;font-weight:700;color:#166534" id="prevTitle"></div>
    <div style="font-size:12px;color:#374151;margin-top:4px" id="prevDesc"></div>
  </div>

  <div id="customFields" style="display:none;background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px;margin-bottom:14px">
    <h3 style="font-size:14px;font-weight:700;color:var(--green-dark);margin:0 0 12px">✏️ Custom Program Name</h3>
    <div class="form-group"><label class="form-label">Name (English) *</label><input type="text" id="cen" class="form-control" placeholder="e.g. Advanced Hifz Class"></div>
    <div class="form-group"><label class="form-label">الاسم (عربي)</label><input type="text" id="car" class="form-control" placeholder="مثال: فصل الحفظ المتقدم" dir="rtl"></div>
  </div>

  <form method="POST" id="addForm">
        <?= csrfField() ?>
    <input type="hidden" name="action" value="add_program">
    <input type="hidden" name="slot" id="slotIn" value="">
    <input type="hidden" name="template_id" id="tplIn" value="">
    <input type="hidden" name="is_custom" id="custIn" value="0">
    <input type="hidden" name="custom_name_en" id="cenIn" value="">
    <input type="hidden" name="custom_name_ar" id="carIn" value="">
    <button type="button" onclick="doSubmit()" class="btn btn-primary btn-lg" style="width:100%">✅ <?=$isRtl?'إضافة البرنامج':'Add Program'?></button>
  </form>
</div>

<?php elseif($tab==='applications'): ?>
<?php if(empty($pendingApps)):?>
<div style="text-align:center;padding:60px;color:var(--gray-300)"><div style="font-size:3rem">✅</div><div style="margin-top:12px">No pending applications</div></div>
<?php else: foreach($pendingApps as $app):?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px;margin-bottom:12px">
  <div style="display:grid;grid-template-columns:1fr auto;gap:16px;align-items:start">
    <div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
        <div style="width:42px;height:42px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--green-dark);font-size:1.1rem"><?=strtoupper(substr($app['teacher_name'],0,1))?></div>
        <div><div style="font-weight:700;font-size:15px;color:var(--green-dark)"><?=h($app['teacher_name'])?></div><div style="font-size:12px;color:var(--gray-500)"><?=h($app['teacher_email']??'')?></div></div>
      </div>
      <div style="background:var(--gray-50);border-radius:10px;padding:12px;font-size:13px">
        <div style="font-weight:600;color:var(--green-dark);margin-bottom:4px">📚 <?=h($isRtl?$app['prog_ar']:$app['prog_en'])?> (Slot <?=$app['slot']?>)</div>
        <div>📅 <?=str_replace(',',' / ',$app['days']??'')?></div>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px;min-width:110px">
      <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="approve_teacher"><input type="hidden" name="app_id" value="<?=$app['id']?>"><input type="hidden" name="teacher_id" value="<?=$app['teacher_id']?>"><input type="hidden" name="program_id" value="<?=$app['program_id']?>"><button type="submit" class="btn btn-primary w-full">✅ Approve</button></form>
      <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="reject_teacher"><input type="hidden" name="app_id" value="<?=$app['id']?>"><input type="hidden" name="teacher_id" value="<?=$app['teacher_id']?>"><button type="submit" class="btn btn-secondary w-full" style="color:var(--danger)">❌ Reject</button></form>
    </div>
  </div>
</div>
<?php endforeach; endif; ?>
<?php endif; ?>

</main>
</div>
<script>
let selSlot='',selTpl='';
const tplData=<?php
$tplMap=[];
foreach($templates as $t) $tplMap[$t['id']]=['id'=>$t['id'],'name_en'=>$t['name_en'],'name_ar'=>$t['name_ar'],'description_en'=>$t['description_en'],'description_ar'=>$t['description_ar'],'level'=>$t['level'],'program_type'=>$t['program_type'],'min_age'=>$t['min_age'],'max_age'=>$t['max_age']];
echo json_encode($tplMap,JSON_UNESCAPED_UNICODE);
?>;
function setSlot(s){
  selSlot=s;
  ['A','B'].forEach(x=>{const e=document.getElementById('slot-'+x);if(e){e.style.border='2px solid #e5e7eb';e.style.background='#fff';}});
  const el=document.getElementById('slot-'+s);
  if(el){el.style.border='2px solid #4C1D95';el.style.background='#F5F3FF';}
  // Filter templates by slot
  document.querySelectorAll('[id^="tpl-"][data-slot]').forEach(card=>{
    const cardSlot=card.dataset.slot;
    const show = cardSlot===s || cardSlot==='both';
    card.style.display = show?'block':'none';
  });
  // Reset selection
  selTpl='';
  document.getElementById('tplPreview').style.display='none';
  document.getElementById('customFields').style.display='none';
}
function selectTpl(id,el){
  document.querySelectorAll('[id^="tpl-"]').forEach(e=>{e.style.border=e.id==='tpl-custom'?'2px dashed #d1d5db':'2px solid #e5e7eb';e.style.background='#fff';});
  el.style.border='2px solid #4C1D95';el.style.background='#F5F3FF';
  selTpl=id;
  const cf=document.getElementById('customFields');
  const pv=document.getElementById('tplPreview');
  if(id==='custom'){cf.style.display='block';pv.style.display='none';}
  else{cf.style.display='none';const t=tplData[id];if(t){pv.style.display='block';document.getElementById('prevTitle').textContent=t.name_en+' — '+t.name_ar;document.getElementById('prevDesc').textContent=t.description_en+' | '+t.description_ar;}}
}
function doSubmit(){
  if(!selSlot){alert('Please select a Slot');return;}
  if(!selTpl){alert('Please select a program type');return;}
  document.getElementById('slotIn').value=selSlot;
  if(selTpl==='custom'){
    const en=document.getElementById('cen').value.trim();
    if(!en){alert('Program name required');return;}
    document.getElementById('custIn').value='1';
    document.getElementById('cenIn').value=en;
    document.getElementById('carIn').value=document.getElementById('car').value.trim();
    document.getElementById('tplIn').value='';
  } else {
    document.getElementById('custIn').value='0';
    document.getElementById('tplIn').value=selTpl;
  }
  document.getElementById('addForm').submit();
}
</script>
<?php include '../includes/footer.php'; ?>
