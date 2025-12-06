<?php
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/response.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';

$db = new Database();
$conn = $db->getConnection();

switch ($method) {
    case 'GET':
        $user = requireAuth();
        
        $unreadOnly = isset($_GET['unread']) && $_GET['unread'] === 'true';
        
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$user['id']];
        
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT 50";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $notifications = $stmt->fetchAll();
        
        // Mark as read
        if (!$unreadOnly) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user['id']]);
        }
        
        sendSuccess('Notifications retrieved', $notifications);
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>

