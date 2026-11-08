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
$isAdmin = ($user['role'] ?? '') === 'admin';

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    response(false, "Invalid payload", null, 400);
}

$id       = (int)($input['id'] ?? 0);
$thought  = trim($input['thought'] ?? '');
$author   = trim($input['author'] ?? '');
$category = trim($input['category'] ?? 'Positivity');
$language = trim($input['language'] ?? 'en');
$title    = trim($input['title'] ?? $category);

if (!$id || empty($thought)) {
    response(false, "Thought ID and thought text are required", null, 400);
}

// Check ownership
$stmt = $pdo->prepare("SELECT id, user_id FROM daily_suvichars WHERE id = ? AND is_delete = 0");
$stmt->execute([$id]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$existing) {
    response(false, "Thought not found", null, 404);
}

if (!$isAdmin && (int)$existing['user_id'] !== $userId) {
    response(false, "You are not authorized to edit this thought", null, 403);
}

try {
    $stmt = $pdo->prepare("
        UPDATE daily_suvichars
        SET title = ?, thought = ?, author = ?, category = ?, language = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $title,
        $thought,
        $author,
        $category,
        $language,
        $id
    ]);

    response(true, "Good thought updated successfully", [
        "id" => $id,
        "thought" => $thought,
        "author" => $author,
        "category" => $category
    ]);

} catch (Throwable $e) {
    error_log("[Suvichar Update Error] " . $e->getMessage());
    response(false, "Failed to update thought: " . $e->getMessage(), null, 500);
}
