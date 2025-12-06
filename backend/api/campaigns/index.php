<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$auth = new Auth();

$db = new Database();
$conn = $db->getConnection();

if ($method === 'GET') {
    $status = $_GET['status'] ?? 'active';
    $orgId = $_GET['orgId'] ?? null;
    
    $sql = "
        SELECT c.*, o.name as org_name,
               COUNT(DISTINCT cs.id) as supporter_count
        FROM campaigns c
        LEFT JOIN organizations o ON c.org_id = o.id
        LEFT JOIN campaign_supporters cs ON c.id = cs.campaign_id
        WHERE c.status = ?
    ";
    $params = [$status];
    
    if ($orgId) {
        $sql .= " AND c.org_id = ?";
        $params[] = $orgId;
    }
    
    $sql .= " GROUP BY c.id ORDER BY c.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $campaigns = $stmt->fetchAll();
    
    http_response_code(200);
    echo json_encode($campaigns);
    
} elseif ($method === 'POST') {
    $payload = $auth->requireAuth();
    
    if ($payload['role'] !== 'organization' && $payload['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Only organizations can create campaigns']);
        exit();
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['title', 'goalTokens', 'orgId'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing field: $field"]);
            exit();
        }
    }
    
    // Verify org ownership
    if ($payload['role'] !== 'admin') {
        $stmt = $conn->prepare("SELECT id FROM organizations WHERE id = ? AND created_by_user_id = ?");
        $stmt->execute([$data['orgId'], $payload['userId']]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Not authorized']);
            exit();
        }
    }
    
    $stmt = $conn->prepare("
        INSERT INTO campaigns (org_id, title, description, goal_tokens, raised_tokens, status)
        VALUES (?, ?, ?, ?, 0, 'active')
    ");
    
    $stmt->execute([
        $data['orgId'],
        $data['title'],
        $data['description'] ?? null,
        $data['goalTokens']
    ]);
    
    $campaignId = $conn->lastInsertId();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'id' => $campaignId,
        'message' => 'Campaign created successfully'
    ]);
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>

