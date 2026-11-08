<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";

// Authenticate user
$user = getAuthUser();
$userId = (int)($user['id'] ?? 0);
$userFamilyId = !empty($user['family_id']) ? (int)$user['family_id'] : null;

// Get language preference (default to English 'en')
$lang = isset($_GET['lang']) ? trim($_GET['lang']) : 'en';
if (!in_array($lang, ['en', 'hi', 'gu', 'mr'])) {
    $lang = 'en';
}

$today = date('Y-m-d');
$suvichar = null;

// ── 1. FIRST PRIORITY: User-Submitted Thoughts ──────────────────────────────
// Check if current user or anyone in their family has submitted a good thought
$userThoughtSql = "
    SELECT s.id, s.title, s.thought, s.author, s.language, s.category, s.image_bg, s.display_date,
           s.is_user_submitted, s.user_id, s.created_at,
           u.first_name AS user_first_name,
           u.last_name AS user_last_name,
           u.avatar AS user_avatar,
           TRIM(CONCAT(u.first_name, ' ', u.last_name)) AS user_full_name
    FROM daily_suvichars s
    JOIN users u ON u.id = s.user_id
    WHERE s.is_active = 1 
      AND s.is_delete = 0 
      AND s.is_user_submitted = 1
";

$userThoughtParams = [];
if ($userFamilyId) {
    $userThoughtSql .= " AND (s.family_id = ? OR s.user_id = ?)";
    $userThoughtParams = [$userFamilyId, $userId];
} else {
    $userThoughtSql .= " AND s.user_id = ?";
    $userThoughtParams = [$userId];
}
$userThoughtSql .= " ORDER BY s.id DESC LIMIT 1";

$stmt = $pdo->prepare($userThoughtSql);
$stmt->execute($userThoughtParams);
$suvichar = $stmt->fetch(PDO::FETCH_ASSOC);

// If no family-specific thought, check if there's any active user-submitted thought in the system
if (!$suvichar) {
    $commStmt = $pdo->query("
        SELECT s.id, s.title, s.thought, s.author, s.language, s.category, s.image_bg, s.display_date,
               s.is_user_submitted, s.user_id, s.created_at,
               u.first_name AS user_first_name,
               u.last_name AS user_last_name,
               u.avatar AS user_avatar,
               TRIM(CONCAT(u.first_name, ' ', u.last_name)) AS user_full_name
        FROM daily_suvichars s
        JOIN users u ON u.id = s.user_id
        WHERE s.is_active = 1 
          AND s.is_delete = 0 
          AND s.is_user_submitted = 1
        ORDER BY s.id DESC
        LIMIT 1
    ");
    $suvichar = $commStmt ? $commStmt->fetch(PDO::FETCH_ASSOC) : null;
}

// ── 2. SECOND PRIORITY: Auto-Generated Daily Thoughts (Fallback) ─────────────
if (!$suvichar) {
    // 2a. Check for auto-generated suvichar scheduled specifically for today
    $stmt = $pdo->prepare("
        SELECT id, title, thought, author, language, category, image_bg, display_date,
               is_user_submitted, user_id, created_at,
               NULL AS user_first_name, NULL AS user_last_name, NULL AS user_avatar, NULL AS user_full_name
        FROM daily_suvichars
        WHERE is_active = 1 AND is_delete = 0 AND is_user_submitted = 0 AND language = ? AND display_date = ?
        LIMIT 1
    ");
    $stmt->execute([$lang, $today]);
    $suvichar = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$suvichar) {
    // 2b. Cycle through active auto-generated suvichars in this language by day-of-year
    $stmt = $pdo->prepare("
        SELECT id, title, thought, author, language, category, image_bg, display_date,
               is_user_submitted, user_id, created_at,
               NULL AS user_first_name, NULL AS user_last_name, NULL AS user_avatar, NULL AS user_full_name
        FROM daily_suvichars
        WHERE is_active = 1 AND is_delete = 0 AND is_user_submitted = 0 AND language = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$lang]);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($list) > 0) {
        $dayOfYear = (int)date('z');
        $index = $dayOfYear % count($list);
        $suvichar = $list[$index];
    }
}

if (!$suvichar && $lang !== 'en') {
    // 2c. Fallback to English auto-generated thoughts
    $stmt = $pdo->prepare("
        SELECT id, title, thought, author, language, category, image_bg, display_date,
               is_user_submitted, user_id, created_at,
               NULL AS user_first_name, NULL AS user_last_name, NULL AS user_avatar, NULL AS user_full_name
        FROM daily_suvichars
        WHERE is_active = 1 AND is_delete = 0 AND is_user_submitted = 0 AND language = 'en'
        ORDER BY id ASC
    ");
    $stmt->execute();
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($list) > 0) {
        $dayOfYear = (int)date('z');
        $index = $dayOfYear % count($list);
        $suvichar = $list[$index];
    }
}

if (!$suvichar) {
    // 2d. Ultimate Fallback if DB has no suvichars at all
    $suvichar = [
        "id" => 0,
        "title" => "Positivity",
        "thought" => "Keep your face always toward the sunshine - and shadows will fall behind you.",
        "author" => "Walt Whitman",
        "language" => "en",
        "category" => "Positivity",
        "image_bg" => null,
        "display_date" => null,
        "is_user_submitted" => 0,
        "user_id" => null,
        "created_at" => null,
        "user_first_name" => null,
        "user_last_name" => null,
        "user_avatar" => null,
        "user_full_name" => null
    ];
}

response(true, "Daily Suvichar loaded", $suvichar);
