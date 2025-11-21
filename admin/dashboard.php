<?php
// Enhanced dashboard.php with comprehensive security and performance improvements
require 'functions.php';
require_once 'config.php';
require 'db.php';
require 'auth.php';
require 'translations.php';
$translation = new Translation($pdo);

// Handle AJAX requests for real-time updates with security
if (isset($_GET['action'])) {
  header('Content-Type: application/json');

  $action = sanitizeString($_GET['action']);

  // Rate limiting for AJAX requests
  $rateLimitKey = 'dashboard_ajax_' . getCurrentAdmin()['id'];
  if (!checkRateLimit($rateLimitKey, 50, 600)) { // 50 requests per 10 minutes
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit;
  }

  try {
    switch ($action) {
      case 'get_items':
        // Validate and sanitize search parameters
        $search = sanitizeString($_GET['search'] ?? '');
        $category = sanitizeString($_GET['category'] ?? '');
        $menuType = !empty($_GET['menu_type']) ? validateInput('int', $_GET['menu_type'], ['min' => 1]) : null;

        // Validate search length to prevent resource exhaustion
        if (strlen($search) > 100) {
          throw new Exception('Search term too long');
        }

        // Get items with translations
        $items = $translation->getMenuItemsWithTranslations('en', $menuType);

        // Apply filters with case-insensitive search
        if (!empty($search)) {
          $items = array_filter($items, function ($item) use ($search) {
            return stripos($item['name'], $search) !== false ||
              stripos($item['description'] ?? '', $search) !== false;
          });
        }

        if (!empty($category)) {
          $items = array_filter($items, function ($item) use ($category) {
            return stripos($item['category'], $category) !== false;
          });
        }

        // Add created_at for items that might be missing it and validate image paths
        foreach ($items as &$item) {
          if (!isset($item['created_at'])) {
            $stmt = $pdo->prepare("SELECT created_at FROM menu_items WHERE id = ?");
            $stmt->execute([$item['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $item['created_at'] = $result['created_at'] ?? date('Y-m-d H:i:s');
          }

          // Validate image exists
          if (!empty($item['image'])) {
            $imagePath = '../uploads/' . $item['image'];
            if (!file_exists($imagePath) || !is_readable($imagePath)) {
              $item['image_missing'] = true;
            }
          }
        }

        // Reindex array
        $items = array_values($items);

        // Audit log for search queries
        if (!empty($search)) {
          auditLog('search_items', [
            'search_term' => $search,
            'category_filter' => $category,
            'menu_type_filter' => $menuType,
            'results_count' => count($items)
          ]);
        }

        echo json_encode($items, JSON_UNESCAPED_UNICODE);
        break;

      case 'cleanup_missing_images':
        requirePermission('manage_images');

        // Begin transaction for cleanup
        $pdo->beginTransaction();

        try {
          $stmt = $pdo->query("SELECT id, image FROM menu_items WHERE image IS NOT NULL AND image != ''");
          $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

          $updated = 0;
          $cleanedImages = [];

          foreach ($items as $item) {
            $imagePath = '../uploads/' . $item['image'];
            if (!file_exists($imagePath) || !is_readable($imagePath)) {
              $updateStmt = $pdo->prepare("UPDATE menu_items SET image = NULL WHERE id = ?");
              $updateStmt->execute([$item['id']]);
              $updated++;
              $cleanedImages[] = $item['image'];
            }
          }

          $pdo->commit();

          // Audit log
          auditLog('cleanup_missing_images', [
            'cleaned_count' => $updated,
            'cleaned_images' => $cleanedImages
          ]);

          echo json_encode(['success' => true, 'updated' => $updated]);
        } catch (Exception $e) {
          $pdo->rollback();
          throw $e;
        }
        break;

      default:
        throw new Exception('Invalid action');
    }
  } catch (Exception $e) {
    logError("Dashboard AJAX error", [
      'action' => $action,
      'error' => $e->getMessage(),
      'user' => getCurrentAdmin()['username']
    ]);
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
  }
  exit;
}

// Get initial data with translations and security validation
$selectedMenuType = null;
if (!empty($_GET['menu_type'])) {
  try {
    $selectedMenuType = validateInput('int', $_GET['menu_type'], ['min' => 1]);
    // Verify menu type exists
    $menuTypes = $translation->getMenuTypesWithTranslations('en');
    $validMenuType = false;
    foreach ($menuTypes as $mt) {
      if ($mt['id'] == $selectedMenuType) {
        $validMenuType = true;
        break;
      }
    }
    if (!$validMenuType) {
      $selectedMenuType = null;
    }
  } catch (Exception $e) {
    $selectedMenuType = null;
  }
}

try {
  $items = $translation->getMenuItemsWithTranslations('en', $selectedMenuType);

  // Add created_at for items that might be missing it
  foreach ($items as &$item) {
    if (!isset($item['created_at'])) {
      $stmt = $pdo->prepare("SELECT created_at FROM menu_items WHERE id = ?");
      $stmt->execute([$item['id']]);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      $item['created_at'] = $result['created_at'] ?? date('Y-m-d H:i:s');
    }
  }

  // Get menu types and categories for filters
  $menuTypes = $translation->getMenuTypesWithTranslations('en');
  $categories = $translation->getCategoriesWithTranslations('en', $selectedMenuType);
  $categoryNames = array_column($categories, 'name');

  // Check for missing images
  $missingImages = 0;
  foreach ($items as $item) {
    if ($item['image'] && !file_exists('../uploads/' . $item['image'])) {
      $missingImages++;
    }
  }
} catch (Exception $e) {
  logError("Dashboard data loading failed", [
    'error' => $e->getMessage(),
    'user' => getCurrentAdmin()['username']
  ]);
  $items = [];
  $menuTypes = [];
  $categories = [];
  $categoryNames = [];
  $missingImages = 0;
  $error = "Unable to load dashboard data. Please try again.";
}

// Handle success/error messages from URL parameters
$success = '';
$error = '';
if (!empty($_GET['success'])) {
  $success = sanitizeString($_GET['success']);
}
if (!empty($_GET['error'])) {
  $error = sanitizeString($_GET['error']);
}

// Audit log for dashboard access
auditLog('dashboard_access', [
  'menu_type_filter' => $selectedMenuType,
  'total_items' => count($items),
  'missing_images' => $missingImages
]);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Restaurant Admin Panel</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .search-controls {
      background: #464747;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      display: flex;
      gap: 15px;
      align-items: center;
      flex-wrap: wrap;
      border: 1px solid rgba(248, 248, 248, 0.1);
    }

    .search-controls input,
    .search-controls select {
      padding: 10px 15px;
      border: 2px solid #000000;
      background: #000000;
      color: #f8f8f8;
      border-radius: 8px;
      font-size: 14px;
      min-width: 200px;
    }

    .search-controls input:focus,
    .search-controls select:focus {
      outline: none;
      border-color: #f8f8f8;
      box-shadow: 0 0 0 3px rgba(248, 248, 248, 0.1);
    }

    .clear-btn {
      background: #000000;
      color: #f8f8f8;
      border: 1px solid #f8f8f8;
      padding: 10px 15px;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
    }

    .clear-btn:hover {
      background: #464747;
    }

    .stats-row {
      display: flex;
      gap: 20px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }

    .stat-card {
      background: #464747;
      padding: 15px 20px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      flex: 1;
      min-width: 150px;
      text-align: center;
      border: 1px solid rgba(248, 248, 248, 0.1);
    }

    .stat-number {
      font-size: 24px;
      font-weight: bold;
      color: #f8f8f8;
    }

    .stat-label {
      color: #ccc;
      font-size: 14px;
      margin-top: 5px;
    }

    .loading {
      text-align: center;
      padding: 20px;
      color: #f8f8f8;
    }

    .no-results {
      text-align: center;
      padding: 40px;
      color: #ccc;
      background: #464747;
      border-radius: 10px;
      border: 1px solid rgba(248, 248, 248, 0.1);
    }

    .price-display {
      font-weight: 600;
      color: #f8f8f8;
    }

    .category-badge {
      padding: 5px 12px;
      border-radius: 15px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      background: #000000;
      color: #f8f8f8;
      border: 1px solid #f8f8f8;
    }

    .menu-type-badge {
      padding: 3px 8px;
      border-radius: 10px;
      font-size: 10px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      background: #f3e5f5;
      color: #7b1fa2;
      margin-left: 5px;
    }

    .image-placeholder {
      width: 80px;
      height: 60px;
      background: #000000;
      border: 1px solid #f8f8f8;
      border-radius: 5px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #f8f8f8;
      font-weight: bold;
      font-size: 18px;
    }

    .missing-image {
      background: #2d1b1b;
      border-color: #ff6b6b;
      position: relative;
    }

    .missing-image::after {
      content: "!";
      position: absolute;
      top: -2px;
      right: -2px;
      background: #ff6b6b;
      color: #f8f8f8;
      border-radius: 50%;
      width: 16px;
      height: 16px;
      font-size: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .cleanup-alert {
      background: #2d2416;
      border: 1px solid #ffc107;
      color: #ffc107;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .cleanup-btn {
      background: #ffc107;
      color: #000000;
      border: none;
      padding: 8px 16px;
      border-radius: 5px;
      cursor: pointer;
      font-weight: 600;
    }

    .cleanup-btn:hover {
      background: #e0a800;
    }

    .menu-type-filter-info {
      background: #1b2d1b;
      color: #4CAF50;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      border: 1px solid #4CAF50;
      text-align: center;
    }

    .admin-info {
      background: #1a1a1a;
      padding: 10px 15px;
      border-radius: 5px;
      margin-bottom: 20px;
      border: 1px solid #333;
      font-size: 14px;
      color: #ccc;
    }

    .session-info {
      float: right;
      font-size: 12px;
    }

    .rate-limit-info {
      background: #2a1a1a;
      border: 1px solid #664444;
      color: #ffaaaa;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 15px;
      font-size: 13px;
      text-align: center;
    }

    @media (max-width: 768px) {
      .search-controls {
        flex-direction: column;
        align-items: stretch;
      }

      .search-controls input,
      .search-controls select {
        min-width: auto;
        width: 100%;
      }

      .stats-row {
        flex-direction: column;
      }

      .cleanup-alert {
        flex-direction: column;
        gap: 10px;
        text-align: center;
      }

      .admin-info {
        text-align: center;
      }

      .session-info {
        float: none;
        margin-top: 5px;
      }
    }
  </style>
</head>

<body>
  <h1>🍽️ LivingRoom Control Panel</h1>

  <div class="container">
    <!-- Admin Session Info -->
    <div class="admin-info">
      <strong>Welcome, <?= htmlspecialchars(getCurrentAdmin()['username'], ENT_QUOTES, 'UTF-8') ?></strong>
      <span class="session-info">
        Session: <?= date('H:i', getCurrentAdmin()['login_time']) ?> |
        IP: <?= htmlspecialchars(getCurrentAdmin()['ip'], ENT_QUOTES, 'UTF-8') ?>
      </span>
    </div>

    <!-- Rate Limit Info -->
    <div class="rate-limit-info">
      🛡️ Security: All actions are logged and rate-limited for your protection
    </div>

    <!-- Success/Error Messages -->
    <?php if (!empty($success)): ?>
      <div style="background: #1b2d1b; color: #4CAF50; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #4CAF50;">
        ✅ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div style="background: #2d1b1b; color: #ff6b6b; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ff6b6b;">
        ⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
      <h2 style="margin: 0;">Menu Items Management</h2>
      <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="manage-menu-types.php" class="btn">🍴 Manage Menu Types</a>
        <a href="add.php" class="btn add-btn">➕ Add New Item</a>
        <a href="manage-categories.php" class="btn">📂 Manage Categories</a>
        <a href="logout.php" class="btn">🚪 Logout</a>
      </div>
    </div>

    <!-- Menu Type Filter Info -->
    <?php if ($selectedMenuType): ?>
      <?php
      $selectedMenuTypeName = '';
      foreach ($menuTypes as $mt) {
        if ($mt['id'] == $selectedMenuType) {
          $selectedMenuTypeName = $mt['name'];
          break;
        }
      }
      ?>
      <div class="menu-type-filter-info">
        🔍 Currently viewing: <strong><?= htmlspecialchars($selectedMenuTypeName, ENT_QUOTES, 'UTF-8') ?></strong>
        <a href="dashboard.php" style="color: #4CAF50; margin-left: 15px; text-decoration: underline;">View All Menu Types</a>
      </div>
    <?php endif; ?>

    <!-- Missing Images Alert -->
    <?php if ($missingImages > 0): ?>
      <div class="cleanup-alert" id="missingImagesAlert">
        <div>
          <strong>⚠️ Missing Images Detected</strong><br>
          <?= $missingImages ?> menu items reference images that don't exist in the uploads folder.
        </div>
        <button class="cleanup-btn" onclick="cleanupMissingImages()">🧹 Clean Up Database</button>
      </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-number" id="totalItems"><?= count($items) ?></div>
        <div class="stat-label">Total Items</div>
      </div>
      <?php
      $categoryStats = [];
      foreach ($items as $item) {
        $cat = $item['category'];
        if (!isset($categoryStats[$cat])) {
          $categoryStats[$cat] = 0;
        }
        $categoryStats[$cat]++;
      }
      foreach ($categoryStats as $cat => $count): ?>
        <div class="stat-card">
          <div class="stat-number" id="total<?= preg_replace('/[^a-zA-Z0-9]/', '', $cat) ?>"><?= $count ?></div>
          <div class="stat-label"><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Search and Filter Controls -->
    <div class="search-controls">
      <select id="menuTypeFilter">
        <option value="">All Menu Types</option>
        <?php foreach ($menuTypes as $menuType): ?>
          <option value="<?= $menuType['id'] ?>" <?= $selectedMenuType == $menuType['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($menuType['name'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>

      <input type="text" id="searchInput" placeholder="🔍 Search menu items..." maxlength="100">

      <select id="categoryFilter">
        <option value="">All Categories</option>
        <?php foreach ($categoryNames as $category): ?>
          <option value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>

      <button class="clear-btn" onclick="clearFilters()">🗑️ Clear Filters</button>
      <button class="clear-btn" onclick="refreshData()">🔄 Refresh</button>
    </div>

    <!-- Menu Items Table -->
    <div id="tableContainer">
      <?php if (empty($items)): ?>
        <div style="text-align: center; padding: 40px; color: #666;">
          <h3>No menu items yet</h3>
          <p>Start by <a href="manage-menu-types.php">creating menu types</a>, then <a href="manage-categories.php">creating categories</a>, then <a href="add.php">add your first dish</a>!</p>
        </div>
      <?php else: ?>
        <table id="menuTable">
          <thead>
            <tr>
              <th>Dish Name</th>
              <th>Description</th>
              <th>Menu Type</th>
              <th>Category</th>
              <th>Price</th>
              <th>Image</th>
              <th>Added</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="tableBody">
            <?php foreach ($items as $item): ?>
              <tr>
                <td style="font-weight: 600;"><?= htmlspecialchars($item['name'] ?? 'No Name', ENT_QUOTES, 'UTF-8') ?></td>
                <td style="font-style: italic; color: #ccc; max-width: 200px; word-wrap: break-word;">
                  <?= htmlspecialchars($item['description'] ?? '') ?: '<em>No description</em>' ?>
                </td>
                <td>
                  <span class="menu-type-badge">
                    <?= htmlspecialchars($item['menu_type'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </td>
                <td>
                  <span class="category-badge category-<?= strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $item['category'])) ?>">
                    <?= htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </td>
                <td class="price-display"><?= formatPrice($item['price']) ?></td>
                <td>
                  <?php if ($item['image']): ?>
                    <?php if (file_exists('../uploads/' . $item['image'])): ?>
                      <img src="../uploads/<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>" width="80" alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>" style="border-radius: 5px;">
                    <?php else: ?>
                      <div class="image-placeholder missing-image" title="Image file missing: <?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>">
                        IMG
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <div class="image-placeholder" title="No image uploaded">
                      IMG
                    </div>
                  <?php endif; ?>
                </td>
                <td style="color: #666; font-size: 12px;">
                  <?= date('M j, Y', strtotime($item['created_at'])) ?>
                </td>
                <td class="action-links">
                  <a href="edit.php?id=<?= $item['id'] ?>" class="edit">✏️ Edit</a>
                  <a href="delete.php?id=<?= $item['id'] ?>" class="delete"
                    onclick="return confirm('⚠️ Are you sure you want to delete <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>?')">
                    🗑️ Delete
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div style="margin-top: 20px; text-align: center; color: #666;">
          <p>Showing <span id="itemCount"><?= count($items) ?></span> items</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const menuTypeFilter = document.getElementById('menuTypeFilter');
    const tableBody = document.getElementById('tableBody');
    const tableContainer = document.getElementById('tableContainer');
    const itemCount = document.getElementById('itemCount');

    // Rate limiting for search requests
    let lastSearchTime = 0;
    const searchCooldown = 500; // 500ms between requests

    // Search and filter functionality with enhanced security
    function performSearch() {
      const now = Date.now();
      if (now - lastSearchTime < searchCooldown) {
        return;
      }
      lastSearchTime = now;

      const search = searchInput.value;
      const category = categoryFilter.value;
      const menuType = menuTypeFilter.value;

      // Validate input lengths
      if (search.length > 100) {
        alert('Search term too long');
        return;
      }

      // Show loading state
      tableBody.innerHTML = '<tr><td colspan="8" class="loading">🔍 Searching...</td></tr>';

      // Make AJAX request with error handling
      const params = new URLSearchParams({
        action: 'get_items',
        search: search,
        category: category,
        menu_type: menuType
      });

      fetch(`dashboard.php?${params}`)
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
          }
          return response.json();
        })
        .then(data => {
          if (data.error) {
            throw new Error(data.error);
          }
          updateTable(data);
          updateStats(data);
        })
        .catch(error => {
          console.error('Search error:', error);
          tableBody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: #e74c3c;">⚠️ Error loading data: ${escapeHtml(error.message)}</td></tr>`;
        });
    }

    function updateTable(items) {
      if (items.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="8" class="no-results">📭 No items found matching your criteria</td></tr>';
        itemCount.textContent = '0';
        return;
      }

      tableBody.innerHTML = items.map(item => {
        let imageHTML;
        if (item.image) {
          if (item.image_missing) {
            imageHTML = `<div class="image-placeholder missing-image" title="Image file missing">IMG</div>`;
          } else {
            imageHTML = `<img src="../uploads/${escapeHtml(item.image)}" width="80" alt="${escapeHtml(item.name)}" style="border-radius: 5px;" onerror="this.outerHTML='<div class=\\"image-placeholder missing-image\\" title=\\"Image file missing\\">IMG</div>'">`;
          }
        } else {
          imageHTML = '<div class="image-placeholder" title="No image uploaded">IMG</div>';
        }

        const description = item.description && item.description.trim() ?
          escapeHtml(item.description) :
          '<em>No description</em>';

        return `
      <tr>
        <td style="font-weight: 600;">${escapeHtml(item.name || 'No Name')}</td>
        <td style="font-style: italic; color: #ccc; max-width: 200px; word-wrap: break-word;">${description}</td>
        <td>
          <span class="menu-type-badge">
            ${escapeHtml(item.menu_type || 'Unknown')}
          </span>
        </td>
        <td>
          <span class="category-badge category-${item.category.toLowerCase().replace(/[^a-z0-9]/g, '')}">
            ${escapeHtml(item.category)}
          </span>
        </td>
        <td class="price-display">${formatPrice(item.price)}</td>
        <td>${imageHTML}</td>
        <td style="color: #666; font-size: 12px;">
          ${formatDate(item.created_at)}
        </td>
        <td class="action-links">
          <a href="edit.php?id=${item.id}" class="edit">✏️ Edit</a>
          <a href="delete.php?id=${item.id}" class="delete" 
             onclick="return confirm('⚠️ Are you sure you want to delete ${escapeHtml(item.name || 'this item')}?')">
             🗑️ Delete
          </a>
        </td>
      </tr>
    `;
      }).join('');

      itemCount.textContent = items.length;
    }

    function updateStats(items) {
      document.getElementById('totalItems').textContent = items.length;

      // Update dynamic category stats
      const categoryStats = {};
      items.forEach(item => {
        const cat = item.category;
        const cleanCat = cat.replace(/[^a-zA-Z0-9]/g, '');
        categoryStats[cleanCat] = (categoryStats[cleanCat] || 0) + 1;
      });

      // Update each category stat
      Object.keys(categoryStats).forEach(cleanCat => {
        const element = document.getElementById('total' + cleanCat);
        if (element) {
          element.textContent = categoryStats[cleanCat];
        }
      });

      // Reset categories not in current results to 0
      document.querySelectorAll('.stat-card .stat-number[id^="total"]:not(#totalItems)').forEach(el => {
        const catName = el.id.replace('total', '');
        if (!categoryStats[catName]) {
          el.textContent = '0';
        }
      });
    }

    function formatPrice(price) {
      const priceStr = price.toString();
      if (priceStr.includes('IQD')) {
        return priceStr;
      }
      const numPrice = parseFloat(priceStr);
      return isNaN(numPrice) ? priceStr : `IQD ${numPrice.toFixed(2)}`;
    }

    function formatDate(dateStr) {
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function clearFilters() {
      searchInput.value = '';
      categoryFilter.value = '';
      // Don't clear menu type filter - it's a main navigation choice
      performSearch();
    }

    function refreshData() {
      performSearch();
    }

    // Menu type filter change - reload page with new menu type
    menuTypeFilter.addEventListener('change', function() {
      const selectedMenuType = this.value;
      if (selectedMenuType) {
        window.location.href = `dashboard.php?menu_type=${selectedMenuType}`;
      } else {
        window.location.href = 'dashboard.php';
      }
    });

    // Cleanup missing images function with confirmation
    function cleanupMissingImages() {
      if (!confirm('🧹 This will remove image references for items where the image file is missing.\n\n⚠️ This action cannot be undone. Continue?')) {
        return;
      }

      fetch('dashboard.php?action=cleanup_missing_images')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(`✅ Successfully cleaned up ${data.updated} items with missing images.`);
            location.reload();
          } else {
            alert('❌ Error: ' + data.error);
          }
        })
        .catch(error => {
          console.error('Cleanup error:', error);
          alert('❌ Failed to cleanup missing images.');
        });
    }

    // Event listeners with input validation
    if (!searchInput.hasAttribute('data-listeners-attached')) {
      searchInput.addEventListener('input', function() {
        // Sanitize input
        this.value = this.value.replace(/[<>]/g, '');

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(performSearch, 800);
      });
      searchInput.setAttribute('data-listeners-attached', 'true');
    }

    if (!categoryFilter.hasAttribute('data-listeners-attached')) {
      categoryFilter.addEventListener('change', performSearch);
      categoryFilter.setAttribute('data-listeners-attached', 'true');
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
      // Ctrl/Cmd + K for search focus
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        searchInput.focus();
      }

      // Escape to clear search
      if (e.key === 'Escape' && document.activeElement === searchInput) {
        clearFilters();
      }
    });

    // Auto-hide success/error messages after 5 seconds
    setTimeout(() => {
      const messages = document.querySelectorAll('[style*="background: #1b2d1b"], [style*="background: #2d1b1b"]');
      messages.forEach(msg => {
        msg.style.transition = 'opacity 0.5s ease';
        msg.style.opacity = '0';
        setTimeout(() => msg.remove(), 500);
      });
    }, 5000);

    // Security: Prevent XSS in dynamic content
    document.addEventListener('DOMContentLoaded', function() {
      // Remove any potentially malicious scripts
      document.querySelectorAll('script[src]:not([src^="/"], [src^="./"], [src^="../"])').forEach(script => {
        script.remove();
      });
    });
  </script>

</body>

</html>

<?php
// Helper function for price formatting
function formatPrice($price)
{
  $priceStr = (string)$price;
  if (strpos($priceStr, 'IQD') !== false) {
    return $priceStr;
  }
  $numPrice = floatval($priceStr);
  return 'IQD ' . number_format($numPrice, 2);
}
?>