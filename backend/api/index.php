<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/response.php';

// Handle CORS
handleCors();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';

// Remove leading slash
$path = ltrim($path, '/');

// Route to appropriate endpoint
$routes = [
    'auth' => 'auth.php',
    'users' => 'users.php',
    'feed' => 'feed.php',
    'camps' => 'camps.php',
    'tasks' => 'tasks.php',
    'campaigns' => 'campaigns.php',
    'polls' => 'polls.php',
    'organizations' => 'organizations.php',
    'notifications' => 'notifications.php',
    'likes' => 'likes.php',
    'posts' => 'posts.php'
];

$pathParts = explode('/', $path);
$route = $pathParts[0];

if (isset($routes[$route])) {
    require_once __DIR__ . '/' . $routes[$route];
} else {
    sendError('Endpoint not found', 404);
}
?>

