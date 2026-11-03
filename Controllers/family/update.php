<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/fcm_helper.php";

$user = getAuthUser();

$id   = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$bio  = $_POST['bio'] ?? '';

if (!$id || !$name) {
    response(false, "Invalid data", null, 400);
}

/* 🔹 Fetch existing family */
$stmt = $pdo->prepare(
    "SELECT cover_image FROM families WHERE id = ? AND created_by = ?"
);
$stmt->execute([$id, $user['id']]);
$family = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$family) {
    response(false, "Family not found or unauthorized", null, 403);
}

$coverPath = $family['cover_image'];

/* ================= IMAGE UPLOAD ================= */

if (!empty($_FILES['cover_image']['name'])) {

    $allowed = ['jpg','jpeg','png','webp'];
    $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        response(false, "Invalid image type", null, 400);
    }

    $uploadDir = __DIR__ . "/../../public/uploads/families/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = "family_" . uniqid() . "." . $ext;
    $fullPath = $uploadDir . $fileName;

    if (!move_uploaded_file($_FILES['cover_image']['tmp_name'], $fullPath)) {
        response(false, "Image upload failed", null, 500);
    }

    // optionally delete old image
    if ($coverPath && file_exists(__DIR__ . "/../../public/" . $coverPath)) {
        @unlink(__DIR__ . "/../../public/" . $coverPath);
    }

    $coverPath = "uploads/families/" . $fileName;
}

/* ================= UPDATE ================= */

$stmt = $pdo->prepare("
    UPDATE families
    SET name = ?, bio = ?, cover_image = ?
    WHERE id = ? AND created_by = ?
");

$stmt->execute([
    $name,
    $bio,
    $coverPath,
    $id,
    $user['id']
]);

/* ================= NOTIFY OTHER FAMILY MEMBERS ================= */
$updaterName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if (empty($updaterName)) $updaterName = "A family member";

$notifUsersStmt = $pdo->prepare("
    SELECT user_id FROM family_memberships
    WHERE family_id = ? AND user_id != ?
");
$notifUsersStmt->execute([$id, $user['id']]);
$targetUserIds = $notifUsersStmt->fetchAll(PDO::FETCH_COLUMN);

$notifTitle = "🏡 Family Updated";
$notifMsg   = "{$updaterName} updated {$name}'s info.";

if (!empty($targetUserIds)) {
    $notifInsert = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, reference_token, is_read, created_at)
        VALUES (?, ?, ?, 'family_update', ?, 0, NOW())
    ");
    foreach ($targetUserIds as $tUserId) {
        $notifInsert->execute([$tUserId, $notifTitle, $notifMsg, (string)$id]);
    }

    try {
        sendFcmNotification($targetUserIds, $notifTitle, $notifMsg, [
            'type'            => 'family_update',
            'family_id'       => (string)$id,
            'family_name'     => $name,
            'reference_token' => (string)$id,
        ]);
    } catch (Throwable $pe) {
        error_log("[Push Error] " . $pe->getMessage());
    }
}

response(true, "Family updated successfully", [
    "cover_image" => $coverPath
]);
