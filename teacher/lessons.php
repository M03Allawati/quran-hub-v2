<?php
require_once dirname(__DIR__) . '/config.php';
requireRole('teacher');
$lang=$_SESSION['lang']??'en'; $isRtl=$lang==='ar';
$pdo=getPDO(); $userId=$_SESSION['user_id'];
$pageTitle=($isRtl?'خطط الدروس — ':'Lesson Plans — ').APP_NAME;

// Get teacher's program & slot type
$myProg=$pdo->prepare("SELECT mp.*, m.name_en as mosque_en, m.name_ar as mosque_ar FROM mosque_programs mp JOIN mosques m ON m.id=mp.mosque_id WHERE mp.teacher_id=? AND mp.is_active=1 LIMIT 1");
$myProg->execute([$userId]); $myProg=$myProg->fetch();
if(!$myProg){ setFlash('warning',$isRtl?'لم يتم تعيينك في برنامج بعد':'You are not assigned to a program yet'); header('Location: /teacher/programs.php'); exit; }
$progId=$myProg['id'];
$progType=$myProg['program_type'];
$targetType=$myProg['target_type'];

// Get levels for this program type
$levels=$pdo->prepare("SELECT * FROM course_levels WHERE program_type=? ORDER BY level_number");
$levels->execute([$progType]); $levels=$levels->fetchAll();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action=$_POST['action']??'';

    if($action==='save_weekly'){
        // LESSON_DATE_VALIDATE
        $wstart = $_POST['week_start'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $wstart) || !strtotime($wstart)) {
            setFlash('danger', 'Invalid date format');
            header('Location: /teacher/lessons.php'); exit;
        }
        $wend=date('Y-m-d',strtotime($wstart.' +6 days'));
        $pdo->prepare("INSERT INTO weekly_lessons (program_id,teacher_id,level_id,week_start,week_end,surah_number,surah_name_en,surah_name_ar,ayah_from,ayah_to,topic_en,topic_ar,objectives,notes,homework,status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'planned')
            ON DUPLICATE KEY UPDATE level_id=VALUES(level_id),surah_number=VALUES(surah_number),surah_name_en=VALUES(surah_name_en),surah_name_ar=VALUES(surah_name_ar),ayah_from=VALUES(ayah_from),ayah_to=VALUES(ayah_to),topic_en=VALUES(topic_en),topic_ar=VALUES(topic_ar),objectives=VALUES(objectives),notes=VALUES(notes),homework=VALUES(homework)")
            ->execute([$progId,$userId,(int)($_POST['level_id']??0)?:null,$wstart,$wend,
                (int)($_POST['surah_number']??0)?:null,$_POST['surah_name_en']??'',$_POST['surah_name_ar']??'',
                (int)($_POST['ayah_from']??0)?:null,(int)($_POST['ayah_to']??0)?:null,
                $_POST['topic_en'],$_POST['topic_ar'],$_POST['objectives']??'',$_POST['notes']??'',$_POST['homework']??'']);
        setFlash('success',$isRtl?'✅ تم حفظ الدرس الأسبوعي':'✅ Weekly lesson saved');
        header('Location: /teacher/lessons.php?view=weekly'); exit;
    }

    if($action==='save_monthly'){
        $pdo->prepare("INSERT INTO lesson_plans (program_id,teacher_id,level_id,plan_type,start_date,end_date,title_en,title_ar,description,status)
            VALUES (?,?,?,?,?,?,?,?,?,'active')")
            ->execute([$progId,$userId,(int)($_POST['level_id']??0)?:null,'monthly',
                preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['start_date']??'') ? $_POST['start_date'] : date('Y-m-01'),
                preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['end_date']??'') ? $_POST['end_date'] : date('Y-m-t'),$_POST['title_en'],$_POST['title_ar'],$_POST['description']??'']);
        setFlash('success',$isRtl?'✅ تم حفظ الخطة الشهرية':'✅ Monthly plan saved');
        header('Location: /teacher/lessons.php?view=monthly'); exit;
    }

    if($action==='delete_weekly'){
        $pdo->prepare("DELETE FROM weekly_lessons WHERE id=? AND teacher_id=?")->execute([(int)$_POST['id'],$userId]);
        setFlash('info',$isRtl?'تم الحذف':'Deleted');
        header('Location: /teacher/lessons.php?view=weekly'); exit;
    }

    if($action==='delete_monthly'){
        $pdo->prepare("DELETE FROM lesson_plans WHERE id=? AND teacher_id=?")->execute([(int)$_POST['id'],$userId]);
        setFlash('info',$isRtl?'تم الحذف':'Deleted');
        header('Location: /teacher/lessons.php?view=monthly'); exit;
    }

    if($action==='update_status'){
        $pdo->prepare("UPDATE weekly_lessons SET status=? WHERE id=? AND teacher_id=?")->execute([$_POST['status'],(int)$_POST['id'],$userId]);
        header('Location: /teacher/lessons.php?view=weekly'); exit;
    }
}

