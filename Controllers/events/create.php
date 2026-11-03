<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/fcm_helper.php";

$user = getAuthUser();
if (!$user || empty($user['id'])) {
    response(false, "Unauthorized", null, 401);
}

$input = json_decode(file_get_contents("php://input"), true);

/* ---------------- BASIC ---------------- */
$title = trim($input['title'] ?? '');
if (!$title) {
    response(false, "Event title is required", null, 400);
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

    /* ---------------- CREATOR MEMBER ---------------- */
    $stmt = $pdo->prepare("
        SELECT member_id
        FROM user_member_links
        WHERE user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $creatorMemberId = (int)$stmt->fetchColumn();

    if (!$creatorMemberId) {
        response(false, "Creator member not found", null, 400);
    }

    /* ---------------- CREATE EVENT ---------------- */
    $stmt = $pdo->prepare("
        INSERT INTO life_events (
            member_id,
            audience_type,
            title,
            description,
            event_date,
            date_precision,
            location,
            emoji
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $creatorMemberId,
        $audienceType,
        $title,
        $input['description'] ?? null,
        $input['event_date'] ?? null,
        $input['date_precision'] ?? null,
        $input['location'] ?? null,
        $input['emoji'] ?? null
    ]);

    $eventId = (int)$pdo->lastInsertId();

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
        $stmt->execute([$eventId, $creatorMemberId]);

        foreach ($memberIds as $mid) {
            $stmt->execute([$eventId, (int)$mid]);
        }
    }

    /* ---------------- NOTIFY AUDIENCE ---------------- */
    $creatorName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    if (empty($creatorName)) $creatorName = "A family member";

    $targetUserIds = [];

    if ($audienceType === 'family' && !empty($familyIds)) {
        $inFam = implode(',', array_map('intval', $familyIds));
        $notifUsersStmt = $pdo->prepare("
            SELECT DISTINCT user_id FROM family_memberships
            WHERE family_id IN ($inFam) AND user_id != ?
        ");
        $notifUsersStmt->execute([$user['id']]);
        $targetUserIds = $notifUsersStmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($audienceType === 'members' && !empty($memberIds)) {
        $inMem = implode(',', array_map('intval', $memberIds));
        $notifUsersStmt = $pdo->prepare("
            SELECT DISTINCT user_id FROM user_member_links
            WHERE member_id IN ($inMem) AND user_id != ?
        ");
        $notifUsersStmt->execute([$user['id']]);
        $targetUserIds = $notifUsersStmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($audienceType === 'everyone') {
        $notifUsersStmt = $pdo->prepare("
            SELECT DISTINCT fm2.user_id 
            FROM family_memberships fm1
            JOIN family_memberships fm2 ON fm2.family_id = fm1.family_id
            WHERE fm1.user_id = ? AND fm2.user_id != ?
        ");
        $notifUsersStmt->execute([$user['id'], $user['id']]);
        $targetUserIds = $notifUsersStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if (!empty($targetUserIds)) {
        $notifInsert = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, reference_token, is_read)
            VALUES (?, ?, ?, 'new_event', ?, 0)
        ");

        $eventDateStr = !empty($input['event_date']) ? " for " . date("d M Y", strtotime($input['event_date'])) : "";
        $notifTitle = "🎉 New Event: {$title}";
        $notifMsg = "{$creatorName} created a new event '{$title}'{$eventDateStr}.";

        foreach ($targetUserIds as $tUserId) {
            $notifInsert->execute([$tUserId, $notifTitle, $notifMsg, (string)$eventId]);
        }
    }

    $pdo->commit();

    if (!empty($targetUserIds)) {
        try {
            sendFcmNotification($targetUserIds, $notifTitle, $notifMsg, [
                'type'            => 'new_event',
                'event_id'        => (string)$eventId,
                'reference_token' => (string)$eventId,
            ]);
        } catch (Throwable $pe) {
            error_log("[Push Error] " . $pe->getMessage());
        }
    }

    response(true, "Event created successfully", [
        "event_id" => $eventId
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();
    response(false, "Failed to create event", [
        "error" => $e->getMessage()
    ], 500);
}









// header("Content-Type: application/json");

// require_once __DIR__ . "/../../config/database.php";
// require_once __DIR__ . "/../../helpers/auth_helper.php";
// require_once __DIR__ . "/../../helpers/response.php";

// $user = getAuthUser();
// if (!$user || empty($user['id'])) {
//     response(false, "Unauthorized", null, 401);
// }

// $input = json_decode(file_get_contents("php://input"), true);

// $title = trim($input['title'] ?? '');
// if (!$title) {
//     response(false, "Event title is required", null, 400);
// }

// $audienceType = $input['audience_type'] ?? 'everyone';
// $familyIds    = $input['family_ids'] ?? [];
// $memberIds    = $input['member_ids'] ?? [];

// /* ---------- VALIDATE AUDIENCE ---------- */
// $allowedAudiences = ['everyone', 'family', 'members'];
// if (!in_array($audienceType, $allowedAudiences)) {
//     response(false, "Invalid audience type", null, 400);
// }

// try {
//     $pdo->beginTransaction();

//     /* ---------- GET CREATOR MEMBER ---------- */
//     $stmt = $pdo->prepare("
//         SELECT member_id
//         FROM user_member_links
//         WHERE user_id = ?
//         LIMIT 1
//     ");
//     $stmt->execute([$user['id']]);
//     $creatorMemberId = $stmt->fetchColumn();

//     /* ---------- CREATE EVENT ---------- */
//     $stmt = $pdo->prepare("
//         INSERT INTO life_events (
//             member_id,
//             audience_type,
//             title,
//             description,
//             event_date,
//             date_precision,
//             location,
//             emoji
//         )
//         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
//     ");

//     $stmt->execute([
//         $creatorMemberId ?: null,
//         $audienceType,
//         $title,
//         $input['description'] ?? null,
//         $input['event_date'] ?? null,
//         $input['date_precision'] ?? null,
//         $input['location'] ?? null,
//         $input['emoji'] ?? null
//     ]);

//     $eventId = (int)$pdo->lastInsertId();

//     /* ---------- LINK FAMILIES ---------- */
//     if ($audienceType === 'family' && !empty($familyIds)) {
//         $stmt = $pdo->prepare("
//             INSERT IGNORE INTO life_event_families (event_id, family_id)
//             VALUES (?, ?)
//         ");

//         foreach ($familyIds as $fid) {
//             $stmt->execute([$eventId, (int)$fid]);
//         }
//     }

//     /* ---------- LINK MEMBERS ---------- */
//     if ($audienceType === 'members') {
//         $stmt = $pdo->prepare("
//             INSERT IGNORE INTO life_event_members (event_id, member_id)
//             VALUES (?, ?)
//         ");

//         // always include creator
//         if ($creatorMemberId) {
//             $stmt->execute([$eventId, (int)$creatorMemberId]);
//         }

//         foreach ($memberIds as $mid) {
//             $stmt->execute([$eventId, (int)$mid]);
//         }
//     }

//     $pdo->commit();

//     response(true, "Event created successfully", [
//         "event_id" => $eventId
//     ]);

// } catch (Throwable $e) {
//     $pdo->rollBack();
//     response(false, "Failed to create event", [
//         "error" => $e->getMessage()
//     ], 500);
// }
