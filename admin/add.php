<?php
// Enhanced add.php with comprehensive security measures
require 'db.php';
require 'auth.php';
require 'translations.php';
require 'functions.php';
$translation = new Translation($pdo);
$languages = $translation->getLanguages();
$menuTypes = $translation->getMenuTypesWithTranslations();

// Get selected menu type from URL parameter with validation
$selectedMenuType = null;
if (isset($_GET['menu_type'])) {
    try {
        $selectedMenuType = validateInput('int', $_GET['menu_type'], ['min' => 1]);
        // Verify menu type exists
        $menuTypeExists = false;
        foreach ($menuTypes as $mt) {
            if ($mt['id'] == $selectedMenuType) {
                $menuTypeExists = true;
                break;
            }
        }
        if (!$menuTypeExists) {
            $selectedMenuType = null;
        }
    } catch (Exception $e) {
        $selectedMenuType = null;
    }
}

$existingCategories = [];
if ($selectedMenuType) {
    $existingCategories = $translation->getCategoriesWithTranslations('en', $selectedMenuType);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        validateFormCSRF();

        // Validate and sanitize inputs
        $categoryId = validateInput('int', $_POST['category_id'] ?? '', ['min' => 1]);
        $price = validateInput('float', $_POST['price'] ?? '', ['min' => 0, 'max' => 999999.99]);

        // Validate category belongs to selected menu type
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
        $imageName = '';
        if (!empty($_FILES['image']['name'])) {
            $imageName = uploadImage($_FILES['image'], 'menu_');
        }

        // Database transaction for data integrity
        $pdo->beginTransaction();

        try {
            // Insert menu item
            $stmt = $pdo->prepare("INSERT INTO menu_items (category_id, price, image, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$categoryId, $price, $imageName]);
            $menuItemId = $pdo->lastInsertId();

            // Validate and insert translations
            $translationsAdded = 0;
            foreach ($languages as $lang) {
                $nameKey = 'name_' . $lang['code'];
                $descKey = 'description_' . $lang['code'];

                if (!empty($_POST[$nameKey])) {
                    $name = validateInput('string', $_POST[$nameKey], ['max_length' => 255]);
                    $description = validateInput('string', $_POST[$descKey] ?? '', ['max_length' => 1000]);

                    if (!empty($name)) {
                        $translation->saveMenuItemTranslation($menuItemId, $lang['code'], $name, $description);
                        $translationsAdded++;
                    }
                }
            }

            // Ensure at least one translation exists
            if ($translationsAdded === 0) {
                throw new Exception('At least one language translation is required');
            }

            $pdo->commit();

            // Audit log
            auditLog('menu_item_created', [
                'menu_item_id' => $menuItemId,
                'category_id' => $categoryId,
                'price' => $price,
                'image' => $imageName,
                'translations_count' => $translationsAdded
            ]);

            $success = "Menu item added successfully with {$translationsAdded} translations!";

            // Redirect back to the same menu type
            $redirectUrl = $selectedMenuType ? "dashboard.php?menu_type={$selectedMenuType}&success=" . urlencode($success) : "dashboard.php?success=" . urlencode($success);
            header('Location: ' . $redirectUrl);
            exit;
        } catch (Exception $e) {
            $pdo->rollback();
            // Clean up uploaded image if database operation failed
            if (!empty($imageName) && file_exists(UPLOAD_PATH . $imageName)) {
                unlink(UPLOAD_PATH . $imageName);
            }
            throw $e;
        }
    } catch (Exception $e) {
        $error = "Error adding menu item: " . $e->getMessage();
        logError("Menu item creation failed", [
            'error' => $e->getMessage(),
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
    <title>Add Menu Item - Restaurant Admin</title>
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

        .file-upload-info {
            background: #2a2a2a;
            border-radius: 5px;
            padding: 10px;
            margin-top: 5px;
            font-size: 12px;
            color: #ccc;
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

        .menu-type-card.selected {
            border-color: #4CAF50;
            background: #1b2d1b;
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

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }
    </style>
</head>

<body>
    <h1>➕ Add New Dish</h1>

    <div class="container">
        <?php if (!$selectedMenuType): ?>
            <!-- Step 1: Select Menu Type -->
            <div class="menu-type-selector">
                <h2 style="margin-bottom: 10px;">🍽️ Choose Menu Type</h2>
                <p>First, select which menu you want to add items to.</p>

                <div class="menu-type-cards">
                    <?php foreach ($menuTypes as $menuType): ?>
                        <a href="add.php?menu_type=<?= $menuType['id'] ?>" class="menu-type-card">
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
            <!-- Step 2: Add Item Form -->
            <?php
            $selectedMenuTypeName = '';
            foreach ($menuTypes as $mt) {
                if ($mt['id'] == $selectedMenuType) {
                    $selectedMenuTypeName = $mt['name'];
                    break;
                }
            }
            ?>

            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> →
                <a href="add.php">Add Item</a> →
                <strong><?= htmlspecialchars($selectedMenuTypeName, ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div style="margin-bottom: 20px;">
                <h2 style="margin-bottom: 10px;">➕ Create New Menu Item</h2>
                <p>Adding item to: <strong><?= htmlspecialchars($selectedMenuTypeName, ENT_QUOTES, 'UTF-8') ?></strong></p>
            </div>

            <!-- Security Notice -->
            <div class="security-notice">
                <strong>🔒 Security Notice:</strong> All uploads are scanned and validated. Maximum file size: <?= MAX_FILE_SIZE / 1024 / 1024 ?>MB.
                Allowed formats: JPG, PNG, GIF, WebP.
            </div>

            <?php if (isset($success)): ?>
                <div style="background: #1b2d1b; color: #4CAF50; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #4CAF50;">
                    ✅ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div style="background: #2d1b1b; color: #ff6b6b; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ff6b6b;">
                    ⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (empty($existingCategories)): ?>
                <div style="background: #2d2416; color: #ffc107; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ffc107;">
                    <strong>⚠️ No categories found for this menu type!</strong><br>
                    You need to create at least one category first.
                    <a href="manage-categories.php?menu_type=<?= $selectedMenuType ?>" style="color: #ffc107; text-decoration: underline;">Go to Categories Management</a>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" id="addItemForm" novalidate>
                <!-- CSRF Protection -->
                <?= renderCSRFField() ?>

                <!-- Progress Indicator -->
                <div class="progress-indicator">
                    <div class="progress-text">Translation Progress</div>
                    <div class="lang-indicator" id="langIndicator">
                        <?php foreach ($languages as $lang): ?>
                            <div class="lang-dot" data-lang="<?= $lang['code'] ?>" title="<?= htmlspecialchars($lang['native_name'], ENT_QUOTES, 'UTF-8') ?>"></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Basic Details -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div>
                        <label>
                            🏷️ Category <span class="required-indicator">*</span>
                            <select name="category_id" required <?= empty($existingCategories) ? 'disabled' : '' ?>>
                                <option value="">Select a category</option>
                                <?php foreach ($existingCategories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <div>
                        <label>
                            💰 Price (IQD) <span class="required-indicator">*</span>
                            <input type="number" name="price" step="0.01" min="0" max="999999.99" required placeholder="0.00">
                            <div class="validation-error" id="priceError">Price must be between 0 and 999,999.99</div>
                        </label>
                    </div>
                </div>

                <div style="margin-bottom: 30px;">
                    <label>
                        📷 Dish Image <span class="required-indicator">*</span>
                        <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" required id="imageInput">
                        <div class="file-upload-info">
                            <strong>Security Requirements:</strong><br>
                            • Maximum size: <?= MAX_FILE_SIZE / 1024 / 1024 ?>MB<br>
                            • Allowed types: JPEG, PNG, GIF, WebP<br>
                            • Files are automatically scanned for security<br>
                            • Images will be optimized automatically
                        </div>
                        <img id="imagePreview" class="upload-preview" alt="Image preview">
                        <div class="validation-error" id="imageError">Please select a valid image file</div>
                    </label>
                </div>

                <!-- Language Tabs -->
                <div class="translation-tabs">
                    <?php foreach ($languages as $index => $lang): ?>
                        <button type="button" class="tab-btn <?= $index === 0 ? 'active' : '' ?>"
                            onclick="switchTab('<?= $lang['code'] ?>')">
                            <?= htmlspecialchars($lang['native_name'], ENT_QUOTES, 'UTF-8') ?> (<?= strtoupper($lang['code']) ?>)
                            <?php if ($index === 0): ?><span class="required-indicator">*</span><?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Translation Content -->
                <?php foreach ($languages as $index => $lang): ?>
                    <div class="translation-content <?= $index === 0 ? 'active' : '' ?> <?= $lang['direction'] === 'rtl' ? 'rtl-content' : '' ?>"
                        id="tab-<?= $lang['code'] ?>">

                        <h4>
                            <span><?= htmlspecialchars($lang['native_name'], ENT_QUOTES, 'UTF-8') ?> Translation</span>
                            <?php if ($index === 0): ?>
                                <span class="required-indicator">(Required)</span>
                            <?php endif; ?>
                        </h4>

                        <div style="display: grid; gap: 15px;">
                            <div>
                                <label>
                                    🍽️ Dish Name <?= $index === 0 ? '<span class="required-indicator">*</span>' : '' ?>
                                    <input type="text"
                                        name="name_<?= $lang['code'] ?>"
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
                                    📝 Description (Optional)
                                    <textarea name="description_<?= $lang['code'] ?>"
                                        rows="3"
                                        maxlength="1000"
                                        placeholder="Brief description in <?= htmlspecialchars($lang['native_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        oninput="updateProgress(); updateCharCount(this, 1000)"></textarea>
                                    <div class="input-counter" id="description_<?= $lang['code'] ?>_counter">0/1000</div>
                                </label>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div style="display: flex; gap: 10px; justify-content: center; margin-top: 30px;">
                    <button type="submit" class="btn" <?= empty($existingCategories) ? 'disabled' : '' ?> id="submitBtn">
                        ✅ Add Menu Item
                    </button>
                    <a href="add.php" class="btn">
                        ← Choose Different Menu Type
                    </a>
                    <a href="dashboard.php?menu_type=<?= $selectedMenuType ?>" class="btn">
                        ← Back to <?= htmlspecialchars($selectedMenuTypeName, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        // Security: Disable right-click on sensitive areas
        document.addEventListener('contextmenu', function(e) {
            if (e.target.closest('.security-notice') || e.target.closest('form')) {
                e.preventDefault();
            }
        });

        // Form validation and security
        const form = document.getElementById('addItemForm');
        const submitBtn = document.getElementById('submitBtn');
        let formValid = false;

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

            // Validate image
            const image = document.querySelector('input[name="image"]');
            const imageError = document.getElementById('imageError');
            if (!image.files.length) {
                isValid = false;
                imageError.style.display = 'block';
            } else {
                imageError.style.display = 'none';
            }

            formValid = isValid;
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
                    validateForm();
                    return;
                }

                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    error.textContent = 'Invalid file type. Please select a JPEG, PNG, GIF, or WebP image.';
                    error.style.display = 'block';
                    preview.style.display = 'none';
                    e.target.value = '';
                    validateForm();
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

            validateForm();
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

        // Form submission with final validation
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!formValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields correctly');
                    return false;
                }

                const englishName = document.querySelector('input[name="name_en"]');
                if (!englishName.value.trim()) {
                    e.preventDefault();
                    alert('English name is required!');
                    switchTab('en');
                    englishName.focus();
                    return false;
                }

                // Show loading state
                submitBtn.disabled = true;
                submitBtn.textContent = 'Creating item...';

                return true;
            });
        }

        // Security: Clear sensitive form data on page unload
        window.addEventListener('beforeunload', function() {
            document.querySelectorAll('input[type="file"]').forEach(input => {
                input.value = '';
            });
        });
    </script>

</body>

</html>