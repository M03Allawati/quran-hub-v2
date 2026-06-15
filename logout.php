<?php
require_once __DIR__ . "/config.php";
// Complete session cleanup
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), "", time()-42000,
        $p["path"], $p["domain"], $p["secure"], $p["httponly"]
    );
}
session_destroy();
// Prevent back button from showing cached pages
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Location: /login.php");
exit;
