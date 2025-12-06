<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$auth = new Auth();

$db = new Database();
$conn = $db->getConnection();

if ($method === 'GET') {
    $status = $_GET['status'] ?? null;
    $orgId = $_GET['orgId'] ?? null;
    
    $sql = "
        SELECT c.*, o.name as org_name, o.verified as org_verified,
               COUNT(DISTINCT ca.id) as attendee_count
        FROM camps c
        LEFT JOIN organizations o ON c.org_id = o.id
        LEFT JOIN camp_attendees ca ON c.id = ca.camp_id
        WHERE 1=1
    ";
    $params = [];
    
    if ($status) {
        $sql .= " AND c.status = ?";
        $params[] = $status;
    }
    
    if ($orgId) {
        $sql .= " AND c.org_id = ?";
        $params[] = $orgId;
    }
    
    $sql .= " GROUP BY c.id ORDER BY c.date_time ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $camps = $stmt->fetchAll();
    
    foreach ($camps as &$camp) {
        $camp['needed_groups'] = json_decode($camp['needed_groups'] ?? '[]', true);
        $camp['location'] = [
            'lat' => (float)$camp['location_lat'],
            'lng' => (float)$camp['location_lng'],
            'address' => $camp['location_address']
        ];
        unset($camp['location_lat'], $camp['location_lng'], $camp['location_address']);
    }
    
    http_response_code(200);
    echo json_encode($camps);
    
} elseif ($method === 'POST') {
    $payload = $auth->requireAuth();
    
    if ($payload['role'] !== 'organization' && $payload['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Only organizations can create camps']);
        exit();
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['title', 'dateTime', 'orgId'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing field: $field"]);
            exit();
        }
    }
    
    // Verify org ownership
    if ($payload['role'] !== 'admin') {
        $stmt = $conn->prepare("SELECT id FROM organizations WHERE id = ? AND created_by_user_id = ?");
        $stmt->execute([$data['orgId'], $payload['userId']]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Not authorized to create camp for this organization']);
            exit();
        }
    }
    
    $location = $data['location'] ?? [];
    
    $stmt = $conn->prepare("
        INSERT INTO camps (org_id, title, description, date_time, 
                          location_lat, location_lng, location_address, 
                          needed_groups, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'upcoming')
    ");
    
    $stmt->execute([
        $data['orgId'],
        $data['title'],
        $data['description'] ?? null,
        $data['dateTime'],
        $location['lat'] ?? null,
        $location['lng'] ?? null,
        $location['address'] ?? null,
        json_encode($data['neededGroups'] ?? [])
    ]);
    
    $campId = $conn->lastInsertId();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'id' => $campId,
        'message' => 'Camp created successfully'
    ]);
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>

