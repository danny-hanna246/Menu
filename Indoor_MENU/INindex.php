<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Indoor MENU - Living Room</title>
  <link rel="icon" href="../uploads/logo for menu.png" style="height: 100px; width: 100px; margin-right: 10px;" />

  <style>
    /* Custom Font Declarations */
    @font-face {
      font-family: 'CustomCalibri';
      src: url('../uploads/CALIBRI.TTF') format('truetype');
      font-weight: normal;
      font-style: normal;
      font-display: swap;
    }

    @font-face {
      font-family: 'CustomArabic';
      src: url('../uploads/ALL-GENDERS-LIGHT-V4.OTF') format('opentype');
      font-weight: normal;
      font-style: normal;
      font-display: swap;
    }

    :root {
      --primary-color: #f8f8f8;
      --accent-color: #464747;
      --bg-color: #000000;
      --text-color: #f8f8f8;
      --card-bg: #464747;
      --footer-bg: #000000;
      --border-color: #464747;
      --hover-color: #5a5a5a;
    }

    * {
      box-sizing: border-box;
    }

    body {
      font-family: 'CustomCalibri', Arial, sans-serif;
      background: url('../uploads/wallpaper menu copy.png') center/cover no-repeat fixed;
      margin: 0;
      padding: 2rem 1rem 6rem;
      color: var(--text-color);
      display: flex;
      flex-direction: column;
      align-items: center;
      transition: background-color 0.4s, color 0.4s;
    }

    /* RTL Support with Custom Arabic Font */
    [dir="rtl"] {
      font-family: 'CustomArabic', 'CustomCalibri', Arial, sans-serif;
    }

    [dir="rtl"] .categories {
      direction: rtl;
    }

    [dir="rtl"] .menu {
      direction: rtl;
    }

    [dir="rtl"] .info {
      text-align: right;
    }

    [dir="rtl"] footer {
      direction: rtl;
    }

    /* Apply Arabic font to specific language content */
    .lang-ar {
      font-family: 'CustomArabic', Arial, sans-serif;
    }

    .lang-en {
      font-family: 'CustomCalibri', Arial, sans-serif;
    }

    .logo {
      display: flex;
      gap: 5px;
      border-radius: 25px;
      padding: 5px;
      transition: all 0.3s ease;
      height: 95px;
      justify-content: space-between;
      align-content: space-between;
      align-items: center;
    }

    .logo img {
      height: 80px;
      width: auto;
      max-width: 150px;
      object-fit: contain;
      transition: all 0.3s ease;
    }

    .top-btn {
      position: fixed;
      top: 20px;
      z-index: 1000;
      display: flex;
      gap: 5px;
      background: var(--card-bg);
      border-radius: 25px;
      padding: 5px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
      border: 1px solid rgba(248, 248, 248, 0.1);
    }

    /* Language Switcher */
    .language-switcher {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 1000;
    }

    .lang-dropdown {
      position: relative;
      display: inline-block;
    }

    .lang-current {
      background: var(--card-bg);
      border: 1px solid rgba(248, 248, 248, 0.1);
      color: var(--text-color);
      padding: 8px 16px;
      border-radius: 25px;
      cursor: pointer;
      font-weight: 600;
      font-size: 14px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
      display: flex;
      align-items: center;
      gap: 8px;
      min-width: 60px;
      justify-content: center;
      font-family: inherit;
    }

    .lang-current:hover {
      opacity: 1;
      transform: translateY(-1px);
    }

    .lang-options {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: var(--card-bg);
      border: 1px solid rgba(248, 248, 248, 0.1);
      border-radius: 15px;
      margin-top: 5px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
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
      font-weight: 600;
      font-size: 14px;
      transition: all 0.3s ease;
      text-align: center;
      font-family: inherit;
    }

    .lang-btn:hover {
      background: var(--accent-color);
      color: var(--primary-color);
      transform: translateY(-1px);
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

    h1 {
      margin-bottom: 2rem;
      font-weight: 600;
      font-size: 2.5rem;
      color: var(--primary-color);
      text-align: center;
      transition: color 0.4s;
      font-family: inherit;
    }

    .categories {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      justify-content: center;
      margin-bottom: 2rem;
    }

    .categories button {
      background: transparent;
      border: 2px solid var(--accent-color);
      color: var(--text-color);
      padding: 0.5rem 1.25rem;
      border-radius: 30px;
      cursor: pointer;
      font-weight: 600;
      font-size: 1rem;
      transition: all 0.3s ease;
      font-family: inherit;
    }

    .categories button.active,
    .categories button:hover {
      background: var(--accent-color);
      border-color: var(--accent-color);
      color: var(--primary-color);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(70, 71, 71, 0.3);
    }

    .menu {
      max-width: 1000px;
      width: 100%;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
    }

    .menu-item {
      background: var(--card-bg);
      border-radius: 15px;
      box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      cursor: default;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      opacity: 0;
      transform: translateY(30px);
      border: 1px solid rgba(248, 248, 248, 0.1);
    }

    .menu-item.visible {
      opacity: 1;
      transform: translateY(0);
      transition: opacity 0.6s ease, transform 0.6s ease;
    }

    .menu-item:hover {
      transform: translateY(-8px);
      box-shadow: 0 15px 25px rgba(0, 0, 0, 0.4);
      border-color: var(--accent-color);
    }

    .menu-item img {
      width: 100%;
      height: 220px;
      /* Increased from 180px */
      object-fit: cover;
      transition: transform 0.4s ease;
    }

    .menu-item:hover img {
      transform: scale(1.05);
    }

    .image-placeholder {
      width: 100%;
      height: 220px;
      /* Increased from 180px */
      background: linear-gradient(135deg, var(--accent-color) 0%, var(--hover-color) 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 3rem;
      color: var(--primary-color);
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .info {
      padding: 1rem 1.25rem 1.5rem;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      font-family: inherit;
    }

    .info h3 {
      margin: 0 0 0.7rem;
      font-weight: 600;
      font-size: 1.25rem;
      color: var(--primary-color);
      transition: color 0.4s;
      word-wrap: break-word;
      font-family: inherit;
    }

    .info .description {
      margin: 0.5rem 0;
      font-size: 0.9rem;
      color: #ccc;
      line-height: 1.4;
      font-style: italic;
      opacity: 0.9;
      font-family: inherit;
    }

    .info .price {
      margin: 0;
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--text-color);
      font-family: inherit;
    }

    .info p {
      margin: 0;
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--text-color);
      font-family: inherit;
    }

    .loading {
      text-align: center;
      padding: 2rem;
      color: var(--text-color);
      font-family: inherit;
    }

    .error {
      text-align: center;
      color: #ff6b6b;
      padding: 2rem;
      background: var(--card-bg);
      border-radius: 15px;
      margin: 2rem;
      border: 1px solid rgba(255, 107, 107, 0.3);
      font-family: inherit;
    }

    .empty-menu {
      text-align: center;
      padding: 3rem 2rem;
      background: var(--card-bg);
      border-radius: 15px;
      color: var(--text-color);
      border: 1px solid rgba(248, 248, 248, 0.1);
      font-family: inherit;
    }

    .empty-menu h3 {
      color: var(--primary-color);
      margin-bottom: 1rem;
      font-family: inherit;
    }

    footer {
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      background: var(--footer-bg);
      color: var(--primary-color);
      padding: 1rem 2rem;
      font-weight: 600;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-family: inherit;
      box-shadow: 0 -4px 8px rgba(0, 0, 0, 0.3);
      z-index: 1000;
      transition: background-color 0.4s;
      border-top: 1px solid var(--border-color);
    }

    .spinner {
      border: 3px solid var(--accent-color);
      border-top: 3px solid var(--primary-color);
      border-radius: 50%;
      width: 40px;
      height: 40px;
      animation: spin 1s linear infinite;
      margin: 0 auto 1rem;
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
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    .error button {
      background: var(--accent-color);
      color: var(--primary-color);
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 5px;
      cursor: pointer;
      margin-top: 1rem;
      transition: all 0.3s ease;
      font-family: inherit;
    }

    .error button:hover {
      background: var(--hover-color);
      transform: translateY(-1px);
    }

    /* Responsive Styles */

    /* Large Desktop screens - even larger images */
    @media (min-width: 1440px) {

      .menu-item img,
      .image-placeholder {
        height: 280px;
      }

      .logo img {
        height: 100px;
        max-width: 180px;
      }
    }

    /* Standard Desktop */
    @media (min-width: 1200px) and (max-width: 1439px) {

      .menu-item img,
      .image-placeholder {
        height: 250px;
      }

      .logo img {
        height: 90px;
        max-width: 160px;
      }
    }

    @media (max-width: 1024px) {

      .menu-item img,
      .image-placeholder {
        height: 200px;
        /* Increased from default */
      }

      .logo img {
        height: 60px;
        /* Increased from 45px */
        max-width: 140px;
      }
    }

    @media (max-width: 768px) {
      body {
        padding: 0.8rem 0.8rem 2rem;
      }

      .menu-item img,
      .image-placeholder {
        height: 180px;
        /* Increased */
      }

      .logo {
        margin: 1rem 0 0.8rem 0;
        height: 75px;
      }

      .logo img {
        height: 55px;
        /* Increased from 40px */
        max-width: 130px;
      }

      .categories {
        margin-bottom: 1rem;
      }

      .empty-menu {
        padding: 1.5rem 1rem;
        margin: 0 0.5rem;
      }
    }

    @media (max-width: 480px) {
      .menu {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
      }

      .menu-item img,
      .image-placeholder {
        height: 160px;
        /* Increased */
      }

      h1 {
        font-size: 2rem;
        margin-top: 60px;
        /* Account for language switcher */
      }

      .categories button {
        font-size: 0.9rem;
        padding: 0.4rem 1rem;
      }

      .language-switcher {
        top: 10px;
        right: 10px;
      }

      .lang-current {
        padding: 6px 12px;
        font-size: 13px;
        min-width: 50px;
      }

      .top-btn {
        top: 10px;
      }

      .logo {
        top: 10px;
        left: 10px;
        padding: 3px;
      }

      .logo img {
        height: 45px;
        /* Increased from 35px */
        max-width: 110px;
      }
    }

    @media (max-width: 360px) {

      .menu-item img,
      .image-placeholder {
        height: 140px;
        /* Increased */
      }

      .logo {
        top: 8px;
        left: 8px;
        padding: 2px;
      }

      .logo img {
        width: 100px;
        height: 100px;
      }

      .language-switcher {
        top: 8px;
        right: 8px;
      }

      .lang-current {
        padding: 6px 8px;
        font-size: 12px;
        min-width: 45px;
      }

      .top-btn {
        top: 8px;
        padding: 3px;
      }
    }

    @media (max-width: 768px) {
      footer {
        flex-direction: column;
        gap: 0.5rem;
        padding: 1rem;
        text-align: center;
      }

      footer div {
        font-size: 0.9rem;
      }
    }

    /* Animation for language change */
    .fade-out {
      opacity: 0;
      transform: translateY(20px);
      transition: opacity 0.3s ease, transform 0.3s ease;
    }

    .fade-in {
      opacity: 1;
      transform: translateY(0);
      transition: opacity 0.3s ease, transform 0.3s ease;
    }
  </style>
</head>

<body>

  <!-- Language Switcher -->
  <div class="language-switcher" id="languageSwitcher">
    <div class="lang-dropdown" id="langDropdown">
      <button class="lang-current" id="langCurrent">EN</button>
      <div class="lang-options" id="langOptions">
        <button class="lang-btn" data-lang="en">EN</button>
        <button class="lang-btn" data-lang="ar">AR</button>
        <button class="lang-btn" data-lang="ku">KU</button>
      </div>
    </div>
  </div>
  <!-- <div class="top-btn">
    <button class="lang-btn">⬆</button>
  </div> -->


  <!-- Loading Overlay -->
  <div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
  </div>

  <h1 id="pageTitle"></h1>

  <img id="logo" class="logo" src="../uploads/logo for menu.png" alt="Living Room Restaurant Logo" />

  <div class="categories" id="categories">
    <button class="active" onclick="filterMenu('All', this)" id="allButton">All</button>
    <!-- Dynamic categories will be loaded here -->
  </div>

  <div class="menu" id="menu">
    <div class="loading">
      <div class="spinner"></div>
      Loading menu...
    </div>
  </div>

  <!-- <footer id="footer">
    <div id="welcomeText"> Welcome To Living Room</div>
    <div id="phoneText">📞 +1 234 567 8901</div>
  </footer> -->

  <script>
    let menuItems = [];
    let categories = [];
    let currentLanguage = 'ar';
    let uiLabels = {};
    let languageInfo = {};

    const MENU_TYPE_ID = 1;

    // Category emoji mapping
    const categoryEmojis = {
      // 'Starters': '🥗', 'Appetizers': '🥗', 'المقبلات': '🥗', 'پێشخۆراک': '🥗',
      // 'Main': '', 'Main Course': '', 'الأطباق الرئيسية': '', 'خواردنی سەرەکی': '',
      // 'Desserts': '🍰', 'الحلويات': '🍰', 'شیرینی': '🍰',
      // 'Drinks': '🥤', 'Beverages': '🥤', 'المشروبات': '🥤', 'خواردنەوە': '🥤',
      // 'Salads': '🥗', 'السلطات': '🥗', 'زەڵاتە': '🥗',
      // 'Soups': '🍲', 'الشوربات': '🍲', 'شۆربا': '🍲',
      // 'default': ''
    };

    // Initialize language from localStorage or default
    function initializeLanguage() {
      const savedLang = localStorage.getItem('selectedLanguage') || 'en';
      currentLanguage = savedLang;
      updateLanguageDisplay(savedLang);
      loadMenu();
    }

    // Update language display in dropdown
    function updateLanguageDisplay(lang) {
      const langCurrent = document.getElementById('langCurrent');
      langCurrent.textContent = lang.toUpperCase();
    }

    // Toggle language dropdown
    function toggleLanguageDropdown() {
      const dropdown = document.getElementById('langDropdown');
      dropdown.classList.toggle('open');
    }

    // Close dropdown when clicking outside
    function closeLanguageDropdown() {
      const dropdown = document.getElementById('langDropdown');
      dropdown.classList.remove('open');
    }

    // Show loading overlay
    function showLoading() {
      document.getElementById('loadingOverlay').classList.add('active');
    }

    // Hide loading overlay
    function hideLoading() {
      document.getElementById('loadingOverlay').classList.remove('active');
    }

    // Update page direction and language
    function updatePageDirection(direction, lang) {
      const html = document.documentElement;
      html.setAttribute('dir', direction);
      html.setAttribute('lang', lang);

      // Apply appropriate font class to body based on language
      const body = document.body;
      body.classList.remove('lang-en', 'lang-ar', 'lang-ku');
      if (lang === 'ar') {
        body.classList.add('lang-ar');
      } else {
        body.classList.add('lang-en');
      }
    }

    // Update UI labels
    function updateUILabels() {
      if (!uiLabels) return;

      // Update page title
      // const titleText = currentLanguage === 'ar' ? 'قائمة طعامنا اللذيذة' : 
      //                  currentLanguage === 'ku' ? 'مینووی خۆراکی خۆشمان' : 
      //                  'Our Delicious Menu';
      // document.getElementById('pageTitle').textContent = titleText;

      // Update footer
      // document.getElementById('welcomeText').textContent = 
      //   ` ${uiLabels.welcome || 'Welcome To Living Room'}`;
      // document.getElementById('phoneText').textContent = 
      //   `📞 ${uiLabels.phone || '+1 234 567 8901'}`;

      // Update all button
      document.getElementById('allButton').textContent = uiLabels.all || 'All';
    }

    // Language change handler
    function changeLanguage(lang) {
      if (lang === currentLanguage) return;

      showLoading();
      currentLanguage = lang;
      localStorage.setItem('selectedLanguage', lang);
      updateLanguageDisplay(lang);
      closeLanguageDropdown();

      // Add fade out effect
      document.getElementById('menu').classList.add('fade-out');

      setTimeout(() => {
        loadMenu();
      }, 300);
    }

    // Fetch menu data from PHP API
    async function loadMenu() {
      try {
        console.log(`Loading menu for language: ${currentLanguage}`);
        const response = await fetch(`../menu-api.php?lang=${currentLanguage}&menu_type=1`);

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        console.log('API Response:', data);

        if (data.error) {
          throw new Error(data.error);
        }

        if (!data.success || !Array.isArray(data.data)) {
          throw new Error('Unexpected API response format');
        }

        // Update language info and UI
        languageInfo = data.language;
        uiLabels = data.ui_labels;

        // Update page direction
        const currentLangInfo = languageInfo.current;
        if (currentLangInfo) {
          updatePageDirection(currentLangInfo.direction, currentLangInfo.code);
        }

        // Update UI labels
        updateUILabels();

        // Check if we have any items
        if (!data.data || data.data.length === 0) {
          showEmptyMenu();
          hideLoading();
          return;
        }

        menuItems = data.data;
        categories = data.categories || [];

        console.log('Categories found:', categories);

        loadCategories();
        renderMenu(menuItems);
        hideLoading();

        // Remove fade out and add fade in
        const menuContainer = document.getElementById('menu');
        menuContainer.classList.remove('fade-out');
        menuContainer.classList.add('fade-in');

      } catch (error) {
        console.error('Error loading menu:', error);
        showError(error.message);
        hideLoading();
      }
    }

    function showError(message) {
      document.getElementById('menu').innerHTML = `
    <div class="error">
      <h3>😔 ${currentLanguage === 'ar' ? 'عذراً! حدث خطأ' :
          currentLanguage === 'ku' ? 'ببوورە! هەڵەیەک ڕوویدا' :
            'Oops! Something went wrong'}</h3>
      <p><strong>Error:</strong> ${message}</p>
      <button onclick="loadMenu()">${uiLabels.retry || 'Try Again'}</button>
    </div>
  `;
    }

    function showEmptyMenu() {
      const emptyTitle = currentLanguage === 'ar' ? 'القائمة قريباً!' :
        currentLanguage === 'ku' ? 'مینوو بەزووەوە دێت!' :
        'Menu Coming Soon!';
      const emptyText = currentLanguage === 'ar' ? 'نحن نحضر شيئاً لذيذاً لك' :
        currentLanguage === 'ku' ? 'شتێکی خۆشمان بۆ ئامادە دەکەیت' :
        'We\'re preparing something delicious for you';

      document.getElementById('menu').innerHTML = `
    <div class="empty-menu">
      <h3> ${emptyTitle}</h3>
      <p>${emptyText}</p>
    </div>
  `;
    }

    // Load category buttons dynamically
    function loadCategories() {
      const categoriesContainer = document.getElementById('categories');

      // Keep the "All" button and add dynamic categories
      const allButton = categoriesContainer.querySelector('button');
      categoriesContainer.innerHTML = '';
      categoriesContainer.appendChild(allButton);

      // Add dynamic category buttons
      categories.forEach(category => {
        const button = document.createElement('button');
        const emoji = categoryEmojis[category] || categoryEmojis.default;
        button.textContent = `${emoji} ${category}`;
        button.onclick = () => filterMenu(category, button);
        categoriesContainer.appendChild(button);
      });
    }

    async function renderMenu(items) {
      const menuContainer = document.getElementById('menu');

      if (items.length === 0) {
        showEmptyMenu();
        return;
      }

      menuContainer.innerHTML = '';

      for (const item of items) {
        const card = document.createElement('div');
        card.classList.add('menu-item');

        const price = item.price;
        const emoji = categoryEmojis[item.category] || categoryEmojis.default;

        // Check if image exists
        let imageHTML;
        if (item.image) {
          const imageExists = await checkImageExists(item.image);
          if (imageExists) {
            imageHTML = `<img src="../${item.image}" alt="${item.name}" />`;
          } else {
            imageHTML = `<div class="image-placeholder">${emoji}</div>`;
          }
        } else {
          imageHTML = `<div class="image-placeholder">${emoji}</div>`;
        }

        // Build description HTML - only show if description exists and is not empty
        const descriptionHTML = item.description && item.description.trim() ?
          `<p class="description">${item.description}</p>` :
          '';

        card.innerHTML = `
          ${imageHTML}
          <div class="info">
            <h3>${item.name}</h3>
            ${descriptionHTML}
            <p class="price">${price}</p>
          </div>
        `;
        menuContainer.appendChild(card);
      }

      // Animate items on scroll
      observeMenuItems();
    }

    // Function to check if image exists
    async function checkImageExists(src) {
      try {
        const response = await fetch(`../${src}`, {
          method: 'HEAD'
        });
        return response.ok;
      } catch {
        return false;
      }
    }

    function filterMenu(category, btn) {
      // Remove active class from all buttons
      document.querySelectorAll('.categories button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      if (category === 'All' || category === uiLabels.all) {
        renderMenu(menuItems);
      } else {
        const filtered = menuItems.filter(item => item.category === category);
        renderMenu(filtered);
      }
    }

    // Intersection Observer for scroll animations
    function observeMenuItems() {
      const items = document.querySelectorAll('.menu-item');
      const options = {
        threshold: 0.1
      };

      const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            obs.unobserve(entry.target);
          }
        });
      }, options);

      items.forEach(item => observer.observe(item));
    }

    // Language switcher event listeners
    document.addEventListener('DOMContentLoaded', function() {
      // Language dropdown event listeners
      const langCurrent = document.getElementById('langCurrent');
      const langButtons = document.querySelectorAll('.lang-btn');

      langCurrent.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleLanguageDropdown();
      });

      langButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.stopPropagation();
          changeLanguage(this.dataset.lang);
        });
      });

      // Close dropdown when clicking outside
      document.addEventListener('click', function() {
        closeLanguageDropdown();
      });

      // Initialize
      initializeLanguage();
    });
  </script>

</body>

</html>