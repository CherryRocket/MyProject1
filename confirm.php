<?php
// Подключение к базе данных
require_once 'db_connect.php';

// Получаем токен из параметров URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Проверяем токен в базе данных и обновляем статус
    $sql = "UPDATE users SET is_confirmed = 1, role_id = 2 WHERE confirm_token = ? AND is_confirmed = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();

    // Проверяем, была ли обновлена хотя бы одна запись
    if ($stmt->affected_rows > 0) {
        $message = "Аккаунт успешно подтвержден! Сейчас вы будете перенаправлены...";
        $success = true;
    } else {
        $message = "Ошибка подтверждения аккаунта или ссылка уже использована.";
        $success = false;
        
    }
} else {
    $message = "Токен не указан.";
    $success = false;
}

// Закрываем соединение
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подтверждение аккаунта</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f4f4f4;
            text-align: center;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color: <?= $success ? '#4CAF50' : '#D32F2F' ?>;
        }
        p {
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            background-color: <?= $success ? '#4CAF50' : '#D32F2F' ?>;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><?= $message ?></h2>
        <p>Вы будете перенаправлены через 3 секунды.</p>
        <p><a href="index.php" class="btn">Перейти на главную</a></p>
    </div>

    <?php if ($success): ?>
        <script>
            setTimeout(function() {
                window.location.href = 'index.php';
            }, 3000);
        </script>
    <?php endif; ?>
</body>
</html>
