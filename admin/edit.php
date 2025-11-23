<?php
// Enhanced edit.php with comprehensive security measures - FIXED VERSION
require 'db.php';
require 'auth.php';
require 'translations.php';
require 'functions.php';
require_once 'config.php';

$translation = new Translation($pdo);
$languages = $translation->getLanguages();
$menuTypes = $translation->getMenuTypesWithTranslations();

// Validate and get item ID
$id = null;
if (isset($_GET['id'])) {
    try {
        $id = validateInput('int', $_GET['id'], ['min' => 1]);
    } catch (Exception $e) {
        logError("Invalid item ID in edit request", ['id' => $_GET['id'], 'user' => getCurrentAdmin()['username']]);
        header('Location: dashboard.php');
        exit;
    }
}

if (!$id) {
    header('Location: dashboard.php');
    exit;
}

// Fetch current data with enhanced security
// ✅ FIXED: Removed c.name from SELECT - using category_translations instead
try {
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

    if (!$item) {
        logError("Attempt to edit non-existent item", ['id' => $id, 'user' => getCurrentAdmin()['username']]);
        header('Location: dashboard.php?error=' . urlencode('Item not found'));
        exit;
    }
    
    // ✅ FIXED: Get category name from translations
    $existingCategories = $translation->getCategoriesWithTranslations('en', $item['menu_type_id']);
    $item['category_name'] = 'Unknown Category';
    foreach ($existingCategories as $cat) {
        if ($cat['id'] == $item['category_id']) {
            $item['category_name'] = $cat['name'];
            break;
        }
    }
} catch (PDOException $e) {
    logError("Database error fetching item for edit", ['id' => $id, 'error' => $e->getMessage()]);
    header('Location: dashboard.php?error=' . urlencode('Unable to load item'));
    exit;
}

// Get existing translations for this item
$existingTranslations = $translation->getMenuItemTranslations($id);
$translationsByLang = [];
foreach ($existingTranslations as $trans) {
    $translationsByLang[$trans['language_code']] = $trans;
}

// Get menu type name for display
$currentMenuTypeName = '';
foreach ($menuTypes as $mt) {
    if ($mt['id'] == $item['menu_type_id']) {
        $currentMenuTypeName = $mt['name'];
        break;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        validateFormCSRF();

        // Validate and sanitize inputs
        $categoryId = validateInput('int', $_POST['category_id'] ?? '', ['min' => 1]);
        $price = validateInput('float', $_POST['price'] ?? '', ['min' => 0]);

        // Image upload handling
        $newImage = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imageUpload = validateImageUpload($_FILES['image']);
            if ($imageUpload['success']) {
                $newImage = $imageUpload['filename'];
                
                // Delete old image if exists
                if (!empty($item['image'])) {
                    $oldImagePath = UPLOAD_PATH . $item['image'];
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
            }
        }

        // Update menu item
        $pdo->beginTransaction();

        if ($newImage) {
            $updateStmt = $pdo->prepare("
                UPDATE menu_items 
                SET category_id = ?, price = ?, image = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$categoryId, $price, $newImage, $id]);
        } else {
            $updateStmt = $pdo->prepare("
                UPDATE menu_items 
                SET category_id = ?, price = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$categoryId, $price, $id]);
        }

        // Update translations
        $translationsSaved = 0;
        foreach ($languages as $lang) {
            $langCode = $lang['code'];
            $name = $_POST["name_{$langCode}"] ?? '';
            $description = $_POST["description_{$langCode}"] ?? '';

            if (!empty($name)) {
                $translation->saveMenuItemTranslation($id, $langCode, $name, $description);
                $translationsSaved++;
            }
        }

        $pdo->commit();

        // Log the update
        auditLog('menu_item_updated', [
            'item_id' => $id,
            'category_id' => $categoryId,
            'price' => $price,
            'image_updated' => !is_null($newImage),
            'translations_saved' => $translationsSaved
        ]);

        // Redirect back to dashboard with menu type filter
        $redirectUrl = "dashboard.php?menu_type=" . $item['menu_type_id'] . 
                      "&success=" . urlencode("Item updated successfully with {$translationsSaved} translations!");
        header('Location: ' . $redirectUrl);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        $error = $e->getMessage();
        logError("Error updating menu item", ['id' => $id, 'error' => $error]);
    }
}

