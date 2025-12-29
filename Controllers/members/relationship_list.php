<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();
$input = json_decode(file_get_contents("php://input"), true);

$memberId     = (int)($input['member_id'] ?? 0);
$familyId     = (int)($input['family_id'] ?? 0);
$relationType = $input['relation_type'] ?? '';
$selectedIds  = $input['selected_ids'] ?? [];

if (!$familyId || !$relationType) {
    response(false, "Missing required fields");
}

/**
 * ❌ Prevent invalid relationship rules
 */
function isInvalidRelation($type, $gender) {
    if ($type === 'parent' && $gender === 'child') return true;
    return false;
}

/**
 * 🧮 Age formatter
 */
function formatAge($birthDate) {
    if (!$birthDate) return null;

    $dob = new DateTime($birthDate);
    $now = new DateTime();
    $diff = $dob->diff($now);

    if ($diff->y > 0) return $diff->y . " years";
    if ($diff->m > 0) return $diff->m . " months";
    return $diff->d . " days";
}

/**
 * ❌ Exclude circular relations
 */
$excludeIds = [$memberId];

$stmt = $pdo->prepare("
    SELECT related_person_id 
    FROM relationships 
    WHERE person_id = ? AND relation_type IN ('parent','child','spouse')
");
$stmt->execute([$memberId]);
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
    $excludeIds[] = $id;
}

/**
 * 🔍 Fetch members
 */
$placeholders = implode(',', array_fill(0, count($excludeIds), '?'));

$sql = "
SELECT 
    fm.id,
    fm.first_name,
    fm.last_name,
    fm.gender,
    fm.birth_date
FROM family_members fm
WHERE fm.family_id = ?
  AND fm.iddelete = 0
  AND fm.id NOT IN ($placeholders)
ORDER BY fm.first_name ASC
";

$params = array_merge([$familyId], $excludeIds);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Format output
 */
$list = [];

foreach ($rows as $row) {
    $list[] = [
        "id" => (int)$row['id'],
        "name" => trim($row['first_name'] . " " . $row['last_name']),
        "gender" => $row['gender'],
        "age" => formatAge($row['birth_date']),
        "selected" => in_array($row['id'], $selectedIds),
    ];
}

/**
 * Selected on top
 */
usort($list, fn($a, $b) => ($b['selected'] <=> $a['selected']));

response(true, "Members fetched", [
    "list" => $list
]);
