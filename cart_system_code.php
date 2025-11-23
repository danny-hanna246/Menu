/**
* نظام السلة والطلب عبر واتساب
* يتم إضافة هذا الكود في نهاية ملف index.php (قبل </body>)
*
* المتطلبات:
* 1. تأكد من وجود رقم الواتساب في قاعدة البيانات (جدول settings)
* 2. يعمل فقط عندما $selectedLocation === 'delivery'
*/

<?php
// الحصول على رقم الواتساب من الإعدادات
$whatsappNumber = '9647xxxxxxxxx'; // رقم افتراضي
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'whatsapp_number'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $whatsappNumber = $result['setting_value'];
    }
} catch (Exception $e) {
    logError("Failed to get WhatsApp number", ['error' => $e->getMessage()]);
}
?>

<!-- نافذة السلة المنبثقة (تظهر فقط للتوصيل) -->
<?php if ($selectedLocation === 'delivery'): ?>
    <div id="cartModal" class="cart-modal">
        <div class="cart-content">
            <div class="cart-header">
                <h2>🛒 <?= $lang === 'ar' ? 'سلة الطلبات' : ($lang === 'ku' ? 'سەبەتەی داواکاری' : 'Your Cart') ?></h2>
                <span class="cart-close" onclick="closeCart()">&times;</span>
            </div>

            <div id="cartItems" class="cart-items">
                <!-- سيتم ملؤها ديناميكياً بواسطة JavaScript -->
            </div>

            <div class="cart-footer">
                <div class="cart-total">
                    <span><?= $lang === 'ar' ? 'المجموع:' : ($lang === 'ku' ? 'کۆی گشتی:' : 'Total:') ?></span>
                    <span id="cartTotal">IQD 0.00</span>
                </div>
                <button class="checkout-btn" onclick="checkout()">
                    <?= $lang === 'ar' ? '📱 إرسال الطلب عبر واتساب' : ($lang === 'ku' ? '📱 ناردن بە واتساپ' : '📱 Send Order via WhatsApp') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- زر فتح السلة العائم -->
    <div class="floating-cart-btn" onclick="openCart()" id="floatingCartBtn">
        <span class="cart-icon">🛒</span>
        <span class="cart-count" id="cartCount">0</span>
    </div>

    <style>
        /* نافذة السلة */
        .cart-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .cart-content {
            background: #1a1a1a;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            border-radius: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 2px solid rgba(239, 68, 68, 0.5);
        }

        .cart-header {
            background: #ef4444;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-header h2 {
            color: white;
            font-size: 22px;
            margin: 0;
        }

        .cart-close {
            font-size: 32px;
            color: white;
            cursor: pointer;
            line-height: 1;
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        .cart-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .cart-item-info {
            flex: 1;
        }

        .cart-item-name {
            color: #f8f8f8;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .cart-item-price {
            color: #ef4444;
            font-size: 14px;
        }

        .cart-item-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.1);
            padding: 5px 10px;
            border-radius: 20px;
        }

        .qty-btn {
            background: #ef4444;
            color: white;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qty-btn:hover {
            background: #dc2626;
        }

        .item-quantity {
            color: #f8f8f8;
            font-size: 16px;
            font-weight: 600;
            min-width: 30px;
            text-align: center;
        }

        .remove-btn {
            background: transparent;
            border: 1px solid #ef4444;
            color: #ef4444;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .remove-btn:hover {
            background: #ef4444;
            color: white;
        }

        .cart-footer {
            background: rgba(0, 0, 0, 0.5);
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            color: #f8f8f8;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .cart-total span:last-child {
            color: #ef4444;
        }

        .checkout-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #25D366, #128C7E);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4);
        }

        .empty-cart {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-cart-icon {
            font-size: 64px;
            margin-bottom: 15px;
        }

        /* زر السلة العائم */
        .floating-cart-btn {
            position: fixed;
            bottom: 30px;
            <?= $dir === 'rtl' ? 'left: 30px;' : 'right: 30px;' ?>width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(239, 68, 68, 0.5);
            z-index: 9999;
            transition: all 0.3s;
        }

        .floating-cart-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(239, 68, 68, 0.7);
        }

        .cart-icon {
            font-size: 28px;
        }

        .cart-count {
            position: absolute;
            top: -5px;
            <?= $dir === 'rtl' ? 'left: -5px;' : 'right: -5px;' ?>background: #22c55e;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            border: 2px solid #1a1a1a;
        }

        /* زر إضافة للسلة في بطاقات المنتجات */
        .add-to-cart-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .add-to-cart-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        @media (max-width: 768px) {
            .cart-content {
                width: 95%;
                max-height: 85vh;
            }

            .floating-cart-btn {
                width: 55px;
                height: 55px;
                bottom: 20px;
                <?= $dir === 'rtl' ? 'left: 20px;' : 'right: 20px;' ?>
            }
        }
    </style>

    <script>
        // نظام إدارة السلة
        const cart = {
            items: [],

            init() {
                // تحميل السلة من localStorage
                const saved = localStorage.getItem('restaurantCart');
                if (saved) {
                    this.items = JSON.parse(saved);
                    this.updateUI();
                }
            },

            add(id, name, price) {
                const existing = this.items.find(item => item.id === id);
                if (existing) {
                    existing.quantity++;
                } else {
                    this.items.push({
                        id: id,
                        name: name,
                        price: parseFloat(price),
                        quantity: 1
                    });
                }
                this.save();
                this.updateUI();
                this.showNotification('<?= $lang === 'ar' ? 'تمت الإضافة للسلة' : ($lang === 'ku' ? 'زیادکرا بۆ سەبەتە' : 'Added to cart') ?>');
            },

            remove(id) {
                this.items = this.items.filter(item => item.id !== id);
                this.save();
                this.updateUI();
            },

            updateQuantity(id, change) {
                const item = this.items.find(item => item.id === id);
                if (item) {
                    item.quantity += change;
                    if (item.quantity <= 0) {
                        this.remove(id);
                    } else {
                        this.save();
                        this.updateUI();
                    }
                }
            },

            clear() {
                this.items = [];
                this.save();
                this.updateUI();
            },

            save() {
                localStorage.setItem('restaurantCart', JSON.stringify(this.items));
            },

            getTotal() {
                return this.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            },

            updateUI() {
                // تحديث عدد العناصر
                const count = this.items.reduce((sum, item) => sum + item.quantity, 0);
                document.getElementById('cartCount').textContent = count;

                // تحديث محتوى السلة
                const cartItems = document.getElementById('cartItems');
                if (this.items.length === 0) {
                    cartItems.innerHTML = `
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <p><?= $lang === 'ar' ? 'السلة فارغة' : ($lang === 'ku' ? 'سەبەتە بەتاڵە' : 'Cart is empty') ?></p>
                </div>
            `;
                } else {
                    cartItems.innerHTML = this.items.map(item => `
                <div class="cart-item">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-price">IQD ${(item.price * item.quantity).toFixed(2)}</div>
                    </div>
                    <div class="cart-item-controls">
                        <div class="quantity-controls">
                            <button class="qty-btn" onclick="cart.updateQuantity(${item.id}, -1)">−</button>
                            <span class="item-quantity">${item.quantity}</span>
                            <button class="qty-btn" onclick="cart.updateQuantity(${item.id}, 1)">+</button>
                        </div>
                        <button class="remove-btn" onclick="cart.remove(${item.id})">🗑️</button>
                    </div>
                </div>
            `).join('');
                }

                // تحديث المجموع
                document.getElementById('cartTotal').textContent = `IQD ${this.getTotal().toFixed(2)}`;
            },

            showNotification(message) {
                // إظهار إشعار بسيط
                const notification = document.createElement('div');
                notification.textContent = message;
                notification.style.cssText = `
            position: fixed;
            top: 20px;
            <?= $dir === 'rtl' ? 'right: 20px;' : 'left: 20px;' ?>
            background: #22c55e;
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            z-index: 10001;
            animation: slideIn 0.3s ease-out;
        `;
                document.body.appendChild(notification);
                setTimeout(() => {
                    notification.style.animation = 'slideOut 0.3s ease-out';
                    setTimeout(() => notification.remove(), 300);
                }, 2000);
            }
        };

        // وظائف التحكم بالسلة
        function addToCart(id, name, price) {
            cart.add(id, name, price);
        }

        function openCart() {
            document.getElementById('cartModal').style.display = 'flex';
        }

        function closeCart() {
            document.getElementById('cartModal').style.display = 'none';
        }

        function checkout() {
            if (cart.items.length === 0) {
                alert('<?= $lang === 'ar' ? 'السلة فارغة!' : ($lang === 'ku' ? 'سەبەتە بەتاڵە!' : 'Cart is empty!') ?>');
                return;
            }

            // إنشاء رسالة الطلب
            const restaurantName = '<?= $lang === 'ar' ? 'مطعم الغرفة الحية' : ($lang === 'ku' ? 'چێشتخانەی ژووری ژیان' : 'Living Room Restaurant') ?>';
            let message = `*${restaurantName}*\n`;
            message += `<?= $lang === 'ar' ? '🛒 طلب جديد' : ($lang === 'ku' ? '🛒 داواکاریی نوێ' : '🛒 New Order') ?>\n\n`;

            cart.items.forEach(item => {
                message += `▫️ ${item.name}\n`;
                message += `   ${item.quantity} × IQD ${item.price} = IQD ${(item.price * item.quantity).toFixed(2)}\n\n`;
            });

            message += `*<?= $lang === 'ar' ? 'المجموع' : ($lang === 'ku' ? 'کۆی گشتی' : 'Total') ?>: IQD ${cart.getTotal().toFixed(2)}*`;

            // إنشاء رابط واتساب
            const whatsappURL = `https://wa.me/<?= $whatsappNumber ?>?text=${encodeURIComponent(message)}`;

            // فتح واتساب
            window.open(whatsappURL, '_blank');

            // مسح السلة بعد الإرسال (اختياري)
            // cart.clear();
            // closeCart();
        }

        // تهيئة السلة عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', () => {
            cart.init();
        });

        // إضافة أنيميشن
        const style = document.createElement('style');
        style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(<?= $dir === 'rtl' ? '100%' : '-100%' ?>);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(<?= $dir === 'rtl' ? '100%' : '-100%' ?>);
            opacity: 0;
        }
    }
