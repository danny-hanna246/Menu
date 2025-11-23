<?php
/**
 * Admin Dashboard - Enhanced with Category Grouping and Sorting
 * لوحة التحكم الرئيسية - مع التقسيم حسب الفئات وإمكانية الترتيب
 */

require_once 'session.php';
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

requireAdmin();

$success = '';
$error = '';

// Handle item ordering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    try {
        validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '');
        
        $itemId = intval($_POST['item_id']);
        $newOrder = intval($_POST['display_order']);
        
        $stmt = $pdo->prepare("UPDATE menu_items SET display_order = ? WHERE id = ?");
        $stmt->execute([$newOrder, $itemId]);
        
        $success = "Display order updated successfully!";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle item deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    try {
        validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '');
        
        $itemId = intval($_POST['item_id']);
        
        // Get image name before deletion
        $stmt = $pdo->prepare("SELECT image FROM menu_items WHERE id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        
        // Delete translations
        $pdo->prepare("DELETE FROM menu_item_translations WHERE menu_item_id = ?")->execute([$itemId]);
        
        // Delete item
        $pdo->prepare("DELETE FROM menu_items WHERE id = ?")->execute([$itemId]);
        
        // Delete image file
        if ($item && $item['image']) {
            $imagePath = '../uploads/' . $item['image'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        $success = "Item deleted successfully!";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all items with translations - grouped by category
$query = "
    SELECT 
        m.id,
        m.price,
        m.image,
        m.display_order,
        m.is_active,
        m.is_featured,
        m.created_at,
        c.id as category_id,
        mt.id as menu_type_id,
        COALESCE(mit_en.name, 'No Name') as name_en,
        COALESCE(mit_en.description, '') as description_en,
        COALESCE(ct_en.name, 'Uncategorized') as category_name,
        COALESCE(mtt_en.name, 'Unknown Type') as menu_type_name
    FROM menu_items m
    LEFT JOIN categories c ON m.category_id = c.id
    LEFT JOIN menu_types mt ON c.menu_type_id = mt.id
    LEFT JOIN menu_item_translations mit_en ON m.id = mit_en.menu_item_id AND mit_en.language_code = 'en'
    LEFT JOIN category_translations ct_en ON c.id = ct_en.category_id AND ct_en.language_code = 'en'
    LEFT JOIN menu_type_translations mtt_en ON mt.id = mtt_en.menu_type_id AND mtt_en.language_code = 'en'
    ORDER BY mt.id, c.display_order, m.display_order, m.id DESC
";

$items = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Group items by menu type and category
$groupedItems = [];
foreach ($items as $item) {
    $menuType = $item['menu_type_name'];
    $category = $item['category_name'];
    
    if (!isset($groupedItems[$menuType])) {
        $groupedItems[$menuType] = [];
    }
    
    if (!isset($groupedItems[$menuType][$category])) {
        $groupedItems[$menuType][$category] = [];
    }
    
    $groupedItems[$menuType][$category][] = $item;
}

// Calculate statistics
$totalItems = count($items);
$activeItems = count(array_filter($items, fn($item) => $item['is_active'] == 1));
$featuredItems = count(array_filter($items, fn($item) => $item['is_featured'] == 1));

$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Living Room Restaurant</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #000000;
            padding: 25px;
            border-radius: 10px;
            border: 1px solid rgba(248, 248, 248, 0.1);
            text-align: center;
            transition: all 0.3s;
        }

        .stat-card:hover {
            border-color: #ef4444;
            transform: translateY(-3px);
        }

        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #ef4444;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #ccc;
            font-size: 14px;
        }

        .menu-type-section {
            margin-bottom: 40px;
            background: #1a1a1a;
            border-radius: 15px;
            padding: 25px;
            border: 2px solid rgba(239, 68, 68, 0.3);
        }

        .menu-type-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(239, 68, 68, 0.5);
        }

        .menu-type-icon {
            font-size: 32px;
        }

        .menu-type-title {
            font-size: 24px;
            font-weight: 600;
            color: #ef4444;
            margin: 0;
        }

        .category-section {
            margin-bottom: 30px;
            background: #000000;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid rgba(248, 248, 248, 0.1);
        }

        .category-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #333;
        }

        .category-title {
            font-size: 18px;
            font-weight: 600;
            color: #f8f8f8;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .category-count {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: 600;
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .item-card {
            background: #1a1a1a;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid rgba(248, 248, 248, 0.1);
            transition: all 0.3s;
            position: relative;
        }

        .item-card:hover {
            border-color: #ef4444;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.2);
        }

        .item-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: #000;
        }

        .item-content {
            padding: 15px;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }

        .item-name {
            font-size: 16px;
            font-weight: 600;
            color: #f8f8f8;
            flex: 1;
        }

        .item-order-badge {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            padding: 3px 8px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
        }

        .item-desc {
            color: #999;
            font-size: 13px;
            margin-bottom: 12px;
            line-height: 1.5;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .item-price {
            font-size: 18px;
            font-weight: bold;
            color: #ef4444;
            margin-bottom: 15px;
        }

        .item-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .item-actions .btn {
            padding: 8px;
            font-size: 13px;
            text-align: center;
        }

        .order-control {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }

        .order-input {
            flex: 1;
            padding: 8px;
            background: #000;
            border: 1px solid #333;
            border-radius: 5px;
            color: #f8f8f8;
            font-size: 13px;
        }

        .btn-order {
            padding: 8px 15px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-order:hover {
            background: #2563eb;
        }

        .btn-edit {
            background: #fbbf24;
            color: #000;
        }

        .btn-edit:hover {
            background: #f59e0b;
        }

        .btn-delete {
            background: #ef4444;
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .featured-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(251, 191, 36, 0.9);
            color: #000;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .inactive-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }

        .no-items {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 15px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .action-buttons .btn {
            padding: 12px 20px;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .items-grid {
                grid-template-columns: 1fr;
            }

            .menu-type-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .category-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏠 Dashboard</h1>
            <div class="action-buttons">
                <a href="manage-menu-types.php" class="btn" style="background: #8b5cf6;">🍴 Menu Types</a>
                <a href="manage-categories.php" class="btn" style="background: #3b82f6;">📂 Categories</a>
                <a href="add.php" class="btn" style="background: #22c55e;">➕ Add Item</a>
                <a href="orders.php" class="btn" style="background: #f59e0b;">📦 Orders</a>
                <a href="ratings.php" class="btn" style="background: #fbbf24;">⭐ Ratings</a>
                <a href="logout.php" class="btn" style="background: #ef4444;">🚪 Logout</a>
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

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number"><?= $totalItems ?></div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $activeItems ?></div>
                <div class="stat-label">Active Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $featuredItems ?></div>
                <div class="stat-label">Featured Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($groupedItems) ?></div>
                <div class="stat-label">Menu Types</div>
            </div>
        </div>

        <?php if (empty($items)): ?>
            <div class="no-items">
                <h3>📭 No menu items yet</h3>
                <p>Start by <a href="manage-menu-types.php">creating menu types</a>, then <a href="manage-categories.php">add categories</a>, and finally <a href="add.php">add your first item</a>!</p>
            </div>
        <?php else: ?>
            <!-- Display items grouped by Menu Type and Category -->
            <?php foreach ($groupedItems as $menuTypeName => $categories): ?>
                <div class="menu-type-section">
                    <div class="menu-type-header">
                        <span class="menu-type-icon">
                            <?= $menuTypeName === 'Indoor Menu' ? '🍽️' : '🚚' ?>
                        </span>
                        <h2 class="menu-type-title"><?= htmlspecialchars($menuTypeName) ?></h2>
                        <span class="category-count">
                            <?= array_sum(array_map('count', $categories)) ?> items
                        </span>
                    </div>

                    <?php foreach ($categories as $categoryName => $categoryItems): ?>
                        <div class="category-section">
                            <div class="category-header">
                                <div class="category-title">
                                    <span>📂</span>
                                    <span><?= htmlspecialchars($categoryName) ?></span>
                                </div>
                                <span class="category-count"><?= count($categoryItems) ?> items</span>
                            </div>

                            <div class="items-grid">
                                <?php foreach ($categoryItems as $item): ?>
                                    <div class="item-card">
                                        <?php if ($item['is_featured']): ?>
                                            <span class="featured-badge">⭐ Featured</span>
                                        <?php endif; ?>

                                        <?php if (!$item['is_active']): ?>
                                            <span class="inactive-badge">❌ Inactive</span>
                                        <?php endif; ?>

                                        <?php if ($item['image'] && file_exists('../uploads/' . $item['image'])): ?>
                                            <img src="../uploads/<?= htmlspecialchars($item['image']) ?>" 
                                                 alt="<?= htmlspecialchars($item['name_en']) ?>" 
                                                 class="item-image">
                                        <?php else: ?>
                                            <div class="item-image" style="display: flex; align-items: center; justify-content: center; color: #666; font-size: 48px;">
                                                📷
                                            </div>
                                        <?php endif; ?>

                                        <div class="item-content">
                                            <div class="item-header">
                                                <div class="item-name"><?= htmlspecialchars($item['name_en']) ?></div>
                                                <span class="item-order-badge">#<?= $item['display_order'] ?></span>
                                            </div>

                                            <?php if ($item['description_en']): ?>
                                                <div class="item-desc"><?= htmlspecialchars($item['description_en']) ?></div>
                                            <?php endif; ?>

                                            <div class="item-price"><?= number_format($item['price']) ?> IQD</div>

                                            <!-- Order Control -->
                                            <form method="POST" class="order-control">
                                                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                <input type="number" 
                                                       name="display_order" 
                                                       value="<?= $item['display_order'] ?>" 
                                                       class="order-input" 
                                                       min="0" 
                                                       placeholder="Order">
                                                <button type="submit" name="update_order" class="btn-order">
                                                    🔄 Update
                                                </button>
                                            </form>

                                            <!-- Action Buttons -->
                                            <div class="item-actions">
                                                <a href="edit.php?id=<?= $item['id'] ?>" class="btn btn-edit">
                                                    ✏️ Edit
                                                </a>
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this item?');" style="margin: 0;">
                                                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                    <button type="submit" name="delete_item" class="btn btn-delete" style="width: 100%;">
                                                        🗑️ Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        // Auto-hide success/error messages
        setTimeout(() => {
            const messages = document.querySelectorAll('.success-message, .error-message');
            messages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);

        // Confirm before deleting
        document.querySelectorAll('form[onsubmit*="delete"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('⚠️ Are you sure you want to delete this item? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>