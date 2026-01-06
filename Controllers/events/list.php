<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";

$user = getAuthUser();
if (!$user || empty($user['id'])) {
    response(false, "Unauthorized", null, 401);
}

$input = json_decode(file_get_contents("php://input"), true);

/* ---------------- PAGINATION ---------------- */
$page  = max(1, (int)($input['page'] ?? 1));
$limit = max(1, (int)($input['limit'] ?? 20));
$offset = ($page - 1) * $limit;

/* ---------------- FILTERS ---------------- */
$familyId  = isset($input['family_id']) ? (int)$input['family_id'] : null;
$startDate = $input['start_date'] ?? null;
$endDate   = $input['end_date'] ?? null;
$preset    = $input['preset'] ?? null;

/* ---------------- DATE PRESETS ---------------- */
if ($preset === 'this_week') {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate   = date('Y-m-d', strtotime('sunday this week'));
}
if ($preset === 'this_month') {
    $startDate = date('Y-m-01');
    $endDate   = date('Y-m-t');
}

/* ---------------- VISIBILITY RULES ---------------- */
$where = "
WHERE e.iddelete = 0
AND (
    e.audience_type = 'everyone'
    OR e.member_id = ?
    OR (
        e.audience_type = 'family'
        AND EXISTS (
            SELECT 1 FROM life_event_families lef
            JOIN family_memberships fm ON fm.family_id = lef.family_id
            WHERE lef.event_id = e.id AND fm.user_id = ?
        )
    )
    OR (
        e.audience_type = 'members'
        AND EXISTS (
            SELECT 1 FROM life_event_members lem
            JOIN user_member_links uml ON uml.member_id = lem.member_id
            WHERE lem.event_id = e.id AND uml.user_id = ?
        )
    )
)
";

$params = [$user['id'], $user['id'], $user['id']];

/* ---------------- FAMILY FILTER ---------------- */
if ($familyId) {
    $where .= "
    AND EXISTS (
        SELECT 1 FROM life_event_families lef
        WHERE lef.event_id = e.id AND lef.family_id = ?
    )";
    $params[] = $familyId;
}

/* ---------------- DATE FILTER ---------------- */
if ($startDate) {
    $where .= " AND e.event_date >= ?";
    $params[] = $startDate;
}
if ($endDate) {
    $where .= " AND e.event_date <= ?";
    $params[] = $endDate;
}

/* ---------------- MAIN QUERY ---------------- */
$sql = "
SELECT
    e.id,
    e.title,
    e.description,
    e.event_date,
    e.location,
    e.cover_image,
    e.audience_type,
    e.created_at,
    fm.id AS creator_id,
    CONCAT(fm.first_name, ' ', fm.last_name) AS creator_name,
    fm.avatar AS creator_avatar
FROM life_events e
LEFT JOIN family_members fm ON fm.id = e.member_id
$where
ORDER BY e.event_date DESC
LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------- COLLECT IDS ---------------- */
$eventIds = array_column($events, 'id');

/* ---------------- FAMILIES ---------------- */
$familiesByEvent = [];
if ($eventIds) {
    $in = implode(',', array_fill(0, count($eventIds), '?'));
    $stmt = $pdo->prepare("
        SELECT lef.event_id, f.id, f.name, f.cover_image AS avatar
        FROM life_event_families lef
        JOIN families f ON f.id = lef.family_id
        WHERE lef.event_id IN ($in)
    ");
    $stmt->execute($eventIds);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $familiesByEvent[$r['event_id']][] = [
            "id" => (int)$r['id'],
            "name" => $r['name'],
            "avatar" => $r['avatar']
        ];
    }
}

/* ---------------- MEMBERS ---------------- */
$membersByEvent = [];
if ($eventIds) {
    $stmt = $pdo->prepare("
        SELECT lem.event_id, fm.id, CONCAT(fm.first_name,' ',fm.last_name) AS name, fm.avatar
        FROM life_event_members lem
        JOIN family_members fm ON fm.id = lem.member_id
        WHERE lem.event_id IN ($in)
    ");
    $stmt->execute($eventIds);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $membersByEvent[$r['event_id']][] = [
            "id" => (int)$r['id'],
            "name" => $r['name'],
            "avatar" => $r['avatar']
        ];
    }
}

/* ---------------- RESPONSE ---------------- */
$list = [];
foreach ($events as $e) {
    $id = (int)$e['id'];

    $list[] = [
        "event_id" => $id,
        "title" => $e['title'],
        "audience_type" => $e['audience_type'],
        "event_image_path" => $e['cover_image'],
        "location" => $e['location'],
        "event_date" => $e['event_date'],
        "description" => $e['description'],
        "creator" => [
            "id" => (int)$e['creator_id'],
            "name" => $e['creator_name'],
            "avatar" => $e['creator_avatar']
        ],
        "families" => $e['audience_type'] === 'family' ? ($familiesByEvent[$id] ?? []) : [],
        "members" => $e['audience_type'] === 'members' ? ($membersByEvent[$id] ?? []) : []
    ];
}

