<?php
// Enhanced manage-categories.php with comprehensive security measures
require_once 'config.php';
require 'db.php';
require 'auth.php';
require 'translations.php';
require 'functions.php';

// Require category management permission
requirePermission('manage_categories');

$translation = new Translation($pdo);
$languages = $translation->getLanguages();
$menuTypes = $translation->getMenuTypesWithTranslations();

// Rate limiting for category management operations
$rateLimitKey = 'categories_' . getCurrentAdmin()['id'];
if (!checkRateLimit($rateLimitKey, 30, 600)) { // 30 operations per 10 minutes
    $error = 'Too many category operations. Please wait before trying again.';
    logError("Category management rate limit exceeded", [
        'user' => getCurrentAdmin()['username'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
}

// Get and validate selected menu type from URL parameter
$selectedMenuType = null;
if (isset($_GET['menu_type'])) {
    try {
        $selectedMenuType = validateInput('int', $_GET['menu_type'], ['min' => 1]);
        // Verify menu type exists and user has access
        $menuTypeExists = false;
        foreach ($menuTypes as $mt) {
            if ($mt['id'] == $selectedMenuType) {
                $menuTypeExists = true;
                break;
            }
        }
        if (!$menuTypeExists) {
            $selectedMenuType = null;
            logError("Invalid menu type access attempt", [
                'menu_type_id' => $_GET['menu_type'],
                'user' => getCurrentAdmin()['username']
            ]);
        }
    } catch (Exception $e) {
        $selectedMenuType = null;
        logError("Invalid menu type parameter", [
            'parameter' => $_GET['menu_type'] ?? 'missing',
            'error' => $e->getMessage()
        ]);
    }
}

// Handle form submissions with enhanced security
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error)) {
    try {
        // Validate CSRF token
        validateFormCSRF();

        if (isset($_POST['add_category'])) {
            $menuTypeId = validateInput('int', $_POST['menu_type_id'] ?? '', ['min' => 1]);

            // Verify user has permission to add categories to this menu type
            $hasPermission = false;
            foreach ($menuTypes as $mt) {
                if ($mt['id'] == $menuTypeId) {
                    $hasPermission = true;
                    break;
                }
            }

            if (!$hasPermission) {
                throw new Exception('Invalid menu type or insufficient permissions');
            }

            // Database transaction for data integrity
            $pdo->beginTransaction();

            try {
                // Validate that at least English name is provided
                $englishName = validateInput('string', $_POST['category_name_en'] ?? '', ['max_length' => 255]);
                if (empty($englishName)) {
                    throw new Exception("English category name is required!");
                }

                // Check for duplicate category names within the same menu type
                $duplicateCheck = $pdo->prepare("
                    SELECT c.id FROM categories c 
                    JOIN category_translations ct ON c.id = ct.category_id 
                    WHERE c.menu_type_id = ? AND ct.language_code = 'en' AND ct.name = ?
                ");
                $duplicateCheck->execute([$menuTypeId, $englishName]);
                if ($duplicateCheck->fetch()) {
                    throw new Exception("A category with this name already exists in this menu type");
                }

                // Insert category first
                $stmt = $pdo->prepare("INSERT INTO categories (menu_type_id, created_at) VALUES (?, NOW())");
                $stmt->execute([$menuTypeId]);
                $categoryId = $pdo->lastInsertId();

                // Insert translations with validation
                $translationsAdded = 0;
                $translationData = [];

                foreach ($languages as $lang) {
                    $nameKey = 'category_name_' . $lang['code'];
                    $descKey = 'category_description_' . $lang['code'];

                    if (!empty($_POST[$nameKey])) {
                        $name = validateInput('string', $_POST[$nameKey], ['max_length' => 255]);
                        $description = validateInput('string', $_POST[$descKey] ?? '', ['max_length' => 1000]);

                        if (!empty($name)) {
                            $translation->saveCategoryTranslation($categoryId, $lang['code'], $name, $description);
                            $translationsAdded++;
                            $translationData[$lang['code']] = ['name' => $name, 'description' => $description];
                        }
                    }
                }

                if ($translationsAdded === 0) {
                    throw new Exception("At least one language translation is required!");
                }

                $pdo->commit();

                // Audit log
                auditLog('category_created', [
                    'category_id' => $categoryId,
                    'menu_type_id' => $menuTypeId,
                    'translations_added' => $translationsAdded,
                    'translations' => $translationData
                ]);

                $success = "Category added successfully with {$translationsAdded} translations!";
            } catch (Exception $e) {
                $pdo->rollback();
                // Clean up if category was created but translation failed
                if (isset($categoryId)) {
                    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$categoryId]);
                }
                throw $e;
            }
        }

        if (isset($_POST['delete_category'])) {
            $categoryId = validateInput('int', $_POST['category_id'] ?? '', ['min' => 1]);

            // Security check: verify category belongs to accessible menu type
            $categoryCheck = $pdo->prepare("
                SELECT c.*, COUNT(m.id) as item_count 
                FROM categories c 
                LEFT JOIN menu_items m ON c.id = m.category_id 
                WHERE c.id = ? 
                GROUP BY c.id
            ");
            $categoryCheck->execute([$categoryId]);
            $categoryData = $categoryCheck->fetch(PDO::FETCH_ASSOC);

            if (!$categoryData) {
                throw new Exception('Category not found');
            }

            // Additional permission check
            $hasPermission = false;
            foreach ($menuTypes as $mt) {
                if ($mt['id'] == $categoryData['menu_type_id']) {
                    $hasPermission = true;
                    break;
                }
            }

            if (!$hasPermission) {
                throw new Exception('Insufficient permissions to delete this category');
            }

            $pdo->beginTransaction();

            try {
                // Get category name for confirmation
                $categories = $translation->getCategoriesWithTranslations();
                $categoryName = 'Unknown Category';
                foreach ($categories as $cat) {
                    if ($cat['id'] == $categoryId) {
                        $categoryName = $cat['name'];
                        break;
                    }
                }

                // Get and delete all images in this category
                if ($categoryData['item_count'] > 0) {
                    $imageStmt = $pdo->prepare("SELECT image FROM menu_items WHERE category_id = ? AND image != ''");
                    $imageStmt->execute([$categoryId]);
                    $images = $imageStmt->fetchAll(PDO::FETCH_COLUMN);

                    $deletedImages = [];
                    foreach ($images as $image) {
                        $imagePath = '../uploads/' . $image;
                        if (file_exists($imagePath) && is_file($imagePath)) {
                            if (unlink($imagePath)) {
                                $deletedImages[] = $image;
                            }
                        }
                    }

                    // Delete menu items and their translations
                    $pdo->prepare("DELETE FROM menu_items WHERE category_id = ?")->execute([$categoryId]);
                }

                // Delete category translations and category
                $pdo->prepare("DELETE FROM category_translations WHERE category_id = ?")->execute([$categoryId]);
                $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$categoryId]);

                $pdo->commit();

                // Audit log
                auditLog('category_deleted', [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'menu_type_id' => $categoryData['menu_type_id'],
                    'items_deleted' => $categoryData['item_count'],
                    'images_deleted' => $deletedImages ?? []
                ]);

                $success = "Category '{$categoryName}' and all its {$categoryData['item_count']} items deleted successfully!";
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
        }

        if (isset($_POST['update_category'])) {
            $categoryId = validateInput('int', $_POST['category_id'] ?? '', ['min' => 1]);

            // Verify category exists and user has permission
            $categoryCheck = $pdo->prepare("SELECT menu_type_id FROM categories WHERE id = ?");
            $categoryCheck->execute([$categoryId]);
            $categoryData = $categoryCheck->fetch(PDO::FETCH_ASSOC);

            if (!$categoryData) {
                throw new Exception('Category not found');
            }

            $hasPermission = false;
            foreach ($menuTypes as $mt) {
                if ($mt['id'] == $categoryData['menu_type_id']) {
                    $hasPermission = true;
                    break;
                }
            }

            if (!$hasPermission) {
                throw new Exception('Insufficient permissions to update this category');
            }

            $pdo->beginTransaction();

            try {
                // Update translations for each language
                $updatedTranslations = 0;
                $updateData = [];

                foreach ($languages as $lang) {
                    $nameKey = 'new_name_' . $lang['code'];
                    $descKey = 'new_description_' . $lang['code'];

                    if (!empty($_POST[$nameKey])) {
                        $name = validateInput('string', $_POST[$nameKey], ['max_length' => 255]);
                        $description = validateInput('string', $_POST[$descKey] ?? '', ['max_length' => 1000]);

                        if (!empty($name)) {
                            $translation->saveCategoryTranslation($categoryId, $lang['code'], $name, $description);
                            $updatedTranslations++;
                            $updateData[$lang['code']] = ['name' => $name, 'description' => $description];
                        }
                    }
                }

                if ($updatedTranslations === 0) {
                    throw new Exception("At least one language translation is required");
                }

                $pdo->commit();

                // Audit log
                auditLog('category_updated', [
                    'category_id' => $categoryId,
                    'translations_updated' => $updatedTranslations,
                    'update_data' => $updateData
                ]);

                $success = "Category updated successfully!";
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        logError("Category management operation failed", [
            'action' => array_keys($_POST)[0] ?? 'unknown',
            'error' => $e->getMessage(),
            'user' => getCurrentAdmin()['username']
        ]);
    }
}

// Get categories data based on selected menu type
$categoriesData = [];
if ($selectedMenuType) {
    try {
        // Get all categories with their item counts for the selected menu type
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.created_at,
                c.menu_type_id,
                COUNT(m.id) as item_count
            FROM categories c
            LEFT JOIN menu_items m ON c.id = m.category_id
            WHERE c.menu_type_id = ?
            GROUP BY c.id, c.created_at, c.menu_type_id
            ORDER BY c.id DESC
        ");
        $stmt->execute([$selectedMenuType]);
        $categoriesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get selected menu type name
        $selectedMenuTypeName = '';
        foreach ($menuTypes as $mt) {
            if ($mt['id'] == $selectedMenuType) {
                $selectedMenuTypeName = $mt['name'];
                break;
            }
        }
    } catch (PDOException $e) {
        logError("Error fetching categories data", [
            'menu_type_id' => $selectedMenuType,
            'error' => $e->getMessage()
        ]);
        $categoriesData = [];
    }
}

// Generate CSRF token for forms
$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Restaurant Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .security-notice {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #ccc;
            font-size: 14px;
        }

        .menu-type-selector {
            background: #000000;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid rgba(248, 248, 248, 0.1);
            text-align: center;
        }

        .menu-type-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .menu-type-card {
            background: #464747;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid rgba(248, 248, 248, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #f8f8f8;
        }

        .menu-type-card:hover {
            border-color: #f8f8f8;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .menu-type-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .menu-type-description {
            color: #ccc;
            font-size: 14px;
        }

        .translation-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .lang-card {
            background: #000000;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid rgba(248, 248, 248, 0.1);
        }

        .lang-card h5 {
            color: #f8f8f8;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .lang-flag {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
        }

        .flag-en {
            background: linear-gradient(to bottom, #b22234 33%, #fff 33%, #fff 66%, #b22234 66%);
        }

        .flag-ar {
            background: linear-gradient(to bottom, #000 33%, #fff 33%, #fff 66%, #ce1126 66%);
        }

        .flag-ku {
            background: linear-gradient(to bottom, #ff0000 33%, #fff 33%, #fff 66%, #00ff00 66%);
        }

        .rtl-input {
            direction: rtl;
            text-align: right;
        }

        .category-translations {
            margin-top: 15px;
            padding: 15px;
            background: #000000;
            border-radius: 8px;
            border: 1px solid rgba(248, 248, 248, 0.1);
        }

        .translation-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(248, 248, 248, 0.1);
        }

        .translation-item:last-child {
            border-bottom: none;
        }

        .translation-lang {
            font-weight: 600;
            color: #f8f8f8;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .translation-text {
            color: #ccc;
            text-align: right;
            flex: 1;
            margin-left: 15px;
        }

        .edit-translations {
            display: none;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .edit-translations.active {
            display: grid;
        }

        .required-lang {
            position: relative;
        }

        .required-lang::after {
            content: " *";
            color: #ff6b6b;
            font-weight: bold;
        }

        .breadcrumb {
            background: #000000;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid rgba(248, 248, 248, 0.1);
            color: #ccc;
        }

        .breadcrumb a {
            color: #f8f8f8;
            text-decoration: underline;
        }

        .input-counter {
            font-size: 12px;
            color: #999;
            text-align: right;
            margin-top: 2px;
        }

        .validation-error {
            color: #ff6b6b;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }
    </style>
</head>

<body>
    <h1>📂 Manage Categories</h1>

    <div class="container">
        <!-- Admin Session Info -->
        <div style="background: #1a1a1a; padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #333; color: #ccc;">
            Logged in as: <strong><?= htmlspecialchars(getCurrentAdmin()['username'], ENT_QUOTES, 'UTF-8') ?></strong>
        </div>

        <?php if (!$selectedMenuType): ?>
            <!-- Step 1: Select Menu Type -->
            <div class="menu-type-selector">
                <h2 style="margin-bottom: 10px;">🍽️ Choose Menu Type</h2>
                <p>Select which menu type you want to manage categories for.</p>

                <div class="menu-type-cards">
                    <?php foreach ($menuTypes as $menuType): ?>
                        <a href="manage-categories.php?menu_type=<?= $menuType['id'] ?>" class="menu-type-card">
                            <div class="menu-type-title"><?= htmlspecialchars($menuType['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="menu-type-description"><?= htmlspecialchars($menuType['description'], ENT_QUOTES, 'UTF-8') ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 30px;">
                    <a href="dashboard.php" class="btn">← Back to Dashboard</a>
                    <a href="manage-menu-types.php" class="btn">🍴 Manage Menu Types</a>
                </div>
            </div>

        <?php else: ?>
            <!-- Step 2: Manage Categories for Selected Menu Type -->

            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> →
                <a href="manage-categories.php">Manage Categories</a> →
                <strong><?= htmlspecialchars($selectedMenuTypeName, ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <!-- Security Notice -->
            <div class="security-notice">
                <strong>🔒 Security Notice:</strong> All category operations are logged and monitored.
                Rate limiting is enforced to prevent abuse.
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">Categories for <?= htmlspecialchars($selectedMenuTypeName, ENT_QUOTES, 'UTF-8') ?></h2>
                <div>
                    <a href="manage-categories.php" class="btn">← Choose Different Menu Type</a>
                    <a href="dashboard.php?menu_type=<?= $selectedMenuType ?>" class="btn">← Back to <?= htmlspecialchars($selectedMenuTypeName, ENT_QUOTES, 'UTF-8') ?></a>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($success)): ?>
                <div class="success-message">
                    ✅ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="error-message">
                    ⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <!-- Add New Category Form -->
            <div class="add-category-form">
                <h3 style="margin-bottom: 20px;">➕ Add New Category to <?= htmlspecialchars($selectedMenuTypeName, ENT_QUOTES, 'UTF-8') ?></h3>
                <form method="post" id="addCategoryForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="menu_type_id" value="<?= $selectedMenuType ?>">

                    <div class="translation-section">
                        <?php foreach ($languages as $lang): ?>
                            <div class="lang-card">
                                <h5 class="<?= $lang['is_default'] ? 'required-lang' : '' ?>">
                                    <span class="lang-flag flag-<?= $lang['code'] ?>"></span>
                                    <?= htmlspecialchars($lang['native_name'], ENT_QUOTES, 'UTF-8') ?>
                                </h5>

                                <label>
                                    Category Name
                                    <input type="text"
                                        name="category_name_<?= $lang['code'] ?>"
                                        placeholder="Enter name in <?= htmlspecialchars($lang['native_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        maxlength="255"
                                        <?= $lang['direction'] === 'rtl' ? 'class="rtl-input"' : '' ?>
                                        <?= $lang['is_default'] ? 'required' : '' ?>
                                        oninput="updateCharCount(this, 255); validateForm()">
                                    <div class="input-counter" id="name_<?= $lang['code'] ?>_counter">0/255</div>
                                    <div class="validation-error" id="name_<?= $lang['code'] ?>_error">
                                        Name is required for <?= htmlspecialchars($lang['native_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </label>

                                <label>
                                    Description (Optional)
                                    <input type="text"
                                        name="category_description_<?= $lang['code'] ?>"
                                        placeholder="Brief description"
                                        maxlength="1000"
                                        <?= $lang['direction'] === 'rtl' ? 'class="rtl-input"' : '' ?>
                                        oninput="updateCharCount(this, 1000)">
                                    <div class="input-counter" id="desc_<?= $lang['code'] ?>_counter">0/1000</div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" name="add_category" class="btn add-btn" id="submitBtn">➕ Add Category</button>
                </form>
            </div>

            <!-- Existing Categories -->
            <div>
                <h3 style="margin-bottom: 20px;">
                    Existing Categories
                    <span style="color: #666; font-size: 14px; font-weight: normal;">(<?= count($categoriesData) ?> total)</span>
                </h3>

                <?php if (empty($categoriesData)): ?>
                    <div style="text-align: center; padding: 40px; color: #666; background: #464747; border-radius: 10px; border: 1px solid rgba(248, 248, 248, 0.1);">
                        <h3>No categories yet for <?= htmlspecialchars($selectedMenuTypeName, ENT_QUOTES, 'UTF-8') ?></h3>
                        <p>Add your first category above to get started!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($categoriesData as $categoryData): ?>
                        <?php
                        // Get translations for this category
                        $categoryTranslations = $translation->getCategoryTranslations($categoryData['id']);
                        $defaultTranslation = array_filter($categoryTranslations, function ($t) {
                            return $t['language_code'] === 'en';
                        });
                        $defaultTranslation = reset($defaultTranslation);
                        $displayName = $defaultTranslation ? $defaultTranslation['name'] : 'Unnamed Category';
                        ?>

                        <div class="category-item">
                            <div class="category-info">
                                <div class="category-name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></div>

                                <!-- Show translations -->
                                <div class="category-translations">
                                    <strong style="color: #f8f8f8; margin-bottom: 10px; display: block;">Translations:</strong>
                                    <?php foreach ($categoryTranslations as $trans): ?>
                                        <div class="translation-item">
                                            <div class="translation-lang">
                                                <span class="lang-flag flag-<?= $trans['language_code'] ?>"></span>
                                                <?= htmlspecialchars($trans['native_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <div class="translation-text" <?= $trans['language_code'] === 'ar' || $trans['language_code'] === 'ku' ? 'dir="rtl"' : '' ?>>
                                                <?= htmlspecialchars($trans['name'], ENT_QUOTES, 'UTF-8') ?>
                                                <?php if ($trans['description']): ?>
                                                    <br><small style="color: #999;"><?= htmlspecialchars($trans['description'], ENT_QUOTES, 'UTF-8') ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="category-meta">
                                    <span class="item-count <?= $categoryData['item_count'] == 0 ? 'empty-category' : '' ?>">
                                        <?= $categoryData['item_count'] ?> items
                                    </span>
                                    <span class="category-date">
                                        Created: <?= date('M j, Y', strtotime($categoryData['created_at'])) ?>
                                    </span>
                                </div>

                                <!-- Edit Form (hidden by default) -->
                                <div class="edit-translations" id="edit-form-<?= $categoryData['id'] ?>">
                                    <form method="post" style="display: contents;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="category_id" value="<?= $categoryData['id'] ?>">

                                        <?php foreach ($languages as $lang): ?>
                                            <?php
                                            $existingTrans = array_filter($categoryTranslations, function ($t) use ($lang) {
                                                return $t['language_code'] === $lang['code'];
                                            });
                                            $existingTrans = reset($existingTrans);
                                            ?>
                                            <div class="lang-card">
                                                <h5>
                                                    <span class="lang-flag flag-<?= $lang['code'] ?>"></span>
                                                    <?= htmlspecialchars($lang['native_name'], ENT_QUOTES, 'UTF-8') ?>
                                                </h5>
                                                <label>
                                                    Name
                                                    <input type="text"
                                                        name="new_name_<?= $lang['code'] ?>"
                                                        value="<?= $existingTrans ? htmlspecialchars($existingTrans['name'], ENT_QUOTES, 'UTF-8') : '' ?>"
                                                        maxlength="255"
                                                        <?= $lang['direction'] === 'rtl' ? 'class="rtl-input"' : '' ?>>
                                                </label>
                                                <label>
                                                    Description
                                                    <input type="text"
                                                        name="new_description_<?= $lang['code'] ?>"
                                                        value="<?= $existingTrans ? htmlspecialchars($existingTrans['description'], ENT_QUOTES, 'UTF-8') : '' ?>"
                                                        maxlength="1000"
                                                        <?= $lang['direction'] === 'rtl' ? 'class="rtl-input"' : '' ?>>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>

                                        <div style="grid-column: 1 / -1; display: flex; gap: 10px; justify-content: center; margin-top: 15px;">
                                            <button type="submit" name="update_category" class="btn">💾 Save Changes</button>
                                            <button type="button" onclick="toggleEdit(<?= $categoryData['id'] ?>)" class="btn" style="background: #000000;">❌ Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="category-actions">
                                <a href="add.php?menu_type=<?= $selectedMenuType ?>" class="btn" style="background: #1b2d1b; border-color: #4CAF50; color: #4CAF50;">
                                    ➕ Add Item to Category
                                </a>

                                <button onclick="toggleEdit(<?= $categoryData['id'] ?>)" class="edit-btn">
                                    ✏️ Edit Translations
                                </button>

                                <form method="post" style="margin: 0;" onsubmit="return confirmDelete('<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>', <?= $categoryData['item_count'] ?>)">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="category_id" value="<?= $categoryData['id'] ?>">
                                    <button type="submit" name="delete_category" class="delete-btn">
                                        🗑️ Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleEdit(categoryId) {
            const editForm = document.getElementById('edit-form-' + categoryId);
            const isActive = editForm.classList.contains('active');

            // Hide all edit forms
            document.querySelectorAll('.edit-translations').forEach(form => {
                form.classList.remove('active');
            });

            // Toggle current form
            if (!isActive) {
                editForm.classList.add('active');
            }
        }

        function updateCharCount(input, maxLength) {
            const counter = document.getElementById(input.name.replace('category_', '').replace('_name', '_counter').replace('_description', '_counter'));
            if (counter) {
                const length = input.value.length;
                counter.textContent = `${length}/${maxLength}`;
                counter.style.color = length > maxLength * 0.9 ? '#ff6b6b' : '#999';
            }
        }

        function validateForm() {
            const englishName = document.querySelector('input[name="category_name_en"]');
            const submitBtn = document.getElementById('submitBtn');
            const errorDiv = document.getElementById('name_en_error');

            if (!englishName.value.trim()) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                errorDiv.style.display = 'block';
                return false;
            } else {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                errorDiv.style.display = 'none';
                return true;
            }
        }

        function confirmDelete(categoryName, itemCount) {
            const message = `⚠️ This will permanently delete the category '${categoryName}' and ALL ${itemCount} items in it!\n\nThis action cannot be undone.\n\nAre you absolutely sure?`;
            return confirm(message);
        }

        // Initialize character counters
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input[maxlength]').forEach(input => {
                const maxLength = parseInt(input.getAttribute('maxlength'));
                updateCharCount(input, maxLength);

                input.addEventListener('input', function() {
                    updateCharCount(this, maxLength);
                });
            });

            // Initial form validation
            validateForm();
        });

        // Form validation on submit
        document.getElementById('addCategoryForm').addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                alert('Please fill in the required English category name.');
                return false;
            }
        });

        // Auto-hide success/error messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.success-message, .error-message');
            messages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);

        // Security: Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>

</body>

</html>