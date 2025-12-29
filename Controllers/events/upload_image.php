<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();

$eventId = (int)($_POST['event_id'] ?? 0);

if (!$eventId || !isset($_FILES['image'])) {
    response(false, "Event ID and image required", null, 400);
}

$file = $_FILES['image'];
$allowed = ['jpg', 'jpeg', 'png', 'webp'];

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed)) {
    response(false, "Invalid image type", null, 400);
}

/* Upload directory */
$dir = __DIR__ . "/../../public/uploads/events/";
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$filename = "event_" . uniqid() . "." . $ext;
$path = $dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $path)) {
    response(false, "Image upload failed", null, 500);
}

$imagePath = "uploads/events/" . $filename;

/* Save image path */
$stmt = $pdo->prepare("
    UPDATE life_events
    SET cover_image = ?
    WHERE id = ?
");
$stmt->execute([$imagePath, $eventId]);

response(true, "Event image uploaded successfully", [
    "image" => $imagePath
]);
