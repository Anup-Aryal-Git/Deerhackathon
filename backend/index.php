<?php
// API Router
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/cors.php';

$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Remove query string
$path = parse_url($request_uri, PHP_URL_PATH);
// Remove /hackathon/backend or /backend from path
$path = preg_replace('#^/?hackathon/?backend/?|^/?backend/?#', '', $path);
$path = trim($path, '/');

// Handle index.php in path
if (strpos($path, 'index.php') !== false) {
    $path = str_replace('index.php', '', $path);
    $path = trim($path, '/');
}

// If path is empty or just 'index.php', check for direct API calls
if (empty($path) || $path === 'index.php') {
    // Try to get path from REQUEST_URI directly
    $path = $_GET['path'] ?? '';
}

// Route to appropriate endpoint
$routes = [
    'api/test' => 'api/test.php',
    'api/feed' => 'api/feed/index.php',
    'api/auth/signup' => 'api/auth/signup.php',
    'api/auth/login' => 'api/auth/login.php',
    'api/users/me' => 'api/users/me.php',
    'api/users/search' => 'api/users/search.php',
    'api/camps' => 'api/camps/index.php',
    'api/camps/join' => 'api/camps/join.php',
    'api/tasks' => 'api/tasks/index.php',
    'api/tasks/apply' => 'api/tasks/apply.php',
    'api/campaigns' => 'api/campaigns/index.php',
    'api/campaigns/support' => 'api/campaigns/support.php',
    'api/polls' => 'api/polls/index.php',
    'api/polls/vote' => 'api/polls/vote.php',
    'api/organizations' => 'api/organizations/index.php',
    'api/organizations/verify' => 'api/organizations/verify.php',
    'api/notifications' => 'api/notifications/index.php',
    'api/likes/toggle' => 'api/likes/toggle.php',
    'api/posts/delete' => 'api/posts/delete.php',
    'api/posts/interest' => 'api/posts/interest.php',
    'api/users/delete' => 'api/users/delete.php',
];

if (isset($routes[$path])) {
    require_once __DIR__ . '/' . $routes[$path];
} else {
    // Debug output
    error_log("Path not found: " . $path);
    error_log("Request URI: " . $request_uri);
    http_response_code(404);
    echo json_encode([
        'error' => 'Endpoint not found',
        'path' => $path,
        'request_uri' => $request_uri
    ]);
}
?>

