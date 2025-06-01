<?php
require_once 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Пользователь не авторизован']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$product_id = isset($data['product_id']) ? intval($data['product_id']) : 0;
$user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
$rating = isset($data['rating']) ? intval($data['rating']) : 0;
$comment = isset($data['comment']) ? $data['comment'] : '';

if ($product_id <= 0 || $user_id <= 0 || $rating < 1 || $rating > 5 || empty($comment)) {
    echo json_encode(['error' => 'Неверные данные']);
    exit;
}

// Проверка, покупал ли пользователь этот товар
$sql_orders = "SELECT id FROM orders WHERE user_id = ?";
$stmt_orders = $conn->prepare($sql_orders);
$stmt_orders->bind_param("i", $user_id);
$stmt_orders->execute();
$result_orders = $stmt_orders->get_result();

$has_purchased = false;

while ($order = $result_orders->fetch_assoc()) {
    $order_id = $order['id'];

    $sql_items = "SELECT * FROM order_items WHERE order_id = ? AND product_id = ?";
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param("ii", $order_id, $product_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();

    if ($result_items->num_rows > 0) {
        $has_purchased = true;
        break;
    }
}

if (!$has_purchased) {
    echo json_encode(['error' => 'Вы не можете оставить отзыв, так как не покупали этот товар']);
    exit;
}

// Добавление отзыва
$sql = "INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiis", $user_id, $product_id, $rating, $comment);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Ошибка при добавлении отзыва']);
}

$stmt->close();
$conn->close();
?>