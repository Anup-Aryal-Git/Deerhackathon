<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/jwt.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password required']);
    exit();
}

$email = trim($data['email']);
$password = $data['password'];

$db = new Database();
$conn = $db->getConnection();

// Check if columns exist for location fields
$checkColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'province'");
$hasLocationFields = $checkColumns->rowCount() > 0;

if ($hasLocationFields) {
    $stmt = $conn->prepare("
        SELECT id, name, email, password_hash, role, is_donor, is_volunteer, 
               blood_group, city, province, district, municipality, email_verified
        FROM users 
        WHERE email = ?
    ");
} else {
    $stmt = $conn->prepare("
        SELECT id, name, email, password_hash, role, is_donor, is_volunteer, 
               blood_group, city, email_verified
        FROM users 
        WHERE email = ?
    ");
}

$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid email or password']);
    exit();
}

// Generate JWT token
$token = JWT::encode([
    'userId' => $user['id'],
    'email' => $user['email'],
    'role' => $user['role']
]);

$userData = [
    'id' => $user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'role' => $user['role'],
    'isDonor' => (bool)$user['is_donor'],
    'isVolunteer' => (bool)$user['is_volunteer'],
    'bloodGroup' => $user['blood_group'],
    'city' => $user['city'],
    'emailVerified' => (bool)$user['email_verified']
];

if ($hasLocationFields) {
    $userData['province'] = $user['province'];
    $userData['district'] = $user['district'];
    $userData['municipality'] = $user['municipality'];
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'token' => $token,
    'user' => $userData
]);
?>

