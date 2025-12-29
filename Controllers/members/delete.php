<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();
$input = json_decode(file_get_contents("php://input"), true);

$memberId = (int)($input['member_id'] ?? 0);

if (!$memberId) {
    response(false, "Member ID required", null, 400);
}

try {
    $pdo->beginTransaction();

    // soft delete member
    $stmt = $pdo->prepare("
        UPDATE family_members SET iddelete = 1 WHERE id = ?
    ");
    $stmt->execute([$memberId]);

    // disable relations
    $pdo->prepare("
        UPDATE relationships SET is_active = 0
        WHERE person_id = ? OR related_person_id = ?
    ")->execute([$memberId, $memberId]);

    $pdo->commit();

    response(true, "Member deleted successfully");

} catch (Exception $e) {
    $pdo->rollBack();
    response(false, "Failed to delete member", [
        "error" => $e->getMessage()
    ], 500);
}
