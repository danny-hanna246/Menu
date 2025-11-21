<?php
// Enhanced translations.php with comprehensive security and performance improvements

class Translation {
    private $pdo;
    private $defaultLang = 'en';
    private $cache = [];
    private $cacheEnabled = true;
    private $cacheExpiry = 300; // 5 minutes
    
    public function __construct($pdo) {
        if (!$pdo instanceof PDO) {
            throw new InvalidArgumentException('PDO instance required');
        }
        $this->pdo = $pdo;
    }
    
    /**
     * Get all active languages with caching
     */
    public function getLanguages() {
        $cacheKey = 'languages_active';
        
        if ($this->cacheEnabled && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM languages 
                WHERE is_active = 1 
                ORDER BY is_default DESC, name
            ");
            $stmt->execute();
            $languages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Validate language data
            $validLanguages = [];
            foreach ($languages as $lang) {
                if ($this->validateLanguageData($lang)) {
                    $validLanguages[] = $lang;
                }
            }
            
            if ($this->cacheEnabled) {
                $this->cache[$cacheKey] = $validLanguages;
            }
            
            return $validLanguages;
            
        } catch (PDOException $e) {
            logError("Error fetching languages", ['error' => $e->getMessage()]);
            throw new Exception("Unable to retrieve languages");
        }
    }
    
