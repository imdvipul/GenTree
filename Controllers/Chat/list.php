<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../config/migrations.php";

ensureChatTables($pdo);
$user = getAuthUser();
$userId = (int)$user['id'];

$stmt = $pdo->prepare("
SELECT c.id as chat_id, c.type, c.title,
       u.id as peer_id,
       CONCAT(u.first_name,' ',u.last_name) as peer_name,
       u.avatar
FROM chats c
JOIN chat_participants cp1 ON c.id=cp1.chat_id
JOIN chat_participants cp2 ON c.id=cp2.chat_id
LEFT JOIN users u ON u.id=cp2.user_id AND c.type='direct'
WHERE cp1.user_id=?
  AND (cp2.user_id!=? OR c.type='group')
");
$stmt->execute([$userId, $userId]);

response(true, "Chat list", $stmt->fetchAll());
