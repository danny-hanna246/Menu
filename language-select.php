<?php
// language-select.php - صفحة اختيار اللغة الأولى
session_start();

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
            background: rgba(0, 0, 0, 0.65);
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
            background: rgba(239, 68, 68, 0.9);
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 18px 30px;
            border-radius: 50px;
            font-size: 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            text-decoration: none;
        }

        .language-btn:hover {
            background: rgba(239, 68, 68, 1);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.5);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .language-btn:active {
            transform: translateY(-1px);
        }

        .flag {
            font-size: 28px;
        }

        .footer-text {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            margin-top: 20px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                width: 95%;
                padding: 30px 20px;
            }

            h1 {
                font-size: 24px;
            }

            .subtitle {
                font-size: 16px;
                margin-bottom: 30px;
            }

            .language-btn {
                padding: 16px 25px;
                font-size: 18px;
            }

            .logo {
                width: 100px;
                height: 100px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 25px 15px;
            }

            h1 {
                font-size: 22px;
            }

            .subtitle {
                font-size: 15px;
            }

            .language-btn {
                padding: 14px 20px;
                font-size: 17px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo">
            <img src="uploads/logo for menu.png" alt="Living Room Restaurant Logo">
        </div>

        <h1>Living Room Restaurant</h1>
        <p class="subtitle">اختر لغتك المفضلة - Select Your Language - زمانی خۆت هەڵبژێرە</p>

        <form method="POST" class="language-options">
            <button type="submit" name="language" value="ar" class="language-btn">
                <span class="flag">🇮🇶</span>
                <span>العربية</span>
            </button>

            <button type="submit" name="language" value="en" class="language-btn">
                <span class="flag">🇬🇧</span>
                <span>English</span>
            </button>

            <button type="submit" name="language" value="ku" class="language-btn">
                <span class="flag">🟨🔴🟩</span>
                <span>کوردی</span>
            </button>
        </form>

        <p class="footer-text">بيتك وأعز - Make Yourself at Home - ماڵت و خۆش</p>
    </div>

    <script nonce="<?= $nonce ?>">
        // تحسين التفاعل
        document.querySelectorAll('.language-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
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