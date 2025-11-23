<?php
// language-select.php - صفحة اختيار اللغة
session_start();

// إذا كانت اللغة محددة مسبقاً، انتقل مباشرة لصفحة اختيار الموقع
if (isset($_SESSION['lang']) && !empty($_SESSION['lang'])) {
    header('Location: location-select.php');
    exit;
}

// معالجة اختيار اللغة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['language'])) {
    $allowedLanguages = ['ar', 'en', 'ku'];
    $selectedLang = $_POST['language'];

    if (in_array($selectedLang, $allowedLanguages)) {
        $_SESSION['lang'] = $selectedLang;
        header('Location: location-select.php');
        exit;
    }
}

// Content Security Policy
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'nonce-{$nonce}'; img-src 'self' data:; font-src 'self'");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختر اللغة - Select Language - زمانێک هەڵبژێرە</title>
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
            font-family: 'CustomCalibri', 'Segoe UI', Arial, sans-serif;
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
            background: rgba(0, 0, 0, 0.6);
            z-index: -1;
        }

        .container {
            max-width: 600px;
            width: 90%;
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            text-align: center;
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

        .logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 30px;
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
            font-size: 28px;
            margin-bottom: 15px;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 18px;
            margin-bottom: 40px;
            font-family: 'CustomArabic', Arial, sans-serif;
        }

        .language-options {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 30px;
        }

        .language-btn {
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            padding: 20px 30px;
            font-size: 22px;
            font-weight: 500;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            text-decoration: none;
            backdrop-filter: blur(5px);
        }

        .language-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 255, 255, 0.2);
        }

        .language-btn:active {
            transform: translateY(0);
        }

        .flag {
            font-size: 32px;
        }

        .lang-text {
            flex: 1;
            text-align: center;
        }

        .arabic-text {
            font-family: 'CustomArabic', Arial, sans-serif;
        }

        .social-icons {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .social-icons h3 {
            color: #ffffff;
            font-size: 18px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .social-link {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.3);
            text-decoration: none;
        }

        .social-link:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
        }

        .social-link svg {
            width: 24px;
            height: 24px;
            fill: #ffffff;
        }

        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
            }

            h1 {
                font-size: 24px;
            }

            .subtitle {
                font-size: 16px;
            }

            .language-btn {
                padding: 18px 25px;
                font-size: 20px;
            }

            .logo {
                width: 100px;
                height: 100px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Logo -->
        <div class="logo">
            <img src="uploads/logo for menu.png" alt="Living Room Restaurant Logo" />
        </div>

        <!-- العنوان -->
        <h1>Welcome - مرحباً - بەخێربێن</h1>
        <p class="subtitle arabic-text">اختر لغتك المفضلة | Select Your Language | زمانی دڵخوازت هەڵبژێرە</p>

        <!-- خيارات اللغة -->
        <form method="post" class="language-options">
            <!-- العربية -->
            <button type="submit" name="language" value="ar" class="language-btn">
                <span class="flag">🇮🇶</span>
                <span class="lang-text arabic-text">العربية</span>
            </button>

            <!-- English -->
            <button type="submit" name="language" value="en" class="language-btn">
                <span class="flag">🇬🇧</span>
                <span class="lang-text">English</span>
            </button>

            <!-- كردي -->
            <button type="submit" name="language" value="ku" class="language-btn">
                <span class="flag">🇮🇶</span>
                <span class="lang-text arabic-text">کوردی</span>
            </button>
        </form>

        <!-- أيقونات التواصل الاجتماعي -->
        <div class="social-icons">
            <h3 class="arabic-text">تابعنا على | Follow Us | شوێنمان بکەوە</h3>
            <div class="social-links">
                <!-- Facebook -->
                <a href="https://facebook.com/livingroomrestaurant" target="_blank" rel="noopener noreferrer" class="social-link" aria-label="Facebook">
                    <svg viewBox="0 0 24 24">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                    </svg>
                </a>

                <!-- Instagram -->
                <a href="https://instagram.com/livingroomrestaurant" target="_blank" rel="noopener noreferrer" class="social-link" aria-label="Instagram">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                    </svg>
                </a>

                <!-- WhatsApp -->
                <a href="https://wa.me/9647xxxxxxxxx" target="_blank" rel="noopener noreferrer" class="social-link" aria-label="WhatsApp">
                    <svg viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                    </svg>
                </a>

                <!-- TikTok -->
                <a href="https://tiktok.com/@livingroomrestaurant" target="_blank" rel="noopener noreferrer" class="social-link" aria-label="TikTok">
                    <svg viewBox="0 0 24 24">
                        <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z" />
                    </svg>
                </a>
            </div>
        </div>
    </div>
</body>

</html>