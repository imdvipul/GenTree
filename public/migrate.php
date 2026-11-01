<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

try {
    $schemaFile = __DIR__ . '/../database/schema.sql';
    $docSchemaFile = __DIR__ . '/../database/document_intelligence.sql';

    if (file_exists($schemaFile)) {
        $schema = file_get_contents($schemaFile);
        $pdo->exec($schema);
    }

    if (file_exists($docSchemaFile)) {
        $docSchema = file_get_contents($docSchemaFile);
        $pdo->exec($docSchema);
    }

    // Fetch created tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        "status" => true,
        "message" => "All database tables imported successfully!",
        "database" => $db,
        "tables_count" => count($tables),
        "tables" => $tables
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Migration failed",
        "error" => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
