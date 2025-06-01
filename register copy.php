<?php
// Подключение к базе данных
require_once 'db_connect.php';
session_start();

// Настройки Yandex SMTP
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-6.9.3/src/Exception.php';
require 'PHPMailer-6.9.3/src/PHPMailer.php';
require 'PHPMailer-6.9.3/src/SMTP.php';

$from_email = 'koroleffilyas@yandex.ru'; // Замените на ваш Yandex email
$password = 'velomfifnssyerbb'; // Пароль приложения Yandex

// Обработка формы регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password_user = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Базовая валидация
    if (empty($email) || empty($password_user) || empty($confirm_password)) {
        $error = "Все поля обязательны для заполнения";
    } elseif ($password_user !== $confirm_password) {
        $error = "Пароли не совпадают";
    } else {
        // Проверка наличия пользователя с таким email
        $sql_check = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql_check);
        if (!$stmt) {
            die("Ошибка подготовки запроса: " . $conn->error);
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Пользователь с таким email уже зарегистрирован";
        } else {
            // Получаем максимальный ID и увеличиваем его на 1
            $sql_max_id = "SELECT MAX(id) AS max_id FROM users";
            $result_max_id = $conn->query($sql_max_id);
            $row_max = $result_max_id->fetch_assoc();
            $new_id = $row_max['max_id'] + 1;

            // Генерация уникального токена для подтверждения аккаунта
            $confirm_token = substr(bin2hex(random_bytes(96)), 0, 191);

            // Вставка нового пользователя в базу данных с ID
            $sql_insert = "INSERT INTO users (id, email, password, confirm_token) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_insert);
            if (!$stmt) {
                die("Ошибка подготовки запроса INSERT: " . $conn->error);
            }
            $stmt->bind_param("isss", $new_id, $email, $password_user, $confirm_token);
            if (!$stmt->execute()) {
                $error_message = "Ошибка при регистрации: " . $stmt->error;
                file_put_contents("error_log.txt", $error_message . "\n", FILE_APPEND);
                die($error_message);
            } else {
                // Формирование ссылки для подтверждения аккаунта
                $confirmation_link = "http://newproject/confirm.php?token=" . urlencode($confirm_token);

                // Отправка письма через Yandex SMTP
                $mail = new PHPMailer(true);
                try {
                    // Настройка SMTP
                    $mail->isSMTP();
                    $mail->SMTPDebug = 2; // Уровень отладки 2 - покажет детальный лог
                    $mail->Host = 'smtp.yandex.ru';
                    $mail->SMTPAuth = true;
                    $mail->Username = $from_email;
                    $mail->Password = $password;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port = 465;

                    // Отправитель и получатель
                    $mail->setFrom($from_email, 'koroleffilyas');
                    $mail->addAddress($email);
                    $mail->CharSet = 'UTF-8'; // Указываем кодировку
                    // Содержимое письма
                    $mail->isHTML(true);
                    $mail->Subject = 'Подтвердите ваш аккаунт';
                    $mail->Body = "
                        <html>
                            <body>
                                <p>Спасибо за регистрацию!</p>
                                <p>Чтобы подтвердить ваш аккаунт, перейдите по следующей ссылке:</p>
                                <a href='$confirmation_link'>$confirmation_link</a>
                            </body>
                        </html>
                    ";
                    $mail->AltBody = 'Для подтверждения аккаунта перейдите по ссылке: ' . $confirmation_link;

                    // Отправка письма
                    if ($mail->send()) {
                        $_SESSION['success'] = "Регистрация успешно завершена! Пожалуйста, подтвердите ваш аккаунт через email.";
                        header('Location: login.php');
                        exit;
                    } else {
                        $error_message = "Ошибка при отправке письма: " . $mail->ErrorInfo;
                        file_put_contents("error_log.txt", $error_message . "\n", FILE_APPEND);
                        die($error_message);
                    }
                } catch (Exception $e) {
                    $error_message = "Ошибка PHPMailer: " . $mail->ErrorInfo;
                    file_put_contents("error_log.txt", $error_message . "\n", FILE_APPEND);
                    die($error_message);
                }
            }
        }
    }
}

// Вывод ошибки на экран (для тестирования)
if (isset($error)) {
    echo "<p style='color:red;'>Ошибка: $error</p>";
}
?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="auth-container">
    <h2>Регистрация</h2>
    <?php if (isset($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form action="register.php" method="POST">
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Пароль:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="confirm_password">Подтвердите пароль:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn">Зарегистрироваться</button>
        <p>Уже есть аккаунт? <a href="login.php">Войти</a></p>
    </form>
</div>
</body>
</html>