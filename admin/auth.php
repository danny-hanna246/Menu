<?php
// Enhanced auth.php with improved security measures
require_once 'config.php';

// Start secure session if not already started
if (session_status() === PHP_SESSION_NONE) {
    startSecureSession();
}

/**
 * Check if user is authenticated and session is valid
 */
function isAuthenticated()
{
    // Check if admin session exists
    if (!isset($_SESSION['admin']) || !is_array($_SESSION['admin'])) {
        return false;
    }

    // Check required session fields
    $requiredFields = ['id', 'username', 'login_time', 'ip'];
    foreach ($requiredFields as $field) {
        if (!isset($_SESSION['admin'][$field])) {
            return false;
        }
    }

    // Check session timeout
    if (time() - $_SESSION['admin']['login_time'] > SESSION_TIMEOUT) {
        destroySession();
        return false;
    }

    // Check IP consistency (optional, can be disabled for mobile users)
    $currentIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if ($_SESSION['admin']['ip'] !== $currentIP) {
        logError("IP address changed during session", [
            'username' => $_SESSION['admin']['username'],
            'original_ip' => $_SESSION['admin']['ip'],
            'current_ip' => $currentIP
        ]);
        // Uncomment the following lines to enforce strict IP checking
        // destroySession();
        // return false;
    }

    return true;
}

/**
 * Require authentication - redirect to login if not authenticated
 */


// ===================================================================
// CSRF PROTECTION FUNCTIONS - START
// ===================================================================


/**
 * Generates a secure CSRF token and stores it in the session.
 * @return string The generated token.
 */
function generateCSRFToken()
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_token_time'] = time();
    return $token;
}

/**
 * Renders the HTML for the hidden CSRF token input field.
 * It always generates a fresh token for the form being displayed.
 * @return string The HTML input field.
 */
function renderCSRFField()
{
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validates a given token against the one in the session.
 * Returns true or false. Ideal for pages with custom error handling like login.php.
 * @param string $token The token submitted from the form.
 * @return bool True if valid, false otherwise.
 */
function validateCSRFToken($token)
{
    if (empty($token) || empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time'])) {
        return false;
    }

    // Check for expiration (1 hour)
    if (time() - $_SESSION['csrf_token_time'] > 3600) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        return false;
    }

    $isValid = hash_equals($_SESSION['csrf_token'], $token);

    // Invalidate the token after the first check to prevent reuse
    unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);

    return $isValid;
}

/**
 * A wrapper function for all admin forms that use try-catch.
 * It gets the token from $_POST and throws an Exception on failure.
 * @throws Exception if validation fails.
 */
function validateFormCSRF()
{
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        throw new Exception('Invalid security token. Please refresh the page and try again.');
    }
}

// ===================================================================
// CSRF PROTECTION FUNCTIONS - END
// ===================================================================
function requireAuth()
{
    if (!isAuthenticated()) {
        // Clear any existing session data
        destroySession();

        // Log unauthorized access attempt
        logError("Unauthorized access attempt", [
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

        // Redirect to login page
        header('Location: login.php');
        exit;
    }

    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
}

/**
 * Safely destroy session
 */
function destroySession()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];

        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
    }
}

/**
 * Get current admin user info
 */
function getCurrentAdmin()
{
    if (!isAuthenticated()) {
        return null;
    }

    return $_SESSION['admin'];
}

/**
 * Check if user has specific permission (extensible for role-based access)
 */
function hasPermission($permission)
{
    if (!isAuthenticated()) {
        return false;
    }

    // For now, all authenticated admins have all permissions
    // This can be extended to check user roles and permissions from database
    return true;
}

/**
 * Require specific permission
 */
function requirePermission($permission)
{
    if (!hasPermission($permission)) {
        logError("Permission denied", [
            'username' => $_SESSION['admin']['username'] ?? 'unknown',
            'permission' => $permission,
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ]);

        http_response_code(403);
        die('Access denied: Insufficient permissions');
    }
}

/**
 * Generate and validate CSRF tokens for forms
 */

/**
 * Validate and sanitize form input
 */


/**
 * Security audit log
 */
function auditLog($action, $details = [])
{
    $admin = getCurrentAdmin();
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'username' => $admin['username'] ?? 'unknown',
        'action' => $action,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'details' => $details
    ];

    $logFile = LOG_PATH . 'audit_' . date('Y-m-d') . '.log';
    $logMessage = json_encode($logData) . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Check authentication for all admin pages (except login.php)
$currentScript = basename($_SERVER['SCRIPT_NAME']);
if ($currentScript !== 'login.php') {
    requireAuth();

    // Audit page access
    auditLog('page_access', [
        'page' => $currentScript,
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'query' => $_SERVER['QUERY_STRING'] ?? ''
    ]);
}

// Additional security headers for admin pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