/* ---------------- COUNT ---------------- */
$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM life_events e $where
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

response(true, "Events fetched successfully", [
    "list" => $list,
    "pagination" => [
        "page" => $page,
        "limit" => $limit,
        "total" => $total,
        "hasMore" => ($page * $limit) < $total
    ]
]);








// header("Content-Type: application/json");

// require_once __DIR__ . "/../../config/database.php";
// require_once __DIR__ . "/../../helpers/auth_helper.php";
// require_once __DIR__ . "/../../helpers/response.php";

// $user = getAuthUser();
// if (!$user || empty($user['id'])) {
//     response(false, "Unauthorized", null, 401);
// }

// $input = json_decode(file_get_contents("php://input"), true);

// /* ---------------- PAGINATION ---------------- */
// $page  = max(1, (int)($input['page'] ?? 1));
// $limit = max(1, (int)($input['limit'] ?? 20));
// $offset = ($page - 1) * $limit;

// /* ---------------- FILTERS ---------------- */
// $familyId   = isset($input['family_id']) ? (int)$input['family_id'] : null;
// $startDate  = $input['start_date'] ?? null;
// $endDate    = $input['end_date'] ?? null;
// $preset     = $input['preset'] ?? null;

// /* ---------------- DATE PRESETS ---------------- */
// if ($preset === 'this_week') {
//     $startDate = date('Y-m-d', strtotime('monday this week'));
//     $endDate   = date('Y-m-d', strtotime('sunday this week'));
// }

// if ($preset === 'this_month') {
//     $startDate = date('Y-m-01');
//     $endDate   = date('Y-m-t');
// }

// /* ---------------- BASE QUERY ---------------- */
// $where = " WHERE e.iddelete = 0 ";
// $params = [];

// /* Audience visibility */
// $where .= "
// AND (
//     e.audience_type = 'everyone'
//     OR (
//         e.audience_type = 'family'
//         AND EXISTS (
//             SELECT 1 FROM life_event_families lef
//             JOIN family_memberships fm ON fm.family_id = lef.family_id
//             WHERE lef.event_id = e.id AND fm.user_id = ?
//         )
//     )
//     OR (
//         e.audience_type = 'members'
//         AND EXISTS (
//             SELECT 1 FROM life_event_members lem
//             JOIN user_member_links uml ON uml.member_id = lem.member_id
//             WHERE lem.event_id = e.id AND uml.user_id = ?
//         )
//     )
// )";
// $params[] = $user['id'];
// $params[] = $user['id'];

// /* Family filter */
// if ($familyId) {
//     $where .= "
//     AND EXISTS (
//         SELECT 1 FROM life_event_families lef
//         WHERE lef.event_id = e.id AND lef.family_id = ?
//     )";
//     $params[] = $familyId;
// }

// /* Date filter */
// if ($startDate) {
//     $where .= " AND e.event_date >= ?";
//     $params[] = $startDate;
// }

// if ($endDate) {
//     $where .= " AND e.event_date <= ?";
//     $params[] = $endDate;
// }

// /* ---------------- MAIN QUERY ---------------- */
// $sql = "
// SELECT
//     e.id,
//     e.title,
//     e.description,
//     e.event_date,
//     e.location,
//     e.cover_image,
//     e.audience_type,
//     e.created_at,

//     fm.id AS creator_id,
//     CONCAT(fm.first_name, ' ', fm.last_name) AS creator_name,
//     fm.avatar AS creator_avatar

// FROM life_events e
// LEFT JOIN family_members fm ON fm.id = e.member_id
// $where
// ORDER BY e.event_date DESC
// LIMIT $limit OFFSET $offset
// ";

// $stmt = $pdo->prepare($sql);
// $stmt->execute($params);
// $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// /* ---------------- COLLECT EVENT IDS ---------------- */
// $eventIds = array_column($events, 'id');

// /* ---------------- FAMILIES ---------------- */
// $familiesByEvent = [];
// if ($eventIds) {
//     $in = implode(',', array_fill(0, count($eventIds), '?'));
//     $stmt = $pdo->prepare("
//         SELECT
//             lef.event_id,
//             f.id,
//             f.name,
//             f.cover_image AS avatar
//         FROM life_event_families lef
//         JOIN families f ON f.id = lef.family_id
//         WHERE lef.event_id IN ($in)
//     ");
//     $stmt->execute($eventIds);

