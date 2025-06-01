<?php
// Подключение к базе данных
require_once 'db_connect.php';
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Получаем данные пользователя
$user_id = $_SESSION['user_id'];
$sql_user = "SELECT * FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user = $stmt_user->get_result()->fetch_assoc();

// Если пользователь администратор (role_id = 1), запрещаем смену пароля
$is_admin = ($user['role_id'] == 1);

// Настройки PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-6.9.3/src/Exception.php';
require 'PHPMailer-6.9.3/src/PHPMailer.php';
require 'PHPMailer-6.9.3/src/SMTP.php';

$from_email = 'koroleffilyas@yandex.ru';
$mail_password = 'velomfifnssyerbb'; // пароль приложения Yandex

function sendVerificationCode($email, $code) {
    global $from_email, $mail_password;

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
        $mail->addAddress($email);
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = "Код подтверждения для смены пароля";

        $mail->Body = "
            <h2>Код подтверждения</h2>
            <p>Ваш код для смены пароля: <strong>$code</strong></p>
            <p>Введите его на сайте.</p>
        ";
        $mail->AltBody = "Ваш код для смены пароля: $code\nВведите его на сайте.";

        return $mail->send();
    } catch (Exception $e) {
        error_log("Mail error: " . $e->getMessage());
        return false;
    }
}

