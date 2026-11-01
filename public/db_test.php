<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        "status" => true,
        "message" => "Database connected successfully!",
        "database" => $db,
        "tables_count" => count($tables),
        "tables" => $tables
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Database check failed",
        "error" => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
