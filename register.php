<?php
// register.php — Регистрация пользователя с подтверждением через email

require_once 'db_connect.php';
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-6.9.3/src/Exception.php';
require 'PHPMailer-6.9.3/src/PHPMailer.php';
require 'PHPMailer-6.9.3/src/SMTP.php';

$from_email = 'koroleffilyas@yandex.ru'; // заменить на ваш Yandex email
$password = 'velomfifnssyerbb'; // пароль приложения Yandex

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password_user = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($email) || empty($password_user) || empty($confirm_password)) {
        $error = "Все поля обязательны для заполнения";
    } elseif ($password_user !== $confirm_password) {
        $error = "Пароли не совпадают";
    } else {
        // Проверка уникальности email
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error = "Пользователь с таким email уже существует";
        } else {
            // Генерация нового ID
            $result_max = $conn->query("SELECT MAX(id) AS max_id FROM users");
            $row_max = $result_max->fetch_assoc();
            $new_id = $row_max['max_id'] + 1;

            // Генерация токена подтверждения
            $confirm_token = bin2hex(random_bytes(50));

            // Хэширование пароля
            $hashed_password = ($password_user);

            // Вставка пользователя в БД
            $stmt_insert = $conn->prepare("INSERT INTO users (id, email, password, confirm_token) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param("isss", $new_id, $email, $hashed_password, $confirm_token);

            if (!$stmt_insert->execute()) {
                error_log("Ошибка при регистрации: " . $stmt_insert->error);
                $error = "Не удалось создать аккаунт";
            } else {
                // Формируем ссылку подтверждения
                $confirmation_link = "http://newproject/confirm.php?token=" . urlencode($confirm_token);

                // Отправляем письмо
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.yandex.ru';
                    $mail->SMTPAuth = true;
                    $mail->Username = $from_email;
                    $mail->Password = $password;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port = 465;
                    $mail->CharSet = 'UTF-8';

                    $mail->setFrom($from_email, 'MyShop');
                    $mail->addAddress($email);
                    $mail->isHTML(true);
                    $mail->Subject = 'Подтвердите ваш аккаунт';

                    $mail->Body = '
                        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: auto;">
                            <h2 style="color: #007BFF;">Добро пожаловать в MyShop!</h2>
                            <p>Спасибо за регистрацию. Чтобы активировать аккаунт, нажмите кнопку ниже:</p>
                            <a href="' . $confirmation_link . '" 
                               style="display: inline-block; padding: 1rem 2rem; background-color: #007BFF; color: white; text-decoration: none; border-radius: 10px; font-weight: bold;">
                               Подтвердить email
                            </a>
                            <p style="margin-top: 1rem; font-size: 0.9rem; color: #666;">
                               Или скопируйте и вставьте эту ссылку в браузер:<br>
                               <small>' . $confirmation_link . '</small>
                            </p>
                        </div>
                    ';

                    $mail->AltBody = "Перейдите по ссылке: $confirmation_link";

                    if ($mail->send()) {
                        $success = "Регистрация успешна! Проверьте вашу почту и подтвердите аккаунт.";
                    } else {
                        $error = "Ошибка при отправке письма: " . $mail->ErrorInfo;
                    }
                } catch (Exception $e) {
                    $error = "Ошибка при отправке письма: {$mail->ErrorInfo}";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Регистрация — MyShop</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .auth-container {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 400px;
        }

        h2 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.6rem 1rem;
            border: 1px solid #ccc;
            border-radius: var(--border-radius);
            font-size: 1rem;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.2);
        }

        .btn {
            width: 100%;
            padding: 0.7rem;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .auth-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.9rem;
        }

        a {
            color: var(--primary);
            text-decoration: none;
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
            background-color: var(--success);
        }

        .notification.error {
            background-color: var(--danger);
        }

        .notification.warning {
            background-color: orange;
        }

        /* Сообщение о подтверждении */
        .verification-message {
            background: var(--success);
            color: white;
            padding: 1rem;
            margin-top: 1rem;
            border-radius: var(--border-radius);
            font-size: 0.95rem;
            text-align: center;
        }

        .verification-message a {
            color: white;
            font-weight: bold;
            text-decoration: underline;
        }
    </style>
</head>
<body data-theme="light">

<!-- Уведомления -->
<div class="notification <?= !empty($error) ? 'error' : 'success' ?>" id="notification">
    <?= htmlspecialchars($error ?: $success) ?>
</div>

<?php if (!empty($error)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const notification = document.getElementById('notification');
    notification.classList.add('show');
    setTimeout(() => notification.classList.remove('show'), 4000);
});
</script>
<?php elseif (!empty($success)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const notification = document.getElementById('notification');
    notification.textContent = "<?= str_replace('Проверьте вашу почту', '', $success) ?>";
    notification.className = 'notification success show';
    setTimeout(() => notification.classList.remove('show'), 4000);
});
</script>
<?php endif; ?>

<div class="auth-container">
    <h2>Регистрация</h2>
    <form method="post">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required placeholder="example@example.com" value="<?= htmlspecialchars($email ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="password">Пароль</label>
            <input type="password" id="password" name="password" required minlength="6">
        </div>
        <div class="form-group">
            <label for="confirm_password">Подтвердите пароль</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
        </div>
        <button type="submit" class="btn">Зарегистрироваться</button>
    </form>

<!-- Сообщение о подтверждении -->
<?php if (!empty($success)): ?>
    <div class="verification-message">
        ✅ Проверьте вашу почту и перейдите по ссылке для подтверждения.<br><br>
    </div>
<?php endif; ?>

    <p class="auth-footer">
        Уже есть аккаунт?
        <a href="login.php">Войти</a>
    </p>
</div>

</body>
</html>