$view=$_GET['view']??'weekly';

// Weekly lessons
$weeklyLessons=$pdo->prepare("SELECT wl.*,cl.name_en as level_name,cl.name_ar as level_name_ar FROM weekly_lessons wl LEFT JOIN course_levels cl ON cl.id=wl.level_id WHERE wl.program_id=? AND wl.teacher_id=? ORDER BY wl.week_start DESC LIMIT 20");
$weeklyLessons->execute([$progId,$userId]); $weeklyLessons=$weeklyLessons->fetchAll();

// Monthly plans
$monthlyPlans=$pdo->prepare("SELECT lp.*,cl.name_en as level_name FROM lesson_plans lp LEFT JOIN course_levels cl ON cl.id=lp.level_id WHERE lp.program_id=? AND lp.teacher_id=? ORDER BY lp.start_date DESC");
$monthlyPlans->execute([$progId,$userId]); $monthlyPlans=$monthlyPlans->fetchAll();

// Current week
$thisMonday=date('Y-m-d',strtotime('monday this week'));
$thisWeekLesson=null;
foreach($weeklyLessons as $wl){ if($wl['week_start']===$thisMonday){$thisWeekLesson=$wl;break;} }

$statusColors=['planned'=>['#FEF3C7','#92400E','⏳'],'in_progress'=>['#DBEAFE','#1D4ED8','🔵'],'completed'=>['#D1FAE5','#065F46','✅']];
$surahs=[[1,'Al-Fatihah','الفاتحة'],[2,'Al-Baqarah','البقرة'],[3,'Ali Imran','آل عمران'],[36,'Ya-Sin','يس'],[55,'Ar-Rahman','الرحمن'],[56,'Al-Waqiah','الواقعة'],[67,'Al-Mulk','الملك'],[78,'An-Naba','النبأ'],[87,'Al-Ala','الأعلى'],[93,'Ad-Duha','الضحى'],[94,'Ash-Sharh','الشرح'],[108,'Al-Kawthar','الكوثر'],[112,'Al-Ikhlas','الإخلاص'],[113,'Al-Falaq','الفلق'],[114,'An-Nas','الناس']];

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<!-- Header -->
<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:1.5rem;font-weight:800;margin:0">📅 <?=$isRtl?'خطط الدروس':'Lesson Plans'?></h1>
    <div style="display:flex;align-items:center;gap:8px;margin-top:6px;flex-wrap:wrap">
      <span style="background:<?=$targetType==='child'?'#FEF3C7':'#DBEAFE'?>;color:<?=$targetType==='child'?'#92400E':'#1D4ED8'?>;padding:3px 10px;border-radius:99px;font-size:12px;font-weight:700">
        <?=$targetType==='child'?'👶 '.($isRtl?'أطفال':'Children'):'🎓 '.($isRtl?'طلاب':'Students')?>
      </span>
      <span style="background:var(--green-pale);color:var(--green-dark);padding:3px 10px;border-radius:99px;font-size:12px;font-weight:700">
        <?=h($isRtl?$myProg['name_ar']:$myProg['name_en'])?>
      </span>
      <span style="color:var(--gray-500);font-size:12px">🕌 <?=h($isRtl?$myProg['mosque_ar']:$myProg['mosque_en'])?></span>
    </div>
  </div>
