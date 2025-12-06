<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$auth = new Auth();
$payload = $auth->requireAuth();
$userId = $payload['userId'];

$db = new Database();
$conn = $db->getConnection();

if ($method === 'GET') {
    $stmt = $conn->prepare("
        SELECT id, name, email, role, is_donor, is_volunteer, 
               blood_group, city, province, district, municipality, phone, last_donation, badges, 
               email_verified, created_at
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit();
    }
    
    $user['badges'] = json_decode($user['badges'] ?? '[]', true);
    
    http_response_code(200);
    echo json_encode($user);
    
} elseif ($method === 'PATCH') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $updates = [];
    $params = [];
    
    $allowedFields = ['name', 'blood_group', 'city', 'province', 'district', 'municipality', 'phone', 'last_donation', 'is_donor', 'is_volunteer'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            // Database field names are already in snake_case
            $dbField = $field;
            if ($field === 'is_donor' || $field === 'is_volunteer') {
                $updates[] = "$dbField = ?";
                $params[] = $data[$field] ? 1 : 0;
            } else {
                $updates[] = "$dbField = ?";
                $params[] = $data[$field];
            }
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid fields to update']);
        exit();
    }
    
    $params[] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Profile updated']);
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>

