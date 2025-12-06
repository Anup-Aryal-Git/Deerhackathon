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
    $status = $_GET['status'] ?? null;
    $orgId = $_GET['orgId'] ?? null;
    
    $sql = "
        SELECT t.*, o.name as org_name,
               COUNT(DISTINCT ta.id) as applicant_count
        FROM volunteer_tasks t
        LEFT JOIN organizations o ON t.org_id = o.id
        LEFT JOIN task_applicants ta ON t.id = ta.task_id
        WHERE 1=1
    ";
    $params = [];
    
    if ($status) {
        $sql .= " AND t.status = ?";
        $params[] = $status;
    }
    
    if ($orgId) {
        $sql .= " AND t.org_id = ?";
        $params[] = $orgId;
    }
    
    $sql .= " GROUP BY t.id ORDER BY t.date_time ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
    
    http_response_code(200);
    echo json_encode($tasks);
    
} elseif ($method === 'POST') {
    $payload = $auth->requireAuth();
    
    if ($payload['role'] !== 'organization' && $payload['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Only organizations can create tasks']);
        exit();
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['title', 'dateTime', 'orgId'];
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
        INSERT INTO volunteer_tasks (org_id, title, description, date_time, 
                                    location, hours_estimated, status)
        VALUES (?, ?, ?, ?, ?, ?, 'open')
    ");
    
    $stmt->execute([
        $data['orgId'],
        $data['title'],
        $data['description'] ?? null,
        $data['dateTime'],
        $data['location'] ?? null,
        $data['hoursEstimated'] ?? 0
    ]);
    
    $taskId = $conn->lastInsertId();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'id' => $taskId,
        'message' => 'Task created successfully'
    ]);
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>

