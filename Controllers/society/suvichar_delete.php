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
$id = (int)($input['id'] ?? $_GET['id'] ?? 0);

if (!$id) {
    response(false, "Thought ID required", null, 400);
}

// Check ownership
$stmt = $pdo->prepare("SELECT id, user_id FROM daily_suvichars WHERE id = ? AND is_delete = 0");
$stmt->execute([$id]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$existing) {
    response(false, "Thought not found", null, 404);
}

if (!$isAdmin && (int)$existing['user_id'] !== $userId) {
    response(false, "You are not authorized to delete this thought", null, 403);
}

try {
    $stmt = $pdo->prepare("UPDATE daily_suvichars SET is_delete = 1, is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);

    response(true, "Good thought deleted successfully", ["id" => $id]);

} catch (Throwable $e) {
    error_log("[Suvichar Delete Error] " . $e->getMessage());
    response(false, "Failed to delete thought: " . $e->getMessage(), null, 500);
}
