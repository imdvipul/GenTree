<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser(); // id, family_id

if (!$user['family_id']) {
    response(false, "User is not associated with any family");
}

$input = json_decode(file_get_contents("php://input"), true);

$message   = trim($input['message'] ?? '');
$image     = $input['image'] ?? null;
$isForAll  = (int)($input['is_for_all_members'] ?? 1);
$memberIds = $input['member_ids'] ?? [];

if (!$message) {
    response(false, "Message is required");
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO thoughtful_messages
        (message, image, family_id, is_for_all_members, created_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $message,
        $image,
        $user['family_id'],
        $isForAll,
        $user['id']
    ]);

    $messageId = $pdo->lastInsertId();

    if ($isForAll === 0 && !empty($memberIds)) {
        $stmt = $pdo->prepare("
            INSERT INTO thoughtful_message_members (message_id, member_id)
            VALUES (?, ?)
        ");
        foreach ($memberIds as $mid) {
            $stmt->execute([$messageId, $mid]);
        }
    }

    $pdo->commit();

    response(true, "Thoughtful message created successfully");

} catch (Exception $e) {
    $pdo->rollBack();
    response(false, "Failed to create message", $e->getMessage());
}
