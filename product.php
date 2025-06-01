    <?php
    // Подключение к базе данных
    require_once 'db_connect.php';
    session_start();

    // Проверка авторизации
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    // Получаем ID товара из параметров URL
    if (isset($_GET['id'])) {
        $product_id = intval($_GET['id']);
    } else {
        header('Location: index.php'); // Если ID не указан, перенаправляем на главную страницу
        exit;
    }

    // Запрос на выборку товара по ID
    $sql_product = "SELECT *, (SELECT stock FROM products WHERE id = ?) AS stock FROM products WHERE id = ?";
    $stmt = $conn->prepare($sql_product);
    $stmt->bind_param("ii", $product_id, $product_id);
    $stmt->execute();
    $product_result = $stmt->get_result();

    if ($product_result->num_rows === 0) {
        echo "Товар не найден";
        exit;
    }
    $product = $product_result->fetch_assoc();
    $stock = $product['stock'];
    mysqli_close($conn);
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <title><?= htmlspecialchars($product['name']) ?></title>
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
            .product-container {
                background: white;
                border-radius: var(--border-radius);
                box-shadow: var(--shadow);
                overflow: hidden;
                margin-bottom: 2rem;
            }
            .product-header {
                background: linear-gradient(to right, var(--primary), #0056b3);
                color: white;
                padding: 1.0rem;
            }
            .product-header h2 {
                font-size: 1.0rem;
                margin: 0;
            }
            .product-info {
                padding: 1.0rem;
            }
            .product-image img {
                width: 30%;
                height: 200px;
                border-radius: var(--border-radius);
                object-fit: cover;
            }
            .product-details {
                margin-top: 1rem;
            }
            .product-description {
                margin-bottom: 1rem;
            }
            .product-price {
                font-weight: bold;
                color: var(--dark);
            }
            .product-actions {
                margin-top: 1rem;
                display: flex;
                flex-wrap: wrap;
                gap: 1rem;
                align-items: center;
            }
            .quantity-controls {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .quantity-controls input[type="number"] {
                width: 60px;
                padding: 0.5rem;
                border-radius: var(--border-radius);
                border: 1px solid #ccc;
                text-align: center;
            }
            .quantity-controls button {
                width: 30px;
                height: 30px;
                background-color: var(--primary);
                color: white;
                border: none;
                border-radius: 50%;
                cursor: pointer;
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
            /* Уведомления */
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
            .notification.warning {
                background-color: orange;
            }
            .notification.info {
                background-color: var(--primary);
            }
            .sidebar-right {
                flex: 1;
                background: white;
                border-radius: var(--border-radius);
                box-shadow: var(--shadow);
                padding: 1.5rem;
            }
            .sidebar-right ul {
                list-style: none;
            }
            .sidebar-right li {
                margin-bottom: 1rem;
            }
            .sidebar-right a {
                color: var(--dark);
                font-weight: 500;
                transition: color 0.3s;
            }
            .sidebar-right a:hover {
                color: var(--primary);
            }
            .reviews-section {
                margin-top: 2rem;
            }
            .reviews-section h3 {
                margin-bottom: 1rem;
            }
            .review {
                border-bottom: 1px solid #ddd;
                padding: 1rem 0;
            }
            .review strong {
                display: block;
                margin-bottom: 0.5rem;
            }
            .review p {
                margin-bottom: 0.5rem;
            }
            .review-form {
                margin-top: 2rem;
            }
            .review-form h4 {
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
        </style>
    </head>
    <body data-theme="light">
    <!-- Уведомление -->
    <div class="notification success" id="notification"></div>
    <div class="preloader" id="preloader">
        <i class="fas fa-spinner fa-spin fa-3x"></i>
    </div>
    <header>
        <a href="index.php"><i class="fas fa-home"></i> Главная</a>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти</a></li>
        <li><a href="orders.php"><i class="fas fa-box"></i> Мои заказы</a></li>
        <?php if ($_SESSION['role_id'] == 1): ?>
            <li><a href="admin.php"><i class="fas fa-cog"></i> Панель администратора</a></li>
        <?php endif; ?>
    </header>
    <div class="main-container">
        <div class="product-container">
            <div class="product-header">
                <h2><?= htmlspecialchars($product['name']) ?></h2>
                <p class="product-category">Категория: <?= htmlspecialchars($product['category_id']) ?></p>
            </div>
            <div class="product-info">
                <div class="product-image">
                    <img src="<?= htmlspecialchars($product['image_path']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                </div>
                <div class="product-details">
                    <p class="product-description"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                    <p class="product-price">Цена: <?= number_format($product['price'], 0, '', ' ') ?> ₽</p>
                    <p class="product-stock">Осталось на складе: <span id="stock"><?= htmlspecialchars($product['stock']) ?></span> шт</p>
                    <div class="product-actions">
                        <!-- Контроли количества -->
                        <div class="quantity-controls">
                            <button onclick="changeQuantity(-1)">−</button>
                            <input type="number" id="quantity" value="1" min="1" max="<?= $stock ?>" readonly style="text-align:center;">
                            <button onclick="changeQuantity(1)">+</button>
                        </div>
                        <!-- Кнопки -->
                        <div style="margin-top: 1rem;">
                            <button class="btn" onclick="addToCart(<?= $product['id'] ?>)">Добавить в корзину</button>
                            <a href="cart.php" class="btn">Посмотреть корзину</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Отзывы -->
        <div class="reviews-section">
            <h3>Отзывы</h3>
            <div id="reviews-list"></div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="review-form">
                    <h4>Оставьте отзыв</h4>
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

                        <button type="submit" class="btn">Оставить отзыв</button>
                    </form>
                </div>
            <?php else: ?>
                <p>Пожалуйста, <a href="login.php">войдите</a>, чтобы оставить отзыв.</p>
            <?php endif; ?>
        </div>
    </div>
    <footer>
        <p>&copy; <?= date("Y") ?> MyShop — Все права защищены.</p>
    </footer>
    <script>
    const quantityInput = document.getElementById('quantity');
    const stockSpan = document.getElementById('stock');
    const maxStock = parseInt(stockSpan.textContent);

    function changeQuantity(delta) {
        let current = parseInt(quantityInput.value);
        let newQuantity = current + delta;
        if (newQuantity < 1) newQuantity = 1;
        if (newQuantity > maxStock) newQuantity = maxStock;
        quantityInput.value = newQuantity;
    }

    function addToCart(productId) {
        const userId = <?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null' ?>;
        if (userId === null) {
            showNotification('Пожалуйста, войдите, чтобы добавить товар в корзину.', 'error');
            return;
        }
        const quantity = parseInt(document.getElementById('quantity').value);
        if (isNaN(quantity) || quantity <= 0 || quantity > maxStock) {
            showNotification('Неверное количество', 'error');
            return;
        }
        fetch('add_to_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                product_id: productId,
                user_id: userId,
                quantity: quantity
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(`Товар добавлен (${quantity})`, 'success');
                updateCartCounter();
            } else {
                showNotification('Ошибка при добавлении в корзину', 'error');
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
                    document.getElementById('cart-counter').innerText = data.count;
                }
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadReviews();
        setTimeout(() => {
            document.getElementById('preloader').style.opacity = 0;
            document.getElementById('preloader').style.visibility = 'hidden';
        }, 1000);
    });

    function loadReviews() {
    const productId = <?= $product['id'] ?>;
    fetch(`get_reviews.php?product_id=${productId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Сеть не отвечает');
            }
            return response.json();
        })
        .then(data => {
            const reviewsList = document.getElementById('reviews-list');
            reviewsList.innerHTML = '';
            if (!data.reviews || data.reviews.length === 0) {
                reviewsList.innerHTML = '<p>Нет отзывов.</p>';
                return;
            }
            data.reviews.forEach(review => {
                const reviewDiv = document.createElement('div');
                reviewDiv.className = 'review';
                reviewDiv.innerHTML = `
                    <strong>Пользователь ${review.user_id}</strong>
                    <p>Оценка: ${'★'.repeat(review.rating)}${'☆'.repeat(5 - review.rating)}</p>
                    <p>${review.comment}</p>
                    <small>${new Date(review.created_at).toLocaleString()}</small>
                `;
                reviewsList.appendChild(reviewDiv);
            });
        })
        .catch(error => {
            console.error('Ошибка при загрузке отзывов:', error);
            document.getElementById('reviews-list').innerHTML = '<p>Не удалось загрузить отзывы.</p>';
        });
}

document.getElementById('review-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const productId = <?= $product['id'] ?>;
    const userId = <?= $_SESSION['user_id'] ?>;
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
            product_id: productId,
            user_id: userId,
            rating: parseInt(rating),
            comment: comment
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Отзыв успешно добавлен!', 'success');
            document.getElementById('review-form').reset();
            loadReviews();
        } else if (data.error && data.error.includes('не покупали')) {
            showNotification('Вы не можете оставить отзыв, так как не покупали этот товар', 'error');
        } else {
            showNotification('Ошибка при добавлении отзыва.', 'error');
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showNotification('Произошла ошибка.', 'error');
    });
});
    </script>
    </body>
    </html>