<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../models/Family.php";

$user = getAuthUser();

if (!$user || !isset($user['id'])) {
    response(false, "Unauthorized", null, 401);
}

$name = trim($_POST['name'] ?? '');
$bio  = $_POST['bio'] ?? '';

if (!$name) {
    response(false, "Family name is required", null, 400);
}

/* ================= IMAGE UPLOAD ================= */

$coverImagePath = null;

if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === 0) {

    $uploadDir = __DIR__ . "/../../public/uploads/families/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $file = $_FILES['cover_image'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($ext, $allowed)) {
        response(false, "Invalid image type", null, 400);
    }

    $fileName = "family_" . uniqid() . "." . $ext;
    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        response(false, "Failed to upload cover image", null, 500);
    }

    // URL stored in DB
    $coverImagePath = "uploads/families/" . $fileName;
}

/* ================= CREATE FAMILY ================= */

$familyModel = new Family($pdo);

$created = $familyModel->create(
    $name,
    $bio,
    $coverImagePath,
    $user['id']
);

if (!$created) {
    response(false, "Failed to create family", null, 500);
}

response(true, "Family created successfully", [
    "name" => $name,
    "bio" => $bio,
    "cover_image" => $coverImagePath
]);