//     foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
//         $familiesByEvent[$row['event_id']][] = [
//             "id" => (int)$row['id'],
//             "name" => $row['name'],
//             "avatar" => $row['avatar']
//         ];
//     }
// }

// /* ---------------- MEMBERS ---------------- */
// $membersByEvent = [];
// if ($eventIds) {
//     $in = implode(',', array_fill(0, count($eventIds), '?'));
//     $stmt = $pdo->prepare("
//         SELECT
//             lem.event_id,
//             fm.id,
//             CONCAT(fm.first_name, ' ', fm.last_name) AS name,
//             fm.avatar
//         FROM life_event_members lem
//         JOIN family_members fm ON fm.id = lem.member_id
//         WHERE lem.event_id IN ($in)
//     ");
//     $stmt->execute($eventIds);

//     foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
//         $membersByEvent[$row['event_id']][] = [
//             "id" => (int)$row['id'],
//             "name" => $row['name'],
//             "avatar" => $row['avatar']
//         ];
//     }
// }

// /* ---------------- FINAL RESPONSE ---------------- */
// $list = [];

// foreach ($events as $e) {
//     $id = (int)$e['id'];

//     $list[] = [
//         "event_id" => $id,
//         "title" => $e['title'],
//         "audience_type" => $e['audience_type'],
//         "event_image_path" => $e['cover_image'],
//         "location" => $e['location'],
//         "event_date" => $e['event_date'],
//         "description" => $e['description'],

//         "creator" => [
//             "id" => (int)$e['creator_id'],
//             "name" => $e['creator_name'],
//             "avatar" => $e['creator_avatar']
//         ],

//         "families" => $e['audience_type'] === 'family'
//             ? ($familiesByEvent[$id] ?? [])
//             : [],

//         "members" => $e['audience_type'] === 'members'
//             ? ($membersByEvent[$id] ?? [])
//             : []
//     ];
// }

// /* ---------------- COUNT ---------------- */
// $countStmt = $pdo->prepare("
//     SELECT COUNT(*)
//     FROM life_events e
//     $where
// ");
// $countStmt->execute($params);
// $total = (int)$countStmt->fetchColumn();

// /* ---------------- RESPONSE ---------------- */
// response(true, "Events fetched successfully", [
//     "list" => $list,
//     "pagination" => [
//         "page" => $page,
//         "limit" => $limit,
//         "total" => $total,
//         "hasMore" => ($page * $limit) < $total
//     ]
// ]);



// header("Content-Type: application/json");

// require_once __DIR__ . "/../../config/database.php";
// require_once __DIR__ . "/../../helpers/response.php";
// require_once __DIR__ . "/../../helpers/auth_helper.php";

// $user = getAuthUser();
// if (!$user || !isset($user['id'])) {
//     response(false, "Unauthorized", null, 401);
// }

// $input = json_decode(file_get_contents("php://input"), true);

// $page   = max(1, (int)($input['page'] ?? 1));
// $limit  = max(1, (int)($input['limit'] ?? 20));
// $search = trim($input['search'] ?? '');
// $offset = ($page - 1) * $limit;

// /* -------------------------------------------------
//    1) collect user's member ids and family ids
// ------------------------------------------------- */
// $userId = (int)$user['id'];

// /* member ids that this user is linked to (across families) */
// $stmt = $pdo->prepare("
//     SELECT DISTINCT member_id, family_id
//     FROM user_member_links
//     WHERE user_id = ?
// ");
// $stmt->execute([$userId]);
// $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// $userMemberIds = [];
// $userFamilyIds = [];
// foreach ($rows as $r) {
//     $userMemberIds[] = (int)$r['member_id'];
//     $userFamilyIds[] = (int)$r['family_id'];
// }

// /* ensure at least one placeholder value so IN (...) always valid */
// if (empty($userMemberIds)) $userMemberIds = [0];
// if (empty($userFamilyIds)) $userFamilyIds = [0];

// /* build placeholders for IN lists */
// $memberPlaceholders = implode(',', array_fill(0, count($userMemberIds), '?'));
// $familyPlaceholders = implode(',', array_fill(0, count($userFamilyIds), '?'));

// /* -------------------------------------------------
//    2) Build WHERE clause that enforces audience rules
//    Rules:
//     - audience = 'everyone' -> visible to all
//     - audience = 'family'   -> visible if event linked to any family user belongs to
//     - audience = 'members'  -> visible if event linked to any member user is linked to
//     - OR events created by user's member(s) (creator)
// ------------------------------------------------- */

