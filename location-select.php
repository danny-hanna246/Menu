<?php
// location-select.php - صفحة اختيار الموقع (في المطعم أو في المنزل)
session_start();

// إذا لم يتم اختيار اللغة، ارجع لصفحة اختيار اللغة
if (!isset($_SESSION['lang']) || empty($_SESSION['lang'])) {
    header('Location: language-select.php');
    exit;
}

$selectedLang = $_SESSION['lang'];

// معالجة اختيار الموقع
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['location'])) {
    $allowedLocations = ['indoor', 'delivery'];
    $selectedLocation = $_POST['location'];

    if (in_array($selectedLocation, $allowedLocations)) {
        $_SESSION['location'] = $selectedLocation;

        // توجيه المستخدم حسب اختياره
        if ($selectedLocation === 'indoor') {
            header('Location: Indoor_MENU/INindex.php');
        } else {
            header('Location: Delivery_MENU/Dindex.php');
        }
        exit;
    }
}

// النصوص متعددة اللغات
$translations = [
    'ar' => [
        'title' => 'أين أنت الآن؟',
        'subtitle' => 'اختر الخيار المناسب لك',
        'indoor_title' => 'في المطعم',
        'indoor_desc' => 'عرض القائمة فقط',
        'delivery_title' => 'في المنزل',
        'delivery_desc' => 'طلب وتوصيل',
        'footer' => 'بيتك وأعز'
    ],
    'en' => [
        'title' => 'Where Are You?',
        'subtitle' => 'Choose Your Option',
        'indoor_title' => 'At Restaurant',
        'indoor_desc' => 'View Menu Only',
        'delivery_title' => 'At Home',
        'delivery_desc' => 'Order & Delivery',
        'footer' => 'Make Yourself at Home'
    ],
    'ku' => [
        'title' => 'لە کوێیت ئێستا؟',
        'subtitle' => 'بژاردەکەت هەڵبژێرە',
        'indoor_title' => 'لە چێشتخانە',
        'indoor_desc' => 'بینینی مێنوو تەنها',
        'delivery_title' => 'لە ماڵەوە',
        'delivery_desc' => 'داواکاری و گەیاندن',
        'footer' => 'ماڵت و خۆش'
    ]
];

