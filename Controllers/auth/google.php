<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/firebase.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/jwt_helper.php";
require_once __DIR__ . "/../../models/User.php";
require_once __DIR__ . "/../../models/FamilyMember.php";

$input = json_decode(file_get_contents("php://input"), true);
$idToken = $input['id_token'] ?? '';

if (!$idToken) {
    response(false, "Google id_token is required");
}

// 1. Verify Google ID token via Google API tokeninfo endpoint
$url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($idToken);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);


if ($httpCode !== 200 || !$response) {
    response(false, "Invalid Google ID token");
}

$payload = json_decode($response, true);
$email = trim($payload['email'] ?? '');
$googleSubId = $payload['sub'] ?? '';
$firstName = trim($payload['given_name'] ?? $payload['name'] ?? 'Google');
$lastName = trim($payload['family_name'] ?? 'User');

if (!$email || !$googleSubId) {
    response(false, "Unable to retrieve user info from Google token");
}

$userModel = new User($pdo);
$memberModel = new FamilyMember($pdo);

// Find existing user
$user = $userModel->findByEmail($email);

if (!$user) {
    // 2. If user doesn't exist, create a new one
    $passwordPlaceholder = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
    $created = $userModel->create([
        'first_name'   => $firstName,
        'last_name'    => $lastName,
        'email'        => $email,
        'password'     => $passwordPlaceholder,
        'phone_number' => '',
        'family_id'    => null,
        'provider'     => 'google',
        'provider_id'  => $googleSubId,
    ]);

    if (!$created) {
        response(false, "Failed to create user account");
    }

    $userId = $pdo->lastInsertId();
    $user = [
        'id'        => $userId,
        'email'     => $email,
        'family_id' => null,
        'role'      => 'member'
    ];
} else {
    // 3. User exists: Update provider details if not set
    if ($user['provider'] !== 'google') {
        $stmt = $pdo->prepare("UPDATE users SET provider = 'google', provider_id = ? WHERE id = ?");
        $stmt->execute([$googleSubId, $user['id']]);
        $user['provider'] = 'google';
        $user['provider_id'] = $googleSubId;
    }
}

// Auto-link member: if member exists and user.family_id is empty, attach from member
$member = $memberModel->findByUserId($user['id']);
if ($member && empty($user['family_id']) && !empty($member['family_id'])) {
    $userModel->updateFamily($user['id'], $member['family_id']);
    $user['family_id'] = $member['family_id'];
}

// Generate JWT for backend auth
$token = generateJWT([
    "user_id"   => $user['id'],
    "family_id" => $user['family_id'],
    "email"     => $user['email'],
    "role"      => $user['role'] ?? 'member'
]);

// Create Firebase custom token for chat (Firestore)
$auth = firebaseAuth();
$firebaseToken = $auth->createCustomToken((string)$user['id'], [
    'email'     => $user['email'],
    'family_id' => $user['family_id'],
    'role'      => $user['role'] ?? 'member'
])->toString();

response(true, "Google login successful", [
    "token"          => $token,
    "firebase_token" => $firebaseToken,
    "user"           => [
        "id"          => (int)$user['id'],
        "first_name"  => $user['first_name'],
        "last_name"   => $user['last_name'],
        "email"       => $user['email'],
        "family_id"   => $user['family_id'] !== null ? (int)$user['family_id'] : null,
        "role"        => $user['role'] ?? 'member',
        "society_id"  => $user['society_id'] !== null ? (int)$user['society_id'] : null,
        "building"    => $user['building'] ?? null,
        "wing"        => $user['wing'] ?? null,
        "floor"       => $user['floor'] ?? null,
        "unit_number" => $user['unit_number'] ?? null,
    ]
]);

