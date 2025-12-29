<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();
if (!$user || !isset($user['id'])) {
    response(false, "Unauthorized", null, 401);
}
$memberId = (int)$user['id']; // ✅ from JWT

$input = json_decode(file_get_contents("php://input"), true);

$page   = max(1, (int)($input['page'] ?? 1));
$limit  = max(1, (int)($input['limit'] ?? 20));
$search = trim($input['search'] ?? '');

$offset = ($page - 1) * $limit;

/* ================= SEARCH ================= */
$where = " WHERE e.member_id = ? AND e.iddelete = 0 ";
$params = [$memberId];

if ($search !== '') {
    $where .= " AND (
        e.title LIKE ?
        OR e.description LIKE ?
        OR e.location LIKE ?
    )";
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}

/* ================= MAIN QUERY ================= */
$sql = "
SELECT
    e.id,
    e.title,
    e.description,
    e.event_date,
    e.date_precision,
    e.location,
    e.emoji,
    e.cover_image,
    e.created_at
FROM life_events e
$where
ORDER BY e.event_date DESC
LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= COUNT ================= */
$countSql = "
SELECT COUNT(*) 
FROM life_events e
$where
";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

/* ================= FORMAT RESPONSE ================= */
$data = array_map(function ($row) {
    return [
        "id"             => (int)$row['id'],
        "title"          => $row['title'],
        "description"    => $row['description'],
        "event_date"     => $row['event_date'],
        "date_precision" => $row['date_precision'],
        "location"       => $row['location'],
        "emoji"          => $row['emoji'],
        "cover_image"    => $row['cover_image'],
        "created_at"     => $row['created_at'],
    ];
}, $rows);

response(true, "Events fetched successfully", [
    "list" => $data,
    "pagination" => [
        "page" => $page,
        "limit" => $limit,
        "total" => $total,
        "hasMore" => ($page * $limit) < $total
    ]
]);
