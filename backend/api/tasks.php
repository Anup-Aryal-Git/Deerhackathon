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
        $sql = "SELECT t.*, 
                o.organization_name,
                u.username as created_by_username,
                (SELECT COUNT(*) FROM task_applicants ta WHERE ta.task_id = t.id AND ta.status = 'accepted') as accepted_count
                FROM volunteer_tasks t
                LEFT JOIN organizations o ON t.organization_id = o.id
                LEFT JOIN users u ON t.created_by = u.id";
        
        $params = [];
        if ($status) {
            $sql .= " WHERE t.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY t.task_date ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();
        
        // Add user's application status if authenticated
        if ($user) {
            foreach ($tasks as &$task) {
                $stmt = $conn->prepare("SELECT status FROM task_applicants WHERE task_id = ? AND user_id = ?");
                $stmt->execute([$task['id'], $user['id']]);
                $application = $stmt->fetch();
                $task['user_application'] = $application ? $application['status'] : null;
            }
        }
        
        sendSuccess('Tasks retrieved', $tasks);
        break;
        
    case 'POST':
        if ($path === 'apply') {
            $user = requireAuth();
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['task_id'])) {
                sendError('Task ID required');
            }
            
            // Check if already applied
            $stmt = $conn->prepare("SELECT id FROM task_applicants WHERE task_id = ? AND user_id = ?");
            $stmt->execute([$data['task_id'], $user['id']]);
            if ($stmt->fetch()) {
                sendError('Already applied for this task');
            }
            
            // Apply
            $stmt = $conn->prepare("INSERT INTO task_applicants (task_id, user_id, status, application_message) VALUES (?, ?, 'pending', ?)");
            $stmt->execute([$data['task_id'], $user['id'], $data['message'] ?? null]);
            
            // Create notification
            $stmt = $conn->prepare("SELECT created_by FROM volunteer_tasks WHERE id = ?");
            $stmt->execute([$data['task_id']]);
            $task = $stmt->fetch();
            
            if ($task && $task['created_by'] != $user['id']) {
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id, related_type) VALUES (?, 'task_application', 'New Task Application', ?, ?, 'task')");
                $stmt->execute([$task['created_by'], $user['full_name'] . ' applied for your task', $data['task_id']]);
            }
            
            sendSuccess('Successfully applied for task');
        } else {
            sendError('Invalid endpoint', 404);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>

