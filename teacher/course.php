<?php
require_once dirname(__DIR__) . '/config.php';
requireRole('teacher');
$lang=$_SESSION['lang']??'en'; $isRtl=$lang==='ar';
$pdo=getPDO(); $userId=$_SESSION['user_id'];

$courseId=(int)($_GET['id']??0);
$progId=(int)($_GET['prog']??0);
if(!$courseId||!$progId){ header('Location: /teacher/programs.php'); exit; }

// Verify teacher owns this program
$prog=$pdo->prepare("SELECT mp.*,m.name_en as mosque_en,m.name_ar as mosque_ar FROM mosque_programs mp JOIN mosques m ON m.id=mp.mosque_id WHERE mp.id=? AND mp.teacher_id=? AND mp.is_active=1");
$prog->execute([$progId,$userId]); $prog=$prog->fetch();
if(!$prog){ header('Location: /teacher/programs.php'); exit; }

$course=$pdo->prepare("SELECT * FROM fixed_courses WHERE id=? AND slot=?");
$course->execute([$courseId,$prog['slot']]); $course=$course->fetch();
if(!$course){ header('Location: /teacher/programs.php'); exit; }

$pageTitle=h($isRtl?$course['name_ar']:$course['name_en']).' — '.APP_NAME;
$isChild=$prog['target_type']==='child';
$slotColor=$isChild?'#D97706':'#1D4ED8';
$slotBg=$isChild?'#FEF3C7':'#DBEAFE';
$slotGrad=$isChild?'linear-gradient(135deg,#92400E,#D97706)':'linear-gradient(135deg,#1D4ED8,#4F46E5)';

// Get class id
$myClass=$pdo->prepare("SELECT id FROM classes WHERE teacher_id=? AND is_active=1 LIMIT 1");
$myClass->execute([$userId]); $myClass=$myClass->fetch();
$classId=$myClass['id']??0;

// Levels
$levels=$pdo->prepare("SELECT * FROM course_levels WHERE program_type=? ORDER BY level_number");
$levels->execute([$prog['program_type']]); $levels=$levels->fetchAll();

// Is active course?
$activeSel=$pdo->prepare("SELECT course_id FROM teacher_course_selections WHERE teacher_id=? AND program_id=? AND is_active=1");
$activeSel->execute([$userId,$progId]); $activeSel=$activeSel->fetch();
$isActiveCourse=$activeSel&&$activeSel['course_id']==$courseId;

