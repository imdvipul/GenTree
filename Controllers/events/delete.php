<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();

$input = json_decode(file_get_contents("php://input"), true);

$eventId = (int)($input['event_id'] ?? 0);

if (!$eventId) {
    response(false, "Event ID is required", null, 400);
}

/* Check event */
$stmt = $pdo->prepare("
    SELECT id, member_id 
    FROM life_events 
    WHERE id = ? AND iddelete = 0
");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    response(false, "Event not found", null, 404);
}

/* Authorization */
if ((int)$event['member_id'] !== (int)$user['id']) {
    response(false, "Unauthorized access", null, 403);
}

/* Soft delete */
$stmt = $pdo->prepare("
    UPDATE life_events 
    SET iddelete = 1 
    WHERE id = ?
");
$stmt->execute([$eventId]);

response(true, "Event deleted successfully");
