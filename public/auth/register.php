<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/string_helper.php";
require_once __DIR__ . "/../../models/User.php";

$input = json_decode(file_get_contents("php://input"), true);

$name     = cleanString($input['name'] ?? '');
$email    = cleanString($input['email'] ?? '');
$password = $input['password'] ?? '';

if (!$name || !$email || !$password) {
    response(false, "All fields required");
}

$userModel = new User($pdo);

if ($userModel->findByEmail($email)) {
    response(false, "Email already exists");
}

$hashedPassword = password_hash($password, PASSWORD_BCRYPT);
$userModel->create($name, $email, $hashedPassword);

response(true, "Registered");
