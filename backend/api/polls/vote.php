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

if (!isset($data['pollId']) || !isset($data['optionId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing pollId or optionId']);
    exit();
}

$pollId = $data['pollId'];
$optionId = $data['optionId'];

$db = new Database();
$conn = $db->getConnection();

// Check if poll exists and is active, and get creator info
$stmt = $conn->prepare("
    SELECT p.id, p.options, p.status, p.expires_at, p.question, p.created_by
    FROM polls p
    WHERE p.id = ?
");
$stmt->execute([$pollId]);
$poll = $stmt->fetch();

if (!$poll) {
    http_response_code(404);
    echo json_encode(['error' => 'Poll not found']);
    exit();
}

if ($poll['status'] !== 'active') {
    http_response_code(400);
    echo json_encode(['error' => 'Poll is not active']);
    exit();
}

if ($poll['expires_at'] && strtotime($poll['expires_at']) < time()) {
    http_response_code(400);
    echo json_encode(['error' => 'Poll has expired']);
    exit();
}

// Check if already voted
$stmt = $conn->prepare("SELECT id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
$stmt->execute([$pollId, $userId]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Already voted']);
    exit();
}

// Validate option exists
$options = json_decode($poll['options'], true);
$optionExists = false;
foreach ($options as $opt) {
    if ($opt['id'] === $optionId) {
        $optionExists = true;
        break;
    }
}

if (!$optionExists) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid option']);
    exit();
}

try {
    $conn->beginTransaction();
    
    // Add vote
    $stmt = $conn->prepare("
        INSERT INTO poll_votes (poll_id, user_id, option_id)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$pollId, $userId, $optionId]);
    
    // Update option count
    foreach ($options as &$opt) {
        if ($opt['id'] === $optionId) {
            $opt['count']++;
        }
    }
    
    $stmt = $conn->prepare("UPDATE polls SET options = ? WHERE id = ?");
    $stmt->execute([json_encode($options), $pollId]);
    
    // Create notification for poll creator when someone votes (but not if voting on own poll)
    $pollCreatorId = (int)$poll['created_by'];
    if ($pollCreatorId && $pollCreatorId !== $userId) {
        // Get voter's name or organization name
        $userStmt = $conn->prepare("SELECT u.name, u.role, o.name as org_name FROM users u LEFT JOIN organizations o ON o.created_by_user_id = u.id WHERE u.id = ?");
        $userStmt->execute([$userId]);
        $voter = $userStmt->fetch();
        
        // Use organization name if user is an organization, otherwise use user name
        $voterName = ($voter['role'] === 'organization' && $voter['org_name']) ? $voter['org_name'] : ($voter['name'] ?? 'Someone');
        
        // Get the option text that was voted for
        $votedOptionText = '';
        foreach ($options as $opt) {
            if ($opt['id'] === $optionId) {
                $votedOptionText = $opt['text'];
                break;
            }
        }
        
        $message = sprintf(
            "%s voted on your poll \"%s\"",
            $voterName,
            $poll['question']
        );
        
        $meta = json_encode([
            'pollId' => $pollId,
            'userId' => $userId,
            'question' => $poll['question'],
            'optionId' => $optionId,
            'optionText' => $votedOptionText,
            'userName' => $voterName
        ]);
        
        $notifStmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, message, meta)
            VALUES (?, 'poll_vote', ?, ?)
        ");
        $notifStmt->execute([$pollCreatorId, $message, $meta]);
    }
    
    $conn->commit();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Vote recorded',
        'options' => $options
    ]);
    
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Vote error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Vote failed']);
}
?>

