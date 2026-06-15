<?php
require_once dirname(__DIR__) . '/config.php';
requireRole('teacher');
// This page is replaced by course.php — redirect to My Programs
header('Location: /teacher/programs.php');
exit;
