<?php
// Enhanced db.php with improved security and error handling

require_once 'config.php';

// Set security headers
setSecurityHeaders();

try {
    // PDO options for security
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdoOptions);

    // Set SQL mode for strict data handling
    $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
} catch (PDOException $e) {
    // Log the actual error for debugging
    logError("Database connection failed", [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    // Show generic error to user
    if (php_sapi_name() === 'cli') {
        die("Database connection failed. Check logs for details.\n");
    } else {
        http_response_code(500);
        die("Service temporarily unavailable. Please try again later.");
    }
}

// Database helper functions
class DatabaseHelper
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Execute a prepared statement with error handling
     */
    public function execute($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            logError("Database query failed", [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Database operation failed");
        }
    }

    /**
     * Get a single row
     */
    public function fetch($sql, $params = [])
    {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Get all rows
     */
    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Insert with validation
     */
    public function insert($table, $data)
    {
        $columns = array_keys($data);
        $placeholders = ':' . implode(', :', $columns);
        $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES ({$placeholders})";

        $stmt = $this->execute($sql, $data);
        return $this->pdo->lastInsertId();
    }

    /**
     * Update with validation
     */
    public function update($table, $data, $where, $whereParams = [])
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "`{$column}` = :{$column}";
        }

        $sql = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE {$where}";
        $params = array_merge($data, $whereParams);

        return $this->execute($sql, $params);
    }

    /**
     * Delete with validation
     */
    public function delete($table, $where, $whereParams = [])
    {
        $sql = "DELETE FROM `{$table}` WHERE {$where}";
        return $this->execute($sql, $whereParams);
    }

    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit()
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback()
    {
        return $this->pdo->rollback();
    }
}

// Create database helper instance
$db = new DatabaseHelper($pdo);

// File upload validation
function validateImageUpload($file)
{
    $errors = [];

    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed';
        return $errors;
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = 'File size exceeds maximum limit of ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB';
    }

    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
        $errors[] = 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed';
    }

    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $allowedExtensions)) {
        $errors[] = 'Invalid file extension';
    }

    // Check if file is actually an image
    if (!getimagesize($file['tmp_name'])) {
        $errors[] = 'File is not a valid image';
    }

    return $errors;
}

// Secure file upload function
function uploadImage($file, $prefix = '')
{
    $errors = validateImageUpload($file);
    if (!empty($errors)) {
        throw new Exception('File validation failed: ' . implode(', ', $errors));
    }

    // Generate secure filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $prefix . uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $targetPath = UPLOAD_PATH . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to move uploaded file');
    }

    // Set proper permissions
    chmod($targetPath, 0644);

    return $filename;
}

// Initialize secure session
startSecureSession();
