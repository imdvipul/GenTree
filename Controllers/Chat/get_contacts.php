<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../config/migrations.php";

ensureChatTables($pdo);
/**
 * Get logged-in user from JWT
 */
$user = getAuthUser();

if (!$user || empty($user['id'])) {
    response(false, "Unauthorized", null, 401);
}

$userId = (int)$user['id'];

/*
|--------------------------------------------------------------------------
| Fetch all users that share family with current user
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT DISTINCT
    u.id,
    u.first_name,
    u.last_name,
    u.avatar,
    f.id AS family_id,
    f.name AS family_name
FROM family_memberships fm1
JOIN family_memberships fm2 
    ON fm1.family_id = fm2.family_id
JOIN users u 
    ON u.id = fm2.user_id
JOIN families f
    ON f.id = fm1.family_id
WHERE fm1.user_id = ?
  AND fm2.user_id != ?
  AND fm1.status = 'active'
  AND fm2.status = 'active'
  AND u.isdelete = 0
  AND f.iddelete = 0
");

$stmt->execute([$userId, $userId]);

$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

response(true, "Chat contacts loaded", $contacts);





// {
//   "status": true,
//   "message": "Chat contacts loaded",
//   "data": [
//     {
//       "id": 5,
//       "first_name": "Raj",
//       "last_name": "Patel",
//       "email": "raj@test.com",
//       "avatar": null
//     },
//     {
//       "id": 8,
//       "first_name": "Kajal",
//       "last_name": "Patel",
//       "email": "kajal@test.com",
//       "avatar": "uploads/avatar.jpg"
//     }
//   ]
// }
