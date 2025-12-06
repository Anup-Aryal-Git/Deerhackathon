<?php
// Test endpoint to verify backend is working
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/cors.php';

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Backend API is working!',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => $_SERVER['SERVER_NAME'] ?? 'localhost'
]);
?>

