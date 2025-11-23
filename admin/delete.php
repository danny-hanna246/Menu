<?php
// Enhanced delete.php with comprehensive security measures - FIXED VERSION

require 'db.php';
require 'auth.php';
require 'translations.php';
require 'functions.php';
require_once 'config.php';

// Require deletion permission
requirePermission('delete_items');

// Rate limiting for delete operations
$rateLimitKey = 'delete_' . getCurrentAdmin()['id'];
if (!checkRateLimit($rateLimitKey, 10, 600)) {
    logError("Delete rate limit exceeded", [
        'user' => getCurrentAdmin()['username'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    header('Location: dashboard.php?error=' . urlencode('Too many delete operations. Please wait before trying again.'));
    exit;
}

// Validate and get item ID
$id = null;
if (isset($_GET['id'])) {
    try {
        $id = validateInput('int', $_GET['id'], ['min' => 1]);
    } catch (Exception $e) {
        logError("Invalid item ID in delete request", [
            'id' => $_GET['id'] ?? 'missing',
            'user' => getCurrentAdmin()['username']
        ]);
        header('Location: dashboard.php?error=' . urlencode('Invalid item ID'));
        exit;
    }
}

if (!$id) {
    logError("Missing item ID in delete request", [
        'user' => getCurrentAdmin()['username']
    ]);
    header('Location: dashboard.php?error=' . urlencode('Item ID required'));
    exit;
}

// Initialize translation helper
$translation = new Translation($pdo);

try {
    // Start transaction for data integrity
    $pdo->beginTransaction();

    // ✅ FIXED: Get item details WITHOUT c.name
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            c.menu_type_id
        FROM menu_items m
        LEFT JOIN categories c ON m.category_id = c.id
        WHERE m.id = ?
    ");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ✅ FIXED: Fetch category name from translations
    if ($item) {
        $allCategories = $translation->getCategoriesWithTranslations('en', $item['menu_type_id']);
        $item['category_name'] = 'Unknown Category';
        foreach ($allCategories as $cat) {
            if ($cat['id'] == $item['category_id']) {
                $item['category_name'] = $cat['name'];
                break;
            }
        }
    }
    
    if (!$item) {
        $pdo->rollback();
        logError("Attempt to delete non-existent item", [
            'id' => $id,
            'user' => getCurrentAdmin()['username']
        ]);
        header('Location: dashboard.php?error=' . urlencode('Item not found'));
        exit;
    }

    // Get all translations for audit log
    $translations = $translation->getMenuItemTranslations($id);
    $itemNames = [];
    foreach ($translations as $trans) {
        $itemNames[$trans['language_code']] = $trans['name'];
    }

    // Get the primary name for display
    $primaryName = $itemNames['en'] ?? $itemNames[array_key_first($itemNames)] ?? 'Unknown Item';

    // Delete image file if exists
    $imageDeleted = false;
    if (!empty($item['image'])) {
        $imagePath = UPLOAD_PATH . $item['image'];
        if (file_exists($imagePath)) {
            if (unlink($imagePath)) {
                $imageDeleted = true;
            } else {
                logError("Failed to delete image file", [
                    'item_id' => $id,
                    'image_path' => $item['image']
                ]);
            }
        }
    }

    // Delete translations first (foreign key constraint)
    $deleteTranslationsStmt = $pdo->prepare("DELETE FROM menu_item_translations WHERE menu_item_id = ?");
    $deleteTranslationsStmt->execute([$id]);
    $deletedTranslations = $deleteTranslationsStmt->rowCount();

    // Delete the menu item
    $deleteItemStmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
    $deleteItemStmt->execute([$id]);
    $deletedItems = $deleteItemStmt->rowCount();

    if ($deletedItems === 0) {
        $pdo->rollback();
        logError("Failed to delete menu item from database", [
            'item_id' => $id,
            'user' => getCurrentAdmin()['username']
        ]);
        header('Location: dashboard.php?error=' . urlencode('Failed to delete item'));
        exit;
    }

    // Commit transaction
    $pdo->commit();

    // Comprehensive audit log
    auditLog('menu_item_deleted', [
        'item_id' => $id,
        'item_name' => $primaryName,
        'item_names_all_languages' => $itemNames,
        'category_id' => $item['category_id'],
        'category_name' => $item['category_name'],
        'menu_type_id' => $item['menu_type_id'],
        'price' => $item['price'],
        'image_path' => $item['image'],
        'image_deleted' => $imageDeleted,
        'translations_deleted' => $deletedTranslations,
        'created_at' => $item['created_at']
    ]);

    // Determine redirect URL - back to the same menu type if applicable
    $redirectUrl = $item['menu_type_id']
        ? "dashboard.php?menu_type=" . $item['menu_type_id']
        : "dashboard.php";

    $successMessage = "Item '{$primaryName}' deleted successfully" .
        ($imageDeleted ? ' (including image)' : '') .
        " with {$deletedTranslations} translations.";

    header('Location: ' . $redirectUrl . '?success=' . urlencode($successMessage));
    exit;

} catch (PDOException $e) {
    $pdo->rollback();

    logError("Database error during delete operation", [
        'item_id' => $id,
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
        'user' => getCurrentAdmin()['username']
    ]);

    header('Location: dashboard.php?error=' . urlencode('Database error occurred during deletion'));
    exit;
    
} catch (Exception $e) {
    $pdo->rollback();

    logError("General error during delete operation", [
        'item_id' => $id,
        'error' => $e->getMessage(),
        'user' => getCurrentAdmin()['username']
    ]);

    header('Location: dashboard.php?error=' . urlencode('An error occurred during deletion'));
    exit;
}
?>
