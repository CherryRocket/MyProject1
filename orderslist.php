<?php
require_once 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: login.php');
    exit;
}

// Получаем все заказы с товарами
$stmt = $conn->prepare("
    SELECT 
        o.id AS order_id,
        o.user_id,
        u.email AS user_email,
        o.order_date,
        o.total_price,
        o.delivery_type,
        o.delivery_address,
        GROUP_CONCAT(p.name SEPARATOR ', ') AS product_names
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    GROUP BY o.id
    ORDER BY o.order_date DESC");

if (!$stmt) {
    die("Ошибка подготовки запроса: " . $conn->error);
}

$stmt->execute();
$result = $stmt->get_result();
$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Заказы — MyShop</title>
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
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        th, td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        thead {
            background: linear-gradient(to right, var(--primary), #0056b3);
            color: white;
        }

        tbody tr:hover {
            background: #f1f1f1;
        }

        .btn {
            display: inline-block;
            padding: 0.6rem 1rem;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .btn.send-email-btn {
            background: var(--success);
            margin-left: 0.5rem;
        }

        .btn.send-email-btn:hover {
            background: #27ae60;
        }

        .delivery-type {
            text-transform: lowercase;
        }

        .delivery-type::first-letter {
            text-transform: uppercase;
        }

        footer {
            text-align: center;
            padding: 2rem 1rem;
            background: #222;
            color: white;
            margin-top: 3rem;
        }

        /* Уведомления */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            color: white;
            font-size: 1rem;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
        }

        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }

        .notification.success {
            background: var(--success);
        }

        .notification.error {
            background: var(--danger);
        }
    </style>
</head>
<body data-theme="light">

<div class="notification success" id="notification"></div>

<header>
    <h1>MyShop — Заказы</h1>
</header>

<div class="main-container">
    <h2 class="orders-title">Список всех заказов</h2>

    <?php if (empty($orders)): ?>
        <p style="text-align:center; margin-top: 2rem;">Нет заказов</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Номер заказа</th>
                    <th>Email</th>
                    <th>Товары</th>
                    <th>Сумма</th>
                    <th>Доставка</th>
                    <th>Дата</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= htmlspecialchars($order['order_id']) ?></td>
                        <td><?= htmlspecialchars($order['user_email']) ?></td>
                        <td><?= htmlspecialchars($order['product_names']) ?></td>
                        <td><?= number_format($order['total_price'], 0, '', ' ') ?> ₽</td>
                        <td>
                            <?= $order['delivery_type'] === 'self_pickup' ? 'Самовывоз' : 'Доставка: ' . htmlspecialchars($order['delivery_address']) ?>
                        </td>
                        <td><?= date("d.m.Y H:i", strtotime($order['order_date'])) ?></td>
                        <td>
                            <button class="btn send-email-btn" onclick="sendEmailToClient('<?= $order['user_email'] ?>', '<?= $order['user_name'] ?>', <?= $order['order_id'] ?>)">
                                📧 Отправка товара клиенту
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<footer>
    <p>&copy; <?= date("Y") ?> MyShop — Все права защищены.</p>
</footer>

<script>
function sendEmailToClient(email, name, orderId) {
    const subject = "Ваш заказ №" + orderId;
    const message = `Здравствуйте, ${name}! Спасибо за заказ №${orderId}. Ваш заказ уже в пути! ( Если самовывоз, подойдите к кассе )

    Оплата при получении: Банк.Картой/Наличный рассчёт.

    Свяжитесь с нами по номеру 79870202022 если возникли вопросы.`;

    fetch('send_email_to_client.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, subject, message })
    })
    .then(res => res.json())
    .then(data => {
        showNotification(data.message, data.success ? 'success' : 'error');
    });
}

function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = 'notification ' + type + ' show'; // заменяем класс
    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}
</script>

</body>
</html>