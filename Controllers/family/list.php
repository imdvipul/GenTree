<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

try {
    $user = getAuthUser();

    if (!$user || !$user['id']) {
        response(false, "Unauthorized", null, 401);
    }

    $sql = "
    SELECT
        f.id,
        f.name,
        f.bio,
        f.cover_image,
        f.created_by,
        f.created_at,

        fms.role AS user_role,

        -- members count (CORRECT)
        (
            SELECT COUNT(DISTINCT uml.member_id)
            FROM user_member_links uml
            JOIN family_members fm2 ON fm2.id = uml.member_id
            WHERE uml.family_id = f.id
              AND fm2.iddelete = 0
        ) AS members_count,

        -- events count
        (
            SELECT COUNT(*)
            FROM life_events le
            JOIN family_members fm3 ON fm3.id = le.member_id
            JOIN user_member_links uml3 ON uml3.member_id = fm3.id
            WHERE uml3.family_id = f.id
        ) AS events_count

    FROM family_memberships fms
    JOIN families f ON f.id = fms.family_id

    WHERE
        fms.user_id = ?
        AND fms.status = 'active'
        AND f.iddelete = 0

    ORDER BY f.created_at DESC
";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id']]);
    $families = $stmt->fetchAll(PDO::FETCH_ASSOC);


    response(true, "Families fetched successfully", $families);

} catch (PDOException $e) {
    response(false, "Failed to fetch families", [
        "error" => $e->getMessage()
    ], 500);
}



// header("Content-Type: application/json");

// require_once __DIR__ . "/../../config/database.php";
// require_once __DIR__ . "/../../helpers/response.php";
// require_once __DIR__ . "/../../helpers/auth_helper.php";

// try {
//     $user = getAuthUser(); // JWT validated

//     /*
//       members_count  → count family_members (not deleted)
//       events_count   → count life_events via family_members
//     */

//     $sql = "
//         SELECT
//             f.id,
//             f.name,
//             f.bio,
//             f.cover_image,
//             f.created_by,
//             f.created_at,

//             -- members count
//             (
//                 SELECT COUNT(*)
//                 FROM family_members fm
//                 WHERE fm.family_id = f.id
//                   AND fm.iddelete = 0
//             ) AS members_count,

//             -- events count
//             (
//                 SELECT COUNT(*)
//                 FROM life_events le
//                 INNER JOIN family_members fm2 ON fm2.id = le.member_id
//                 WHERE fm2.family_id = f.id
//             ) AS events_count

//         FROM families f
//         WHERE f.iddelete = 0
//         ORDER BY f.created_at DESC
//     ";

//     $stmt = $pdo->prepare($sql);
//     $stmt->execute();

//     $families = $stmt->fetchAll(PDO::FETCH_ASSOC);

//     response(true, "Families fetched successfully", $families);

// } catch (PDOException $e) {
//     response(false, "Failed to fetch families", [
//         "error" => $e->getMessage()
//     ], 500);
// }
