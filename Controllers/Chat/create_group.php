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

$title = trim($data['title']);
$members = $data['members'] ?? [];

$pdo->beginTransaction();

$stmt = $pdo->prepare("INSERT INTO chats(type,title,created_by) VALUES('group',?,?)");
$stmt->execute([$title, $userId]);
$chatId = $pdo->lastInsertId();

$stmt = $pdo->prepare("INSERT INTO chat_participants VALUES(?,?,?)");
$stmt->execute([$chatId, $userId, 'admin']);

foreach ($members as $uid) {
    $stmt->execute([$chatId, $uid, 'member']);
}

$pdo->commit();

response(true, "Group created", ["chat_id"=>$chatId]);
