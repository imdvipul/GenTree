<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";

$user = getAuthUser();
$input = json_decode(file_get_contents("php://input"), true);

$familyId  = (int)($input['family_id'] ?? 0);
$memberIds = $input['member_ids'] ?? [];

if (!$familyId || !is_array($memberIds) || empty($memberIds)) {
    response(false, "family_id and member_ids required", null, 400);
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

$pdo->beginTransaction();

try {

    foreach ($memberIds as $memberId) {

        $memberId = (int)$memberId;
        if (!$memberId) continue;

        /* ---------- PREVENT OWNER SELF REMOVAL ---------- */
        $stmt = $pdo->prepare("
            SELECT user_id
            FROM user_member_links
            WHERE family_id = ? AND member_id = ?
        ");
        $stmt->execute([$familyId, $memberId]);
        $linkedUserId = $stmt->fetchColumn();

        if ($linkedUserId && (int)$linkedUserId === (int)$user['id']) {
            continue; // owner cannot remove self
        }

        /* ---------- REMOVE FAMILY-SPECIFIC RELATIONS ---------- */
        $stmt = $pdo->prepare("
            DELETE FROM relationships
            WHERE family_id = ?
              AND (person_id = ? OR related_person_id = ?)
        ");
        $stmt->execute([$familyId, $memberId, $memberId]);

        /* ---------- REMOVE MEMBER-FAMILY LINK ---------- */
        $stmt = $pdo->prepare("
            DELETE FROM user_member_links
            WHERE family_id = ? AND member_id = ?
        ");
        $stmt->execute([$familyId, $memberId]);
    }

    $pdo->commit();

    response(true, "Members removed from family successfully");

} catch (Throwable $e) {
    $pdo->rollBack();
    response(false, "Failed to remove members", [
        "error" => $e->getMessage()
    ], 500);
}