// AJAX-обработка запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => 'Неизвестная ошибка'];

    if ($_POST['action'] === 'send_code') {
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

        if (!$email) {
            $response['message'] = 'Некорректный email';
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $response['message'] = 'Пользователь с таким email не найден';
            } else {
                $code = substr(str_shuffle("0123456789"), 0, 6); // 6-значный код
                $_SESSION['reset_code'] = $code;
                $_SESSION['reset_email'] = $email;

                if (sendVerificationCode($email, $code)) {
                    $response = ['success' => true, 'message' => 'Код отправлен на ваш email'];
                } else {
                    $response['message'] = 'Ошибка при отправке письма';
                }
            }
        }

    } elseif ($_POST['action'] === 'change_password') {
        $input_code = trim($_POST['code']);
        $new_password = trim($_POST['new_password']);

        if (!isset($_SESSION['reset_code']) || $_SESSION['reset_code'] !== $input_code) {
            $response['message'] = 'Неверный код подтверждения';
        } elseif (strlen($new_password) < 6) {
            $response['message'] = 'Пароль должен быть не менее 6 символов';
        } else {
            $email = $_SESSION['reset_email'];
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->bind_param("ss", $new_password, $email);
            if ($stmt->execute()) {
                unset($_SESSION['reset_code']);
                unset($_SESSION['reset_email']);
                $response = ['success' => true, 'message' => 'Пароль успешно изменён'];
            } else {
                $response['message'] = 'Ошибка при изменении пароля';
            }
        }
    }

    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Профиль — MyShop</title>
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
            display: flex;
            flex-direction: column;
            max-width: 1200px;
            margin: auto;
            padding: 2rem;
        }
        @media (min-width: 768px) {
            .main-container {
                flex-direction: row;
                gap: 2rem;
            }
        }
        .profile-container {
            flex: 3;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        .profile-header {
            background: linear-gradient(to right, var(--primary), #0056b3);
            color: white;
            padding: 1.5rem;
        }
        .profile-header h2 {
            font-size: 1.5rem;
            margin: 0;
        }
        .profile-info {
            padding: 1.5rem;
        }
        .info-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 2rem;
        }
        .user-avatar img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--primary);
            margin-bottom: 1rem;
        }
        .user-details h3 {
            margin-bottom: 1rem;
        }
        .user-details p {
            margin: 0.4rem 0;
        }
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
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
        /* Модальное окно */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            max-width: 500px;
            width: 90%;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        .close-btn {
            float: right;
            font-size: 1.2rem;
            cursor: pointer;
            font-weight: bold;
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
    <h1>MyShop</h1>
</header>

<div class="main-container">
    <div class="profile-container">
        <div class="profile-header">
            <h2>Профиль пользователя</h2>
        </div>
        <div class="profile-info">
            <div class="info-item">
                <div class="user-avatar">
                    <img src="https://otvet.imgsmail.ru/download/875a8375f91de049494d6073098e8a2f_6bd296c3728f5def4dbd047883873acf.gif " alt="Аватар пользователя">
                </div>
                <div class="user-details">
                    <h3><?= htmlspecialchars($user['name']) ?></h3>
                    <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                    <p><strong>Роль:</strong> <?= $user['role_id'] == 1 ? "Администратор ✅" : "Пользователь"; ?></p>
                    <p><strong>Статус:</strong> <?= $user['is_confirmed'] ? "Подтвержден ✅" : "Не подтвержден"; ?></p>
                </div>
            </div>
            <div class="action-buttons">
                <?php if (!$is_admin): ?>
                    <button onclick="openRequestCodeModal()" class="btn">Сменить пароль</button>
                <?php else: ?>
                    <button disabled class="btn" style="background-color: #ccc;">Админ не может менять пароль</button>
                <?php endif; ?>
                <button onclick="document.getElementById('editProfileModal').style.display='flex'" class="btn">Редактировать email</button>
            </div>
        </div>
    </div>
    <aside class="sidebar-right">
        <ul>
            <li><a href="index.php"><i class="fas fa-home"></i> Главная</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти</a></li>
            <li><a href="orders.php"><i class="fas fa-box"></i> Мои заказы</a></li>
            <li><a href="reviews.php"><i class="fas fa-box"></i> Мои отзывы</a></li>
            <?php if ($user['role_id'] == 1): ?>
                <li><a href="admin.php"><i class="fas fa-cog"></i> Панель администратора</a></li>
            <?php endif; ?>
        </ul>
    </aside>
</div>

<footer>
    <p>&copy; <?= date("Y") ?> MyShop — Все права защищены.</p>
</footer>

<!-- Модальное окно: запрос email -->
<div class="modal" id="requestCodeModal">
    <div class="modal-content">
        <span class="close-btn" onclick="this.closest('.modal').style.display='none'">&times;</span>
        <h3 style="margin-bottom: 1rem;">Введите ваш email</h3>
        <form id="requestCodeForm">
            <label style="display: block; margin-bottom: 0.8rem;">
                Email:
                <input type="email" id="requestCodeEmail" required style="width: 100%; padding: 0.5rem; margin-top: 0.3rem; border-radius: 6px; border: 1px solid #ccc;">
            </label>
            <div style="display: flex; justify-content: space-between; margin-top: 1rem;">
                <button type="submit" class="btn">Отправить код</button>
                <button type="button" onclick="document.getElementById('requestCodeModal').style.display='none'" class="btn" style="background-color: var(--danger);">Отмена</button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно: проверка кода и смена пароля -->
<div class="modal" id="verifyCodeModal">
    <div class="modal-content">
        <span class="close-btn" onclick="this.closest('.modal').style.display='none'">&times;</span>
        <h3 style="margin-bottom: 1rem;">Введите код из письма</h3>
        <form id="verifyCodeForm">
            <label style="display: block; margin-bottom: 0.8rem;">
                Код:
                <input type="text" id="verifyCodeInput" required style="width: 100%; padding: 0.5rem; margin-top: 0.3rem; border-radius: 6px; border: 1px solid #ccc;">
            </label>
            <label style="display: block; margin-bottom: 0.8rem;">
                Новый пароль:
                <input type="password" id="newPasswordInput" minlength="6" required style="width: 100%; padding: 0.5rem; margin-top: 0.3rem; border-radius: 6px; border: 1px solid #ccc;">
            </label>
            <div style="display: flex; justify-content: space-between; margin-top: 1rem;">
                <button type="submit" class="btn">Сохранить пароль</button>
                <button type="button" onclick="document.getElementById('verifyCodeModal').style.display='none'" class="btn" style="background-color: var(--danger);">Отмена</button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно для редактирования email -->
<div class="modal" id="editProfileModal">
    <div class="modal-content">
        <span class="close-btn" onclick="this.closest('.modal').style.display='none'">&times;</span>
        <h3 style="margin-bottom: 1rem;">Редактировать email</h3>
        <form method="post">
            <input type="hidden" name="edit_profile" value="1">
            <label style="display: block; margin-bottom: 0.8rem;">
                Email:
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required style="width: 100%; padding: 0.5rem; margin-top: 0.3rem; border-radius: 6px; border: 1px solid #ccc;">
            </label>
            <div style="display: flex; justify-content: space-between; margin-top: 1rem;">
                <button type="submit" class="btn">Сохранить</button>
                <button type="button" onclick="document.getElementById('editProfileModal').style.display='none'" class="btn" style="background-color: var(--danger);">Отмена</button>
            </div>
        </form>
    </div>
</div>

<script>
function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = 'notification ' + type + ' show';
    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}

function openRequestCodeModal() {
    document.getElementById('requestCodeModal').style.display = 'flex';
}

document.getElementById('requestCodeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const email = document.getElementById('requestCodeEmail').value;

    fetch('profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=send_code&email=' + encodeURIComponent(email)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('requestCodeModal').style.display = 'none';
            document.getElementById('verifyCodeModal').style.display = 'flex';
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message, 'error');
        }
    });
});

document.getElementById('verifyCodeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const code = document.getElementById('verifyCodeInput').value;
    const newPassword = document.getElementById('newPasswordInput').value;

    fetch('profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=change_password&code=' + encodeURIComponent(code) + '&new_password=' + encodeURIComponent(newPassword)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('verifyCodeModal').style.display = 'none';
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message, 'error');
        }
    });
});
</script>

<?php mysqli_close($conn); ?>
</body>
</html>