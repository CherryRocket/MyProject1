<?php
// naklad.php — Админ-панель для добавления накладных

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
$password = 'velomfifnssyerbb';

function sendEmailToUsers($subject, $message, $conn, $from_email, $password) {
    $users_query = "SELECT email FROM users WHERE is_confirmed = 1";
    $result = $conn->query($users_query);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $email = $row['email'];
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
                $mail->addAddress($email);
                $mail->CharSet = 'UTF-8';
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $message;
                $mail->AltBody = strip_tags($message);
                $mail->send();
            } catch (Exception $e) {
                error_log("Ошибка отправки email: {$mail->ErrorInfo}");
            }
        }
    }
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_invoice'])) {
    $invoice_number = "INV-" . rand(1000, 9999);
    $supplier = trim($_POST['supplier']);
    $date = date('Y-m-d H:i:s');

    // Обработка загрузки файла
    if (!empty($_FILES['document']['name'])) {
        $target_dir = "uploads/";
        $file_name = basename($_FILES["document"]["name"]);
        $document_path = $target_dir . $invoice_number . '-' . $file_name;

        if (!move_uploaded_file($_FILES["document"]["tmp_name"], $document_path)) {
            echo json_encode(['success' => false, 'message' => 'Ошибка при загрузке файла']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Файл не загружен']);
        exit;
    }

    // Вставка накладной в БД
    $stmt = $conn->prepare("INSERT INTO invoices (invoice_number, supplier, date, document_path) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Ошибка подготовки запроса']);
        exit;
    }

    $stmt->bind_param("ssss", $invoice_number, $supplier, $date, $document_path);
    if ($stmt->execute()) {
        // Формируем сообщение
        $full_message = "
            <h2>Новая накладная оприходована</h2>
            <p><strong>Номер:</strong> $invoice_number</p>
            <p><strong>Поставщик:</strong> $supplier</p>
            <p><strong>Дата:</strong> $date</p>
            <p><a href='https://вашдомен/$document_path'>📄 Скачать документ</a></p>
        ";

        // Отправляем всем пользователям
        sendEmailToUsers('Оприходование накладной', $full_message, $conn, $from_email, $password);

        echo json_encode(['success' => true, 'message' => 'Накладная успешно добавлена']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка при сохранении накладной']);
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | Накладные</title>
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
            color: var(--primary);
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

        nav a:hover {
            color: var(--dark);
        }

        .admin-container {
            max-width: 1000px;
            margin: 2rem auto;
            background: white;
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
        input[type="file"] {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid #ccc;
            border-radius: var(--border-radius);
            font-size: 1rem;
        }

        input[type="file"] {
            padding: 0.4rem;
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2rem;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        th, td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        thead {
            background: #fafafa;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
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

        footer {
            text-align: center;
            padding: 2rem 1rem;
            background: #222;
            color: white;
            margin-top: 3rem;
        }
    </style>
</head>
<body data-theme="light">

<div class="notification success" id="notification"></div>

<header>
    <h1>MyShop — Накладные</h1>
    <nav>
        <ul class="nav-links">
            <li><a href="index.php">Главная</a></li>
            <li><a href="admin.php">Добавить товар</a></li>
            <li><a href="naklad.php">Накладные</a></li>
            <li><a href="editMenu.php">Редактировать меню</a></li>
            <li><a href="logout.php">Выйти</a></li>
        </ul>
    </nav>
</header>

<div class="admin-container">
    <h2>Добавление накладной</h2>
    <form id="invoiceForm" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="supplier">Поставщик:</label>
            <input type="text" id="supplier" name="supplier" required placeholder="Название поставщика">
        </div>
        <div class="form-group">
            <label for="document">Загрузите PDF-накладную:</label>
            <input type="file" id="document" name="document" accept=".pdf" required>
        </div>
        <button type="submit" name="add_invoice" class="btn">Оприходовать накладную</button>
    </form>

    <h2 style="margin-top: 3rem;">История накладных</h2>
    <table>
        <thead>
            <tr>
                <th>Номер</th>
                <th>Поставщик</th>
                <th>Дата</th>
                <th>Документ</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "SELECT * FROM invoices ORDER BY date DESC";
            $result = $conn->query($sql);
            if ($result && $result->num_rows > 0):
                while ($invoice = $result->fetch_assoc()):
            ?>
                    <tr>
                        <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                        <td><?= htmlspecialchars($invoice['supplier']) ?></td>
                        <td><?= htmlspecialchars($invoice['date']) ?></td>
                        <td><a href="<?= htmlspecialchars($invoice['document_path']) ?>" target="_blank">📄 Скачать</a></td>
                    </tr>
            <?php
                endwhile;
            else:
            ?>
                <tr>
                    <td colspan="4" style="text-align:center; color:#888;">Нет записей</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<footer>
    <p>&copy; <?= date("Y") ?> MyShop — Все права защищены.</p>
</footer>

<script>
document.getElementById('invoiceForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch('naklad.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message || 'Произошла ошибка', 'error');
        }
    })
    .catch(err => {
        console.error('Ошибка сети:', err);
        showNotification('Ошибка при отправке данных', 'error');
    });
});

function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = 'notification ' + type + ' show'; // заменяем класс
    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}
</script>

</body>
</html>