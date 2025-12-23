<?php
header("Content-Type: application/json");
require_once "../../config/database.php";
require_once "../../helpers/response.php";

$user = getAuthUser();
$input = json_decode(file_get_contents("php://input"), true);

$messageId = (int)($input['message_id'] ?? 0);

if (!$messageId) {
    response(false, "Message ID required");
}

/* Check already liked */
$stmt = $pdo->prepare("
    SELECT id FROM thoughtful_message_likes
    WHERE message_id = ? AND member_id = ?
");
$stmt->execute([$messageId, $user['id']]);

if ($stmt->fetch()) {
    // 🔻 Unlike
    $pdo->prepare("
        DELETE FROM thoughtful_message_likes
        WHERE message_id = ? AND member_id = ?
    ")->execute([$messageId, $user['id']]);

    response(true, "Like removed");
}

/* 🔺 Like */
$pdo->prepare("
    INSERT INTO thoughtful_message_likes (message_id, member_id)
    VALUES (?, ?)
")->execute([$messageId, $user['id']]);

response(true, "Message liked");
