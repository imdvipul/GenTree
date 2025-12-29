<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();

$input = json_decode(file_get_contents("php://input"), true);

$eventId = (int)($input['id'] ?? 0);

if (!$eventId) {
    response(false, "Event ID is required", null, 400);
}

/* Optional fields */
$title         = $input['title'] ?? null;
$description   = $input['description'] ?? null;
$eventDate     = $input['event_date'] ?? null;
$datePrecision = $input['date_precision'] ?? null;
$location      = $input['location'] ?? null;
$emoji         = $input['emoji'] ?? null;

/* ---------- CHECK EVENT EXISTS & OWNERSHIP ---------- */
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

/* Optional: ensure user owns this member */
if ((int)$event['member_id'] !== (int)$user['id']) {
    response(false, "Unauthorized access", null, 403);
}

/* ---------- BUILD DYNAMIC UPDATE ---------- */
$fields = [];
$params = [];

if ($title !== null) {
    $fields[] = "title = ?";
    $params[] = $title;
}

if ($description !== null) {
    $fields[] = "description = ?";
    $params[] = $description;
}

if ($eventDate !== null) {
    $fields[] = "event_date = ?";
    $params[] = $eventDate;
}

if ($datePrecision !== null) {
    $fields[] = "date_precision = ?";
    $params[] = $datePrecision;
}

if ($location !== null) {
    $fields[] = "location = ?";
    $params[] = $location;
}

if ($emoji !== null) {
    $fields[] = "emoji = ?";
    $params[] = $emoji;
}

if (empty($fields)) {
    response(false, "Nothing to update", null, 400);
}

$params[] = $eventId;

$sql = "
UPDATE life_events 
SET " . implode(", ", $fields) . "
WHERE id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

response(true, "Event updated successfully");
