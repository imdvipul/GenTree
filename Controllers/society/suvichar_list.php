<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";

$user = getAuthUser();
$userId = (int)($user['id'] ?? 0);
$userFamilyId = !empty($user['family_id']) ? (int)$user['family_id'] : null;

$category = isset($_GET['category']) ? trim($_GET['category']) : null;
$type = isset($_GET['type']) ? trim($_GET['type']) : 'all'; // 'all', 'user', 'auto'

$sql = "
    SELECT s.id, s.title, s.thought, s.author, s.language, s.category, s.image_bg, s.display_date,
           s.is_user_submitted, s.user_id, s.created_at, s.is_active,
           u.first_name AS user_first_name,
           u.last_name AS user_last_name,
           u.avatar AS user_avatar,
           TRIM(CONCAT(u.first_name, ' ', u.last_name)) AS user_full_name,
           IF(s.user_id = ?, 1, 0) AS is_mine
    FROM daily_suvichars s
    LEFT JOIN users u ON u.id = s.user_id
    WHERE s.is_delete = 0
";

$params = [$userId];

if ($type === 'user') {
    $sql .= " AND s.is_user_submitted = 1";
} elseif ($type === 'auto') {
    $sql .= " AND s.is_user_submitted = 0";
}

if ($category && $category !== 'All') {
    $sql .= " AND s.category = ?";
    $params[] = $category;
}

$sql .= " ORDER BY s.is_user_submitted DESC, s.id DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

response(true, "Suvichar list fetched", $list);
