<?php
// admin/orders.php - صفحة إدارة الطلبات
require_once 'session.php';
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

// Define logAudit if not already defined
if (!function_exists('logAudit')) {
    function logAudit($action, $details = []) {
        // Example implementation: log to a file
        $logEntry = date('Y-m-d H:i:s') . " | $action | " . json_encode($details) . PHP_EOL;
        file_put_contents(__DIR__ . '/audit.log', $logEntry, FILE_APPEND);
    }
}

requireAdmin();

$success = '';
$error = '';

// معالجة تحديث حالة الطلب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '');

        $orderId = intval($_POST['order_id']);
        $newStatus = $_POST['status'];

        $allowedStatuses = ['pending', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled'];
        if (!in_array($newStatus, $allowedStatuses)) {
            throw new Exception('Invalid status');
        }

        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);

        $success = "Order status updated successfully";
        logAudit("Order status updated", ['order_id' => $orderId, 'new_status' => $newStatus]);
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError("Order status update failed", ['error' => $e->getMessage()]);
    }
}

// معالجة حذف طلب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    try {
        validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '');

        $orderId = intval($_POST['order_id']);

        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);

        $success = "Order deleted successfully";
        logAudit("Order deleted", ['order_id' => $orderId]);
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError("Order deletion failed", ['error' => $e->getMessage()]);
    }
}

// الحصول على الفلاتر
$statusFilter = $_GET['status'] ?? 'all';
$dateFilter = $_GET['date'] ?? 'all';
$orderTypeFilter = $_GET['order_type'] ?? 'all';

// بناء الاستعلام
$query = "SELECT * FROM orders WHERE 1=1";
$params = [];

if ($statusFilter !== 'all') {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
}

if ($orderTypeFilter !== 'all') {
    $query .= " AND order_type = ?";
    $params[] = $orderTypeFilter;
}

if ($dateFilter !== 'all') {
    switch ($dateFilter) {
        case 'today':
            $query .= " AND DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// حساب الإحصائيات
$statsQuery = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_amount ELSE 0 END) as today_revenue,
    SUM(total_amount) as total_revenue
FROM orders";
$stats = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);

