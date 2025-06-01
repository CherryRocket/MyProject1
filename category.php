<?php
// category.php — Страница категории товаров

require_once 'db_connect.php';
session_start();

// Проверка наличия ID категории
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$category_id = intval($_GET['id']);

// Получаем данные о категории
$stmt_category = $conn->prepare("SELECT Name FROM category WHERE id = ?");
$stmt_category->bind_param("i", $category_id);
$stmt_category->execute();
$result_category = $stmt_category->get_result();

if ($result_category->num_rows === 0) {
    echo "<p>Категория не найдена</p>";
    exit;
}

$category = $result_category->fetch_assoc();

// Получаем товары из этой категории
$stmt_products = $conn->prepare("SELECT * FROM products WHERE category_id = ?");
$stmt_products->bind_param("i", $category_id);
$stmt_products->execute();
$products_result = $stmt_products->get_result();
$products = $products_result->fetch_all(MYSQLI_ASSOC);

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= htmlspecialchars($category['Name']) ?> — MyShop</title>
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

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            align-items: center;
            margin-top: 1rem;
        }

        .search-form input[type="text"] {
            width: 300px;
            padding: 0.6rem 1rem;
            border: 1px solid #ccc;
            border-radius: var(--border-radius);
            font-size: 1rem;
        }

        .search-form button {
            padding: 0.6rem 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: background 0.3s;
        }

        .search-form button:hover {
            background: #0056b3;
        }

        .main-container {
            max-width: 1200px;
            margin: auto;
            padding: 2rem;
        }

        .category-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
            color: var(--dark);
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .product-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .product-info {
            padding: 1rem;
        }

        .product-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .product-desc {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 1rem;
        }

        .product-price {
            font-weight: bold;
            color: var(--success);
            margin-bottom: 1rem;
        }

        .product-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 1rem 1rem;
        }

        .btn {
            padding: 0.6rem 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #0056b3;
        }

        .btn.view {
            background: var(--success);
        }

        .btn.view:hover {
            background: #27ae60;
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

        .notification.warning {
            background: orange;
        }
    </style>
</head>
<body data-theme="light">

<div class="notification success" id="notification"></div>

<header>
    <h1>MyShop — <?= htmlspecialchars($category['Name']) ?></h1>
    <nav class="toolbar">
        <form action="search.php" method="GET" class="search-form">
            <input type="text" name="query" placeholder="Поиск по категории..." required>
            <button type="submit">🔍 Найти</button>
        </form>
    </nav>
</header>

<main class="main-container">
    <h2 class="category-title"><?= htmlspecialchars($category['Name']) ?></h2>

    <?php if (empty($products)): ?>
        <p style="text-align:center; color:#888;">В этой категории пока нет товаров</p>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <img src="<?= htmlspecialchars($product['image_path'] ?? '/images/default.jpg') ?>" 
                         alt="<?= htmlspecialchars($product['name']) ?>" 
                         class="product-image">
                    <div class="product-info">
                        <h3 class="product-name"><?= htmlspecialchars($product['name']) ?></h3>
                        <p class="product-desc"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                        <p class="product-price">Цена: <?= number_format($product['price'], 0, '', ' ') ?> ₽</p>
                        <div class="product-actions">
                            <button class="btn" onclick="addToCart(<?= $product['id'] ?>)">🛒 В корзину</button>
                            <a href="product.php?id=<?= $product['id'] ?>" class="btn view">🔎 Подробнее</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<footer class="footer">
    <p>&copy; <?= date("Y") ?> MyShop — Все права защищены.</p>
</footer>

<script>
function addToCart(productId) {
    fetch('add_to_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, user_id: <?= $_SESSION['user_id'] ?? 'null' ?>, quantity: 1 })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification('Товар добавлен в корзину', 'success');
            updateCartCounter();
        } else {
            showNotification(data.message || 'Ошибка при добавлении', 'error');
        }
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

function updateCartCounter() {
    fetch('get_cart_count.php')
        .then(res => res.json())
        .then(data => {
            if (data.success && document.getElementById('cart-counter')) {
                document.getElementById('cart-counter').textContent = data.count;
            }
        });
}
</script>

</body>
</html>