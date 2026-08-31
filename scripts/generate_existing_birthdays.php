<?php
require_once __DIR__ . "/../config/database.php";

echo "Starting birthday events generation for existing members...\n";

try {
    // 1. Fetch all active members who have a birth date and are linked to a family
    $stmt = $pdo->query("
        SELECT fm.id AS member_id, fm.first_name, fm.birth_date, uml.family_id
        FROM family_members fm
        INNER JOIN user_member_links uml ON uml.member_id = fm.id
        WHERE fm.birth_date IS NOT NULL 
          AND fm.iddelete = 0
    ");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($members) . " members with birth dates in database.\n";

    $createdCount = 0;
    $skippedCount = 0;

    $checkStmt = $pdo->prepare("
        SELECT id FROM life_events 
        WHERE member_id = ? AND emoji = '🎂' AND iddelete = 0 
        LIMIT 1
    ");

    $insertEventStmt = $pdo->prepare("
        INSERT INTO life_events (
            member_id,
            audience_type,
            title,
            description,
            event_date,
            emoji,
            iddelete
        )
        VALUES (?, 'family', ?, ?, ?, '🎂', 0)
    ");

    $insertLinkStmt = $pdo->prepare("
        INSERT INTO life_event_families (event_id, family_id)
        VALUES (?, ?)
    ");

    foreach ($members as $m) {
        $memberId  = (int)$m['member_id'];
        $familyId  = (int)$m['family_id'];
        $firstName = $m['first_name'];
        $birthDate = $m['birth_date'];

        // Skip invalid zero dates
        if (!$birthDate || $birthDate === '0000-00-00' || $birthDate === '0000-00-00 00:00:00') {
            continue;
        }

        // 2. Check if birthday event already exists for this member
        $checkStmt->execute([$memberId]);
        $eventId = $checkStmt->fetchColumn();

        if ($eventId) {
            $skippedCount++;
            continue;
        }

        // 3. Create birthday event
        $insertEventStmt->execute([
            $memberId,
            "{$firstName}'s Birthday",
            "Celebrating {$firstName}'s Birthday! 🎂",
            $birthDate
        ]);
        $newEventId = (int)$pdo->lastInsertId();

        // 4. Link event to family
        $insertLinkStmt->execute([$newEventId, $familyId]);
        $createdCount++;
    }

    echo "Successfully generated $createdCount birthday events!\n";
    echo "Skipped $skippedCount members who already had birthday events.\n";

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
