<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
// ALL API actions require authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$pdo    = getPDO();

if ($action === 'mosques') {
    $gov = trim($_GET['gov'] ?? '');
    if (!$gov) { echo json_encode([]); exit; }
    $stmt = $pdo->prepare(
        "SELECT id, name_en, name_ar, wilayat, governorate, is_grand, capacity
         FROM mosques WHERE governorate=? AND is_active=1
         ORDER BY is_grand DESC, name_en"
    );
    $stmt->execute([$gov]);
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'programs') {
    $mosqueId = (int)($_GET['mosque_id'] ?? 0);
    $type     = $_GET['type'] ?? 'student';
    if (!$mosqueId) { echo json_encode([]); exit; }

    if ($type === 'student') {
        // Students see only Slot A (target_type='student')
        $stmt = $pdo->prepare(
            "SELECT mp.*,
                    u.full_name as teacher_name,
                    (SELECT COUNT(*) FROM program_enrollments pe WHERE pe.program_id=mp.id AND pe.status='active') as enrolled
             FROM mosque_programs mp
             LEFT JOIN users u ON u.id = mp.teacher_id
             WHERE mp.mosque_id=? AND mp.is_active=1 AND mp.target_type='student'
             ORDER BY mp.slot"
        );
        $stmt->execute([$mosqueId]);
    } elseif ($type === 'child') {
        // Children (via parent) see only Slot B (target_type='child')
        $stmt = $pdo->prepare(
            "SELECT mp.*,
                    u.full_name as teacher_name,
                    (SELECT COUNT(*) FROM program_enrollments pe WHERE pe.program_id=mp.id AND pe.status='active') as enrolled
             FROM mosque_programs mp
             LEFT JOIN users u ON u.id = mp.teacher_id
             WHERE mp.mosque_id=? AND mp.is_active=1 AND mp.target_type='child'
             ORDER BY mp.slot"
        );
        $stmt->execute([$mosqueId]);
    } else {
        // Teachers see available slots (no teacher assigned) with target_type label
        $stmt = $pdo->prepare(
            "SELECT mp.*,
                    NULL as teacher_name,
                    (SELECT COUNT(*) FROM program_enrollments pe WHERE pe.program_id=mp.id AND pe.status='active') as enrolled
             FROM mosque_programs mp
             WHERE mp.mosque_id=? AND mp.is_active=1 AND mp.teacher_id IS NULL
             ORDER BY mp.slot"
        );
        $stmt->execute([$mosqueId]);
    }
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'governorate_users') {
    // Get users in same governorate (for messaging)
    requireLogin();
    $gov = trim($_GET['gov'] ?? '');
    if (!$gov) { echo json_encode([]); exit; }
    $stmt = $pdo->prepare(
        "SELECT id, full_name, role FROM users
         WHERE governorate=? AND is_active=1 AND id!=?
         ORDER BY role, full_name"
    );
    $stmt->execute([$gov, $_SESSION['user_id']]);
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
