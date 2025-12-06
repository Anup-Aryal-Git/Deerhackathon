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
$payload = $auth->requireAuth();
$userId = $payload['userId'];

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['taskId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing taskId']);
    exit();
}

$taskId = $data['taskId'];

$db = new Database();
$conn = $db->getConnection();

// Check if task exists
$stmt = $conn->prepare("SELECT id, status, title FROM volunteer_tasks WHERE id = ?");
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task) {
    http_response_code(404);
    echo json_encode(['error' => 'Task not found']);
    exit();
}

if ($task['status'] !== 'open') {
    http_response_code(400);
    echo json_encode(['error' => 'Task is not accepting applications']);
    exit();
}

// Check if already applied
$stmt = $conn->prepare("SELECT id FROM task_applicants WHERE task_id = ? AND user_id = ?");
$stmt->execute([$taskId, $userId]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Already applied for this task']);
    exit();
}

try {
    $stmt = $conn->prepare("
        INSERT INTO task_applicants (task_id, user_id, status)
        VALUES (?, ?, 'pending')
    ");
    $stmt->execute([$taskId, $userId]);
    
    // Create notification
    $stmt = $conn->prepare("
        SELECT o.created_by_user_id 
        FROM volunteer_tasks t 
        JOIN organizations o ON t.org_id = o.id 
        WHERE t.id = ?
    ");
    $stmt->execute([$taskId]);
    $org = $stmt->fetch();
    
    if ($org) {
        // Get applicant's name or organization name
        $applicantStmt = $conn->prepare("SELECT u.name, u.role, o.name as org_name FROM users u LEFT JOIN organizations o ON o.created_by_user_id = u.id WHERE u.id = ?");
        $applicantStmt->execute([$userId]);
        $applicant = $applicantStmt->fetch();
        
        // Use organization name if applicant is an organization, otherwise use user name
        $applicantName = ($applicant['role'] === 'organization' && $applicant['org_name']) ? $applicant['org_name'] : ($applicant['name'] ?? 'A volunteer');

        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, message, meta)
            VALUES (?, 'task_application', ?, ?)
        ");
        $taskTitle = $task['title'] ?? 'your task';
        $message = sprintf("%s is interested in \"%s\"", $applicantName, $taskTitle);
        $meta = json_encode([
            'taskId' => $taskId,
            'userId' => $userId,
            'taskTitle' => $taskTitle,
            'applicantName' => $applicantName
        ]);
        $stmt->execute([$org['created_by_user_id'], $message, $meta]);
    }
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Application submitted successfully'
    ]);
    
} catch (PDOException $e) {
    error_log("Apply task error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Application failed']);
}
?>

