<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";

$user = getAuthUser();
$senderId = $user['id'];

$data = json_decode(file_get_contents("php://input"), true);
$chatId = $data['chat_id'];
$text = $data['text'];

// Find receivers
$stmt = $pdo->prepare("
SELECT u.fcm_token
FROM chat_participants cp
JOIN users u ON u.id = cp.user_id
WHERE cp.chat_id = ?
  AND cp.user_id != ?
  AND u.fcm_token IS NOT NULL
");
$stmt->execute([$chatId, $senderId]);
$tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tokens as $token) {
    sendFCM($token, "New message", $text);
}

response(true, "Push sent");

function sendFCM($token, $title, $body) {
    $serverKey = "BOa39hpfQg1hAU2zgV5IZ_yqOiVny562SIIzMYx3H2-Q1SBrALfD7mUGyHHv1rlKrVCgXtHCSj1XS_ASN-daeEg";

    $payload = [
        "to" => $token,
        "notification" => [
            "title" => $title,
            "body" => $body,
        ],
        "data" => [
            "click_action" => "FLUTTER_NOTIFICATION_CLICK",
        ]
    ];

    $ch = curl_init("https://fcm.googleapis.com/fcm/send");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: key=$serverKey",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_exec($ch);
    curl_close($ch);
}
