<?php
/**
 * Database Connection and Schema Test
 * Run this file in browser: http://localhost/hackathon/backend/test_database.php
 */

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<h1>HamroSewa Database Test</h1>";

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    echo "<p style='color: red;'>❌ Database connection failed!</p>";
    echo "<p>Check your database configuration in backend/config/database.php</p>";
    exit;
}

echo "<p style='color: green;'>✅ Database connection successful!</p>";

// Check if users table exists
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() === 0) {
        echo "<p style='color: red;'>❌ Users table does not exist!</p>";
        echo "<p>Run: mysql -u root -p hamrosewa < database/schema.sql</p>";
        exit;
    }
    echo "<p style='color: green;'>✅ Users table exists</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error checking tables: " . $e->getMessage() . "</p>";
    exit;
}

// Check for required columns
$requiredColumns = ['name', 'email', 'password_hash', 'is_donor', 'blood_group', 'phone'];
$locationColumns = ['province', 'district', 'municipality'];

echo "<h2>Checking Required Columns</h2>";
$stmt = $conn->query("SHOW COLUMNS FROM users");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

$missingColumns = [];
foreach ($requiredColumns as $col) {
    if (!in_array($col, $columns)) {
        $missingColumns[] = $col;
        echo "<p style='color: red;'>❌ Missing column: $col</p>";
    } else {
        echo "<p style='color: green;'>✅ Column exists: $col</p>";
    }
}

echo "<h2>Checking Location Columns</h2>";
$missingLocation = [];
foreach ($locationColumns as $col) {
    if (!in_array($col, $columns)) {
        $missingLocation[] = $col;
        echo "<p style='color: orange;'>⚠️ Missing location column: $col</p>";
    } else {
        echo "<p style='color: green;'>✅ Location column exists: $col</p>";
    }
}

if (!empty($missingLocation)) {
    echo "<h3>Fix Required</h3>";
    echo "<p>Run this SQL to add missing location columns:</p>";
    echo "<pre style='background: #f0f0f0; padding: 10px; border-radius: 5px;'>";
    echo "USE hamrosewa;\n\n";
    foreach ($missingLocation as $col) {
        $after = $col === 'province' ? 'city' : ($col === 'district' ? 'province' : 'district');
        echo "ALTER TABLE users ADD COLUMN $col VARCHAR(100) NULL AFTER $after;\n";
    }
    echo "\nCREATE INDEX idx_province ON users(province);\n";
    echo "CREATE INDEX idx_district ON users(district);\n";
    echo "CREATE INDEX idx_municipality ON users(municipality);\n";
    echo "</pre>";
    echo "<p>Or run: <code>mysql -u root -p hamrosewa < database/quick_fix.sql</code></p>";
}

// Check indexes
echo "<h2>Checking Indexes</h2>";
$stmt = $conn->query("SHOW INDEXES FROM users");
$indexes = $stmt->fetchAll(PDO::FETCH_COLUMN, 2);

$requiredIndexes = ['idx_province', 'idx_district', 'idx_municipality', 'idx_blood_group', 'idx_is_donor'];
foreach ($requiredIndexes as $idx) {
    if (in_array($idx, $indexes)) {
        echo "<p style='color: green;'>✅ Index exists: $idx</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Missing index: $idx (optional but recommended)</p>";
    }
}

// Test query
echo "<h2>Testing Queries</h2>";
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_donor = 1");
    $result = $stmt->fetch();
    echo "<p style='color: green;'>✅ Query test successful</p>";
    echo "<p>Total donors in database: <strong>" . $result['count'] . "</strong></p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Query test failed: " . $e->getMessage() . "</p>";
}

// Summary
echo "<h2>Summary</h2>";
if (empty($missingColumns) && empty($missingLocation)) {
    echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✅ Database is properly configured!</p>";
    echo "<p>You can now test registration and donor search.</p>";
} else {
    echo "<p style='color: orange; font-size: 18px; font-weight: bold;'>⚠️ Some columns are missing</p>";
    echo "<p>Please run the migration script to fix this.</p>";
}

?>






