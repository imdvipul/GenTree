<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

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

response(true, "Family updated successfully", [
    "cover_image" => $coverPath
]);