</div>

<!-- Tabs -->
<div style="display:flex;background:var(--gray-50);border-radius:12px;padding:4px;margin-bottom:20px;width:fit-content;gap:2px">
  <a href="?view=weekly" style="padding:8px 18px;border-radius:10px;font-size:13px;text-decoration:none;background:<?=$view==='weekly'?'#fff':'transparent'?>;font-weight:<?=$view==='weekly'?700:400?>;color:<?=$view==='weekly'?'var(--green-dark)':'var(--gray-500)'?>">
    📅 <?=$isRtl?'الدروس الأسبوعية':'Weekly Lessons'?>
  </a>
  <a href="?view=monthly" style="padding:8px 18px;border-radius:10px;font-size:13px;text-decoration:none;background:<?=$view==='monthly'?'#fff':'transparent'?>;font-weight:<?=$view==='monthly'?700:400?>;color:<?=$view==='monthly'?'var(--green-dark)':'var(--gray-500)'?>">
    🗓️ <?=$isRtl?'الخطط الشهرية':'Monthly Plans'?>
  </a>
  <a href="?view=levels" style="padding:8px 18px;border-radius:10px;font-size:13px;text-decoration:none;background:<?=$view==='levels'?'#fff':'transparent'?>;font-weight:<?=$view==='levels'?700:400?>;color:<?=$view==='levels'?'var(--green-dark)':'var(--gray-500)'?>">
    🏆 <?=$isRtl?'المستويات':'Levels'?>
  </a>
</div>

<?php if($view==='weekly'): ?>
<!-- ══ WEEKLY VIEW ══ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

<!-- ADD WEEKLY FORM -->
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px">
  <h3 style="font-size:14px;font-weight:700;color:var(--green-dark);margin:0 0 16px">
    ➕ <?=$isRtl?'إضافة درس أسبوعي':'Add Weekly Lesson'?>
  </h3>
  <form method="POST">
        <?= csrfField() ?>
    <input type="hidden" name="action" value="save_weekly">

    <div class="form-group">
      <label class="form-label">📅 <?=$isRtl?'بداية الأسبوع (الاثنين)':'Week Start (Monday)'?></label>
      <input type="date" name="week_start" class="form-control" value="<?=$thisMonday?>" required
        onchange="setWeekEnd(this.value)">
      <div style="font-size:11px;color:var(--gray-400);margin-top:3px" id="weekEndLabel">
        <?=$isRtl?'نهاية الأسبوع:':'Week ends:'?> <?=date('d M Y',strtotime($thisMonday.' +6 days'))?>
      </div>
    </div>

    <?php if(!empty($levels)):?>
    <div class="form-group">
      <label class="form-label">🏆 <?=$isRtl?'المستوى':'Level'?></label>
      <select name="level_id" class="form-control form-select">
        <option value=""><?=$isRtl?'-- اختر --':'-- Select --'?></option>
        <?php foreach($levels as $l):?>
        <option value="<?=$l['id']?>"><?=$l['level_number']?>. <?=h($isRtl?$l['name_ar']:$l['name_en'])?></option>
        <?php endforeach;?>
      </select>
    </div>
    <?php endif;?>

    <div class="form-group">
      <label class="form-label">📖 <?=$isRtl?'السورة (اختياري)':'Surah (optional)'?></label>
      <select name="surah_number" class="form-control form-select" onchange="fillSurahNames(this)">
        <option value="">-- <?=$isRtl?'اختر سورة':'Select Surah'?> --</option>
        <?php foreach($surahs as $s):?>
        <option value="<?=$s[0]?>" data-en="<?=$s[1]?>" data-ar="<?=$s[2]?>"><?=$s[0]?>. <?=$s[2]?> (<?=$s[1]?>)</option>
        <?php endforeach;?>
      </select>
      <input type="hidden" name="surah_name_en" id="surahNameEn">
      <input type="hidden" name="surah_name_ar" id="surahNameAr">
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div class="form-group">
        <label class="form-label"><?=$isRtl?'من آية':'From Ayah'?></label>
        <input type="number" name="ayah_from" class="form-control" min="1" placeholder="1">
      </div>
      <div class="form-group">
        <label class="form-label"><?=$isRtl?'إلى آية':'To Ayah'?></label>
        <input type="number" name="ayah_to" class="form-control" min="1" placeholder="7">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">📝 <?=$isRtl?'موضوع الدرس (عربي)':'Lesson Topic (Arabic)'?></label>
      <input type="text" name="topic_ar" class="form-control" dir="rtl" placeholder="مراجعة سورة الفاتحة" required>
    </div>
    <div class="form-group">
      <label class="form-label">📝 <?=$isRtl?'موضوع الدرس (إنجليزي)':'Lesson Topic (English)'?></label>
      <input type="text" name="topic_en" class="form-control" placeholder="Review Al-Fatihah" required>
    </div>

    <div class="form-group">
      <label class="form-label">🎯 <?=$isRtl?'أهداف الدرس':'Objectives'?></label>
      <textarea name="objectives" class="form-control" rows="2" placeholder="<?=$isRtl?'ماذا سيتعلم الطالب؟':'What will students learn?'?>"></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">📋 <?=$isRtl?'واجب منزلي':'Homework'?></label>
      <textarea name="homework" class="form-control" rows="2" placeholder="<?=$isRtl?'واجب الأسبوع القادم...':'Next week homework...'?>"></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">💬 <?=$isRtl?'ملاحظات':'Notes'?></label>
      <textarea name="notes" class="form-control" rows="2" placeholder="<?=$isRtl?'أي ملاحظات إضافية...':'Any additional notes...'?>"></textarea>
    </div>

    <button type="submit" class="btn btn-primary" style="width:100%">
      💾 <?=$isRtl?'حفظ الدرس':'Save Lesson'?>
    </button>
  </form>
