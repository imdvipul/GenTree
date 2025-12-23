<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../models/User.php";

$input = json_decode(file_get_contents("php://input"), true);

$name      = trim($input['name'] ?? '');
$email     = trim($input['email'] ?? '');
$password  = $input['password'] ?? '';
$family_id = $input['family_id'] ?? null; // optional

if (!$name || !$email || !$password) {
    response(false, "All fields required");
}

$userModel = new User($pdo);

if ($userModel->findByEmail($email)) {
    response(false, "Email already exists");
}

$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// ✅ Create user with family_id
$userModel->create(
    $name,
    $email,
    $hashedPassword,
    $family_id
);

response(true, "Registered successfully");
