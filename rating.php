<?php
// rating.php - صفحة تقييم العملاء
session_start();
require_once 'admin/config.php';
require_once 'admin/db.php';
require_once 'admin/functions.php';

$selectedLang = $_SESSION['lang'] ?? 'en';
$direction = ($selectedLang === 'ar' || $selectedLang === 'ku') ? 'rtl' : 'ltr';
$fontClass = ($selectedLang === 'ar') ? 'CustomArabic' : 'CustomCalibri';

$success = '';
$error = '';

// معالجة إرسال التقييم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    try {
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $serviceRating = intval($_POST['service_rating'] ?? 0);
        $staffRating = intval($_POST['staff_rating'] ?? 0);
        $cleanlinessRating = intval($_POST['cleanliness_rating'] ?? 0);
        $overallExperience = $_POST['overall_experience'] ?? '';
        $comments = trim($_POST['comments'] ?? '');
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        // التحقق من البيانات
        if (empty($customerName)) {
            throw new Exception('الرجاء إدخال الاسم');
        }

        if ($serviceRating < 1 || $serviceRating > 5 || 
            $staffRating < 1 || $staffRating > 5 || 
            $cleanlinessRating < 1 || $cleanlinessRating > 5) {
            throw new Exception('الرجاء تحديد تقييم صحيح');
        }

        if (!in_array($overallExperience, ['good', 'neutral', 'bad'])) {
            throw new Exception('الرجاء اختيار التجربة العامة');
        }

        // إدراج التقييم في قاعدة البيانات
        $stmt = $pdo->prepare("
            INSERT INTO ratings (
                customer_name, customer_phone, service_rating, staff_rating, 
                cleanliness_rating, overall_experience, comments, ip_address
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $customerName,
            $customerPhone,
            $serviceRating,
            $staffRating,
            $cleanlinessRating,
            $overallExperience,
            $comments,
            $ipAddress
        ]);

        $success = true;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// النصوص متعددة اللغات
$translations = [
    'ar' => [
        'title' => 'قيّم تجربتك',
        'subtitle' => 'رأيك يهمنا! ساعدنا في تحسين خدماتنا',
        'name_label' => 'الاسم',
        'name_placeholder' => 'اسمك الكريم',
        'phone_label' => 'رقم الهاتف (اختياري)',
        'phone_placeholder' => '07xxxxxxxxx',
        'service_label' => 'تقييم الخدمة',
        'staff_label' => 'تقييم الموظفين',
        'cleanliness_label' => 'تقييم النظافة',
        'experience_label' => 'كيف كانت تجربتك الإجمالية؟',
        'excellent' => 'ممتازة',
        'neutral' => 'جيدة',
        'bad' => 'سيئة',
        'comments_label' => 'تعليقات إضافية (اختياري)',
        'comments_placeholder' => 'شاركنا ملاحظاتك واقتراحاتك...',
        'submit_btn' => 'إرسال التقييم',
        'thanks_title' => 'شكراً لتقييمك! 🎉',
        'thanks_message' => 'نقدر وقتك وملاحظاتك القيمة',
        'rate_again' => 'تقييم جديد',
        'back_home' => 'العودة للرئيسية'
    ],
    'en' => [
        'title' => 'Rate Your Experience',
        'subtitle' => 'Your opinion matters! Help us improve our services',
        'name_label' => 'Name',
        'name_placeholder' => 'Your name',
        'phone_label' => 'Phone Number (Optional)',
        'phone_placeholder' => '07xxxxxxxxx',
        'service_label' => 'Service Rating',
        'staff_label' => 'Staff Rating',
        'cleanliness_label' => 'Cleanliness Rating',
        'experience_label' => 'How was your overall experience?',
        'excellent' => 'Excellent',
        'neutral' => 'Good',
        'bad' => 'Bad',
        'comments_label' => 'Additional Comments (Optional)',
        'comments_placeholder' => 'Share your feedback and suggestions...',
        'submit_btn' => 'Submit Rating',
        'thanks_title' => 'Thank You for Your Rating! 🎉',
        'thanks_message' => 'We appreciate your time and valuable feedback',
        'rate_again' => 'Rate Again',
        'back_home' => 'Back to Home'
    ],
    'ku' => [
        'title' => 'هەڵسەنگاندنەکەت',
        'subtitle' => 'بۆچوونەکەت گرنگە! یارمەتیمان بدە بۆ باشترکردنی خزمەتگوزاریەکانمان',
        'name_label' => 'ناو',
        'name_placeholder' => 'ناوی خۆت',
        'phone_label' => 'ژمارەی تەلەفۆن (دڵخواز)',
        'phone_placeholder' => '07xxxxxxxxx',
        'service_label' => 'هەڵسەنگاندنی خزمەتگوزاری',
        'staff_label' => 'هەڵسەنگاندنی ستاف',
        'cleanliness_label' => 'هەڵسەنگاندنی پاکی',
        'experience_label' => 'ئەزموونی گشتیت چۆن بوو؟',
        'excellent' => 'نایاب',
        'neutral' => 'باش',
        'bad' => 'خراپ',
        'comments_label' => 'تێبینی زیادە (دڵخواز)',
        'comments_placeholder' => 'بۆچوون و پێشنیارەکانت لەگەڵمان بڵێ...',
        'submit_btn' => 'ناردنی هەڵسەنگاندن',
        'thanks_title' => 'سوپاس بۆ هەڵسەنگاندنەکەت! 🎉',
        'thanks_message' => 'کاتەکەت و بۆچوونە بەنرخەکەت بە نرخ دەزانین',
        'rate_again' => 'هەڵسەنگاندنێکی نوێ',
        'back_home' => 'گەڕانەوە بۆ سەرەکی'
    ]
];

