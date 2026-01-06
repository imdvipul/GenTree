<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";

$user = getAuthUser();

$stmt = $pdo->prepare("
    SELECT id, title, message, type, is_read, reference_token, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");

$stmt->execute([$user['id']]);

$response = $stmt->fetchAll(PDO::FETCH_ASSOC);

response(true, "Notifications fetched", $response);
