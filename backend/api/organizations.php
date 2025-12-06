<?php
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/response.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';

$db = new Database();
$conn = $db->getConnection();

switch ($method) {
    case 'GET':
        $user = getCurrentUser(); // Optional auth
        
        $sql = "SELECT o.*, 
                u.username, u.email, u.phone, u.profile_image
                FROM organizations o
                LEFT JOIN users u ON o.user_id = u.id
                ORDER BY o.is_verified DESC, o.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $organizations = $stmt->fetchAll();
        
        sendSuccess('Organizations retrieved', $organizations);
        break;
        
    case 'POST':
        if ($path === 'verify') {
            $user = requireRole('admin');
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['organization_id'])) {
                sendError('Organization ID required');
            }
            
            $verify = isset($data['verify']) ? (bool)$data['verify'] : true;
            
            $stmt = $conn->prepare("UPDATE organizations SET is_verified = ? WHERE id = ?");
            $stmt->execute([$verify ? 1 : 0, $data['organization_id']]);
            
            // Create notification
            $stmt = $conn->prepare("SELECT user_id FROM organizations WHERE id = ?");
            $stmt->execute([$data['organization_id']]);
            $org = $stmt->fetch();
            
            if ($org) {
                $message = $verify ? 'Your organization has been verified' : 'Your organization verification has been revoked';
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id, related_type) VALUES (?, 'organization_verification', 'Organization Verification', ?, ?, 'organization')");
                $stmt->execute([$org['user_id'], $message, $data['organization_id']]);
            }
            
            sendSuccess($verify ? 'Organization verified' : 'Organization verification revoked');
        } else {
            sendError('Invalid endpoint', 404);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>

