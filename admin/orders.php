<?php
// admin/orders.php - صفحة إدارة الطلبات
require_once 'session.php';
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

if (!function_exists('logAudit')) {
    /**
     * Fallback audit logger used when no logAudit implementation is present.
     * Writes a JSON line to admin/audit.log with time, message and metadata.
     *
     * @param string $message
     * @param array $meta
     * @return void
     */
    function logAudit($message, $meta = []) {
        $entry = [
            'time' => date('c'),
            'message' => $message,
            'meta' => $meta
        ];
        // ensure directory is writable; write to a logfile next to this script
        $logFile = __DIR__ . '/audit.log';
        error_log(json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, 3, $logFile);
    }
}

// التحقق من صلاحيات الأدمن
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
            throw new Exception('Invalid status value');
        }

        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);

        $success = "Order status updated successfully";
        logAudit("Order status updated", [
            'order_id' => $orderId,
            'new_status' => $newStatus
        ]);
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
            min-width: 150px;
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

        .orders-table {
            background: #000000;
            border-radius: 10px;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #1a1a1a;
            color: #f8f8f8;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #333;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #222;
            color: #ccc;
        }

        tr:hover {
            background: #1a1a1a;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background: #fbbf24;
            color: #000;
        }

        .status-confirmed {
            background: #3b82f6;
            color: #fff;
        }

        .status-preparing {
            background: #8b5cf6;
            color: #fff;
        }

        .status-ready {
            background: #10b981;
            color: #fff;
        }

        .status-delivered {
            background: #22c55e;
            color: #fff;
        }

        .status-cancelled {
            background: #ef4444;
            color: #fff;
        }

        .order-type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }

        .order-delivery {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid #ef4444;
        }

        .order-dine-in {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
            border: 1px solid #22c55e;
        }

        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-view {
            background: #3b82f6;
            color: white;
        }

        .btn-view:hover {
            background: #2563eb;
        }

        .btn-delete {
            background: #ef4444;
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .status-select {
            padding: 6px 10px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 5px;
            color: #f8f8f8;
            font-size: 13px;
        }

        .order-details-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: #1a1a1a;
            padding: 30px;
            border-radius: 15px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            border: 1px solid #333;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #333;
        }

        .modal-close {
            font-size: 28px;
            cursor: pointer;
            color: #999;
            transition: color 0.3s;
        }

        .modal-close:hover {
            color: #fff;
        }

        .order-info-group {
            margin-bottom: 20px;
        }

        .order-info-label {
            color: #999;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .order-info-value {
            color: #f8f8f8;
            font-size: 16px;
            font-weight: 500;
        }

        .order-items-list {
            background: #000;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            table {
                font-size: 13px;
            }

            th,
            td {
                padding: 10px 8px;
            }

            .action-btns {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="admin-container">
        <h1>📦 Orders Management</h1>

        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
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
                <div class="stat-number">IQD <?= number_format($stats['today_revenue'], 2) ?></div>
                <div class="stat-label">Today's Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">IQD <?= number_format($stats['total_revenue'], 2) ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <!-- الفلاتر -->
        <form method="get" class="filters">
            <div class="filter-group">
                <label>Status Filter</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
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

        <!-- جدول الطلبات -->
        <div class="orders-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                No orders found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?= $order['id'] ?></td>
                                <td><?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($order['customer_phone'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="order-type-badge order-<?= $order['order_type'] ?>">
                                        <?= ucfirst(str_replace('-', ' ', $order['order_type'])) ?>
                                    </span>
                                </td>
                                <td>IQD <?= number_format($order['total_amount'], 2) ?></td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <select name="status" class="status-select" onchange="this.form.submit()">
                                            <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="confirmed" <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                            <option value="preparing" <?= $order['status'] === 'preparing' ? 'selected' : '' ?>>Preparing</option>
                                            <option value="ready" <?= $order['status'] === 'ready' ? 'selected' : '' ?>>Ready</option>
                                            <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                            <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </td>
                                <td><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn-small btn-view" onclick="viewOrder(<?= htmlspecialchars(json_encode($order), ENT_QUOTES, 'UTF-8') ?>)">
                                            View
                                        </button>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this order?');">
                                            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <button type="submit" name="delete_order" class="btn-small btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- مودال تفاصيل الطلب -->
    <div id="orderModal" class="order-details-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Order Details</h2>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <div id="modalBody"></div>
        </div>
    </div>

    <script>
        function viewOrder(order) {
            const modal = document.getElementById('orderModal');
            const modalBody = document.getElementById('modalBody');

            const items = JSON.parse(order.order_items);
            let itemsHTML = '<ul style="list-style: none; padding: 0;">';
            items.forEach(item => {
                itemsHTML += `<li style="padding: 8px 0; border-bottom: 1px solid #333;">
                    <strong>${item.name}</strong> × ${item.quantity} = IQD ${item.price * item.quantity}
                </li>`;
            });
            itemsHTML += '</ul>';

            modalBody.innerHTML = `
                <div class="order-info-group">
                    <div class="order-info-label">Order ID</div>
                    <div class="order-info-value">#${order.id}</div>
                </div>
                <div class="order-info-group">
                    <div class="order-info-label">Customer Name</div>
                    <div class="order-info-value">${order.customer_name}</div>
                </div>
                <div class="order-info-group">
                    <div class="order-info-label">Phone</div>
                    <div class="order-info-value">${order.customer_phone}</div>
                </div>
                ${order.customer_address ? `
                <div class="order-info-group">
                    <div class="order-info-label">Address</div>
                    <div class="order-info-value">${order.customer_address}</div>
                </div>
                ` : ''}
                <div class="order-info-group">
                    <div class="order-info-label">Order Type</div>
                    <div class="order-info-value">${order.order_type}</div>
                </div>
                <div class="order-info-group">
                    <div class="order-info-label">Status</div>
                    <div class="order-info-value">
                        <span class="status-badge status-${order.status}">${order.status}</span>
                    </div>
                </div>
                <div class="order-info-group">
                    <div class="order-info-label">Order Items</div>
                    <div class="order-items-list">${itemsHTML}</div>
                </div>
                <div class="order-info-group">
                    <div class="order-info-label">Total Amount</div>
                    <div class="order-info-value" style="color: #ef4444; font-size: 20px;">IQD ${order.total_amount}</div>
                </div>
                ${order.notes ? `
                <div class="order-info-group">
                    <div class="order-info-label">Notes</div>
                    <div class="order-info-value">${order.notes}</div>
                </div>
                ` : ''}
                <div class="order-info-group">
                    <div class="order-info-label">Order Date</div>
                    <div class="order-info-value">${order.created_at}</div>
                </div>
            `;

            modal.style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('orderModal').style.display = 'none';
        }

        // إغلاق المودال عند الضغط خارجه
        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>

</html>