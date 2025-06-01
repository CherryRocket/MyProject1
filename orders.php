<?php
include('db_connect.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Получаем заказы пользователя и их товары
$sql = "SELECT o.id AS order_id, o.order_date, o.total_price, o.delivery_type, o.delivery_address,
               oi.quantity, oi.price, p.name AS product_name
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE o.user_id = ?
        ORDER BY o.order_date DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Ошибка подготовки запроса: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];

while ($row = $result->fetch_assoc()) {
    $id = $row['order_id'];
    if (!isset($orders[$id])) {
        $orders[$id] = [
            'order_date' => $row['order_date'],
            'total_price' => $row['total_price'],
            'delivery_type' => $row['delivery_type'],
            'delivery_address' => $row['delivery_address'],
            'items' => []
        ];
    }
    $orders[$id]['items'][] = [
        'product_name' => $row['product_name'],
        'quantity' => $row['quantity'],
        'price' => $row['price']
    ];
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Мои заказы — MyShop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter :wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css " />
    <style>
        :root {
            --primary: #007BFF;
            --secondary: #f5f7fa;
            --dark: #222;
            --light: #fff;
            --danger: #e74c3c;
            --success: #2ecc71;
            --border-radius: 10px;
            --transition: all 0.3s ease-in-out;
            --shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        [data-theme="dark"] {
            --primary: #1E90FF;
            --secondary: #1a1a1a;
            --dark: #eee;
            --light: #222;
            --shadow: 0 2px 10px rgba(255,255,255,0.05);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--secondary);
            color: var(--dark);
            line-height: 1.6;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .preloader {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeOut 1s forwards;
        }

        @keyframes fadeOut {
            to { opacity: 0; visibility: hidden; }
        }

        header {
            background: white;
            box-shadow: var(--shadow);
            padding: 1rem 2rem;
            text-align: center;
        }

        header h1 {
            font-size: 1.8rem;
            color: var(--primary);
        }

        .main-container {
            max-width: 1200px;
            margin: auto;
            padding: 2rem;
        }

        .orders-title {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }

        .empty {
            text-align: center;
            font-size: 1.2rem;
            color: #888;
            margin-top: 2rem;
        }

        .order-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: transform 0.3s;
        }

        .order-card:hover {
            transform: translateY(-5px);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .order-id {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .order-date {
            color: var(--dark);
            font-size: 0.9rem;
        }

        .order-details {
            margin-bottom: 1rem;
        }

        .order-details p {
            margin: 0.4rem 0;
        }

        .order-total {
            font-weight: bold;
            color: var(--primary);
            font-size: 1.1rem;
        }

        .order-items {
            margin-top: 1rem;
            border-top: 1px solid #eee;
            padding-top: 1rem;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 0.4rem 0;
        }

        .back-link {
            display: inline-block;
            margin-top: 2rem;
            color: var(--primary);
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        footer {
            text-align: center;
            padding: 2rem 1rem;
            background: #222;
            color: white;
            margin-top: 3rem;
        }

        /* Темная тема */
        [data-theme="dark"] .order-card {
            background: #2b2b2b;
            color: var(--light);
        }

        [data-theme="dark"] .order-item {
            color: var(--light);
        }

        [data-theme="dark"] .order-id {
            color: var(--light);
        }

        .theme-toggle {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--dark);
            font-size: 1.2rem;
            margin-left: 1rem;
            transition: color 0.3s;
        }

        .theme-toggle:hover {
            color: var(--primary);
        }
    </style>
</head>
<body data-theme="light">

<div class="preloader" id="preloader">
    <i class="fas fa-spinner fa-spin fa-3x"></i>
</div>

<header>
    <h1>Мои заказы</h1>
</header>

<div class="main-container">
    <h2 class="orders-title">История ваших заказов</h2>

    <?php if (empty($orders)): ?>
        <p class="empty">У вас пока нет заказов.</p>
    <?php else: ?>
        <?php foreach ($orders as $order_id => $order): ?>
            <div class="order-card">
                <div class="order-header">
                    <div class="order-id">Заказ №<?= $order_id ?></div>
                    <div class="order-date"><?= $order['order_date'] ?></div>
                </div>

                <div class="order-details">
                    <!-- Доставка -->
                    <p><strong>Доставка:</strong>
                        <?= htmlspecialchars($order['delivery_type']) === 'self_pickup' ? 'Самовывоз' : 'Доставка до адреса' ?>
                    </p>

                    <!-- Адрес -->
                    <p><strong>Адрес:</strong>
                        <?= htmlspecialchars($order['delivery_type']) === 'self_pickup'
                            ? 'Г. Москва ул Примерова, д.12'
                            : htmlspecialchars($order['delivery_address'])
                        ?>
                    </p>

                    <!-- Статус оплаты (можно расширить логику) -->
                    <p><strong>Статус оплаты:</strong> Оплачен</p>
                </div>

                <div class="order-items">
                    <strong>Товары:</strong>
                    <?php foreach ($order['items'] as $item): ?>
                        <div class="order-item">
                            <span><?= htmlspecialchars($item['product_name']) ?></span>
                            <span><?= $item['quantity'] ?> шт × <?= number_format($item['price'], 0, '', ' ') ?> ₽</span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="order-total">
                    Итого: <?= number_format($order['total_price'], 0, '', ' ') ?> ₽
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <a href="index.php" class="back-link">← Вернуться на главную</a>
</div>

<footer>
    <p>&copy; <?= date("Y") ?> MyShop — Все права защищены.</p>
</footer>

<script>
function toggleTheme() {
    const current = document.body.getAttribute('data-theme');
    document.body.setAttribute('data-theme', current === 'dark' ? 'light' : 'dark');
}

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        document.getElementById('preloader').style.opacity = 0;
        document.getElementById('preloader').style.visibility = 'hidden';
    }, 1000);
});
</script>

</body>
</html>