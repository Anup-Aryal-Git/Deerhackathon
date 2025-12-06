<?php
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/response.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';

$db = new Database();
$conn = $db->getConnection();

switch ($method) {
    case 'GET':
        $user = requireAuth();
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT p.*, 
                u.username, u.full_name, u.profile_image as user_profile_image,
                o.organization_name,
                (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) as like_count,
                (SELECT COUNT(*) FROM post_interests pi WHERE pi.post_id = p.id) as interest_count,
                (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) as comment_count,
                (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = ?) as is_liked,
                (SELECT COUNT(*) FROM post_interests pi WHERE pi.post_id = p.id AND pi.user_id = ?) as is_interested
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN organizations o ON p.organization_id = o.id
                WHERE p.is_public = 1
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user['id'], $user['id'], $limit, $offset]);
        $posts = $stmt->fetchAll();
        
        // Get comments for each post
        foreach ($posts as &$post) {
            $stmt = $conn->prepare("SELECT c.*, u.username, u.full_name, u.profile_image 
                                   FROM comments c 
                                   JOIN users u ON c.user_id = u.id 
                                   WHERE c.post_id = ? 
                                   ORDER BY c.created_at DESC 
                                   LIMIT 5");
            $stmt->execute([$post['id']]);
            $post['comments'] = $stmt->fetchAll();
        }
        
        sendSuccess('Feed retrieved', [
            'posts' => $posts,
            'page' => $page,
            'limit' => $limit
        ]);
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>

