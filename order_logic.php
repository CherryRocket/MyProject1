<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-6.9.3/src/Exception.php';
require 'PHPMailer-6.9.3/src/PHPMailer.php';
require 'PHPMailer-6.9.3/src/SMTP.php';

require_once 'db_connect.php';
session_start();

header('Content-Type: application/json');

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
    exit;
}

// Получаем данные из запроса
$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Ошибка в формате JSON: ' . json_last_error_msg()]);
    exit;
}

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Вы не авторизованы']);
    exit;
}

$user_id = $_SESSION['user_id'];
$delivery_type = $data['delivery_type'];
$delivery_address = $data['deliveryadress'] ?? null;
$total_price = 0;
$order_date = date('Y-m-d H:i:s');
$payment_status = "success";

// Получаем данные из корзины
$sql_cart = "SELECT cart.id AS cart_id, cart.product_id, cart.quantity, products.name, products.price, products.stock 
             FROM cart 
             JOIN products ON cart.product_id = products.id 
             WHERE cart.user_id = ?";
$stmt_cart = $conn->prepare($sql_cart);
if (!$stmt_cart) {
    echo json_encode(['success' => false, 'message' => 'Ошибка подготовки запроса корзины: ' . $conn->error]);
    exit;
}
$stmt_cart->bind_param("i", $user_id);
$stmt_cart->execute();
$cart_result = $stmt_cart->get_result();

$cart_items = [];
while ($item = $cart_result->fetch_assoc()) {
    if ($item['stock'] < $item['quantity']) {
        echo json_encode(['success' => false, 'message' => "Недостаточно товара {$item['name']} на складе"]);
        exit;
    }
    $cart_items[] = $item;
    $total_price += $item['price'] * $item['quantity'];
}

if (empty($cart_items)) {
    echo json_encode(['success' => false, 'message' => 'Корзина пуста']);
    exit;
}

// Сохраняем заказ в базу данных
$sql_order = "INSERT INTO orders (user_id, total_price, delivery_type, delivery_address, payment_status, order_date) 
              VALUES (?, ?, ?, ?, ?, ?)";
$stmt_order = $conn->prepare($sql_order);
if (!$stmt_order) {
    echo json_encode(['success' => false, 'message' => 'Ошибка подготовки запроса заказа: ' . $conn->error]);
    exit;
}
$stmt_order->bind_param("idssss", $user_id, $total_price, $delivery_type, $delivery_address, $payment_status, $order_date);
$stmt_order->execute();

if ($stmt_order->affected_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Ошибка оформления заказа']);
    exit;
}

// Получаем ID заказа
// Получаем ID заказа
$order_id = $stmt_order->insert_id;

// Сохраняем товары заказа в таблицу orders_items
$stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)");
if (!$stmt_item) {
    echo json_encode(['success' => false, 'message' => 'Ошибка подготовки запроса на сохранение товаров заказа: ' . $conn->error]);
    exit;
}
foreach ($cart_items as $item) {
    $stmt_item->bind_param("iii", $order_id, $item['product_id'], $item['quantity']);
    if (!$stmt_item->execute()) {
        echo json_encode(['success' => false, 'message' => 'Ошибка при добавлении товара в заказ: ' . $stmt_item->error]);
        exit;
    }
}

// Получаем email пользователя
$sql_user = "SELECT email FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
if (!$stmt_user) {
    echo json_encode(['success' => false, 'message' => 'Ошибка подготовки запроса для получения email пользователя: ' . $conn->error]);
    exit;
}
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user_data = $result_user->fetch_assoc();

if (!$user_data) {
    echo json_encode(['success' => false, 'message' => 'Ошибка получения email пользователя']);
    exit;
}

$user_email = $user_data['email'];

// Формируем текст письма с чеком
$order_details = "";
foreach ($cart_items as $item) {
    $order_details .= "{$item['name']} x {$item['quantity']} = " . ($item['price'] * $item['quantity']) . " руб.\n";
}

$email_body = "
Здравствуйте! Ваш заказ успешно оформлен.

Номер заказа: #$order_id
Дата заказа: $order_date
Способ доставки: " . ($delivery_type === 'self_pickup' ? "Самовывоз" : "Доставка") . "
Адрес доставки: " . ($delivery_address ?: "Не указан") . "

Список товаров:
$order_details

Итого: $total_price руб.

Спасибо за покупку!
";

// Отправляем письмо пользователю
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->SMTPDebug = 0; 
    $mail->Host = 'smtp.yandex.ru';
    $mail->SMTPAuth = true;
    $mail->Username = 'koroleffilyas@yandex.ru';
    $mail->Password = 'velomfifnssyerbb';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom('koroleffilyas@yandex.ru', 'Mаgaz');
    $mail->addAddress($user_email);
    $mail->Subject = 'Ваш заказ успешно оформлен';
    $mail->Body = $email_body;

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Заказ оформлен и чек отправлен на email']);

    // Очищаем корзину после успешного оформления заказа
    $sql_clear_cart = "DELETE FROM cart WHERE user_id = ?";
    $stmt_clear_cart = $conn->prepare($sql_clear_cart);
    $stmt_clear_cart->bind_param("i", $user_id);
    $stmt_clear_cart->execute();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка при отправке письма: ' . $mail->ErrorInfo]);
}

// Закрываем соединения
$stmt_cart->close();
$stmt_order->close();
$stmt_user->close();
$stmt_clear_cart->close();
$conn->close();

?>
