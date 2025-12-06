<?php
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/response.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';

$db = new Database();
$conn = $db->getConnection();

switch ($method) {
    case 'POST':
        if ($path === 'toggle') {
            $user = requireAuth();
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['post_id'])) {
                sendError('Post ID required');
            }
            
            // Check if already liked
            $stmt = $conn->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$data['post_id'], $user['id']]);
            $like = $stmt->fetch();
            
            if ($like) {
                // Unlike
                $stmt = $conn->prepare("DELETE FROM post_likes WHERE id = ?");
                $stmt->execute([$like['id']]);
                
                $stmt = $conn->prepare("UPDATE posts SET like_count = GREATEST(like_count - 1, 0) WHERE id = ?");
                $stmt->execute([$data['post_id']]);
                
                sendSuccess('Post unliked', ['liked' => false]);
            } else {
                // Like
                $stmt = $conn->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
                $stmt->execute([$data['post_id'], $user['id']]);
                
                $stmt = $conn->prepare("UPDATE posts SET like_count = like_count + 1 WHERE id = ?");
                $stmt->execute([$data['post_id']]);
                
                sendSuccess('Post liked', ['liked' => true]);
            }
        } else {
            sendError('Invalid endpoint', 404);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>

