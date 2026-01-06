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
$eventId = (int)($input['event_id'] ?? 0);

if (!$eventId) {
    response(false, "event_id is required", null, 400);
}

try {
    $pdo->beginTransaction();

    /* -------------------------------------------------
       1️⃣ Fetch event owner
    ------------------------------------------------- */
    $stmt = $pdo->prepare("
        SELECT member_id
        FROM life_events
        WHERE id = ? AND iddelete = 0
        LIMIT 1
    ");
    $stmt->execute([$eventId]);
    $creatorMemberId = $stmt->fetchColumn();

    if (!$creatorMemberId) {
        response(false, "Event not found", null, 404);
    }

    /* -------------------------------------------------
       2️⃣ Check ownership
    ------------------------------------------------- */
    $stmt = $pdo->prepare("
        SELECT 1
        FROM user_member_links
        WHERE user_id = ? AND member_id = ?
        LIMIT 1
    ");
    $stmt->execute([$user['id'], $creatorMemberId]);

    if (!$stmt->fetch()) {
        response(false, "You are not allowed to delete this event", null, 403);
    }

    /* -------------------------------------------------
       3️⃣ Soft delete event
    ------------------------------------------------- */
    $stmt = $pdo->prepare("
        UPDATE life_events
        SET iddelete = 1
        WHERE id = ?
    ");
    $stmt->execute([$eventId]);

    /* -------------------------------------------------
       4️⃣ Cleanup relations (optional but recommended)
    ------------------------------------------------- */
    $pdo->prepare("
        DELETE FROM life_event_families
        WHERE event_id = ?
    ")->execute([$eventId]);

    $pdo->prepare("
        DELETE FROM life_event_members
        WHERE event_id = ?
    ")->execute([$eventId]);

    $pdo->commit();

    response(true, "Event deleted successfully");

} catch (Throwable $e) {
    $pdo->rollBack();
    response(false, "Failed to delete event", [
        "error" => $e->getMessage()
    ], 500);
}
