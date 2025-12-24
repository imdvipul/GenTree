<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();
$input = json_decode(file_get_contents("php://input"), true);

/* ================= INPUT ================= */
$familyId  = (int)($input['family_id'] ?? 0);
$page      = max(1, (int)($input['page'] ?? 1));
$limit     = max(1, (int)($input['limit'] ?? 20));
$search    = trim($input['search'] ?? '');
$sortBy    = $input['sort_by'] ?? 'name';
$sortOrder = strtolower($input['sort_order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

if (!$familyId) {
    response(false, "Family ID is required", null, 400);
}

$offset = ($page - 1) * $limit;

/* ================= SORT MAP ================= */
$sortMap = [
    'name'       => "fm.first_name",
    'nickname'   => "fm.nickname",
    'gender'     => "fm.gender",
    'birth_date' => "fm.birth_date"
];
$orderBy = $sortMap[$sortBy] ?? "fm.first_name";

/* ================= SEARCH ================= */
$whereSearch = "";
$params = [$user['id'], $familyId];

if ($search !== "") {
    $whereSearch = "AND (
        fm.first_name LIKE ?
        OR fm.last_name LIKE ?
        OR fm.nickname LIKE ?
    )";
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}

/* ================= MAIN QUERY ================= */
$sql = "
SELECT
    fm.id,
    fm.first_name,
    fm.last_name,
    fm.nickname,
    fm.gender,
    fm.birth_date,

    -- Self
    CASE WHEN fm.id = ? THEN 1 ELSE 0 END AS is_self,

    -- Spouse
    EXISTS (
        SELECT 1 FROM relationships r
        WHERE r.person_id = fm.id
          AND r.relation_type = 'spouse'
          AND r.is_active = 1
    ) AS is_married,

    -- Kids count
    (
        SELECT COUNT(*) FROM relationships r
        WHERE r.person_id = fm.id
          AND r.relation_type = 'parent'
          AND r.is_active = 1
    ) AS kids_count

FROM family_members fm
WHERE fm.family_id = ?
  AND fm.iddelete = 0
  $whereSearch
ORDER BY $orderBy $sortOrder
LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= RELATION LABEL HELPER ================= */
function getRelationLabel($pdo, $familyId, $viewerId, $memberId) {
    if ($viewerId == $memberId) return "Self";

    $stmt = $pdo->prepare("
        SELECT relation_type
        FROM relationships
        WHERE family_id = ?
          AND person_id = ?
          AND related_person_id = ?
          AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$familyId, $viewerId, $memberId]);
    $rel = $stmt->fetchColumn();

    return match ($rel) {
        'parent' => 'Parent',
        'child'  => 'Child',
        'spouse' => 'Spouse',
        default  => 'Family Member',
    };
}

/* ================= FORMAT DATA ================= */
$data = [];

foreach ($members as $m) {
    $name = trim($m['first_name'] . " " . $m['last_name']);

    $age = "N/A";
    if ($m['birth_date']) {
        $age = floor((time() - strtotime($m['birth_date'])) / 31556926) . " yrs";
    }

    $data[] = [
        "id"            => (int)$m['id'],
        "name"          => $name,
        "age"           => $age,
        "relation"      => getRelationLabel($pdo, $familyId, $user['id'], $m['id']),
        "isSelf"        => (bool)$m['is_self'],
        "isMarried"     => (bool)$m['is_married'],
        "kidsCount"     => (int)$m['kids_count'],
        "storiesCount"  => 0
    ];
}

/* ================= TOTAL COUNT ================= */
$countSql = "
SELECT COUNT(*)
FROM family_members fm
WHERE fm.family_id = ?
  AND fm.iddelete = 0
  $whereSearch
";

$countParams = [$familyId];
if ($search !== "") {
    $like = "%$search%";
    array_push($countParams, $like, $like, $like);
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$total = (int)$countStmt->fetchColumn();

/* ================= RESPONSE ================= */
response(true, "Members fetched successfully", [
    "list" => $data,
    "pagination" => [
        "page"    => $page,
        "limit"   => $limit,
        "total"   => $total,
        "hasMore" => ($page * $limit) < $total
    ]
]);
