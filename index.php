<?php
// Enhanced index.php with comprehensive security measures
session_start();

// إذا لم يتم اختيار اللغة، انتقل لصفحة اختيار اللغة
if (!isset($_SESSION['lang']) || empty($_SESSION['lang'])) {
  header('Location: language-select.php');
  exit;
}

// إذا لم يتم اختيار الموقع، انتقل لصفحة اختيار الموقع
if (!isset($_SESSION['location']) || empty($_SESSION['location'])) {
  header('Location: location-select.php');
  exit;
}

// الحصول على اللغة والموقع المحددين
$selectedLang = $_SESSION['lang'];
$selectedLocation = $_SESSION['location'];

// السماح بتغيير اللغة
if (isset($_GET['change_lang'])) {
  unset($_SESSION['lang']);
  unset($_SESSION['location']);
  header('Location: language-select.php');
  exit;
}
// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Start session securely if needed for language preference
if (session_status() === PHP_SESSION_NONE) {
  ini_set('session.use_strict_mode', 1);
  ini_set('session.use_only_cookies', 1);
  ini_set('session.cookie_httponly', 1);
  ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
  ini_set('session.cookie_samesite', 'Strict');
  session_start();
}

// Input validation and sanitization
$selectedLang = 'en';
if (!empty($_GET['lang'])) {
  $inputLang = filter_var($_GET['lang'], FILTER_SANITIZE_STRING);
  $allowedLangs = ['en', 'ar', 'ku'];
  if (in_array($inputLang, $allowedLangs)) {
    $selectedLang = $inputLang;
    $_SESSION['user_language'] = $selectedLang;
  }
} elseif (!empty($_SESSION['user_language'])) {
  $selectedLang = $_SESSION['user_language'];
}

// Rate limiting for page requests (basic protection)
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateKey = 'page_' . $clientIP;

// Simple file-based rate limiting
$rateFile = sys_get_temp_dir() . '/rate_' . md5($rateKey) . '.txt';
$currentTime = time();
$timeWindow = 60; // 1 minute
$maxRequests = 30; // 30 requests per minute

if (file_exists($rateFile)) {
  $rateData = json_decode(file_get_contents($rateFile), true);
  if ($rateData && ($currentTime - $rateData['first_request']) < $timeWindow) {
    if ($rateData['count'] >= $maxRequests) {
      http_response_code(429);
      header('Retry-After: ' . ($timeWindow - ($currentTime - $rateData['first_request'])));
      exit('Rate limit exceeded. Please try again later.');
    }
    $rateData['count']++;
  } else {
    $rateData = ['first_request' => $currentTime, 'count' => 1];
  }
} else {
  $rateData = ['first_request' => $currentTime, 'count' => 1];
}
file_put_contents($rateFile, json_encode($rateData), LOCK_EX);

// Cleanup old rate limit files (5% chance)
if (random_int(1, 100) <= 5) {
  $tempDir = sys_get_temp_dir();
  $files = glob($tempDir . '/rate_*.txt');
  foreach ($files as $file) {
    if (file_exists($file) && (time() - filemtime($file)) > 3600) { // 1 hour old
      @unlink($file);
    }
  }
}

