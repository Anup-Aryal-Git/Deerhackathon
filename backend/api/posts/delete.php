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
$role = $payload['role'];

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['postId'], $data['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing postId or type']);
    exit();
}

$postId = (int)$data['postId'];
$type = $data['type'];

$typeMap = [
    'camp' => ['table' => 'camps'],
    'task' => ['table' => 'volunteer_tasks'],
    'campaign' => ['table' => 'campaigns'],
    'poll' => ['table' => 'polls'],
];

if (!isset($typeMap[$type])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post type']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

try {
    if ($type === 'poll') {
        $stmt = $conn->prepare("SELECT created_by FROM polls WHERE id = ?");
        $stmt->execute([$postId]);
        $poll = $stmt->fetch();

        if (!$poll) {
            http_response_code(404);
            echo json_encode(['error' => 'Poll not found']);
            exit();
        }

        if ($role !== 'admin' && (int)$poll['created_by'] !== $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'Not authorized to delete this poll']);
            exit();
        }

        $deleteStmt = $conn->prepare("DELETE FROM polls WHERE id = ?");
        $deleteStmt->execute([$postId]);
    } else {
        $table = $typeMap[$type]['table'];
        $stmt = $conn->prepare("
            SELECT t.id, t.org_id, o.created_by_user_id as owner_id
            FROM {$table} t
            JOIN organizations o ON t.org_id = o.id
            WHERE t.id = ?
        ");
        $stmt->execute([$postId]);
        $record = $stmt->fetch();

        if (!$record) {
            http_response_code(404);
            echo json_encode(['error' => ucfirst($type) . ' not found']);
            exit();
        }

        if ($role !== 'admin' && (int)$record['owner_id'] !== $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'Not authorized to delete this post']);
            exit();
        }

        $deleteStmt = $conn->prepare("DELETE FROM {$table} WHERE id = ?");
        $deleteStmt->execute([$postId]);
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Post deleted',
    ]);

} catch (PDOException $e) {
    error_log('Delete post error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete post']);
}



