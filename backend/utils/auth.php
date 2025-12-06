<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $conn;
    
    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }
    
    public function verifyToken() {
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            return null;
        }
        
        $payload = JWT::decode($token);
        if (!$payload) {
            return null;
        }
        
        return $payload;
    }
    
    public function requireAuth() {
        $payload = $this->verifyToken();
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit();
        }
        return $payload;
    }
    
    public function requireRole($role) {
        $payload = $this->requireAuth();
        if ($payload['role'] !== $role) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit();
        }
        return $payload;
    }
    
    public function requireAdmin() {
        return $this->requireRole('admin');
    }
}
?>

