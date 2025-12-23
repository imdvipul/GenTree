<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();

$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    response(false, "Family ID is required", null, 400);
}

/**
 * Only creator can delete family
 */
$stmt = $pdo->prepare("
    UPDATE families
    SET iddelete = 1
    WHERE id = ? AND created_by = ?
");

$stmt->execute([$id, $user['id']]);

if ($stmt->rowCount() === 0) {
    response(false, "Family not found or unauthorized", null, 403);
}

response(true, "Family deleted successfully");
