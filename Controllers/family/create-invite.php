<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

require_once __DIR__ . "/../../models/User.php";
require_once __DIR__ . "/../../models/Notification.php";

$user = getAuthUser();

if (!$user || !$user['id']) {
    response(false, "Unauthorized", null, 401);
}

if (!$user['family_id']) {
    response(false, "You are not part of any family");
}

$input = json_decode(file_get_contents("php://input"), true);

$role = $input['role'] ?? 'viewer';
$email = trim($input['email'] ?? '');

if (!$email) {
    response(false, "Invite email is required");
}

$allowedRoles = ['viewer', 'member', 'admin'];
if (!in_array($role, $allowedRoles)) {
    response(false, "Invalid role");
}

// Generate token
$token = bin2hex(random_bytes(24));
$expiresAt = date("Y-m-d H:i:s", strtotime("+7 days"));

// Save invite
$stmt = $pdo->prepare("
    INSERT INTO family_invites (family_id, token, role, created_by, expires_at)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->execute([
    $user['family_id'],
    $token,
    $role,
    $user['id'],
    $expiresAt
]);

// 🔔 NOTIFICATION LOGIC
$userModel = new User($pdo);
$notificationModel = new Notification($pdo);

// check if user already exists
$invitedUser = $userModel->findByEmail($email);

if ($invitedUser) {
    $notificationModel->create(
        $invitedUser['id'],
        "Family Invitation",
        "You have been invited to join a family. Open the app to accept.",
        "family_invite",
        $token
    );
}

$inviteUrl = "https://yourapp.com/join/$token";

response(true, "Invite created successfully", [
    "token" => $token,
    "invite_url" => $inviteUrl,
    "expires_at" => $expiresAt
]);
