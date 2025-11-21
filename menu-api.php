<?php
// Enhanced menu-api.php with comprehensive security and performance improvements

// Prevent direct access if not via proper request
if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit('Method not allowed');
}

// Start output buffering and error handling
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Security headers for API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: public, max-age=300'); // 5 minutes cache

// Rate limiting for API
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitKey = 'api_' . $clientIP;

// Include dependencies
try {
    if (!file_exists('admin/config.php')) {
        throw new Exception('Configuration file not found');
    }
    require_once 'admin/functions.php';
    require_once 'admin/config.php';

    if (!file_exists('admin/db.php')) {
        throw new Exception('Database configuration not found');
    }

    require_once 'admin/db.php';

    if (!file_exists('admin/translations.php')) {
        throw new Exception('Translation system not found');
    }

    require_once 'admin/translations.php';
} catch (Exception $e) {
    logError("API dependency loading failed", ['error' => $e->getMessage(), 'ip' => $clientIP]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Service configuration error',
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check rate limiting
if (!checkRateLimit($rateLimitKey, 100, 3600)) { // 100 requests per hour
    logError("API rate limit exceeded", ['ip' => $clientIP]);
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Rate limit exceeded. Please try again later.',
        'retry_after' => 3600,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Validate database connection
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }

    // Initialize translation helper
    $translation = new Translation($pdo);

    // Input validation and sanitization
    $requestedLang = $_GET['lang'] ?? '';
    $menuTypeFilter = $_GET['menu_type'] ?? '';
    $categoryFilter = $_GET['category'] ?? '';

    // Sanitize language parameter
    if (!empty($requestedLang)) {
        $requestedLang = preg_replace('/[^a-z]/i', '', $requestedLang);
        if (strlen($requestedLang) > 5) {
            $requestedLang = '';
        }
    }

    // Get default language if none specified or invalid
    if (empty($requestedLang)) {
        $requestedLang = $translation->getDefaultLanguage();
    }

    // Validate language exists
    $languages = $translation->getLanguages();
    $validLangs = array_column($languages, 'code');
    if (!in_array($requestedLang, $validLangs)) {
        $requestedLang = $translation->getDefaultLanguage();
    }

    // Validate menu type filter if provided
    $menuTypeId = null;
    if (!empty($menuTypeFilter)) {
        try {
            $menuTypeId = validateInput('int', $menuTypeFilter, ['min' => 1]);
            // Verify menu type exists
            $menuTypes = $translation->getMenuTypesWithTranslations($requestedLang);
            $validMenuType = false;
            foreach ($menuTypes as $mt) {
                if ($mt['id'] == $menuTypeId) {
                    $validMenuType = true;
                    break;
                }
            }
            if (!$validMenuType) {
                $menuTypeId = null;
            }
        } catch (Exception $e) {
            $menuTypeId = null;
        }
    }

    // Cache key for performance
    $cacheKey = "menu_api_{$requestedLang}_{$menuTypeId}_{$categoryFilter}";

    // Try to get from cache (simple file-based cache)
    $cacheFile = LOG_PATH . "cache_{$cacheKey}.json";
    $cacheExpiry = 300; // 5 minutes

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheExpiry) {
        $cachedData = file_get_contents($cacheFile);
        if ($cachedData) {
            header('X-Cache: HIT');
            echo $cachedData;
            exit;
        }
    }

    // Get menu items with translations
    $items = $translation->getMenuItemsWithTranslations($requestedLang, $menuTypeId);

    // Filter by category if specified
    if (!empty($categoryFilter)) {
        $categoryFilter = sanitizeString($categoryFilter);
        $items = array_filter($items, function ($item) use ($categoryFilter) {
            return stripos($item['category'], $categoryFilter) !== false;
        });
    }

    // Filter only items with valid categories and process
    $validItems = array_filter($items, function ($item) {
        return !empty($item['category']) && !empty($item['name']);
    });

    // Process items for API response
    $processedItems = [];
    foreach ($validItems as $item) {
        $processedItem = [
            'id' => (int)$item['id'],
            'name' => $item['name'],
            'description' => $item['description'] ?? '',
            'category' => $item['category'],
            'menu_type' => $item['menu_type'] ?? '',
            'price' => formatPrice($item['price']),
            'image' => null,
            'created_at' => $item['created_at']
        ];

        // Handle image with security checks
        if (!empty($item['image'])) {
            $imagePath = "uploads/" . $item['image'];
            $fullImagePath = __DIR__ . '/' . $imagePath;

            // Verify image file exists and is readable
            if (file_exists($fullImagePath) && is_readable($fullImagePath)) {
                // Additional security: verify it's actually an image
                $imageInfo = @getimagesize($fullImagePath);
                if ($imageInfo !== false) {
                    $processedItem['image'] = $imagePath;
                }
            }
        }

        $processedItems[] = $processedItem;
    }

    // Get categories for this language and menu type
    $categories = $translation->getCategoriesWithTranslations($requestedLang, $menuTypeId);
    $categoryNames = array_unique(array_column($categories, 'name'));
    sort($categoryNames);

    // Get UI labels for this language
    $uiLabels = $translation->getUILabels($requestedLang);

    // Get current language info
    $currentLangStmt = $pdo->prepare("SELECT * FROM languages WHERE code = ? AND is_active = 1");
    $currentLangStmt->execute([$requestedLang]);
    $currentLanguage = $currentLangStmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentLanguage) {
        throw new Exception('Language not found or inactive');
    }

    // Performance metrics
    $endTime = microtime(true);
    $startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? $endTime;
    $executionTime = round(($endTime - $startTime) * 1000, 2); // milliseconds

    // Success response with comprehensive data
    $response = [
        'success' => true,
        'timestamp' => date('c'),
        'language' => [
            'current' => $currentLanguage,
            'available' => array_values($languages),
            'requested' => $requestedLang
        ],
        'ui_labels' => $uiLabels,
        'filters' => [
            'menu_type_id' => $menuTypeId,
            'category' => $categoryFilter ?: null
        ],
        'stats' => [
            'total_items' => count($processedItems),
            'categories_count' => count($categoryNames),
            'execution_time_ms' => $executionTime,
            'cache_status' => 'MISS'
        ],
        'categories' => $categoryNames,
        'data' => array_values($processedItems)
    ];

    // Clean output buffer
    ob_clean();

    // Generate JSON response
    $jsonResponse = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($jsonResponse === false) {
        throw new Exception('JSON encoding failed: ' . json_last_error_msg());
    }

    // Cache the response
    file_put_contents($cacheFile, $jsonResponse, LOCK_EX);

    // Add cache headers
    header('X-Cache: MISS');
    header('Cache-Control: public, max-age=' . $cacheExpiry);
    header('ETag: "' . md5($jsonResponse) . '"');

    // Output response
    echo $jsonResponse;

    // Log successful API call
    logError("API request successful", [
        'language' => $requestedLang,
        'menu_type' => $menuTypeId,
        'items_returned' => count($processedItems),
        'execution_time' => $executionTime,
        'ip' => $clientIP
    ]);
} catch (Exception $e) {
    // Clean output buffer
    ob_clean();

    // Log the actual error for debugging
    logError("API request failed", [
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'lang' => $requestedLang ?? 'unknown',
        'ip' => $clientIP
    ]);

    // Return generic error to client
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to retrieve menu data. Please try again later.',
        'error_code' => 'INTERNAL_ERROR',
        'timestamp' => date('c'),
        'support' => 'If this problem persists, please contact support.'
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    // Clean output buffer
    ob_clean();

    // Log database error
    logError("API database error", [
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
        'ip' => $clientIP
    ]);

    // Return database error to client
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => 'Database service temporarily unavailable',
        'error_code' => 'DATABASE_ERROR',
        'timestamp' => date('c'),
        'retry_after' => 60
    ], JSON_UNESCAPED_UNICODE);
}

// Helper function for price formatting with validation
function formatPrice($price)
{
    if (empty($price)) {
        return 'Price not available';
    }

    // Remove any existing currency symbols and clean the price
    $cleanPrice = preg_replace('/[^\d.,]/', '', $price);
    $numPrice = floatval(str_replace(',', '', $cleanPrice));

    // Validate price range
    if ($numPrice < 0 || $numPrice > 999999.99) {
        return 'Price not available';
    }

    // Format based on value
    if ($numPrice == 0) {
        return 'Free';
    }

    return 'IQD ' . number_format($numPrice, 2);
}

// Clean up cache files older than 1 hour
function cleanupCache()
{
    $cacheDir = LOG_PATH;
    $files = glob($cacheDir . 'cache_*.json');
    $expiry = 3600; // 1 hour

    foreach ($files as $file) {
        if (file_exists($file) && (time() - filemtime($file)) > $expiry) {
            unlink($file);
        }
    }
}

// Cleanup old cache files (run occasionally)
if (random_int(1, 100) <= 5) { // 5% chance
    cleanupCache();
}

ob_end_flush();
