<?php
// order_process.php — Обработка оформления заказа с уменьшением stock и отправкой письма

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Метод не разрешён']));
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Неавторизован']));
}

$user_id = $_SESSION['user_id'];

// Получаем данные из запроса
$data = json_decode(file_get_contents('php://input'), true);
$delivery_type = $data['delivery_type'] ?? 'self_pickup';
$delivery_address = $delivery_type === 'delivery'
    ? ($data['delivery_address'] ?? '')
    : 'Самовывоз';

function sendEmailToClient($email, $subject, $message) {
    require 'PHPMailer-6.9.3/src/Exception.php';
    require 'PHPMailer-6.9.3/src/PHPMailer.php';
    require 'PHPMailer-6.9.3/src/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.yandex.ru';
        $mail->SMTPAuth = true;
        $mail->Username = 'koroleffilyas@yandex.ru';
        $mail->Password = 'velomfifnssyerbb';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom('koroleffilyas@yandex.ru', 'MyShop');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);

        return $mail->send();
    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log('Mail error: ' . $e->getMessage());
        return false;
    }
}

try {
    // Начинаем транзакцию
    $conn->begin_transaction();

    // Получаем товары из корзины пользователя с проверкой наличия stock и блокировкой записей FOR UPDATE
    $stmt_cart = $conn->prepare("
        SELECT p.id AS product_id, p.price, p.name AS product_name, p.stock, c.quantity 
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
        FOR UPDATE
    ");
    $stmt_cart->bind_param("i", $user_id);
    $stmt_cart->execute();
    $result = $stmt_cart->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Корзина пуста");
    }

    $total_price = 0;
    $items = [];

    while ($row = $result->fetch_assoc()) {
        if ($row['stock'] < $row['quantity']) {
            throw new Exception("Товара '{$row['product_name']}' недостаточно на складе. В наличии: {$row['stock']} шт.");
        }

        $total_price += $row['price'] * $row['quantity'];
        $items[] = $row; // Сохраняем для последующего добавления в заказ
    }

    // Создаём заказ
    $stmt_order = $conn->prepare("
        INSERT INTO orders (user_id, total_price, delivery_type, delivery_address)
        VALUES (?, ?, ?, ?)
    ");
    $stmt_order->bind_param("idss", $user_id, $total_price, $delivery_type, $delivery_address);
    if (!$stmt_order->execute()) {
        throw new Exception("Ошибка создания заказа: " . $stmt_order->error);
    }
    $order_id = $conn->insert_id;

    // Добавляем товары в order_items и уменьшаем stock
    $stmt_item = $conn->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, price)
        VALUES (?, ?, ?, ?)
    ");
    $stmt_update_stock = $conn->prepare("
        UPDATE products SET stock = stock - ? WHERE id = ?
    ");

    foreach ($items as $item) {
        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        $price = $item['price'];

        if (!$stmt_item->bind_param("iiid", $order_id, $product_id, $quantity, $price)) {
            throw new Exception("Ошибка привязки параметров для order_items: " . $stmt_item->error);
        }
        if (!$stmt_item->execute()) {
            throw new Exception("Ошибка добавления товара в заказ: " . $stmt_item->error);
        }

        if (!$stmt_update_stock->bind_param("ii", $quantity, $product_id)) {
            throw new Exception("Ошибка привязки параметров для обновления stock: " . $stmt_update_stock->error);
        }
        if (!$stmt_update_stock->execute()) {
            throw new Exception("Ошибка обновления stock товара с ID {$product_id}: " . $stmt_update_stock->error);
        }
    }

    // Очищаем корзину пользователя
    $stmt_clear = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt_clear->bind_param("i", $user_id);
    if (!$stmt_clear->execute()) {
        throw new Exception("Ошибка очистки корзины: " . $stmt_clear->error);
    }

    // Получаем email пользователя
    $stmt_user = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    $user_email = '';
    if ($row = $result_user->fetch_assoc()) {
        $user_email = $row['email'];
    }
    $stmt_user->close();

    // Формируем чек
    $receipt_html = "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto;'>
        <h2 style='color: #007BFF;'>MyShop — Ваш чек №{$order_id}</h2>
        <p>Спасибо за заказ! Вот подробности вашего заказа:</p>
        <ul>";

    foreach ($items as $item) {
        $name = htmlspecialchars($item['product_name']);
        $qty = (int)$item['quantity'];
        $price_fmt = number_format($item['price'], 2, ',', ' ');
        $sum_fmt = number_format($item['price'] * $qty, 2, ',', ' ');
        $receipt_html .= "<li>{$name} — {$qty} шт. по {$price_fmt} ₽ = {$sum_fmt} ₽</li>";
    }

    $total_price_formatted = number_format($total_price, 2, ',', ' ');
    $receipt_html .= "</ul>
        <p><b>Итого: {$total_price_formatted} ₽</b></p>
        <p>Тип доставки: " . ($delivery_type === 'delivery' ? htmlspecialchars($delivery_address) : 'Самовывоз') . "</p>
        <p>Если у вас возникнут вопросы, свяжитесь с нами.</p>
        <p>С уважением, команда MyShop.</p>
    </div>";

    // Отправляем письмо покупателю
    if ($user_email && sendEmailToClient($user_email, "Ваш заказ №{$order_id} в MyShop", $receipt_html)) {
        // Письмо отправлено успешно
    } else {
        error_log("Не удалось отправить письмо на {$user_email} для заказа {$order_id}");
    }

    // Фиксируем транзакцию
    $conn->commit();

    // Возвращаем успешный ответ
    echo json_encode([
        'success' => true,
        'order_id' => $order_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
