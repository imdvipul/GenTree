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

/* -------------------------------------------------
   1️⃣ Fetch event + creator
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT
        e.id,
        e.title,
        e.description,
        e.emoji,
        e.event_date,
        e.date_precision,
        e.location,
        e.cover_image,
        e.audience_type,
        e.created_at,

        fm.id AS creator_id,
        CONCAT(fm.first_name, ' ', fm.last_name) AS creator_name,
        fm.avatar AS creator_avatar

    FROM life_events e
    LEFT JOIN family_members fm ON fm.id = e.member_id
    WHERE e.id = ?
      AND e.iddelete = 0
    LIMIT 1
");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    response(false, "Event not found", null, 404);
}

/* -------------------------------------------------
   2️⃣ Permission check
------------------------------------------------- */
$canView = false;

if ($event['audience_type'] === 'everyone') {
    $canView = true;
}

/* Family visibility */
if (!$canView && $event['audience_type'] === 'family') {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM life_event_families lef
        JOIN family_memberships fm
          ON fm.family_id = lef.family_id
        WHERE lef.event_id = ?
          AND fm.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$eventId, $user['id']]);
    if ($stmt->fetch()) $canView = true;
}

/* Member visibility */
if (!$canView && $event['audience_type'] === 'members') {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM life_event_members lem
        JOIN user_member_links uml
          ON uml.member_id = lem.member_id
        WHERE lem.event_id = ?
          AND uml.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$eventId, $user['id']]);
    if ($stmt->fetch()) $canView = true;
}

if (!$canView) {
    response(false, "You do not have permission to view this event", null, 403);
}

/* -------------------------------------------------
   3️⃣ Fetch families
------------------------------------------------- */
$families = [];

if ($event['audience_type'] === 'family') {
    $stmt = $pdo->prepare("
        SELECT
            f.id,
            f.name,
            f.cover_image AS avatar
        FROM life_event_families lef
        JOIN families f ON f.id = lef.family_id
        WHERE lef.event_id = ?
    ");
    $stmt->execute([$eventId]);
    $families = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* -------------------------------------------------
   4️⃣ Fetch members
------------------------------------------------- */
$members = [];

if ($event['audience_type'] === 'members') {
    $stmt = $pdo->prepare("
        SELECT
            fm.id,
            CONCAT(fm.first_name, ' ', fm.last_name) AS name,
            fm.avatar
        FROM life_event_members lem
        JOIN family_members fm ON fm.id = lem.member_id
        WHERE lem.event_id = ?
    ");
    $stmt->execute([$eventId]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* -------------------------------------------------
   2️⃣.5️⃣ Check ownership
------------------------------------------------- */
$isOwner = false;

if (!empty($event['creator_id'])) {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM user_member_links
        WHERE member_id = ?
          AND user_id = ?
        LIMIT 1
    ");
    $stmt->execute([
        $event['creator_id'],
        $user['id']
    ]);

    if ($stmt->fetch()) {
        $isOwner = true;
    }
}


/* -------------------------------------------------
   5️⃣ Response
------------------------------------------------- */
response(true, "Event details fetched successfully", [
    "event" => [
        "event_id"      => (int)$event['id'],
        "title"         => $event['title'],
        "description"   => $event['description'],
        "emoji"         => $event['emoji'],
        "event_date"    => $event['event_date'],
        "date_precision"=> $event['date_precision'],
        "location"      => $event['location'],
        "cover_image"   => $event['cover_image'],
        "audience_type" => $event['audience_type'],
        "created_at"    => $event['created_at'],
        "is_owner" => $isOwner,

        "creator" => [
            "id"     => (int)$event['creator_id'],
            "name"   => $event['creator_name'],
            "avatar" => $event['creator_avatar'],
        ],

        "families" => $families,
        "members"  => $members
    ]
]);