// Students
$students=$pdo->prepare("SELECT s.*,
    (SELECT COUNT(*) FROM attendance a WHERE a.student_id=s.id AND a.status='present') as present_cnt,
    (SELECT COUNT(*) FROM attendance a WHERE a.student_id=s.id) as total_att,
    (SELECT COUNT(*) FROM progress p WHERE p.student_id=s.id AND p.memorization_pct=100) as surahs_done,
    (SELECT AVG(p2.memorization_pct) FROM progress p2 WHERE p2.student_id=s.id) as avg_pct
    FROM program_enrollments pe JOIN students s ON s.id=pe.student_id
    WHERE pe.program_id=? AND pe.status='active' ORDER BY s.full_name");
$students->execute([$progId]); $students=$students->fetchAll();

// Selected student for progress
$selStudent=(int)($_GET['student']??($students[0]['id']??0));
$selStudentInfo=null;
foreach($students as $s){ if($s['id']==$selStudent){$selStudentInfo=$s;break;} }

// Active tab
$tab=$_GET['tab']??'lessons';

// Surahs list
$surahs=[[1,'Al-Fatihah','الفاتحة',7],[2,'Al-Baqarah','البقرة',286],[3,'Ali Imran','آل عمران',200],
    [36,'Ya-Sin','يس',83],[55,'Ar-Rahman','الرحمن',78],[56,'Al-Waqiah','الواقعة',96],
    [67,'Al-Mulk','الملك',30],[78,'An-Naba','النبأ',40],[87,'Al-Ala','الأعلى',19],
    [93,'Ad-Duha','الضحى',11],[94,'Ash-Sharh','الشرح',8],[95,'At-Tin','التين',8],
    [99,'Az-Zalzalah','الزلزلة',8],[102,'At-Takathur','التكاثر',8],[103,'Al-Asr','العصر',3],
    [105,'Al-Fil','الفيل',5],[107,'Al-Maun','الماعون',7],[108,'Al-Kawthar','الكوثر',3],
    [109,'Al-Kafirun','الكافرون',6],[110,'An-Nasr','النصر',3],[111,'Al-Masad','المسد',5],
    [112,'Al-Ikhlas','الإخلاص',4],[113,'Al-Falaq','الفلق',5],[114,'An-Nas','الناس',6]];

// ── POST HANDLERS ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action=$_POST['action']??'';

    if($action==='set_active'){
        $pdo->prepare("INSERT INTO teacher_course_selections (teacher_id,program_id,course_id,start_date,is_active) VALUES (?,?,?,CURDATE(),1) ON DUPLICATE KEY UPDATE course_id=VALUES(course_id),start_date=CURDATE(),is_active=1")->execute([$userId,$progId,$courseId]);
        setFlash('success',$isRtl?'✅ تم تفعيل الدورة':'✅ Course activated');
        header("Location: /teacher/course.php?id=$courseId&prog=$progId&tab=lessons"); exit;
    }

    if($action==='save_lesson'){
        $type=$_POST['lesson_type']??'weekly';
        $startDate=$_POST['start_date'];
        $endDate=$type==='weekly'?date('Y-m-d',strtotime($startDate.' +6 days')):$_POST['end_date'];
        $levelId=(int)($_POST['level_id']??0)?:null;
        $surahNum=(int)($_POST['surah_number']??0)?:null;
        $sen=$sar=''; foreach($surahs as $s){ if($s[0]==$surahNum){$sen=$s[1];$sar=$s[2];break;} }
        if($type==='weekly'){
            $pdo->prepare("INSERT INTO weekly_lessons (program_id,teacher_id,level_id,week_start,week_end,surah_number,surah_name_en,surah_name_ar,ayah_from,ayah_to,topic_en,topic_ar,objectives,notes,homework,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'planned')")
                ->execute([$progId,$userId,$levelId,$startDate,$endDate,$surahNum,$sen,$sar,(int)($_POST['ayah_from']??0)?:null,(int)($_POST['ayah_to']??0)?:null,$_POST['topic_en']??'',$_POST['topic_ar']??'',$_POST['objectives']??'',$_POST['notes']??'',$_POST['homework']??'']);
        } else {
            $pdo->prepare("INSERT INTO lesson_plans (program_id,teacher_id,level_id,plan_type,start_date,end_date,title_en,title_ar,description,status) VALUES (?,?,?,'monthly',?,?,?,?,?,'active')")
                ->execute([$progId,$userId,$levelId,$startDate,$endDate,$_POST['topic_en']??'',$_POST['topic_ar']??'',$_POST['notes']??'']);
        }
        setFlash('success','✅ Lesson saved');
        header("Location: /teacher/course.php?id=$courseId&prog=$progId&tab=lessons"); exit;
    }

    if($action==='update_status'){
    $allowedStatus = ['planned','completed','cancelled'];
    if (!in_array($_POST['status'] ?? '', $allowedStatus)) { echo json_encode(['ok'=>false,'error'=>'Invalid status']); exit; }
        $pdo->prepare("UPDATE weekly_lessons SET status=? WHERE id=? AND teacher_id=?")->execute([$_POST['status'],(int)$_POST['lid'],$userId]);
        header("Location: /teacher/course.php?id=$courseId&prog=$progId&tab=lessons"); exit;
    }

    if($action==='delete_lesson'){
        $pdo->prepare("DELETE FROM weekly_lessons WHERE id=? AND teacher_id=?")->execute([(int)$_POST['lid'],$userId]);
        header("Location: /teacher/course.php?id=$courseId&prog=$progId&tab=lessons"); exit;
    }

    if($action==='save_attendance'){
        $date=$_POST['date']??date('Y-m-d');
        foreach(($_POST['status']??[]) as $sid=>$st){
            $sid=(int)$sid;
            $pdo->prepare("INSERT INTO attendance (student_id,class_id,date,status,recorded_by) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),recorded_by=VALUES(recorded_by)")
                ->execute([$sid,$classId,$date,$st,$userId]);
            if($st==='absent'){
                $r=$pdo->prepare("SELECT s.full_name,s.parent_id,s.user_id FROM students s WHERE s.id=?");
                $r->execute([$sid]); $r=$r->fetch();
                if($r){
                    $notifyId=$r['parent_id']??$r['user_id']??null;
                    if($notifyId){ $ex=$pdo->prepare("SELECT id FROM users WHERE id=? AND is_active=1"); $ex->execute([$notifyId]); if($ex->fetch()) $pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'warning')")->execute([$notifyId,'Absent',$r['full_name'].' was absent on '.$date]); }
                }
            }
        }
        setFlash('success','✅ Attendance saved');
        header("Location: /teacher/course.php?id=$courseId&prog=$progId&tab=attendance"); exit;
    }

    if($action==='save_progress'){
        $studentId=(int)$_POST['student_id'];
        $surahNum=(int)($_POST['surah_number']??0);
        // For non-surah courses (Arabic Letters, Stories etc), use slot 200-254 (safe for TINYINT UNSIGNED max 255)
        // Each course type gets its own slot number so records don't collide
        if(!$surahNum){
            $courseSlots=[
                // Slot B (Children)
                'Arabic Letters'=>200,'Short Words & Reading'=>201,
                'Basic Tajweed for Kids'=>202,'Quran Stories & Values'=>203,
                // Slot A (Students) - non-surah courses
                'Tajweed Basics'=>210,'Quran for Converts'=>211,
                'Advanced Tajweed'=>212,
            ];
            $surahNum=$courseSlots[$course['name_en']]??250;
        }
        $surahInfo=null; foreach($surahs as $s){ if($s[0]==$surahNum){$surahInfo=$s;break;} }
        // If no surah matched, create generic entry
        if(!$surahInfo) $surahInfo=[$surahNum,'Record '.date('d M Y'),'سجل '.date('d M Y'),0];

        if($classId){
            $pdo->prepare("INSERT INTO progress (student_id,class_id,surah_number,surah_name_en,surah_name_ar,ayah_from,ayah_to,tajweed_level,memorization_pct,evaluation,notes,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE tajweed_level=VALUES(tajweed_level),memorization_pct=VALUES(memorization_pct),evaluation=VALUES(evaluation),notes=VALUES(notes),updated_by=VALUES(updated_by),updated_at=NOW()")
                ->execute([$studentId,$classId,$surahNum,$surahInfo[1],$surahInfo[2],(int)($_POST['ayah_from']??0),(int)($_POST['ayah_to']??0),$_POST['tajweed_level']??1,(int)($_POST['memorization_pct']??0),$_POST['evaluation']??'Good',$_POST['notes']??'',$userId]);
            $sr=$pdo->prepare("SELECT full_name,parent_id,user_id FROM students WHERE id=?"); $sr->execute([$studentId]); $sr=$sr->fetch();
            if($sr){ $nid=$sr['parent_id']??$sr['user_id']??null; if($nid){ $ex=$pdo->prepare("SELECT id FROM users WHERE id=? AND is_active=1"); $ex->execute([$nid]); if($ex->fetch()) $pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'success')")->execute([$nid,'Progress',$sr['full_name'].' — '.($surahInfo[2]??'').' — '.($_POST['evaluation']??'')]); } }
        }
        setFlash('success','✅ Progress saved');
        header("Location: /teacher/course.php?id=$courseId&prog=$progId&tab=progress&student=$studentId"); exit;
    }

    if($action==='delete_progress'){
        $pdo->prepare("DELETE FROM progress WHERE student_id=? AND class_id=? AND surah_number=?")->execute([(int)$_POST['student_id'],$classId,(int)$_POST['surah_number']]);
        setFlash('info','Deleted');
        header("Location: /teacher/course.php?id=$courseId&prog=$progId&tab=progress&student=".$_POST['student_id']); exit;
    }
}

// Tab data
$lessons=[]; $monthlyPlans=[];
if($tab==='lessons'){
    $wl=$pdo->prepare("SELECT wl.*,cl.name_en as level_name FROM weekly_lessons wl LEFT JOIN course_levels cl ON cl.id=wl.level_id WHERE wl.program_id=? AND wl.teacher_id=? ORDER BY wl.week_start DESC LIMIT 10");
    $wl->execute([$progId,$userId]); $lessons=$wl->fetchAll();
    $mp=$pdo->prepare("SELECT lp.*,cl.name_en as level_name FROM lesson_plans lp LEFT JOIN course_levels cl ON cl.id=lp.level_id WHERE lp.program_id=? AND lp.teacher_id=? ORDER BY lp.start_date DESC LIMIT 5");
    $mp->execute([$progId,$userId]); $monthlyPlans=$mp->fetchAll();
}

// Attendance data
$todayAtt=[];$attHistory=[];
if($tab==='attendance' && $classId){
    $date=$_GET['date']??date('Y-m-d');
    $ta=$pdo->prepare("SELECT student_id,status FROM attendance WHERE class_id=? AND date=?");
    $ta->execute([$classId,$date]); foreach($ta->fetchAll() as $a) $todayAtt[$a['student_id']]=$a['status'];
    $ah=$pdo->prepare("SELECT a.date,a.status,s.full_name FROM attendance a JOIN students s ON s.id=a.student_id WHERE a.class_id=? ORDER BY a.date DESC,s.full_name LIMIT 30");
    $ah->execute([$classId]); $attHistory=$ah->fetchAll();
}

// Progress data
$studentProgress=[];
if($tab==='progress' && $selStudent && $classId){
    $sp=$pdo->prepare("SELECT * FROM progress WHERE student_id=? AND class_id=? ORDER BY surah_number");
    $sp->execute([$selStudent,$classId]); $studentProgress=$sp->fetchAll();
}

$statusColors=['planned'=>['#FEF3C7','#92400E','⏳'],'in_progress'=>['#DBEAFE','#1D4ED8','🔵'],'completed'=>['#D1FAE5','#065F46','✅']];
$evalBg=['Excellent'=>'#D1FAE5','Good'=>'#DBEAFE','Needs Improvement'=>'#FEF3C7','Repeat'=>'#FEE2E2'];
$evalTc=['Excellent'=>'#065F46','Good'=>'#1D4ED8','Needs Improvement'=>'#92400E','Repeat'=>'#991B1B'];

// Course-specific lesson templates
$courseTemplates=[
'Arabic Letters'=>[['letter','letters','Letter of the Week','حرف الأسبوع'],['activity','select_act','Activity Type','نوع النشاط'],['topic_ar','text_ar','Topic (AR)','الموضوع'],['topic_en','text_en','Topic (EN)','Topic'],['objectives','textarea','Objectives','الأهداف'],['homework','textarea','Homework','الواجب'],['notes','textarea','Notes','ملاحظات']],
'Short Words & Reading'=>[['topic_ar','text_ar','Words/Topic (AR)','الكلمات'],['topic_en','text_en','Words/Topic (EN)','Words/Topic'],['objectives','textarea','Words to Practice','الكلمات'],['homework','textarea','Homework','الواجب'],['notes','textarea','Notes','ملاحظات']],
'Al-Fatihah & Short Surahs'=>[['surah_number','surah_short','Surah','السورة'],['ayah_from','number','From Ayah','من آية'],['ayah_to','number','To Ayah','إلى آية'],['topic_ar','text_ar','Topic (AR)','الموضوع'],['topic_en','text_en','Topic (EN)','Topic'],['objectives','textarea','Goals','الأهداف'],['homework','textarea','Repetitions','التكرار'],['notes','textarea','Notes','ملاحظات']],
'Juz Amma for Kids'=>[['surah_number','surah_juzamma','Surah','السورة'],['ayah_from','number','From Ayah','من آية'],['ayah_to','number','To Ayah','إلى آية'],['topic_ar','text_ar','Title (AR)','العنوان'],['topic_en','text_en','Title (EN)','Title'],['activity','fun_activity','Fun Activity','نشاط ممتع'],['objectives','textarea','What kids learn','ماذا يتعلم'],['homework','textarea','Home Practice','التدريب'],['notes','textarea','Notes','ملاحظات']],
'Basic Tajweed for Kids'=>[['tajweed','tajweed_rule','Tajweed Rule','حكم التجويد'],['topic_ar','text_ar','Title (AR)','العنوان'],['topic_en','text_en','Title (EN)','Title'],['objectives','textarea','Rules to Learn','الأحكام'],['homework','textarea','Practice','التدريب'],['notes','textarea','Notes','ملاحظات']],
'Quran Stories & Values'=>[['story','story_select','Story / Value','القصة/القيمة'],['topic_ar','text_ar','Session Title (AR)','العنوان'],['topic_en','text_en','Session Title (EN)','Title'],['objectives','textarea','Lessons & Values','الدروس'],['homework','textarea','Activity','نشاط'],['notes','textarea','Notes','ملاحظات']],
'Juz Amma Memorization'=>[['surah_number','surah_all','Surah','السورة'],['ayah_from','number','From Ayah','من آية'],['ayah_to','number','To Ayah','إلى آية'],['method','memorize_method','Method','الطريقة'],['topic_ar','text_ar','Topic (AR)','الموضوع'],['topic_en','text_en','Topic (EN)','Topic'],['objectives','textarea','Goals','الأهداف'],['homework','textarea','Homework','الواجب'],['notes','textarea','Notes','ملاحظات']],
'Juz Tabarak Memorization'=>[['surah_number','surah_all','Surah','السورة'],['ayah_from','number','From Ayah','من آية'],['ayah_to','number','To Ayah','إلى آية'],['method','memorize_method','Method','الطريقة'],['topic_ar','text_ar','Topic (AR)','الموضوع'],['topic_en','text_en','Topic (EN)','Topic'],['objectives','textarea','Goals','الأهداف'],['homework','textarea','Homework','الواجب'],['notes','textarea','Notes','ملاحظات']],
'Tajweed Basics'=>[['tajweed','tajweed_rule_select','Rule Studied','الحكم المدروس'],['score','pct_slider','Mastery %','نسبة الإتقان'],['tajweed','tajweed_stars','Application Level','مستوى التطبيق'],['evaluation','eval_select','Evaluation','التقييم'],['notes','textarea','Notes','ملاحظات']],
'Advanced Tajweed'=>[['tajweed','tajweed_rule_select','Advanced Rule','الحكم المتقدم'],['score','pct_slider','Mastery %','نسبة الإتقان'],['tajweed','tajweed_stars','Level','المستوى'],['evaluation','eval_select','Evaluation','التقييم'],['notes','textarea','Notes','ملاحظات']],
'Fluent Recitation'=>[['surah_number','surah_all','Surah','السورة'],['ayah_from','number','From Ayah','من آية'],['ayah_to','number','To Ayah','إلى آية'],['topic_ar','text_ar','Topic (AR)','الموضوع'],['topic_en','text_en','Topic (EN)','Topic'],['objectives','textarea','Goals','أهداف التلاوة'],['homework','textarea','Homework','الواجب'],['notes','textarea','Notes','ملاحظات']],
'Tafseer Introduction'=>[['surah_number','surah_all','Surah','السورة'],['ayah_from','number','From Ayah','من آية'],['ayah_to','number','To Ayah','إلى آية'],['topic_ar','text_ar','Tafseer Topic (AR)','موضوع التفسير'],['topic_en','text_en','Tafseer Topic (EN)','Topic'],['objectives','textarea','Key Meanings','المعاني'],['homework','textarea','Reflection','تأمل'],['notes','textarea','Notes','ملاحظات']],
'Quran for Converts'=>[['score','pct_slider','Reading Progress %','نسبة تقدم القراءة'],['tajweed','tajweed_stars','Level','المستوى'],['topic_ar','text_ar','Topic (AR)','الموضوع'],['topic_en','text_en','Topic (EN)','Topic'],['evaluation','eval_select','Evaluation','التقييم'],['notes','textarea','Notes','ملاحظات']],
];
$tpl=$courseTemplates[$course['name_en']]??[['topic_ar','text_ar','Topic (AR)','الموضوع'],['topic_en','text_en','Topic (EN)','Topic'],['objectives','textarea','Objectives','الأهداف'],['homework','textarea','Homework','الواجب'],['notes','textarea','Notes','ملاحظات']];

$shortSurahs=[[1,'Al-Fatihah','الفاتحة'],[93,'Ad-Duha','الضحى'],[94,'Ash-Sharh','الشرح'],[95,'At-Tin','التين'],[96,'Al-Alaq','العلق'],[97,'Al-Qadr','القدر'],[99,'Az-Zalzalah','الزلزلة'],[100,'Al-Adiyat','العاديات'],[101,'Al-Qariah','القارعة'],[102,'At-Takathur','التكاثر'],[103,'Al-Asr','العصر'],[104,'Al-Humazah','الهمزة'],[105,'Al-Fil','الفيل'],[106,'Quraysh','قريش'],[107,'Al-Maun','الماعون'],[108,'Al-Kawthar','الكوثر'],[109,'Al-Kafirun','الكافرون'],[110,'An-Nasr','النصر'],[111,'Al-Masad','المسد'],[112,'Al-Ikhlas','الإخلاص'],[113,'Al-Falaq','الفلق'],[114,'An-Nas','الناس']];
$arabicLetters=['أ','ب','ت','ث','ج','ح','خ','د','ذ','ر','ز','س','ش','ص','ض','ط','ظ','ع','غ','ف','ق','ك','ل','م','ن','هـ','و','ي'];

include '../includes/header.php';
?>
<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">

<!-- Breadcrumb -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;font-size:12px;color:var(--gray-400)">
  <a href="/teacher/programs.php?tab=mine&prog=<?=$progId?>" style="color:var(--gray-400);text-decoration:none">📚 <?=$isRtl?'برامجي':'My Programs'?></a>
  <span>›</span>
  <span style="color:var(--green-dark);font-weight:600"><?=h($isRtl?$course['name_ar']:$course['name_en'])?></span>
</div>

<!-- Course Header -->
<div style="background:<?=$slotGrad?>;border-radius:16px;padding:20px;margin-bottom:16px;color:#fff">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div style="display:flex;align-items:center;gap:12px">
      <span style="font-size:2.2rem"><?=$course['icon']?></span>
      <div>
        <h1 style="font-size:1.2rem;font-weight:800;color:#fff;margin:0"><?=h($isRtl?$course['name_ar']:$course['name_en'])?></h1>
        <div style="font-size:12px;opacity:.8;margin-top:2px">
          🕌 <?=h($isRtl?$prog['mosque_ar']:$prog['mosque_en'])?>
          · <?=$isChild?'👶 Children':'🎓 Students'?>
          · Slot <?=$prog['slot']?>
          · <?=str_replace(',',' · ',$prog['days'])?>
        </div>
      </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <?php if($isActiveCourse):?>
      <span style="background:#D1FAE5;color:#065F46;padding:5px 12px;border-radius:99px;font-size:11px;font-weight:700">✅ <?=$isRtl?'الدورة الحالية':'Active Course'?></span>
      <?php else:?>
      <form method="POST" style="display:inline">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="set_active">
        <button type="submit" style="background:rgba(255,255,255,.2);color:#fff;border:1.5px solid rgba(255,255,255,.5);border-radius:99px;padding:5px 14px;font-size:11px;font-weight:600;cursor:pointer">
          🚀 <?=$isRtl?'تفعيل':'Set Active'?>
        </button>
      </form>
      <?php endif;?>
    </div>
  </div>
</div>

<!-- Students Quick Row -->
<?php if(!empty($students)):?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:12px;padding:12px 16px;margin-bottom:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
  <span style="font-size:12px;font-weight:700;color:var(--gray-400);text-transform:uppercase"><?=$isChild?($isRtl?'الأطفال:':'Children:'):($isRtl?'الطلاب:':'Students:')?></span>
  <?php foreach($students as $s):
    $isSel=$s['id']==$selStudent && $tab==='progress';
    $attPct=$s['total_att']>0?round($s['present_cnt']/$s['total_att']*100):0;
  ?>
  <a href="?id=<?=$courseId?>&prog=<?=$progId?>&tab=progress&student=<?=$s['id']?>"
     style="display:flex;align-items:center;gap:8px;padding:6px 12px;border-radius:99px;border:2px solid <?=$isSel?$slotColor:'var(--gray-100)'?>;background:<?=$isSel?$slotBg:'var(--gray-50)'?>;text-decoration:none;transition:.15s">
    <div style="width:28px;height:28px;border-radius:50%;background:<?=$isSel?$slotColor:'#4C1D95'?>;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0"><?=strtoupper(substr($s['full_name'],0,1))?></div>
    <div>
      <div style="font-weight:600;font-size:12px;color:<?=$isSel?$slotColor:'var(--green-dark)'?>"><?=h(explode(' ',$s['full_name'])[0])?></div>
      <div style="font-size:10px;color:var(--gray-400)"><?=$s['surahs_done']?>📖 · <?=$attPct?>%✅</div>
    </div>
  </a>
  <?php endforeach;?>
</div>
<?php endif;?>

<!-- Tabs -->
<div style="display:flex;background:var(--gray-50);border-radius:12px;padding:4px;margin-bottom:16px;gap:3px">
  <?php foreach([
    ['lessons','📅 '.($isRtl?'الدروس':'Lessons')],
    ['attendance','✅ '.($isRtl?'الحضور':'Attendance')],
    ['progress','📈 '.($isRtl?'التقدم':'Progress')],
  ] as [$t,$l]):?>
  <a href="?id=<?=$courseId?>&prog=<?=$progId?>&tab=<?=$t?><?=$t==='progress'&&$selStudent?'&student='.$selStudent:''?>"
     style="flex:1;padding:8px 14px;border-radius:10px;font-size:12px;text-decoration:none;text-align:center;background:<?=$tab===$t?'#fff':'transparent'?>;font-weight:<?=$tab===$t?700:400?>;color:<?=$tab===$t?'var(--green-dark)':'var(--gray-500)'?>">
    <?=$l?>
  </a>
  <?php endforeach;?>
</div>

<!-- ══ LESSONS TAB ══ -->
<?php if($tab==='lessons'):?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

<!-- Add Lesson Form -->
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px">
    <div style="font-weight:700;font-size:13px;color:var(--green-dark)">➕ <?=$isRtl?'إضافة درس':'Add Lesson'?></div>
    <div style="display:flex;background:var(--gray-50);border-radius:8px;padding:3px;gap:2px">
      <button onclick="setLT('weekly')" id="btn-w" style="padding:5px 12px;border-radius:6px;border:none;font-size:11px;font-weight:600;cursor:pointer;background:<?=$slotColor?>;color:#fff">📅 <?=$isRtl?'أسبوعي':'Weekly'?></button>
      <button onclick="setLT('monthly')" id="btn-m" style="padding:5px 12px;border-radius:6px;border:none;font-size:11px;font-weight:600;cursor:pointer;background:transparent;color:var(--gray-500)">🗓️ <?=$isRtl?'شهري':'Monthly'?></button>
    </div>
  </div>
  <form method="POST">
        <?= csrfField() ?>
    <input type="hidden" name="action" value="save_lesson">
    <input type="hidden" name="lesson_type" id="ltInput" value="weekly">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
      <div class="form-group" style="margin:0"><label class="form-label" style="font-size:11px">📅 <?=$isRtl?'البداية':'Start'?></label><input type="date" name="start_date" class="form-control" value="<?=date('Y-m-d')?>" required></div>
      <div class="form-group" id="edDiv" style="margin:0;display:none"><label class="form-label" style="font-size:11px">📅 <?=$isRtl?'النهاية':'End'?></label><input type="date" name="end_date" class="form-control" value="<?=date('Y-m-t')?>"></div>
    </div>
    <?php if(!empty($levels)):?>
    <div class="form-group" style="margin-top:8px"><label class="form-label" style="font-size:11px">🏆 <?=$isRtl?'المستوى':'Level'?></label><select name="level_id" class="form-control form-select"><option value="">--</option><?php foreach($levels as $l):?><option value="<?=$l['id']?>"><?=$l['level_number']?>. <?=h($isRtl?$l['name_ar']:$l['name_en'])?></option><?php endforeach;?></select></div>
    <?php endif;?>

    <?php foreach($tpl as [$fname,$ftype,$labelEn,$labelAr]):
      $dbName=in_array($fname,['topic_ar','topic_en','objectives','homework','notes','ayah_from','ayah_to','surah_number'])?$fname:'notes';
    ?>
    <div class="form-group" style="margin-top:8px">
      <label class="form-label" style="font-size:11px"><?=$isRtl?$labelAr:$labelEn?></label>
      <?php if(in_array($ftype,['text_ar','text_en'])):?>
      <input type="text" name="<?=$dbName?>" class="form-control" dir="<?=$ftype==='text_ar'?'rtl':'ltr'?>" <?=$fname==='topic_ar'||$fname==='topic_en'?'required':''?>>
      <?php elseif($ftype==='textarea'):?>
      <textarea name="<?=$dbName?>" class="form-control" rows="2" placeholder="..."></textarea>
      <?php elseif($ftype==='number'):?>
      <input type="number" name="<?=$dbName?>" class="form-control" min="1">
      <?php elseif($ftype==='surah_short'):?>
      <select name="surah_number" class="form-control form-select"><option value="">--</option><?php foreach($shortSurahs as $s):?><option value="<?=$s[0]?>"><?=$s[0]?>. <?=$s[2]?></option><?php endforeach;?></select>
      <?php elseif($ftype==='surah_juzamma'):?>
      <select name="surah_number" class="form-control form-select"><option value="">--</option><?php foreach(array_filter($surahs,fn($s)=>$s[0]>=78||$s[0]==1) as $s):?><option value="<?=$s[0]?>"><?=$s[0]?>. <?=$s[2]?></option><?php endforeach;?></select>
      <?php elseif($ftype==='surah_all'):?>
      <select name="surah_number" class="form-control form-select"><option value="">--</option><?php foreach($surahs as $s):?><option value="<?=$s[0]?>"><?=$s[0]?>. <?=$s[2]?></option><?php endforeach;?></select>
      <?php elseif($ftype==='letters'):?>
      <select name="notes" class="form-control form-select"><option value="">--</option><?php foreach($arabicLetters as $l):?><option value="حرف <?=$l?>"><?=$l?></option><?php endforeach;?></select>
      <?php elseif($ftype==='select_act'):?>
      <select name="notes" class="form-control form-select"><option value="">--</option><?php foreach(['Writing Practice','Reading Aloud','Singing','Drawing','Flashcards','Interactive Game'] as $o):?><option><?=$o?></option><?php endforeach;?></select>
      <?php elseif($ftype==='fun_activity'):?>
      <select name="notes" class="form-control form-select"><option value="">--</option><?php foreach(['Group Repetition','Sing Along','Story Time','Coloring Sheet','Quiz Game','Role Play'] as $o):?><option><?=$o?></option><?php endforeach;?></select>
      <?php elseif($ftype==='tajweed_rule'):?>
      <select name="notes" class="form-control form-select"><option value="">--</option><?php foreach(['Noon Sakinah - Idgham','Noon Sakinah - Ikhfa','Noon Sakinah - Iqlab','Noon Sakinah - Izhar','Meem Sakinah','Madd Rules','Qalqalah','Waqf Rules','Other'] as $o):?><option><?=$o?></option><?php endforeach;?></select>
      <?php elseif($ftype==='story_select'):?>
      <select name="notes" class="form-control form-select"><option value="">--</option><?php foreach(['Story of Prophet Nuh','Story of Prophet Ibrahim','Story of Prophet Musa','Story of Prophet Muhammad ﷺ','Value: Honesty','Value: Kindness','Value: Patience','Value: Gratitude','Other'] as $o):?><option><?=$o?></option><?php endforeach;?></select>
      <?php elseif($ftype==='memorize_method'):?>
      <select name="notes" class="form-control form-select"><option value="">--</option><?php foreach(['Tikrar (Repetition)','Talqeen (Dictation)','Listening & Repeat','Writing & Memorize','Group Recitation'] as $o):?><option><?=$o?></option><?php endforeach;?></select>
      <?php endif;?>
    </div>
    <?php endforeach;?>
    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px;background:<?=$slotColor?>;border-color:<?=$slotColor?>">💾 <?=$isRtl?'حفظ الدرس':'Save Lesson'?></button>
  </form>
</div>

<!-- Lessons History -->
<div>
  <?php if(!empty($lessons)):?>
  <h3 style="font-size:13px;font-weight:700;color:var(--green-dark);margin:0 0 10px">📅 <?=$isRtl?'الدروس الأسبوعية':'Weekly Lessons'?> (<?=count($lessons)?>)</h3>
  <?php foreach($lessons as $wl): $sc=$statusColors[$wl['status']]??['#F3F4F6','#374151','⏳']; $isTW=date('Y-m-d')>=$wl['week_start']&&date('Y-m-d')<=$wl['week_end'];?>
  <div style="border:1px solid <?=$isTW?'#86EFAC':'var(--gray-100)'?>;border-radius:10px;padding:12px;margin-bottom:8px;background:<?=$isTW?'#F0FDF4':'#fff'?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:6px">
      <div>
        <div style="font-weight:700;font-size:13px;color:var(--green-dark)"><?=h($isRtl?$wl['topic_ar']:$wl['topic_en'])?></div>
        <div style="font-size:10px;color:var(--gray-400)"><?=date('d M',strtotime($wl['week_start']))?> – <?=date('d M Y',strtotime($wl['week_end']))?><?=$isTW?' 🟢':''?></div>
        <?php if($wl['surah_name_ar']):?><div style="font-size:11px;color:var(--gray-600)">📖 <?=h($wl['surah_name_ar'])?><?=$wl['ayah_from']?' (ayah '.$wl['ayah_from'].'–'.$wl['ayah_to'].')':''?></div><?php endif;?>
        <?php if($wl['level_name']):?><div style="font-size:10px;color:var(--gray-500)">🏆 <?=h($wl['level_name'])?></div><?php endif;?>
        <?php if($wl['homework']):?><div style="font-size:10px;color:var(--info)">📋 <?=h($wl['homework'])?></div><?php endif;?>
        <?php if($wl['objectives']):?><div style="font-size:10px;color:var(--gray-500)">🎯 <?=h($wl['objectives'])?></div><?php endif;?>
      </div>
      <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-end;flex-shrink:0">
        <span style="background:<?=$sc[0]?>;color:<?=$sc[1]?>;padding:1px 7px;border-radius:99px;font-size:10px;font-weight:700"><?=$sc[2]?> <?=ucfirst($wl['status'])?></span>
        <div style="display:flex;gap:3px">
          <?php foreach(['planned'=>'⏳','in_progress'=>'🔵','completed'=>'✅'] as $st=>$ic): if($st===$wl['status']) continue;?>
          <form method="POST" style="display:inline">
        <?= csrfField() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="lid" value="<?=$wl['id']?>"><input type="hidden" name="status" value="<?=$st?>"><button type="submit" style="background:<?=$statusColors[$st][0]?>;color:<?=$statusColors[$st][1]?>;border:none;border-radius:5px;padding:1px 6px;font-size:10px;cursor:pointer"><?=$ic?></button></form>
          <?php endforeach;?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
        <?= csrfField() ?><input type="hidden" name="action" value="delete_lesson"><input type="hidden" name="lid" value="<?=$wl['id']?>"><button type="submit" style="background:#FEE2E2;color:#DC2626;border:none;border-radius:5px;padding:1px 6px;font-size:10px;cursor:pointer">🗑</button></form>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach;?>
  <?php endif;?>

  <?php if(!empty($monthlyPlans)):?>
  <h3 style="font-size:13px;font-weight:700;color:var(--green-dark);margin:14px 0 10px">🗓️ <?=$isRtl?'الخطط الشهرية':'Monthly Plans'?></h3>
  <?php foreach($monthlyPlans as $mp): $isAct=date('Y-m-d')>=$mp['start_date']&&date('Y-m-d')<=$mp['end_date'];?>
  <div style="border:1px solid <?=$isAct?'#86EFAC':'var(--gray-100)'?>;border-radius:10px;padding:12px;margin-bottom:8px;background:<?=$isAct?'#F0FDF4':'#fff'?>">
    <div style="font-weight:700;font-size:13px;color:var(--green-dark)"><?=h($isRtl?$mp['title_ar']:$mp['title_en'])?></div>
    <div style="font-size:10px;color:var(--gray-400)"><?=date('d M',strtotime($mp['start_date']))?> – <?=date('d M Y',strtotime($mp['end_date']))?><?=$isAct?' 🟢':''?></div>
    <?php if($mp['description']):?><div style="font-size:11px;color:var(--gray-600);margin-top:6px;white-space:pre-line;background:var(--gray-50);border-radius:6px;padding:6px 8px"><?=h($mp['description'])?></div><?php endif;?>
  </div>
  <?php endforeach;?>
  <?php endif;?>

  <?php if(empty($lessons)&&empty($monthlyPlans)):?>
  <div style="background:#fff;border:1px solid var(--gray-100);border-radius:12px;padding:40px;text-align:center;color:var(--gray-300)">
    <div style="font-size:2.5rem">📅</div>
    <div style="margin-top:8px;font-size:13px"><?=$isRtl?'لا توجد دروس بعد':'No lessons yet — add your first lesson'?></div>
  </div>
  <?php endif;?>
</div>
</div>

<!-- ══ ATTENDANCE TAB ══ -->
<?php elseif($tab==='attendance'):?>
<?php $attDate=$_GET['date']??date('Y-m-d');?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;overflow:hidden">
  <!-- Date picker -->
  <div style="padding:14px 18px;background:var(--gray-50);border-bottom:1px solid var(--gray-100);display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <form method="GET" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      <input type="hidden" name="id" value="<?=$courseId?>">
      <input type="hidden" name="prog" value="<?=$progId?>">
      <input type="hidden" name="tab" value="attendance">
      <label class="form-label" style="margin:0;font-size:13px">📅 <?=$isRtl?'التاريخ':'Date'?></label>
      <input type="date" name="date" class="form-control" value="<?=$attDate?>" max="<?=date('Y-m-d')?>" onchange="this.form.submit()" style="width:160px">
    </form>
    <span style="font-size:13px;color:var(--gray-500)"><?=count($students)?> <?=$isRtl?'طالب':'students'?></span>
    <?php
    $presentToday=count(array_filter($todayAtt,fn($s)=>$s==='present'));
    $absentToday=count(array_filter($todayAtt,fn($s)=>$s==='absent'));
    ?>
    <?php if($presentToday):?><span style="background:#D1FAE5;color:#065F46;padding:2px 10px;border-radius:99px;font-size:11px;font-weight:700">✅ <?=$presentToday?> Present</span><?php endif;?>
    <?php if($absentToday):?><span style="background:#FEE2E2;color:#DC2626;padding:2px 10px;border-radius:99px;font-size:11px;font-weight:700">❌ <?=$absentToday?> Absent</span><?php endif;?>
  </div>

  <?php if(empty($students)):?>
  <div style="padding:40px;text-align:center;color:var(--gray-300)"><div style="font-size:2rem">📭</div><div style="margin-top:8px"><?=$isRtl?'لا يوجد طلاب':'No students enrolled'?></div></div>
  <?php else:?>
  <form method="POST">
        <?= csrfField() ?>
    <input type="hidden" name="action" value="save_attendance">
    <input type="hidden" name="date" value="<?=$attDate?>">
    <!-- Quick buttons -->
    <div style="padding:10px 16px;display:flex;gap:8px;border-bottom:1px solid var(--gray-50)">
      <button type="button" onclick="setAll('present')" style="background:#D1FAE5;color:#065F46;border:none;border-radius:8px;padding:5px 14px;font-size:12px;cursor:pointer;font-weight:600">✅ All Present</button>
      <button type="button" onclick="setAll('absent')" style="background:#FEE2E2;color:#DC2626;border:none;border-radius:8px;padding:5px 14px;font-size:12px;cursor:pointer;font-weight:600">❌ All Absent</button>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead><tr style="background:linear-gradient(135deg,<?=$isChild?'#92400E,#D97706':'#1D4ED8,#4F46E5'?>)">
        <th style="padding:10px 16px;color:#fff;text-align:left">Student</th>
        <?php foreach(['present'=>($isRtl?'✅ حاضر':'✅ Present'),'absent'=>($isRtl?'❌ غائب':'❌ Absent'),'late'=>($isRtl?'⏰ متأخر':'⏰ Late'),'excused'=>($isRtl?'📋 بعذر':'📋 Excused')] as $val=>$label):?>
        <th style="padding:10px 12px;color:#fff;text-align:center;font-size:11px"><?=$label?></th>
        <?php endforeach;?>
      </tr></thead>
      <tbody>
      <?php foreach($students as $s):
        $current=$todayAtt[$s['id']]??'present';
      ?>
      <tr style="border-bottom:1px solid var(--gray-50)" id="row-<?=$s['id']?>">
        <td style="padding:12px 16px">
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:34px;height:34px;border-radius:50%;background:<?=$slotBg?>;color:<?=$slotColor?>;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0"><?=strtoupper(substr($s['full_name'],0,1))?></div>
            <div>
              <div style="font-weight:700"><?=h($s['full_name'])?></div>
              <?php if($s['full_name_ar']):?><div style="font-size:11px;color:var(--gray-400)"><?=h($s['full_name_ar'])?></div><?php endif;?>
            </div>
          </div>
        </td>
        <?php foreach(['present'=>'#065F46','absent'=>'#DC2626','late'=>'#D97706','excused'=>'#1D4ED8'] as $val=>$col):?>
        <td style="padding:12px;text-align:center">
          <input type="radio" name="status[<?=$s['id']?>]" value="<?=$val?>" <?=$current===$val?'checked':''?>
            style="width:18px;height:18px;accent-color:<?=$col?>;cursor:pointer"
            onchange="highlightRow(<?=$s['id']?>,'<?=$val?>')">
        </td>
        <?php endforeach;?>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
    <div style="padding:14px 16px">
      <button type="submit" class="btn btn-primary" style="background:<?=$slotColor?>;border-color:<?=$slotColor?>">💾 <?=$isRtl?'حفظ الحضور':'Save Attendance'?></button>
    </div>
  </form>
  <?php endif;?>
</div>

<!-- Attendance History -->
<?php if(!empty($attHistory)):?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:16px;margin-top:14px">
  <h3 style="font-size:13px;font-weight:700;color:var(--green-dark);margin:0 0 12px">📋 <?=$isRtl?'سجل الحضور':'Attendance History'?></h3>
  <div style="max-height:300px;overflow-y:auto">
    <table style="width:100%;border-collapse:collapse;font-size:12px">
      <thead><tr style="background:var(--gray-50)"><th style="padding:8px 12px;text-align:left">Student</th><th style="padding:8px 12px;text-align:center">Date</th><th style="padding:8px 12px;text-align:center">Status</th></tr></thead>
      <tbody>
      <?php foreach($attHistory as $ah):
        $sc=['present'=>['#D1FAE5','#065F46','✅'],'absent'=>['#FEE2E2','#DC2626','❌'],'late'=>['#FEF3C7','#92400E','⏰'],'excused'=>['#DBEAFE','#1D4ED8','📋']];
        $s=$sc[$ah['status']]??['#F3F4F6','#374151','?'];
      ?>
      <tr style="border-bottom:1px solid var(--gray-50)">
        <td style="padding:7px 12px;font-weight:600"><?=h($ah['full_name'])?></td>
        <td style="padding:7px 12px;text-align:center;color:var(--gray-500)"><?=date('d M Y',strtotime($ah['date']))?></td>
        <td style="padding:7px 12px;text-align:center"><span style="background:<?=$s[0]?>;color:<?=$s[1]?>;padding:1px 8px;border-radius:99px;font-size:10px;font-weight:700"><?=$s[2]?> <?=ucfirst($ah['status'])?></span></td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
</div>
<?php endif;?>

<!-- ══ PROGRESS TAB ══ -->
<?php elseif($tab==='progress'):?>
<?php if(empty($students)):?>
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:60px;text-align:center;color:var(--gray-300)">
  <div style="font-size:3rem">📖</div>
  <div style="margin-top:12px"><?=$isRtl?'لا يوجد طلاب مسجّلون':'No students enrolled'?></div>
</div>
<?php else:?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

<!-- Progress Form -->
<div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:20px">
  <div style="margin-bottom:14px">
    <div style="font-size:11px;font-weight:700;color:var(--gray-400);text-transform:uppercase;margin-bottom:8px">Select Student</div>
    <div style="display:flex;flex-wrap:wrap;gap:6px">
      <?php foreach($students as $s):
        $isSel=$s['id']==$selStudent;
        $attPct=$s['total_att']>0?round($s['present_cnt']/$s['total_att']*100):0;
      ?>
      <a href="?id=<?=$courseId?>&prog=<?=$progId?>&tab=progress&student=<?=$s['id']?>"
         style="display:flex;align-items:center;gap:7px;padding:7px 12px;border-radius:99px;border:2px solid <?=$isSel?$slotColor:'var(--gray-100)'?>;background:<?=$isSel?$slotBg:'var(--gray-50)'?>;text-decoration:none">
        <div style="width:26px;height:26px;border-radius:50%;background:<?=$isSel?$slotColor:'#4C1D95'?>;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px"><?=strtoupper(substr($s['full_name'],0,1))?></div>
        <span style="font-weight:600;font-size:12px;color:<?=$isSel?$slotColor:'var(--green-dark)'?>"><?=h(explode(' ',$s['full_name'])[0])?></span>
      </a>
      <?php endforeach;?>
    </div>
  </div>

  <?php if($selStudentInfo):?>
  <div style="padding-top:14px;border-top:1px solid var(--gray-100)">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
      <span style="font-size:1.2rem"><?=$course['icon']?></span>
      <div>
        <div style="font-weight:700;font-size:13px;color:<?=$slotColor?>"><?=h($isRtl?$course['name_ar']:$course['name_en'])?></div>
        <div style="font-size:11px;color:var(--gray-400)"><?=h($selStudentInfo['full_name'])?></div>
      </div>
    </div>
    <form method="POST" id="progForm">
        <?= csrfField() ?>
      <input type="hidden" name="action" value="save_progress">
      <input type="hidden" name="student_id" value="<?=$selStudent?>">

      <?php
      // Course-specific progress fields
      $progFields=[
        'Arabic Letters'=>[
          ['letter','letters','Letter Practiced','الحرف المدروس'],
          ['activity','act_select','Activity Type','نوع النشاط'],
          ['score','pct_slider','Score %','النسبة'],
          ['evaluation','eval_select','Evaluation','التقييم'],
          ['notes','textarea','Notes','ملاحظات'],
        ],
        'Short Words & Reading'=>[
          ['words','textarea','Words Practiced','الكلمات المدروسة'],
          ['score','pct_slider','Reading Score %','نسبة القراءة'],
          ['evaluation','eval_select','Evaluation','التقييم'],
          ['notes','textarea','Notes','ملاحظات'],
        ],
        'Al-Fatihah & Short Surahs'=>[
          ['surah_number','surah_short','Surah','السورة'],
          ['ayah_range','ayah_range','Ayah Range','نطاق الآيات'],
          ['score','pct_slider','Memorization %','نسبة الحفظ'],
          ['tajweed','tajweed_stars','Tajweed Level','مستوى التجويد'],
          ['evaluation','eval_select','Evaluation','التقييم'],
          ['notes','textarea','Notes','ملاحظات'],
        ],
        'Juz Amma for Kids'=>[
          ['surah_number','surah_juzamma','Surah','السورة'],
          ['ayah_range','ayah_range','Ayah Range','نطاق الآيات'],
          ['score','pct_slider','Memorization %','نسبة الحفظ'],
          ['activity','kids_activity','Fun Activity','نشاط ممتع'],
          ['tajweed','tajweed_stars','Tajweed Level','مستوى التجويد'],
          ['evaluation','eval_select','Evaluation','التقييم'],
          ['notes','textarea','Notes','ملاحظات'],
        ],
        'Basic Tajweed for Kids'=>[
          ['tajweed_rule','tajweed_rule_select','Tajweed Rule Studied','الحكم المدروس'],
          ['examples','textarea','Examples Given','أمثلة تطبيقية'],
          ['score','pct_slider','Understanding %','نسبة الفهم'],
          ['evaluation','eval_select','Evaluation','التقييم'],
          ['notes','textarea','Notes','ملاحظات'],
        ],
        'Quran Stories & Values'=>[
          ['story','story_select','Story/Value','القصة/القيمة'],
          ['activity','story_activity','Activity','النشاط'],
          ['score','pct_slider','Engagement %','نسبة المشاركة'],
          ['evaluation','eval_select','Evaluation','التقييم'],
          ['notes','textarea','Notes','ملاحظات'],
        ],
        'Juz Amma Memorization'=>[
          ['surah_number','surah_all','Surah','السورة'],
          ['ayah_range','ayah_range','Ayah Range','نطاق الآيات'],
          ['score','pct_slider','Memorization %','نسبة الحفظ'],
          ['tajweed','tajweed_stars','Tajweed Level','مستوى التجويد'],
          ['method','memorize_method','Method Used','الطريقة'],
          ['evaluation','eval_select','Evaluation','التقييم'],
          ['notes','textarea','Notes','ملاحظات'],
        ],
        'Tajweed Basics'=>[
          ['tajweed_rule','tajweed_rule_select','Rule Studied','الحكم المدروس'],
          ['score','pct_slider','Mastery %','نسبة الإتقان'],
          ['tajweed','tajweed_stars','Application Level','مستوى التطبيق'],
          ['evaluation','eval_select','Evaluation','التقييم'],
          ['notes','textarea','Notes','ملاحظات'],
        ],
        'Fluent Recitation'=>[
          ['surah_number','surah_all','Surah Recited','السورة المتلوة'],
          ['ayah_range','ayah_range','Ayah Range','نطاق الآيات'],
          ['score','pct_slider','Fluency %','نسبة الطلاقة'],
          ['tajweed','tajweed_stars','Tajweed Level','مستوى التجويد'],
          ['evaluation','eval_select','Evaluation','التقييم'],
          ['notes','textarea','Notes','ملاحظات'],
        ],
        'Tafseer Introduction'=>[
          ['surah_number','surah_all','Surah','السورة'],
          ['ayah_range','ayah_range','Ayahs Covered','الآيات المغطاة'],
          ['score','pct_slider','Comprehension %','نسبة الفهم'],
          ['evaluation','eval_select','Evaluation','التقييم'],
          ['notes','textarea','Notes','ملاحظات'],
        ],
      ];
      $pf=$progFields[$course['name_en']]??[
        ['surah_number','surah_all','Surah','السورة'],
        ['ayah_range','ayah_range','Ayah Range','نطاق الآيات'],
        ['score','pct_slider','Progress %','نسبة التقدم'],
        ['tajweed','tajweed_stars','Tajweed','التجويد'],
        ['evaluation','eval_select','Evaluation','التقييم'],
        ['notes','textarea','Notes','ملاحظات'],
      ];
      ?>
      <?php foreach($pf as [$fname,$ftype,$lEn,$lAr]):?>
      <div class="form-group" style="margin-top:8px">
        <label class="form-label" style="font-size:12px"><?=$isRtl?$lAr:$lEn?></label>
        <?php if($ftype==='surah_short'):?>
        <select name="surah_number" id="surahSel" class="form-control form-select" onchange="updateAyahs(this)"><option value="">--</option><?php foreach($shortSurahs as $s):?><option value="<?=$s[0]?>" data-ayahs="7"><?=$s[0]?>. <?=$s[2]?></option><?php endforeach;?></select>
        <?php elseif($ftype==='surah_juzamma'):?>
        <select name="surah_number" id="surahSel" class="form-control form-select" onchange="updateAyahs(this)"><option value="">--</option><?php foreach(array_filter($surahs,fn($s)=>$s[0]>=78||$s[0]==1) as $s):?><option value="<?=$s[0]?>" data-ayahs="<?=$s[3]?>"><?=$s[0]?>. <?=$s[2]?></option><?php endforeach;?></select>
        <?php elseif($ftype==='surah_all'):?>
        <select name="surah_number" id="surahSel" class="form-control form-select" onchange="updateAyahs(this)"><option value="">--</option><?php foreach($surahs as $s):?><option value="<?=$s[0]?>" data-ayahs="<?=$s[3]?>"><?=$s[0]?>. <?=$s[2]?> (<?=$s[1]?>)</option><?php endforeach;?></select>
        <?php elseif($ftype==='ayah_range'):?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <input type="number" name="ayah_from" id="ayahFrom" class="form-control" min="1" value="1" placeholder="From">
          <input type="number" name="ayah_to" id="ayahTo" class="form-control" min="1" value="1" placeholder="To">
        </div>
        <?php elseif($ftype==='pct_slider'):?>
        <input type="range" name="memorization_pct" min="0" max="100" value="0" class="form-control" style="padding:4px 0;height:auto" oninput="document.getElementById('pctVal').textContent=this.value">
        <div style="font-size:11px;color:var(--gray-400);text-align:right;margin-top:2px"><span id="pctVal">0</span>%</div>
        <?php elseif($ftype==='tajweed_stars'):?>
        <div style="display:flex;gap:10px"><?php for($i=1;$i<=5;$i++):?>
        <label style="display:flex;flex-direction:column;align-items:center;gap:2px;cursor:pointer"><input type="radio" name="tajweed_level" value="<?=$i?>" <?=$i===1?'checked':''?> style="accent-color:<?=$slotColor?>"><span style="font-size:10px"><?=str_repeat('⭐',$i)?></span></label>
        <?php endfor;?></div>
        <?php elseif($ftype==='eval_select'):?>
        <select name="evaluation" class="form-control form-select">
          <option value="Excellent"><?=$isRtl?'ممتاز':'Excellent'?></option>
          <option value="Good"><?=$isRtl?'جيد':'Good'?></option>
          <option value="Needs Improvement"><?=$isRtl?'يحتاج تحسين':'Needs Improvement'?></option>
          <option value="Repeat"><?=$isRtl?'إعادة':'Repeat'?></option>
        </select>
        <?php elseif($ftype==='textarea'):?>
        <textarea name="<?=$fname==='notes'?'notes':($fname==='examples'?'notes':($fname==='words'?'notes':'notes'))?>" class="form-control" rows="2" placeholder="..."></textarea>
        <?php elseif($ftype==='letters'):?>
        <select name="notes" class="form-control form-select"><option value="">--</option><?php foreach($arabicLetters as $l):?><option value="حرف <?=$l?>"><?=$l?></option><?php endforeach;?></select>
        <?php elseif($ftype==='act_select'):?>
        <select name="notes" class="form-control form-select"><option value="">--</option><?php foreach(['Writing Practice','Reading Aloud','Singing','Drawing','Flashcards','Game'] as $o):?><option><?=$o?></option><?php endforeach;?></select>
        <?php elseif($ftype==='kids_activity'):?>
        <select name="notes" class="form-control form-select"><option value="">--</option><?php foreach(['Group Repetition','Sing Along','Story Time','Coloring','Quiz Game','Role Play'] as $o):?><option><?=$o?></option><?php endforeach;?></select>
        <?php elseif($ftype==='story_select'):?>
        <select name="notes" class="form-control form-select"><option value="">--</option><?php foreach(['Story of Prophet Nuh','Story of Prophet Ibrahim','Story of Prophet Musa','Story of Prophet Muhammad ﷺ','Value: Honesty','Value: Kindness','Value: Patience','Value: Gratitude','Other'] as $o):?><option><?=$o?></option><?php endforeach;?></select>
        <?php elseif($ftype==='story_activity'):?>
        <select name="notes" class="form-control form-select"><option value="">--</option><?php foreach(['Drawing','Role Play','Quiz','Discussion','Craft','Other'] as $o):?><option><?=$o?></option><?php endforeach;?></select>
        <?php elseif($ftype==='tajweed_rule_select'):?>
        <select name="notes" class="form-control form-select"><option value="">--</option><?php foreach(['Noon Sakinah - Idgham','Noon Sakinah - Ikhfa','Noon Sakinah - Iqlab','Noon Sakinah - Izhar','Meem Sakinah','Madd Rules','Qalqalah','Waqf Rules','Other'] as $o):?><option><?=$o?></option><?php endforeach;?></select>
        <?php elseif($ftype==='memorize_method'):?>
        <select name="notes" class="form-control form-select"><option value="">--</option><?php foreach(['Tikrar (Repetition)','Talqeen (Dictation)','Listening & Repeat','Writing & Memorize','Group'] as $o):?><option><?=$o?></option><?php endforeach;?></select>
        <?php endif;?>
      </div>
      <?php endforeach;?>
      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:12px;background:<?=$slotColor?>;border-color:<?=$slotColor?>">💾 <?=$isRtl?'حفظ التقدم':'Save Progress'?></button>
    </form>
  </div>
  <?php endif;?>
</div>

<!-- Progress History -->
<div>
  <?php if($selStudentInfo):
    $totalS=count($studentProgress);
    $doneS=count(array_filter($studentProgress,fn($p)=>$p['memorization_pct']==100));
    $avgP=$totalS>0?round(array_sum(array_column($studentProgress,'memorization_pct'))/$totalS):0;
  ?>
  <!-- Stats -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px">
    <div style="background:<?=$slotBg?>;border-radius:10px;padding:12px;text-align:center"><div style="font-size:1.3rem;font-weight:800;color:<?=$slotColor?>"><?=$totalS?></div><div style="font-size:10px;color:var(--gray-500)">Total</div></div>
    <div style="background:#D1FAE5;border-radius:10px;padding:12px;text-align:center"><div style="font-size:1.3rem;font-weight:800;color:#065F46"><?=$doneS?></div><div style="font-size:10px;color:var(--gray-500)">Done ✅</div></div>
    <div style="background:#EDE9FE;border-radius:10px;padding:12px;text-align:center"><div style="font-size:1.3rem;font-weight:800;color:#4C1D95"><?=$avgP?>%</div><div style="font-size:10px;color:var(--gray-500)">Average</div></div>
  </div>

  <div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:16px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <div style="font-weight:700;font-size:13px;color:var(--green-dark)">📖 <?=h($selStudentInfo['full_name'])?></div>
      <a href="/teacher/student_profile.php?id=<?=$selStudent?>" style="font-size:11px;color:#4C1D95;text-decoration:none;font-weight:600">👤 Full Profile →</a>
    </div>
    <?php if(empty($studentProgress)):?>
    <div style="text-align:center;padding:24px;color:var(--gray-300);font-size:13px"><div style="font-size:2rem">📖</div><div style="margin-top:6px">No progress yet</div></div>
    <?php else:?>
    <div style="max-height:450px;overflow-y:auto">
      <?php foreach($studentProgress as $ep):
        $pct=(int)$ep['memorization_pct'];
        $barColor=$pct===100?'#065F46':($pct>=70?'var(--green-main)':($pct>=40?'var(--warning)':'var(--danger)'));
      ?>
      <div style="border:1px solid var(--gray-100);border-radius:10px;padding:12px;margin-bottom:6px;background:<?=$pct===100?'#F0FDF4':'#fff'?>">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
          <div>
            <div style="font-weight:700;font-size:13px;color:var(--green-dark)"><?=h($ep['surah_name_ar'])?></div>
            <div style="font-size:11px;color:var(--gray-400)"><?=h($ep['surah_name_en'])?> · Ayah <?=$ep['ayah_from']?>–<?=$ep['ayah_to']?></div>
          </div>
          <span style="background:<?=$evalBg[$ep['evaluation']]??'#F3F4F6'?>;color:<?=$evalTc[$ep['evaluation']]??'#374151'?>;padding:1px 8px;border-radius:99px;font-size:10px;font-weight:700"><?=$ep['evaluation']?></span>
        </div>
        <div style="height:5px;background:var(--gray-100);border-radius:3px;overflow:hidden;margin-bottom:5px">
          <div style="height:100%;width:<?=$pct?>%;background:<?=$barColor?>;border-radius:3px"></div>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div style="font-size:11px"><?=str_repeat('⭐',$ep['tajweed_level'])?><?=str_repeat('☆',5-$ep['tajweed_level'])?></div>
          <div style="font-size:11px;font-weight:700;color:<?=$barColor?>"><?=$pct?>%</div>
        </div>
        <?php if($ep['notes']):?><div style="font-size:10px;color:var(--gray-500);margin-top:4px;background:var(--gray-50);padding:3px 7px;border-radius:5px">💬 <?=h($ep['notes'])?></div><?php endif;?>
        <div style="display:flex;gap:4px;margin-top:6px">
          <button onclick="editP(<?=htmlspecialchars(json_encode($ep))?>,this)" style="background:var(--info-pale);color:var(--info);border:none;border-radius:6px;padding:2px 10px;font-size:10px;cursor:pointer;font-weight:600">✏️ Edit</button>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
        <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_progress">
            <input type="hidden" name="student_id" value="<?=$selStudent?>">
            <input type="hidden" name="surah_number" value="<?=$ep['surah_number']?>">
            <button type="submit" style="background:#FEE2E2;color:#DC2626;border:none;border-radius:6px;padding:2px 10px;font-size:10px;cursor:pointer;font-weight:600">🗑 Del</button>
          </form>
        </div>
      </div>
      <?php endforeach;?>
    </div>
    <?php endif;?>
  </div>
  <?php else:?>
  <div style="background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:40px;text-align:center;color:var(--gray-300)">
    <div style="font-size:2rem">👆</div>
    <div style="margin-top:8px;font-size:13px"><?=$isRtl?'اختر طالباً أعلاه':'Select a student above'?></div>
  </div>
  <?php endif;?>
</div>
</div>
<?php endif;?>
<?php endif;?>

</main>
</div>
<script>
function setLT(t){
  document.getElementById('ltInput').value=t;
  document.getElementById('edDiv').style.display=t==='monthly'?'block':'none';
  const c='<?=$slotColor?>';
  document.getElementById('btn-w').style.cssText='padding:5px 12px;border-radius:6px;border:none;font-size:11px;font-weight:600;cursor:pointer;background:'+(t==='weekly'?c:'transparent')+';color:'+(t==='weekly'?'#fff':'var(--gray-500)');
  document.getElementById('btn-m').style.cssText='padding:5px 12px;border-radius:6px;border:none;font-size:11px;font-weight:600;cursor:pointer;background:'+(t==='monthly'?c:'transparent')+';color:'+(t==='monthly'?'#fff':'var(--gray-500)');
}
function setAll(val){
  document.querySelectorAll('input[type=radio][value='+val+']').forEach(r=>{
    r.checked=true; highlightRow(r.name.match(/\d+/)[0],val);
  });
}
function highlightRow(id,val){
  const row=document.getElementById('row-'+id);
  if(!row) return;
  const colors={present:'#F0FDF4',absent:'#FFF5F5',late:'#FFFBEB',excused:'#EFF6FF'};
  row.style.background=colors[val]||'';
}
function updateAyahs(sel){
  const ayahs=sel.options[sel.selectedIndex]?.dataset.ayahs||1;
  document.getElementById('ayahFrom').value=1;
  document.getElementById('ayahTo').value=ayahs;
}
function editP(ep,btn){
  const sel=document.getElementById('surahSel');
  for(let i=0;i<sel.options.length;i++){ if(sel.options[i].value==ep.surah_number){sel.selectedIndex=i;break;} }
  document.querySelector('[name=ayah_from]').value=ep.ayah_from||1;
  document.querySelector('[name=ayah_to]').value=ep.ayah_to||1;
  document.querySelector('[name=memorization_pct]').value=ep.memorization_pct||0;
  document.getElementById('pctVal').textContent=ep.memorization_pct||0;
  document.querySelectorAll('[name=tajweed_level]').forEach(r=>{if(r.value==ep.tajweed_level)r.checked=true;});
  const ev=document.querySelector('[name=evaluation]');
  for(let i=0;i<ev.options.length;i++){if(ev.options[i].value===ep.evaluation){ev.selectedIndex=i;break;}}
  document.querySelector('[name=notes]').value=ep.notes||'';
  document.getElementById('progForm').scrollIntoView({behavior:'smooth'});
}
</script>
<?php include '../includes/footer.php'; ?>