$text = $translations[$selectedLang];

// Content Security Policy
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'nonce-{$nonce}'; img-src 'self' data:; font-src 'self'");
?>
<!DOCTYPE html>
<html lang="<?= $selectedLang ?>" dir="<?= $direction ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $text['title'] ?> - Living Room Restaurant</title>
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
            font-family: '<?= $fontClass ?>', 'Segoe UI', Arial, sans-serif;
            background: url('uploads/wallpaper menu copy.png') center/cover no-repeat fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.65);
            z-index: -1;
        }

        .container {
            max-width: 700px;
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border-radius: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            padding: 40px;
            animation: fadeIn 0.6s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            text-align: center;
            margin-bottom: 35px;
        }

        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            border-radius: 50%;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
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
            color: rgba(255, 255, 255, 0.85);
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            color: #ffffff;
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 10px;
        }

        input[type="text"],
        input[type="tel"],
        textarea {
            width: 100%;
            padding: 12px 18px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 12px;
            color: #ffffff;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        input::placeholder,
        textarea::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: rgba(239, 68, 68, 0.8);
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Star Rating System */
        .rating-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 12px;
        }

        .rating-label {
            color: #ffffff;
            font-size: 15px;
            font-weight: 500;
            flex: 1;
        }

        .stars {
            display: flex;
            gap: 8px;
            direction: ltr;
        }

        .star {
            font-size: 28px;
            cursor: pointer;
            color: rgba(255, 255, 255, 0.3);
            transition: all 0.2s ease;
            user-select: none;
        }

        .star:hover,
        .star.active {
            color: #fbbf24;
            transform: scale(1.1);
        }

        .star input[type="radio"] {
            display: none;
        }

        /* Overall Experience Selector */
        .experience-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .experience-option {
            background: rgba(255, 255, 255, 0.08);
            border: 2px solid rgba(255, 255, 255, 0.15);
            border-radius: 15px;
            padding: 20px 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .experience-option:hover {
            border-color: rgba(239, 68, 68, 0.5);
            background: rgba(255, 255, 255, 0.12);
            transform: translateY(-3px);
        }

        .experience-option input[type="radio"] {
            display: none;
        }

        .experience-option input[type="radio"]:checked + .experience-content {
            border-color: rgba(239, 68, 68, 1);
        }

        .experience-option.selected {
            border-color: rgba(239, 68, 68, 1);
            background: rgba(239, 68, 68, 0.15);
        }

        .experience-emoji {
            font-size: 40px;
            display: block;
            margin-bottom: 10px;
        }

        .experience-text {
            color: #ffffff;
            font-size: 15px;
            font-weight: 500;
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: rgba(239, 68, 68, 0.9);
            border: none;
            border-radius: 50px;
            color: white;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
            margin-top: 10px;
        }

        .submit-btn:hover {
            background: rgba(239, 68, 68, 1);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.5);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        /* Success Message */
        .success-message {
            text-align: center;
            padding: 40px 20px;
        }

        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .success-title {
            color: #ffffff;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .success-text {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin-bottom: 30px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 12px 30px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 50px;
            color: white;
            font-size: 15px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .action-btn.primary {
            background: rgba(239, 68, 68, 0.9);
            border-color: rgba(239, 68, 68, 0.5);
        }

        .action-btn.primary:hover {
            background: rgba(239, 68, 68, 1);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
            }

            h1 {
                font-size: 24px;
            }

            .subtitle {
                font-size: 14px;
            }

            .rating-group {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .stars {
                align-self: flex-end;
            }

            .star {
                font-size: 24px;
            }

            .experience-options {
                grid-template-columns: 1fr;
            }

            .experience-emoji {
                font-size: 35px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 25px 15px;
            }

            h1 {
                font-size: 22px;
            }

            .star {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
            <!-- Success Message -->
            <div class="success-message">
                <div class="success-icon">✨</div>
                <h2 class="success-title"><?= $text['thanks_title'] ?></h2>
                <p class="success-text"><?= $text['thanks_message'] ?></p>
                <div class="action-buttons">
                    <a href="rating.php" class="action-btn primary"><?= $text['rate_again'] ?></a>
                    <a href="index.php" class="action-btn"><?= $text['back_home'] ?></a>
                </div>
            </div>
        <?php else: ?>
            <!-- Rating Form -->
            <div class="header">
                <div class="logo">
                    <img src="uploads/logo for menu.png" alt="Living Room Restaurant Logo">
                </div>
                <h1><?= $text['title'] ?></h1>
                <p class="subtitle"><?= $text['subtitle'] ?></p>
            </div>

            <?php if ($error): ?>
                <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.5); padding: 15px; border-radius: 12px; color: white; margin-bottom: 25px; text-align: center;">
                    ⚠️ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="ratingForm">
                <!-- Customer Name -->
                <div class="form-group">
                    <label><?= $text['name_label'] ?> *</label>
                    <input type="text" name="customer_name" placeholder="<?= $text['name_placeholder'] ?>" required>
                </div>

                <!-- Customer Phone -->
                <div class="form-group">
                    <label><?= $text['phone_label'] ?></label>
                    <input type="tel" name="customer_phone" placeholder="<?= $text['phone_placeholder'] ?>">
                </div>

                <!-- Service Rating -->
                <div class="rating-group">
                    <span class="rating-label"><?= $text['service_label'] ?>:</span>
                    <div class="stars" data-rating="service">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <label class="star" data-value="<?= $i ?>">
                                <input type="radio" name="service_rating" value="<?= $i ?>" required>
                                <span>⭐</span>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Staff Rating -->
                <div class="rating-group">
                    <span class="rating-label"><?= $text['staff_label'] ?>:</span>
                    <div class="stars" data-rating="staff">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <label class="star" data-value="<?= $i ?>">
                                <input type="radio" name="staff_rating" value="<?= $i ?>" required>
                                <span>⭐</span>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Cleanliness Rating -->
                <div class="rating-group">
                    <span class="rating-label"><?= $text['cleanliness_label'] ?>:</span>
                    <div class="stars" data-rating="cleanliness">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <label class="star" data-value="<?= $i ?>">
                                <input type="radio" name="cleanliness_rating" value="<?= $i ?>" required>
                                <span>⭐</span>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Overall Experience -->
                <div class="form-group">
                    <label><?= $text['experience_label'] ?> *</label>
                    <div class="experience-options">
                        <label class="experience-option" data-experience="good">
                            <input type="radio" name="overall_experience" value="good" required>
                            <div class="experience-content">
                                <span class="experience-emoji">😊</span>
                                <span class="experience-text"><?= $text['excellent'] ?></span>
                            </div>
                        </label>
                        <label class="experience-option" data-experience="neutral">
                            <input type="radio" name="overall_experience" value="neutral" required>
                            <div class="experience-content">
                                <span class="experience-emoji">😐</span>
                                <span class="experience-text"><?= $text['neutral'] ?></span>
                            </div>
                        </label>
                        <label class="experience-option" data-experience="bad">
                            <input type="radio" name="overall_experience" value="bad" required>
                            <div class="experience-content">
                                <span class="experience-emoji">😞</span>
                                <span class="experience-text"><?= $text['bad'] ?></span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Comments -->
                <div class="form-group">
                    <label><?= $text['comments_label'] ?></label>
                    <textarea name="comments" placeholder="<?= $text['comments_placeholder'] ?>" rows="4"></textarea>
                </div>

                <button type="submit" name="submit_rating" class="submit-btn">
                    <?= $text['submit_btn'] ?> ✨
                </button>
            </form>
        <?php endif; ?>
    </div>

    <script nonce="<?= $nonce ?>">
        // Star Rating Functionality
        document.querySelectorAll('.stars').forEach(starsContainer => {
            const stars = starsContainer.querySelectorAll('.star');
            
            stars.forEach((star, index) => {
                // Mouse hover effect
                star.addEventListener('mouseenter', () => {
                    stars.forEach((s, i) => {
                        if (i <= index) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });

                // Click to select
                star.addEventListener('click', () => {
                    const input = star.querySelector('input[type="radio"]');
                    input.checked = true;
                    
                    stars.forEach((s, i) => {
                        if (i <= index) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });
            });

            // Reset on mouse leave
            starsContainer.addEventListener('mouseleave', () => {
                const checkedInput = starsContainer.querySelector('input[type="radio"]:checked');
                if (checkedInput) {
                    const checkedValue = parseInt(checkedInput.value);
                    stars.forEach((s, i) => {
                        if (i < checkedValue) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                } else {
                    stars.forEach(s => s.classList.remove('active'));
                }
            });
        });

        // Overall Experience Selection
        document.querySelectorAll('.experience-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                document.querySelectorAll('.experience-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Check the radio button
                this.querySelector('input[type="radio"]').checked = true;
            });
        });

        // Form Validation
        document.getElementById('ratingForm').addEventListener('submit', function(e) {
            const serviceRating = document.querySelector('input[name="service_rating"]:checked');
            const staffRating = document.querySelector('input[name="staff_rating"]:checked');
            const cleanlinessRating = document.querySelector('input[name="cleanliness_rating"]:checked');
            const overallExperience = document.querySelector('input[name="overall_experience"]:checked');

            if (!serviceRating || !staffRating || !cleanlinessRating || !overallExperience) {
                e.preventDefault();
                alert('<?= $selectedLang === "ar" ? "الرجاء ملء جميع التقييمات المطلوبة" : "Please complete all required ratings" ?>');
            }
        });

        // Lazy load images
        if ('loading' in HTMLImageElement.prototype) {
            const images = document.querySelectorAll('img');
            images.forEach(img => img.loading = 'lazy');
        }
    </script>
</body>
</html>