<?php
require_once 'db_connect.php';
session_start();

// Проверка администратора
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: login.php');
    exit;
}

// Оборот за год
$stmt = $conn->prepare("
    SELECT SUM(total_price) AS yearly_revenue 
    FROM orders 
    WHERE YEAR(order_date) = YEAR(CURRENT_DATE())
");
$stmt->execute();
$revenue_row = $stmt->get_result()->fetch_assoc();
$yearly_revenue = $revenue_row['yearly_revenue'] ?? 0;

// Количество заказов за год
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total_orders 
    FROM orders 
    WHERE YEAR(order_date) = YEAR(CURRENT_DATE())
");
$stmt->execute();
$total_orders = $stmt->get_result()->fetch_assoc()['total_orders'] ?? 0;

// Новых пользователей за год
$stmt = $conn->prepare("
    SELECT COUNT(*) AS users 
    FROM users 
");
$stmt->execute();
$new_users = $stmt->get_result()->fetch_assoc()['users'] ?? 0;

// Топ 5 товаров по продажам
$stmt = $conn->prepare("
    SELECT p.name, SUM(oi.quantity) AS total_sold
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE YEAR(o.order_date) = YEAR(CURRENT_DATE())
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 5
");
$stmt->execute();
$top_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Выручка по месяцам
$stmt = $conn->prepare("
    SELECT MONTH(order_date) AS month, SUM(total_price) AS revenue
    FROM orders
    WHERE YEAR(order_date) = YEAR(CURRENT_DATE())
    GROUP BY MONTH(order_date)
");
$stmt->execute();
$monthly_revenue_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Формируем данные для графика
$months = [
    1 => 'Янв', 2 => 'Фев', 3 => 'Мар',
    4 => 'Апр', 5 => 'Май', 6 => 'Июн',
    7 => 'Июл', 8 => 'Авг', 9 => 'Сен',
    10 => 'Окт', 11 => 'Ноя', 12 => 'Дек'
];

$revenue_by_month = array_fill_keys(array_values($months), 0);
foreach ($monthly_revenue_data as $row) {
    $revenue_by_month[$months[$row['month']]] = (float)$row['revenue'];
}

// Общее количество пользователей
$user_count_sql = "SELECT COUNT(*) AS total_users FROM users";
$user_count_result = $conn->query($user_count_sql);
$total_users = $user_count_result->fetch_assoc()['total_users'] ?? 0;

// Общее количество категорий
$category_count_sql = "SELECT COUNT(*) AS total_categories FROM category";
$category_count_result = $conn->query($category_count_sql);
$total_categories = $category_count_result->fetch_assoc()['total_categories'] ?? 0;

// Общее количество товаров
$product_count_sql = "SELECT COUNT(*) AS total_products FROM products";
$product_count_result = $conn->query($product_count_sql);
$total_products = $product_count_result->fetch_assoc()['total_products'] ?? 0;

// Закрытие соединения
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>📊 Статистика — MyShop</title>
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

        .back-link {
            display: inline-block;
            margin-bottom: 1.5rem;
            color: var(--primary);
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-title {
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
        }

        .chart-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        canvas#revenueChart {
            max-width: 100%;
            height: 300px;
        }

        .top-products {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 0.8rem;
            border-bottom: 1px solid #eee;
        }

        thead {
            background: linear-gradient(to right, var(--primary), #0056b3);
            color: white;
        }

        .footer {
            text-align: center;
            padding: 2rem 1rem;
            background: #222;
            color: white;
            margin-top: 3rem;
        }
    </style>
</head>
<body data-theme="light">

<header>
    <h1>📊 MyShop — Аналитика</h1>
</header>

<div class="main-container">
    <a href="admin.php" class="back-link">← Назад в админ панель</a>

    <!-- Статистика -->
    <div class="stats-grid">
        <div class="stat-card">
            <p class="stat-title">Годовая выручка</p>
            <p class="stat-value"><?= number_format($yearly_revenue, 0, '', ' ') ?> ₽</p>
        </div>
        <div class="stat-card">
            <p class="stat-title">Всего заказов</p>
            <p class="stat-value"><?= $total_orders ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-title">Новые клиенты</p>
            <p class="stat-value"><?= $new_users ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-title">Всего пользователей</p>
            <p class="stat-value"><?= $total_users ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-title">Количество категорий</p>
            <p class="stat-value"><?= $total_categories ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-title">Товары в каталоге</p>
            <p class="stat-value"><?= $total_products ?></p>
        </div>
    </div>

    <!-- График выручки -->
    <div class="chart-container">
        <h3>Выручка по месяцам</h3>
        <canvas id="revenueChart"></canvas>
    </div>

    <!-- Топ товаров -->
    <div class="top-products">
        <h3>Топ 5 товаров по продажам</h3>
        <?php if (!empty($top_products)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Продано</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_products as $product): ?>
                        <tr>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td><?= htmlspecialchars($product['total_sold']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="margin-top: 1rem;">Пока нет продаж</p>
        <?php endif; ?>
    </div>
</div>

<footer class="footer">
    <p>&copy; <?= date("Y") ?> MyShop — Все права защищены.</p>
</footer>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js "></script>

<!-- График -->
<script>
const ctx = document.getElementById('revenueChart').getContext('2d');

const monthlyRevenue = {
    labels: <?= json_encode(array_keys($revenue_by_month)) ?>,
    datasets: [{
        label: 'Выручка (₽)',
        data: <?= json_encode(array_values($revenue_by_month)) ?>,
        backgroundColor: 'rgba(0, 123, 255, 0.2)',
        borderColor: 'var(--primary)',
        borderWidth: 2,
        tension: 0.3,
        fill: true,
        pointRadius: 4,
        pointHoverRadius: 5,
        pointBackgroundColor: 'var(--primary)',
        pointBorderColor: 'white',
        pointBorderWidth: 2
    }]
};

new Chart(ctx, {
    type: 'line',
    data: monthlyRevenue,
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.raw.toLocaleString() + ' ₽';
                    }
                }
            }
        },
        scales: {
            y: {
                ticks: {
                    callback: value => value.toLocaleString() + ' ₽',
                    color: 'var(--dark)'
                }
            },
            x: {
                ticks: {
                    color: 'var(--dark)'
                }
            }
        }
    }
});
</script>

</body>
</html>