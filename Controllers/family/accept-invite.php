<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();

if (!$user || empty($user['id'])) {
    response(false, "Unauthorized", null, 401);
}

$input = json_decode(file_get_contents("php://input"), true);
$token = $input['token'] ?? null;

if (!$token) {
    response(false, "Invite token required", null, 400);
}

/* ---------------- FETCH INVITE ---------------- */
$stmt = $pdo->prepare("
    SELECT * FROM family_invites
    WHERE token = ?
      AND (expires_at IS NULL OR expires_at > NOW())
    LIMIT 1
");
$stmt->execute([$token]);
$invite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invite) {
    response(false, "Invalid or expired invite", null, 400);
}

/* ---------------- ALREADY MEMBER CHECK ---------------- */
$stmt = $pdo->prepare("
    SELECT id FROM family_memberships
    WHERE family_id = ? AND user_id = ?
");
$stmt->execute([$invite['family_id'], $user['id']]);

if ($stmt->fetch()) {
    response(false, "You are already a member of this family", null, 400);
}

$pdo->beginTransaction();

try {

    /* ---------------- 1️⃣ FAMILY MEMBERSHIP ---------------- */
    $stmt = $pdo->prepare("
        INSERT INTO family_memberships (family_id, user_id, role, status)
        VALUES (?, ?, ?, 'active')
    ");
    $stmt->execute([
        $invite['family_id'],
        $user['id'],
        $invite['role']
    ]);

    /* ---------------- 2️⃣ FIND OR CREATE GLOBAL MEMBER ---------------- */
    $stmt = $pdo->prepare("
        SELECT id FROM family_members
        WHERE user_id = ? AND iddelete = 0
        LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $memberId = $stmt->fetchColumn();

    if (!$memberId) {
        // Create ONE global member
        $stmt = $pdo->prepare("
            INSERT INTO family_members (first_name, last_name, user_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $user['first_name'] ?? 'Member',
            $user['last_name'] ?? '',
            $user['id']
        ]);
        $memberId = $pdo->lastInsertId();
    }

    /* ---------------- 3️⃣ LINK MEMBER TO FAMILY ---------------- */
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO user_member_links (user_id, family_id, member_id)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        $invite['family_id'],
        $memberId
    ]);

    /* ---------------- 4️⃣ MARK NOTIFICATION READ ---------------- */
    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = ? AND reference_token = ?
    ");
    $stmt->execute([$user['id'], $token]);

    $pdo->commit();

    response(true, "Successfully joined the family", [
        "family_id" => (int)$invite['family_id'],
        "member_id" => (int)$memberId
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();
    response(false, "Failed to accept invite", [
        "error" => $e->getMessage()
    ], 500);
}






// header("Content-Type: application/json");

// require_once __DIR__ . "/../../config/database.php";
// require_once __DIR__ . "/../../helpers/response.php";
// require_once __DIR__ . "/../../helpers/auth_helper.php";

// $user = getAuthUser();

// if (!$user || !$user['id']) {
//     response(false, "Unauthorized", null, 401);
// }

// $input = json_decode(file_get_contents("php://input"), true);
// $token = $input['token'] ?? null;

// if (!$token) {
//     response(false, "Invite token required");
// }

// // Fetch invite
// $stmt = $pdo->prepare("
//     SELECT * FROM family_invites
//     WHERE token = ?
//     AND (expires_at IS NULL OR expires_at > NOW())
//     LIMIT 1
// ");
// $stmt->execute([$token]);
// $invite = $stmt->fetch(PDO::FETCH_ASSOC);

// if (!$invite) {
//     response(false, "Invalid or expired invite");
// }

// // Prevent joining twice
// $check = $pdo->prepare("
//     SELECT id FROM family_members WHERE user_id = ?
// ");
// $check->execute([$user['id']]);
// if ($check->fetch()) {
//     response(false, "You are already part of a family");
// }

// // Create family member
// $stmt = $pdo->prepare("
//     INSERT INTO family_members (family_id, user_id)
//     VALUES (?, ?)
// ");
// $stmt->execute([
//     $invite['family_id'],
//     $user['id']
// ]);

// // Update user family_id
// $pdo->prepare("
//     UPDATE users SET family_id = ? WHERE id = ?
// ")->execute([
//     $invite['family_id'],
//     $user['id']
// ]);

// // Mark notification as read (if exists)
// $pdo->prepare("
//     UPDATE notifications
//     SET is_read = 1
//     WHERE user_id = ?
//     AND type = 'family_invite'
// ")->execute([$user['id']]);

// response(true, "Successfully joined the family");
