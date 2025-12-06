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
        
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        $sql = "SELECT c.*, 
                o.organization_name,
                u.username as created_by_username,
                (SELECT COUNT(*) FROM campaign_supporters cs WHERE cs.campaign_id = c.id) as supporter_count
                FROM campaigns c
                LEFT JOIN organizations o ON c.organization_id = o.id
                LEFT JOIN users u ON c.created_by = u.id";
        
        $params = [];
        if ($status) {
            $sql .= " WHERE c.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY c.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $campaigns = $stmt->fetchAll();
        
        sendSuccess('Campaigns retrieved', $campaigns);
        break;
        
    case 'POST':
        if ($path === 'support') {
            $user = requireAuth();
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['campaign_id'])) {
                sendError('Campaign ID required');
            }
            
            $amount = isset($data['amount']) ? (float)$data['amount'] : 0.00;
            
            // Support campaign
            $stmt = $conn->prepare("INSERT INTO campaign_supporters (campaign_id, user_id, amount, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$data['campaign_id'], $user['id'], $amount, $data['message'] ?? null]);
            
            // Update campaign amount
            $stmt = $conn->prepare("UPDATE campaigns SET current_amount = current_amount + ? WHERE id = ?");
            $stmt->execute([$amount, $data['campaign_id']]);
            
            // Create notification
            $stmt = $conn->prepare("SELECT created_by FROM campaigns WHERE id = ?");
            $stmt->execute([$data['campaign_id']]);
            $campaign = $stmt->fetch();
            
            if ($campaign && $campaign['created_by'] != $user['id']) {
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id, related_type) VALUES (?, 'campaign_support', 'Campaign Support', ?, ?, 'campaign')");
                $stmt->execute([$campaign['created_by'], $user['full_name'] . ' supported your campaign', $data['campaign_id']]);
            }
            
            sendSuccess('Successfully supported campaign');
        } else {
            sendError('Invalid endpoint', 404);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>

