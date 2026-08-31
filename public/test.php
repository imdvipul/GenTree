<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=family_db", "root", "YOUR_ROOT_PASSWORD");
    echo "DB CONNECTED";
} catch (PDOException $e) {
    echo $e->getMessage();
}