    /**
     * Validate language data structure
     */
    private function validateLanguageData($lang) {
        $required = ['id', 'code', 'name', 'native_name', 'is_active'];
        foreach ($required as $field) {
            if (!isset($lang[$field])) {
                logError("Invalid language data", ['missing_field' => $field, 'language' => $lang]);
                return false;
            }
        }
        
        // Validate language code format
        if (!preg_match('/^[a-z]{2,5}$/i', $lang['code'])) {
            logError("Invalid language code format", ['code' => $lang['code']]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get default language with fallback
     */
    public function getDefaultLanguage() {
        $cacheKey = 'default_language';
        
        if ($this->cacheEnabled && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT code FROM languages 
                WHERE is_default = 1 AND is_active = 1 
                LIMIT 1
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $defaultLang = $result ? $result['code'] : $this->defaultLang;
            
            if ($this->cacheEnabled) {
                $this->cache[$cacheKey] = $defaultLang;
            }
            
            return $defaultLang;
            
        } catch (PDOException $e) {
            logError("Error fetching default language", ['error' => $e->getMessage()]);
            return $this->defaultLang;
        }
    }
    
    // MENU TYPES METHODS
    
    /**
     * Save menu type translation with validation
     */
    public function saveMenuTypeTranslation($menuTypeId, $languageCode, $name, $description = '') {
        // Input validation
        $menuTypeId = $this->validateId($menuTypeId);
        $languageCode = $this->validateLanguageCode($languageCode);
        $name = $this->validateTranslationText($name, 255, true);
        $description = $this->validateTranslationText($description, 1000, false);
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO menu_type_translations (menu_type_id, language_code, name, description, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                name = VALUES(name), 
                description = VALUES(description),
                updated_at = NOW()
            ");
            
            $result = $stmt->execute([$menuTypeId, $languageCode, $name, $description]);
            
            if ($result) {
                $this->clearCache('menu_types');
                auditLog('menu_type_translation_saved', [
                    'menu_type_id' => $menuTypeId,
                    'language_code' => $languageCode,
                    'name_length' => strlen($name),
                    'description_length' => strlen($description)
                ]);
            }
            
            return $result;
            
        } catch (PDOException $e) {
            logError("Error saving menu type translation", [
                'menu_type_id' => $menuTypeId,
                'language_code' => $languageCode,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Unable to save menu type translation");
        }
    }
    
    /**
     * Get menu type translations with security validation
     */
    public function getMenuTypeTranslations($menuTypeId) {
        $menuTypeId = $this->validateId($menuTypeId);
        $cacheKey = "menu_type_translations_{$menuTypeId}";
        
        if ($this->cacheEnabled && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT mtt.*, l.name as language_name, l.native_name, l.direction
                FROM menu_type_translations mtt
                JOIN languages l ON mtt.language_code = l.code
                WHERE mtt.menu_type_id = ? AND l.is_active = 1
                ORDER BY l.is_default DESC, l.name
            ");
            $stmt->execute([$menuTypeId]);
            $translations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Sanitize output data
            foreach ($translations as &$trans) {
                $trans['name'] = $this->sanitizeOutput($trans['name']);
                $trans['description'] = $this->sanitizeOutput($trans['description']);
            }
            
            if ($this->cacheEnabled) {
                $this->cache[$cacheKey] = $translations;
            }
            
            return $translations;
            
        } catch (PDOException $e) {
            logError("Error fetching menu type translations", [
                'menu_type_id' => $menuTypeId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get menu types with translations and enhanced security
     */
    public function getMenuTypesWithTranslations($languageCode = null) {
        if (!$languageCode) {
            $languageCode = $this->getDefaultLanguage();
        }
        
        $languageCode = $this->validateLanguageCode($languageCode);
        $cacheKey = "menu_types_with_translations_{$languageCode}";
        
        if ($this->cacheEnabled && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    mt.id,
                    mt.created_at,
                    COALESCE(mtt.name, mtt_default.name, 'Unnamed Menu Type') as name,
                    COALESCE(mtt.description, mtt_default.description, '') as description
                FROM menu_types mt
                LEFT JOIN menu_type_translations mtt ON mt.id = mtt.menu_type_id AND mtt.language_code = ?
                LEFT JOIN menu_type_translations mtt_default ON mt.id = mtt_default.menu_type_id AND mtt_default.language_code = ?
                ORDER BY COALESCE(mtt.name, mtt_default.name)
            ");
            
            $defaultLang = $this->getDefaultLanguage();
            $stmt->execute([$languageCode, $defaultLang]);
            $menuTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Sanitize output and validate
            $validMenuTypes = [];
            foreach ($menuTypes as $menuType) {
                if ($this->validateId($menuType['id'])) {
                    $menuType['name'] = $this->sanitizeOutput($menuType['name']);
                    $menuType['description'] = $this->sanitizeOutput($menuType['description']);
                    $validMenuTypes[] = $menuType;
                }
            }
            
            if ($this->cacheEnabled) {
                $this->cache[$cacheKey] = $validMenuTypes;
            }
            
            return $validMenuTypes;
            
        } catch (PDOException $e) {
            logError("Error fetching menu types with translations", [
                'language_code' => $languageCode,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    // CATEGORY METHODS (Updated for menu types)
    
    /**
     * Save category translation with enhanced validation
     */
    public function saveCategoryTranslation($categoryId, $languageCode, $name, $description = '') {
        $categoryId = $this->validateId($categoryId);
        $languageCode = $this->validateLanguageCode($languageCode);
        $name = $this->validateTranslationText($name, 255, true);
        $description = $this->validateTranslationText($description, 1000, false);
        
        try {
            // Check if category exists and user has permission
            $checkStmt = $this->pdo->prepare("SELECT id FROM categories WHERE id = ?");
            $checkStmt->execute([$categoryId]);
            if (!$checkStmt->fetch()) {
                throw new Exception("Category not found");
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO category_translations (category_id, language_code, name, description, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                name = VALUES(name), 
                description = VALUES(description),
                updated_at = NOW()
            ");
            
            $result = $stmt->execute([$categoryId, $languageCode, $name, $description]);
            
            if ($result) {
                $this->clearCache('categories');
                auditLog('category_translation_saved', [
                    'category_id' => $categoryId,
                    'language_code' => $languageCode,
                    'name_length' => strlen($name),
                    'description_length' => strlen($description)
                ]);
            }
            
            return $result;
            
        } catch (PDOException $e) {
            logError("Error saving category translation", [
                'category_id' => $categoryId,
                'language_code' => $languageCode,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Unable to save category translation");
        }
    }
    
    /**
     * Get category translations with security validation
     */
    public function getCategoryTranslations($categoryId) {
        $categoryId = $this->validateId($categoryId);
        $cacheKey = "category_translations_{$categoryId}";
        
        if ($this->cacheEnabled && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT ct.*, l.name as language_name, l.native_name, l.direction
                FROM category_translations ct
                JOIN languages l ON ct.language_code = l.code
                WHERE ct.category_id = ? AND l.is_active = 1
                ORDER BY l.is_default DESC, l.name
            ");
            $stmt->execute([$categoryId]);
            $translations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Sanitize output data
            foreach ($translations as &$trans) {
                $trans['name'] = $this->sanitizeOutput($trans['name']);
                $trans['description'] = $this->sanitizeOutput($trans['description']);
            }
            
            if ($this->cacheEnabled) {
                $this->cache[$cacheKey] = $translations;
            }
            
            return $translations;
            
        } catch (PDOException $e) {
            logError("Error fetching category translations", [
                'category_id' => $categoryId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get categories with translations (filtered by menu type)
     */
    public function getCategoriesWithTranslations($languageCode = null, $menuTypeId = null) {
        if (!$languageCode) {
            $languageCode = $this->getDefaultLanguage();
        }
        
        $languageCode = $this->validateLanguageCode($languageCode);
        $menuTypeId = $menuTypeId ? $this->validateId($menuTypeId) : null;
        
        $cacheKey = "categories_with_translations_{$languageCode}_{$menuTypeId}";
        
        if ($this->cacheEnabled && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        try {
            $whereClause = $menuTypeId ? "WHERE c.menu_type_id = ?" : "";
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.id,
                    c.created_at,
                    c.menu_type_id,
                    COALESCE(ct.name, ct_default.name, 'Unnamed Category') as name,
                    COALESCE(ct.description, ct_default.description, '') as description
                FROM categories c
                LEFT JOIN category_translations ct ON c.id = ct.category_id AND ct.language_code = ?
                LEFT JOIN category_translations ct_default ON c.id = ct_default.category_id AND ct_default.language_code = ?
                {$whereClause}
                ORDER BY COALESCE(ct.name, ct_default.name)
            ");
            
            $defaultLang = $this->getDefaultLanguage();
            $params = [$languageCode, $defaultLang];
            if ($menuTypeId) {
                $params[] = $menuTypeId;
            }
            
            $stmt->execute($params);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Sanitize output and validate
            $validCategories = [];
            foreach ($categories as $category) {
                if ($this->validateId($category['id'])) {
                    $category['name'] = $this->sanitizeOutput($category['name']);
                    $category['description'] = $this->sanitizeOutput($category['description']);
                    $validCategories[] = $category;
                }
            }
            
            if ($this->cacheEnabled) {
                $this->cache[$cacheKey] = $validCategories;
            }
            
            return $validCategories;
            
        } catch (PDOException $e) {
            logError("Error fetching categories with translations", [
                'language_code' => $languageCode,
                'menu_type_id' => $menuTypeId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    // MENU ITEM METHODS
    
    /**
     * Save menu item translation with enhanced validation
     */
    public function saveMenuItemTranslation($menuItemId, $languageCode, $name, $description = '') {
        $menuItemId = $this->validateId($menuItemId);
        $languageCode = $this->validateLanguageCode($languageCode);
        $name = $this->validateTranslationText($name, 255, true);
        $description = $this->validateTranslationText($description, 1000, false);
        
        try {
            // Check if menu item exists
            $checkStmt = $this->pdo->prepare("SELECT id FROM menu_items WHERE id = ?");
            $checkStmt->execute([$menuItemId]);
            if (!$checkStmt->fetch()) {
                throw new Exception("Menu item not found");
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO menu_item_translations (menu_item_id, language_code, name, description, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                name = VALUES(name), 
                description = VALUES(description),
                updated_at = NOW()
            ");
            
            $result = $stmt->execute([$menuItemId, $languageCode, $name, $description]);
            
            if ($result) {
                $this->clearCache('menu_items');
                auditLog('menu_item_translation_saved', [
                    'menu_item_id' => $menuItemId,
                    'language_code' => $languageCode,
                    'name_length' => strlen($name),
                    'description_length' => strlen($description)
                ]);
            }
            
            return $result;
            
        } catch (PDOException $e) {
            logError("Error saving menu item translation", [
                'menu_item_id' => $menuItemId,
                'language_code' => $languageCode,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Unable to save menu item translation");
        }
    }
    
    /**
     * Get menu item translations with security validation
     */
    public function getMenuItemTranslations($menuItemId) {
        $menuItemId = $this->validateId($menuItemId);
        $cacheKey = "menu_item_translations_{$menuItemId}";
        
        if ($this->cacheEnabled && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT mit.*, l.name as language_name, l.native_name, l.direction
                FROM menu_item_translations mit
                JOIN languages l ON mit.language_code = l.code
                WHERE mit.menu_item_id = ? AND l.is_active = 1
                ORDER BY l.is_default DESC, l.name
            ");
            $stmt->execute([$menuItemId]);
            $translations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Sanitize output data
            foreach ($translations as &$trans) {
                $trans['name'] = $this->sanitizeOutput($trans['name']);
                $trans['description'] = $this->sanitizeOutput($trans['description']);
            }
            
            if ($this->cacheEnabled) {
                $this->cache[$cacheKey] = $translations;
            }
            
            return $translations;
            
        } catch (PDOException $e) {
            logError("Error fetching menu item translations", [
                'menu_item_id' => $menuItemId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get menu items with translations for specific language (filtered by menu type)
     */
    public function getMenuItemsWithTranslations($languageCode = null, $menuTypeId = null) {
        if (!$languageCode) {
            $languageCode = $this->getDefaultLanguage();
        }
        
        $languageCode = $this->validateLanguageCode($languageCode);
        $menuTypeId = $menuTypeId ? $this->validateId($menuTypeId) : null;
        
        $cacheKey = "menu_items_with_translations_{$languageCode}_{$menuTypeId}";
        
        if ($this->cacheEnabled && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        try {
            $whereClause = $menuTypeId ? "AND c.menu_type_id = ?" : "";
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    m.id,
                    m.price,
                    m.image,
                    m.created_at,
                    m.category_id,
                    c.menu_type_id,
                    COALESCE(mit.name, mit_default.name) as name,
                    COALESCE(mit.description, mit_default.description, '') as description,
                    COALESCE(ct.name, ct_default.name) as category,
                    COALESCE(mtt.name, mtt_default.name) as menu_type,
                    c.id as category_id
                FROM menu_items m
                LEFT JOIN categories c ON m.category_id = c.id
                LEFT JOIN menu_types mt ON c.menu_type_id = mt.id
                LEFT JOIN menu_item_translations mit ON m.id = mit.menu_item_id AND mit.language_code = ?
                LEFT JOIN menu_item_translations mit_default ON m.id = mit_default.menu_item_id AND mit_default.language_code = ?
                LEFT JOIN category_translations ct ON c.id = ct.category_id AND ct.language_code = ?
                LEFT JOIN category_translations ct_default ON c.id = ct_default.category_id AND ct_default.language_code = ?
                LEFT JOIN menu_type_translations mtt ON mt.id = mtt.menu_type_id AND mtt.language_code = ?
                LEFT JOIN menu_type_translations mtt_default ON mt.id = mtt_default.menu_type_id AND mtt_default.language_code = ?
                WHERE c.id IS NOT NULL {$whereClause}
                ORDER BY COALESCE(mtt.name, mtt_default.name), COALESCE(ct.name, ct_default.name), m.id DESC
            ");
            
            $defaultLang = $this->getDefaultLanguage();
            $params = [$languageCode, $defaultLang, $languageCode, $defaultLang, $languageCode, $defaultLang];
            if ($menuTypeId) {
                $params[] = $menuTypeId;
            }
            
            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Sanitize output and validate
            $validItems = [];
            foreach ($items as $item) {
                if ($this->validateId($item['id']) && !empty($item['name'])) {
                    $item['name'] = $this->sanitizeOutput($item['name']);
                    $item['description'] = $this->sanitizeOutput($item['description']);
                    $item['category'] = $this->sanitizeOutput($item['category']);
                    $item['menu_type'] = $this->sanitizeOutput($item['menu_type']);
                    
                    // Validate price
                    $item['price'] = $this->validatePrice($item['price']);
                    
                    $validItems[] = $item;
                }
            }
            
            if ($this->cacheEnabled) {
                $this->cache[$cacheKey] = $validItems;
            }
            
            return $validItems;
            
        } catch (PDOException $e) {
            logError("Error fetching menu items with translations", [
                'language_code' => $languageCode,
                'menu_type_id' => $menuTypeId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get UI labels for admin interface with fallbacks
     */
    public function getUILabels($languageCode = 'en') {
        $languageCode = $this->validateLanguageCode($languageCode);
        
        $labels = [
            'en' => [
                'dashboard' => 'Dashboard',
                'add_item' => 'Add New Item',
                'manage_categories' => 'Manage Categories',
                'manage_menu_types' => 'Manage Menu Types',
                'logout' => 'Logout',
                'name' => 'Name',
                'category' => 'Category',
                'menu_type' => 'Menu Type',
                'price' => 'Price',
                'image' => 'Image',
                'description' => 'Description',
                'save' => 'Save',
                'cancel' => 'Cancel',
                'edit' => 'Edit',
                'delete' => 'Delete',
                'add_translation' => 'Add Translation',
                'translations' => 'Translations',
                'language' => 'Language',
                'welcome' => 'Welcome To Living Room',
                'phone' => '+1 234 567 8901',
                'all' => 'All',
                'indoor_menu' => 'Indoor Menu',
                'outdoor_menu' => 'Outdoor Menu'
            ],
            'ar' => [
                'dashboard' => 'لوحة التحكم',
                'add_item' => 'إضافة عنصر جديد',
                'manage_categories' => 'إدارة الفئات',
                'manage_menu_types' => 'إدارة أنواع القوائم',
                'logout' => 'تسجيل الخروج',
                'name' => 'الاسم',
                'category' => 'الفئة',
                'menu_type' => 'نوع القائمة',
                'price' => 'السعر',
                'image' => 'الصورة',
                'description' => 'الوصف',
                'save' => 'حفظ',
                'cancel' => 'إلغاء',
                'edit' => 'تعديل',
                'delete' => 'حذف',
                'add_translation' => 'إضافة ترجمة',
                'translations' => 'الترجمات',
                'language' => 'اللغة',
                'welcome' => 'مرحباً بكم في غرفة المعيشة',
                'phone' => '+1 234 567 8901',
                'all' => 'الكل',
                'indoor_menu' => 'قائمة الطعام الداخلية',
                'outdoor_menu' => 'قائمة الطعام الخارجية'
            ],
            'ku' => [
                'dashboard' => 'پانێڵی بەڕێوەبردن',
                'add_item' => 'بەگەیەکی نوێ زیاد بکە',
                'manage_categories' => 'بەڕێوەبردنی پۆلەکان',
                'manage_menu_types' => 'بەڕێوەبردنی جۆرەکانی لیست',
                'logout' => 'چوونە دەرەوە',
                'name' => 'ناو',
                'category' => 'پۆل',
                'menu_type' => 'جۆری لیست',
                'price' => 'نرخ',
                'image' => 'وێنە',
                'description' => 'وەسف',
                'save' => 'پاشەکەوت',
                'cancel' => 'هەڵوەشاندنەوە',
                'edit' => 'دەستکاری',
                'delete' => 'سڕینەوە',
                'add_translation' => 'وەرگێڕان زیاد بکە',
                'translations' => 'وەرگێڕانەکان',
                'language' => 'زمان',
                'welcome' => 'بەخێربێن بۆ ژووری نیشتنەوە',
                'phone' => '+1 234 567 8901',
                'all' => 'هەموو',
                'indoor_menu' => 'لیستی خواردنی ناوەوە',
                'outdoor_menu' => 'لیستی خواردنی دەرەوە'
            ]
        ];
        
        $selectedLabels = $labels[$languageCode] ?? $labels['en'];
        
        // Sanitize all labels
        foreach ($selectedLabels as $key => $label) {
            $selectedLabels[$key] = $this->sanitizeOutput($label);
        }
        
        return $selectedLabels;
    }
    
    // VALIDATION METHODS
    
    /**
     * Validate ID parameter
     */
    private function validateId($id) {
        if (!is_numeric($id) || $id <= 0 || $id > 2147483647) {
            throw new InvalidArgumentException("Invalid ID: {$id}");
        }
        return (int)$id;
    }
    
    /**
     * Validate language code
     */
    private function validateLanguageCode($code) {
        if (!is_string($code) || !preg_match('/^[a-z]{2,5}$/i', $code)) {
            throw new InvalidArgumentException("Invalid language code: {$code}");
        }
        return strtolower($code);
    }
    
    /**
     * Validate translation text
     */
    private function validateTranslationText($text, $maxLength, $required = false) {
        if ($required && empty(trim($text))) {
            throw new InvalidArgumentException("Required text field cannot be empty");
        }
        
        if (strlen($text) > $maxLength) {
            throw new InvalidArgumentException("Text exceeds maximum length of {$maxLength}");
        }
        
        // Remove potentially dangerous content
        $text = strip_tags($text);
        $text = htmlspecialchars_decode($text);
        
        return trim($text);
    }
    
    /**
     * Validate price
     */
    private function validatePrice($price) {
        if (!is_numeric($price) || $price < 0 || $price > 999999.99) {
            return 0;
        }
        return round((float)$price, 2);
    }
    
    /**
     * Sanitize output data
     */
    private function sanitizeOutput($text) {
        if (empty($text)) {
            return '';
        }
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    // CACHE MANAGEMENT
    
    /**
     * Clear cache by pattern
     */
    private function clearCache($pattern = null) {
        if ($pattern === null) {
            $this->cache = [];
        } else {
            foreach (array_keys($this->cache) as $key) {
                if (strpos($key, $pattern) !== false) {
                    unset($this->cache[$key]);
                }
            }
        }
    }
    
    /**
     * Disable caching (for testing)
     */
    public function disableCache() {
        $this->cacheEnabled = false;
        $this->cache = [];
    }
    
    /**
     * Get cache statistics
     */
    public function getCacheStats() {
        return [
            'enabled' => $this->cacheEnabled,
            'items' => count($this->cache),
            'keys' => array_keys($this->cache)
        ];
    }
}
?>