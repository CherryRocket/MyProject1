<?php
// order.php — Страница оформления заказа

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Подключение к базе данных
require_once 'db_connect.php';
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оформление заказа — MyShop</title>
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
            position: relative;
        }

        header h1 {
            font-size: 1.8rem;
            color: var(--primary);
        }

        .theme-toggle {
            position: absolute;
            right: 2rem;
            top: 1.2rem;
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: var(--dark);
        }

        .main-container {
            max-width: 1200px;
            margin: auto;
            padding: 2rem;
        }

        .checkout-title {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .cart-table th,
        .cart-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .cart-table th {
            background: #fafafa;
        }

        .summary {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            max-width: 400px;
            margin-left: auto;
        }

        .summary h3 {
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        .summary p {
            margin: 0.5rem 0;
        }

        .summary .total {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary);
            margin-top: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        input[type="text"],
        input[type="email"],
        select {
            width: 100%;
            padding: 0.6rem;
            border-radius: var(--border-radius);
            border: 1px solid #ccc;
            font-size: 1rem;
        }

        .btn {
            display: inline-block;
            padding: 0.8rem 1.2rem;
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

        .thank-you {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 4rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            max-width: 600px;
            margin: auto;
        }

        .thank-you i {
            font-size: 4rem;
            color: var(--success);
            margin-bottom: 1rem;
        }

        footer {
            text-align: center;
            padding: 2rem 1rem;
            background: #222;
            color: white;
            margin-top: 3rem;
        }

        /* Анимация */
        .animate-add {
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #cartAnimation {
            display: inline-block;
            font-size: 1.5rem;
            transition: transform 0.3s ease;
        }

        #cartAnimation.animate {
            transform: scale(1.2);
        }

        .empty-cart {
            text-align: center;
            font-size: 1.2rem;
            color: #888;
            margin-top: 2rem;
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

<div class="preloader" id="preloader">
    <i class="fas fa-spinner fa-spin fa-3x"></i>
</div>

<header>
    <h1>MyShop — Оформление заказа</h1>
</header>

<div class="main-container">
    <h2 class="checkout-title">Ваш заказ</h2>

    <!-- Корзина -->
    <table class="cart-table" id="cartTable">
        <thead>
            <tr>
                <th>Товар</th>
                <th>Кол-во</th>
                <th>Цена</th>
                <th>Итого</th>
            </tr>
        </thead>
        <tbody id="cartItems">
            <!-- Данные загружаются через JS -->
        </tbody>
    </table>

    <!-- Форма доставки -->
    <div class="summary" id="deliveryForm">
        <div class="form-group">
            <label for="deliveryType">Способ доставки</label>
            <select id="deliveryType" onchange="toggleAddress()">
                <option value="self_pickup">Самовывоз</option>
                <option value="delivery">Доставка</option>
            </select>
        </div>
        <div class="form-group" id="addressField" style="display: none;">
            <label for="deliveryAddress">Адрес доставки</label>
            <input type="text" id="deliveryAddress" placeholder="Город, улица, дом, квартира">
        </div>
        <button onclick="placeOrder()" class="btn">
            <i class="fas fa-shopping-cart"></i> Оформить заказ
        </button>
    </div>

    <!-- Экран после оформления -->
    <div class="thank-you" id="thankYouScreen">
        <i class="fas fa-check-circle"></i>
        <h2>Спасибо за заказ!</h2>
        <p>С вами свяжутся по вашему контактному почтовому адресу!</p>
        <a href="orders.php" class="btn" style="margin-top: 1rem;">Посмотреть свои заказы</a>
    </div>
</div>

<footer class="footer">
    <p>&copy; <?= date("Y") ?> MyShop — Все права защищены.</p>
</footer>

<i class="fas fa-shopping-cart" id="cartAnimation" style="display: none;"></i>

<script>
function toggleTheme() {
    const current = document.body.getAttribute('data-theme');
    document.body.setAttribute('data-theme', current === 'dark' ? 'light' : 'dark');
}

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        const preloader = document.getElementById('preloader');
        if (preloader) {
            preloader.style.opacity = 0;
            preloader.style.visibility = 'hidden';
        }
    }, 1000);

    fetchCartItems();
});

let total = 0;

function fetchCartItems() {
    fetch('get_cart_items.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('cartItems');
            if (!tbody) return;

            if (!data.success || data.items.length === 0) {
                document.querySelector('.main-container').innerHTML = '<p class="empty-cart">Ваша корзина пуста</p>';
                return;
            }

            tbody.innerHTML = '';
            total = 0;

            data.items.forEach(item => {
                const row = document.createElement('tr');
                const itemTotal = item.quantity * item.price;
                total += itemTotal;

                row.innerHTML = `
                    <td>${item.name}</td>
                    <td>${item.quantity}</td>
                    <td>${item.price.toLocaleString()} ₽</td>
                    <td>${itemTotal.toLocaleString()} ₽</td>
                `;
                tbody.appendChild(row);
            });

            const summary = document.createElement('p');
            summary.className = "total";
            summary.textContent = "Общая сумма: " + total.toLocaleString() + " ₽";
            document.getElementById('deliveryForm').appendChild(summary);
        })
        .catch(err => {
            console.error("Ошибка при получении корзины:", err);
        });
}

function toggleAddress() {
    const deliveryType = document.getElementById('deliveryType').value;
    const addressField = document.getElementById('addressField');
    addressField.style.display = (deliveryType === 'delivery') ? 'block' : 'none';
}

function placeOrder() {
    const deliveryType = document.getElementById('deliveryType').value;
    const address = document.getElementById('deliveryAddress').value;

    if (deliveryType === 'delivery' && address.trim() === '') {
        alert("Пожалуйста, укажите адрес доставки.");
        return;
    }

    fetch('order_process.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ deliveryType, address })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            document.getElementById('deliveryForm').style.display = 'none';
            document.getElementById('cartTable').style.display = 'none';
            document.getElementById('thankYouScreen').style.display = 'flex';
        } else {
            alert("Ошибка оформления заказа: " + result.message);
        }
    })
    .catch(error => {
        console.error("Ошибка при отправке заказа:", error);
        alert("Произошла ошибка при оформлении заказа.");
    });
}
</script>
</body>
</html>
