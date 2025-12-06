<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get search parameters
$province = isset($_GET['province']) && $_GET['province'] !== '' ? $_GET['province'] : null;
$district = isset($_GET['district']) && $_GET['district'] !== '' ? $_GET['district'] : null;
$municipality = isset($_GET['municipality']) && $_GET['municipality'] !== '' ? $_GET['municipality'] : null;
$bloodGroup = isset($_GET['bloodGroup']) && $_GET['bloodGroup'] !== '' ? $_GET['bloodGroup'] : null;

// Check if location columns exist
$checkColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'province'");
$hasLocationFields = $checkColumns->rowCount() > 0;

// Build query based on available columns
if ($hasLocationFields) {
    $sql = "
        SELECT id, name, email, blood_group, phone, city, 
               province, district, municipality
        FROM users 
        WHERE is_donor = 1
    ";
} else {
    $sql = "
        SELECT id, name, email, blood_group, phone, city
        FROM users 
        WHERE is_donor = 1
    ";
}

$params = [];

if ($bloodGroup) {
    $sql .= " AND blood_group = ?";
    $params[] = $bloodGroup;
}

if ($hasLocationFields) {
    if ($province) {
        $sql .= " AND province = ?";
        $params[] = $province;
    }

    if ($district) {
        $sql .= " AND district = ?";
        $params[] = $district;
    }

    if ($municipality) {
        $sql .= " AND municipality = ?";
        $params[] = $municipality;
    }
}

$sql .= " ORDER BY name ASC";

try {
    // Debug logging
    error_log("Search query: " . $sql);
    error_log("Search params: " . json_encode($params));
    
    if (empty($params)) {
        $stmt = $conn->query($sql);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
    }
    
    $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response - keep camelCase for frontend compatibility
    $formattedDonors = [];
    foreach ($donors as $donor) {
        $formattedDonor = [
            'id' => (int)$donor['id'],
            'name' => $donor['name'],
            'bloodGroup' => $donor['blood_group'] ?? null,
        ];
        
        // Include phone if available
        if (!empty($donor['phone'])) {
            $formattedDonor['phone'] = $donor['phone'];
        }
        
        if ($hasLocationFields) {
            $formattedDonor['province'] = $donor['province'] ?? null;
            $formattedDonor['district'] = $donor['district'] ?? null;
            $formattedDonor['municipality'] = $donor['municipality'] ?? null;
        }
        
        if (!empty($donor['city'])) {
            $formattedDonor['city'] = $donor['city'];
        }
        
        // Include all donors (even without phone) - frontend will handle display
        $formattedDonors[] = $formattedDonor;
    }
    
    error_log("Found " . count($formattedDonors) . " donors");
    
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode($formattedDonors, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    error_log("Search error: " . $e->getMessage());
    error_log("SQL: " . $sql);
    error_log("Params: " . json_encode($params));
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Search failed',
        'message' => $e->getMessage(),
        'debug' => [
            'sql' => $sql,
            'params' => $params
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>

