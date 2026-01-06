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

if (!$memberId || !$familyId || !$relationType) {
    response(false, "Missing required fields");
}

/* -------------------------------------------------
   1️⃣ Get selected members (based on relation type)
------------------------------------------------- */

if ($relationType === 'parent') {

    // Parent of member → child → parent relationship
    // child -> parent
    $selectedSql = "
        SELECT related_person_id AS related_id
        FROM relationships
        WHERE person_id = ?
          AND relation_type = 'child'
    ";
    $params = [$memberId];

} elseif ($relationType === 'child') {

    // Child of member → parent → child relationship
    $selectedSql = "
        SELECT related_person_id AS related_id
        FROM relationships
        WHERE person_id = ?
          AND relation_type = 'parent'
    ";
    $params = [$memberId];

} elseif ($relationType === 'spouse') {

    $selectedSql = "
        SELECT
            CASE
                WHEN person_id = ? THEN related_person_id
                ELSE person_id
            END AS related_id
        FROM relationships
        WHERE (person_id = ? OR related_person_id = ?)
          AND relation_type = 'spouse'
    ";
    $params = [$memberId, $memberId, $memberId];

} else {
    response(false, "Invalid relation type");
}

$stmt = $pdo->prepare($selectedSql);
$stmt->execute($params);
$selectedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

/* -------------------------------------------------
   2️⃣ Exclude only invalid relations (NOT selected)
------------------------------------------------- */

$excludeIds = [$memberId]; // cannot relate to self

// find all existing relations
$stmt = $pdo->prepare("
    SELECT
        CASE
            WHEN person_id = ? THEN related_person_id
            ELSE person_id
        END AS related_id
    FROM relationships
    WHERE (person_id = ? OR related_person_id = ?)
");
$stmt->execute([$memberId, $memberId, $memberId]);

$allRelated = $stmt->fetchAll(PDO::FETCH_COLUMN);

/*
  Remove already-selected ones from exclusion
*/
$excludeIds = array_unique(
    array_diff(
        array_merge($excludeIds, $allRelated),
        $selectedIds
    )
);

/* -------------------------------------------------
   3️⃣ Fetch family members
------------------------------------------------- */

/* -------------------------------------------------
   3️⃣ Fetch family members (CORRECT)
------------------------------------------------- */

$sql = "
SELECT
    fm.id,
    fm.first_name,
    fm.last_name,
    fm.gender,
    fm.birth_date
FROM family_members fm
INNER JOIN user_member_links uml 
    ON uml.member_id = fm.id
WHERE uml.family_id = ?
  AND fm.iddelete = 0
";

$params = [$familyId];

if (!empty($excludeIds)) {
    $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
    $sql .= " AND fm.id NOT IN ($placeholders)";
    $params = array_merge($params, $excludeIds);
}

$sql .= " ORDER BY fm.first_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------------------------------
   Helper: age formatter
------------------------------------------------- */
function formatAge($birthDate) {
    if (!$birthDate) return null;

    $dob = new DateTime($birthDate);
    $now = new DateTime();
    $diff = $dob->diff($now);

    if ($diff->y > 0) return $diff->y . " years";
    if ($diff->m > 0) return $diff->m . " months";
    return $diff->d . " days";
}

/* -------------------------------------------------
   4️⃣ Build response list
------------------------------------------------- */

$list = [];

foreach ($rows as $row) {
    $list[] = [
        "id"       => (int)$row['id'],
        "name"     => trim($row['first_name'] . " " . $row['last_name']),
        "gender"   => $row['gender'],
        "age"      => formatAge($row['birth_date']),
        "selected" => in_array($row['id'], $selectedIds),
    ];
}

/* Put selected on top */
usort($list, fn($a, $b) => $b['selected'] <=> $a['selected']);

response(true, "Members fetched", [
    "list" => $list
]);





// header("Content-Type: application/json");

// require_once __DIR__ . "/../../config/database.php";
// require_once __DIR__ . "/../../helpers/response.php";
// require_once __DIR__ . "/../../helpers/auth_helper.php";

// $user = getAuthUser();
// $input = json_decode(file_get_contents("php://input"), true);

// $memberId     = (int)($input['member_id'] ?? 0);
// $familyId     = (int)($input['family_id'] ?? 0);
// $relationType = $input['relation_type'] ?? '';
// $selectedIds  = $input['selected_ids'] ?? [];

// if (!$familyId || !$relationType) {
//     response(false, "Missing required fields");
// }

// /**
//  * ❌ Prevent invalid relationship rules
//  */
// function isInvalidRelation($type, $gender) {
//     if ($type === 'parent' && $gender === 'child') return true;
//     return false;
// }

// /**
//  * 🧮 Age formatter
//  */
// function formatAge($birthDate) {
//     if (!$birthDate) return null;

//     $dob = new DateTime($birthDate);
//     $now = new DateTime();
//     $diff = $dob->diff($now);

//     if ($diff->y > 0) return $diff->y . " years";
//     if ($diff->m > 0) return $diff->m . " months";
//     return $diff->d . " days";
// }

// /**
//  * ❌ Exclude circular relations
//  */
// $excludeIds = [$memberId];

// $stmt = $pdo->prepare("
//     SELECT related_person_id 
//     FROM relationships 
//     WHERE person_id = ? AND relation_type IN ('parent','child','spouse')
// ");
// $stmt->execute([$memberId]);
// foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
//     $excludeIds[] = $id;
// }

// /**
//  * 🔍 Fetch members
//  */
// $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));

// $sql = "
// SELECT 
//     fm.id,
//     fm.first_name,
//     fm.last_name,
//     fm.gender,
//     fm.birth_date
// FROM family_members fm
// WHERE fm.family_id = ?
//   AND fm.iddelete = 0
//   AND fm.id NOT IN ($placeholders)
// ORDER BY fm.first_name ASC
// ";

// $params = array_merge([$familyId], $excludeIds);
// $stmt = $pdo->prepare($sql);
// $stmt->execute($params);
// $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// /**
//  * Format output
//  */
// $list = [];

// foreach ($rows as $row) {
//     $list[] = [
//         "id" => (int)$row['id'],
//         "name" => trim($row['first_name'] . " " . $row['last_name']),
//         "gender" => $row['gender'],
//         "age" => formatAge($row['birth_date']),
//         "selected" => in_array($row['id'], $selectedIds),
//     ];
// }

// /**
//  * Selected on top
//  */
// usort($list, fn($a, $b) => ($b['selected'] <=> $a['selected']));

// response(true, "Members fetched", [
//     "list" => $list
// ]);
