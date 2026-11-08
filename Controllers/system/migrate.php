<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/schema_helper.php";

$result = ensureDailySuvicharSchema($pdo, true);

$cols = $pdo->query("SHOW COLUMNS FROM daily_suvichars")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'status' => true,
    'message' => 'Schema migration executed',
    'result' => $result,
    'columns' => array_column($cols, 'Field')
], JSON_PRETTY_PRINT);
exit;
