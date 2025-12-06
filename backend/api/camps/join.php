<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$auth = new Auth();
$payload = $auth->requireAuth();
$userId = $payload['userId'];

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['campId']) || !isset($data['role'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing campId or role']);
    exit();
}

$campId = $data['campId'];
$role = $data['role']; // 'donor' or 'volunteer'

if (!in_array($role, ['donor', 'volunteer'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid role']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Check if camp exists
$stmt = $conn->prepare("SELECT id, status FROM camps WHERE id = ?");
$stmt->execute([$campId]);
$camp = $stmt->fetch();

if (!$camp) {
    http_response_code(404);
    echo json_encode(['error' => 'Camp not found']);
    exit();
}

if ($camp['status'] !== 'upcoming') {
    http_response_code(400);
    echo json_encode(['error' => 'Camp is not accepting registrations']);
    exit();
}

// Check if already registered
$stmt = $conn->prepare("SELECT id FROM camp_attendees WHERE camp_id = ? AND user_id = ?");
$stmt->execute([$campId, $userId]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Already registered for this camp']);
    exit();
}

// Get user blood group if donor
$bloodGroup = null;
if ($role === 'donor') {
    $stmt = $conn->prepare("SELECT blood_group FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $bloodGroup = $user['blood_group'] ?? null;
}

try {
    $stmt = $conn->prepare("
        INSERT INTO camp_attendees (camp_id, user_id, role, blood_group, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$campId, $userId, $role, $bloodGroup]);
    
    // Create notification for organization
    $stmt = $conn->prepare("
        SELECT o.created_by_user_id 
        FROM camps c 
        JOIN organizations o ON c.org_id = o.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$campId]);
    $org = $stmt->fetch();
    
    if ($org) {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, message, meta)
            VALUES (?, 'camp_registration', ?, ?)
        ");
        $message = "New {$role} registration for camp";
        $meta = json_encode(['campId' => $campId, 'userId' => $userId, 'role' => $role]);
        $stmt->execute([$org['created_by_user_id'], $message, $meta]);
    }
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Successfully registered for camp'
    ]);
    
} catch (PDOException $e) {
    error_log("Join camp error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed']);
}
?>