`;
        document.head.appendChild(style);
    </script>

<?php endif; // نهاية شرط التوصيل 
?>

<!-- إضافة أزرار إضافية في نهاية الصفحة -->
<div style="position: fixed; bottom: 20px; <?= $dir === 'rtl' ? 'left: 20px;' : 'right: 20px;' ?>; display: flex; gap: 10px; z-index: 9998; flex-direction: column;">
    <!-- تغيير اللغة -->
    <a href="?change_lang=1" style="background: rgba(0,0,0,0.8); padding: 12px 20px; border-radius: 10px; color: white; text-decoration: none; text-align: center; backdrop-filter: blur(10px);">
        🌐 <?= $lang === 'ar' ? 'تغيير اللغة' : ($lang === 'ku' ? 'گۆڕینی زمان' : 'Change Language') ?>
    </a>

    <!-- صفحة التقييم -->
    <a href="rating.php" style="background: #ef4444; padding: 12px 20px; border-radius: 10px; color: white; text-decoration: none; text-align: center;">
        ⭐ <?= $lang === 'ar' ? 'قيّم خدمتنا' : ($lang === 'ku' ? 'هەڵسەنگاندن' : 'Rate Us') ?>
    </a>
</div>

/**
* ملاحظات الاستخدام:
*
* 1. في بطاقات عرض المنتجات، أضف زر "Add to Cart" بهذا الشكل:
*
* <?php if ($selectedLocation === 'delivery'): ?>
    * <button class="add-to-cart-btn" onclick="addToCart(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>', <?= $item['price'] ?>)">
        * <?= $lang === 'ar' ? 'إضافة للسلة' : ($lang === 'ku' ? 'زیادکردن' : 'Add to Cart') ?>
        * </button>
    * <?php endif; ?>
*
* 2. تأكد من تحديث رقم الواتساب في المتغير $whatsappNumber
*
* 3. يتم حفظ السلة في localStorage للحفاظ على البيانات عند تحديث الصفحة
*
* 4. عند الضغط على "Send Order"، يفتح واتساب مع رسالة منسقة
*/