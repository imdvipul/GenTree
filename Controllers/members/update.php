<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/fcm_helper.php";

$user = getAuthUser();
$input = json_decode(file_get_contents("php://input"), true);

$memberId = (int)($input['member_id'] ?? 0);
$familyId = (int)($input['family_id'] ?? 0);

if (!$memberId || !$familyId) {
    response(false, "member_id and family_id are required", null, 400);
}

try {
    $pdo->beginTransaction();

    /* ---------- CHECK MEMBER BELONGS TO FAMILY ---------- */
    $check = $pdo->prepare("
        SELECT fm.id
        FROM family_members fm
        INNER JOIN user_member_links uml ON uml.member_id = fm.id
        WHERE fm.id = ?
          AND uml.family_id = ?
          AND fm.iddelete = 0
    ");
    $check->execute([$memberId, $familyId]);

    if (!$check->fetch()) {
        response(false, "Member not found in this family", null, 404);
    }

    /* ---------- UPDATE MEMBER (GLOBAL) ---------- */
    $stmt = $pdo->prepare("
        UPDATE family_members SET
            first_name = ?,
            last_name = ?,
            nickname = ?,
            gender = ?,
            birth_date = ?,
            bio = ?,
            is_default_viewpoint = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $input['first_name'] ?? null,
        $input['last_name'] ?? null,
        $input['nickname'] ?? null,
        $input['gender'] ?? null,
        $input['birth_date'] ?? null,
        $input['bio'] ?? null,
        (int)($input['is_default_viewpoint'] ?? 0),
        $memberId
    ]);

    /* ---------- REMOVE OLD FAMILY RELATIONS ONLY ---------- */
    $pdo->prepare("
        DELETE FROM relationships
        WHERE family_id = ?
          AND (person_id = ? OR related_person_id = ?)
    ")->execute([$familyId, $memberId, $memberId]);

    /* ---------- RELATION INSERT ---------- */
    function insertRelation(PDO $pdo, int $familyId, int $from, int $to, string $type) {
        if (!$from || !$to || $from === $to) return;

        $pdo->prepare("
            INSERT INTO relationships
            (family_id, person_id, related_person_id, relation_type)
            VALUES (?, ?, ?, ?)
        ")->execute([$familyId, $from, $to, $type]);
    }

    $parents  = $input['parents'] ?? [];
    $children = $input['children'] ?? [];
    $spouse   = (int)($input['spouse'] ?? 0);

    foreach ($parents as $pid) {
        insertRelation($pdo, $familyId, $pid, $memberId, 'parent');
        insertRelation($pdo, $familyId, $memberId, $pid, 'child');
    }

    foreach ($children as $cid) {
        insertRelation($pdo, $familyId, $memberId, $cid, 'parent');
        insertRelation($pdo, $familyId, $cid, $memberId, 'child');
    }

    if ($spouse && $spouse !== $memberId) {
        insertRelation($pdo, $familyId, $memberId, $spouse, 'spouse');
        insertRelation($pdo, $familyId, $spouse, $memberId, 'spouse');
    }

    /* ---------- SYNC BIRTHDAY LIFE EVENT ---------- */
    $firstName = trim($input['first_name'] ?? '');
    $birthDate = $input['birth_date'] ?? null;

    if (!$birthDate) {
        // Soft delete birthday event if date is cleared
        $stmt = $pdo->prepare("
            UPDATE life_events 
            SET iddelete = 1 
            WHERE member_id = ? AND emoji = '🎂'
        ");
        $stmt->execute([$memberId]);
    } else {
        // Check if birthday event already exists for this member
        $stmt = $pdo->prepare("
            SELECT id FROM life_events 
            WHERE member_id = ? AND emoji = '🎂' AND iddelete = 0 
            LIMIT 1
        ");
        $stmt->execute([$memberId]);
        $eventId = $stmt->fetchColumn();

        if ($eventId) {
            // Update existing event
            $stmt = $pdo->prepare("
                UPDATE life_events SET 
                    title = ?,
                    description = ?,
                    event_date = ?
                WHERE id = ?
            ");
            $stmt->execute([
                "{$firstName}'s Birthday",
                "Celebrating {$firstName}'s Birthday! 🎂",
                $birthDate,
                $eventId
            ]);
        } else {
            // Create new event
            $stmt = $pdo->prepare("
                INSERT INTO life_events (
                    member_id,
                    audience_type,
                    title,
                    description,
                    event_date,
                    emoji,
                    iddelete
                )
                VALUES (?, ?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([
                $memberId,
                'family',
                "{$firstName}'s Birthday",
                "Celebrating {$firstName}'s Birthday! 🎂",
                $birthDate,
                '🎂'
            ]);
            $newEventId = (int)$pdo->lastInsertId();

            // Link event to family
            $stmt = $pdo->prepare("
                INSERT INTO life_event_families (event_id, family_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$newEventId, $familyId]);
        }
    }

    /* ---------- NOTIFY OTHER FAMILY MEMBERS ---------- */
    $updaterName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    if (empty($updaterName)) $updaterName = "A family member";

    $famStmt = $pdo->prepare("SELECT name FROM families WHERE id = ?");
    $famStmt->execute([$familyId]);
    $familyName = $famStmt->fetchColumn() ?: "Family";

    $notifUsersStmt = $pdo->prepare("
        SELECT user_id FROM family_memberships
        WHERE family_id = ? AND user_id != ?
    ");
    $notifUsersStmt->execute([$familyId, $user['id']]);
    $targetUserIds = $notifUsersStmt->fetchAll(PDO::FETCH_COLUMN);

    $updatedMemberName = trim(($input['first_name'] ?? '') . ' ' . ($input['last_name'] ?? ''));
    $notifTitle = "🌳 Member Updated";
    $notifMsg   = "{$updaterName} updated {$updatedMemberName}'s details in the {$familyName} tree.";

    if (!empty($targetUserIds)) {
        $notifInsert = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, reference_token, is_read, created_at)
            VALUES (?, ?, ?, 'member_updated', ?, 0, NOW())
        ");
        foreach ($targetUserIds as $tUserId) {
            $notifInsert->execute([$tUserId, $notifTitle, $notifMsg, (string)$memberId]);
        }
    }

    $pdo->commit();

    if (!empty($targetUserIds)) {
        try {
            sendFcmNotification($targetUserIds, $notifTitle, $notifMsg, [
                'type'            => 'member_updated',
                'member_id'       => (string)$memberId,
                'family_id'       => (string)$familyId,
                'family_name'     => $familyName,
                'reference_token' => (string)$memberId,
            ]);
        } catch (Throwable $pe) {
            error_log("[Push Error] " . $pe->getMessage());
        }
    }

    response(true, "Member updated successfully");

} catch (Throwable $e) {
    $pdo->rollBack();
    response(false, "Failed to update member", [
        "error" => $e->getMessage()
    ], 500);
}








// header("Content-Type: application/json");

// require_once __DIR__ . "/../../config/database.php";
// require_once __DIR__ . "/../../helpers/response.php";
// require_once __DIR__ . "/../../helpers/auth_helper.php";

// $user = getAuthUser();
// $input = json_decode(file_get_contents("php://input"), true);

// $memberId = (int)($input['member_id'] ?? 0);
// $familyId = (int)($input['family_id'] ?? 0);

// if (!$memberId || !$familyId) {
//     response(false, "member_id and family_id are required", null, 400);
// }

// try {
//     $pdo->beginTransaction();

//     /** ================== CHECK MEMBER ================== */
//     $check = $pdo->prepare("
//         SELECT id FROM family_members 
//         WHERE id = ? AND family_id = ? AND iddelete = 0
//     ");
//     $check->execute([$memberId, $familyId]);

//     if (!$check->fetch()) {
//         response(false, "Member not found", null, 404);
//     }

//     /** ================== UPDATE BASIC INFO ================== */
//     $stmt = $pdo->prepare("
//         UPDATE family_members SET
//             first_name = ?,
//             last_name = ?,
//             nickname = ?,
//             gender = ?,
//             birth_date = ?,
//             bio = ?,
//             is_default_viewpoint = ?
//         WHERE id = ?
//     ");

//     $stmt->execute([
//         $input['first_name'] ?? null,
//         $input['last_name'] ?? null,
//         $input['nickname'] ?? null,
//         $input['gender'] ?? null,
//         $input['birth_date'] ?? null,
//         $input['bio'] ?? null,
//         (int)($input['is_default_viewpoint'] ?? 0),
//         $memberId
//     ]);

//     /** ================== REMOVE OLD RELATIONSHIPS ================== */
//     $pdo->prepare("
//         DELETE FROM relationships 
//         WHERE person_id = ? OR related_person_id = ?
//     ")->execute([$memberId, $memberId]);

//     /** ================== RELATION HELPERS ================== */
//     function relationExists(PDO $pdo, int $familyId, int $a, int $b, string $type): bool {
//         $stmt = $pdo->prepare("
//             SELECT id FROM relationships
//             WHERE family_id = ?
//               AND person_id = ?
//               AND related_person_id = ?
//               AND relation_type = ?
//               AND is_active = 1
//         ");
//         $stmt->execute([$familyId, $a, $b, $type]);
//         return (bool)$stmt->fetch();
//     }

//     function insertRelation(PDO $pdo, int $familyId, int $from, int $to, string $type) {
//         $pdo->prepare("
//             INSERT INTO relationships
//             (family_id, person_id, related_person_id, relation_type)
//             VALUES (?, ?, ?, ?)
//         ")->execute([$familyId, $from, $to, $type]);
//     }

//     /** ================== APPLY RELATIONS ================== */
//     $parents  = $input['parents'] ?? [];
//     $children = $input['children'] ?? [];
//     $spouse   = (int)($input['spouse'] ?? 0);

//     // Parents
//     foreach ($parents as $pid) {
//         if (!$pid || $pid == $memberId) continue;

//         insertRelation($pdo, $familyId, $pid, $memberId, 'parent');
//         insertRelation($pdo, $familyId, $memberId, $pid, 'child');
//     }

//     // Children
//     foreach ($children as $cid) {
//         if (!$cid || $cid == $memberId) continue;

//         insertRelation($pdo, $familyId, $memberId, $cid, 'parent');
//         insertRelation($pdo, $familyId, $cid, $memberId, 'child');
//     }

//     // Spouse
//     if ($spouse && $spouse !== $memberId) {
//         insertRelation($pdo, $familyId, $memberId, $spouse, 'spouse');
//         insertRelation($pdo, $familyId, $spouse, $memberId, 'spouse');
//     }

//     $pdo->commit();

//     response(true, "Member updated successfully");

// } catch (Throwable $e) {
//     $pdo->rollBack();

//     response(false, "Failed to update member", [
//         "error" => $e->getMessage()
//     ], 500);
// }
