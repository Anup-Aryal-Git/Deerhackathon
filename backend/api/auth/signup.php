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

if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

$name = trim($data['name']);
$email = trim($data['email']);
$password = $data['password'];
$role = $data['role'] ?? 'user';
$isDonor = $data['isDonor'] ?? false;
$isVolunteer = $data['isVolunteer'] ?? false;
$bloodGroup = $data['bloodGroup'] ?? null;
$city = $data['city'] ?? null;
$province = $data['province'] ?? null;
$district = $data['district'] ?? null;
$municipality = $data['municipality'] ?? null;
$phone = $data['phone'] ?? null;

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 6 characters']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Check if email exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Email already exists']);
    exit();
}

// Hash password
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

// Generate verification token
$verificationToken = bin2hex(random_bytes(32));

try {
    // Check if columns exist, if not use basic insert
    $checkColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'province'");
    $hasLocationFields = $checkColumns->rowCount() > 0;
    
    if ($hasLocationFields) {
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, password_hash, role, is_donor, is_volunteer, blood_group, city, province, district, municipality, phone, verification_token)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $name,
            $email,
            $passwordHash,
            $role,
            $isDonor ? 1 : 0,
            $isVolunteer ? 1 : 0,
            $bloodGroup ?: null,
            $city ?: null,
            $province ?: null,
            $district ?: null,
            $municipality ?: null,
            $phone ?: null,
            $verificationToken
        ]);
    } else {
        // Fallback for older schema
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, password_hash, role, is_donor, is_volunteer, blood_group, city, phone, verification_token)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $name,
            $email,
            $passwordHash,
            $role,
            $isDonor ? 1 : 0,
            $isVolunteer ? 1 : 0,
            $bloodGroup ?: null,
            $city ?: null,
            $phone ?: null,
            $verificationToken
        ]);
    }
    
    $userId = $conn->lastInsertId();
    
    // If organization role, create organization record
    if ($role === 'organization') {
        $orgName = $data['orgName'] ?? $name;
        $contact = $data['contact'] ?? $data['orgPhone'] ?? $phone;
        $address = $data['address'] ?? $data['orgAddress'] ?? null;
        $orgEmail = $data['orgEmail'] ?? $email;
        
        $stmt = $conn->prepare("
            INSERT INTO organizations (name, email, contact, address, created_by_user_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$orgName, $orgEmail, $contact, $address, $userId]);
    }
    
    // Generate JWT token
    $token = JWT::encode([
        'userId' => $userId,
        'email' => $email,
        'role' => $role
    ]);
    
    // TODO: Send verification email
    
    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'User registered successfully',
        'token' => $token,
        'user' => [
            'id' => (int)$userId,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'isDonor' => (bool)$isDonor,
            'isVolunteer' => (bool)$isVolunteer,
            'bloodGroup' => $bloodGroup,
            'province' => $province,
            'district' => $district,
            'municipality' => $municipality
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    error_log("Signup error: " . $e->getMessage());
    error_log("Signup error trace: " . $e->getTraceAsString());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Registration failed',
        'message' => $e->getMessage()
    ]);
}
?>

