<?php
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

function logMessage($message) {
    file_put_contents('log.txt', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

if (!isset($_GET['id'])) {
    logMessage('Ошибка: Не указан ID товара.');
    die('Ошибка: Не указан ID товара.');
}

$id = intval($_GET['id']);
$query = "SELECT * FROM products WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    logMessage("Ошибка: Товар с ID $id не найден.");
    die('Ошибка: Товар не найден.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $category_id = intval($_POST['category_id']);
    $image_path = trim($_POST['image_path']);
    $new_quantity = intval($_POST['stock']);

    $prev_quantity = $product['stock'];

    $update_sql = "UPDATE products SET name=?, description=?, price=?, category_id=?, image_path=?, stock=? WHERE id=?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sdidsii", $name, $description, $price, $category_id, $image_path, $new_quantity, $id);
    if ($stmt->execute()) {
        logMessage("Товар с ID $id успешно обновлен.");
    } else {
        logMessage("Ошибка обновления товара с ID $id: " . $stmt->error);
    }

    if ($prev_quantity == 0 && $new_quantity > 0) {
        $users_query = "SELECT Email FROM users";
        $result = $conn->query($users_query);
        while ($row = $result->fetch_assoc()) {
            $email = $row['Email'];
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.yandex.ru';
                $mail->SMTPAuth = true;
                $mail->Username = $from_email;
                $mail->Password = $password;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;
                $mail->setFrom($from_email, 'Магазин');
                $mail->addAddress($email);
                $mail->CharSet = 'UTF-8';
                $mail->isHTML(true);
                $mail->Subject = 'Пополнение товара в магазине!';
                $mail->Body = "<html><body><h2>$name снова в наличии!</h2><p>$description</p><p><strong>Цена:</strong> $price руб.</p><p><a href='$image_path' target='_blank'>Посмотреть товар</a></p></body></html>";
                $mail->send();
                logMessage("Успешно отправлено уведомление на $email.");
            } catch (Exception $e) {
                logMessage("Ошибка отправки email на $email: " . $mail->ErrorInfo);
            }
        }
    }

    header("Location: admin.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать товар</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Редактирование товара</h2>
        <form action="edit_product.php?id=<?= $id ?>" method="POST" class="edit-form">
    <div class="form-group">
        <label for="name">Название:</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
    </div>

    <div class="form-group">
        <label for="description">Описание:</label>
        <textarea id="description" name="description" required><?= htmlspecialchars($product['description']) ?></textarea>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="price">Цена:</label>
            <input type="number" id="price" step="0.01" name="price" value="<?= $product['price'] ?>" required>
        </div>

        <div class="form-group">
            <label for="category_id">Категория:</label>
            <input type="number" id="category_id" name="category_id" value="<?= $product['category_id'] ?>" required>
        </div>
    </div>

    <div class="form-group">
        <label for="image_path">Ссылка на изображение:</label>
        <input type="text" id="image_path" name="image_path" value="<?= htmlspecialchars($product['image_path']) ?>" required>
    </div>

    <div class="form-group">
        <label for="stock">Количество:</label>
        <input type="number" id="stock" name="stock" value="<?= $product['stock'] ?>" required>
    </div>

    <button type="submit" class="save-btn">Сохранить изменения</button>
    </form>
    <a href="admin.php" class="back-btn">Вернуться назад</a>
    </div>
</body>
</html>
