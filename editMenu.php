<?php
require_once 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: login.php');
    exit;
}

// Получаем все товары
$query = "SELECT p.id, p.name, p.price, p.stock, c.Name AS category 
          FROM products p
          JOIN category c ON p.category_id = c.id
          ORDER BY p.name ASC";
$products = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ панель — Товары</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter :wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css "/>
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

        .container {
            max-width: 1200px;
            margin: auto;
            padding: 2rem;
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

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1.5rem;
            justify-content: space-between;
            align-items: center;
        }

        .search-filter {
            flex: 1;
            min-width: 250px;
        }

        .search-filter input[type="text"] {
            width: 100%;
            padding: 0.6rem 1rem;
            border: 1px solid #ccc;
            border-radius: var(--border-radius);
            font-size: 1rem;
        }

        .btn {
            padding: 0.6rem 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #0056b3;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5rem;
            background: white;
            box-shadow: var(--shadow);
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        th, td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        thead {
            background: linear-gradient(to right, var(--primary), #0056b3);
            color: white;
        }

        tbody tr:hover {
            background: #f1f1f1;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .edit-btn, .delete-btn {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            text-align: center;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .edit-btn {
            background: var(--primary);
            color: white;
        }

        .delete-btn {
            background: var(--danger);
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 400px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }

        .modal-content h3 {
            margin-bottom: 1rem;
        }

        .close-btn {
            float: right;
            font-size: 1.2rem;
            cursor: pointer;
            font-weight: bold;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            color: white;
            font-size: 1rem;
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
    <h1>MyShop — Админ панель</h1>
    <nav class="toolbar">
        <div class="search-filter">
            <input type="text" id="searchInput" placeholder="Поиск по названию..." onkeyup="filterProducts()">
        </div>
        <a href="add_product.php" class="btn">+ Добавить товар</a>
        <a href="admin.php" class="btn">В меню</a>
    </nav>
</header>

<div class="container">
    <table id="productsTable">
        <thead>
            <tr>
                <th>Название</th>
                <th>Цена</th>
                <th>На складе</th>
                <th>Категория</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($product = $products->fetch_assoc()): ?>
                <tr id="row-<?= $product['id'] ?>">
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td contenteditable="true" onblur="updateField(this, <?= $product['id'] ?>, 'price')">
                        <?= number_format($product['price'], 0, '', ' ') ?>
                    </td>
                    <td contenteditable="true" onblur="updateField(this, <?= $product['id'] ?>, 'stock')">
                        <?= $product['stock'] ?>
                    </td>
                    <td><?= htmlspecialchars($product['category']) ?></td>
                    <td class="actions">
                        <a href="edit_product.php?id=<?= $product['id'] ?>" class="edit-btn">✏️ Редактировать</a>
                        <button onclick="openDeleteModal(<?= $product['id'] ?>)" class="delete-btn">🗑 Удалить</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Модальное окно для подтверждения удаления -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <span class="close-btn" onclick="document.getElementById('deleteModal').style.display='none'">&times;</span>
        <h3>Вы уверены?</h3>
        <p>Это действие нельзя отменить.</p>
        <div style="display: flex; justify-content: space-between;">
            <button onclick="confirmDelete()" class="btn" style="background: var(--danger);">Удалить</button>
            <button onclick="document.getElementById('deleteModal').style.display='none'" class="btn">Отмена</button>
        </div>
    </div>
</div>

<footer class="footer">
    <p>&copy; <?= date("Y") ?> MyShop — Все права защищены.</p>
</footer>

<script>
let deleteProductId = null;

function openDeleteModal(productId) {
    deleteProductId = productId;
    document.getElementById('deleteModal').style.display = 'flex';
}

function confirmDelete() {
    if (!deleteProductId) return;

    fetch('delete_product.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'product_id=' + deleteProductId
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('row-' + deleteProductId).remove();
            showNotification('Товар удален', 'success');
        } else {
            showNotification('Ошибка при удалении', 'error');
        }
        document.getElementById('deleteModal').style.display = 'none';
    });
}

function updateField(element, productId, field) {
    const value = element.textContent.trim().replace(/\D+/g, '');
    fetch('update_product_field.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `product_id=${productId}&field=${field}&value=${value}`
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            showNotification('Ошибка при обновлении поля', 'error');
        }
    });
}

function filterProducts() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const rows = document.querySelectorAll("#productsTable tbody tr");

    rows.forEach(row => {
        const name = row.cells[0].textContent.toLowerCase();
        row.style.display = name.includes(filter) ? '' : 'none';
    });
}

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