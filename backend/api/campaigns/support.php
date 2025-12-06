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

if (!isset($data['campaignId']) || !isset($data['tokens'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing campaignId or tokens']);
    exit();
}

$campaignId = $data['campaignId'];
$tokens = (int)$data['tokens'];

if ($tokens <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Tokens must be positive']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Check if campaign exists
$stmt = $conn->prepare("SELECT id, status, goal_tokens, raised_tokens FROM campaigns WHERE id = ?");
$stmt->execute([$campaignId]);
$campaign = $stmt->fetch();

if (!$campaign) {
    http_response_code(404);
    echo json_encode(['error' => 'Campaign not found']);
    exit();
}

if ($campaign['status'] !== 'active') {
    http_response_code(400);
    echo json_encode(['error' => 'Campaign is not active']);
    exit();
}

try {
    $conn->beginTransaction();
    
    // Add supporter record
    $stmt = $conn->prepare("
        INSERT INTO campaign_supporters (campaign_id, user_id, tokens)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE tokens = tokens + ?
    ");
    $stmt->execute([$campaignId, $userId, $tokens, $tokens]);
    
    // Update campaign raised tokens
    $stmt = $conn->prepare("
        UPDATE campaigns 
        SET raised_tokens = raised_tokens + ? 
        WHERE id = ?
    ");
    $stmt->execute([$tokens, $campaignId]);
    
    // Check if goal reached
    $stmt = $conn->prepare("SELECT raised_tokens, goal_tokens FROM campaigns WHERE id = ?");
    $stmt->execute([$campaignId]);
    $updated = $stmt->fetch();
    
    if ($updated['raised_tokens'] >= $updated['goal_tokens']) {
        $stmt = $conn->prepare("UPDATE campaigns SET status = 'completed' WHERE id = ?");
        $stmt->execute([$campaignId]);
    }
    
    $conn->commit();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Support added successfully',
        'raisedTokens' => $updated['raised_tokens']
    ]);
    
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Support campaign error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Support failed']);
}
?>