$text = $translations[$selectedLang];
$direction = ($selectedLang === 'ar' || $selectedLang === 'ku') ? 'rtl' : 'ltr';
$fontClass = ($selectedLang === 'ar') ? 'CustomArabic' : 'CustomCalibri';

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
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
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

        /* Language Switcher */
        .language-switcher {
            position: fixed;
            top: 20px;
            z-index: 1000;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .container {
            max-width: 800px;
            width: 90%;
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            text-align: center;
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

        .logo {
            width: 100px;
            height: 100px;
            margin: 0 auto 25px;
            border-radius: 50%;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        h1 {
            color: #ffffff;
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 18px;
            margin-bottom: 40px;
        }

        .location-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .location-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            padding: 35px 25px;
            cursor: pointer;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .location-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: 0;
        }

        .location-card:hover::before {
            opacity: 1;
        }

        .location-card:hover {
            border-color: rgba(239, 68, 68, 0.8);
            transform: translateY(-8px);
            box-shadow: 0 12px 35px rgba(239, 68, 68, 0.3);
        }

        .location-content {
            position: relative;
            z-index: 1;
        }

        .location-icon {
            font-size: 60px;
            margin-bottom: 20px;
            display: block;
        }

        .location-title {
            color: #ffffff;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
        }

        .location-desc {
            color: rgba(255, 255, 255, 0.85);
            font-size: 16px;
            line-height: 1.5;
        }

        .location-btn {
            background: rgba(239, 68, 68, 0.9);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .location-btn:hover {
            background: rgba(239, 68, 68, 1);
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.5);
        }

        .footer-text {
            color: rgba(255, 255, 255, 0.7);
            font-size: 16px;
            margin-top: 25px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                width: 95%;
                padding: 30px 20px;
            }

            h1 {
                font-size: 26px;
            }

            .subtitle {
                font-size: 16px;
                margin-bottom: 30px;
            }

            .location-options {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .location-card {
                padding: 30px 20px;
            }

            .location-icon {
                font-size: 50px;
            }

            .location-title {
                font-size: 22px;
            }

            .logo {
                width: 80px;
                height: 80px;
            }
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 24px;
            }

            .subtitle {
                font-size: 15px;
            }

            .location-card {
                padding: 25px 15px;
            }

            .location-icon {
                font-size: 45px;
                margin-bottom: 15px;
            }

            .location-title {
                font-size: 20px;
            }

            .location-desc {
                font-size: 15px;
            }
        }
    </style>
</head>

<body>
    <!-- Language Switcher -->
    <div class="language-switcher <?= ($direction === 'rtl') ? 'rtl' : 'ltr' ?>">
        <a href="language-select.php" class="back-btn">
            <?= $direction === 'rtl' ? '← تغيير اللغة' : 'Change Language →' ?>
        </a>
    </div>

    <div class="container">
        <div class="logo">
            <img src="uploads/logo for menu.png" alt="Living Room Restaurant Logo">
        </div>

        <h1><?= $text['title'] ?></h1>
        <p class="subtitle"><?= $text['subtitle'] ?></p>

        <form method="POST" class="location-options">
            <div class="location-card">
                <div class="location-content">
                    <span class="location-icon">🍽️</span>
                    <h2 class="location-title"><?= $text['indoor_title'] ?></h2>
                    <p class="location-desc"><?= $text['indoor_desc'] ?></p>
                    <button type="submit" name="location" value="indoor" class="location-btn">
                        <?= $selectedLang === 'ar' ? 'عرض القائمة' : ($selectedLang === 'ku' ? 'بینینی مێنوو' : 'View Menu') ?>
                    </button>
                </div>
            </div>

            <div class="location-card">
                <div class="location-content">
                    <span class="location-icon">🏠</span>
                    <h2 class="location-title"><?= $text['delivery_title'] ?></h2>
                    <p class="location-desc"><?= $text['delivery_desc'] ?></p>
                    <button type="submit" name="location" value="delivery" class="location-btn">
                        <?= $selectedLang === 'ar' ? 'اطلب الآن' : ($selectedLang === 'ku' ? 'ئێستا داوا بکە' : 'Order Now') ?>
                    </button>
                </div>
            </div>
        </form>

        <!-- Social Media Icons -->
        <div style="display: flex; justify-content: center; gap: 15px; margin: 30px 0;">
            <a href="https://wa.me/9647511401949" target="_blank" rel="noopener noreferrer"
                style="width: 50px; height: 50px; background: rgba(37, 211, 102, 0.2); backdrop-filter: blur(10px); border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; border: 1px solid rgba(255, 255, 255, 0.2);"
                onmouseover="this.style.background='rgba(37, 211, 102, 0.4)'; this.style.transform='translateY(-5px)'"
                onmouseout="this.style.background='rgba(37, 211, 102, 0.2)'; this.style.transform='translateY(0)'">
                <img src="uploads/whatsapp (2).png" alt="WhatsApp" style="width: 26px; height: 26px;">
            </a>
            <a href="https://www.facebook.com/shadysamitrue" target="_blank" rel="noopener noreferrer"
                style="width: 50px; height: 50px; background: rgba(59, 89, 152, 0.2); backdrop-filter: blur(10px); border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; border: 1px solid rgba(255, 255, 255, 0.2);"
                onmouseover="this.style.background='rgba(59, 89, 152, 0.4)'; this.style.transform='translateY(-5px)'"
                onmouseout="this.style.background='rgba(59, 89, 152, 0.2)'; this.style.transform='translateY(0)'">
                <img src="uploads/facebook (1).png" alt="Facebook" style="width: 26px; height: 26px;">
            </a>
            <a href="https://www.instagram.com/livingroom.restaurant/" target="_blank" rel="noopener noreferrer"
                style="width: 50px; height: 50px; background: rgba(225, 48, 108, 0.2); backdrop-filter: blur(10px); border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; border: 1px solid rgba(255, 255, 255, 0.2);"
                onmouseover="this.style.background='rgba(225, 48, 108, 0.4)'; this.style.transform='translateY(-5px)'"
                onmouseout="this.style.background='rgba(225, 48, 108, 0.2)'; this.style.transform='translateY(0)'">
                <img src="uploads/insta (1).png" alt="Instagram" style="width: 26px; height: 26px;">
            </a>
            <a href="https://www.tiktok.com/@living_room54" target="_blank" rel="noopener noreferrer"
                style="width: 50px; height: 50px; background: rgba(0, 0, 0, 0.3); backdrop-filter: blur(10px); border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; border: 1px solid rgba(255, 255, 255, 0.2);"
                onmouseover="this.style.background='rgba(0, 0, 0, 0.5)'; this.style.transform='translateY(-5px)'"
                onmouseout="this.style.background='rgba(0, 0, 0, 0.3)'; this.style.transform='translateY(0)'">
                <img src="uploads/tik-tok (1).png" alt="TikTok" style="width: 26px; height: 26px;">
            </a>
        </div>

        <p class="footer-text"><?= $text['footer'] ?></p>
    </div>

    <script nonce="<?= $nonce ?>">
        // تحسين التفاعل
        document.querySelectorAll('.location-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                this.style.transform = 'scale(0.95)';
                this.style.opacity = '0.8';
            });
        });

        // تحسين الأداء
        if ('loading' in HTMLImageElement.prototype) {
            const images = document.querySelectorAll('img');
            images.forEach(img => img.loading = 'lazy');
        }
    </script>
</body>

</html>