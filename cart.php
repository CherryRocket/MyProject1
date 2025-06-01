<?php
// cart.php
require_once 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Получаем данные пользователя
$stmt_user = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();

$is_admin = ($user['role_id'] == 1); // Проверка администратора

// Обработка удаления товара
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    $product_id = intval($_POST['product_id']);
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка при удалении']);
    }
    exit;
}

// Обработка очистки корзины
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cart'])) {
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка при очистке корзины']);
    }
    exit;
}

// Обработка обновления количества
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity'])) {
    $product_id = intval($_POST['product_id']);
    $new_quantity = max(1, intval($_POST['quantity']));

    // Проверка наличия на складе
    $stmt_stock = $conn->prepare("SELECT stock, price FROM products WHERE id = ?");
    $stmt_stock->bind_param("i", $product_id);
    $stmt_stock->execute();
    $stock_data = $stmt_stock->get_result()->fetch_assoc();
    $stock = $stock_data['stock'];
    $price = $stock_data['price'];

    if ($new_quantity > $stock) {
        echo json_encode(['success' => false, 'message' => "Максимум доступно: $stock"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("iii", $new_quantity, $user_id, $product_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'total_price' => $price * $new_quantity,
            'product_price' => $price
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка обновления']);
    }
    exit;
}

// Получаем товары из корзины
$sql_cart = "
    SELECT 
        cart.id AS cart_id, 
        cart.product_id, 
        cart.quantity, 
        products.name, 
        products.price, 
        products.image_path, 
        products.stock 
    FROM cart 
    JOIN products ON cart.product_id = products.id 
    WHERE cart.user_id = ?";
$stmt_cart = $conn->prepare($sql_cart);
$stmt_cart->bind_param("i", $user_id);
$stmt_cart->execute();
$cart_result = $stmt_cart->get_result();

$cart_items = [];
$total_price = 0;

if ($cart_result->num_rows > 0) {
    while ($item = $cart_result->fetch_assoc()) {
        $item['subtotal'] = $item['price'] * $item['quantity'];
        $cart_items[] = $item;
        $total_price += $item['subtotal'];
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Корзина — MyShop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter :wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css "/>
    <style>
        :root {
            --primary: #4f46e5;
            --secondary: #f9fafb;
            --dark: #111827;
            --light: #ffffff;
            --danger: #ef4444;
            --success: #10b981;
            --border-radius: 1rem;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--secondary);
            color: var(--dark);
            margin: 0;
            padding: 0;
        }

        header {
            background: linear-gradient(to right, var(--primary), #6366f1);
            color: white;
            padding: 2rem 3rem;
            text-align: center;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            padding: 2rem;
        }

        h2 {
            font-size: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .cart-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 768px) {
            .cart-grid {
                grid-template-columns: 1fr;
            }
        }

        .cart-item {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            padding: 1rem;
            transition: transform 0.2s ease-in-out;
        }

        .cart-item:hover {
            transform: translateY(-4px);
        }

        .item-image {
            width: 100px;
            height: 100px;
            overflow: hidden;
            margin-right: 1rem;
            border-radius: var(--border-radius);
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .item-details h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.1rem;
        }

        .item-details p {
            margin: 0.2rem 0;
            font-size: 0.95rem;
        }

        .item-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }

        .quantity-controls input[type="number"] {
            width: 60px;
            padding: 0.4rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            text-align: center;
            font-weight: bold;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
        }

        .btn.delete-btn {
            background: var(--danger);
            color: white;
            font-size: 0.9rem;
        }

        .summary {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .summary h3 {
            margin-bottom: 1rem;
        }

        .summary ul {
            list-style: none;
            padding-left: 0;
        }

        .summary li {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.7rem;
        }

        .summary .total {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary);
            margin-top: 1rem;
        }

        .empty-cart {
            text-align: center;
            font-size: 1.2rem;
            color: #6b7280;
            margin-top: 3rem;
        }

        .footer {
            background: #111827;
            color: white;
            text-align: center;
            padding: 2rem;
            margin-top: 4rem;
        }

        .admin-warning {
            background: #fef3c7;
            color: #b45309;
            padding: 1rem;
            border-radius: var(--border-radius);
            text-align: center;
            font-weight: bold;
            margin-bottom: 2rem;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            color: white;
            background: #333;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
        }

        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }

        .notification.success {
            background-color: var(--success);
        }

        .notification.error {
            background-color: var(--danger);
        }

        .animate-remove {
            animation: removeAnim 0.3s forwards;
        }

        @keyframes removeAnim {
            to { opacity: 0; transform: scale(0.9); }
        }
    </style>
</head>
<body>

<header>
    <h1>🛍 Ваша корзина</h1>
</header>

<div class="container">

    <?php if ($is_admin): ?>
        <div class="admin-warning">
            <i class="fas fa-exclamation-triangle"></i>&nbsp;&nbsp;
            Вы вошли как администратор. Добавление и покупка товаров недоступны.
        </div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <p class="empty-cart">Ваша корзина пуста.</p>
    <?php else: ?>
        <div class="cart-grid">
            <div>
                <?php foreach ($cart_items as $item): ?>
                    <div class="cart-item" id="item-<?= $item['product_id'] ?>">
                        <div class="item-image">
                            <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                        </div>
                        <div class="item-details">
                            <h3><?= htmlspecialchars($item['name']) ?></h3>
                            <p>Цена: <?= number_format($item['price'], 0, '', ' ') ?> ₽</p>
                            <p>Наличие: <?= $item['stock'] ?> шт</p>
                            <div class="item-actions">
                                <div class="quantity-controls">
                                    <label for="quantity-<?= $item['product_id'] ?>">Кол-во:</label>
                                    <input type="number"
                                           id="quantity-<?= $item['product_id'] ?>"
                                           name="quantity"
                                           value="<?= $item['quantity'] ?>"
                                           min="1"
                                           max="<?= $item['stock'] ?>"
                                           data-product-id="<?= $item['product_id'] ?>"
                                           onchange="updateQuantity(this)"
                                           oninput="this.blur()">
                                </div>
                                <button class="btn delete-btn" onclick="removeItem(<?= $item['product_id'] ?>)">Удалить</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <button class="btn delete-btn" style="width: 100%; margin-top: 1rem;" onclick="clearCart()">Очистить корзину</button>
            </div>

            <aside class="summary">
                <h3>Итоги</h3>
                <ul>
                    <?php foreach ($cart_items as $item): ?>
                        <li>
                            <span><?= htmlspecialchars($item['name']) ?></span>
                            <span id="subtotal-<?= $item['product_id'] ?>">
                                <?= number_format($item['price'] * $item['quantity'], 0, '', ' ') ?> ₽
                            </span>
                        </li>
                    <?php endforeach; ?>
                    <li style="border-top: 1px solid #ccc; padding-top: 1rem;">
                        <strong>Общая сумма</strong>
                        <strong id="total-price"><?= number_format($total_price, 0, '', ' ') ?> ₽</strong>
                    </li>
                </ul>
                <br>
                <?php if (!$is_admin): ?>
                    <button class="btn" style="background: var(--success); width: 100%;" onclick="window.location.href='order.php'">
                        Оформить заказ
                    </button>
                <?php else: ?>
                    <button class="btn" style="background: #ccc; width: 100%;" disabled>
                        Админ не может оформить заказ
                    </button>
                <?php endif; ?>
            </aside>
        </div>
    <?php endif; ?>
</div>

<footer class="footer">
    &copy; <?= date("Y") ?> MyShop — Все права защищены
</footer>

<!-- Уведомления -->
<div class="notification success" id="notification"></div>

<script>
function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = 'notification ' + type + ' show';
    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}

function updateQuantity(input) {
    let productId = parseInt(input.dataset.productId);
    let quantity = parseInt(input.value);
    let stock = <?= json_encode(array_column($cart_items, 'stock', 'product_id')) ?>[productId];

    if (isNaN(quantity) || quantity < 1 || quantity > stock) {
        input.value = Math.min(stock, Math.max(1, quantity));
        showNotification(`Можно заказать максимум ${stock} шт`, 'warning');
        return;
    }

    fetch('cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `update_quantity=true&product_id=${productId}&quantity=${quantity}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Обновляем цену для конкретного товара
            document.getElementById('subtotal-' + productId).textContent =
                `${data.total_price.toLocaleString()} ₽`;

            // Пересчитываем общую сумму
            updateTotalPrice();
            showNotification('Количество обновлено', 'success');
        } else {
            alert(data.message);
        }
    });
}

function updateTotalPrice() {
    let total = 0;
    document.querySelectorAll('[id^="subtotal-"]').forEach(el => {
        total += parseInt(el.textContent.replace(/\D+/g, ''));
    });
    document.getElementById('total-price').textContent = total.toLocaleString() + ' ₽';
}

function removeItem(productId) {
    if (!confirm('Удалить этот товар?')) return;

    fetch('cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `remove_item=true&product_id=${productId}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById(`item-${productId}`).classList.add('animate-remove');
            setTimeout(() => document.getElementById(`item-${productId}`).remove(), 300);
            showNotification('Товар удален', 'success');
            updateTotalPrice();
        } else {
            alert(data.message);
        }
    });
}

function clearCart() {
    if (!confirm('Вы уверены, что хотите очистить корзину?')) return;

    fetch('cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'clear_cart=true'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
            showNotification('Корзина очищена', 'success');
        } else {
            alert(data.message);
        }
    });
}
</script>

</body>
</html>