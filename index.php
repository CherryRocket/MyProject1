<?php
require_once 'db_connect.php';
session_start();

// Получаем пользователя
$user_id = $_SESSION['user_id'] ?? null;
$user = $cart_count = null;

if ($user_id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // Подсчёт товаров в корзине
    $res = mysqli_query($conn, "SELECT COUNT(*) as count FROM cart WHERE user_id = $user_id");
    $cart_count = mysqli_fetch_assoc($res)['count'];
}

// Сортировка
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
switch ($sort) {
    case 'price':
        $order_by = 'price ASC';
        break;
    case 'price_desc':
        $order_by = 'price DESC';
        break;
    case 'name_desc':
        $order_by = 'name DESC';
        break;
    default:
        $order_by = 'name ASC';
}
$result = mysqli_query($conn, "SELECT * FROM products ORDER BY $order_by");

// Для рекомендаций: рандомные товары
$recommendations = mysqli_query($conn, "SELECT * FROM products ORDER BY RAND() LIMIT 4");

// Для карусели: популярные товары
$carousel_products = mysqli_query($conn, "SELECT * FROM products ORDER BY RAND() LIMIT 6");

// Получаем самые популярные товары по рейтингу
$popular_products = mysqli_query($conn, "
    SELECT p.id, p.name, p.image_path, p.price, p.stock, AVG(r.rating) AS avg_rating
    FROM products p
    LEFT JOIN reviews r ON p.id = r.product_id
    GROUP BY p.id
    ORDER BY avg_rating DESC
    LIMIT 1
");
$popular_product = mysqli_fetch_assoc($popular_products);

// Получаем все отзывы для отображения на главной
$all_reviews = mysqli_query($conn, "SELECT * FROM reviews ORDER BY created_at DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>MyShop — Интернет-магазин</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
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
            --dark: #255;
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
            transition: background-color 0.3s, color 0.3s;
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
        .hero {
            background: linear-gradient(to right, var(--primary), #0056b3);
            color: white;
            padding: 4rem 1rem;
            text-align: center;
        }
        .hero h1 {
            font-size: 2.8rem;
            margin-bottom: 0.5rem;
        }
        .hero p {
            font-size: 1.2rem;
            max-width: 600px;
            margin: auto;
        }
        header {
            background: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 999;
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: auto;
            padding: 1rem;
        }
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary);
        }
        nav a {
            margin-left: 1.5rem;
            color: var(--dark);
            font-weight: 500;
        }
        .search-form input {
            padding: 0.5rem 1rem;
            width: 250px;
            border: 1px solid #ccc;
            border-radius: var(--border-radius);
            outline: none;
        }
        .main-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
            padding: 2rem;
            max-width: 1200px;
            margin: auto;
        }
        @media (max-width: 768px) {
            .main-container {
                grid-template-columns: 1fr;
            }
        }
        .category ul {
            list-style: none;
            padding-left: 0;
        }
        .category li {
            margin-bottom: 0.5rem;
        }
        .category a {
            color: var(--dark);
            transition: color 0.2s;
        }
        .category a:hover {
            color: var(--primary);
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.5rem;
        }
        .product-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
        }
        .product-image {
            height: 200px;
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        .stock-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: orange;
            color: white;
            padding: 4px 8px;
            font-size: 0.7rem;
            border-radius: var(--border-radius);
        }
        .out-of-stock {
            background: var(--danger);
        }
        .product-info {
            padding: 1rem;
        }
        .product-name {
            font-size: 1.1rem;
            margin: 0 0 0.5rem;
            font-weight: 600;
        }
        .product-desc {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }
        .product-price {
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        .btn {
            display: inline-block;
            margin-top: 0.5rem;
            padding: 0.5rem 1rem;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .sort-select {
            margin-bottom: 1rem;
        }
        footer {
            background: #222;
            color: white;
            padding: 2rem 1rem;
            text-align: center;
        }
        .footer-links {
            margin-top: 1rem;
        }
        .footer-links a {
            color: white;
            margin: 0 0.5rem;
            transition: color 0.3s;
        }
        .footer-links a:hover {
            color: var(--primary);
        }
        .cart-link {
            position: relative;
        }
        .cart-counter {
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 4px 8px;
            font-size: 0.75rem;
            position: absolute;
            top: -10px;
            right: -10px;
        }
        .social-icons a {
            color: white;
            margin: 0 0.5rem;
            text-decoration: none;
            font-size: 1.2rem;
            transition: color 0.3s;
        }
        .social-icons a:hover {
            color: var(--primary);
        }
        .recommended-section {
            margin: 2rem 0;
            padding: 2rem;
            background: #f1f3f5;
            border-radius: var(--border-radius);
        }
        .recommended-title {
            text-align: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .newsletter {
            background: var(--primary);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .newsletter input[type="email"] {
            padding: 0.5rem;
            width: 250px;
            border-radius: var(--border-radius);
            border: none;
            margin-right: 0.5rem;
        }
        .newsletter button {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--border-radius);
            background: white;
            color: var(--primary);
            cursor: pointer;
        }
        .rating {
            color: gold;
            margin-bottom: 0.5rem;
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
        /* Swiper styles */
        .swiper {
            width: 100%;
            max-width: 400px;
            margin: auto;
            padding-bottom: 2rem;
        }
        .swiper-slide {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        .swiper-slide img {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        .swiper-slide h4 {
            padding: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
        }
        .swiper-slide p {
            padding: 0 0.5rem 0.5rem;
            font-size: 0.9rem;
            color: #666;
        }

        /* Отзывы */
        .reviews-section {
            margin: 2rem 0;
            padding: 2rem;
            background: #f9f9f9;
            border-radius: var(--border-radius);
        }
        .reviews-section h2 {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .review {
            background: white;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
        }
        .review strong {
            display: block;
            margin-bottom: 0.5rem;
        }
        .review .stars {
            color: gold;
            margin-bottom: 0.5rem;
        }
        .review p {
            margin-bottom: 0.5rem;
        }
        .review small {
            color: #888;
            font-size: 0.8rem;
        }

        /* Популярный товар */
        .popular-product {
            margin: 2rem 0;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        .popular-product h2 {
            text-align: center;
            margin-bottom: 1rem;
        }
        .popular-product .product-card {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .popular-product .product-image {
            width: 200px;
            height: 150px;
            margin-bottom: 1rem;
        }
        .popular-product .product-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .popular-product .product-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .popular-product .product-price {
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        .popular-product .btn {
            width: 100%;
        }

        /* Отзывы */
        .reviews-section {
            margin: 2rem 0;
            padding: 2rem;
            background: #f9f9f9;
            border-radius: var(--border-radius);
        }
        .reviews-section h2 {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .review {
            background: white;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
        }
        .review strong {
            display: block;
            margin-bottom: 0.5rem;
        }
        .review .stars {
            color: gold;
            margin-bottom: 0.5rem;
        }
        .review p {
            margin-bottom: 0.5rem;
        }
        .review small {
            color: #888;
            font-size: 0.8rem;
        }
    </style>
</head>
<body data-theme="light">
<div class="preloader" id="preloader">
    <i class="fas fa-spinner fa-spin fa-3x"></i>
</div>

<header>
    <div class="top-bar">
        <div class="logo">MyShop</div>
        <nav>
            <a href="index.php">Главная</a>
            <?php if ($user): ?>
                <a href="profile.php"><?= htmlspecialchars($user['email']) ?></a>
                <a href="logout.php">Выход</a>
            <?php else: ?>
                <a href="login.php">Вход</a>
                <a href="register.php">Регистрация</a>
            <?php endif; ?>
            <?php if ($user && $user['role_id'] != 1): ?>
                <a href="cart.php" class="cart-link">
                    Корзина
                    <?php if ($cart_count > 0): ?>
                        <span class="cart-counter"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            <button class="theme-toggle" onclick="toggleTheme()">🌙</button>
        </nav>
    </div>
    <form action="search.php" method="GET" class="search-form" style="text-align:center;">
        <input type="text" name="query" placeholder="Поиск товаров..." required />
        <button type="submit" class="btn">Найти</button>
    </form>
</header>

<section class="hero">
    <h1>Лучшие товары по лучшим ценам</h1>
    <p>Большой выбор, удобные фильтры, быстрая доставка</p>
</section>
<div class="main-container">
    <aside class="category">
        <h3>Категории</h3>
        <ul>
            <?php
            $categories = mysqli_query($conn, "SELECT * FROM category");
            while ($cat = mysqli_fetch_assoc($categories)) {
                echo "<li><a href='category.php?id=" . htmlspecialchars($cat['id']) . "'>" . htmlspecialchars($cat['Name']) . "</a></li>";
            }
            ?>
        </ul>
    </aside>
    <main>
        <div class="sort-select">
            <label for="sort">Сортировать по:</label>
            <select name="sort" id="sort" onchange="window.location.href='?sort='+this.value">
                <option value="name" <?= $sort == 'name' ? 'selected' : '' ?>>Названию (А-Я)</option>
                <option value="name_desc" <?= $sort == 'name_desc' ? 'selected' : '' ?>>Названию (Я-А)</option>
                <option value="price" <?= $sort == 'price' ? 'selected' : '' ?>>Цене (по возрастанию)</option>
                <option value="price_desc" <?= $sort == 'price_desc' ? 'selected' : '' ?>>Цене (по убыванию)</option>
            </select>
        </div>
        <div class="product-grid">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="<?= htmlspecialchars($row['image_path']) ?>" alt="<?= htmlspecialchars($row['name']) ?>">
                            <?php if ($row['stock'] <= 0): ?>
                                <span class="stock-badge out-of-stock">Нет в наличии</span>
                            <?php elseif ($row['stock'] < 10): ?>
                                <span class="stock-badge">Осталось меньше 10 ед</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name"><?= htmlspecialchars($row['name']) ?></h3>
                            <p class="product-desc"><?= htmlspecialchars(substr($row['description'], 0, 60)) ?></p>
                            <p class="product-price"><?= number_format($row['price'], 0, '', ' ') ?> ₽</p>
                            <a href="product.php?id=<?= $row['id'] ?>" class="btn">Подробнее</a>
                            <?php if ($row['stock'] > 0 && $user && $user['role_id'] != 1): ?>
                                <button onclick="addToCart(<?= $row['id'] ?>" class="btn" style="margin-top: 0.5rem;">В корзину</button>
                            <?php elseif ($row['stock'] > 0 && $user && $user['role_id'] == 1): ?>
                                <button disabled class="btn" style="margin-top: 0.5rem; background-color: #ccc; cursor: not-allowed;">Админ не может покупать</button>
                            <?php elseif ($row['stock'] > 0): ?>
                                <button onclick="alert('Войдите, чтобы добавить товар')" class="btn" style="margin-top: 0.5rem;">В корзину</button>
                            <?php endif; ?>
                            <?php
// Получаем отзывы для этого товара
$reviews_query = "SELECT * FROM reviews WHERE product_id = ?";
$stmt_reviews = $conn->prepare($reviews_query);
$stmt_reviews->bind_param("i", $row['id']);
$stmt_reviews->execute();
$reviews_result = $stmt_reviews->get_result();

$reviews = [];
$sum_rating = 0;
$count_reviews = 0;

while ($review = $reviews_result->fetch_assoc()) {
    $reviews[] = $review;
    $sum_rating += $review['rating'];
    $count_reviews++;
}

$avg_rating = $count_reviews > 0 ? round($sum_rating / $count_reviews) : 0;
?>

<!-- Отображение рейтинга -->
<div class="product-rating">
    <span class="rating-stars">
        <?= '★'.str_repeat('☆', 5 - $avg_rating) ?>
    </span>
    <span class="rating-count">(<?= $count_reviews ?> отзыв)</span>
</div>

<!-- Отображение последних отзывов -->
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>Товаров не найдено.</p>
            <?php endif; ?>
        </div>

        <!-- Подписка -->
        <div class="newsletter">
            <h3>Подпишитесь на рассылку</h3>
            <p>Получайте уведомления о новых акциях и товарах</p>
            <form>
                <input type="email" placeholder="Введите email" required />
                <button type="submit" class="btn">Подписаться</button>
            </form>
        </div>
    </main>
</div>

<footer>
    <p>&copy; 2025 MyShop — Все права защищены.</p>
    <div class="footer-links">
        <a href="#">О нас</a>
        <a href="#">Доставка</a>
        <a href="#">Гарантия</a>
        <a href="#">Контакты</a>
    </div>
    <div class="social-icons">
        <a href="#"><i class="fab fa-facebook"></i></a>
        <a href="#"><i class="fab fa-instagram"></i></a>
        <a href="#"><i class="fab fa-youtube"></i></a>
    </div>
</footer>

<script>
function addToCart(productId) {
    fetch('add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            product_id: productId,
            user_id: <?= $user_id ?? 0 ?>,
            quantity: 1
        })
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        location.reload();
    });
}

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