<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();
$input = json_decode(file_get_contents("php://input"), true);

$memberId = (int)($input['member_id'] ?? 0);
$familyId = (int)($input['family_id'] ?? 0);

if (!$memberId || !$familyId) {
    response(false, "member_id and family_id are required", null, 400);
}

/* ---------- PERMISSION CHECK ---------- */
$stmt = $pdo->prepare("
    SELECT role
    FROM family_memberships
    WHERE family_id = ? AND user_id = ?
");
$stmt->execute([$familyId, $user['id']]);
$role = $stmt->fetchColumn();

if (!in_array($role, ['owner', 'admin'])) {
    response(false, "You are not allowed to remove members", null, 403);
}

try {
    $pdo->beginTransaction();

    /* ---------- REMOVE USER ↔ MEMBER LINK (ONLY THIS FAMILY) ---------- */
    $stmt = $pdo->prepare("
        DELETE FROM user_member_links
        WHERE family_id = ? AND member_id = ?
    ");
    $stmt->execute([$familyId, $memberId]);

    /* ---------- DISABLE RELATIONSHIPS (ONLY THIS FAMILY) ---------- */
    $stmt = $pdo->prepare("
        UPDATE relationships
        SET is_active = 0
        WHERE family_id = ?
          AND (person_id = ? OR related_person_id = ?)
    ");
    $stmt->execute([$familyId, $memberId, $memberId]);

    /* ---------- SOFT DELETE MEMBER ONLY IF NO FAMILY LEFT ---------- */
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM user_member_links
        WHERE member_id = ?
    ");
    $stmt->execute([$memberId]);
    $stillLinked = (int)$stmt->fetchColumn();

    if ($stillLinked === 0) {
        $pdo->prepare("
            UPDATE family_members
            SET iddelete = 1
            WHERE id = ?
        ")->execute([$memberId]);
    }

    $pdo->commit();

    response(true, "Member removed from family successfully");

} catch (Exception $e) {
    $pdo->rollBack();
    response(false, "Failed to remove member", [
        "error" => $e->getMessage()
    ], 500);
}

