<?php
// rating.php - صفحة التقييم
session_start();

require_once 'admin/config.php';
require_once 'admin/db.php';

// الحصول على اللغة
$lang = $_SESSION['lang'] ?? 'en';

// الترجمات
$translations = [
    'ar' => [
        'title' => 'تقييم الخدمة',
        'subtitle' => 'شاركنا رأيك حول تجربتك',
        'service' => 'الخدمة',
        'staff' => 'الموظفين',
        'cleanliness' => 'النظافة',
        'overall' => 'التجربة الكلية',
        'name' => 'الاسم (اختياري)',
        'phone' => 'رقم الهاتف (اختياري)',
        'comment' => 'ملاحظات إضافية',
        'submit' => 'إرسال التقييم',
        'thank_you' => 'شكراً لك!',
        'success_msg' => 'تم إرسال تقييمك بنجاح',
        'error_msg' => 'حدث خطأ، يرجى المحاولة مرة أخرى',
        'back_menu' => 'العودة للقائمة',
        'bad' => 'سيء',
        'neutral' => 'متوسط',
        'good' => 'ممتاز'
    ],
    'en' => [
        'title' => 'Rate Our Service',
        'subtitle' => 'Share your experience with us',
        'service' => 'Service',
        'staff' => 'Staff',
        'cleanliness' => 'Cleanliness',
        'overall' => 'Overall Experience',
        'name' => 'Name (Optional)',
        'phone' => 'Phone (Optional)',
        'comment' => 'Additional Comments',
        'submit' => 'Submit Rating',
        'thank_you' => 'Thank You!',
        'success_msg' => 'Your rating has been submitted successfully',
        'error_msg' => 'An error occurred, please try again',
        'back_menu' => 'Back to Menu',
        'bad' => 'Bad',
        'neutral' => 'Neutral',
        'good' => 'Excellent'
    ],
    'ku' => [
        'title' => 'هەڵسەنگاندنی خزمەتگوزاری',
        'subtitle' => 'ئەزموونەکەت لەگەڵمان بەش بکە',
        'service' => 'خزمەتگوزاری',
        'staff' => 'کارمەندان',
        'cleanliness' => 'پاکیژەیی',
        'overall' => 'ئەزموونی گشتی',
        'name' => 'ناو (ئەختیاری)',
        'phone' => 'ژمارەی مۆبایل (ئەختیاری)',
        'comment' => 'تێبینییە زیادەکان',
        'submit' => 'ناردنی هەڵسەنگاندن',
        'thank_you' => 'سوپاس!',
        'success_msg' => 'هەڵسەنگاندنەکەت بە سەرکەوتوویی نێردرا',
        'error_msg' => 'هەڵەیەک ڕوویدا، تکایە دووبارە هەوڵبدەرەوە',
        'back_menu' => 'گەڕانەوە بۆ لیست',
        'bad' => 'خراپ',
        'neutral' => 'مامناوەند',
        'good' => 'نایاب'
    ]
];

$t = $translations[$lang];
$dir = ($lang === 'ar' || $lang === 'ku') ? 'rtl' : 'ltr';

$success = false;
$error = false;

