<?php
// Enhanced logout.php with comprehensive security measures

require_once 'config.php';
require 'functions.php';
require 'auth.php';
require_once 'config.php';
// Start secure session if not already started
if (session_status() === PHP_SESSION_NONE) {
    startSecureSession();
}

// Get current admin info for audit log before destroying session
$currentAdmin = null;
if (isset($_SESSION['admin']) && is_array($_SESSION['admin'])) {
    $currentAdmin = $_SESSION['admin'];
}

// Rate limiting for logout requests to prevent abuse
$rateLimitKey = 'logout_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!checkRateLimit($rateLimitKey, 20, 300)) { // 20 logouts per 5 minutes
    logError("Logout rate limit exceeded", [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    // Still allow logout but log the suspicious activity
}

// Validate logout request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    logError("Invalid logout request method", [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method not allowed');
}
// CSRF protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? $_GET[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRFToken($token)) {
        logError("CSRF token validation failed on logout", [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user' => $currentAdmin['username'] ?? 'unknown'
        ]);
        // Still allow logout for security, but log the incident
    }
}

// Audit log for logout
if ($currentAdmin) {
    auditLog('user_logout', [
        'username' => $currentAdmin['username'],
        'login_time' => $currentAdmin['login_time'] ?? 'unknown',
        'session_duration' => time() - ($currentAdmin['login_time'] ?? time()),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
    ]);

    logError("User logout", [
        'username' => $currentAdmin['username'],
        'session_duration_minutes' => round((time() - ($currentAdmin['login_time'] ?? time())) / 60, 2),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
}

// Comprehensive session cleanup
function secureLogout()
{
    // Clear all session variables
    $_SESSION = [];

    // Delete the session cookie securely
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

        // Also clear any other related cookies
        $cookiesToClear = [
            'PHPSESSID',
            'admin_session',
            'remember_me',
            'auth_token'
        ];

        foreach ($cookiesToClear as $cookieName) {
            if (isset($_COOKIE[$cookieName])) {
                setcookie(
                    $cookieName,
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
        }
    }

    // Destroy the session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    // Clear any temporary files or caches related to this session
    $tempDir = sys_get_temp_dir();
    $sessionFiles = glob($tempDir . '/sess_' . session_id() . '*');
    foreach ($sessionFiles as $file) {
        if (file_exists($file)) {
            @unlink($file);
        }
    }
}

// Perform secure logout
secureLogout();

// Set security headers for logout response
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Handle different logout scenarios
$redirectUrl = 'login.php';
$message = '';

// Check for logout reason in URL parameters
$reason = $_GET['reason'] ?? '';
switch ($reason) {
    case 'timeout':
        $message = 'Your session has expired. Please log in again.';
        break;
    case 'security':
        $message = 'You have been logged out for security reasons.';
        break;
    case 'inactivity':
        $message = 'You have been logged out due to inactivity.';
        break;
    case 'force':
        $message = 'Your session has been terminated by an administrator.';
        break;
    default:
        $message = 'You have been successfully logged out.';
}

// Handle AJAX logout requests
if (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'redirect' => $redirectUrl,
        'timestamp' => date('c')
    ]);
    exit;
}

// For API requests
if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'redirect' => $redirectUrl,
        'timestamp' => date('c')
    ]);
    exit;
}

// Standard redirect for web requests
if (!empty($message)) {
    $redirectUrl .= '?message=' . urlencode($message);
}

// Use JavaScript redirect for better UX and to ensure client-side cleanup
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - Restaurant Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #000000;
            color: #f8f8f8;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .logout-container {
            text-align: center;
            background: #464747;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(248, 248, 248, 0.1);
            max-width: 400px;
            width: 90%;
        }

        .logout-icon {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .logout-message {
            margin-bottom: 20px;
            color: #ccc;
            line-height: 1.5;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(248, 248, 248, 0.3);
            border-radius: 50%;
            border-top-color: #f8f8f8;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .redirect-info {
            font-size: 14px;
            color: #999;
            margin-top: 20px;
        }

        .manual-link {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            background: #464747;
            color: #f8f8f8;
            text-decoration: none;
            border-radius: 5px;
            border: 1px solid #f8f8f8;
            transition: all 0.3s ease;
        }

        .manual-link:hover {
            background: #000000;
            transform: translateY(-1px);
        }
    </style>
</head>

<body>
    <div class="logout-container">
        <div class="logout-icon">🔒</div>
        <h2>Logging Out...</h2>
        <div class="logout-message">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div class="loading"></div>
        <span>Securing your session...</span>
        <div class="redirect-info">
            You will be redirected to the login page in a moment.
        </div>
        <a href="<?= htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') ?>" class="manual-link">
            Continue to Login
        </a>
    </div>

    <script>
        // Client-side session cleanup
        function clearClientData() {
            // Clear localStorage
            try {
                localStorage.clear();
            } catch (e) {
                console.warn('Could not clear localStorage:', e);
            }

            // Clear sessionStorage
            try {
                sessionStorage.clear();
            } catch (e) {
                console.warn('Could not clear sessionStorage:', e);
            }

            // Clear any cached data
            if ('caches' in window) {
                caches.keys().then(function(names) {
                    names.forEach(function(name) {
                        caches.delete(name);
                    });
                });
            }

            // Clear any service worker registrations
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistrations().then(function(registrations) {
                    registrations.forEach(function(registration) {
                        registration.unregister();
                    });
                });
            }
        }

        // Perform client-side cleanup
        clearClientData();

        // Redirect after a short delay
        setTimeout(function() {
            // Replace current history entry to prevent back button issues
            window.location.replace('<?= htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') ?>');
        }, 2000);

        // Security: Prevent user from staying on this page
        window.addEventListener('beforeunload', function() {
            clearClientData();
        });

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function() {
            window.location.replace('<?= htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') ?>');
        });

        // Prevent caching of this page
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.replace('<?= htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') ?>');
            }
        });
    </script>
</body>

</html>