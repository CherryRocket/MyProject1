<?php
require_once 'db_connect.php';
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$reviews = [];
$my_reviews = [];

// Получение всех отзывов
$sql_all_reviews = "SELECT r.*, p.name AS product_name FROM reviews r JOIN products p ON r.product_id = p.id ORDER BY r.created_at DESC";
$result_all = mysqli_query($conn, $sql_all_reviews);

while ($row = mysqli_fetch_assoc($result_all)) {
    $reviews[] = $row;
}

// Получение отзывов пользователя
$sql_my_reviews = "SELECT r.*, p.name AS product_name FROM reviews r JOIN products p ON r.product_id = p.id WHERE r.user_id = ?";
$stmt_my = $conn->prepare($sql_my_reviews);
$stmt_my->bind_param("i", $user_id);
$stmt_my->execute();
$result_my = $stmt_my->get_result();

while ($row = $result_my->fetch_assoc()) {
    $my_reviews[] = $row;
}

// Проверка, покупал ли пользователь товар
function hasPurchasedProduct($user_id, $product_id) {
    global $conn;
    $sql_check = "SELECT COUNT(*) AS count FROM order_items WHERE user_id = ? AND product_id = ?";
    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Мои отзывы — MyShop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
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
        .reviews-section {
            margin-top: 2rem;
        }
        .reviews-section h2 {
            margin-bottom: 1rem;
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
        .review-form {
            margin-top: 2rem;
        }
        .review-form h3 {
            margin-bottom: 1rem;
        }
        .review-form select, .review-form textarea {
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 1rem;
            border-radius: var(--border-radius);
            border: 1px solid #ccc;
        }
        .review-form button {
            width: 100%;
        }
        .btn {
            display: inline-block;
            padding: 0.6rem 1.2rem;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        footer {
            text-align: center;
            padding: 1.5rem;
            background: #222;
            color: white;
        }
        footer p {
            margin: 0;
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            color: white;
            background-color: #333;
            font-size: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
    <h1>Мои отзывы</h1>
    <button class="theme-toggle" onclick="toggleTheme()">🌙</button>
</header>

<div class="main-container">

    <section class="reviews-section">
    <a href="index.php" class="back-link">← Вернуться на главную</a>
        <h2>Мои отзывы</h2>
        <div id="my-reviews-list">
            <?php if (!empty($my_reviews)): ?>
                <?php foreach ($my_reviews as $review): ?>
                    <div class="review">
                        <strong>Пользователь <?= $review['user_id'] ?></strong>
                        <div class="stars">
                            <?= '★'.str_repeat('☆', 5 - $review['rating']) ?>
                        </div>
                        <p><?= htmlspecialchars($review['comment']) ?></p>
                        <small>Товар: <?= htmlspecialchars($review['product_name']) ?> • <?= date('d.m.Y H:i', strtotime($review['created_at'])) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>У вас ещё нет отзывов.</p>
            <?php endif; ?>
        </div>
    </section>

    <?php if (isset($_GET['product_id'])): ?>
        <?php $product_id = intval($_GET['product_id']); ?>
        <?php if (hasPurchasedProduct($user_id, $product_id)): ?>
            <section class="review-form">
                <h3>Оставьте отзыв</h3>
                <form id="review-form">
                    <label for="rating">Оценка:</label>
                    <select id="rating" name="rating" required>
                        <option value="">Выберите оценку</option>
                        <option value="1">1 звезда</option>
                        <option value="2">2 звезды</option>
                        <option value="3">3 звезды</option>
                        <option value="4">4 звезды</option>
                        <option value="5">5 звезд</option>
                    </select>
                    <label for="comment">Комментарий:</label>
                    <textarea id="comment" name="comment" rows="4" required></textarea>
                    <input type="hidden" name="product_id" value="<?= $product_id ?>">
                    <button type="submit" class="btn">Оставить отзыв</button>
                </form>
            </section>
        <?php else: ?>
            <p>Вы не можете оставить отзыв, так как не покупали этот товар.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<footer>
    <p>&copy; 2025 MyShop — Все права защищены.</p>
</footer>

<script>
function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = 'notification ' + type + ' show';
    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        document.getElementById('preloader').style.opacity = 0;
        document.getElementById('preloader').style.visibility = 'hidden';
    }, 1000);
});

document.getElementById('review-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const productId = this.querySelector('input[name="product_id"]').value;
    const rating = document.getElementById('rating').value;
    const comment = document.getElementById('comment').value;

    if (!rating || !comment) {
        showNotification('Пожалуйста, заполните все поля.', 'error');
        return;
    }

    fetch('add_review.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            product_id: parseInt(productId),
            user_id: <?= $user_id ?>,
            rating: parseInt(rating),
            comment: comment
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Отзыв успешно добавлен!', 'success');
            this.reset();
            location.reload();
        } else {
            showNotification('Ошибка при добавлении отзыва.', 'error');
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showNotification('Произошла ошибка.', 'error');
    });
});

function toggleTheme() {
    const current = document.body.getAttribute('data-theme');
    document.body.setAttribute('data-theme', current === 'dark' ? 'light' : 'dark');
}
</script>
</body>
</html>