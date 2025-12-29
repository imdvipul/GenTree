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

try {
    $pdo->beginTransaction();

    /** ================== CHECK MEMBER ================== */
    $check = $pdo->prepare("
        SELECT id FROM family_members 
        WHERE id = ? AND family_id = ? AND iddelete = 0
    ");
    $check->execute([$memberId, $familyId]);

    if (!$check->fetch()) {
        response(false, "Member not found", null, 404);
    }

    /** ================== UPDATE BASIC INFO ================== */
    $stmt = $pdo->prepare("
        UPDATE family_members SET
            first_name = ?,
            last_name = ?,
            nickname = ?,
            gender = ?,
            birth_date = ?,
            bio = ?,
            is_default_viewpoint = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $input['first_name'] ?? null,
        $input['last_name'] ?? null,
        $input['nickname'] ?? null,
        $input['gender'] ?? null,
        $input['birth_date'] ?? null,
        $input['bio'] ?? null,
        (int)($input['is_default_viewpoint'] ?? 0),
        $memberId
    ]);

    /** ================== REMOVE OLD RELATIONSHIPS ================== */
    $pdo->prepare("
        DELETE FROM relationships 
        WHERE person_id = ? OR related_person_id = ?
    ")->execute([$memberId, $memberId]);

    /** ================== RELATION HELPERS ================== */
    function relationExists(PDO $pdo, int $familyId, int $a, int $b, string $type): bool {
        $stmt = $pdo->prepare("
            SELECT id FROM relationships
            WHERE family_id = ?
              AND person_id = ?
              AND related_person_id = ?
              AND relation_type = ?
              AND is_active = 1
        ");
        $stmt->execute([$familyId, $a, $b, $type]);
        return (bool)$stmt->fetch();
    }

    function insertRelation(PDO $pdo, int $familyId, int $from, int $to, string $type) {
        $pdo->prepare("
            INSERT INTO relationships
            (family_id, person_id, related_person_id, relation_type)
            VALUES (?, ?, ?, ?)
        ")->execute([$familyId, $from, $to, $type]);
    }

    /** ================== APPLY RELATIONS ================== */
    $parents  = $input['parents'] ?? [];
    $children = $input['children'] ?? [];
    $spouse   = (int)($input['spouse'] ?? 0);

    // Parents
    foreach ($parents as $pid) {
        if (!$pid || $pid == $memberId) continue;

        insertRelation($pdo, $familyId, $pid, $memberId, 'parent');
        insertRelation($pdo, $familyId, $memberId, $pid, 'child');
    }

    // Children
    foreach ($children as $cid) {
        if (!$cid || $cid == $memberId) continue;

        insertRelation($pdo, $familyId, $memberId, $cid, 'parent');
        insertRelation($pdo, $familyId, $cid, $memberId, 'child');
    }

    // Spouse
    if ($spouse && $spouse !== $memberId) {
        insertRelation($pdo, $familyId, $memberId, $spouse, 'spouse');
        insertRelation($pdo, $familyId, $spouse, $memberId, 'spouse');
    }

    $pdo->commit();

    response(true, "Member updated successfully");

} catch (Throwable $e) {
    $pdo->rollBack();

    response(false, "Failed to update member", [
        "error" => $e->getMessage()
    ], 500);
}
