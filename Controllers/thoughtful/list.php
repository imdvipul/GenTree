<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();

if (!$user['family_id']) {
    response(false, "User has no family");
}

$stmt = $pdo->prepare("
    SELECT 
        tm.*,
        COUNT(tml.id) AS likes,
        MAX(tml2.id IS NOT NULL) AS is_liked
    FROM thoughtful_messages tm
    LEFT JOIN thoughtful_message_likes tml 
        ON tml.message_id = tm.id
    LEFT JOIN thoughtful_message_likes tml2
        ON tml2.message_id = tm.id AND tml2.member_id = ?
    WHERE tm.isdelete = 0
      AND tm.family_id = ?
      AND (
          tm.is_for_all_members = 1
          OR EXISTS (
              SELECT 1 FROM thoughtful_message_members tmm
              WHERE tmm.message_id = tm.id
              AND tmm.member_id = ?
          )
      )
    GROUP BY tm.id
    ORDER BY tm.created_at DESC
");

$stmt->execute([
    $user['id'],
    $user['family_id'],
    $user['id']
]);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

response(true, "Thoughtful messages fetched", $data);
