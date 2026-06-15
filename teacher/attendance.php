<?php
require_once dirname(__DIR__) . '/config.php';
requireRole('teacher');
// Attendance is now inside course.php — redirect to My Programs
header('Location: /teacher/programs.php');
exit;
