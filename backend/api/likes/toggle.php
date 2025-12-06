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

if (!isset($data['postId']) || !isset($data['type'])) {
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
    $conn->beginTransaction();

    // Get post owner information before toggling like
    $ownerId = null;
    $title = '';

    if ($type === 'poll') {
        $stmt = $conn->prepare("SELECT id, question, created_by FROM polls WHERE id = ?");
        $stmt->execute([$postId]);
        $poll = $stmt->fetch();
        if ($poll) {
            $ownerId = (int)$poll['created_by'];
            $title = $poll['question'];
        }
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
        if ($record) {
            $ownerId = (int)$record['owner_id'];
            $title = $record['title'];
        }
    }

    // Check if already liked
    $stmt = $conn->prepare("
        SELECT id FROM post_likes 
        WHERE user_id = ? AND post_type = ? AND post_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $type, $postId]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Unlike
        $stmt = $conn->prepare("
            DELETE FROM post_likes 
            WHERE id = ?
        ");
        $stmt->execute([$existing['id']]);
        $liked = false;
    } else {
        // Like
        $stmt = $conn->prepare("
            INSERT INTO post_likes (user_id, post_type, post_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $type, $postId]);
        $liked = true;

        // Create notification for post owner when someone likes their post
        if ($ownerId && $ownerId !== $userId) {
            // Get user's name or organization name
            $userStmt = $conn->prepare("SELECT u.name, u.role, o.name as org_name FROM users u LEFT JOIN organizations o ON o.created_by_user_id = u.id WHERE u.id = ?");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch();
            
            // Use organization name if user is an organization, otherwise use user name
            $displayName = ($user['role'] === 'organization' && $user['org_name']) ? $user['org_name'] : ($user['name'] ?? 'Someone');

            $message = sprintf(
                "%s liked your %s \"%s\"",
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
                VALUES (?, 'post_like', ?, ?)
            ");
            $stmt->execute([$ownerId, $message, $meta]);
        }
    }

    // Recalculate like count
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as cnt 
        FROM post_likes 
        WHERE post_type = ? AND post_id = ?
    ");
    $countStmt->execute([$type, $postId]);
    $row = $countStmt->fetch();
    $likeCount = (int)($row['cnt'] ?? 0);

    $conn->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'likeCount' => $likeCount,
    ]);
} catch (PDOException $e) {
    $conn->rollBack();
    error_log('Like toggle failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to toggle like']);
}

