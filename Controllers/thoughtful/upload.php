<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/jwt_helper.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();

if (!isset($_FILES['image'])) {
    response(false, "Image file required");
}

$file = $_FILES['image'];
$ext  = pathinfo($file['name'], PATHINFO_EXTENSION);

$allowed = ['jpg','jpeg','png','webp'];

if (!in_array(strtolower($ext), $allowed)) {
    response(false, "Invalid image type");
}

$dir = __DIR__ . "/../../public/uploads/thoughtful/";

if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$filename = uniqid("thoughtful_") . "." . $ext;
$path = $dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $path)) {
    response(false, "Upload failed");
}

$imageUrl = "uploads/thoughtful/" . $filename;

response(true, "Image uploaded successfully", [
    "image" => $imageUrl
]);
