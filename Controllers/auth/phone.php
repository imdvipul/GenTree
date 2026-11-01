<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/firebase.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/jwt_helper.php";
require_once __DIR__ . "/../../models/User.php";
require_once __DIR__ . "/../../models/FamilyMember.php";

$input = json_decode(file_get_contents("php://input"), true);
$idToken = trim($input['id_token'] ?? '');
$passedPhone = trim($input['phone_number'] ?? '');

$phone = $passedPhone;
$firebaseUid = '';

// If idToken is provided, verify or extract claims
if ($idToken) {
    try {
        $auth = firebaseAuth();
        if ($auth) {
            $verifiedIdToken = $auth->verifyIdToken($idToken);
            $tokenPhone = $verifiedIdToken->claims()->get('phone_number');
            $uid = $verifiedIdToken->claims()->get('sub');
            if ($tokenPhone) $phone = $tokenPhone;
            if ($uid) $firebaseUid = $uid;
        }
    } catch (\Throwable $e) {
        error_log("Firebase verifyIdToken error: " . $e->getMessage());
    }

    // Fallback: decode JWT payload
    if (!$phone || !$firebaseUid) {
        $parts = explode('.', $idToken);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            if (!empty($payload['phone_number'])) {
                $phone = $payload['phone_number'];
            }
            if (!empty($payload['sub'])) {
                $firebaseUid = $payload['sub'];
            }
        }
    }
}

if (!$phone) {
    response(false, "Phone number is required");
}

// Clean phone digits for matching
$cleanDigits = preg_replace('/[^\d]/', '', $phone);
$last10 = substr($cleanDigits, -10);

$userModel = new User($pdo);
$memberModel = new FamilyMember($pdo);

// Find existing user by exact phone, cleaned digits, or last 10 digits
$stmt = $pdo->prepare("
    SELECT * FROM users 
    WHERE phone_number = ? 
       OR REPLACE(REPLACE(phone_number, '+', ''), ' ', '') = ?
       OR RIGHT(REPLACE(REPLACE(phone_number, '+', ''), ' ', ''), 10) = ?
    LIMIT 1
");
$stmt->execute([$phone, $cleanDigits, $last10]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Check if phone matches any family member in family_members
    $memStmt = $pdo->prepare("
        SELECT * FROM family_members
        WHERE phone_number = ?
           OR RIGHT(REPLACE(REPLACE(phone_number, '+', ''), ' ', ''), 10) = ?
        LIMIT 1
    ");
    $memStmt->execute([$phone, $last10]);
    $matchedMember = $memStmt->fetch(PDO::FETCH_ASSOC);

    $firstName = $matchedMember ? ($matchedMember['first_name'] ?: 'Family') : 'User';
    $lastName  = $matchedMember ? ($matchedMember['last_name'] ?: 'Member') : substr($last10, -4);
    $familyId  = $matchedMember ? ($matchedMember['family_id'] ?? null) : null;

    $passwordPlaceholder = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
    $dummyEmail = $cleanDigits . '@phone.gentree.app';

    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO users (
            first_name, last_name, email, password, phone_number,
            role, family_id, provider, provider_id
        ) VALUES (
            ?, ?, ?, ?, ?, 'member', ?, 'phone', ?
        )
    ");
    $stmt->execute([
        $firstName,
        $lastName,
        $dummyEmail,
        $passwordPlaceholder,
        $phone,
        $familyId,
        $firebaseUid
    ]);

    $userId = (int)$pdo->lastInsertId();
    $user = [
        'id'           => $userId,
        'first_name'   => $firstName,
        'last_name'    => $lastName,
        'email'        => $dummyEmail,
        'phone_number' => $phone,
        'family_id'    => $familyId,
        'role'         => 'member',
        'society_id'   => null,
        'building'     => null,
        'wing'         => null,
        'floor'        => null,
        'unit_number'  => null,
    ];

    // If matched member, link in user_member_links
    if ($matchedMember) {
        $linkStmt = $pdo->prepare("
            INSERT IGNORE INTO user_member_links (user_id, member_id, family_id)
            VALUES (?, ?, ?)
        ");
        $linkStmt->execute([$userId, $matchedMember['id'], $matchedMember['family_id'] ?? 1]);
    }
} else {
    // Update phone/provider if not set
    if ($user['provider'] !== 'phone' && empty($user['provider_id'])) {
        $pdo->prepare("UPDATE users SET provider = 'phone', provider_id = ? WHERE id = ?")
            ->execute([$firebaseUid, $user['id']]);
    }
}

// Auto-link member: if member exists and user.family_id is empty
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

// Firebase custom token
$firebaseToken = null;
try {
    $auth = firebaseAuth();
    if ($auth) {
        $firebaseToken = $auth->createCustomToken((string)$user['id'], [
            'email'     => $user['email'],
            'family_id' => $user['family_id'],
            'role'      => $user['role'] ?? 'member'
        ])->toString();
    }
} catch (\Throwable $e) {
    error_log("Firebase phone token creation failed: " . $e->getMessage());
}

response(true, "Phone login successful", [
    "token"          => $token,
    "firebase_token" => $firebaseToken,
    "user"           => [
        "id"          => (int)$user['id'],
        "first_name"  => $user['first_name'],
        "last_name"   => $user['last_name'],
        "email"       => $user['email'],
        "phone_number"=> $user['phone_number'],
        "family_id"   => $user['family_id'] !== null ? (int)$user['family_id'] : null,
        "role"        => $user['role'] ?? 'member',
        "society_id"  => $user['society_id'] !== null ? (int)$user['society_id'] : null,
        "building"    => $user['building'] ?? null,
        "wing"        => $user['wing'] ?? null,
        "floor"       => $user['floor'] ?? null,
        "unit_number" => $user['unit_number'] ?? null,
    ]
]);
