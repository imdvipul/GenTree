<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/firebase.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/jwt_helper.php";
require_once __DIR__ . "/../../models/User.php";
require_once __DIR__ . "/../../models/FamilyMember.php";

$input = json_decode(file_get_contents("php://input"), true);

$email    = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (!$email || !$password) {
    response(false, "Email and password required");
}

$userModel   = new User($pdo);
$memberModel = new FamilyMember($pdo);

$user = $userModel->findByEmail($email);

if (!$user || !password_verify($password, $user['password'])) {
    response(false, "Invalid email or password");
}

// Auto-link member: if member exists and user.family_id is empty, attach from member
$member = $memberModel->findByUserId($user['id']);

if ($member && empty($user['family_id']) && !empty($member['family_id'])) {
    $userModel->updateFamily($user['id'], $member['family_id']);
    $user['family_id'] = $member['family_id'];
}

// Create JWT for backend auth
$token = generateJWT([
    "user_id"   => $user['id'],
    "family_id" => $user['family_id'],
    "email"     => $user['email'],
    "role"      => $user['role']
]);

// Create Firebase custom token for chat (Firestore)
$firebaseToken = null;
try {
    $auth = firebaseAuth();
    if ($auth) {
        $firebaseToken = $auth->createCustomToken((string)$user['id'], [
            'email'     => $user['email'],
            'family_id' => $user['family_id'],
            'role'      => $user['role']
        ])->toString();
    }
} catch (\Throwable $e) {
    error_log('Firebase token creation failed: ' . $e->getMessage());
}

response(true, "Login successful", [
    "token"          => $token,
    "firebase_token" => $firebaseToken,
    "user"           => [
        "id"          => (int)$user['id'],
        "first_name"  => $user['first_name'],
        "last_name"   => $user['last_name'],
        "email"       => $user['email'],
        "family_id"   => $user['family_id'] !== null ? (int)$user['family_id'] : null,
        "role"        => $user['role'],
        "society_id"  => $user['society_id'] !== null ? (int)$user['society_id'] : null,
        "building"    => $user['building'],
        "wing"        => $user['wing'],
        "floor"       => $user['floor'],
        "unit_number" => $user['unit_number'],
    ]
]);

