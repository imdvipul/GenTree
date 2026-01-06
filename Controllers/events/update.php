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

/* ---------------- BASIC ---------------- */
$eventId = (int)($input['event_id'] ?? 0);
$title   = trim($input['title'] ?? '');

if (!$eventId || !$title) {
    response(false, "event_id and title are required", null, 400);
}

$audienceType = $input['audience_type'] ?? 'everyone';
$familyIds    = $input['family_ids'] ?? [];
$memberIds    = $input['member_ids'] ?? [];

$allowedAudiences = ['everyone', 'family', 'members'];
if (!in_array($audienceType, $allowedAudiences)) {
    response(false, "Invalid audience type", null, 400);
}

/* ---------------- STRICT VALIDATION ---------------- */
if ($audienceType === 'family' && empty($familyIds)) {
    response(false, "family_ids required for family audience", null, 400);
}

if ($audienceType === 'members' && empty($memberIds)) {
    response(false, "member_ids required for members audience", null, 400);
}

try {
    $pdo->beginTransaction();

    /* ---------------- FETCH EVENT ---------------- */
    $stmt = $pdo->prepare("
        SELECT e.id, e.member_id
        FROM life_events e
        JOIN user_member_links uml ON uml.member_id = e.member_id
        WHERE e.id = ? AND uml.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$eventId, $user['id']]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        response(false, "You are not allowed to update this event", null, 403);
    }

    /* ---------------- UPDATE EVENT ---------------- */
    $stmt = $pdo->prepare("
        UPDATE life_events SET
            title = ?,
            description = ?,
            event_date = ?,
            date_precision = ?,
            location = ?,
            emoji = ?,
            audience_type = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $title,
        $input['description'] ?? null,
        $input['event_date'] ?? null,
        $input['date_precision'] ?? null,
        $input['location'] ?? null,
        $input['emoji'] ?? null,
        $audienceType,
        $eventId
    ]);

    /* ---------------- CLEAR OLD LINKS ---------------- */
    $pdo->prepare("DELETE FROM life_event_families WHERE event_id = ?")
        ->execute([$eventId]);

    $pdo->prepare("DELETE FROM life_event_members WHERE event_id = ?")
        ->execute([$eventId]);

    /* ---------------- FAMILY AUDIENCE ---------------- */
    if ($audienceType === 'family') {
        $stmt = $pdo->prepare("
            INSERT INTO life_event_families (event_id, family_id)
            VALUES (?, ?)
        ");

        foreach ($familyIds as $fid) {
            $stmt->execute([$eventId, (int)$fid]);
        }
    }

    /* ---------------- MEMBER AUDIENCE ---------------- */
    if ($audienceType === 'members') {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO life_event_members (event_id, member_id)
            VALUES (?, ?)
        ");

        // creator always included
        $stmt->execute([$eventId, (int)$event['member_id']]);

        foreach ($memberIds as $mid) {
            $stmt->execute([$eventId, (int)$mid]);
        }
    }

    $pdo->commit();

    response(true, "Event updated successfully", [
        "event_id" => $eventId
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();
    response(false, "Failed to update event", [
        "error" => $e->getMessage()
    ], 500);
}
