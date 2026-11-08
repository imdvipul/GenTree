<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/fcm_helper.php";

$user = getAuthUser();
if (!$user || !isset($user['id'])) {
    response(false, "Unauthorized", null, 401);
}

$userId = (int)$user['id'];
$userFamilyId = !empty($user['family_id']) ? (int)$user['family_id'] : null;

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    response(false, "Invalid payload", null, 400);
}

$thought  = trim($input['thought'] ?? '');
$author   = trim($input['author'] ?? '');
$category = trim($input['category'] ?? 'Positivity');
$language = trim($input['language'] ?? 'en');
$title    = trim($input['title'] ?? $category);

if (empty($thought)) {
    response(false, "Thought text cannot be empty", null, 400);
}

$userFullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if (empty($author)) {
    $author = $userFullName ?: "Community Member";
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO daily_suvichars 
        (title, thought, author, user_id, family_id, language, category, is_user_submitted, is_active, is_delete, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, 0, NOW())
    ");

    $stmt->execute([
        $title,
        $thought,
        $author,
        $userId,
        $userFamilyId,
        $language,
        $category
    ]);

    $newId = (int)$pdo->lastInsertId();

    // Notify family members
    if ($userFamilyId) {
        $famStmt = $pdo->prepare("
            SELECT user_id FROM family_memberships 
            WHERE family_id = ? AND user_id != ?
        ");
        $famStmt->execute([$userFamilyId, $userId]);
        $targetUserIds = $famStmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($targetUserIds)) {
            $notifTitle = "🌱 Daily Thought: {$author}";
            $notifMsg   = "{$author} shared: \"{$thought}\"";

            $notifInsert = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, reference_token, is_read, created_at)
                VALUES (?, ?, ?, 'thought', ?, 0, NOW())
            ");
            foreach ($targetUserIds as $tUid) {
                $notifInsert->execute([(int)$tUid, $notifTitle, $notifMsg, (string)$newId]);
            }

            try {
                sendFcmNotification($targetUserIds, $notifTitle, $notifMsg, [
                    'type'            => 'thought',
                    'suvichar_id'     => (string)$newId,
                    'reference_token' => (string)$newId
                ]);
            } catch (Throwable $e) {}
        }
    }

    response(true, "Good thought posted successfully", [
        "id" => $newId,
        "thought" => $thought,
        "author" => $author,
        "category" => $category,
        "user_full_name" => $userFullName,
        "user_avatar" => $user['avatar'] ?? null,
        "is_user_submitted" => 1
    ]);

} catch (Throwable $e) {
    error_log("[Suvichar Create Error] " . $e->getMessage());

    // If a column is missing, run auto-migration and retry once
    if (str_contains($e->getMessage(), 'user_id') || str_contains($e->getMessage(), '1054') || str_contains($e->getMessage(), 'Unknown column')) {
        try {
            require_once __DIR__ . "/../../helpers/schema_helper.php";
            ensureDailySuvicharSchema($pdo, true);

            $stmt = $pdo->prepare("
                INSERT INTO daily_suvichars 
                (title, thought, author, user_id, family_id, language, category, is_user_submitted, is_active, is_delete, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, 0, NOW())
            ");
            $stmt->execute([
                $title,
                $thought,
                $author,
                $userId,
                $userFamilyId,
                $language,
                $category
            ]);

            $newId = (int)$pdo->lastInsertId();

            response(true, "Good thought posted successfully", [
                "id" => $newId,
                "thought" => $thought,
                "author" => $author,
                "category" => $category,
                "user_full_name" => $userFullName,
                "user_avatar" => $user['avatar'] ?? null,
                "is_user_submitted" => 1
            ]);
            exit;
        } catch (Throwable $retryEx) {
            error_log("[Suvichar Create Retry Failed] " . $retryEx->getMessage());
            response(false, "Failed to create thought: " . $retryEx->getMessage(), null, 500);
            exit;
        }
    }

    response(false, "Failed to create thought: " . $e->getMessage(), null, 500);
}