$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Restaurant Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #000000;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid rgba(248, 248, 248, 0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #ef4444;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #ccc;
            font-size: 14px;
        }

        .filters {
            background: #000000;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            color: #f8f8f8;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .filter-group select {
            width: 100%;
            padding: 8px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 5px;
            color: #f8f8f8;
        }

        .orders-grid {
            display: grid;
            gap: 20px;
        }

        .order-card {
            background: #000000;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid rgba(248, 248, 248, 0.1);
            transition: all 0.3s;
        }

        .order-card:hover {
            border-color: #ef4444;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #222;
        }

        .order-id {
            color: #ef4444;
            font-size: 18px;
            font-weight: 600;
        }

        .order-date {
            color: #999;
            font-size: 14px;
        }

        .customer-info {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .customer-name {
            color: #f8f8f8;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .customer-details {
            color: #999;
            font-size: 14px;
        }

        .order-items {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .order-items-title {
            color: #f8f8f8;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #222;
            color: #ccc;
            font-size: 14px;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-name {
            flex: 1;
        }

        .item-qty {
            color: #999;
            margin: 0 15px;
        }

        .item-price {
            color: #ef4444;
            font-weight: 600;
        }

        .order-total {
            background: #1a1a1a;
            padding: 12px 15px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .total-label {
            color: #f8f8f8;
            font-size: 16px;
            font-weight: 600;
        }

        .total-amount {
            color: #ef4444;
            font-size: 20px;
            font-weight: bold;
        }

        .order-status-form {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 15px;
        }

        .status-select {
            flex: 1;
            padding: 10px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 5px;
            color: #f8f8f8;
            font-size: 14px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }

        .status-confirmed {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .status-preparing {
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
        }

        .status-ready {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .status-delivered {
            background: rgba(34, 197, 94, 0.3);
            color: #22c55e;
            border: 1px solid #22c55e;
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .order-type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 10px;
        }

        .type-delivery {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .type-dine-in {
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
        }

        .order-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-update {
            background: #3b82f6;
            color: white;
        }

        .btn-update:hover {
            background: #2563eb;
        }

        .btn-delete {
            background: #ef4444;
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .btn-whatsapp {
            background: #25d366;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-whatsapp:hover {
            background: #20ba5a;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #000000;
            border-radius: 10px;
            border: 1px solid rgba(248, 248, 248, 0.1);
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #f8f8f8;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filters {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            .order-status-form {
                flex-direction: column;
            }

            .status-select {
                width: 100%;
            }

            .order-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>📦 Orders Management</h1>
            <div style="display: flex; gap: 10px;">
                <a href="dashboard.php" class="btn" style="background: #666; color: white;">← Back to Dashboard</a>
                <a href="ratings.php" class="btn" style="background: #fbbf24; color: #000;">⭐ View Ratings</a>
                <a href="logout.php" class="btn" style="background: #ef4444; color: white;">🚪 Logout</a>
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

        <!-- الإحصائيات -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_orders'] ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['pending_orders'] ?></div>
                <div class="stat-label">Pending Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['delivered_orders'] ?></div>
                <div class="stat-label">Delivered Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['today_revenue']) ?> IQD</div>
                <div class="stat-label">Today's Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['total_revenue']) ?> IQD</div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <!-- الفلاتر -->
        <form method="get" class="filters">
            <div class="filter-group">
                <label>Status Filter</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="preparing" <?= $statusFilter === 'preparing' ? 'selected' : '' ?>>Preparing</option>
                    <option value="ready" <?= $statusFilter === 'ready' ? 'selected' : '' ?>>Ready</option>
                    <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Order Type</label>
                <select name="order_type" onchange="this.form.submit()">
                    <option value="all" <?= $orderTypeFilter === 'all' ? 'selected' : '' ?>>All Types</option>
                    <option value="delivery" <?= $orderTypeFilter === 'delivery' ? 'selected' : '' ?>>Delivery</option>
                    <option value="dine-in" <?= $orderTypeFilter === 'dine-in' ? 'selected' : '' ?>>Dine-In</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Date Filter</label>
                <select name="date" onchange="this.form.submit()">
                    <option value="all" <?= $dateFilter === 'all' ? 'selected' : '' ?>>All Time</option>
                    <option value="today" <?= $dateFilter === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="week" <?= $dateFilter === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="month" <?= $dateFilter === 'month' ? 'selected' : '' ?>>Last 30 Days</option>
                </select>
            </div>
        </form>

        <!-- شبكة الطلبات -->
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📦</div>
                <h3>No Orders Yet</h3>
                <p>Orders will appear here once customers place them</p>
            </div>
        <?php else: ?>
            <div class="orders-grid">
                <?php foreach ($orders as $order):
                    $items = json_decode($order['items'], true);
                ?>
                    <div class="order-card">
                        <!-- رأس الطلب -->
                        <div class="order-header">
                            <div>
                                <div class="order-id">Order #<?= $order['id'] ?></div>
                                <div class="order-date"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></div>
                            </div>
                            <div style="text-align: right;">
                                <span class="status-badge status-<?= $order['status'] ?>">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                                <span class="order-type-badge type-<?= $order['order_type'] ?>">
                                    <?= ucfirst($order['order_type']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- معلومات العميل -->
                        <div class="customer-info">
                            <div class="customer-name">👤 <?= htmlspecialchars($order['customer_name']) ?></div>
                            <div class="customer-details">
                                📞 <?= htmlspecialchars($order['customer_phone']) ?>
                                <?php if ($order['customer_address']): ?>
                                    <br>📍 <?= htmlspecialchars($order['customer_address']) ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- المنتجات المطلوبة -->
                        <div class="order-items">
                            <div class="order-items-title">Ordered Items:</div>
                            <?php if ($items): ?>
                                <?php foreach ($items as $item): ?>
                                    <div class="order-item">
                                        <span class="item-name"><?= htmlspecialchars($item['name']) ?></span>
                                        <span class="item-qty">x<?= $item['quantity'] ?></span>
                                        <span class="item-price"><?= number_format($item['price'] * $item['quantity']) ?> IQD</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- المجموع -->
                        <div class="order-total">
                            <span class="total-label">Total Amount:</span>
                            <span class="total-amount"><?= number_format($order['total_amount']) ?> IQD</span>
                        </div>

                        <?php if ($order['notes']): ?>
                            <div style="background: #1a1a1a; padding: 12px; border-radius: 8px; margin-bottom: 15px;">
                                <div style="color: #999; font-size: 12px; margin-bottom: 5px;">Notes:</div>
                                <div style="color: #ccc; font-size: 14px;"><?= htmlspecialchars($order['notes']) ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- تحديث الحالة -->
                        <form method="POST" class="order-status-form">
                            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <select name="status" class="status-select">
                                <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="confirmed" <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                <option value="preparing" <?= $order['status'] === 'preparing' ? 'selected' : '' ?>>Preparing</option>
                                <option value="ready" <?= $order['status'] === 'ready' ? 'selected' : '' ?>>Ready</option>
                                <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                            <button type="submit" name="update_status" class="btn btn-update">Update</button>
                        </form>

                        <!-- الإجراءات -->
                        <div class="order-actions">
                            <?php
                            $whatsappMessage = "Hello {$order['customer_name']}, your order #{$order['id']} status has been updated to: " . ucfirst($order['status']);
                            $whatsappLink = "https://wa.me/" . preg_replace('/[^0-9]/', '', $order['customer_phone']) . "?text=" . urlencode($whatsappMessage);
                            ?>
                            <a href="<?= $whatsappLink ?>" target="_blank" class="btn btn-whatsapp">
                                📱 WhatsApp Customer
                            </a>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this order?');" style="flex: 1;">
                                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <button type="submit" name="delete_order" class="btn btn-delete" style="width: 100%;">🗑️ Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>