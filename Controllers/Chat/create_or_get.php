<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../config/migrations.php";

ensureChatTables($pdo);

$user = getAuthUser();
$userId = (int)$user['id'];

$data = json_decode(file_get_contents("php://input"), true);
$peerId = (int)($data['peer_id'] ?? 0);

if (!$peerId) response(false, "Peer required");

$stmt = $pdo->prepare("
SELECT c.id FROM chats c
JOIN chat_participants p1 ON c.id=p1.chat_id
JOIN chat_participants p2 ON c.id=p2.chat_id
WHERE c.type='direct' AND p1.user_id=? AND p2.user_id=?
LIMIT 1
");
$stmt->execute([$userId, $peerId]);
$chat = $stmt->fetch();

if ($chat) response(true, "Existing chat", ["chat_id"=>$chat['id']]);

$pdo->beginTransaction();

$pdo->prepare("INSERT INTO chats(type,created_by) VALUES('direct',?)")
    ->execute([$userId]);

$chatId = $pdo->lastInsertId();

$pdo->prepare("
INSERT INTO chat_participants (chat_id, user_id, role)
VALUES (?, ?, ?)
")->execute([$chatId, $userId, 'admin']);

$pdo->prepare("
INSERT INTO chat_participants (chat_id, user_id, role)
VALUES (?, ?, ?)
")->execute([$chatId, $peerId, 'member']);

$pdo->commit();

response(true, "Chat created", ["chat_id"=>$chatId]);
