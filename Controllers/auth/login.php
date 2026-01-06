<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/jwt_helper.php";
require_once __DIR__ . "/../../models/User.php";
require_once __DIR__ . "/../../models/FamilyMember.php";

$input = json_decode(file_get_contents("php://input"), true);

$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (!$email || !$password) {
    response(false, "Email and password required");
}

$userModel = new User($pdo);
$memberModel = new FamilyMember($pdo);

$user = $userModel->findByEmail($email);

if (!$user || !password_verify($password, $user['password'])) {
    response(false, "Invalid email or password");
}

/**
 * 🔗 AUTO LINK MEMBER → USER
 * if family member exists with same email and user_id is NULL
 */
// Step 1: Check if already linked by user_id
// 1. Try to find member by user_id
// ✅ Step 1: try to find member linked to this user
$member = $memberModel->findByUserId($user['id']);

// ✅ Step 2: if member exists and user.family_id is empty, attach user's family_id
if ($member && empty($user['family_id']) && !empty($member['family_id'])) {
    $userModel->updateFamily($user['id'], $member['family_id']);
    $user['family_id'] = $member['family_id'];
}

// ✅ If member NOT found => do nothing (user can join/create family later)

// Create JWT
$token = generateJWT([
    "user_id"   => $user['id'],
    "family_id" => $user['family_id'],
    "email"     => $user['email'],
    "role"      => $user['role']
]);

response(true, "Login successful", [
    "token" => $token,
    "user" => [
        "id" => $user['id'],
        "email" => $user['email'],
        "family_id" => $user['family_id'],
        "role" => $user['role']
    ]
]);






// header("Content-Type: application/json");

// require_once __DIR__ . "/../../config/database.php";
// require_once __DIR__ . "/../../helpers/response.php";
// require_once __DIR__ . "/../../helpers/jwt_helper.php";
// require_once __DIR__ . "/../../models/User.php";

// $input = json_decode(file_get_contents("php://input"), true);

// $email    = trim($input['email'] ?? '');
// $password = $input['password'] ?? '';

// if (!$email || !$password) {
//     response(false, "Email and password required");
// }

// $userModel = new User($pdo);
// $user = $userModel->findByEmail($email);

// if (!$user || !password_verify($password, $user['password'])) {
//     response(false, "Invalid login");
// }

// // ✅ Generate JWT with family_id
// $token = generateJWT([
//     "user_id"   => $user['id'],
//     "email"     => $user['email'],
//     "role"      => $user['role'],
//     "family_id" => $user['family_id'] // may be null
// ]);

// unset($user['password']);

// response(true, "Login success", [
//     "user"  => $user,
//     "token" => $token
// ]);