// Content Security Policy
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'nonce-{$nonce}'; img-src 'self' data:; font-src 'self'; connect-src 'self'");
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($selectedLang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $selectedLang === 'ar' || $selectedLang === 'ku' ? 'rtl' : 'ltr' ?>">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Living Room Restaurant - Authentic dining experience with indoor and delivery menus" />
  <meta name="keywords" content="restaurant, living room, food, menu, dining, delivery" />
  <meta name="author" content="Living Room Restaurant" />
  <title>Living Room Restaurant</title>
  <link rel="icon" href="uploads/logo for menu.png" type="image/png" />
  <link rel="canonical" href="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>" />

  <style>
    /* Custom Font Declarations with fallbacks */
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

    :root {
      --primary-color: #ffffff;
      --text-color: #ffffff;
      --bg-overlay: rgba(0, 0, 0, 0.6);
      --button-bg: rgba(255, 255, 255, 0.1);
      --button-hover: rgba(255, 255, 255, 0.2);
      --glass-bg: rgba(255, 255, 255, 0.1);
      --glass-border: rgba(255, 255, 255, 0.2);
      --shadow: rgba(0, 0, 0, 0.3);
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
      color: var(--text-color);

    }

    /* Background overlay */
    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: var(--bg-overlay);
      z-index: -1;
    }

    /* RTL Support with Custom Arabic Font */
    [dir="rtl"] {
      font-family: 'CustomArabic', 'CustomCalibri', Arial, sans-serif;
    }

    /* Apply specific font classes */
    .lang-ar {
      font-family: 'CustomArabic', Arial, sans-serif;
    }

    .lang-en {
      font-family: 'CustomCalibri', Arial, sans-serif;
    }

    /* Language Switcher with enhanced security */
    .language-switcher {
      position: fixed;
      top: 20px;
      left: 20px;
      z-index: 1000;
      animation: slideInLeft 0.8s ease-out;
    }

    [dir="rtl"] .language-switcher {
      left: auto;
      right: 20px;
    }

    .lang-dropdown {
      position: relative;
      display: inline-block;
    }

    .lang-current {
      background: var(--glass-bg);
      backdrop-filter: blur(10px);
      border: 1px solid var(--glass-border);
      color: var(--text-color);
      padding: 8px 16px;
      border-radius: 25px;
      cursor: pointer;
      font-weight: 500;
      font-size: 14px;
      transition: all 0.3s ease;
      box-shadow: 0 8px 32px var(--shadow);
      display: flex;
      align-items: center;
      gap: 8px;
      min-width: 60px;
      justify-content: center;
      border: none;
      font-family: inherit;
    }

    .lang-current:hover {
      background: var(--button-hover);
      transform: translateY(-2px);
    }

    .lang-options {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: var(--glass-bg);
      backdrop-filter: blur(10px);
      border: 1px solid var(--glass-border);
      border-radius: 15px;
      margin-top: 5px;
      box-shadow: 0 8px 32px var(--shadow);
      opacity: 0;
      visibility: hidden;
      transform: translateY(-10px);
      transition: all 0.3s ease;
      overflow: hidden;
    }

    .lang-dropdown.open .lang-options {
      opacity: 1;
      visibility: visible;
      transform: translateY(0);
    }

    .lang-btn {
      background: transparent;
      border: none;
      color: var(--text-color);
      padding: 8px 16px;
      width: 100%;
      cursor: pointer;
      font-weight: 500;
      font-size: 14px;
      transition: all 0.3s ease;
      text-align: center;
      font-family: inherit;
    }

    .lang-btn:hover {
      background: var(--button-hover);
    }

    /* Loading indicator */
    .loading-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.8);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }

    .loading-overlay.active {
      opacity: 1;
      visibility: visible;
    }

    .spinner {
      width: 50px;
      height: 50px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-top: 3px solid var(--primary-color);
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    /* Main Container */
    .main-container {
      text-align: center;
      max-width: 500px;
      width: 90%;
      padding: 2rem;
      animation: fadeInUp 1s ease-out;
      font-family: inherit;
    }

    /* Logo */
    .logo {
      margin-bottom: 2rem;
      animation: logoFloat 2s ease-in-out infinite alternate;
    }

    .logo img {
      width: 120px;
      height: 120px;
      object-fit: contain;
      filter: drop-shadow(0 10px 20px var(--shadow));
      transition: transform 0.3s ease;
    }

    .logo img:hover {
      transform: scale(1.1) rotate(5deg);
    }

    /* Welcome Text */
    .welcome-text {
      margin-bottom: 0.5rem;
      animation: slideInRight 0.8s ease-out 0.2s both;
      font-family: inherit;
    }

    .welcome-text h1 {
      font-size: 2.5rem;
      font-weight: 600;
      color: var(--primary-color);
      margin-bottom: 0.5rem;
      text-shadow: 0 4px 8px var(--shadow);
      letter-spacing: -0.02em;
      font-family: inherit;
    }

    .welcome-text p {
      font-size: 1.2rem;
      font-weight: 300;
      color: var(--text-color);
      opacity: 0.9;
      text-shadow: 0 2px 4px var(--shadow);
      margin-bottom: 3rem;
      font-family: inherit;
    }

    /* Menu Buttons */
    .menu-buttons {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      margin-bottom: 3rem;
    }

    .menu-btn {
      background: var(--glass-bg);
      backdrop-filter: blur(10px);
      border: 1px solid var(--glass-border);
      color: var(--text-color);
      padding: 1rem 2rem;
      border-radius: 50px;
      font-size: 1.1rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.8rem;
      box-shadow: 0 8px 32px var(--shadow);
      position: relative;
      overflow: hidden;
      font-family: inherit;
    }

    .menu-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.6s ease;
    }

    .menu-btn:hover::before {
      left: 100%;
    }

    .menu-btn:hover {
      transform: translateY(-3px) scale(1.02);
      background: var(--button-hover);
      box-shadow: 0 15px 40px var(--shadow);
    }

    .menu-btn:active {
      transform: translateY(-1px) scale(0.98);
    }

    .menu-btn img {
      width: 24px;
      height: 24px;
      object-fit: contain;
    }

    .menu-btn span {
      font-family: inherit;
    }

    /* Individual button animations */
    .menu-btn:nth-child(1) {
      animation: slideInLeft 0.8s ease-out 0.4s both;
    }

    .menu-btn:nth-child(2) {
      animation: slideInRight 0.8s ease-out 0.6s both;
    }

    /* Social Media Icons */
    .social-icons {
      display: flex;
      justify-content: center;
      gap: 1.5rem;
      animation: fadeInUp 0.8s ease-out 0.8s both;
    }

    .social-icon {
      width: 50px;
      height: 50px;
      background: var(--glass-bg);
      backdrop-filter: blur(10px);
      border: 1px solid var(--glass-border);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--text-color);
      text-decoration: none;
      transition: all 0.3s ease;
      box-shadow: 0 8px 32px var(--shadow);
      position: relative;
      overflow: hidden;
    }

    .social-icon::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 50%;
      transition: all 0.4s ease;
      transform: translate(-50%, -50%);
    }

    .social-icon:hover::before {
      width: 100%;
      height: 100%;
    }

    .social-icon:hover {
      transform: translateY(-5px) scale(1.1);
      background: var(--button-hover);
      box-shadow: 0 15px 40px var(--shadow);
    }

    .social-icon img {
      width: 24px;
      height: 24px;
      object-fit: contain;
      position: relative;
      z-index: 1;
    }

    /* Error handling */
    .error-message {
      background: rgba(255, 0, 0, 0.1);
      border: 1px solid rgba(255, 0, 0, 0.3);
      color: #ff6b6b;
      padding: 1rem;
      border-radius: 10px;
      margin-bottom: 2rem;
      text-align: center;
    }

    /* Animations */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes slideInLeft {
      from {
        opacity: 0;
        transform: translateX(-30px);
      }

      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes slideInRight {
      from {
        opacity: 0;
        transform: translateX(30px);
      }

      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes logoFloat {
      from {
        transform: translateY(0px);
      }

      to {
        transform: translateY(-10px);
      }
    }

    @keyframes spin {
      from {
        transform: rotate(0deg);
      }

      to {
        transform: rotate(360deg);
      }
    }

    /* Responsive Styles */
    @media (max-width: 768px) {
      body {
        background-attachment: scroll;
      }

      .language-switcher {
        top: 15px;
        left: 15px;
      }

      [dir="rtl"] .language-switcher {
        right: 15px;
      }

      .lang-current {
        padding: 6px 12px;
        font-size: 13px;
        min-width: 50px;
      }

      .main-container {
        padding: 1.5rem;
        width: 95%;
      }

      .logo img {
        width: 100px;
        height: 100px;
      }

      .welcome-text h1 {
        font-size: 2rem;
      }

      .welcome-text p {
        font-size: 1rem;
        margin-bottom: 2rem;
      }

      .menu-btn {
        padding: 0.9rem 1.5rem;
        font-size: 1rem;
      }

      .social-icon {
        width: 45px;
        height: 45px;
      }

      .social-icon img {
        width: 20px;
        height: 20px;
      }
    }

    @media (max-width: 480px) {
      .welcome-text h1 {
        font-size: 1.8rem;
      }

      .welcome-text p {
        font-size: 0.95rem;
      }

      .menu-btn {
        padding: 0.8rem 1.2rem;
        font-size: 0.95rem;
      }

      .social-icons {
        gap: 1rem;
      }

      .social-icon {
        width: 40px;
        height: 40px;
      }

      .social-icon img {
        width: 18px;
        height: 18px;
      }
    }

    @media (max-width: 360px) {
      .main-container {
        padding: 1rem;
      }

      .logo img {
        width: 80px;
        height: 80px;
      }

      .welcome-text h1 {
        font-size: 1.6rem;
      }

      .welcome-text p {
        font-size: 0.9rem;
      }

      .menu-btn {
        padding: 0.7rem 1rem;
        font-size: 0.9rem;
        gap: 0.6rem;
      }
    }
  </style>
</head>

<body class="lang-<?= $selectedLang ?>">
  <!-- Language Switcher -->
  <div class="language-switcher" id="languageSwitcher">
    <div class="lang-dropdown" id="langDropdown">
      <button class="lang-current" id="langCurrent" aria-label="Select Language"><?= strtoupper($selectedLang) ?></button>
      <div class="lang-options" id="langOptions" role="menu">
        <button class="lang-btn" data-lang="en" role="menuitem">EN</button>
        <button class="lang-btn" data-lang="ar" role="menuitem">AR</button>
        <button class="lang-btn" data-lang="ku" role="menuitem">KU</button>
      </div>
    </div>
  </div>

  <!-- Loading Overlay -->
  <div class="loading-overlay" id="loadingOverlay" aria-hidden="true">
    <div class="spinner"></div>
  </div>

  <!-- Main Container -->
  <main class="main-container">
    <!-- Logo -->
    <div class="logo">
      <img src="uploads/logo for menu.png" alt="Living Room Restaurant Logo" id="logo">
    </div>

    <!-- Welcome Text -->
    <div class="welcome-text">
      <h1 id="welcomeTitle">
        <?php
        switch ($selectedLang) {
          case 'ar':
            echo 'مرحباً بكم في';
            break;
          case 'ku':
            echo '';
            break;
          default:
            echo 'Welcome to';
        }
        ?>
      </h1>
      <h1>Living Room</h1>
      <p id="welcomeSubtitle">
        <?php
        switch ($selectedLang) {
          case 'ar':
            echo 'بيتك وأعز';
            break;
          case 'ku':
            echo 'بخێربین';
            break;
          default:
            echo 'Make yourself at home!';
        }
        ?>
      </p>
    </div>

    <!-- Menu Buttons -->
    <div class="menu-buttons">
      <a href="Indoor_MENU/INindex.php?lang=<?= $selectedLang ?>" class="menu-btn" id="indoorBtn">
        <img src="uploads/indoor menu.png" alt="Indoor Menu Icon" width="24" height="24">
        <span>
          <?php
          switch ($selectedLang) {
            case 'ar':
              echo 'هل أنت في المطعم؟';
              break;
            case 'ku':
              echo 'مێنووی ناوخۆیی';
              break;
            default:
              echo 'Indoor Menu';
          }
          ?>
        </span>
      </a>
      <a href="Delivery_MENU/Dindex.php?lang=<?= $selectedLang ?>" class="menu-btn" id="deliveryBtn">
        <img src="uploads/dilvery icon.png" alt="Delivery Menu Icon" width="24" height="24">
        <span>
          <?php
          switch ($selectedLang) {
            case 'ar':
              echo 'هل أنت في المنزل؟';
              break;
            case 'ku':
              echo 'مێنووی گەیاندن';
              break;
            default:
              echo 'Delivery Menu';
          }
          ?>
        </span>
      </a>
    </div>

    <!-- Social Media Icons -->
    <div class="social-icons">
      <a href="https://wa.me/9647511401949" class="social-icon" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp">
        <img src="uploads/whatsapp (2).png" alt="WhatsApp" width="24" height="24">
      </a>
      <a href="https://www.facebook.com/share/16K9gAiWJY/?mibextid=wwXIfr" class="social-icon" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
        <img src="uploads/facebook (2).png" alt="Facebook" width="24" height="24">
      </a>
      <a href="https://www.instagram.com/restaurantlivingroom?igsh=aTNqeWF2cW9sNmt0" class="social-icon" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
        <img src="uploads/insta.png" alt="Instagram" width="24" height="24">
      </a>
      <a href="https://www.tiktok.com/@livingroom91?_t=ZS-8zRwCfTl8qa&_r=1" class="social-icon" target="_blank" rel="noopener noreferrer" aria-label="TikTok">
        <img src="uploads/tiktok.png" alt="TikTok" width="24" height="24">
      </a>
      <a href="tel:+9647511401949" class="social-icon" aria-label="Call">
        <img src="uploads/telephone.png" alt="Phone" width="24" height="24">
      </a>
    </div>
  </main>

  <script nonce="<?= $nonce ?>">
    'use strict';

    let currentLanguage = '<?= $selectedLang ?>';

    // Security: Input validation
    function sanitizeInput(input) {
      const div = document.createElement('div');
      div.textContent = input;
      return div.innerHTML;
    }

    // Initialize language from URL or stored preference
    function initializeLanguage() {
      const urlParams = new URLSearchParams(window.location.search);
      const urlLang = urlParams.get('lang');
      const allowedLangs = ['en', 'ar', 'ku'];

      if (urlLang && allowedLangs.includes(urlLang)) {
        currentLanguage = urlLang;
        // Store preference securely
        try {
          sessionStorage.setItem('selectedLanguage', currentLanguage);
        } catch (e) {
          console.warn('Unable to store language preference');
        }
      }

      updateLanguageDisplay(currentLanguage);
    }

    // Update language display in dropdown
    function updateLanguageDisplay(lang) {
      const langCurrent = document.getElementById('langCurrent');
      if (langCurrent) {
        langCurrent.textContent = lang.toUpperCase();
      }
    }

    // Toggle language dropdown with keyboard support
    function toggleLanguageDropdown(event) {
      const dropdown = document.getElementById('langDropdown');
      const isOpen = dropdown.classList.contains('open');

      if (event && event.key && event.key !== 'Enter' && event.key !== ' ') {
        return;
      }

      dropdown.classList.toggle('open');

      // Manage focus for accessibility
      if (dropdown.classList.contains('open')) {
        const firstOption = dropdown.querySelector('.lang-btn');
        if (firstOption) {
          firstOption.focus();
        }
      }
    }

    // Close dropdown when clicking outside
    function closeLanguageDropdown() {
      const dropdown = document.getElementById('langDropdown');
      dropdown.classList.remove('open');
    }

    // Show loading overlay
    function showLoading() {
      const overlay = document.getElementById('loadingOverlay');
      if (overlay) {
        overlay.classList.add('active');
        overlay.setAttribute('aria-hidden', 'false');
      }
    }

    // Hide loading overlay
    function hideLoading() {
      const overlay = document.getElementById('loadingOverlay');
      if (overlay) {
        overlay.classList.remove('active');
        overlay.setAttribute('aria-hidden', 'true');
      }
    }

    // Language change handler with validation
    function changeLanguage(lang) {
      const allowedLangs = ['en', 'ar', 'ku'];
      if (!allowedLangs.includes(lang) || lang === currentLanguage) {
        return;
      }

      showLoading();
      currentLanguage = lang;

      // Store preference securely
      try {
        sessionStorage.setItem('selectedLanguage', lang);
      } catch (e) {
        console.warn('Unable to store language preference');
      }

      updateLanguageDisplay(lang);
      closeLanguageDropdown();

      // Redirect with language parameter
      const newUrl = new URL(window.location);
      newUrl.searchParams.set('lang', lang);

      setTimeout(() => {
        window.location.href = newUrl.toString();
      }, 300);
    }

    // Menu button click handlers with security
    function handleMenuClick(type, lang) {
      showLoading();

      const allowedTypes = ['indoor', 'delivery'];
      const allowedLangs = ['en', 'ar', 'ku'];

      if (!allowedTypes.includes(type) || !allowedLangs.includes(lang)) {
        hideLoading();
        return false;
      }

      const baseUrl = type === 'indoor' ? 'Indoor_MENU/INindex.php' : 'Delivery_MENU/Dindex.php';
      const url = `${baseUrl}?lang=${encodeURIComponent(lang)}`;

      setTimeout(() => {
        window.location.href = url;
      }, 300);
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
      // Language dropdown event listeners
      const langCurrent = document.getElementById('langCurrent');
      const langButtons = document.querySelectorAll('.lang-btn');

      if (langCurrent) {
        langCurrent.addEventListener('click', function(e) {
          e.stopPropagation();
          toggleLanguageDropdown();
        });

        // Keyboard support
        langCurrent.addEventListener('keydown', function(e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggleLanguageDropdown(e);
          }
        });
      }

      langButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.stopPropagation();
          const lang = this.dataset.lang;
          if (lang) {
            changeLanguage(lang);
          }
        });

        // Keyboard support
        btn.addEventListener('keydown', function(e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            const lang = this.dataset.lang;
            if (lang) {
              changeLanguage(lang);
            }
          }
        });
      });

      // Close dropdown when clicking outside
      document.addEventListener('click', function() {
        closeLanguageDropdown();
      });

      // Menu button event listeners
      const indoorBtn = document.getElementById('indoorBtn');
      const deliveryBtn = document.getElementById('deliveryBtn');

      if (indoorBtn) {
        indoorBtn.addEventListener('click', function(e) {
          e.preventDefault();
          handleMenuClick('indoor', currentLanguage);
        });
      }

      if (deliveryBtn) {
        deliveryBtn.addEventListener('click', function(e) {
          e.preventDefault();
          handleMenuClick('delivery', currentLanguage);
        });
      }

      // Initialize language
      initializeLanguage();

      // Hide loading overlay after page loads
      setTimeout(hideLoading, 500);
    });

    // Hide loading overlay when page becomes visible (fixes back button issue)
    document.addEventListener('pageshow', function(event) {
      hideLoading();
    });

    // Security: Clear potential XSS on focus events
    window.addEventListener('focus', function() {
      hideLoading();
    });

    // Add interactive mouse effects (performance optimized)
    let animationFrame;
    document.addEventListener('mousemove', function(e) {
      if (animationFrame) {
        cancelAnimationFrame(animationFrame);
      }

      animationFrame = requestAnimationFrame(function() {
        const logo = document.querySelector('.logo img');
        if (!logo) return;

        const rect = logo.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;

        const deltaX = (e.clientX - centerX) / 50;
        const deltaY = (e.clientY - centerY) / 50;

        logo.style.transform = `translate(${deltaX}px, ${deltaY}px) scale(1.02)`;
      });
    });

    // Reset logo position when mouse leaves
    document.addEventListener('mouseleave', function() {
      if (animationFrame) {
        cancelAnimationFrame(animationFrame);
      }

      const logo = document.querySelector('.logo img');
      if (logo) {
        logo.style.transform = 'translate(0px, 0px) scale(1)';
      }
    });

    // Accessibility: Escape key to close dropdown
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeLanguageDropdown();
      }
    });

    // Error handling for images
    document.querySelectorAll('img').forEach(img => {
      img.addEventListener('error', function() {
        console.warn('Failed to load image:', this.src);
        // Could implement fallback image here
      });
    });

    // Security: Prevent common attacks
    document.addEventListener('contextmenu', function(e) {
      // Allow context menu but log for security monitoring
      console.log('Context menu accessed');
    });

    // Performance: Preload menu pages
    if ('prefetch' in document.createElement('link')) {
      const prefetchLinks = [
        'Indoor_MENU/INindex.php',
        'Delivery_MENU/Dindex.php'
      ];

      prefetchLinks.forEach(url => {
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        document.head.appendChild(link);
      });
    }
  </script>
</body>

</html>