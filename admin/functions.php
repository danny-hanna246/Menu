<?php
function logAudit(string $action, array $meta = [])
{
    // make sure $pdo is available
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userId = $_SESSION['user_id'] ?? null; // adjust if you use another session key

    // Normalize meta
    $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Try DB insert if $pdo exists
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("INSERT INTO audits (action, meta, user_id, ip_address) VALUES (:action, :meta, :user_id, :ip)");
            $stmt->execute([
                ':action' => $action,
                ':meta' => $metaJson,
                ':user_id' => $userId,
                ':ip' => $ip
            ]);
            return true;
        } catch (Exception $e) {
            // fall through to file fallback
            error_log("logAudit DB failed: " . $e->getMessage());
        }
    }

    // Fallback: append to file
    try {
        $logPath = defined('LOG_PATH') ? rtrim(LOG_PATH, '/\\') . '/' : __DIR__ . '/../logs/';
        if (!is_dir($logPath)) {
            @mkdir($logPath, 0755, true);
        }
        $entry = [
            'ts' => date('c'),
            'action' => $action,
            'meta' => $meta,
            'user_id' => $userId,
            'ip' => $ip,
            'request' => [
                'uri' => $_SERVER['REQUEST_URI'] ?? null,
                'method' => $_SERVER['REQUEST_METHOD'] ?? null
            ]
        ];
        file_put_contents($logPath . 'audit.log', json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
        return true;
    } catch (Exception $e) {
        error_log("logAudit fallback failed: " . $e->getMessage());
        return false;
    }
}
function validateInput($type, $value, $options = [])
{
    $value = trim($value);

    switch ($type) {
        case 'string':
            $maxLength = $options['max_length'] ?? 255;
            if (strlen($value) > $maxLength) {
                throw new InvalidArgumentException("String too long (max: {$maxLength})");
            }
            return sanitizeString($value);

        case 'int':
            $min = $options['min'] ?? PHP_INT_MIN;
            $max = $options['max'] ?? PHP_INT_MAX;
            $int = filter_var($value, FILTER_VALIDATE_INT);
            if ($int === false || $int < $min || $int > $max) {
                throw new InvalidArgumentException("Invalid integer value");
            }
            return $int;

        case 'float':
            $min = $options['min'] ?? -PHP_FLOAT_MAX;
            $max = $options['max'] ?? PHP_FLOAT_MAX;
            $float = filter_var($value, FILTER_VALIDATE_FLOAT);
            if ($float === false || $float < $min || $float > $max) {
                throw new InvalidArgumentException("Invalid float value");
            }
            return $float;

        case 'email':
            $email = filter_var($value, FILTER_VALIDATE_EMAIL);
            if ($email === false) {
                throw new InvalidArgumentException("Invalid email address");
            }
            return $email;

        case 'url':
            $url = filter_var($value, FILTER_VALIDATE_URL);
            if ($url === false) {
                throw new InvalidArgumentException("Invalid URL");
            }
            return $url;

        default:
            throw new InvalidArgumentException("Unknown validation type: {$type}");
    }
}
