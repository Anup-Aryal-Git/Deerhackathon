<?php
// CORS headers - Allow multiple origins for development
$allowed_origins = [
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'http://192.168.1.70:3000',
    'http://192.168.0.1:3000',
];

// Get the origin from the request
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Check if origin is in allowed list, or allow all in development
if (in_array($origin, $allowed_origins) || defined('ALLOW_ALL_ORIGINS')) {
    header("Access-Control-Allow-Origin: " . $origin);
} else {
    // For development, allow the requesting origin
    if (!empty($origin) && (strpos($origin, 'localhost') !== false || strpos($origin, '192.168') !== false || strpos($origin, '127.0.0.1') !== false)) {
        header("Access-Control-Allow-Origin: " . $origin);
    } else {
        header("Access-Control-Allow-Origin: " . CORS_ORIGIN);
    }
}

header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>