</div>

<!-- WEEKLY HISTORY -->
<div>
  <!-- This week highlight -->
  <?php if($thisWeekLesson):?>
  <div style="background:linear-gradient(135deg,var(--green-dark),var(--green-main));border-radius:14px;padding:18px;margin-bottom:14px;color:#fff">
    <div style="font-size:12px;opacity:.8;margin-bottom:4px">📅 <?=$isRtl?'درس هذا الأسبوع':'This Week\'s Lesson'?></div>
    <div style="font-weight:800;font-size:15px"><?=h($isRtl?$thisWeekLesson['topic_ar']:$thisWeekLesson['topic_en'])?></div>
    <?php if($thisWeekLesson['surah_name_ar']):?>
    <div style="font-size:12px;opacity:.85;margin-top:4px">📖 <?=h($thisWeekLesson['surah_name_ar'])?> — آية <?=$thisWeekLesson['ayah_from']?>–<?=$thisWeekLesson['ayah_to']?></div>
    <?php endif;?>
    <?php if($thisWeekLesson['level_name']):?>
    <div style="font-size:11px;opacity:.8;margin-top:3px">🏆 <?=h($thisWeekLesson['level_name'])?></div>
    <?php endif;?>
  </div>
  <?php endif;?>

  <h3 style="font-size:14px;font-weight:700;color:var(--green-dark);margin:0 0 12px">
    📋 <?=$isRtl?'سجل الدروس':'Lessons History'?>
    <span style="background:var(--green-pale);color:var(--green-dark);padding:1px 8px;border-radius:99px;font-size:11px;margin-right:6px"><?=count($weeklyLessons)?></span>
  </h3>

  <?php if(empty($weeklyLessons)):?>
  <div style="text-align:center;padding:40px;color:var(--gray-300);background:#fff;border-radius:14px;border:1px solid var(--gray-100)">
    <div style="font-size:2.5rem">📅</div>
    <div style="margin-top:10px;font-size:13px"><?=$isRtl?'لا توجد دروس بعد':'No lessons yet'?></div>
  </div>
  <?php else:?>
  <div style="display:flex;flex-direction:column;gap:8px;max-height:550px;overflow-y:auto">
    <?php foreach($weeklyLessons as $wl):
      $sc=$statusColors[$wl['status']]??['#F3F4F6','#374151','⏳'];
      $isThisWeek=$wl['week_start']===$thisMonday;
    ?>
    <div style="background:<?=$isThisWeek?'#F0FDF4':'#fff'?>;border:1px solid <?=$isThisWeek?'#86EFAC':'var(--gray-100)'?>;border-radius:12px;padding:14px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px">
        <div>
          <div style="font-weight:700;font-size:13px;color:var(--green-dark)"><?=h($isRtl?$wl['topic_ar']:$wl['topic_en'])?></div>
          <div style="font-size:10px;color:var(--gray-400);margin-top:2px">
            <?=date('d M',strtotime($wl['week_start']))?> – <?=date('d M Y',strtotime($wl['week_end']))?>
            <?=$isThisWeek?' 🟢 '.($isRtl?'هذا الأسبوع':'This week'):''?>
          </div>
        </div>
        <div style="display:flex;gap:4px;align-items:center">
          <span style="background:<?=$sc[0]?>;color:<?=$sc[1]?>;padding:1px 7px;border-radius:99px;font-size:10px;font-weight:700"><?=$sc[2]?> <?=ucfirst($wl['status'])?></span>
        </div>
      </div>

      <?php if($wl['surah_name_ar']):?>
      <div style="font-size:11px;color:var(--gray-600);margin-bottom:4px">📖 <?=h($wl['surah_name_ar'])?> <?=$wl['ayah_from']?'آية '.$wl['ayah_from'].'–'.$wl['ayah_to']:''?></div>
      <?php endif;?>
      <?php if($wl['level_name']):?>
      <div style="font-size:11px;color:var(--gray-600);margin-bottom:4px">🏆 <?=h($isRtl?$wl['level_name_ar']:$wl['level_name'])?></div>
      <?php endif;?>
      <?php if($wl['homework']):?>
      <div style="font-size:11px;color:var(--info);margin-bottom:4px">📋 <?=h($wl['homework'])?></div>
      <?php endif;?>

      <div style="display:flex;gap:4px;margin-top:8px;flex-wrap:wrap">
        <?php foreach(['planned'=>($isRtl?'مخطط':'Planned'),'in_progress'=>($isRtl?'جارٍ':'In Progress'),'completed'=>($isRtl?'مكتمل':'Completed')] as $st=>$sl):
          if($st===$wl['status']) continue;?>
        <form method="POST" style="display:inline">
        <?= csrfField() ?>
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="id" value="<?=$wl['id']?>">
          <input type="hidden" name="status" value="<?=$st?>">
          <button type="submit" style="background:<?=$statusColors[$st][0]?>;color:<?=$statusColors[$st][1]?>;border:none;border-radius:6px;padding:2px 8px;font-size:10px;cursor:pointer;font-weight:600"><?=$statusColors[$st][2]?> <?=$sl?></button>
        </form>
        <?php endforeach;?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
        <?= csrfField() ?>
          <input type="hidden" name="action" value="delete_weekly">
          <input type="hidden" name="id" value="<?=$wl['id']?>">
          <button type="submit" style="background:#FEE2E2;color:#DC2626;border:none;border-radius:6px;padding:2px 8px;font-size:10px;cursor:pointer">🗑</button>
        </form>
      </div>
    </div>
    <?php endforeach;?>
  </div>
  <?php endif;?>
