<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/fcm_helper.php";

$user = getAuthUser();
if (!$user || !isset($user['id'])) {
    response(false, "Unauthorized", null, 401);
}

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    response(false, "Invalid payload", null, 400);
}

$chatId       = trim($input['chat_id'] ?? $input['chatId'] ?? '');
$recipientId  = (int)($input['recipient_id'] ?? $input['recipientId'] ?? 0);
$senderName   = trim($input['sender_name'] ?? $input['senderName'] ?? '');
$messageText  = trim($input['message'] ?? $input['text'] ?? '');

if (empty($chatId) || !$recipientId || empty($messageText)) {
    response(false, "chat_id, recipient_id, and message are required", null, 400);
}

if (empty($senderName)) {
    $senderName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    if (empty($senderName)) $senderName = "Family Member";
}

// Ensure the sender cannot send push to themselves
if ($recipientId === (int)$user['id']) {
    response(true, "Self message ignored");
}

$dataPayload = [
    'type'        => 'chat',
    'chatId'      => $chatId,
    'chat_id'     => $chatId,
    'sender_id'   => (string)$user['id'],
    'sender_name' => $senderName,
    'otherName'   => $senderName,
    'other_name'  => $senderName,
    'body'        => $messageText,
];

$res = sendFcmNotification([$recipientId], $senderName, $messageText, $dataPayload);

response(true, "Chat notification dispatched", $res);
