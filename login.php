<?php
// Подключение к базе данных
require_once 'db_connect.php';
session_start();

$error = '';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password']; // Пароль вводится в открытом виде

    // Проверка наличия пользователя в базе данных
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Проверяем, подтвержден ли аккаунт
        if ($user['is_confirmed'] == 0) {
            $error = "Аккаунт не подтвержден. Пожалуйста, подтвердите свой email.";
        } 
        // Простое сравнение паролей
        elseif ($password === $user['password']) { 
            // Успешный вход
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['email'] = $user['email'];

            // Перенаправление на основе роли
            if ($_SESSION['role_id'] == 1) {
                header('Location: admin.php');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $error = "Неверный email или пароль";
        }
    } else {
        $error = "Пользователь с таким email не найден";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — MyShop</title>
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
            --transition: all 0.3s ease-in-out;
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
            color: var(--primary);
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

        .auth-container {
            max-width: 400px;
            margin: 60px auto;
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .auth-container h2 {
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            color: var(--dark);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #ccc;
            border-radius: var(--border-radius);
            outline: none;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            border-color: var(--primary);
        }

        .btn {
            width: 100%;
            padding: 0.7rem;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .error {
            background: var(--danger);
            color: white;
            padding: 0.7rem;
            border-radius: var(--border-radius);
            text-align: center;
            margin-bottom: 1rem;
        }

        .signup-link {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.95rem;
        }

        .theme-toggle {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            color: var(--dark);
            font-size: 1.2rem;
            cursor: pointer;
        }

        footer {
            text-align: center;
            padding: 2rem 1rem;
            background: #222;
            color: white;
            margin-top: 3rem;
        }

        @media (max-width: 500px) {
            .auth-container {
                margin: 40px 1rem;
            }
        }
    </style>
</head>
<body data-theme="light">

<div class="preloader" id="preloader">
    <i class="fas fa-spinner fa-spin fa-2x"></i>
</div>

<header>
    <h1>🛍 MyShop</h1>
</header>

<div class="auth-container">
    <button class="theme-toggle" onclick="toggleTheme()">🌙</button>
    <h2>Вход в аккаунт</h2>

    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required placeholder="Введите ваш email">
        </div>

        <div class="form-group">
            <label for="password">Пароль</label>
            <input type="password" id="password" name="password" required placeholder="Введите пароль">
        </div>

        <button type="submit" class="btn">Войти</button>

        <p class="signup-link">Нет аккаунта? <a href="register.php">Зарегистрироваться</a></p>
    </form>
</div>

<footer>
    <p>&copy; <?= date("Y") ?> MyShop — Все права защищены.</p>
</footer>

<script>
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