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
$payload = $auth->requireAdmin();

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['orgId']) || !isset($data['verified'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing orgId or verified']);
    exit();
}

$orgId = $data['orgId'];
$verified = $data['verified'] ? 1 : 0;

$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("UPDATE organizations SET verified = ? WHERE id = ?");
$stmt->execute([$verified, $orgId]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Organization not found']);
    exit();
}

// Update user role if verified
if ($verified) {
    $stmt = $conn->prepare("
        UPDATE users u
        JOIN organizations o ON u.id = o.created_by_user_id
        SET u.role = 'organization'
        WHERE o.id = ?
    ");
    $stmt->execute([$orgId]);
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Organization verification updated'
]);
?>

