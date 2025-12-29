<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser(); // JWT decoded user

// ✅ Member id comes from token
$memberId = (int)($user['id'] ?? 0);

if (!$memberId) {
    response(false, "Unauthorized user", null, 401);
}

$input = json_decode(file_get_contents("php://input"), true);

$title = trim($input['title'] ?? '');

if (!$title) {
    response(false, "Event title is required", null, 400);
}

/* Optional fields */
$description   = $input['description'] ?? null;
$eventDate     = $input['event_date'] ?? null;
$datePrecision = $input['date_precision'] ?? null;
$location      = $input['location'] ?? null;
$emoji         = $input['emoji'] ?? null;

try {
    $stmt = $pdo->prepare("
        INSERT INTO life_events (
            member_id,
            title,
            description,
            event_date,
            date_precision,
            location,
            emoji
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $memberId,
        $title,
        $description,
        $eventDate,
        $datePrecision,
        $location,
        $emoji
    ]);

    response(true, "Event created successfully", [
        "event_id" => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    response(false, "Failed to create event", [
        "error" => $e->getMessage()
    ], 500);
}