</div>
</div><!-- end grid -->

<?php elseif($view==='monthly'): ?>
<!-- ══ MONTHLY VIEW ══ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

<!-- ADD MONTHLY PLAN -->
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px">
  <h3 style="font-size:14px;font-weight:700;color:var(--green-dark);margin:0 0 16px">
    🗓️ <?=$isRtl?'إضافة خطة شهرية':'Add Monthly Plan'?>
  </h3>
  <form method="POST">
        <?= csrfField() ?>
    <input type="hidden" name="action" value="save_monthly">

    <?php if(!empty($levels)):?>
    <div class="form-group">
      <label class="form-label">🏆 <?=$isRtl?'المستوى':'Level'?></label>
      <select name="level_id" class="form-control form-select">
        <option value=""><?=$isRtl?'-- اختر --':'-- Select --'?></option>
        <?php foreach($levels as $l):?>
        <option value="<?=$l['id']?>"><?=$l['level_number']?>. <?=h($isRtl?$l['name_ar']:$l['name_en'])?></option>
        <?php endforeach;?>
      </select>
    </div>
    <?php endif;?>

    <div class="form-group">
      <label class="form-label">📝 <?=$isRtl?'عنوان الخطة (عربي)':'Plan Title (Arabic)'?></label>
      <input type="text" name="title_ar" class="form-control" dir="rtl" placeholder="خطة شهر رمضان" required>
    </div>
    <div class="form-group">
      <label class="form-label">📝 <?=$isRtl?'عنوان الخطة (إنجليزي)':'Plan Title (English)'?></label>
      <input type="text" name="title_en" class="form-control" placeholder="Ramadan Month Plan" required>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div class="form-group">
        <label class="form-label"><?=$isRtl?'تاريخ البداية':'Start Date'?></label>
        <input type="date" name="start_date" class="form-control" value="<?=date('Y-m-01')?>" required>
      </div>
      <div class="form-group">
        <label class="form-label"><?=$isRtl?'تاريخ النهاية':'End Date'?></label>
        <input type="date" name="end_date" class="form-control" value="<?=date('Y-m-t')?>" required>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">📋 <?=$isRtl?'تفاصيل الخطة':'Plan Details'?></label>
      <textarea name="description" class="form-control" rows="5"
        placeholder="<?=$isRtl?'مثال:\nالأسبوع 1: مراجعة جزء عم\nالأسبوع 2: حفظ سورة الملك\nالأسبوع 3: أحكام النون الساكنة\nالأسبوع 4: تقييم وإعادة':'Example:\nWeek 1: Review Juz Amma\nWeek 2: Memorize Al-Mulk\nWeek 3: Noon Sakinah rules\nWeek 4: Assessment'?>"></textarea>
    </div>

    <button type="submit" class="btn btn-primary" style="width:100%">
      💾 <?=$isRtl?'حفظ الخطة الشهرية':'Save Monthly Plan'?>
    </button>
  </form>
