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
    $id = $_GET['id'] ?? null;
    $verified = $_GET['verified'] ?? null;
    
    if ($id) {
        $stmt = $conn->prepare("
            SELECT o.*, u.name as creator_name
            FROM organizations o
            LEFT JOIN users u ON o.created_by_user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$id]);
        $org = $stmt->fetch();
        
        if (!$org) {
            http_response_code(404);
            echo json_encode(['error' => 'Organization not found']);
            exit();
        }
        
        $org['docs'] = json_decode($org['docs'] ?? '[]', true);
        http_response_code(200);
        echo json_encode($org);
        
    } else {
        $sql = "SELECT o.*, u.name as creator_name FROM organizations o LEFT JOIN users u ON o.created_by_user_id = u.id WHERE 1=1";
        $params = [];
        
        if ($verified !== null) {
            $sql .= " AND o.verified = ?";
            $params[] = $verified ? 1 : 0;
        }
        
        $sql .= " ORDER BY o.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $orgs = $stmt->fetchAll();
        
        foreach ($orgs as &$org) {
            $org['docs'] = json_decode($org['docs'] ?? '[]', true);
        }
        
        http_response_code(200);
        echo json_encode($orgs);
    }
    
} elseif ($method === 'POST') {
    $payload = $auth->requireAuth();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['name', 'email'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing field: $field"]);
            exit();
        }
    }
    
    // Check if org already exists for this user
    $stmt = $conn->prepare("SELECT id FROM organizations WHERE created_by_user_id = ?");
    $stmt->execute([$payload['userId']]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Organization already exists for this user']);
        exit();
    }
    
    $stmt = $conn->prepare("
        INSERT INTO organizations (name, email, contact, address, created_by_user_id, verified)
        VALUES (?, ?, ?, ?, ?, 0)
    ");
    
    $stmt->execute([
        $data['name'],
        $data['email'],
        $data['contact'] ?? null,
        $data['address'] ?? null,
        $payload['userId']
    ]);
    
    $orgId = $conn->lastInsertId();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'id' => $orgId,
        'message' => 'Organization created. Pending verification.'
    ]);
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>

