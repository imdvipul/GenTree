<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

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

/* ================= MEMBER ================= */
$stmt = $pdo->prepare("
    SELECT *
    FROM family_members
    WHERE id = ?
      AND iddelete = 0
");
$stmt->execute([$memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    response(false, "Member not found", null, 404);
}

/* ================= PARENTS ================= */
$parentsStmt = $pdo->prepare("
    SELECT 
        fm.id,
        CONCAT(fm.first_name, ' ', fm.last_name) AS name
    FROM relationships r
    JOIN family_members fm ON fm.id = r.person_id
    WHERE r.related_person_id = ?
      AND r.relation_type = 'parent'
      AND r.is_active = 1
");
$parentsStmt->execute([$memberId]);
$parents = $parentsStmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= CHILDREN ================= */
$childrenStmt = $pdo->prepare("
    SELECT 
        fm.id,
        CONCAT(fm.first_name, ' ', fm.last_name) AS name
    FROM relationships r
    JOIN family_members fm ON fm.id = r.related_person_id
    WHERE r.person_id = ?
      AND r.relation_type = 'parent'
      AND r.is_active = 1
");
$childrenStmt->execute([$memberId]);
$children = $childrenStmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= SPOUSE ================= */
$spouseStmt = $pdo->prepare("
    SELECT 
        fm.id,
        CONCAT(fm.first_name, ' ', fm.last_name) AS name
    FROM relationships r
    JOIN family_members fm 
      ON (fm.id = r.related_person_id OR fm.id = r.person_id)
    WHERE r.relation_type = 'spouse'
      AND r.is_active = 1
      AND (r.person_id = ? OR r.related_person_id = ?)
      AND fm.id != ?
    LIMIT 1
");
$spouseStmt->execute([$memberId, $memberId, $memberId]);
$spouse = $spouseStmt->fetch(PDO::FETCH_ASSOC);

$isowner = $member['user_id'] == $user["id"] ? true : false;

/* ================= RESPONSE ================= */
response(true, "Member detail fetched successfully", [
    "id" => (int)$member['id'],
    // "family_id" => (int)$member['family_id'],
    "first_name" => $member['first_name'],
    "last_name" => $member['last_name'],
    "nickname" => $member['nickname'],
    "gender" => $member['gender'],
    "birth_date" => $member['birth_date'],
    "bio" => $member['bio'],
    "avatar" => $member['avatar'],
    "is_default_viewpoint" => (int)$member['is_default_viewpoint'],
    "isowner" => $isowner,

    "parents" => $parents,
    "children" => $children,
    "spouse" => $spouse ?: null
]);
