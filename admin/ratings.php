<?php
// admin/ratings.php - صفحة عرض التقييمات
require_once 'session.php';
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

if (!function_exists('requireAdmin')) {
    /**
     * Basic fallback requireAdmin implementation:
     * Ensures a session is active and that the current user has role 'admin'.
     * If not, redirect to login (or you can change to a 403 page).
     */
    function requireAdmin() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $userRole = $_SESSION['user']['role'] ?? null;
        if (empty($_SESSION['user']) || $userRole !== 'admin') {
            header('Location: login.php');
            exit;
        }
    }
}
requireAdmin();

$success = '';
$error = '';

// معالجة حذف تقييم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_rating'])) {
    try {
        validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '');

        $ratingId = intval($_POST['rating_id']);

        $stmt = $pdo->prepare("DELETE FROM ratings WHERE id = ?");
        $stmt->execute([$ratingId]);

        $success = "Rating deleted successfully";
        logAudit("Rating deleted", ['rating_id' => $ratingId]);
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError("Rating deletion failed", ['error' => $e->getMessage()]);
    }
}

// الحصول على الفلاتر
$experienceFilter = $_GET['experience'] ?? 'all';
$dateFilter = $_GET['date'] ?? 'all';

// بناء الاستعلام
$query = "SELECT * FROM ratings WHERE 1=1";
$params = [];

if ($experienceFilter !== 'all') {
    $query .= " AND overall_experience = ?";
    $params[] = $experienceFilter;
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
$ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// حساب الإحصائيات
$statsQuery = "SELECT 
    COUNT(*) as total_ratings,
    AVG(service_rating) as avg_service,
    AVG(staff_rating) as avg_staff,
    AVG(cleanliness_rating) as avg_cleanliness,
    SUM(CASE WHEN overall_experience = 'good' THEN 1 ELSE 0 END) as good_exp,
    SUM(CASE WHEN overall_experience = 'neutral' THEN 1 ELSE 0 END) as neutral_exp,
    SUM(CASE WHEN overall_experience = 'bad' THEN 1 ELSE 0 END) as bad_exp
FROM ratings";
$stats = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);

