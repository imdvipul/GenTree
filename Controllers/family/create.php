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







// header("Content-Type: application/json");

// require_once __DIR__ . "/../../config/database.php";
// require_once __DIR__ . "/../../helpers/response.php";
// require_once __DIR__ . "/../../helpers/auth_helper.php";
// require_once __DIR__ . "/../../models/Family.php";

// $user = getAuthUser();

// if (!$user || !isset($user['id'])) {
//     response(false, "Unauthorized", null, 401);
// }

// $name = trim($_POST['name'] ?? '');
// $bio  = $_POST['bio'] ?? '';

// if (!$name) {
//     response(false, "Family name is required", null, 400);
// }

// /* ================= IMAGE UPLOAD ================= */
// $coverImagePath = null;

// if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === 0) {
//     $uploadDir = __DIR__ . "/../../public/uploads/families/";

//     if (!is_dir($uploadDir)) {
//         mkdir($uploadDir, 0777, true);
//     }

//     $file = $_FILES['cover_image'];
//     $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

//     if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
//         response(false, "Invalid image type", null, 400);
//     }

//     $fileName = "family_" . uniqid() . "." . $ext;
//     $filePath = $uploadDir . $fileName;

//     if (!move_uploaded_file($file['tmp_name'], $filePath)) {
//         response(false, "Failed to upload cover image", null, 500);
//     }

//     $coverImagePath = "uploads/families/" . $fileName;
// }

// /* ================= CREATE FAMILY ================= */

// $pdo->beginTransaction();

// try {
//     // Create family
//     $stmt = $pdo->prepare("
//         INSERT INTO families (name, bio, cover_image, created_by)
//         VALUES (?, ?, ?, ?)
//     ");
//     $stmt->execute([$name, $bio, $coverImagePath, $user['id']]);

//     $familyId = $pdo->lastInsertId();

//     /**
//      * STEP 1: find or create member for this user
//      */
//     $stmt = $pdo->prepare("
//         SELECT fm.id
//         FROM user_member_links uml
//         JOIN family_members fm ON fm.id = uml.member_id
//         WHERE uml.user_id = ?
//         LIMIT 1
//     ");
//     $stmt->execute([$user['id']]);
//     $memberId = $stmt->fetchColumn();

//     if (!$memberId) {
//         // create new member record
//         $stmt = $pdo->prepare("
//             INSERT INTO family_members
//             (family_id, first_name, last_name, is_default_viewpoint)
//             VALUES (?, ?, ?, 1)
//         ");

//         $stmt->execute([
//             $familyId,
//             $user['name'] ?? 'User',
//             ''
//         ]);

//         $memberId = $pdo->lastInsertId();

//         // link user → member
//         $stmt = $pdo->prepare("
//             INSERT INTO user_member_links (user_id, member_id)
//             VALUES (?, ?)
//         ");
//         $stmt->execute([$user['id'], $memberId]);
//     }

//     /**
//      * STEP 2: attach member to family as OWNER
//      */
//     $stmt = $pdo->prepare("
//         INSERT INTO family_memberships (family_id, member_id, role)
//         VALUES (?, ?, 'owner')
//     ");
//     $stmt->execute([$familyId, $memberId]);

//     $pdo->commit();

//     response(true, "Family created successfully", [
//         "family_id" => $familyId,
//         "name" => $name,
//         "bio" => $bio,
//         "cover_image" => $coverImagePath
//     ]);

// } catch (Exception $e) {
//     $pdo->rollBack();
//     response(false, "Failed to create family", [
//         "error" => $e->getMessage()
//     ], 500);
// }










// header("Content-Type: application/json");

// require_once __DIR__ . "/../../config/database.php";
// require_once __DIR__ . "/../../helpers/response.php";
// require_once __DIR__ . "/../../helpers/auth_helper.php";
// require_once __DIR__ . "/../../models/Family.php";

// $user = getAuthUser();

// if (!$user || !isset($user['id'])) {
//     response(false, "Unauthorized", null, 401);
// }

// $name = trim($_POST['name'] ?? '');
// $bio  = $_POST['bio'] ?? '';

// if (!$name) {
//     response(false, "Family name is required", null, 400);
// }

// /* ================= IMAGE UPLOAD ================= */

// $coverImagePath = null;

// if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === 0) {

//     $uploadDir = __DIR__ . "/../../public/uploads/families/";

//     if (!is_dir($uploadDir)) {
//         mkdir($uploadDir, 0777, true);
//     }

//     $file = $_FILES['cover_image'];
//     $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

//     $allowed = ['jpg', 'jpeg', 'png', 'webp'];

//     if (!in_array($ext, $allowed)) {
//         response(false, "Invalid image type", null, 400);
//     }

//     $fileName = "family_" . uniqid() . "." . $ext;
//     $filePath = $uploadDir . $fileName;

//     if (!move_uploaded_file($file['tmp_name'], $filePath)) {
//         response(false, "Failed to upload cover image", null, 500);
//     }

//     // URL stored in DB
//     $coverImagePath = "uploads/families/" . $fileName;
// }

// /* ================= CREATE FAMILY ================= */

// $familyModel = new Family($pdo);

// $created = $familyModel->create(
//     $name,
//     $bio,
//     $coverImagePath,
//     $user['id']
// );

// if (!$created) {
//     response(false, "Failed to create family", null, 500);
// }

// response(true, "Family created successfully", [
//     "name" => $name,
//     "bio" => $bio,
//     "cover_image" => $coverImagePath
// ]);
