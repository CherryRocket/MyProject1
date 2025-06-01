<?php
session_start();

if (!isset($_SESSION['verifying_user_id'])) {
    header("Location: profile.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    require_once 'db_connect.php';

    $stmt = $conn->prepare("UPDATE users SET password = ?, verification_code = NULL WHERE id = ?");
    $stmt->bind_param("si", $new_password, $_SESSION['verifying_user_id']);
    
    if ($stmt->execute()) {
        unset($_SESSION['verifying_user_id']);
        echo "<script>alert('Пароль успешно изменён'); window.location.href='profile.php';</script>";
    } else {
        echo "<script>alert('Ошибка при смене пароля');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Сменить пароль</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css "/>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fa;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        h2 {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.8rem;
        }
        input[type="password"] {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        .btn {
            width: 100%;
            padding: 0.6rem;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Новый пароль</h2>
        <form method="post">
            <label>
                Введите новый пароль:
                <input type="password" name="password" minlength="6" required>
            </label>
            <button type="submit" class="btn">Сохранить</button>
        </form>
    </div>
</body>
</html>