<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();
$input = json_decode(file_get_contents("php://input"), true);

$id      = (int)($input['id'] ?? 0);
$message = trim($input['message'] ?? '');
$image   = $input['image'] ?? null;

if (!$id || !$message) {
    response(false, "Message ID and text required");
}

$stmt = $pdo->prepare("
    UPDATE thoughtful_messages
    SET message = ?, image = ?
    WHERE id = ?
      AND created_by = ?
      AND family_id = ?
      AND isdelete = 0
");

$stmt->execute([
    $message,
    $image,
    $id,
    $user['id'],
    $user['family_id']
]);

if ($stmt->rowCount() === 0) {
    response(false, "Message not found or unauthorized");
}

response(true, "Message updated successfully");
