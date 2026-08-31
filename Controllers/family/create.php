<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();
if (!$user || !isset($user['id'])) {
    response(false, "Unauthorized", null, 401);
}

$name = trim($_POST['name'] ?? '');
$bio  = trim($_POST['bio'] ?? '');

if (!$name) {
    response(false, "Family name is required", null, 400);
}

/* ================= IMAGE UPLOAD ================= */
$coverImagePath = null;

if (!empty($_FILES['cover_image']['name'])) {

    $uploadDir = __DIR__ . "/../../public/uploads/families/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        response(false, "Invalid image type", null, 400);
    }

    $fileName = "family_" . uniqid() . "." . $ext;
    move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadDir . $fileName);

    $coverImagePath = "uploads/families/" . $fileName;
}

$pdo->beginTransaction();

try {
    /* 1️⃣ Create family */
    $stmt = $pdo->prepare("
        INSERT INTO families (name, bio, cover_image, created_by)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $name,
        $bio,
        $coverImagePath,
        $user['id']
    ]);

    $familyId = $pdo->lastInsertId();

    /* 2️⃣ Creator becomes OWNER */
    $stmt = $pdo->prepare("
        INSERT INTO family_memberships (family_id, user_id, role)
        VALUES (?, ?, 'owner')
    ");
    $stmt->execute([
        $familyId,
        $user['id']
    ]);

    $pdo->commit();

    response(true, "Family created successfully", [
        "family_id"   => $familyId,
        "role"        => "owner",
        "cover_image" => $coverImagePath
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    response(false, $e->getMessage(), null, 500);
}
