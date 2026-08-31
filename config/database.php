<?php
$host = getenv('DB_HOST') ?: "127.0.0.1";
$db   = getenv('DB_DATABASE') ?: "family_db";
$user = getenv('DB_USERNAME') ?: "root";
$pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status"  => false,
        "message" => "Database connection failed",
        "error"   => $e->getMessage()
    ]);
    exit;
}
