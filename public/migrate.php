<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

try {
    $backupFile = __DIR__ . '/../database/backup_family_db.sql';
    $schemaFile = __DIR__ . '/../database/schema.sql';
    $docSchemaFile = __DIR__ . '/../database/document_intelligence.sql';

    if (file_exists($backupFile)) {
        $sql = file_get_contents($backupFile);
        $pdo->exec($sql);
    } else {
        if (file_exists($schemaFile)) {
            $pdo->exec(file_get_contents($schemaFile));
        }
        if (file_exists($docSchemaFile)) {
            $pdo->exec(file_get_contents($docSchemaFile));
        }
    }

    // Refresh PDO connection to clear multi-statement buffer
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Fetch created tables and their exact row counts
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $tableSummary = [];
    $totalRows = 0;
    foreach ($tables as $tName) {
        $cntStmt = $pdo->query("SELECT COUNT(*) FROM `$tName`");
        $rowCount = (int)$cntStmt->fetchColumn();
        $tableSummary[$tName] = $rowCount;
        $totalRows += $rowCount;
    }

    echo json_encode([
        "status" => true,
        "message" => "All local tables and data were successfully uploaded to the server database!",
        "database" => $db,
        "tables_count" => count($tables),
        "total_records_inserted" => $totalRows,
        "table_records" => $tableSummary
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Migration failed",
        "error" => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
