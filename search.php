<?php
require_once 'db_connect.php'; // Подключение к базе

if (isset($_GET['query'])) {
    $query = mysqli_real_escape_string($conn, $_GET['query']);

    // Запрос к базе данных: ищем по названию
    $sql = "SELECT * FROM products WHERE name LIKE '%$query%'";
    $result = mysqli_query($conn, $sql);
} else {
    $result = false;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Результаты поиска</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>Результаты поиска</h1>
    <a href="index.php">Назад</a>
</header>

<div class="container">
    <?php
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<div class='product'>";
            echo "<h2>" . htmlspecialchars($row['name']) . "</h2>";
            echo "<img src='" . htmlspecialchars($row['image_path']) . "' alt='" . htmlspecialchars($row['name']) . "'>";
            echo "<p>" . htmlspecialchars($row['description']) . "</p>";
            echo "<p class='price'>Цена: " . htmlspecialchars($row['price']) . " руб</p>";
            echo "<a href='product.php?id=" . htmlspecialchars($row['id']) . "'>Подробнее</a>";
            echo "</div>";
        }
    } else {
        echo "<p>Товары не найдены</p>";
    }
    ?>
</div>

<footer>
    <p>&copy; 2023 Мой магазин. Все права защищены.</p>
</footer>

<?php mysqli_close($conn); ?>
</body>
</html>