// معالجة إرسال التقييم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    try {
        $service_rating = intval($_POST['service_rating'] ?? 0);
        $staff_rating = intval($_POST['staff_rating'] ?? 0);
        $cleanliness_rating = intval($_POST['cleanliness_rating'] ?? 0);
        $overall_experience = $_POST['overall_experience'] ?? '';
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        // التحقق من صحة البيانات
        if (
            $service_rating < 1 || $service_rating > 5 ||
            $staff_rating < 1 || $staff_rating > 5 ||
            $cleanliness_rating < 1 || $cleanliness_rating > 5
        ) {
            throw new Exception('Invalid rating values');
        }

        if (!in_array($overall_experience, ['bad', 'neutral', 'good'])) {
            throw new Exception('Invalid overall experience value');
        }

        // إدراج التقييم في قاعدة البيانات
        $stmt = $pdo->prepare("
            INSERT INTO ratings (
                customer_name, customer_phone, service_rating, 
                staff_rating, cleanliness_rating, overall_experience, comment
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $customer_name ?: null,
            $customer_phone ?: null,
            $service_rating,
            $staff_rating,
            $cleanliness_rating,
            $overall_experience,
            $comment ?: null
        ]);

        $success = true;
    } catch (Exception $e) {
        $error = true;
        logError("Rating submission failed", ['error' => $e->getMessage()]);
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" href="uploads/logo for menu.png" type="image/png" />

    <style>
        @font-face {
            font-family: 'CustomCalibri';
            src: url('uploads/CALIBRI.TTF') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'CustomArabic';
            src: url('uploads/ALL-GENDERS-LIGHT-V4.OTF') format('opentype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: <?= ($lang === 'ar' || $lang === 'ku') ? "'CustomArabic'" : "'CustomCalibri'" ?>, Arial, sans-serif;
            background: url('uploads/wallpaper menu copy.png') center/cover no-repeat fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 20px;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: -1;
        }

        .container {
            max-width: 600px;
            width: 100%;
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
            border-radius: 50%;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        h1 {
            color: #ffffff;
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
        }

        .rating-section {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .rating-label {
            color: #ffffff;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            display: block;
        }

        .stars {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .star {
            font-size: 36px;
            cursor: pointer;
            transition: all 0.2s ease;
            filter: grayscale(100%);
        }

        .star:hover,
        .star.active {
            filter: grayscale(0%);
            transform: scale(1.2);
        }

        .emoji-options {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
        }

        .emoji-option {
            cursor: pointer;
            transition: all 0.3s ease;
            opacity: 0.5;
        }

        .emoji-option input[type="radio"] {
            display: none;
        }

        .emoji-option label {
            cursor: pointer;
            font-size: 48px;
            display: block;
            transition: all 0.3s ease;
        }

        .emoji-option input[type="radio"]:checked+label {
            transform: scale(1.3);
        }

        .emoji-option:hover label {
            transform: scale(1.2);
        }

        .emoji-option input[type="radio"]:checked~.emoji-text,
        .emoji-option:hover .emoji-text {
            opacity: 1;
        }

        .emoji-text {
            color: #ffffff;
            font-size: 14px;
            text-align: center;
            margin-top: 5px;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #ffffff;
            font-size: 16px;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            color: #ffffff;
            font-size: 16px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.6);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .success-message,
        .error-message {
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 20px;
            animation: slideIn 0.5s ease-in-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-message {
            background: rgba(34, 197, 94, 0.2);
            border: 2px solid rgba(34, 197, 94, 0.5);
            color: #ffffff;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.2);
            border: 2px solid rgba(239, 68, 68, 0.5);
            color: #ffffff;
        }

        .success-icon,
        .error-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 25px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            color: #ffffff;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .container {
                padding: 25px 20px;
            }

            h1 {
                font-size: 24px;
            }

            .star {
                font-size: 30px;
            }

            .emoji-option label {
                font-size: 40px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <?php if ($success): ?>
            <!-- رسالة النجاح -->
            <div class="success-message">
                <div class="success-icon">✅</div>
                <h2><?= htmlspecialchars($t['thank_you'], ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars($t['success_msg'], ENT_QUOTES, 'UTF-8') ?></p>
                <a href="index.php" class="back-link"><?= htmlspecialchars($t['back_menu'], ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        <?php elseif ($error): ?>
            <!-- رسالة الخطأ -->
            <div class="error-message">
                <div class="error-icon">❌</div>
                <p><?= htmlspecialchars($t['error_msg'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
            <!-- رأس الصفحة -->
            <div class="header">
                <div class="logo">
                    <img src="uploads/logo for menu.png" alt="Logo" />
                </div>
                <h1><?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="subtitle"><?= htmlspecialchars($t['subtitle'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <!-- نموذج التقييم -->
            <form method="post" id="ratingForm">
                <!-- تقييم الخدمة -->
                <div class="rating-section">
                    <label class="rating-label"><?= htmlspecialchars($t['service'], ENT_QUOTES, 'UTF-8') ?></label>
                    <div class="stars" data-rating-name="service_rating">
                        <span class="star" data-value="1">⭐</span>
                        <span class="star" data-value="2">⭐</span>
                        <span class="star" data-value="3">⭐</span>
                        <span class="star" data-value="4">⭐</span>
                        <span class="star" data-value="5">⭐</span>
                    </div>
                    <input type="hidden" name="service_rating" required>
                </div>

                <!-- تقييم الموظفين -->
                <div class="rating-section">
                    <label class="rating-label"><?= htmlspecialchars($t['staff'], ENT_QUOTES, 'UTF-8') ?></label>
                    <div class="stars" data-rating-name="staff_rating">
                        <span class="star" data-value="1">⭐</span>
                        <span class="star" data-value="2">⭐</span>
                        <span class="star" data-value="3">⭐</span>
                        <span class="star" data-value="4">⭐</span>
                        <span class="star" data-value="5">⭐</span>
                    </div>
                    <input type="hidden" name="staff_rating" required>
                </div>

                <!-- تقييم النظافة -->
                <div class="rating-section">
                    <label class="rating-label"><?= htmlspecialchars($t['cleanliness'], ENT_QUOTES, 'UTF-8') ?></label>
                    <div class="stars" data-rating-name="cleanliness_rating">
                        <span class="star" data-value="1">⭐</span>
                        <span class="star" data-value="2">⭐</span>
                        <span class="star" data-value="3">⭐</span>
                        <span class="star" data-value="4">⭐</span>
                        <span class="star" data-value="5">⭐</span>
                    </div>
                    <input type="hidden" name="cleanliness_rating" required>
                </div>

                <!-- التجربة الكلية -->
                <div class="rating-section">
                    <label class="rating-label"><?= htmlspecialchars($t['overall'], ENT_QUOTES, 'UTF-8') ?></label>
                    <div class="emoji-options">
                        <div class="emoji-option">
                            <input type="radio" name="overall_experience" value="bad" id="bad" required>
                            <label for="bad">😞</label>
                            <div class="emoji-text"><?= htmlspecialchars($t['bad'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="overall_experience" value="neutral" id="neutral">
                            <label for="neutral">😐</label>
                            <div class="emoji-text"><?= htmlspecialchars($t['neutral'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="emoji-option">
                            <input type="radio" name="overall_experience" value="good" id="good">
                            <label for="good">😊</label>
                            <div class="emoji-text"><?= htmlspecialchars($t['good'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                </div>

                <!-- معلومات اختيارية -->
                <div class="form-group">
                    <label><?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="text" name="customer_name" maxlength="100">
                </div>

                <div class="form-group">
                    <label><?= htmlspecialchars($t['phone'], ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="tel" name="customer_phone" maxlength="20">
                </div>

                <div class="form-group">
                    <label><?= htmlspecialchars($t['comment'], ENT_QUOTES, 'UTF-8') ?></label>
                    <textarea name="comment" maxlength="500"></textarea>
                </div>

                <!-- زر الإرسال -->
                <button type="submit" name="submit_rating" class="submit-btn">
                    <?= htmlspecialchars($t['submit'], ENT_QUOTES, 'UTF-8') ?>
                </button>
            </form>

            <div style="text-align: center;">
                <a href="index.php" class="back-link"><?= htmlspecialchars($t['back_menu'], ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // معالجة تقييم النجوم
        document.querySelectorAll('.stars').forEach(starsContainer => {
            const stars = starsContainer.querySelectorAll('.star');
            const ratingName = starsContainer.dataset.ratingName;
            const hiddenInput = document.querySelector(`input[name="${ratingName}"]`);

            stars.forEach((star, index) => {
                star.addEventListener('click', () => {
                    const value = star.dataset.value;
                    hiddenInput.value = value;

                    // تحديث مظهر النجوم
                    stars.forEach((s, i) => {
                        if (i < value) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });
            });
        });

        // التحقق من صحة النموذج قبل الإرسال
        document.getElementById('ratingForm').addEventListener('submit', (e) => {
            const serviceRating = document.querySelector('input[name="service_rating"]').value;
            const staffRating = document.querySelector('input[name="staff_rating"]').value;
            const cleanlinessRating = document.querySelector('input[name="cleanliness_rating"]').value;
            const overallExperience = document.querySelector('input[name="overall_experience"]:checked');

            if (!serviceRating || !staffRating || !cleanlinessRating || !overallExperience) {
                e.preventDefault();
                alert('<?= $lang === 'ar' ? 'يرجى إكمال جميع التقييمات' : ($lang === 'ku' ? 'تکایە هەموو هەڵسەنگاندنەکان تەواو بکە' : 'Please complete all ratings') ?>');
            }
        });
    </script>
</body>

</html>