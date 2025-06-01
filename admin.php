<?php
// admin.php — Админ-панель для добавления товаров
require_once 'db_connect.php';
session_start();

// Проверка авторизации администратора
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: login.php');
    exit;
}

// Подключение PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-6.9.3/src/Exception.php';
require 'PHPMailer-6.9.3/src/PHPMailer.php';
require 'PHPMailer-6.9.3/src/SMTP.php';

// SMTP настройки
$from_email = 'koroleffilyas@yandex.ru';
$password = 'velomfifnssyerbb';

// Обработка формы добавления товара
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval(trim($_POST['price']));
    $category_id = intval(trim($_POST['category_id']));
    $image_path = filter_var(trim($_POST['image_path']), FILTER_VALIDATE_URL);

    if (!$image_path) {
        echo json_encode(['success' => false, 'message' => 'Неверная ссылка на изображение']);
        exit;
    }

    // Добавляем товар в БД
    $stmt = $conn->prepare("INSERT INTO products (name, description, price, category_id, image_path) VALUES (?, ?, ?, ?, ?)");
    if ($stmt === false) {
        http_response_code(500);
        exit(json_encode(['success' => false, 'message' => 'Ошибка подготовки запроса']));
    }

    $stmt->bind_param("sdids", $name, $description, $price, $category_id, $image_path);

    if ($stmt->execute()) {
        // Получаем все email пользователей
        $result = $conn->query("SELECT email FROM users WHERE is_confirmed = 1");

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.yandex.ru';
                    $mail->SMTPAuth = true;
                    $mail->Username = $from_email;
                    $mail->Password = $password;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port = 465;

                    $mail->setFrom($from_email, 'MyShop');
                    $mail->addAddress($row['email']);
                    $mail->CharSet = 'UTF-8';
                    $mail->isHTML(true);
                    $mail->Subject = "Добавлен новый товар: $name";
                    $mail->Body = "
                        <h2>Добавлен новый товар</h2>
                        <p><strong>Название:</strong> $name</p>
                        <p><strong>Описание:</strong> $description</p>
                        <p><strong>Цена:</strong> $price руб.</p>
                        <p><a href='$image_path'>Посмотреть изображение</a></p>
                    ";
                    $mail->AltBody = "Добавлен новый товар: $name\nОписание: $description\nЦена: $price руб.\nИзображение: $image_path";

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Ошибка отправки письма: {$e->getMessage()}");
                }
            }
        }

        echo json_encode(['success' => true, 'message' => 'Товар успешно добавлен']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка при добавлении товара']);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Админ-панель</title>
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

        nav ul {
            list-style: none;
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 0.5rem;
        }

        nav li {
            display: inline;
        }

        nav a {
            color: var(--dark);
            font-weight: 500;
            transition: color 0.3s;
        }

        nav a:hover {
            color: var(--primary);
        }

        .admin-container {
            max-width: 800px;
            margin: 2rem auto;
            background: var(--light);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 2rem;
        }

        .admin-container h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        input[type="text"],
        input[type="number"],
        input[type="url"],
        textarea,
        select {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid #ccc;
            border-radius: var(--border-radius);
            font-size: 1rem;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 0.8rem 1.2rem;
            font-size: 1rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #0056b3;
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
            z-index: 99999;
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            color: white;
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

        /* Загрузчик */
        .loader {
            border: 16px solid #f3f3f3;
            border-top: 16px solid var(--primary);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 2s linear infinite;
            margin: 2rem auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Сообщение об успехе */
        .success-message {
            display: none;
            margin-top: 1.5rem;
            padding: 1rem;
            background: var(--success);
            color: white;
            border-radius: var(--border-radius);
            text-align: center;
        }

        .error-message {
            display: none;
            margin-top: 1.5rem;
            padding: 1rem;
            background: var(--danger);
            color: white;
            border-radius: var(--border-radius);
            text-align: center;
        }
    </style>
</head>
<body data-theme="light">

<div class="preloader" id="preloader">
    <div class="loader"></div>
</div>

<header>
    <h1>MyShop — Админ-панель</h1>
    <nav>
        <ul class="nav-links">
            <li><a href="index.php">Главная</a></li>
            <li><a href="add_tovar.php">Добавить товар</a></li>
            <li><a href="naklad.php">Накладные</a></li>
            <li><a href="editMenu.php">Товары</a></li>
            <li><a href="orderslist.php">Заказы</a></li>
            <li><a href="stats.php">Статистика</a></li>
            <li><a href="logout.php">Выйти</a></li>
        </ul>
    </nav>
</header>


<footer>
    <p>&copy; <?= date("Y") ?> MyShop — Все права защищены.</p>
</footer>


</body>
</html>