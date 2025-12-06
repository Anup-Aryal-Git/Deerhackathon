<?php
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/response.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';

$db = new Database();
$conn = $db->getConnection();

switch ($method) {
    case 'GET':
        if ($path === 'me') {
            $user = requireAuth();
            sendSuccess('User retrieved', $user);
        } elseif ($path === 'search') {
            $user = requireAuth();
            $query = isset($_GET['q']) ? $_GET['q'] : '';
            $role = isset($_GET['role']) ? $_GET['role'] : null;
            
            $sql = "SELECT id, username, email, full_name, phone, address, blood_group, role, profile_image, is_verified FROM users WHERE is_active = 1";
            $params = [];
            
            if ($query) {
                $sql .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
                $searchTerm = "%$query%";
                $params = [$searchTerm, $searchTerm, $searchTerm];
            }
            
            if ($role) {
                $sql .= " AND role = ?";
                $params[] = $role;
            }
            
            $sql .= " LIMIT 50";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll();
            
            sendSuccess('Users retrieved', $users);
        } else {
            sendError('Invalid endpoint', 404);
        }
        break;
        
    case 'DELETE':
        if ($path === 'delete') {
            $user = requireAuth();
            
            $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            sendSuccess('Account deleted successfully');
        } else {
            sendError('Invalid endpoint', 404);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>