$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Ratings - Restaurant Admin</title>
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

        .rating-stars {
            color: #fbbf24;
            font-size: 18px;
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

        .ratings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .rating-card {
            background: #000000;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid rgba(248, 248, 248, 0.1);
            transition: all 0.3s;
        }

        .rating-card:hover {
            border-color: #ef4444;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2);
        }

        .rating-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #222;
        }

        .customer-info {
            flex: 1;
        }

        .customer-name {
            color: #f8f8f8;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .customer-phone {
            color: #999;
            font-size: 14px;
        }

        .rating-date {
            color: #999;
            font-size: 12px;
        }

        .rating-scores {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }

        .score-item {
            background: #1a1a1a;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
        }

        .score-label {
            color: #999;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .score-value {
            color: #fbbf24;
            font-size: 16px;
            font-weight: 600;
        }

        .overall-experience {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: #1a1a1a;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .experience-emoji {
            font-size: 32px;
        }

        .experience-text {
            flex: 1;
        }

        .experience-label {
            color: #999;
            font-size: 12px;
        }

        .experience-value {
            color: #f8f8f8;
            font-size: 16px;
            font-weight: 600;
        }

        .experience-good {
            border-left: 3px solid #22c55e;
        }

        .experience-neutral {
            border-left: 3px solid #fbbf24;
        }

        .experience-bad {
            border-left: 3px solid #ef4444;
        }

        .rating-comment {
            background: #1a1a1a;
            padding: 12px;
            border-radius: 8px;
            color: #ccc;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
            font-style: italic;
        }

        .rating-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-delete {
            padding: 8px 16px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .ratings-grid {
                grid-template-columns: 1fr;
            }

            .filters {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            .rating-scores {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="admin-container">
        <h1>⭐ Customer Ratings & Reviews</h1>

        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <!-- الإحصائيات -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_ratings'] ?></div>
                <div class="stat-label">Total Ratings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['avg_service'], 1) ?>/5</div>
                <div class="stat-label">Avg Service Rating</div>
                <div class="rating-stars">
                    <?php
                    $stars = round($stats['avg_service']);
                    for ($i = 1; $i <= 5; $i++) {
                        echo $i <= $stars ? '⭐' : '☆';
                    }
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['avg_staff'], 1) ?>/5</div>
                <div class="stat-label">Avg Staff Rating</div>
                <div class="rating-stars">
                    <?php
                    $stars = round($stats['avg_staff']);
                    for ($i = 1; $i <= 5; $i++) {
                        echo $i <= $stars ? '⭐' : '☆';
                    }
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['avg_cleanliness'], 1) ?>/5</div>
                <div class="stat-label">Avg Cleanliness</div>
                <div class="rating-stars">
                    <?php
                    $stars = round($stats['avg_cleanliness']);
                    for ($i = 1; $i <= 5; $i++) {
                        echo $i <= $stars ? '⭐' : '☆';
                    }
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['good_exp'] ?></div>
                <div class="stat-label">😊 Excellent Experience</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['neutral_exp'] ?></div>
                <div class="stat-label">😐 Neutral Experience</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['bad_exp'] ?></div>
                <div class="stat-label">😞 Bad Experience</div>
            </div>
        </div>

        <!-- الفلاتر -->
        <form method="get" class="filters">
            <div class="filter-group">
                <label>Experience Filter</label>
                <select name="experience" onchange="this.form.submit()">
                    <option value="all" <?= $experienceFilter === 'all' ? 'selected' : '' ?>>All Experiences</option>
                    <option value="good" <?= $experienceFilter === 'good' ? 'selected' : '' ?>>😊 Excellent</option>
                    <option value="neutral" <?= $experienceFilter === 'neutral' ? 'selected' : '' ?>>😐 Neutral</option>
                    <option value="bad" <?= $experienceFilter === 'bad' ? 'selected' : '' ?>>😞 Bad</option>
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

        <!-- شبكة التقييمات -->
        <?php if (empty($ratings)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📝</div>
                <h3>No Ratings Yet</h3>
                <p>Customer ratings will appear here once submitted</p>
            </div>
        <?php else: ?>
            <div class="ratings-grid">
                <?php foreach ($ratings as $rating): ?>
                    <div class="rating-card">
                        <!-- رأس البطاقة -->
                        <div class="rating-header">
                            <div class="customer-info">
                                <div class="customer-name">
                                    <?= $rating['customer_name'] ? htmlspecialchars($rating['customer_name'], ENT_QUOTES, 'UTF-8') : 'Anonymous' ?>
                                </div>
                                <?php if ($rating['customer_phone']): ?>
                                    <div class="customer-phone">
                                        📱 <?= htmlspecialchars($rating['customer_phone'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="rating-date">
                                <?= date('Y-m-d H:i', strtotime($rating['created_at'])) ?>
                            </div>
                        </div>

                        <!-- التقييمات -->
                        <div class="rating-scores">
                            <div class="score-item">
                                <div class="score-label">Service</div>
                                <div class="score-value"><?= $rating['service_rating'] ?>/5 ⭐</div>
                            </div>
                            <div class="score-item">
                                <div class="score-label">Staff</div>
                                <div class="score-value"><?= $rating['staff_rating'] ?>/5 ⭐</div>
                            </div>
                            <div class="score-item">
                                <div class="score-label">Cleanliness</div>
                                <div class="score-value"><?= $rating['cleanliness_rating'] ?>/5 ⭐</div>
                            </div>
                        </div>

                        <!-- التجربة الكلية -->
                        <div class="overall-experience experience-<?= $rating['overall_experience'] ?>">
                            <div class="experience-emoji">
                                <?php
                                $emoji = $rating['overall_experience'] === 'good' ? '😊' : ($rating['overall_experience'] === 'neutral' ? '😐' : '😞');
                                echo $emoji;
                                ?>
                            </div>
                            <div class="experience-text">
                                <div class="experience-label">Overall Experience</div>
                                <div class="experience-value"><?= ucfirst($rating['overall_experience']) ?></div>
                            </div>
                        </div>

                        <!-- التعليق -->
                        <?php if ($rating['comment']): ?>
                            <div class="rating-comment">
                                "<?= htmlspecialchars($rating['comment'], ENT_QUOTES, 'UTF-8') ?>"
                            </div>
                        <?php endif; ?>

                        <!-- الإجراءات -->
                        <div class="rating-actions">
                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this rating?');">
                                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                                <input type="hidden" name="rating_id" value="<?= $rating['id'] ?>">
                                <button type="submit" name="delete_rating" class="btn-delete">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>