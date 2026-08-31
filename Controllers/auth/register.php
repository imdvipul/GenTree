<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/firebase.php";
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../models/User.php";

$input = json_decode(file_get_contents("php://input"), true);

$firstName = trim($input['first_name'] ?? '');
$lastName  = trim($input['last_name'] ?? '');
$email     = trim($input['email'] ?? '');
$password  = $input['password'] ?? '';
$phone     = trim($input['phone_number'] ?? '');
$familyId  = $input['family_id'] ?? null;

if (!$email)    response(false, "Email is required");
if (!$password) response(false, "Password is required");

$userModel = new User($pdo);

// Prevent duplicate registration
if ($userModel->findByEmail($email)) {
    response(false, "Email already exists");
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// Insert new user
$created = $userModel->create([
    'first_name'   => $firstName,
    'last_name'    => $lastName,
    'email'        => $email,
    'password'     => $hashedPassword,
    'phone_number' => $phone,
    'family_id'    => $familyId,
    'provider'     => 'email',
    'provider_id'  => null,
]);

if (!$created) {
    response(false, "Failed to register user");
}

$userId = $pdo->lastInsertId();

// Create Firebase custom token so user can sign in to Firestore/chat immediately
$auth = firebaseAuth();

$customToken = $auth->createCustomToken((string)$userId, [
    'email'     => $email,
    'family_id' => $familyId,
]);

$firebaseToken = $customToken->toString();

response(true, "Registered successfully", [
    "firebase_token" => $firebaseToken,
    "user"           => [
        "id"        => (int)$userId,
        "email"     => $email,
        "family_id" => $familyId,
    ]
]);
