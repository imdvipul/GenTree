<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";

$user = getAuthUser();
$societyId = $user['society_id'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    response(false, "Notice ID is required");
}

$stmt = $pdo->prepare("
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
    WHERE n.id = ? AND n.is_active = 1
    LIMIT 1
");

$stmt->execute([$id]);
$notice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$notice) {
    response(false, "Notice not found");
}

// Security: Make sure user belongs to the same society as the notice
if ($notice['society_id'] !== $societyId) {
    response(false, "You do not have permission to view this notice");
}

response(true, "Notice details loaded", $notice);
