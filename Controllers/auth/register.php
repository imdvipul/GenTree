<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../models/User.php";

$input = json_decode(file_get_contents("php://input"), true);

// Get inputs
$firstName    = trim($input['first_name'] ?? '');
$lastName     = trim($input['last_name'] ?? '');
$email        = trim($input['email'] ?? '');
$password     = $input['password'] ?? '';
$phone        = trim($input['phone_number'] ?? '');
$familyId     = isset($input['family_id']) && $input['family_id'] !== '' ? (int)$input['family_id'] : null;
$provider     = $input['provider'] ?? 'email';
$providerId   = $input['provider_id'] ?? null;

// Validation
if (!$email) {
    response(false, "Email is required");
}

if ($provider === 'email' && !$password) {
    response(false, "Password is required");
}

// Model
$userModel = new User($pdo);

// Check duplicate email
if ($userModel->findByEmail($email)) {
    response(false, "Email already exists");
}

// Password handling
$hashedPassword = null;
if ($provider === 'email') {
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
}

// Create user
$created = $userModel->create([
    'first_name'   => $firstName,
    'last_name'    => $lastName,
    'email'        => $email,
    'password'     => $hashedPassword,
    'phone_number' => $phone,
    'family_id'    => $familyId,
    'provider'     => $provider,
    'provider_id'  => $providerId,
]);

if (!$created) {
    response(false, "Failed to register user");
}

response(true, "Registered successfully");


// ✅ Example JSON Request (Email Signup)
// {
//   "first_name": "Vipul",
//   "last_name": "Parmar",
//   "email": "vipul@gmail.com",
//   "password": "123456",
//   "phone_number": "9876543210"
// }




//Example JSON Request (Google / Apple login)
// {
//   "first_name": "Vipul",
//   "last_name": "Parmar",
//   "email": "vipul@gmail.com",
//   "provider": "google",
//   "provider_id": "google_uid_123456",
//   "family_id": 1
// }
