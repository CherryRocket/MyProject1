<?php
// cart_add.php

// Подключение к базе данных
require_once 'db_connect.php';
session_start();

header('Content-Type: application/json');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Неавторизован']));
}

// Получаем user_id из сессии
$user_id = $_SESSION['user_id'];

// Проверяем роль пользователя
$stmt = $conn->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    exit(json_encode(['success' => false, 'message' => 'Пользователь не найден']));
}

$user = $result->fetch_assoc();
if ($user['role_id'] == 1) { // Предположим, что role_id=1 — это админ
    http_response_code(403); // Forbidden
    exit(json_encode(['success' => false, 'message' => 'Доступ запрещён. Администратор не может добавлять товары в корзину.']));
}

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    exit(json_encode(['success' => false, 'message' => 'Метод не поддерживается']));
}

// Получаем данные из тела запроса
$data = json_decode(file_get_contents('php://input'), true);

// Массив для хранения ошибок
$errors = [];

// Проверяем наличие обязательных параметров
foreach(['product_id', 'quantity'] as $field) {
    if (!isset($data[$field])) {
        $errors[] = $field;
    }
}

// Если есть ошибки, возвращаем сообщение
if (!empty($errors)) {
    http_response_code(400); // Bad Request
    exit(json_encode(['success' => false, 'message' => 'Недостаточно данных: ' . implode(', ', $errors)]));
}

$product_id = intval($data['product_id']);
$quantity = max(1, intval($data['quantity'])); // минимум 1

try {
    global $conn;

    // Проверяем, существует ли товар
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product_result = $stmt->get_result();

    if ($product_result->num_rows === 0) {
        http_response_code(404); // Товар не найден
        exit(json_encode(['success' => false, 'message' => 'Товар не найден']));
    }

    // Проверяем, есть ли товар в корзине
    $stmt = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $cart_result = $stmt->get_result();

    if ($cart_result->num_rows > 0) {
        // Увеличиваем количество
        $item = $cart_result->fetch_assoc();
        $new_quantity = $item['quantity'] + $quantity;

        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("iii", $new_quantity, $user_id, $product_id);
        $result = $stmt->execute();

        if ($result) {
            exit(json_encode(['success' => true, 'message' => 'Количество товара увеличено']));
        } else {
            throw new Exception('Не удалось обновить количество товара');
        }
    } else {
        // Добавляем товар в корзину
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $user_id, $product_id, $quantity);
        $result = $stmt->execute();

        if ($result) {
            exit(json_encode(['success' => true, 'message' => 'Товар добавлен в корзину']));
        } else {
            throw new Exception('Не удалось добавить товар в корзину');
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => $e->getMessage()]));
}

mysqli_close($conn);
?>