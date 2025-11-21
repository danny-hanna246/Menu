<?php
// Enhanced login.php with proper security measures
require 'functions.php';
require 'db.php';
require_once 'config.php';
require 'auth.php';

$error = '';
$rateLimitKey = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

// Check rate limiting
if (!checkRateLimit($rateLimitKey, MAX_LOGIN_ATTEMPTS, LOGIN_LOCKOUT_TIME)) {
    $error = 'Too many login attempts. Please try again later.';
    logError("Rate limit exceeded for login", ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    // CSRF token validation
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRFToken($token)) {
        $error = 'Invalid security token. Please try again.';
        logError("CSRF token validation failed", ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } else {
        // Input validation and sanitization
        $username = sanitizeString($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Basic input validation
        if (empty($username) || empty($password)) {
            $error = 'Username and password are required.';
        } elseif (strlen($username) > 100 || strlen($password) > 255) {
            $error = 'Invalid input length.';
        } else {
            try {
                // Fetch user with prepared statement
                $stmt = $pdo->prepare("SELECT id, username, password, last_login, failed_attempts, locked_until FROM users WHERE username = ? AND active = 1 LIMIT 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // Check if account is locked
                    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                        $error = 'Account is temporarily locked. Please try again later.';
                        logError("Login attempt on locked account", ['username' => $username]);
                    } else {
                        // Verify password using secure hash comparison
                        if (password_verify($password, $user['password'])) {
                            // Successful login
                            session_regenerate_id(true);
                            $_SESSION['admin'] = [
                                'id' => $user['id'],
                                'username' => $user['username'],
                                'login_time' => time(),
                                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                            ];

                            // Reset failed attempts and update last login
                            $updateStmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?");
                            $updateStmt->execute([$user['id']]);

                            // Log successful login
                            logError("Successful login", ['username' => $username, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);

                            // Clear rate limiting on successful login
                            unset($_SESSION[$rateLimitKey]);

                            header('Location: dashboard.php');
                            exit;
                        } else {
                            // Failed login - increment attempts
                            $failedAttempts = ($user['failed_attempts'] ?? 0) + 1;
                            $lockUntil = null;

                            if ($failedAttempts >= 5) {
                                $lockUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
                            }

                            $updateStmt = $pdo->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?");
                            $updateStmt->execute([$failedAttempts, $lockUntil, $user['id']]);

                            $error = 'Invalid username or password.';
                            logError("Failed login attempt", ['username' => $username, 'attempts' => $failedAttempts]);
                        }
                    }
                } else {
                    // User not found - still show generic error to prevent username enumeration
                    $error = 'Invalid username or password.';
                    logError("Login attempt with non-existent username", ['username' => $username]);

                    // Add delay to prevent timing attacks
                    usleep(random_int(100000, 500000)); // 0.1-0.5 seconds
                }
            } catch (PDOException $e) {
                logError("Database error during login", ['error' => $e->getMessage()]);
                $error = 'Login system temporarily unavailable. Please try again later.';
            }
        }
    }
}

// Generate CSRF token for the form
$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Admin Login</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .security-info {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #ccc;
            font-size: 14px;
        }

        .security-info h4 {
            color: #f8f8f8;
            margin-bottom: 10px;
        }

        .strength-meter {
            height: 4px;
            background: #333;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }

        .strength-meter-fill {
            height: 100%;
            background: #ff4444;
            width: 0%;
            transition: all 0.3s ease;
        }

        .strength-weak {
            background: #ff4444 !important;
        }

        .strength-medium {
            background: #ffaa00 !important;
        }

        .strength-strong {
            background: #44ff44 !important;
        }

        .login-attempts {
            color: #ffaa00;
            font-size: 12px;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h1>🔐 Admin Login</h1>
        <p style="text-align: center; color: #666; margin-bottom: 30px;">
            Secure access to your restaurant management panel
        </p>

        <?php if ($error): ?>
            <div class="error">
                ⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="security-info">
            <h4>🛡️ Security Notice</h4>
            <ul style="margin: 0; padding-left: 20px;">
                <li>Your session will expire after <?= SESSION_TIMEOUT / 60 ?> minutes of inactivity</li>
                <li>Account will be locked after <?= MAX_LOGIN_ATTEMPTS ?> failed attempts</li>
                <li>All login attempts are logged for security</li>
            </ul>
        </div>

        <form method="post" autocomplete="on">
            <!-- CSRF Protection -->
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <label>
                👤 Username
                <input type="text"
                    name="username"
                    required
                    maxlength="100"
                    autocomplete="username"
                    placeholder="Enter your username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </label>

            <label>
                🔑 Password
                <input type="password"
                    name="password"
                    required
                    maxlength="255"
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    id="password">
                <div class="strength-meter">
                    <div class="strength-meter-fill" id="strengthMeter"></div>
                </div>
            </label>

            <button type="submit" style="width: 100%; margin-top: 20px;">
                🔓 Login to Dashboard
            </button>
        </form>

        <div style="text-align: center; margin-top: 20px; color: #999; font-size: 14px;">
            <p>🔒 Secure admin access for restaurant management</p>
            <?php if (isset($_SESSION[$rateLimitKey])): ?>
                <div class="login-attempts">
                    Login attempts: <?= $_SESSION[$rateLimitKey]['count'] ?? 0 ?>/<?= MAX_LOGIN_ATTEMPTS ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const meter = document.getElementById('strengthMeter');

            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            const percentage = (strength / 5) * 100;
            meter.style.width = percentage + '%';

            meter.className = 'strength-meter-fill';
            if (strength <= 2) meter.classList.add('strength-weak');
            else if (strength <= 3) meter.classList.add('strength-medium');
            else meter.classList.add('strength-strong');
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.querySelector('input[name="username"]').value.trim();
            const password = document.querySelector('input[name="password"]').value;

            if (!username || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
                return false;
            }

            if (username.length > 100 || password.length > 255) {
                e.preventDefault();
                alert('Input too long');
                return false;
            }
        });

        // Security: Clear password field on page unload
        window.addEventListener('beforeunload', function() {
            document.querySelector('input[name="password"]').value = '';
        });
    </script>
</body>

</html>