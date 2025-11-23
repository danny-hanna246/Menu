<?php
// Enhanced config.php with environment variable support

// Load environment variables from .env file if it exists
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Database configuration with fallbacks
define('DB_HOST', $_ENV['DB_HOST'] ?? '82.197.82.161');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'u307631199_Menu2DB');
define('DB_USER', $_ENV['DB_USER'] ?? 'u307631199_Menu2DB');
define('DB_PASS', $_ENV['DB_PASS'] ?? 'Menu2DB@123');

// Security settings
define('CSRF_TOKEN_NAME', '_token');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Application settings
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('LOG_PATH', __DIR__ . '/logs/');

// Create necessary directories
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}

// Security headers function
function setSecurityHeaders()
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Security-Policy: default-src \'self\'; img-src \'self\' data:; style-src \'self\' \'unsafe-inline\'; script-src \'self\' \'unsafe-inline\';');
}

// CSRF token functions

// Input sanitization functions
function sanitizeString($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function sanitizeInt($input)
{
    return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

function sanitizeFloat($input)
{
    return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

// Logging function
function logError($message, $context = [])
{
    $logFile = LOG_PATH . 'error_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' Context: ' . json_encode($context) : '';
    $logMessage = "[{$timestamp}] {$message}{$contextStr}" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Rate limiting functions
function checkRateLimit($identifier, $maxAttempts = 10, $timeWindow = 3600)
{
    if (!isset($_SESSION)) {
        session_start();
    }

    $key = "rate_limit_{$identifier}";
    $now = time();

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset_time' => $now + $timeWindow];
        return true;
    }

    if ($now > $_SESSION[$key]['reset_time']) {
        $_SESSION[$key] = ['count' => 0, 'reset_time' => $now + $timeWindow];
        return true;
    }

    if ($_SESSION[$key]['count'] >= $maxAttempts) {
        return false;
    }

    $_SESSION[$key]['count']++;
    return true;
}
function startSecureSession()
{
    // Do not start a new session if one is already active
    if (session_status() === PHP_SESSION_NONE) {
        $cookieParams = session_get_cookie_params();
        // Check if running on HTTPS
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_set_cookie_params([
            'lifetime' => $cookieParams['lifetime'],
            'path' => $cookieParams['path'],
            'domain' => $cookieParams['domain'],
            'secure' => $secure,       // Only send cookies over HTTPS if available
            'httponly' => true,     // Prevent JavaScript from accessing the session cookie
            'samesite' => 'Lax'     // Provides a good balance of security and usability
        ]);

        session_start();
    }
}
