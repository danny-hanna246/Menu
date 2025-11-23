<?php
/**
 * Add New Menu Item
 * إضافة منتج جديد مع دعم menu_type_id والترتيب
 */

require_once 'session.php';
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

requireAdmin();

$success = '';
$error = '';

// Get languages
$languages = $pdo->query("SELECT * FROM languages WHERE is_active = 1")->fetchAll();

// Get menu types
$menuTypes = $pdo->query("
    SELECT mt.id, mtt.name 
    FROM menu_types mt
    JOIN menu_type_translations mtt ON mt.id = mtt.menu_type_id
    WHERE mtt.language_code = 'en'
    ORDER BY mt.id
")->fetchAll();

// Get categories with menu types
$categories = $pdo->query("
    SELECT c.id, c.menu_type_id, ct.name, mtt.name as menu_type_name
    FROM categories c
    JOIN category_translations ct ON c.id = ct.category_id AND ct.language_code = 'en'
    JOIN menu_types mt ON c.menu_type_id = mt.id
    JOIN menu_type_translations mtt ON mt.id = mtt.menu_type_id AND mtt.language_code = 'en'
    WHERE c.is_active = 1
    ORDER BY c.menu_type_id, c.display_order
")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    try {
        validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '');

        // Validate required fields
        $categoryId = intval($_POST['category_id']);
        $price = floatval($_POST['price']);
        $displayOrder = intval($_POST['display_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

        if ($categoryId <= 0) {
            throw new Exception('Please select a category');
        }

        if ($price < 0) {
            throw new Exception('Price must be positive');
        }

        // Validate at least English name is provided
        if (empty(trim($_POST['name_en'] ?? ''))) {
            throw new Exception('English name is required');
        }

        // Handle image upload
        $imageName = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                throw new Exception('Invalid image format. Allowed: jpg, jpeg, png, gif, webp');
            }

            // Generate unique filename
            $imageName = uniqid() . '_' . time() . '.' . $ext;
            $uploadPath = '../uploads/' . $imageName;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                throw new Exception('Failed to upload image');
            }
        }

        // Start transaction
        $pdo->beginTransaction();

        // Insert menu item
        $stmt = $pdo->prepare("
            INSERT INTO menu_items (category_id, price, image, display_order, is_active, is_featured)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$categoryId, $price, $imageName, $displayOrder, $isActive, $isFeatured]);
        $itemId = $pdo->lastInsertId();

        // Insert translations
        foreach ($languages as $lang) {
            $name = trim($_POST['name_' . $lang['code']] ?? '');
            $description = trim($_POST['description_' . $lang['code']] ?? '');

            if ($name) {
                $stmt = $pdo->prepare("
                    INSERT INTO menu_item_translations (menu_item_id, language_code, name, description)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$itemId, $lang['code'], $name, $description]);
            }
        }

        $pdo->commit();

        $success = "Menu item added successfully!";
        
        // Redirect to dashboard
        header('Location: dashboard.php?success=Item added successfully');
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Delete uploaded image if transaction failed
        if ($imageName && file_exists('../uploads/' . $imageName)) {
            unlink('../uploads/' . $imageName);
        }
        
        $error = $e->getMessage();
    }
}

$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Menu Item - Living Room Restaurant</title>
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>➕ Add New Menu Item</h1>
            <div style="display: flex; gap: 10px;">
                <a href="dashboard.php" class="btn" style="background: #666;">← Back to Dashboard</a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="success-message">
                ✅ <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">

            <!-- Basic Information -->
            <div class="form-section">
                <h3>📋 Basic Information</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label>Category <span class="required">*</span></label>
                        <select name="category_id" required id="categorySelect">
                            <option value="">Select Category</option>
                            <?php 
                            $currentMenuType = '';
                            foreach ($categories as $cat): 
                                if ($currentMenuType !== $cat['menu_type_name']) {
                                    if ($currentMenuType !== '') echo '</optgroup>';
                                    echo '<optgroup label="' . htmlspecialchars($cat['menu_type_name']) . '">';
                                    $currentMenuType = $cat['menu_type_name'];
                                }
                            ?>
                                <option value="<?= $cat['id'] ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php 
                            endforeach;
                            if ($currentMenuType !== '') echo '</optgroup>';
                            ?>
                        </select>
                        <div class="helper-text">Select the category for this item</div>
                    </div>

                    <div class="form-group">
                        <label>Price (IQD) <span class="required">*</span></label>
                        <input type="number" name="price" step="0.01" min="0" required placeholder="25000">
                        <div class="helper-text">Enter price in Iraqi Dinar</div>
                    </div>

                    <div class="form-group">
                        <label>Display Order</label>
                        <input type="number" name="display_order" value="0" min="0" placeholder="0">
                        <div class="helper-text">Lower numbers appear first (default: 0)</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Item Image</label>
                    <input type="file" name="image" accept="image/*" id="imageInput">
                    <div class="helper-text">Supported formats: JPG, PNG, GIF, WebP (Max: 5MB)</div>
                    <div class="image-preview" id="imagePreview">
                        <img id="previewImg" src="" alt="Preview">
                    </div>
                </div>

                <div class="form-row">
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_active" id="isActive" checked>
                        <label for="isActive">✅ Active (visible to customers)</label>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" name="is_featured" id="isFeatured">
                        <label for="isFeatured">⭐ Featured (highlight this item)</label>
                    </div>
                </div>
            </div>

            <!-- Translations -->
            <div class="form-section">
                <h3>🌐 Translations</h3>
                
                <div class="language-tabs">
                    <?php foreach ($languages as $index => $lang): ?>
                        <button type="button" class="language-tab <?= $index === 0 ? 'active' : '' ?>" 
                                onclick="switchLanguage('<?= $lang['code'] ?>')">
                            <?= htmlspecialchars($lang['name']) ?> (<?= strtoupper($lang['code']) ?>)
                            <?= $lang['code'] === 'en' ? '<span class="required">*</span>' : '' ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($languages as $index => $lang): ?>
                    <div class="language-content <?= $index === 0 ? 'active' : '' ?>" id="lang-<?= $lang['code'] ?>">
                        <div class="form-group">
                            <label>
                                Item Name (<?= $lang['name'] ?>)
                                <?= $lang['code'] === 'en' ? '<span class="required">*</span>' : '' ?>
                            </label>
                            <input type="text" 
                                   name="name_<?= $lang['code'] ?>" 
                                   <?= $lang['code'] === 'en' ? 'required' : '' ?>
                                   placeholder="e.g., Grilled Chicken, حمص، کەباب"
                                   dir="<?= $lang['direction'] ?>">
                        </div>

                        <div class="form-group">
                            <label>Description (<?= $lang['name'] ?>)</label>
                            <textarea name="description_<?= $lang['code'] ?>" 
                                      placeholder="Describe the item..."
                                      dir="<?= $lang['direction'] ?>"></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" name="add_item" class="btn-submit">
                ➕ Add Menu Item
            </button>
        </form>
    </div>

    <script>
        // Image preview
        document.getElementById('imageInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        // Language switcher
        function switchLanguage(langCode) {
            // Hide all language contents
            document.querySelectorAll('.language-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.language-tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected language
            document.getElementById('lang-' + langCode).classList.add('active');
            event.target.classList.add('active');
        }

        // Auto-hide messages
        setTimeout(() => {
            const messages = document.querySelectorAll('.success-message, .error-message');
            messages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>