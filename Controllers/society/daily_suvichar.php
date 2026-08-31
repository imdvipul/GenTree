<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";

// Authenticate user
$user = getAuthUser();

// Get language preference (default to English 'en')
$lang = isset($_GET['lang']) ? trim($_GET['lang']) : 'en';
if (!in_array($lang, ['en', 'hi', 'gu'])) {
    $lang = 'en';
}

$today = date('Y-m-d');

// 1. Try to find a Suvichar assigned specifically for today in this language
$stmt = $pdo->prepare("
    SELECT id, title, thought, author, language, category, image_bg, display_date
    FROM daily_suvichars
    WHERE is_active = 1 AND language = ? AND display_date = ?
    LIMIT 1
");
$stmt->execute([$lang, $today]);
$suvichar = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$suvichar) {
    // 2. Fallback: Find all active suvichars in this language and cycle through them based on the day of the year
    $stmt = $pdo->prepare("
        SELECT id, title, thought, author, language, category, image_bg, display_date
        FROM daily_suvichars
        WHERE is_active = 1 AND language = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$lang]);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($list) > 0) {
        $dayOfYear = (int)date('z'); // 0 to 365
        $index = $dayOfYear % count($list);
        $suvichar = $list[$index];
    }
}

if (!$suvichar && $lang !== 'en') {
    // 3. Fallback: Try English if preferred language has no suvichars
    $stmt = $pdo->prepare("
        SELECT id, title, thought, author, language, category, image_bg, display_date
        FROM daily_suvichars
        WHERE is_active = 1 AND language = 'en'
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
    // 4. Ultimate Fallback if DB is completely empty
    $suvichar = [
        "id" => 0,
        "title" => "Positivity",
        "thought" => "Keep your face always toward the sunshine - and shadows will fall behind you.",
        "author" => "Walt Whitman",
        "language" => "en",
        "category" => "Positivity",
        "image_bg" => null,
        "display_date" => null
    ];
}

response(true, "Daily Suvichar loaded", $suvichar);
