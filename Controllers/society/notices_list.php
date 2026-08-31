<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";

// Authenticate user
$user = getAuthUser();
$societyId = $user['society_id'];

if (!$societyId) {
    response(true, "User is not associated with any society", []);
    exit;
}

$history = isset($_GET['history']) && $_GET['history'] == '1';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$priority = isset($_GET['priority']) ? trim($_GET['priority']) : '';

$today = date('Y-m-d');

// Base query joining societies and users (poster details)
$sql = "
    SELECT 
        n.id,
        n.society_id,
        s.name as society_name,
        n.title,
        n.description,
        n.category,
        n.priority,
        n.image_banner,
        n.attachment_url,
        n.target_type,
        n.target_value,
        n.start_date,
        n.end_date,
        n.is_pinned,
        n.is_active,
        n.created_by,
        CONCAT(u.first_name, ' ', u.last_name) as creator_name,
        n.created_at,
        n.updated_at
    FROM society_notices n
    JOIN societies s ON n.society_id = s.id
    JOIN users u ON n.created_by = u.id
    WHERE n.society_id = :society_id AND n.is_active = 1
";

// Filters
if (!$history) {
    // Show only active/current notices
    $sql .= " AND :today BETWEEN n.start_date AND n.end_date";
}

if ($search !== '') {
    $sql .= " AND (n.title LIKE :search OR n.description LIKE :search)";
}

if ($category !== '') {
    $sql .= " AND n.category = :category";
}

if ($priority !== '') {
    $sql .= " AND n.priority = :priority";
}

// Audience targeting check
// Users can see notices targeted to "all", or matching their building/wing/floor
$sql .= " AND (
    n.target_type = 'all' 
    OR (n.target_type = 'building' AND n.target_value = :user_building)
    OR (n.target_type = 'wing' AND n.target_value = :user_wing)
    OR (n.target_type = 'floor' AND n.target_value = :user_floor)
)";

// Custom Sorting logic:
// 1. Emergency priority first
// 2. High priority second
// 3. Pinned notices third
// 4. Recent start date
// 5. Default ID desc
$sql .= "
    ORDER BY 
        CASE WHEN n.priority = 'emergency' THEN 0 ELSE 1 END ASC,
        CASE WHEN n.priority = 'high' THEN 0 ELSE 1 END ASC,
        n.is_pinned DESC,
        n.start_date DESC,
        n.id DESC
";

$stmt = $pdo->prepare($sql);

// Bind params
$params = [
    ':society_id'    => $societyId,
    ':user_building' => $user['building'],
    ':user_wing'     => $user['wing'],
    ':user_floor'    => $user['floor'],
];

if (!$history) {
    $params[':today'] = $today;
}

if ($search !== '') {
    $params[':search'] = '%' . $search . '%';
}

if ($category !== '') {
    $params[':category'] = $category;
}

if ($priority !== '') {
    $params[':priority'] = $priority;
}

$stmt->execute($params);
$notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

response(true, "Notices loaded successfully", $notices);
