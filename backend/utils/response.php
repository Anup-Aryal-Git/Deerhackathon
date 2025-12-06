<?php
function sendResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit();
}

function sendSuccess($message, $data = null, $statusCode = 200) {
    sendResponse(true, $message, $data, $statusCode);
}

function sendError($message, $statusCode = 400, $data = null) {
    sendResponse(false, $message, $data, $statusCode);
}
?>

