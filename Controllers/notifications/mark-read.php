<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";

$user = getAuthUser();

$input = json_decode(file_get_contents("php://input"), true);
$id = $input['notification_id'] ?? null;

if (!$id) {
    response(false, "Notification id required");
}

$stmt = $pdo->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE id = ? AND user_id = ?
");

$stmt->execute([$id, $user['id']]);

response(true, "Notification marked as read");
