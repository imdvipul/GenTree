<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";

$user = getAuthUser();

if (!$user || empty($user['id'])) {
    response(false, "Unauthorized", null, 401);
}

$userId = (int)$user['id'];

$sql = "
SELECT DISTINCT
    fm.id,
    CONCAT(TRIM(fm.first_name), ' ', TRIM(fm.last_name)) AS full_name,
    fm.gender
FROM family_members fm

JOIN user_member_links uml
    ON uml.member_id = fm.id

JOIN family_memberships fms
    ON fms.family_id = uml.family_id
   AND fms.user_id = :user_id

WHERE
    fm.iddelete = 0
    AND fms.status = 'active'

ORDER BY fm.first_name ASC
";


$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':user_id' => $userId
]);

$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

response(true, "Members fetched successfully", $members);


