<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();
if (!$user || !isset($user['id'])) {
    response(false, "Unauthorized", null, 401);
}

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    response(false, "Invalid JSON payload", null, 400);
}

$familyId = (int)($input['family_id'] ?? 0);
$first    = trim($input['first_name'] ?? '');
$gender   = $input['gender'] ?? null;

if (!$familyId || !$first) {
    response(false, "family_id and first_name required", null, 400);
}

/* 🔐 Permission check */
$stmt = $pdo->prepare("
    SELECT role FROM family_memberships
    WHERE family_id = ? AND user_id = ?
");
$stmt->execute([$familyId, $user['id']]);
$role = $stmt->fetchColumn();

if (!in_array($role, ['owner', 'admin'])) {
    response(false, "Not allowed", null, 403);
}

$pdo->beginTransaction();

try {
    /* ---------- 1️⃣ CREATE GLOBAL MEMBER ---------- */
    $stmt = $pdo->prepare("
        INSERT INTO family_members
        (first_name, last_name, nickname, gender, birth_date, bio, user_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $first,
        $input['last_name'] ?? null,
        $input['nickname'] ?? null,
        $gender,
        $input['birth_date'] ?? null,
        $input['bio'] ?? null,
        $user['id']
    ]);

    $memberId = (int)$pdo->lastInsertId();

    /* ---------- 2️⃣ LINK MEMBER TO FAMILY ---------- */
    $stmt = $pdo->prepare("
        INSERT INTO user_member_links (user_id, family_id, member_id)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user['id'], $familyId, $memberId]);

    /* ---------- 3️⃣ RELATION HELPERS ---------- */
    function memberInFamily($pdo, $familyId, $memberId) {
        $stmt = $pdo->prepare("
            SELECT 1 FROM user_member_links
            WHERE family_id = ? AND member_id = ?
        ");
        $stmt->execute([$familyId, $memberId]);
        return (bool)$stmt->fetchColumn();
    }

    function addRelationSafe($pdo, $familyId, $from, $to, $type) {
        if (!$from || !$to || $from == $to) return;

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO relationships
            (family_id, person_id, related_person_id, relation_type)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$familyId, $from, $to, $type]);
    }

    /* ---------- 4️⃣ RELATIONSHIPS ---------- */
    $parents  = $input['parents'] ?? [];
    $children = $input['children'] ?? [];
    $spouse   = (int)($input['spouse'] ?? 0);

    foreach ($parents as $p) {
        if (memberInFamily($pdo, $familyId, $p)) {
            addRelationSafe($pdo, $familyId, $p, $memberId, 'parent');
            addRelationSafe($pdo, $familyId, $memberId, $p, 'child');
        }
    }

    foreach ($children as $c) {
        if (memberInFamily($pdo, $familyId, $c)) {
            addRelationSafe($pdo, $familyId, $memberId, $c, 'parent');
            addRelationSafe($pdo, $familyId, $c, $memberId, 'child');
        }
    }

    if ($spouse && memberInFamily($pdo, $familyId, $spouse)) {
        addRelationSafe($pdo, $familyId, $memberId, $spouse, 'spouse');
        addRelationSafe($pdo, $familyId, $spouse, $memberId, 'spouse');
    }

    $pdo->commit();

    response(true, "Member created successfully", [
        "member_id" => $memberId
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    response(false, "Failed to create member", [
        "error" => $e->getMessage()
    ], 500);
}






// header("Content-Type: application/json");

// require_once __DIR__ . "/../../config/database.php";
// require_once __DIR__ . "/../../helpers/response.php";
// require_once __DIR__ . "/../../helpers/auth_helper.php";

// $user = getAuthUser();

// // 🔹 READ JSON BODY
// $input = json_decode(file_get_contents("php://input"), true);
// if (!$input) {
//     response(false, "Invalid JSON payload", null, 400);
// }

// $pdo->beginTransaction();

// try {
//     $familyId = (int)($input['family_id'] ?? 0);
//     $first    = trim($input['first_name'] ?? '');
//     $gender   = $input['gender'] ?? null;

//     if (!$familyId || !$first) {
//         response(false, "Required fields missing", null, 400);
//     }

//     /* ---------- CREATE MEMBER ---------- */
//     $stmt = $pdo->prepare("
//         INSERT INTO family_members 
//         (family_id, first_name, last_name, nickname, gender, birth_date, bio, is_default_viewpoint, user_id)
//         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
//     ");

//     $stmt->execute([
//         $familyId,
//         $first,
//         $input['last_name'] ?? null,
//         $input['nickname'] ?? null,
//         $gender,
//         $input['birth_date'] ?? null,
//         $input['bio'] ?? null,
//         (int)($input['is_default_viewpoint'] ?? 0),
//         $user['id']
//     ]);

//     $memberId = (int)$pdo->lastInsertId();

//     /* ---------- HELPERS ---------- */
//     function memberExists($pdo, $memberId, $familyId) {
//         $stmt = $pdo->prepare("
//             SELECT id FROM family_members
//             WHERE id = ? AND family_id = ? AND iddelete = 0
//         ");
//         $stmt->execute([$memberId, $familyId]);
//         return (bool)$stmt->fetch();
//     }

//     function addRelationSafe($pdo, $familyId, $from, $to, $type) {
//         if (!$from || !$to || $from == $to) return;

//         $stmt = $pdo->prepare("
//             INSERT INTO relationships
//             (family_id, person_id, related_person_id, relation_type)
//             VALUES (?, ?, ?, ?)
//         ");
//         $stmt->execute([$familyId, $from, $to, $type]);
//     }

//     /* ---------- RELATIONSHIPS ---------- */
//     $parents  = $input['parents'] ?? [];
//     $children = $input['children'] ?? [];
//     $spouse   = (int)($input['spouse'] ?? 0);

//     foreach ($parents as $p) {
//         if (memberExists($pdo, $p, $familyId)) {
//             addRelationSafe($pdo, $familyId, $p, $memberId, 'parent');
//             addRelationSafe($pdo, $familyId, $memberId, $p, 'child');
//         }
//     }

//     foreach ($children as $c) {
//         if (memberExists($pdo, $c, $familyId)) {
//             addRelationSafe($pdo, $familyId, $memberId, $c, 'parent');
//             addRelationSafe($pdo, $familyId, $c, $memberId, 'child');
//         }
//     }

//     if ($spouse && memberExists($pdo, $spouse, $familyId)) {
//         addRelationSafe($pdo, $familyId, $memberId, $spouse, 'spouse');
//         addRelationSafe($pdo, $familyId, $spouse, $memberId, 'spouse');
//     }

//     $pdo->commit();

//     response(true, "Member created successfully", [
//         "member_id" => $memberId
//     ]);

// } catch (Exception $e) {
//     $pdo->rollBack();
//     response(false, "Failed to create member", [
//         "error" => $e->getMessage()
//     ], 500);
// }
