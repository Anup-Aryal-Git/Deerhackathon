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
        
        $sql = "SELECT p.*, 
                u.username as created_by_username,
                u.full_name as created_by_name
                FROM polls p
                LEFT JOIN users u ON p.created_by = u.id
                WHERE p.is_active = 1 AND (p.expires_at IS NULL OR p.expires_at > NOW())
                ORDER BY p.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $polls = $stmt->fetchAll();
        
        // Get options and user's vote for each poll
        foreach ($polls as &$poll) {
            $stmt = $conn->prepare("SELECT * FROM poll_options WHERE poll_id = ?");
            $stmt->execute([$poll['id']]);
            $poll['options'] = $stmt->fetchAll();
            
            if ($user) {
                $stmt = $conn->prepare("SELECT option_id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
                $stmt->execute([$poll['id'], $user['id']]);
                $vote = $stmt->fetch();
                $poll['user_vote'] = $vote ? $vote['option_id'] : null;
            }
        }
        
        sendSuccess('Polls retrieved', $polls);
        break;
        
    case 'POST':
        if ($path === 'vote') {
            $user = requireAuth();
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['poll_id']) || !isset($data['option_id'])) {
                sendError('Poll ID and Option ID required');
            }
            
            // Check if already voted
            $stmt = $conn->prepare("SELECT id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
            $stmt->execute([$data['poll_id'], $user['id']]);
            if ($stmt->fetch()) {
                sendError('Already voted on this poll');
            }
            
            // Vote
            $stmt = $conn->prepare("INSERT INTO poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)");
            $stmt->execute([$data['poll_id'], $data['option_id'], $user['id']]);
            
            // Update vote count
            $stmt = $conn->prepare("UPDATE poll_options SET vote_count = vote_count + 1 WHERE id = ?");
            $stmt->execute([$data['option_id']]);
            
            sendSuccess('Vote recorded successfully');
        } else {
            sendError('Invalid endpoint', 404);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>