</div>

<!-- MONTHLY HISTORY -->
<div>
  <h3 style="font-size:14px;font-weight:700;color:var(--green-dark);margin:0 0 12px">
    🗓️ <?=$isRtl?'الخطط الشهرية':'Monthly Plans'?>
    <span style="background:var(--green-pale);color:var(--green-dark);padding:1px 8px;border-radius:99px;font-size:11px;margin-right:6px"><?=count($monthlyPlans)?></span>
  </h3>

  <?php if(empty($monthlyPlans)):?>
  <div style="text-align:center;padding:40px;color:var(--gray-300);background:#fff;border-radius:14px;border:1px solid var(--gray-100)">
    <div style="font-size:2.5rem">🗓️</div>
    <div style="margin-top:10px;font-size:13px"><?=$isRtl?'لا توجد خطط شهرية بعد':'No monthly plans yet'?></div>
  </div>
  <?php else:?>
  <div style="display:flex;flex-direction:column;gap:10px">
    <?php foreach($monthlyPlans as $mp):
      $isActive=date('Y-m-d')>=$mp['start_date']&&date('Y-m-d')<=$mp['end_date'];
    ?>
    <div style="background:<?=$isActive?'#F0FDF4':'#fff'?>;border:1px solid <?=$isActive?'#86EFAC':'var(--gray-100)'?>;border-radius:12px;padding:16px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
        <div>
          <div style="font-weight:700;font-size:14px;color:var(--green-dark)"><?=h($isRtl?$mp['title_ar']:$mp['title_en'])?></div>
          <div style="font-size:11px;color:var(--gray-400);margin-top:2px">
            <?=date('d M',strtotime($mp['start_date']))?> – <?=date('d M Y',strtotime($mp['end_date']))?>
            <?=$isActive?' 🟢 '.($isRtl?'الشهر الحالي':'Current'):'';?>
          </div>
          <?php if($mp['level_name']):?>
          <div style="font-size:11px;color:var(--gray-600);margin-top:2px">🏆 <?=h($mp['level_name'])?></div>
          <?php endif;?>
        </div>
        <form method="POST" onsubmit="return confirm('Delete?')">
        <?= csrfField() ?>
          <input type="hidden" name="action" value="delete_monthly">
          <input type="hidden" name="id" value="<?=$mp['id']?>">
          <button type="submit" style="background:none;border:none;color:var(--gray-300);cursor:pointer;font-size:1rem">🗑</button>
        </form>
      </div>
      <?php if($mp['description']):?>
      <div style="font-size:12px;color:var(--gray-600);white-space:pre-line;background:var(--gray-50);border-radius:8px;padding:8px 10px;line-height:1.7"><?=h($mp['description'])?></div>
      <?php endif;?>
    </div>
    <?php endforeach;?>
  </div>
  <?php endif;?>
