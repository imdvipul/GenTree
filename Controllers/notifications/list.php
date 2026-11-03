<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";

$user = getAuthUser();
if (!$user || !isset($user['id'])) {
    response(false, "Unauthorized", null, 401);
}

$userId = (int)$user['id'];

try {
    // ── 1. Fetch User's Families & Linked Members ────────────────────────────
    $stmt = $pdo->prepare("SELECT family_id FROM family_memberships WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userFamilyIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("SELECT member_id FROM user_member_links WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userMemberIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // ── 2. Sync Today's & Upcoming Events ───────────────────────────────────
    $eventConditions = ["e.iddelete = 0", "e.event_date IS NOT NULL"];

    // Audience filter
    $audienceClauses = ["e.audience_type = 'everyone'"];
    if (!empty($userFamilyIds)) {
        $inFam = implode(',', array_map('intval', $userFamilyIds));
        $audienceClauses[] = "(e.audience_type = 'family' AND e.id IN (SELECT event_id FROM life_event_families WHERE family_id IN ($inFam)))";
    }
    if (!empty($userMemberIds)) {
        $inMem = implode(',', array_map('intval', $userMemberIds));
        $audienceClauses[] = "(e.audience_type = 'members' AND e.id IN (SELECT event_id FROM life_event_members WHERE member_id IN ($inMem)))";
    }
    $eventConditions[] = "(" . implode(' OR ', $audienceClauses) . ")";

    // Events happening today
    $todayEventsSql = "
        SELECT e.id, e.title, e.event_date, e.location, e.emoji
        FROM life_events e
        WHERE " . implode(' AND ', $eventConditions) . "
          AND e.event_date = CURDATE()
    ";
    $todayEventsStmt = $pdo->query($todayEventsSql);
    $todayEvents = $todayEventsStmt ? $todayEventsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $checkNotifStmt = $pdo->prepare("
        SELECT id FROM notifications 
        WHERE user_id = ? AND type = ? AND reference_token = ? AND DATE(created_at) = CURDATE()
        LIMIT 1
    ");

    $insertNotifStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, reference_token, is_read)
        VALUES (?, ?, ?, ?, ?, 0)
    ");

    foreach ($todayEvents as $ev) {
        $checkNotifStmt->execute([$userId, 'event_today', (string)$ev['id']]);
        if (!$checkNotifStmt->fetch()) {
            $emoji = $ev['emoji'] ?: '🎉';
            $loc = $ev['location'] ? " at " . $ev['location'] : "";
            $insertNotifStmt->execute([
                $userId,
                "{$emoji} Today's Event: " . $ev['title'],
                "Today is '" . $ev['title'] . "'{$loc}. Tap to view details.",
                'event_today',
                (string)$ev['id']
            ]);
        }
    }

    // Events happening in 1-2 days (Upcoming)
    $upcomingEventsSql = "
        SELECT e.id, e.title, e.event_date, e.location, e.emoji
        FROM life_events e
        WHERE " . implode(' AND ', $eventConditions) . "
          AND e.event_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)
    ";
    $upcomingEventsStmt = $pdo->query($upcomingEventsSql);
    $upcomingEvents = $upcomingEventsStmt ? $upcomingEventsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $checkUpcomingNotifStmt = $pdo->prepare("
        SELECT id FROM notifications 
        WHERE user_id = ? AND type = 'event_reminder' AND reference_token = ? 
          AND created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
        LIMIT 1
    ");

    foreach ($upcomingEvents as $ev) {
        $checkUpcomingNotifStmt->execute([$userId, (string)$ev['id']]);
        if (!$checkUpcomingNotifStmt->fetch()) {
            $formattedDate = date("d M Y", strtotime($ev['event_date']));
            $insertNotifStmt->execute([
                $userId,
                "🗓 Upcoming Event: " . $ev['title'],
                "'" . $ev['title'] . "' is coming up on {$formattedDate}.",
                'event_reminder',
                (string)$ev['id']
            ]);
        }
    }

    // ── 3. Sync Family Member Birthdays Today ───────────────────────────────
    if (!empty($userFamilyIds)) {
        $inFam = implode(',', array_map('intval', $userFamilyIds));
        $birthdayStmt = $pdo->query("
            SELECT DISTINCT m.id, m.first_name, m.last_name, m.birth_date
            FROM family_members m
            JOIN user_member_links uml ON uml.member_id = m.id
            WHERE uml.family_id IN ($inFam)
              AND m.iddelete = 0
              AND m.birth_date IS NOT NULL
              AND DATE_FORMAT(m.birth_date, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
        ");
        $todayBirthdays = $birthdayStmt ? $birthdayStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($todayBirthdays as $bdayMember) {
            $refToken = "bday_" . $bdayMember['id'];
            $checkNotifStmt->execute([$userId, 'event_today', $refToken]);
            if (!$checkNotifStmt->fetch()) {
                $fullName = trim($bdayMember['first_name'] . ' ' . ($bdayMember['last_name'] ?? ''));
                $insertNotifStmt->execute([
                    $userId,
                    "🎂 Today is {$bdayMember['first_name']}'s Birthday!",
                    "Wish {$fullName} a wonderful birthday today! 🎉",
                    'event_today',
                    $refToken
                ]);
            }
        }
    }

    // ── 4. Sync Expiring Documents & Bills Due ──────────────────────────────
    $docStmt = $pdo->prepare("
        SELECT id, title, document_type, expiry_date, DATEDIFF(expiry_date, CURDATE()) as days_left
        FROM documents
        WHERE user_id = ? 
          AND isdelete = 0 
          AND expiry_date IS NOT NULL
          AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $docStmt->execute([$userId]);
    $expiringDocs = $docStmt->fetchAll(PDO::FETCH_ASSOC);

    $checkDocNotifStmt = $pdo->prepare("
        SELECT id FROM notifications 
        WHERE user_id = ? AND type = 'document_expiring' AND reference_token = ?
          AND created_at >= DATE_SUB(NOW(), INTERVAL 5 DAY)
        LIMIT 1
    ");

    foreach ($expiringDocs as $d) {
        $checkDocNotifStmt->execute([$userId, (string)$d['id']]);
        if (!$checkDocNotifStmt->fetch()) {
            $daysLeft = (int)$d['days_left'];
            $daysStr = $daysLeft === 0 
                ? "expires today!" 
                : ($daysLeft === 1 ? "expires tomorrow!" : "expires in {$daysLeft} days (on " . date("d M Y", strtotime($d['expiry_date'])) . ").");

            $insertNotifStmt->execute([
                $userId,
                "📄 Document Expiring: " . $d['title'],
                "{$d['title']} ({$d['document_type']}) {$daysStr}",
                'document_expiring',
                (string)$d['id']
            ]);
        }
    }

    // Bills due soon
    $billStmt = $pdo->prepare("
        SELECT d.id as doc_id, d.title, b.vendor_name, b.total_amount, b.due_date, b.currency,
               DATEDIFF(b.due_date, CURDATE()) as days_left
        FROM bills b
        JOIN documents d ON d.id = b.document_id
        WHERE d.user_id = ? 
          AND d.isdelete = 0
          AND b.payment_status = 'unpaid'
          AND b.due_date IS NOT NULL
          AND b.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ");
    $billStmt->execute([$userId]);
    $dueBills = $billStmt->fetchAll(PDO::FETCH_ASSOC);

    $checkBillNotifStmt = $pdo->prepare("
        SELECT id FROM notifications 
        WHERE user_id = ? AND type = 'document_reminder' AND reference_token = ?
          AND created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
        LIMIT 1
    ");

    foreach ($dueBills as $b) {
        $checkBillNotifStmt->execute([$userId, (string)$b['doc_id']]);
        if (!$checkBillNotifStmt->fetch()) {
            $vendor = $b['vendor_name'] ?: $b['title'];
            $curr = $b['currency'] ?: '₹';
            $dueDateFormatted = date("d M Y", strtotime($b['due_date']));
            $insertNotifStmt->execute([
                $userId,
                "💰 Bill Due Soon: {$vendor}",
                "Payment of {$curr}{$b['total_amount']} is due on {$dueDateFormatted}.",
                'document_reminder',
                (string)$b['doc_id']
            ]);
        }
    }

} catch (Exception $e) {
    error_log("Notification sync error: " . $e->getMessage());
}

// ── 5. Fetch Final Notifications List ────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT id, title, message, type, is_read, reference_token, 
           created_at,
           UNIX_TIMESTAMP(created_at) as timestamp,
           DATE_FORMAT(created_at, '%Y-%m-%dT%H:%i:%s+05:30') as created_at_iso
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 100
");
$stmt->execute([$userId]);
$response = $stmt->fetchAll(PDO::FETCH_ASSOC);

response(true, "Notifications fetched", $response);
