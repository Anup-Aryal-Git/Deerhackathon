<?php
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/jwt.php';
require_once __DIR__ . '/../utils/response.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';

$db = new Database();
$conn = $db->getConnection();

switch ($method) {
    case 'POST':
        if ($path === 'signup' || $path === '') {
            // Signup
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['username']) || !isset($data['email']) || !isset($data['password']) || !isset($data['full_name'])) {
                sendError('Missing required fields');
            }
            
            // Validate email
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                sendError('Invalid email format');
            }
            
            // Check if user exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$data['email'], $data['username']]);
            if ($stmt->fetch()) {
                sendError('User already exists');
            }
            
            // Hash password
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, phone, address, blood_group, date_of_birth, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $role = isset($data['role']) ? $data['role'] : 'donor';
            $stmt->execute([
                $data['username'],
                $data['email'],
                $passwordHash,
                $data['full_name'],
                $data['phone'] ?? null,
                $data['address'] ?? null,
                $data['blood_group'] ?? null,
                $data['date_of_birth'] ?? null,
                $role
            ]);
            
            $userId = $conn->lastInsertId();
            
            // Generate tokens
            $accessToken = JWT::encode(['user_id' => $userId, 'username' => $data['username']]);
            $refreshToken = JWT::generateRefreshToken($userId);
            
            // Get user data
            $stmt = $conn->prepare("SELECT id, username, email, full_name, phone, address, blood_group, date_of_birth, role, profile_image, is_verified FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            sendSuccess('User registered successfully', [
                'user' => $user,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken
            ], 201);
            
        } elseif ($path === 'login') {
            // Login
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['email']) || !isset($data['password'])) {
                sendError('Email and password required');
            }
            
            $stmt = $conn->prepare("SELECT id, username, email, password_hash, full_name, phone, address, blood_group, date_of_birth, role, profile_image, is_verified, is_active FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($data['password'], $user['password_hash'])) {
                sendError('Invalid credentials', 401);
            }
            
            if (!$user['is_active']) {
                sendError('Account is deactivated', 403);
            }
            
            // Generate tokens
            $accessToken = JWT::encode(['user_id' => $user['id'], 'username' => $user['username']]);
            $refreshToken = JWT::generateRefreshToken($user['id']);
            
            // Remove password hash from response
            unset($user['password_hash']);
            
            sendSuccess('Login successful', [
                'user' => $user,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken
            ]);
            
        } else {
            sendError('Invalid endpoint', 404);
        }
        break;
        
    case 'GET':
        if ($path === 'me') {
            // Get current user
            $user = requireAuth();
            sendSuccess('User retrieved', $user);
        } else {
            sendError('Invalid endpoint', 404);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
}
?>

