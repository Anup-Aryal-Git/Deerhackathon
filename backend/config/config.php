<?php
// Application configuration
define('JWT_SECRET', 'your-secret-key-change-in-production');
define('JWT_EXPIRATION', 3600 * 24); // 24 hours
define('REFRESH_TOKEN_EXPIRATION', 3600 * 24 * 7); // 7 days
define('CORS_ORIGIN', 'http://localhost:3000');

// Email configuration (for verification)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('FROM_EMAIL', 'noreply@hamrosewa.com');
define('FROM_NAME', 'HamroSewa');

// File upload
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Rate limiting
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 3600); // 1 hour

// Timezone
date_default_timezone_set('Asia/Kathmandu');
?>

