<?php
// delete_product.php — Удаление товара из корзины

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php'; // Подключение к БД
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    exit(json_encode(['success' => false, 'message' => 'Метод не разрешён']));
}

// Получаем product_id из формы-urlencoded
if (!isset($_POST['product_id'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Не указан ID товара']));
}

$product_id = intval($_POST['product_id']); // Приводим к числу для безопасности

try {
    // Удаляем товар из корзины
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);

    if (!$stmt->execute()) {
        throw new Exception("Ошибка при удалении товара: " . $stmt->error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Товар успешно удален из корзины'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();