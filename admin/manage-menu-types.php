<?php
// Enhanced manage-menu-types.php with comprehensive security measures
require_once 'config.php';
require 'db.php';
require 'auth.php';
require 'translations.php';
require 'functions.php';
// Require menu type management permission
requirePermission('manage_menu_types');

$translation = new Translation($pdo);
$languages = $translation->getLanguages();

// Rate limiting for menu type management operations
$rateLimitKey = 'menu_types_' . getCurrentAdmin()['id'];
if (!checkRateLimit($rateLimitKey, 20, 600)) { // 20 operations per 10 minutes
    $error = 'Too many menu type operations. Please wait before trying again.';
    logError("Menu type management rate limit exceeded", [
        'user' => getCurrentAdmin()['username'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
}

// Handle form submissions with enhanced security
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error)) {
    try {
        // Validate CSRF token
        validateFormCSRF();

        if (isset($_POST['add_menu_type'])) {
            // Database transaction for data integrity
            $pdo->beginTransaction();

            try {
                // Validate that at least English name is provided
                $englishName = validateInput('string', $_POST['menu_type_name_en'] ?? '', ['max_length' => 255]);
                if (empty($englishName)) {
                    throw new Exception("English menu type name is required!");
                }

                // Check for duplicate menu type names
                $duplicateCheck = $pdo->prepare("
                    SELECT mt.id FROM menu_types mt 
                    JOIN menu_type_translations mtt ON mt.id = mtt.menu_type_id 
                    WHERE mtt.language_code = 'en' AND mtt.name = ?
                ");
                $duplicateCheck->execute([$englishName]);
                if ($duplicateCheck->fetch()) {
                    throw new Exception("A menu type with this name already exists");
                }

                // Insert menu type first
                $stmt = $pdo->prepare("INSERT INTO menu_types (created_at) VALUES (NOW())");
                $stmt->execute();
                $menuTypeId = $pdo->lastInsertId();

                // Insert translations with validation
                $translationsAdded = 0;
                $translationData = [];

                foreach ($languages as $lang) {
                    $nameKey = 'menu_type_name_' . $lang['code'];
                    $descKey = 'menu_type_description_' . $lang['code'];

                    if (!empty($_POST[$nameKey])) {
                        $name = validateInput('string', $_POST[$nameKey], ['max_length' => 255]);
                        $description = validateInput('string', $_POST[$descKey] ?? '', ['max_length' => 1000]);

                        if (!empty($name)) {
                            $translation->saveMenuTypeTranslation($menuTypeId, $lang['code'], $name, $description);
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
                auditLog('menu_type_created', [
                    'menu_type_id' => $menuTypeId,
                    'translations_added' => $translationsAdded,
                    'translations' => $translationData
                ]);

                $success = "Menu type added successfully with {$translationsAdded} translations!";
            } catch (Exception $e) {
                $pdo->rollback();
                // Clean up if menu type was created but translation failed
                if (isset($menuTypeId)) {
                    $pdo->prepare("DELETE FROM menu_types WHERE id = ?")->execute([$menuTypeId]);
                }
                throw $e;
            }
        }

        if (isset($_POST['delete_menu_type'])) {
            $menuTypeId = validateInput('int', $_POST['menu_type_id'] ?? '', ['min' => 1]);

            // Security check: verify menu type exists
            $menuTypeCheck = $pdo->prepare("SELECT id FROM menu_types WHERE id = ?");
            $menuTypeCheck->execute([$menuTypeId]);
            if (!$menuTypeCheck->fetch()) {
                throw new Exception('Menu type not found');
            }

            $pdo->beginTransaction();

            try {
                // Get menu type name and counts for confirmation
                $menuTypes = $translation->getMenuTypesWithTranslations();
                $menuTypeName = 'Unknown Menu Type';
                foreach ($menuTypes as $mt) {
                    if ($mt['id'] == $menuTypeId) {
                        $menuTypeName = $mt['name'];
                        break;
                    }
                }

                // Count categories and items that will be deleted
                $categoryStmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE menu_type_id = ?");
                $categoryStmt->execute([$menuTypeId]);
                $categoryCount = $categoryStmt->fetchColumn();

                $itemStmt = $pdo->prepare("SELECT COUNT(*) FROM menu_items m JOIN categories c ON m.category_id = c.id WHERE c.menu_type_id = ?");
                $itemStmt->execute([$menuTypeId]);
                $itemCount = $itemStmt->fetchColumn();

                // Get and delete all images in this menu type
                $imageStmt = $pdo->prepare("SELECT m.image FROM menu_items m JOIN categories c ON m.category_id = c.id WHERE c.menu_type_id = ? AND m.image != ''");
                $imageStmt->execute([$menuTypeId]);
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

                // Delete in proper order to respect foreign key constraints
                // 1. Delete menu item translations
                $pdo->prepare("
                    DELETE mit FROM menu_item_translations mit 
                    JOIN menu_items m ON mit.menu_item_id = m.id 
                    JOIN categories c ON m.category_id = c.id 
                    WHERE c.menu_type_id = ?
                ")->execute([$menuTypeId]);

                // 2. Delete menu items
                $pdo->prepare("
                    DELETE m FROM menu_items m 
                    JOIN categories c ON m.category_id = c.id 
                    WHERE c.menu_type_id = ?
                ")->execute([$menuTypeId]);

                // 3. Delete category translations
                $pdo->prepare("
                    DELETE ct FROM category_translations ct 
                    JOIN categories c ON ct.category_id = c.id 
                    WHERE c.menu_type_id = ?
                ")->execute([$menuTypeId]);

                // 4. Delete categories
                $pdo->prepare("DELETE FROM categories WHERE menu_type_id = ?")->execute([$menuTypeId]);

                // 5. Delete menu type translations
                $pdo->prepare("DELETE FROM menu_type_translations WHERE menu_type_id = ?")->execute([$menuTypeId]);

                // 6. Delete menu type
                $pdo->prepare("DELETE FROM menu_types WHERE id = ?")->execute([$menuTypeId]);

                $pdo->commit();

                // Audit log
                auditLog('menu_type_deleted', [
                    'menu_type_id' => $menuTypeId,
                    'menu_type_name' => $menuTypeName,
                    'categories_deleted' => $categoryCount,
                    'items_deleted' => $itemCount,
                    'images_deleted' => $deletedImages
                ]);

                $success = "Menu type '{$menuTypeName}' and all its {$categoryCount} categories with {$itemCount} items deleted successfully!";
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
        }

        if (isset($_POST['update_menu_type'])) {
            $menuTypeId = validateInput('int', $_POST['menu_type_id'] ?? '', ['min' => 1]);

            // Verify menu type exists
            $menuTypeCheck = $pdo->prepare("SELECT id FROM menu_types WHERE id = ?");
            $menuTypeCheck->execute([$menuTypeId]);
            if (!$menuTypeCheck->fetch()) {
                throw new Exception('Menu type not found');
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
                            $translation->saveMenuTypeTranslation($menuTypeId, $lang['code'], $name, $description);
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
                auditLog('menu_type_updated', [
                    'menu_type_id' => $menuTypeId,
                    'translations_updated' => $updatedTranslations,
                    'update_data' => $updateData
                ]);

                $success = "Menu type updated successfully!";
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        logError("Menu type management operation failed", [
            'action' => array_keys($_POST)[0] ?? 'unknown',
            'error' => $e->getMessage(),
            'user' => getCurrentAdmin()['username']
        ]);
    }
}

// Get all menu types with their category and item counts
try {
    $stmt = $pdo->query("
        SELECT 
            mt.id,
            mt.created_at,
            COUNT(DISTINCT c.id) as category_count,
            COUNT(DISTINCT m.id) as item_count
        FROM menu_types mt
        LEFT JOIN categories c ON mt.id = c.menu_type_id
        LEFT JOIN menu_items m ON c.id = m.category_id
        GROUP BY mt.id, mt.created_at
        ORDER BY mt.id DESC
    ");
    $menuTypesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError("Error fetching menu types data", ['error' => $e->getMessage()]);
    $menuTypesData = [];
}

// Generate CSRF token for forms
$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Menu Types - Restaurant Admin</title>
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

        .menu-type-translations {
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

        .menu-type-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #464747;
            margin-bottom: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s ease;
            border: 1px solid rgba(248, 248, 248, 0.1);
        }

        .menu-type-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .menu-type-info {
            flex: 1;
        }

        .menu-type-name {
            font-weight: 600;
            font-size: 18px;
            color: #f8f8f8;
            margin-bottom: 5px;
        }

        .menu-type-stats {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-top: 10px;
        }

        .stat-badge {
            background: #000000;
            color: #f8f8f8;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #f8f8f8;
        }

        .menu-type-actions {
            display: flex;
            gap: 10px;
            align-items: center;
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

        .info-box {
            background: #464747;
            border: 1px solid rgba(248, 248, 248, 0.1);
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }

        .info-box h4 {
            color: #f8f8f8;
            margin-bottom: 10px;
        }

        .info-box ul {
            color: #ccc;
            margin-left: 20px;
        }

        .info-box li {
            margin-bottom: 5px;
        }
    </style>
</head>

<body>
    <h1>🍴 Manage Menu Types</h1>

    <div class="container">
        <!-- Admin Session Info -->
        <div style="background: #1a1a1a; padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #333; color: #ccc;">
            Logged in as: <strong><?= htmlspecialchars(getCurrentAdmin()['username'], ENT_QUOTES, 'UTF-8') ?></strong>
        </div>

        <!-- Security Notice -->
        <div class="security-notice">
            <strong>🔒 Security Notice:</strong> All menu type operations are logged and monitored.
            Rate limiting is enforced to prevent abuse.
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Menu Types Management</h2>
            <a href="dashboard.php" class="btn">← Back to Dashboard</a>
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

        <!-- Add New Menu Type Form -->
        <div class="add-category-form">
            <h3 style="margin-bottom: 20px;">➕ Add New Menu Type</h3>
            <form method="post" id="addMenuTypeForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="translation-section">
                    <?php foreach ($languages as $lang): ?>
                        <div class="lang-card">
                            <h5 class="<?= $lang['is_default'] ? 'required-lang' : '' ?>">
                                <span class="lang-flag flag-<?= $lang['code'] ?>"></span>
                                <?= htmlspecialchars($lang['native_name'], ENT_QUOTES, 'UTF-8') ?>
                            </h5>

                            <label>
                                Menu Type Name
                                <input type="text"
                                    name="menu_type_name_<?= $lang['code'] ?>"
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
                                    name="menu_type_description_<?= $lang['code'] ?>"
                                    placeholder="Brief description"
                                    maxlength="1000"
                                    <?= $lang['direction'] === 'rtl' ? 'class="rtl-input"' : '' ?>
                                    oninput="updateCharCount(this, 1000)">
                                <div class="input-counter" id="desc_<?= $lang['code'] ?>_counter">0/1000</div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" name="add_menu_type" class="btn add-btn" id="submitBtn">➕ Add Menu Type</button>
            </form>
        </div>

        <!-- Existing Menu Types -->
        <div>
            <h3 style="margin-bottom: 20px;">
                Existing Menu Types
                <span style="color: #666; font-size: 14px; font-weight: normal;">(<?= count($menuTypesData) ?> total)</span>
            </h3>

            <?php if (empty($menuTypesData)): ?>
                <div style="text-align: center; padding: 40px; color: #666; background: #464747; border-radius: 10px; border: 1px solid rgba(248, 248, 248, 0.1);">
                    <h3>No menu types yet</h3>
                    <p>Add your first menu type above to get started!</p>
                </div>
            <?php else: ?>
                <?php foreach ($menuTypesData as $menuTypeData): ?>
                    <?php
                    // Get translations for this menu type
                    $menuTypeTranslations = $translation->getMenuTypeTranslations($menuTypeData['id']);
                    $defaultTranslation = array_filter($menuTypeTranslations, function ($t) {
                        return $t['language_code'] === 'en';
                    });
                    $defaultTranslation = reset($defaultTranslation);
                    $displayName = $defaultTranslation ? $defaultTranslation['name'] : 'Unnamed Menu Type';
                    ?>

                    <div class="menu-type-item">
                        <div class="menu-type-info">
                            <div class="menu-type-name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></div>

                            <!-- Show translations -->
                            <div class="menu-type-translations">
                                <strong style="color: #f8f8f8; margin-bottom: 10px; display: block;">Translations:</strong>
                                <?php foreach ($menuTypeTranslations as $trans): ?>
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

                            <div class="menu-type-stats">
                                <span class="stat-badge">
                                    <?= $menuTypeData['category_count'] ?> categories
                                </span>
                                <span class="stat-badge">
                                    <?= $menuTypeData['item_count'] ?> items
                                </span>
                                <span style="color: #666; font-size: 12px;">
                                    Created: <?= date('M j, Y', strtotime($menuTypeData['created_at'])) ?>
                                </span>
                            </div>

                            <!-- Edit Form (hidden by default) -->
                            <div class="edit-translations" id="edit-form-<?= $menuTypeData['id'] ?>">
                                <form method="post" style="display: contents;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="menu_type_id" value="<?= $menuTypeData['id'] ?>">

                                    <?php foreach ($languages as $lang): ?>
                                        <?php
                                        $existingTrans = array_filter($menuTypeTranslations, function ($t) use ($lang) {
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
                                        <button type="submit" name="update_menu_type" class="btn">💾 Save Changes</button>
                                        <button type="button" onclick="toggleEdit(<?= $menuTypeData['id'] ?>)" class="btn" style="background: #000000;">❌ Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="menu-type-actions">
                            <a href="manage-categories.php?menu_type=<?= $menuTypeData['id'] ?>" class="btn">
                                📂 Manage Categories
                            </a>

                            <button onclick="toggleEdit(<?= $menuTypeData['id'] ?>)" class="edit-btn">
                                ✏️ Edit Translations
                            </button>

                            <form method="post" style="margin: 0;" onsubmit="return confirmDelete('<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>', <?= $menuTypeData['category_count'] ?>, <?= $menuTypeData['item_count'] ?>)">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="menu_type_id" value="<?= $menuTypeData['id'] ?>">
                                <button type="submit" name="delete_menu_type" class="delete-btn">
                                    🗑️ Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Information Box -->
        <div class="info-box">
            <h4>📋 Menu Types Structure:</h4>
            <ul>
                <li><strong>Indoor Menu</strong> - For dine-in service items</li>
                <li><strong>Delivery Menu</strong> - For takeaway/delivery items</li>
                <li><strong>Hierarchy</strong> - Menu Type → Categories → Menu Items</li>
                <li><strong>Translations</strong> - Each menu type supports multiple languages</li>
                <li><strong>Security</strong> - All operations are logged and rate-limited</li>
            </ul>
        </div>
    </div>

    <script>
        function toggleEdit(menuTypeId) {
            const editForm = document.getElementById('edit-form-' + menuTypeId);
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
            const counter = document.getElementById(input.name.replace('menu_type_', '').replace('_name', '_counter').replace('_description', '_counter'));
            if (counter) {
                const length = input.value.length;
                counter.textContent = `${length}/${maxLength}`;
                counter.style.color = length > maxLength * 0.9 ? '#ff6b6b' : '#999';
            }
        }

        function validateForm() {
            const englishName = document.querySelector('input[name="menu_type_name_en"]');
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

        function confirmDelete(menuTypeName, categoryCount, itemCount) {
            const message = `⚠️ This will permanently delete the menu type '${menuTypeName}' and ALL ${categoryCount} categories with ${itemCount} items!\n\nThis action cannot be undone and will remove all associated data including images.\n\nAre you absolutely sure?`;
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
        document.getElementById('addMenuTypeForm').addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                alert('Please fill in the required English menu type name.');
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

        // Security: Clear sensitive form data on page unload
        window.addEventListener('beforeunload', function() {
            document.querySelectorAll('input[type="text"]').forEach(input => {
                if (input.name.includes('name') || input.name.includes('description')) {
                    // Don't actually clear for better UX, but this is where you'd add cleanup
                }
            });
        });
    </script>

</body>

</html>