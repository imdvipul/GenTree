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

$memberId     = (int)($input['member_id'] ?? 0);
$familyId     = (int)($input['family_id'] ?? 0);
$relationType = $input['relation_type'] ?? '';

if (!$familyId || !$relationType) {
    response(false, "Missing required fields");
}

/* -------------------------------------------------
   1️⃣ Get selected members & related exclusions (if member exists)
------------------------------------------------- */
$selectedIds = [];
$allRelated = [];

if ($memberId > 0) {
    if ($relationType === 'parent') {
        $selectedSql = "
            SELECT related_person_id AS related_id
            FROM relationships
            WHERE person_id = ?
              AND relation_type = 'child'
        ";
        $params = [$memberId];
    } elseif ($relationType === 'child') {
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

    // Get all related to exclude circular relations
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
}

$excludeIds = [];
if ($memberId > 0) {
    $excludeIds[] = $memberId; // cannot relate to self
    $excludeIds = array_unique(
        array_diff(
            array_merge($excludeIds, $allRelated),
            $selectedIds
        )
    );
}

/* -------------------------------------------------
   2️⃣ Fetch family members
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
   3️⃣ Build response list
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
