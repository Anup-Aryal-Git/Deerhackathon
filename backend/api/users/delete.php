<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$auth = new Auth();
$payload = $auth->requireAuth();
$userId = $payload['userId'];
$role = $payload['role'];

$db = new Database();
$conn = $db->getConnection();

try {
    $conn->beginTransaction();

    if ($role === 'organization') {
        $orgStmt = $conn->prepare("SELECT id FROM organizations WHERE created_by_user_id = ?");
        $orgStmt->execute([$userId]);
        $org = $orgStmt->fetch();
        if ($org) {
            $conn->prepare("DELETE FROM campaigns WHERE org_id = ?")->execute([$org['id']]);
            $conn->prepare("DELETE FROM volunteer_tasks WHERE org_id = ?")->execute([$org['id']]);
            $conn->prepare("DELETE FROM camps WHERE org_id = ?")->execute([$org['id']]);
            $conn->prepare("DELETE FROM organizations WHERE id = ?")->execute([$org['id']]);
        }
    }

    $conn->prepare("DELETE FROM polls WHERE created_by = ?")->execute([$userId]);
    $conn->prepare("DELETE FROM post_likes WHERE user_id = ?")->execute([$userId]);
    $conn->prepare("DELETE FROM post_interests WHERE user_id = ?")->execute([$userId]);
    $conn->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$userId]);

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    $conn->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Account deleted'
    ]);
} catch (PDOException $e) {
    $conn->rollBack();
    error_log('Delete user error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete account']);
}



