<?php
// ============================================================
//  Digital Quran Center Hub — Configuration (Railway Ready)
// ============================================================

define('APP_NAME',    'Digital Quran Center Hub');
define('APP_NAME_AR', 'مركز تحفيظ القرآن الرقمي');
define('APP_VERSION', '2.0');
define('BASE_URL',    getenv('APP_URL') ?: 'http://localhost:8099');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// Database (Railway env vars or local fallback)
define('DB_HOST', getenv('MYSQLHOST')     ?: 'db');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'quran_hub');
define('DB_USER', getenv('MYSQLUSER')     ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: 'RootPass@2025');
define('DB_PORT', getenv('MYSQLPORT')     ?: '3306');
define('DB_CHARSET', 'utf8mb4');

// Production error handling
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/quran_hub_errors.log');

// Security Headers
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com;");
}

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_lifetime', 0);

if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

session_start();

// PDO Connection (singleton)
function getPDO(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        return $pdo;
    } catch (PDOException $e) {
        error_log("DB Connection failed: " . $e->getMessage());
        http_response_code(500);
        die("Service temporarily unavailable.");
    }
}

// Auth helpers
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}
function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        header('Location: /dashboard.php');
        exit;
    }
}
function currentUser(): array {
    if (!isLoggedIn()) return [];
    return $_SESSION['user'] ?? [];
}

// Sanitize helpers
function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Flash messages
function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function getFlash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// Unread notifications count
function unreadNotifCount(): int {
    if (!isLoggedIn()) return 0;
    try {
        $pdo = getPDO();
        $st = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
        $st->execute([$_SESSION['user_id']]);
        return (int)$st->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

// Input length limits
define('MAX_INPUT_LENGTH', 500);
define('MAX_NAME_LENGTH', 120);
define('MAX_EMAIL_LENGTH', 120);
define('MAX_MESSAGE_LENGTH', 2000);
define('MAX_TEXT_LENGTH', 5000);

// CSRF Protection
function generateCsrf(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die("Invalid request. Please refresh and try again.");
    }
}
function csrfField(): string {
    return "<input type='hidden' name='csrf_token' value='" . generateCsrf() . "'>";
}

mb_internal_encoding('UTF-8');