// Generate CSRF token
$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Menu Item - Living Room Restaurant</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-section {
            background: #000000;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid rgba(248, 248, 248, 0.1);
        }

        .form-section h3 {
            color: #ef4444;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #f8f8f8;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group label .required {
            color: #ef4444;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="file"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            color: #f8f8f8;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ef4444;
            background: #000;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: #1a1a1a;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .checkbox-group:hover {
            background: #222;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            flex: 1;
        }

        .btn-submit {
            background: #ef4444;
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }

        .btn-submit:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }

        .current-image {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #1a1a1a;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .current-image img {
            max-width: 150px;
            max-height: 150px;
            border-radius: 8px;
            border: 2px solid #333;
        }

        .current-image .image-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .image-preview {
            margin-top: 10px;
            display: none;
        }

        .image-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 2px solid #333;
        }

        .language-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .language-tab {
            padding: 10px 20px;
            background: #1a1a1a;
            border: 2px solid #333;
            border-radius: 8px;
            color: #f8f8f8;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .language-tab:hover {
            background: #222;
        }

        .language-tab.active {
            background: #ef4444;
            border-color: #ef4444;
        }

        .language-content {
            display: none;
        }

        .language-content.active {
            display: block;
        }

        .helper-text {
            color: #999;
            font-size: 12px;
            margin-top: 5px;
        }

        .btn-delete-image {
            background: #ef4444;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
        }

        .btn-delete-image:hover {
            background: #dc2626;
        }
    </style>
</head>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Menu Item - Restaurant Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="admin-container">
        <h1>✏️ Edit Menu Item</h1>
        
        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        
        <div class="form-section">
            <form method="POST" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                
                <!-- Menu Type (Read-only) -->
                <div class="form-group">
                    <label>Menu Type</label>
                    <input type="text" value="<?= htmlspecialchars($currentMenuTypeName) ?>" readonly class="readonly-input">
                </div>

                <!-- Category (Read-only for now, or make it editable with menu type check) -->
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" value="<?= htmlspecialchars($item['category_name']) ?>" readonly class="readonly-input">
                    <input type="hidden" name="category_id" value="<?= $item['category_id'] ?>">
                </div>

                <!-- Price -->
                <div class="form-group">
                    <label>Price (IQD) *</label>
                    <input type="number" name="price" step="0.01" min="0" 
                           value="<?= htmlspecialchars($item['price']) ?>" required>
                </div>

                <!-- Image -->
                <div class="form-group">
                    <label>Item Image</label>
                    <?php if ($item['image']): ?>
                        <div class="current-image">
                            <img src="../uploads/<?= htmlspecialchars($item['image']) ?>" 
                                 alt="Current image" style="max-width: 200px; border-radius: 8px;">
                            <p style="margin-top: 5px; color: #999;">Current image</p>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" accept="image/*">
                    <small>Leave empty to keep current image</small>
                </div>

                <!-- Translations -->
                <h3 style="margin-top: 30px;">Item Translations</h3>
                <?php foreach ($languages as $lang): ?>
                    <div class="translation-card">
                        <h4><?= htmlspecialchars($lang['name']) ?> (<?= htmlspecialchars($lang['code']) ?>)</h4>
                        
                        <div class="form-group">
                            <label>Name in <?= htmlspecialchars($lang['name']) ?> *</label>
                            <input type="text" 
                                   name="name_<?= $lang['code'] ?>" 
                                   value="<?= htmlspecialchars($translationsByLang[$lang['code']]['name'] ?? '') ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label>Description in <?= htmlspecialchars($lang['name']) ?></label>
                            <textarea name="description_<?= $lang['code'] ?>" 
                                      rows="3"><?= htmlspecialchars($translationsByLang[$lang['code']]['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Submit Buttons -->
                <div class="form-actions">
                    <button type="submit" class="btn-submit">💾 Save Changes</button>
                    <a href="dashboard.php?menu_type=<?= $item['menu_type_id'] ?>" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>




