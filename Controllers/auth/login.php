<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/jwt_helper.php";
require_once __DIR__ . "/../../models/User.php";

$input = json_decode(file_get_contents("php://input"), true);

$email    = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (!$email || !$password) {
    response(false, "Email and password required");
}

$userModel = new User($pdo);
$user = $userModel->findByEmail($email);

if (!$user || !password_verify($password, $user['password'])) {
    response(false, "Invalid login");
}

// ✅ Generate JWT with family_id
$token = generateJWT([
    "user_id"   => $user['id'],
    "email"     => $user['email'],
    "role"      => $user['role'],
    "family_id" => $user['family_id'] // may be null
]);

unset($user['password']);

response(true, "Login success", [
    "user"  => $user,
    "token" => $token
]);
