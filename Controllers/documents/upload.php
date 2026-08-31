<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/jwt_helper.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

// Secure user auth
$user = getAuthUser();

if (!isset($_FILES['document'])) {
    response(false, "No document file uploaded");
}

$file = $_FILES['document'];
$ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

if (!in_array(strtolower($ext), $allowed)) {
    response(false, "Unsupported file format. Please upload JPG, PNG, WEBP, or PDF.");
}

$dir = __DIR__ . "/../../public/uploads/documents/";

if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

// Generate secure unique filename
$filename = uniqid("doc_") . "_" . time() . "." . strtolower($ext);
$path = $dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $path)) {
    response(false, "Failed to write document file to server disk");
}

$relativeUrl = "uploads/documents/" . $filename;

response(true, "Document uploaded successfully", [
    "file_path" => $relativeUrl,
    "mime_type" => $file['type'] ?? "application/octet-stream",
    "original_name" => $file['name']
]);
