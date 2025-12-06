<?php
require_once __DIR__ . '/jwt.php';

function verifyToken($token) {
    if (!$token) {
        return null;
    }
    
    // Remove 'Bearer ' prefix if present
    $token = str_replace('Bearer ', '', $token);
    
    $payload = JWT::decode($token);
    return $payload;
}

function getCurrentUser() {
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : 
                  (isset($headers['authorization']) ? $headers['authorization'] : '');
    
    if (!$authHeader) {
        return null;
    }
    
    $payload = verifyToken($authHeader);
    if (!$payload || !isset($payload['user_id'])) {
        return null;
    }
    
    require_once __DIR__ . '/../config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT id, username, email, full_name, phone, address, blood_group, date_of_birth, role, profile_image, is_verified, is_active FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$payload['user_id']]);
    $user = $stmt->fetch();
    
    return $user;
}

function requireAuth() {
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit();
    }
    return $user;
}

function requireRole($roles) {
    $user = requireAuth();
    if (!in_array($user['role'], is_array($roles) ? $roles : [$roles])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        exit();
    }
    return $user;
}
?>

