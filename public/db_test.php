<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=family_db", "root", "root");
    echo "DB CONNECTED";
} catch (PDOException $e) {
    echo $e->getMessage();
}


// require_once __DIR__ . "/../config/database.php";
// echo "DB CONNECTED";
