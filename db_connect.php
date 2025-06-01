<?php
$conn = mysqli_connect("localhost", "root", "", "db_web", 3306);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
