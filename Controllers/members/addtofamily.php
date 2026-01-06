<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";

/* ---------------- AUTH ---------------- */
$user = getAuthUser();
if (!$user || !isset($user['id'])) {
    response(false, "Unauthorized", null, 401);
}

/* ---------------- INPUT ---------------- */
$input = json_decode(file_get_contents("php://input"), true);

$familyId  = (int)($input['family_id'] ?? 0);
$memberIds = $input['member_ids'] ?? [];

if (!$familyId || !is_array($memberIds) || empty($memberIds)) {
    response(false, "family_id and member_ids required", null, 400);
}

/* ---------------- PERMISSION ---------------- */
$stmt = $pdo->prepare("
    SELECT role FROM family_memberships
    WHERE family_id = ? AND user_id = ?
");
$stmt->execute([$familyId, $user['id']]);
$role = $stmt->fetchColumn();

if (!in_array($role, ['owner', 'admin'])) {
    response(false, "Not allowed", null, 403);
}

/* ---------------- TRANSACTION ---------------- */
$pdo->beginTransaction();

try {

    // ensure family exists
    $stmt = $pdo->prepare("SELECT id FROM families WHERE id = ?");
    $stmt->execute([$familyId]);
    if (!$stmt->fetch()) {
        throw new Exception("Family not found");
    }

    // prepared statements
    $checkMember = $pdo->prepare("
        SELECT id FROM family_members
        WHERE id = ? AND iddelete = 0
    ");

    $insertLink = $pdo->prepare("
        INSERT INTO user_member_links (user_id, family_id, member_id)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE member_id = member_id
    ");

    $added = 0;

    foreach ($memberIds as $memberId) {
        $memberId = (int)$memberId;

        // validate member exists
        $checkMember->execute([$memberId]);
        if (!$checkMember->fetch()) continue;

        // link member to family (NO CLONING)
        $insertLink->execute([
            $user['id'],
            $familyId,
            $memberId
        ]);

        $added++;
    }

    $pdo->commit();

    response(true, "Members linked successfully", [
        "family_id" => $familyId,
        "added_count" => $added
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    response(false, $e->getMessage(), null, 500);
}
