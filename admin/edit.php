<?php
// Enhanced edit.php with comprehensive security measures
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

// Get existing categories for the current menu type
$existingCategories = $translation->getCategoriesWithTranslations('en', $item['menu_type_id']);
// Find the current item's category name from the translated list
$item['category_name'] = 'Unknown Category'; // Set a default value
foreach ($existingCategories as $cat) {
    if ($cat['id'] == $item['category_id']) {
        $item['category_name'] = $cat['name'];
        break;
    }
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
        $price = validateInput('float', $_POST['price'] ?? '', ['min' => 0, 'max' => 999999.99]);

        // Validate category belongs to current menu type
        $validCategory = false;
        foreach ($existingCategories as $cat) {
            if ($cat['id'] == $categoryId) {
                $validCategory = true;
                break;
            }
        }

        if (!$validCategory) {
            throw new Exception('Invalid category selected');
        }

        // Handle image upload with enhanced security
        $imageName = $item['image']; // Keep existing image by default
        $imageChanged = false;

        if (!empty($_FILES['image']['name'])) {
            // Delete old image if it exists
            if ($item['image'] && file_exists(UPLOAD_PATH . $item['image'])) {
                unlink(UPLOAD_PATH . $item['image']);
            }

            $imageName = uploadImage($_FILES['image'], 'menu_');
            $imageChanged = true;
        }

        // Database transaction for data integrity
        $pdo->beginTransaction();

        try {
            // Update menu item
            $stmt = $pdo->prepare("UPDATE menu_items SET category_id = ?, price = ?, image = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$categoryId, $price, $imageName, $id]);

            // Track changes for audit
            $changes = [];
            if ($categoryId != $item['category_id']) $changes['category_id'] = ['from' => $item['category_id'], 'to' => $categoryId];
            if ($price != $item['price']) $changes['price'] = ['from' => $item['price'], 'to' => $price];
            if ($imageChanged) $changes['image'] = ['from' => $item['image'], 'to' => $imageName];

            // Update translations for each language
            $translationsUpdated = 0;
            $translationChanges = [];

            foreach ($languages as $lang) {
                $nameKey = 'name_' . $lang['code'];
                $descKey = 'description_' . $lang['code'];

                if (!empty($_POST[$nameKey])) {
                    $name = validateInput('string', $_POST[$nameKey], ['max_length' => 255]);
                    $description = validateInput('string', $_POST[$descKey] ?? '', ['max_length' => 1000]);

                    if (!empty($name)) {
                        $existingTrans = $translationsByLang[$lang['code']] ?? null;

                        // Track translation changes
                        if (!$existingTrans || $existingTrans['name'] !== $name || $existingTrans['description'] !== $description) {
                            $translationChanges[$lang['code']] = [
                                'name' => ['from' => $existingTrans['name'] ?? '', 'to' => $name],
                                'description' => ['from' => $existingTrans['description'] ?? '', 'to' => $description]
                            ];
                        }

                        $translation->saveMenuItemTranslation($id, $lang['code'], $name, $description);
                        $translationsUpdated++;
                    }
                }
            }

            // Ensure at least one translation exists
            if ($translationsUpdated === 0) {
                throw new Exception('At least one language translation is required');
            }

            $pdo->commit();

            // Audit log with detailed changes
            auditLog('menu_item_updated', [
                'menu_item_id' => $id,
                'basic_changes' => $changes,
                'translation_changes' => $translationChanges,
                'translations_updated' => $translationsUpdated
            ]);

            $success = "Menu item updated successfully with {$translationsUpdated} translations!";

            // Redirect back to the appropriate dashboard view
            $redirectUrl = $item['menu_type_id'] ? "dashboard.php?menu_type=" . $item['menu_type_id'] . "&success=" . urlencode($success) : "dashboard.php?success=" . urlencode($success);
            header('Location: ' . $redirectUrl);
            exit;
        } catch (Exception $e) {
            $pdo->rollback();
            // Clean up new uploaded image if database operation failed
            if ($imageChanged && !empty($imageName) && file_exists(UPLOAD_PATH . $imageName)) {
                unlink(UPLOAD_PATH . $imageName);
                // Restore old image if it was deleted
                // Note: This is a best-effort restoration; in production, consider backup strategies
            }
            throw $e;
        }
    } catch (Exception $e) {
        $error = "Error updating menu item: " . $e->getMessage();
        logError("Menu item update failed", [
            'error' => $e->getMessage(),
            'item_id' => $id,
            'user' => getCurrentAdmin()['username'],
            'post_data' => array_keys($_POST)
        ]);
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
    <title>Edit Menu Item - Restaurant Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .translation-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #464747;
        }

        .tab-btn {
            background: transparent;
            border: none;
            color: #ccc;
            padding: 12px 20px;
            cursor: pointer;
            font-weight: 600;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            color: #f8f8f8;
            border-bottom-color: #f8f8f8;
        }

        .tab-btn:hover {
            color: #f8f8f8;
        }

        .translation-content {
            display: none;
            background: #464747;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid rgba(248, 248, 248, 0.1);
        }

        .translation-content.active {
            display: block;
        }

        .translation-content h4 {
            color: #f8f8f8;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .rtl-content {
            direction: rtl;
            text-align: right;
        }

        .rtl-content input,
        .rtl-content textarea {
            direction: rtl;
            text-align: right;
        }

        .required-indicator {
            color: #ff6b6b;
            font-weight: bold;
        }

        .progress-indicator {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #000000;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid rgba(248, 248, 248, 0.1);
        }

        .progress-text {
            color: #f8f8f8;
            font-weight: 600;
        }

        .lang-indicator {
            display: flex;
            gap: 10px;
        }

        .lang-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #2a2a2a;
            transition: background 0.3s ease;
        }

        .lang-dot.completed {
            background: #4CAF50;
        }

        .lang-dot.current {
            background: #f8f8f8;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .current-image {
            margin-top: 10px;
            padding: 10px;
            background: #000000;
            border-radius: 8px;
            border: 1px solid rgba(248, 248, 248, 0.1);
        }

        .current-image img {
            border-radius: 5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .translation-status {
            margin-top: 10px;
            padding: 10px;
            background: #000000;
            border-radius: 5px;
            border: 1px solid rgba(248, 248, 248, 0.1);
        }

        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
            color: #ccc;
        }

        .status-complete {
            color: #4CAF50;
        }

        .status-missing {
            color: #ff6b6b;
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

        .menu-type-info {
            background: #1b2d1b;
            color: #4CAF50;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #4CAF50;
            text-align: center;
        }

        .security-notice {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #ccc;
            font-size: 14px;
        }

        .upload-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 5px;
            display: none;
        }

        .validation-error {
            color: #ff6b6b;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .input-counter {
            font-size: 12px;
            color: #999;
            text-align: right;
            margin-top: 2px;
        }

        .change-indicator {
            background: #2d2416;
            color: #ffc107;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            margin-left: 5px;
        }

        .last-updated {
            font-size: 11px;
            color: #666;
            font-style: italic;
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <h1>Edit Dish</h1>

    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <?php if ($item['menu_type_id']): ?>
                <a href="dashboard.php?menu_type=<?= $item['menu_type_id'] ?>"><?= htmlspecialchars($currentMenuTypeName, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif; ?>
            <strong>Edit Item</strong>
        </div>

        <!-- Menu Type Info -->
        <div class="menu-type-info">
            Editing item in: <strong><?= htmlspecialchars($currentMenuTypeName, ENT_QUOTES, 'UTF-8') ?></strong>
        </div>

        <!-- Security Notice -->
        <div class="security-notice">
            <strong>Security Notice:</strong> All changes are logged for audit purposes.
            File uploads are scanned and validated. Maximum file size: <?= MAX_FILE_SIZE / 1024 / 1024 ?>MB.
        </div>

        <div style="margin-bottom: 20px;">
            <h2 style="margin-bottom: 10px;">Edit Menu Item</h2>
            <p>Update translations and details for "<?= htmlspecialchars($translationsByLang['en']['name'] ?? 'Unknown Item', ENT_QUOTES, 'UTF-8') ?>"</p>
        </div>

        <?php if (isset($success)): ?>
            <div style="background: #1b2d1b; color: #4CAF50; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #4CAF50;">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div style="background: #2d1b1b; color: #ff6b6b; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ff6b6b;">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <!-- Translation Status Overview -->
        <div class="translation-status">
            <h4 style="color: #f8f8f8; margin-bottom: 10px;">Translation Status</h4>
            <?php foreach ($languages as $lang): ?>
                <?php $hasTranslation = isset($translationsByLang[$lang['code']]) && !empty($translationsByLang[$lang['code']]['name']); ?>
                <div class="status-item">
                    <span><?= htmlspecialchars($lang['native_name'], ENT_QUOTES, 'UTF-8') ?> (<?= strtoupper($lang['code']) ?>)</span>
                    <span class="<?= $hasTranslation ? 'status-complete' : 'status-missing' ?>">
                        <?= $hasTranslation ? 'Complete' : 'Missing' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="post" enctype="multipart/form-data" id="editItemForm" novalidate>
            <!-- CSRF Protection -->
            <?= renderCSRFField() ?>

            <!-- Progress Indicator -->
            <div class="progress-indicator">
                <div class="progress-text">Translation Progress</div>
                <div class="lang-indicator" id="langIndicator">
                    <?php foreach ($languages as $lang): ?>
                        <?php $hasTranslation = isset($translationsByLang[$lang['code']]) && !empty($translationsByLang[$lang['code']]['name']); ?>
                        <div class="lang-dot <?= $hasTranslation ? 'completed' : '' ?>"
                            data-lang="<?= $lang['code'] ?>"
                            title="<?= htmlspecialchars($lang['native_name'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Basic Details -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                <div>
                    <label>
                        Category <span class="required-indicator">*</span>
                        <select name="category_id" required>
                            <option value="">Select a category</option>
                            <?php foreach ($existingCategories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $item['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <small style="color: #ccc; margin-top: 5px; display: block;">
                        Current: <?= htmlspecialchars($item['category_name'] ?? 'No category', ENT_QUOTES, 'UTF-8') ?>
                        | <a href="manage-categories.php?menu_type=<?= $item['menu_type_id'] ?>" style="color: #f8f8f8;">Manage categories</a>
                    </small>
                </div>

                <div>
                    <label>
                        Price (IQD) <span class="required-indicator">*</span>
                        <input type="number"
                            name="price"
                            step="0.01"
                            min="0"
                            max="999999.99"
                            value="<?= htmlspecialchars($item['price'], ENT_QUOTES, 'UTF-8') ?>"
                            required>
                        <div class="validation-error" id="priceError">Price must be between 0 and 999,999.99</div>
                    </label>
                </div>
            </div>

            <div style="margin-bottom: 30px;">
                <label>
                    Dish Image
                    <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" id="imageInput">
                    <div class="file-upload-info" style="background: #2a2a2a; border-radius: 5px; padding: 10px; margin-top: 5px; font-size: 12px; color: #ccc;">
                        <strong>Upload Requirements:</strong><br>
                        • Maximum size: <?= MAX_FILE_SIZE / 1024 / 1024 ?>MB<br>
                        • Allowed types: JPEG, PNG, GIF, WebP<br>
                        • Leave empty to keep current image
                    </div>
                    <img id="imagePreview" class="upload-preview" alt="New image preview">
                    <div class="validation-error" id="imageError">Invalid image file</div>
                </label>
                <small style="color: #ccc; margin-top: 5px; display: block;">
                    Leave empty to keep current image
                </small>
                <?php if ($item['image']): ?>
                    <div class="current-image">
                        <?php if (file_exists(UPLOAD_PATH . $item['image'])): ?>
                            <img src="../uploads/<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>" width="120" alt="Current image">
                            <br><small style="color: #ccc;">Current image</small>
                        <?php else: ?>
                            <div style="color: #ff6b6b;">Image file missing: <?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Language Tabs -->
            <div class="translation-tabs">
                <?php foreach ($languages as $index => $lang): ?>
                    <button type="button" class="tab-btn <?= $index === 0 ? 'active' : '' ?>"
                        onclick="switchTab('<?= $lang['code'] ?>')">
                        <?= htmlspecialchars($lang['native_name'], ENT_QUOTES, 'UTF-8') ?> (<?= strtoupper($lang['code']) ?>)
                        <?php if ($index === 0): ?><span class="required-indicator">*</span><?php endif; ?>
                        <?php if (isset($translationsByLang[$lang['code']]) && !empty($translationsByLang[$lang['code']]['name'])): ?>
                            <span style="color: #4CAF50; margin-left: 5px;">✓</span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Translation Content -->
            <?php foreach ($languages as $index => $lang): ?>
                <?php $existingTrans = $translationsByLang[$lang['code']] ?? null; ?>
                <div class="translation-content <?= $index === 0 ? 'active' : '' ?> <?= $lang['direction'] === 'rtl' ? 'rtl-content' : '' ?>"
                    id="tab-<?= $lang['code'] ?>">

                    <h4>
                        <span><?= htmlspecialchars($lang['native_name'], ENT_QUOTES, 'UTF-8') ?> Translation</span>
                        <?php if ($index === 0): ?>
                            <span class="required-indicator">(Required)</span>
                        <?php endif; ?>
                        <?php if ($existingTrans): ?>
                            <span style="color: #4CAF50; font-size: 14px;">✓ Exists</span>
                        <?php else: ?>
                            <span style="color: #ff6b6b; font-size: 14px;">✗ Missing</span>
                        <?php endif; ?>
                    </h4>

                    <div style="display: grid; gap: 15px;">
                        <div>
                            <label>
                                Dish Name <?= $index === 0 ? '<span class="required-indicator">*</span>' : '' ?>
                                <input type="text"
                                    name="name_<?= $lang['code'] ?>"
                                    value="<?= $existingTrans ? htmlspecialchars($existingTrans['name'], ENT_QUOTES, 'UTF-8') : '' ?>"
                                    placeholder="Enter dish name in <?= htmlspecialchars($lang['native_name'], ENT_QUOTES, 'UTF-8') ?>"
                                    maxlength="255"
                                    <?= $index === 0 ? 'required' : '' ?>
                                    oninput="updateProgress(); updateCharCount(this, 255)">
                                <div class="input-counter" id="name_<?= $lang['code'] ?>_counter">0/255</div>
                                <div class="validation-error" id="name_<?= $lang['code'] ?>_error">Name is required for <?= htmlspecialchars($lang['native_name'], ENT_QUOTES, 'UTF-8') ?></div>
                            </label>
                        </div>

                        <div>
                            <label>
                                Description (Optional)
                                <textarea name="description_<?= $lang['code'] ?>"
                                    rows="3"
                                    maxlength="1000"
                                    placeholder="Brief description in <?= htmlspecialchars($lang['native_name'], ENT_QUOTES, 'UTF-8') ?>"
                                    oninput="updateProgress(); updateCharCount(this, 1000)"><?= $existingTrans ? htmlspecialchars($existingTrans['description'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                                <div class="input-counter" id="description_<?= $lang['code'] ?>_counter">0/1000</div>
                            </label>
                        </div>

                        <?php if ($existingTrans): ?>
                            <div style="background: #000000; padding: 10px; border-radius: 5px; border: 1px solid rgba(248, 248, 248, 0.1);">
                                <small class="last-updated">
                                    <strong>Last updated:</strong> <?= date('M j, Y \a\t g:i A', strtotime($existingTrans['updated_at'])) ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div style="display: flex; gap: 10px; justify-content: center; margin-top: 30px;">
                <button type="submit" class="btn" id="submitBtn">
                    Save Changes
                </button>
                <?php if ($item['menu_type_id']): ?>
                    <a href="dashboard.php?menu_type=<?= $item['menu_type_id'] ?>" class="btn">
                        ← Back to <?= htmlspecialchars($currentMenuTypeName, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php else: ?>
                    <a href="dashboard.php" class="btn">
                        ← Back to Dashboard
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <script>
        function switchTab(langCode) {
            // Update tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            // Update content
            document.querySelectorAll('.translation-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(`tab-${langCode}`).classList.add('active');

            // Update progress indicator
            updateProgress();
        }

        function updateProgress() {
            const languages = <?= json_encode(array_column($languages, 'code')) ?>;
            const indicators = document.querySelectorAll('.lang-dot');

            languages.forEach((lang, index) => {
                const nameInput = document.querySelector(`input[name="name_${lang}"]`);
                const indicator = indicators[index];

                indicator.classList.remove('completed', 'current');

                if (nameInput && nameInput.value.trim()) {
                    indicator.classList.add('completed');
                } else if (nameInput === document.activeElement) {
                    indicator.classList.add('current');
                }
            });

            validateForm();
        }

        function updateCharCount(input, maxLength) {
            const counter = document.getElementById(input.name + '_counter');
            if (counter) {
                const length = input.value.length;
                counter.textContent = `${length}/${maxLength}`;
                counter.style.color = length > maxLength * 0.9 ? '#ff6b6b' : '#999';
            }
        }

        function validateForm() {
            let isValid = true;

            // Validate required English name
            const englishName = document.querySelector('input[name="name_en"]');
            const englishNameError = document.getElementById('name_en_error');
            if (!englishName.value.trim()) {
                isValid = false;
                englishNameError.style.display = 'block';
            } else {
                englishNameError.style.display = 'none';
            }

            // Validate price
            const price = document.querySelector('input[name="price"]');
            const priceError = document.getElementById('priceError');
            const priceValue = parseFloat(price.value);
            if (!price.value || isNaN(priceValue) || priceValue < 0 || priceValue > 999999.99) {
                isValid = false;
                priceError.style.display = 'block';
            } else {
                priceError.style.display = 'none';
            }

            // Validate category
            const category = document.querySelector('select[name="category_id"]');
            if (!category.value) {
                isValid = false;
            }

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = !isValid;
            submitBtn.style.opacity = isValid ? '1' : '0.5';
        }

        // Image preview and validation
        document.getElementById('imageInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('imagePreview');
            const error = document.getElementById('imageError');

            if (file) {
                // Validate file size
                if (file.size > <?= MAX_FILE_SIZE ?>) {
                    error.textContent = 'File size exceeds <?= MAX_FILE_SIZE / 1024 / 1024 ?>MB limit';
                    error.style.display = 'block';
                    preview.style.display = 'none';
                    e.target.value = '';
                    return;
                }

                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    error.textContent = 'Invalid file type. Please select a JPEG, PNG, GIF, or WebP image.';
                    error.style.display = 'block';
                    preview.style.display = 'none';
                    e.target.value = '';
                    return;
                }

                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);

                error.style.display = 'none';
            } else {
                preview.style.display = 'none';
            }
        });

        // Initialize character counters
        document.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(input => {
            const maxLength = parseInt(input.getAttribute('maxlength'));
            updateCharCount(input, maxLength);

            input.addEventListener('input', function() {
                updateCharCount(this, maxLength);
            });
        });

        // Initialize progress on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateProgress();

            // Add event listeners to all inputs
            document.querySelectorAll('input[name^="name_"], textarea[name^="description_"], input[name="price"], select[name="category_id"]').forEach(input => {
                input.addEventListener('input', updateProgress);
                input.addEventListener('focus', updateProgress);
                input.addEventListener('blur', updateProgress);
            });
        });

        // Form validation
        document.getElementById('editItemForm').addEventListener('submit', function(e) {
            const englishName = document.querySelector('input[name="name_en"]');
            if (!englishName.value.trim()) {
                e.preventDefault();
                alert('English name is required!');
                switchTab('en');
                englishName.focus();
                return false;
            }

            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving changes...';

            return true;
        });

        // Auto-save progress indication
        let saveTimeout;
        document.querySelectorAll('input[name^="name_"], textarea[name^="description_"]').forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(saveTimeout);
                // Visual feedback that changes are being made
                this.style.borderColor = '#ffc107';

                saveTimeout = setTimeout(() => {
                    this.style.borderColor = '';
                }, 1000);
            });
        });

        // Security: Clear sensitive form data on page unload
        window.addEventListener('beforeunload', function() {
            document.querySelectorAll('input[type="file"]').forEach(input => {
                input.value = '';
            });
        });
    </script>

</body>

</html>