// $where = " WHERE e.iddelete = 0
//   AND (
//        e.audience_type = 'everyone'
//     OR (e.audience_type = 'family' AND EXISTS (
//             SELECT 1 FROM life_event_families lef
//             WHERE lef.event_id = e.id
//               AND lef.family_id IN ($familyPlaceholders)
//         ))
//     OR (e.audience_type = 'members' AND EXISTS (
//             SELECT 1 FROM life_event_members lem
//             WHERE lem.event_id = e.id
//               AND lem.member_id IN ($memberPlaceholders)
//         ))
//     OR e.member_id IN ($memberPlaceholders) /* events created by user's linked member(s) */
//   )";

// /* If you want events that do not have audience_type to be visible, you can add OR e.audience_type IS NULL above */

// /* -------------------------------------------------
//    3) Add search (if present)
// ------------------------------------------------- */
// $params = []; // will hold values in order of placeholders

// // add family & member ids first because where contains their placeholders earlier
// foreach ($userFamilyIds as $fid) $params[] = $fid;
// foreach ($userMemberIds as $mid) $params[] = $mid;
// foreach ($userMemberIds as $mid) $params[] = $mid; // reused for creator check

// if ($search !== '') {
//     $where .= " AND (
//         e.title LIKE ?
//         OR e.description LIKE ?
//         OR e.location LIKE ?
//     )";
//     $like = "%$search%";
//     $params[] = $like;
//     $params[] = $like;
//     $params[] = $like;
// }

// /* -------------------------------------------------
//    4) Main query (paged)
// ------------------------------------------------- */
// $sql = "
// SELECT
//     e.id,
//     e.title,
//     e.description,
//     e.event_date,
//     e.date_precision,
//     e.location,
//     e.emoji,
//     e.cover_image,
//     e.audience_type,
//     e.created_at,
//     e.family_id,
//     e.member_id
// FROM life_events e
// {$where}
// ORDER BY e.event_date DESC, e.created_at DESC
// LIMIT ? OFFSET ?
// ";

// $paramsForQuery = $params;
// $paramsForQuery[] = $limit;
// $paramsForQuery[] = $offset;

// $stmt = $pdo->prepare($sql);
// $stmt->execute($paramsForQuery);
// $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// /* -------------------------------------------------
//    5) Count total (same where)
// ------------------------------------------------- */
// $countSql = "SELECT COUNT(*) FROM life_events e {$where}";
// $countStmt = $pdo->prepare($countSql);
// $countStmt->execute($params);
// $total = (int)$countStmt->fetchColumn();

// /* -------------------------------------------------
//    6) For each event, optionally fetch linked families/members (if you want)
//       — this is useful so client knows exact audience targets.
// ------------------------------------------------- */
// $payload = [];
// $eventIds = array_map(fn($r) => (int)$r['id'], $events);

// $familiesByEvent = [];
// $membersByEvent = [];

// if (!empty($eventIds)) {
//     // placeholders for event ids
//     $evPlaceholders = implode(',', array_fill(0, count($eventIds), '?'));

//     // fetch families for events
//     $fstmt = $pdo->prepare("
//         SELECT event_id, family_id
//         FROM life_event_families
//         WHERE event_id IN ($evPlaceholders)
//     ");
//     $fstmt->execute($eventIds);
//     foreach ($fstmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
//         $eid = (int)$r['event_id'];
//         $familiesByEvent[$eid][] = (int)$r['family_id'];
//     }

//     // fetch members for events
//     $mstmt = $pdo->prepare("
//         SELECT event_id, member_id
//         FROM life_event_members
//         WHERE event_id IN ($evPlaceholders)
//     ");
//     $mstmt->execute($eventIds);
//     foreach ($mstmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
//         $eid = (int)$r['event_id'];
//         $membersByEvent[$eid][] = (int)$r['member_id'];
//     }
// }

// /* -------------------------------------------------
//    7) Build response list
// ------------------------------------------------- */
// foreach ($events as $e) {
//     $eid = (int)$e['id'];
//     $payload[] = [
//         'id' => $eid,
//         'title' => $e['title'],
//         'description' => $e['description'],
//         'event_date' => $e['event_date'],
//         'date_precision' => $e['date_precision'],
//         'location' => $e['location'],
//         'emoji' => $e['emoji'],
//         'cover_image' => $e['cover_image'],
//         'audience_type' => $e['audience_type'],
//         'families' => $familiesByEvent[$eid] ?? [],
//         'members' => $membersByEvent[$eid] ?? [],
//         'created_at' => $e['created_at'],
//         'creator_member_id' => (int)$e['member_id'],
//     ];
// }

// response(true, "Events fetched successfully", [
//     'list' => $payload,
//     'pagination' => [
//         'page' => $page,
//         'limit' => $limit,
//         'total' => $total,
//         'hasMore' => ($page * $limit) < $total
//     ]
// ]);
