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

if (!isset($data['postId'], $data['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing postId or type']);
    exit();
}

$postId = (int)$data['postId'];
$type = $data['type'];
$allowedTypes = ['camp', 'task', 'campaign', 'poll'];

if (!in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post type']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

try {
    // Check existing interest
    $stmt = $conn->prepare("
        SELECT id FROM post_interests
        WHERE user_id = ? AND post_type = ? AND post_id = ?
    ");
    $stmt->execute([$userId, $type, $postId]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Already marked interested']);
        exit();
    }

    $conn->beginTransaction();

    $ownerId = null;
    $title = '';

    if ($type === 'poll') {
        $stmt = $conn->prepare("SELECT id, question, created_by FROM polls WHERE id = ?");
        $stmt->execute([$postId]);
        $poll = $stmt->fetch();
        if (!$poll) {
            http_response_code(404);
            echo json_encode(['error' => 'Poll not found']);
            exit();
        }
        $ownerId = (int)$poll['created_by'];
        $title = $poll['question'];
    } else {
        $tableMap = [
            'camp' => 'camps',
            'task' => 'volunteer_tasks',
            'campaign' => 'campaigns'
        ];
        $table = $tableMap[$type];
        $stmt = $conn->prepare("
            SELECT t.id, t.title, o.created_by_user_id as owner_id
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
        $ownerId = (int)$record['owner_id'];
        $title = $record['title'];
    }

    // Insert interest
    $stmt = $conn->prepare("
        INSERT INTO post_interests (user_id, post_type, post_id)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$userId, $type, $postId]);

    // Notification
    if ($ownerId && $ownerId !== $userId) {
        // Get user's name or organization name
        $userStmt = $conn->prepare("SELECT u.name, u.role, o.name as org_name FROM users u LEFT JOIN organizations o ON o.created_by_user_id = u.id WHERE u.id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        
        // Use organization name if user is an organization, otherwise use user name
        $displayName = ($user['role'] === 'organization' && $user['org_name']) ? $user['org_name'] : ($user['name'] ?? 'Someone');

        $message = sprintf(
            "%s is interested in your %s \"%s\"",
            $displayName,
            $type === 'poll' ? 'poll' : $type,
            $title
        );

        $meta = json_encode([
            'postId' => $postId,
            'postType' => $type,
            'userId' => $userId,
            'title' => $title,
            'userName' => $displayName
        ]);

        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, message, meta)
            VALUES (?, 'post_interest', ?, ?)
        ");
        $stmt->execute([$ownerId, $message, $meta]);
    }

    $conn->commit();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Interest recorded'
    ]);

} catch (PDOException $e) {
    $conn->rollBack();
    error_log('Interest error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to record interest']);
}



