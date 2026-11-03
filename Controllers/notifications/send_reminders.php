<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/fcm_helper.php";

/**
 * Automated Life Events, Birthdays & Document Reminders Dispatcher
 *
 * Checks:
 *  1. Events 2 days before
 *  2. Events same day morning
 *  3. Member birthdays today
 *  4. Documents expiring soon
 *
 * Saves in-app notification records and dispatches FCM push notifications.
 */

try {
    $now = date('Y-m-d H:i:s');
    $totalPushes = 0;

    $insertNotifStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, reference_token, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");

    $checkNotifStmt = $pdo->prepare("
        SELECT id FROM notifications 
        WHERE user_id = ? AND type = ? AND reference_token = ? AND DATE(created_at) = CURDATE()
        LIMIT 1
    ");

    // ────────────────────────────────────────────────────────────────────────────
    // 1️⃣ EVENTS HAPPENING IN 2 DAYS (2-Day Advance Reminder)
    // ────────────────────────────────────────────────────────────────────────────
    $eventsIn2DaysSql = "
        SELECT e.id, e.title, e.event_date, e.location, e.emoji, e.audience_type, e.member_id
        FROM life_events e
        WHERE e.iddelete = 0
          AND e.event_date = DATE_ADD(CURDATE(), INTERVAL 2 DAY)
    ";
    $eventsIn2Days = $pdo->query($eventsIn2DaysSql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($eventsIn2Days as $ev) {
        $eventId = (int)$ev['id'];
        $title   = $ev['title'];
        $emoji   = $ev['emoji'] ?: '🗓';
        $loc     = $ev['location'] ? " at " . $ev['location'] : "";
        $formattedDate = date("d M Y", strtotime($ev['event_date']));

        $notifTitle = "{$emoji} Event in 2 Days: {$title}";
        $notifMsg   = "'{$title}' is coming up in 2 days ({$formattedDate}){$loc}. Tap to view details.";

        // Determine audience
        $targetUserIds = [];
        if ($ev['audience_type'] === 'everyone') {
            $targetUserIds = $pdo->query("SELECT id FROM users WHERE isdelete = 0")->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($ev['audience_type'] === 'family') {
            $fStmt = $pdo->prepare("
                SELECT DISTINCT user_id FROM family_memberships 
                WHERE family_id IN (SELECT family_id FROM life_event_families WHERE event_id = ?)
            ");
            $fStmt->execute([$eventId]);
            $targetUserIds = $fStmt->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($ev['audience_type'] === 'members') {
            $mStmt = $pdo->prepare("
                SELECT DISTINCT user_id FROM user_member_links 
                WHERE member_id IN (SELECT member_id FROM life_event_members WHERE event_id = ?)
            ");
            $mStmt->execute([$eventId]);
            $targetUserIds = $mStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $eligibleUserIds = [];
        foreach ($targetUserIds as $uId) {
            $checkNotifStmt->execute([(int)$uId, 'event_reminder', (string)$eventId]);
            if (!$checkNotifStmt->fetch()) {
                $insertNotifStmt->execute([(int)$uId, $notifTitle, $notifMsg, 'event_reminder', (string)$eventId]);
                $eligibleUserIds[] = (int)$uId;
            }
        }

        if (!empty($eligibleUserIds)) {
            sendFcmNotification($eligibleUserIds, $notifTitle, $notifMsg, [
                'type'            => 'event_reminder',
                'event_id'        => (string)$eventId,
                'reference_token' => (string)$eventId,
            ]);
            $totalPushes += count($eligibleUserIds);
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 2️⃣ EVENTS HAPPENING TODAY (Same Day Morning Reminder)
    // ────────────────────────────────────────────────────────────────────────────
    $eventsTodaySql = "
        SELECT e.id, e.title, e.event_date, e.location, e.emoji, e.audience_type, e.member_id
        FROM life_events e
        WHERE e.iddelete = 0
          AND e.event_date = CURDATE()
    ";
    $eventsToday = $pdo->query($eventsTodaySql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($eventsToday as $ev) {
        $eventId = (int)$ev['id'];
        $title   = $ev['title'];
        $emoji   = $ev['emoji'] ?: '🎉';
        $loc     = $ev['location'] ? " at " . $ev['location'] : "";

        $notifTitle = "{$emoji} Today's Event: {$title}";
        $notifMsg   = "Today is '{$title}'{$loc}! Tap to view event details.";

        $targetUserIds = [];
        if ($ev['audience_type'] === 'everyone') {
            $targetUserIds = $pdo->query("SELECT id FROM users WHERE isdelete = 0")->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($ev['audience_type'] === 'family') {
            $fStmt = $pdo->prepare("
                SELECT DISTINCT user_id FROM family_memberships 
                WHERE family_id IN (SELECT family_id FROM life_event_families WHERE event_id = ?)
            ");
            $fStmt->execute([$eventId]);
            $targetUserIds = $fStmt->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($ev['audience_type'] === 'members') {
            $mStmt = $pdo->prepare("
                SELECT DISTINCT user_id FROM user_member_links 
                WHERE member_id IN (SELECT member_id FROM life_event_members WHERE event_id = ?)
            ");
            $mStmt->execute([$eventId]);
            $targetUserIds = $mStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $eligibleUserIds = [];
        foreach ($targetUserIds as $uId) {
            $checkNotifStmt->execute([(int)$uId, 'event_today', (string)$eventId]);
            if (!$checkNotifStmt->fetch()) {
                $insertNotifStmt->execute([(int)$uId, $notifTitle, $notifMsg, 'event_today', (string)$eventId]);
                $eligibleUserIds[] = (int)$uId;
            }
        }

        if (!empty($eligibleUserIds)) {
            sendFcmNotification($eligibleUserIds, $notifTitle, $notifMsg, [
                'type'            => 'event_today',
                'event_id'        => (string)$eventId,
                'reference_token' => (string)$eventId,
            ]);
            $totalPushes += count($eligibleUserIds);
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 3️⃣ FAMILY MEMBER BIRTHDAYS TODAY
    // ────────────────────────────────────────────────────────────────────────────
    $birthdaysTodaySql = "
        SELECT DISTINCT m.id, m.first_name, m.last_name, uml.family_id
        FROM family_members m
        JOIN user_member_links uml ON uml.member_id = m.id
        WHERE m.iddelete = 0
          AND m.birth_date IS NOT NULL
          AND DATE_FORMAT(m.birth_date, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
    ";
    $birthdaysToday = $pdo->query($birthdaysTodaySql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($birthdaysToday as $bm) {
        $memberId = (int)$bm['id'];
        $fullName = trim($bm['first_name'] . ' ' . ($bm['last_name'] ?? ''));
        $familyId = (int)($bm['family_id'] ?? 0);
        $refToken = "bday_" . $memberId;

        $notifTitle = "🎂 Today is {$bm['first_name']}'s Birthday!";
        $notifMsg   = "Wish {$fullName} a wonderful birthday today! 🎉 Tap to view profile.";

        // Find all users in this member's family
        $targetUserIds = [];
        if ($familyId > 0) {
            $fStmt = $pdo->prepare("SELECT DISTINCT user_id FROM family_memberships WHERE family_id = ?");
            $fStmt->execute([$familyId]);
            $targetUserIds = $fStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $eligibleUserIds = [];
        foreach ($targetUserIds as $uId) {
            $checkNotifStmt->execute([(int)$uId, 'event_today', $refToken]);
            if (!$checkNotifStmt->fetch()) {
                $insertNotifStmt->execute([(int)$uId, $notifTitle, $notifMsg, 'event_today', $refToken]);
                $eligibleUserIds[] = (int)$uId;
            }
        }

        if (!empty($eligibleUserIds)) {
            sendFcmNotification($eligibleUserIds, $notifTitle, $notifMsg, [
                'type'            => 'event_today',
                'reference_token' => $refToken,
                'member_id'       => (string)$memberId,
                'family_id'       => (string)$familyId,
            ]);
            $totalPushes += count($eligibleUserIds);
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 4️⃣ EXPIRING DOCUMENTS & BILLS (Expiring within 3 days)
    // ────────────────────────────────────────────────────────────────────────────
    $expDocsSql = "
        SELECT id, user_id, title, document_type, expiry_date, DATEDIFF(expiry_date, CURDATE()) as days_left
        FROM documents
        WHERE isdelete = 0
          AND expiry_date IS NOT NULL
          AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ";
    $expDocs = $pdo->query($expDocsSql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($expDocs as $doc) {
        $uId = (int)$doc['user_id'];
        $docId = (int)$doc['id'];
        $days = (int)$doc['days_left'];
        $daysStr = $days === 0 ? "today" : ($days === 1 ? "tomorrow" : "in {$days} days");

        $notifTitle = "📄 Document Expiry Alert: {$doc['title']}";
        $notifMsg   = "Your {$doc['document_type']} document '{$doc['title']}' expires {$daysStr}. Tap to view.";

        $checkNotifStmt->execute([$uId, 'document_expiring', (string)$docId]);
        if (!$checkNotifStmt->fetch()) {
            $insertNotifStmt->execute([$uId, $notifTitle, $notifMsg, 'document_expiring', (string)$docId]);
            sendFcmNotification([$uId], $notifTitle, $notifMsg, [
                'type'            => 'document_expiring',
                'document_id'     => (string)$docId,
                'reference_token' => (string)$docId,
            ]);
            $totalPushes++;
        }
    }

    response(true, "Daily reminders executed successfully", [
        'pushes_dispatched' => $totalPushes,
        'timestamp' => $now
    ]);

} catch (Throwable $e) {
    error_log("[Reminders Error] " . $e->getMessage());
    response(false, "Failed to send reminders: " . $e->getMessage(), null, 500);
}
