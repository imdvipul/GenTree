<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";

$user = getAuthUser();
if (!$user || !isset($user['id'])) {
    response(false, "Unauthorized", null, 401);
}

$input = json_decode(file_get_contents("php://input"), true);
$id = $input['notification_id'] ?? null;
$markAll = !empty($input['mark_all']);
$isDelete = !empty($input['delete']);

if ($isDelete) {
    if ($markAll) {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        response(true, "All notifications cleared");
    } else if ($id) {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user['id']]);
        response(true, "Notification deleted");
    } else {
        response(false, "Notification id required for deletion");
    }
}

if ($markAll) {
    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user['id']]);
    response(true, "All notifications marked as read");
}

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
