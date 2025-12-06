<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$auth = new Auth();
$payload = $auth->requireAuth();
$userId = $payload['userId'];

$db = new Database();
$conn = $db->getConnection();

if ($method === 'GET') {
    $unreadOnly = $_GET['unreadOnly'] ?? false;
    
    $sql = "SELECT * FROM notifications WHERE user_id = ?";
    $params = [$userId];
    
    if ($unreadOnly) {
        $sql .= " AND read_status = 0";
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
    
    foreach ($notifications as &$notif) {
        $notif['meta'] = json_decode($notif['meta'] ?? '{}', true);
        $notif['read'] = (bool)$notif['read_status'];
        unset($notif['read_status']);
    }
    
    http_response_code(200);
    echo json_encode($notifications);
    
} elseif ($method === 'PATCH') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['notificationId'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing notificationId']);
        exit();
    }
    
    $notifId = $data['notificationId'];
    $read = isset($data['read']) ? ($data['read'] ? 1 : 0) : 1;
    
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET read_status = ? 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$read, $notifId, $userId]);
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Notification updated']);
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>

