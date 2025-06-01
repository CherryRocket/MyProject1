<?php
// add_product.php — Добавление товара с загрузкой изображения
require_once 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: login.php');
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-6.9.3/src/Exception.php';
require 'PHPMailer-6.9.3/src/PHPMailer.php';
require 'PHPMailer-6.9.3/src/SMTP.php';

$from_email = 'koroleffilyas@yandex.ru';
$mail_password = 'velomfifnssyerbb'; // пароль приложения Yandex

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval(trim($_POST['price']));
    $stock = intval(trim($_POST['stock'])); // используем int для количества
    $category_id = intval(trim($_POST['category_id']));

    // Проверка наличия папки uploads
    if (!is_dir('uploads')) {
        mkdir('uploads', 0777, true);
    }

    // Проверка загрузки файла
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error = "Пожалуйста, загрузите изображение";
    } else {
        $image_name = basename($_FILES['image']['name']);
        $image_tmp = $_FILES['image']['tmp_name'];
        $image_path = 'uploads/' . uniqid() . '-' . $image_name;

        if (!move_uploaded_file($image_tmp, $image_path)) {
            $error = "Не удалось сохранить изображение";
        } else {
            // Теперь 6 полей и 6 значений
            $stmt = $conn->prepare("
                INSERT INTO products 
                (name, description, image_path, price, stock, category_id) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                $error = "Ошибка подготовки запроса: " . $conn->error;
            } else {
                // bind_param с 6 значениями: s s s d i i
                $stmt->bind_param("sssdii", $name, $description, $image_path, $price, $stock, $category_id);

                if (!$stmt->execute()) {
                    $error = "Ошибка при добавлении товара: " . $stmt->error;
                } else {
                    $success = "Товар успешно добавлен и изображение загружено";

                    // Отправка email всем подтвержденным пользователям
                    $users_result = $conn->query("SELECT email FROM users WHERE is_confirmed = 1");
                    while ($row = $users_result->fetch_assoc()) {
                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host = 'smtp.yandex.ru';
                            $mail->SMTPAuth = true;
                            $mail->Username = $from_email;
                            $mail->Password = $mail_password;
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                            $mail->Port = 465;
                            $mail->setFrom($from_email, 'MyShop');
                            $mail->addAddress($row['email']);
                            $mail->CharSet = 'UTF-8';
                            $mail->isHTML(true);
                            $mail->Subject = "Новый товар: $name";
                            $mail->Body = "
                                <h2>Добавлен новый товар</h2>
                                <p><strong>Название:</strong> $name</p>
                                <p><strong>Описание:</strong> $description</p>
                                <p><strong>Цена:</strong> $price ₽</p>
                                <img src='https://вашдомен/$image_path' width='300'>
                                <p><a href='https://вашдомен/product.php?id={$conn->insert_id}'>Подробнее о товаре</a></p>
                            ";
                            $mail->AltBody = "Добавлен новый товар: $name\nОписание: $description\nЦена: $price руб.\nСсылка: https://вашдомен/product.php?id={$conn->insert_id}";

                            $mail->send();
                        } catch (Exception $e) {
                            error_log("Ошибка отправки email: {$mail->ErrorInfo}");
                        }
                    }
                }
            }
        }
    }
}

// Получаем категории для выпадающего списка
$category_options = [];
$stmt = $conn->prepare("SELECT id, Name FROM category");
$stmt->execute();
$result = $stmt->get_result();
while ($cat = $result->fetch_assoc()) {
    $category_options[] = $cat;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <title>Добавить товар — MyShop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter :wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #007BFF;
            --secondary: #f5f7fa;
            --danger: #e74c3c;
            --success: #2ecc71;
            --border-radius: 10px;
            --shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--secondary);
            color: #333;
            margin: 0;
            padding: 2rem;
        }

        .container {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        h2 {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        input[type="text"],
        input[type="number"],
        input[type="file"],
        select,
        textarea {
            width: 100%;
            padding: 0.6rem 1rem;
            border: 1px solid #ccc;
            border-radius: var(--border-radius);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.8rem 1.2rem;
            border-radius: var(--border-radius);
            cursor: pointer;
        }

        .btn:hover {
            background: #0056b3;
        }

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

        footer {
            text-align: center;
            margin-top: 3rem;
            color: #666;
        }
    </style>
</head>
<body>

<div class="notification success" id="notification"></div>

<header>
    <h1>🛍 MyShop — Добавление товара</h1>
</header>

<div class="container">
    <h2>Добавить товар</h2>

    <?php if ($error): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const notification = document.getElementById('notification');
                notification.textContent = "<?= addslashes($error) ?>";
                notification.className = 'notification error show';
                setTimeout(() => notification.classList.remove('show'), 4000);
            });
        </script>
    <?php elseif ($success): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const notification = document.getElementById('notification');
                notification.textContent = "<?= addslashes($success) ?>";
                notification.className = 'notification success show';
                setTimeout(() => notification.classList.remove('show'), 4000);
            });
        </script>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="name">Название товара:</label>
            <input type="text" id="name" name="name" required>
        </div>

        <div class="form-group">
            <label for="description">Описание:</label>
            <textarea id="description" name="description" required></textarea>
        </div>

        <div class="form-group">
            <label for="price">Цена:</label>
            <input type="number" step="0.01" id="price" name="price" required>
        </div>

        <div class="form-group">
            <label for="stock">Количество на складе:</label>
            <input type="number" id="stock" name="stock" required>
        </div>

        <div class="form-group">
            <label for="category_id">Категория:</label>
            <select id="category_id" name="category_id" required>
                <?php foreach ($category_options as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['id']) ?>">
                        <?= htmlspecialchars($cat['Name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="image">Изображение:</label>
            <input type="file" id="image" name="image" accept="image/*" required>
        </div>

        <button type="submit" name="add_product" class="btn">Добавить товар</button>
    </form>
</div>

<footer>
    <p>&copy; <?= date("Y") ?> MyShop — Все права защищены.</p>
</footer>

<script>
function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = 'notification ' + type + ' show';
    setTimeout(() => notification.classList.remove('show'), 3000);
}
</script>

</body>
</html>