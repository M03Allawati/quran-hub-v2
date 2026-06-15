<?php
session_start();
$lang = $_POST['lang'] ?? 'en';
$_SESSION['lang'] = in_array($lang, ['en','ar']) ? $lang : 'en';
$redirect = $_POST['redirect'] ?? '/index.php';
// Sanitize redirect
if (!str_starts_with($redirect, '/')) $redirect = '/index.php';
header('Location: ' . $redirect);
exit;
