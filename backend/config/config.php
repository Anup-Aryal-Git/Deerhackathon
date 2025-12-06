<?php
// Application configuration

// Timezone
date_default_timezone_set('Asia/Kathmandu');

// JWT Configuration
define('JWT_SECRET', 'hamrosewa-secret-key-change-in-production-2024');
define('JWT_ALGORITHM', 'HS256');
define('JWT_ACCESS_EXPIRY', 86400); // 24 hours
define('JWT_REFRESH_EXPIRY', 604800); // 7 days

// CORS Configuration
define('CORS_ORIGIN', 'http://localhost:3000');
define('ALLOWED_ORIGINS', ['http://localhost:3000', 'http://localhost:3001']);

// File Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Email Configuration (SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM_EMAIL', 'noreply@hamrosewa.com');
define('SMTP_FROM_NAME', 'HamroSewa');

// Rate Limiting
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 3600); // 1 hour

// Application Settings
define('APP_NAME', 'HamroSewa');
define('APP_URL', 'http://localhost/hackathondeer/backend');
define('API_VERSION', 'v1');

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');
?>

