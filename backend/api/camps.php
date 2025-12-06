<?php
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/response.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';

$db = new Database();
$conn = $db->getConnection();

switch ($method) {
    case 'GET':
        $user = getCurrentUser(); // Optional auth for viewing
        
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        $sql = "SELECT c.*, 
                o.organization_name,
                u.username as created_by_username,
                (SELECT COUNT(*) FROM camp_attendees ca WHERE ca.camp_id = c.id) as registered_count
                FROM camps c
                LEFT JOIN organizations o ON c.organization_id = o.id
                LEFT JOIN users u ON c.created_by = u.id";
        
        $params = [];
        if ($status) {
            $sql .= " WHERE c.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY c.camp_date ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $camps = $stmt->fetchAll();
        
        // Add user's registration status if authenticated
        if ($user) {
            foreach ($camps as &$camp) {
                $stmt = $conn->prepare("SELECT status FROM camp_attendees WHERE camp_id = ? AND user_id = ?");
                $stmt->execute([$camp['id'], $user['id']]);
                $registration = $stmt->fetch();
                $camp['user_registration'] = $registration ? $registration['status'] : null;
            }
        }
        
        sendSuccess('Camps retrieved', $camps);
        break;
        
    case 'POST':
        if ($path === 'join') {
            $user = requireAuth();
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['camp_id'])) {
                sendError('Camp ID required');
            }
            
            // Check if already registered
            $stmt = $conn->prepare("SELECT id FROM camp_attendees WHERE camp_id = ? AND user_id = ?");
            $stmt->execute([$data['camp_id'], $user['id']]);
            if ($stmt->fetch()) {
                sendError('Already registered for this camp');
            }
            
            // Register
            $stmt = $conn->prepare("INSERT INTO camp_attendees (camp_id, user_id, status) VALUES (?, ?, 'registered')");
            $stmt->execute([$data['camp_id'], $user['id']]);
            
            // Update camp count
            $stmt = $conn->prepare("UPDATE camps SET current_donors = current_donors + 1 WHERE id = ?");
            $stmt->execute([$data['camp_id']]);
            
            // Create notification
            $stmt = $conn->prepare("SELECT created_by FROM camps WHERE id = ?");
            $stmt->execute([$data['camp_id']]);
            $camp = $stmt->fetch();
            
            if ($camp && $camp['created_by'] != $user['id']) {
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id, related_type) VALUES (?, 'camp_registration', 'New Camp Registration', ?, ?, 'camp')");
                $stmt->execute([$camp['created_by'], $user['full_name'] . ' registered for your camp', $data['camp_id']]);
            }
            
            sendSuccess('Successfully registered for camp');
        } else {
            sendError('Invalid endpoint', 404);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>

