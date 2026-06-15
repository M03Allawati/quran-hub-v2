<?php
$lang = $_SESSION['lang'] ?? 'en';
$isRtl = $lang === 'ar';
$role = $_SESSION['role'] ?? '';
$current = basename($_SERVER['PHP_SELF']);

function sideLink(string $href, string $icon, string $labelEn, string $labelAr, string $current, bool $isRtl): void {
    $active = (basename($href) === $current || $current === basename($href)) ? 'active' : '';
    $label  = $isRtl ? $labelAr : $labelEn;
    echo "<a href='$href' class='sidebar-link $active'><span class='icon'>$icon</span> $label</a>";
}
?>
<aside class="sidebar">
  <!-- User info -->
  <div style="padding:0 1.2rem 1.2rem;border-bottom:1px solid var(--gray-100);margin-bottom:1rem">
    <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--green-mid),var(--green-dark));display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem;margin-bottom:.6rem">
      <?= strtoupper(substr($_SESSION['user']['full_name'] ?? 'U', 0, 1)) ?>
    </div>
    <div style="font-weight:700;font-size:.9rem;color:var(--green-dark)"><?= h($_SESSION['user']['full_name'] ?? '') ?></div>
    <div style="font-size:.78rem;color:var(--gray-500);text-transform:capitalize"><?= h($role) ?></div>
  </div>

  <?php if ($role === 'admin'): ?>
  <div class="sidebar-section">
    <div class="sidebar-label"><?= $isRtl ? 'القائمة الرئيسية' : 'Main' ?></div>
    <?php sideLink('/dashboard.php','📊','Dashboard','لوحة التحكم',$current,$isRtl) ?>
    <?php sideLink('/admin/mosques.php','🕌','Mosques','المساجد',$current,$isRtl) ?>
    <?php sideLink('/admin/programs.php','📚','Programs','البرامج',$current,$isRtl) ?>
    <?php sideLink('/admin/users.php','👥','Users','المستخدمون',$current,$isRtl) ?>
    <?php sideLink('/admin/students.php','🎓','Students','الطلاب',$current,$isRtl) ?>
    <?php sideLink('/admin/classes.php','📖','Classes','الفصول',$current,$isRtl) ?>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-label"><?= $isRtl ? 'التقارير' : 'Reports' ?></div>
    <?php sideLink('/admin/attendance.php','✅','Attendance','الحضور',$current,$isRtl) ?>
    <?php sideLink('/admin/reports.php','📄','Reports','التقارير',$current,$isRtl) ?>
  </div>

  <?php elseif ($role === 'mosque_admin'): ?>
  <div class="sidebar-section">
    <div class="sidebar-label"><?= $isRtl ? 'مسجدي' : 'My Mosque' ?></div>
    <?php sideLink('/mosque_admin/dashboard.php','📊','Dashboard','لوحتي',$current,$isRtl) ?>
    <?php sideLink('/mosque_admin/programs.php','📚','Programs','البرامج',$current,$isRtl) ?>
    <?php sideLink('/mosque_admin/teachers.php','👨‍🏫','Teachers','المعلمون',$current,$isRtl) ?>
    <?php sideLink('/mosque_admin/students.php','🎓','Students','الطلاب',$current,$isRtl) ?>
    <?php sideLink('/mosque_admin/attendance.php','✅','Attendance','الحضور',$current,$isRtl) ?>
  </div>

  <?php elseif ($role === 'teacher'): ?>
  <div class="sidebar-section">
    <div class="sidebar-label"><?= $isRtl ? 'القائمة' : 'Menu' ?></div>
    <?php sideLink('/dashboard.php','📊','Dashboard','لوحتي',$current,$isRtl) ?>
    <?php sideLink('/teacher/programs.php','📚','My Programs','برامجي',$current,$isRtl) ?>
    <?php sideLink('/teacher/classes.php','📖','My Classes','فصولي',$current,$isRtl) ?>
  </div>

  <?php elseif ($role === 'student'): ?>
  <div class="sidebar-section">
    <div class="sidebar-label"><?= $isRtl ? 'القائمة' : 'Menu' ?></div>
    <?php sideLink('/student/dashboard.php','📊','My Dashboard','لوحتي',$current,$isRtl) ?>
    <?php sideLink('/student/programs.php','📚','My Programs','برامجي',$current,$isRtl) ?>
    <?php sideLink('/student/progress.php','📖','My Progress','تقدمي',$current,$isRtl) ?>
    <?php sideLink('/student/attendance.php','✅','Attendance','حضوري',$current,$isRtl) ?>
    <?php sideLink('/student/classes.php','📋','My Classes','فصولي',$current,$isRtl) ?>
    <?php sideLink('/achievements.php','🏆','Achievements','إنجازاتي',$current,$isRtl) ?>
  </div>

  <?php elseif ($role === 'parent'): ?>
  <div class="sidebar-section">
    <div class="sidebar-label"><?= $isRtl ? 'القائمة' : 'Menu' ?></div>
    <?php sideLink('/dashboard.php','📊','Dashboard','لوحتي',$current,$isRtl) ?>
    <?php sideLink('/parent/children.php','👶','My Children','أطفالي',$current,$isRtl) ?>
    <?php sideLink('/parent/attendance.php','✅','Attendance','الحضور',$current,$isRtl) ?>
    <?php sideLink('/parent/progress.php','📈','Progress','التقدم',$current,$isRtl) ?>
    <?php sideLink('/parent/classes.php','📚','Class Schedule','جدول الحصص',$current,$isRtl) ?>
  </div>
  <?php endif; ?>

  <div class="sidebar-section" style="margin-top:auto">
    <div class="sidebar-label"><?= $isRtl ? 'أخرى' : 'Other' ?></div>
    <?php sideLink('/messages.php','💬','Messages','الرسائل',$current,$isRtl) ?>
    <?php sideLink('/achievements.php','🏆','Achievements','الإنجازات',$current,$isRtl) ?>
    <?php if ($role === 'student'): ?>
    <?php sideLink('/student/mosques.php','🕌','My Mosques','مساجد محافظتي',$current,$isRtl) ?>
    <?php else: ?>
    <?php sideLink('/admin/mosques.php','🕌','Oman Mosques','مساجد عُمان',$current,$isRtl) ?>
    <?php endif; ?>
    <?php sideLink('/admin/announcements.php','📢','Announcements','الإعلانات',$current,$isRtl) ?>
    <?php sideLink('/notifications.php','🔔','Notifications','الإشعارات',$current,$isRtl) ?>
    <?php sideLink('/profile.php','⚙️','Profile','حسابي',$current,$isRtl) ?>
    <a href="/logout.php" class="sidebar-link" style="color:var(--danger)">
      <span class="icon">🚪</span> <?= $isRtl ? 'تسجيل الخروج' : 'Logout' ?>
    </a>
  </div>
</aside>
