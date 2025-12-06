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
    
    $sql = "
        SELECT p.*, u.name as creator_name,
               COUNT(DISTINCT pv.id) as vote_count,
               COUNT(DISTINCT pc.id) as comment_count
        FROM polls p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN poll_votes pv ON p.id = pv.poll_id
        LEFT JOIN poll_comments pc ON p.id = pc.poll_id
        WHERE p.status = ?
    ";
    
    if ($status === 'active') {
        $sql .= " AND (p.expires_at IS NULL OR p.expires_at > NOW())";
    }
    
    $sql .= " GROUP BY p.id ORDER BY p.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$status]);
    $polls = $stmt->fetchAll();
    
    foreach ($polls as &$poll) {
        $poll['options'] = json_decode($poll['options'], true);
    }
    
    http_response_code(200);
    echo json_encode($polls);
    
} elseif ($method === 'POST') {
    $payload = $auth->requireAuth();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['question']) || !isset($data['options'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing question or options']);
        exit();
    }
    
    $options = $data['options'];
    if (!is_array($options) || count($options) < 2) {
        http_response_code(400);
        echo json_encode(['error' => 'At least 2 options required']);
        exit();
    }
    
    // Format options with IDs and counts
    $formattedOptions = [];
    foreach ($options as $idx => $option) {
        $formattedOptions[] = [
            'id' => 'opt_' . ($idx + 1),
            'text' => $option,
            'count' => 0
        ];
    }
    
    $stmt = $conn->prepare("
        INSERT INTO polls (created_by, question, options, expires_at, status)
        VALUES (?, ?, ?, ?, 'active')
    ");
    
    $expiresAt = isset($data['expiresAt']) ? $data['expiresAt'] : null;
    
    $stmt->execute([
        $payload['userId'],
        $data['question'],
        json_encode($formattedOptions),
        $expiresAt
    ]);
    
    $pollId = $conn->lastInsertId();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'id' => $pollId,
        'message' => 'Poll created successfully'
    ]);
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>