</div>
</div><!-- end grid -->

<?php elseif($view==='levels'): ?>
<!-- ══ LEVELS VIEW ══ -->
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px">
  <h3 style="font-size:14px;font-weight:700;color:var(--green-dark);margin:0 0 16px">
    🏆 <?=$isRtl?'مستويات برنامج':'Program Levels'?> — <?=h($isRtl?$myProg['name_ar']:$myProg['name_en'])?>
  </h3>
  <?php if(empty($levels)):?>
  <div style="text-align:center;padding:40px;color:var(--gray-300)">
    <div style="font-size:2.5rem">🏆</div>
    <div style="margin-top:10px"><?=$isRtl?'لا توجد مستويات لهذا البرنامج':'No levels for this program type'?></div>
  </div>
  <?php else:?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
    <?php foreach($levels as $i=>$l):
      $colors=[['#EDE9FE','#4C1D95'],['#DBEAFE','#1D4ED8'],['#D1FAE5','#065F46'],['#FEF3C7','#92400E']];
      $c=$colors[$i%4];
    ?>
    <div style="background:<?=$c[0]?>;border-radius:12px;padding:16px;border:1px solid <?=$c[1]?>33">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
        <div style="width:36px;height:36px;border-radius:50%;background:<?=$c[1]?>;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px"><?=$l['level_number']?></div>
        <div>
          <div style="font-weight:700;font-size:13px;color:<?=$c[1]?>"><?=h($isRtl?$l['name_ar']:$l['name_en'])?></div>
          <div style="font-size:11px;color:var(--gray-600)"><?=h($isRtl?$l['name_en']:$l['name_ar'])?></div>
        </div>
      </div>
      <?php if($l['description_ar']):?>
      <div style="font-size:11px;color:var(--gray-600);line-height:1.5"><?=h($isRtl?$l['description_ar']:$l['description_en'])?></div>
      <?php endif;?>
    </div>
    <?php endforeach;?>
  </div>
  <?php endif;?>
</div>
<?php endif;?>

</main>
</div>

<script>
function setWeekEnd(dateStr){
  const d=new Date(dateStr);
  d.setDate(d.getDate()+6);
  const opts={day:'2-digit',month:'short',year:'numeric'};
  document.getElementById('weekEndLabel').textContent='<?=$isRtl?'نهاية الأسبوع:':'Week ends:'?> '+d.toLocaleDateString('<?=$isRtl?'ar':'en'?>',opts);
}
function fillSurahNames(sel){
  const opt=sel.options[sel.selectedIndex];
  document.getElementById('surahNameEn').value=opt.dataset.en||'';
  document.getElementById('surahNameAr').value=opt.dataset.ar||'';
}
</script>
<?php include '../includes/footer.php'; ?>
