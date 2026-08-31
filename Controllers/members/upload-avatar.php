<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();

$memberId = (int)($_POST['member_id'] ?? 0);

if (!$memberId || !isset($_FILES['image'])) {
    response(false, "Member ID and image required", null, 400);
}

/* Check member belongs to user's family */
$stmt = $pdo->prepare("
    SELECT avatar_url
    FROM family_members
    WHERE id = ? AND user_id = ?
");
$stmt->execute([$memberId, $user['id']]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    response(false, "Unauthorized member access", null, 403);
}

$file = $_FILES['image'];

/* Max 2MB */
if ($file['size'] > 2 * 1024 * 1024) {
    response(false, "Image size must be less than 2MB", null, 400);
}

$allowed = ['jpg', 'jpeg', 'png', 'webp'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed)) {
    response(false, "Invalid image type", null, 400);
}

/* Upload directory */
$dir = __DIR__ . "/../../public/uploads/family/members/";
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

/* Delete old avatar */
if (!empty($member['avatar_url'])) {
    $oldPath = __DIR__ . "/../../public/" . $member['avatar_url'];
    if (file_exists($oldPath)) {
        unlink($oldPath);
    }
}

$filename = "member_" . $memberId . "_" . uniqid() . "." . $ext;
$path = $dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $path)) {
    response(false, "Image upload failed", null, 500);
}

$imagePath = "uploads/family/members/" . $filename;

/* Save avatar */
$stmt = $pdo->prepare("
    UPDATE family_members
    SET avatar_url = ?
    WHERE id = ?
");
$stmt->execute([$imagePath, $memberId]);

response(true, "Avatar uploaded successfully", [
    "avatar" => $imagePath
]);
