<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/schema_helper.php";

$suvicharResult = ensureDailySuvicharSchema($pdo, true);
$smartDocResult = ensureSmartDocumentsSchema($pdo, true);

$suvicharCols = $pdo->query("SHOW COLUMNS FROM daily_suvichars")->fetchAll(PDO::FETCH_ASSOC);
$smartDocTables = $pdo->query("SHOW TABLES LIKE '%document%'")->fetchAll(PDO::FETCH_COLUMN);

echo json_encode([
    'status' => true,
    'message' => 'Schema migrations executed successfully on Aiven MySQL',
    'suvichar' => [
        'result' => $suvicharResult,
        'columns' => array_column($suvicharCols, 'Field')
    ],
    'smart_documents' => [
        'result' => $smartDocResult,
        'tables' => $smartDocTables
    ]
], JSON_PRETTY_PRINT);
exit;
