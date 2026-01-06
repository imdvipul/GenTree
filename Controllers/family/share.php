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

$input = json_decode(file_get_contents("php://input"), true);

$familyId = $input['family_id'] ?? null;
$email    = trim($input['email'] ?? '');
$role     = $input['role'] ?? 'viewer';

if (!$familyId || !$email) {
    response(false, "family_id and email are required");
}

/* -------------------------------------------------
   Validate role
------------------------------------------------- */
$allowedRoles = ['viewer', 'editor', 'owner'];
if (!in_array($role, $allowedRoles)) {
    response(false, "Invalid role");
}

/* -------------------------------------------------
   1. Verify membership via family_memberships
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT role 
    FROM family_memberships
    WHERE family_id = ? AND user_id = ? AND status = 'active'
");
$stmt->execute([$familyId, $user['id']]);
$membership = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$membership) {
    response(false, "You are not a member of this family");
}

/* Permission check */
if (!in_array($membership['role'], ['owner', 'editor'])) {
    response(false, "You do not have permission to invite members");
}

/* -------------------------------------------------
   2. Load family info
------------------------------------------------- */
$stmt = $pdo->prepare("SELECT id, name FROM families WHERE id = ?");
$stmt->execute([$familyId]);
$family = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$family) {
    response(false, "Family not found");
}

/* -------------------------------------------------
   3. Create invite
------------------------------------------------- */
$token = bin2hex(random_bytes(24));
$expiresAt = date("Y-m-d H:i:s", strtotime("+7 days"));

$stmt = $pdo->prepare("
    INSERT INTO family_invites (family_id, token, role, created_by, expires_at)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([
    $familyId,
    $token,
    $role,
    $user['id'],
    $expiresAt
]);

/* -------------------------------------------------
   4. Notify user if exists
------------------------------------------------- */
$userModel = new User($pdo);
$notificationModel = new Notification($pdo);

$invitedUser = $userModel->findByEmail($email);

if ($invitedUser) {
    $notificationModel->create(
        $invitedUser['id'],
        "Family Invitation",
        "You were invited to join the family \"{$family['name']}\".",
        "family_invite",
        $token
    );
}

/* -------------------------------------------------
   5. Success response
------------------------------------------------- */
response(true, "Invite created successfully", [
    "family" => [
        "id" => $family['id'],
        "name" => $family['name'],
    ],
    "token" => $token,
    "invite_url" => "https://yourapp.com/join/$token",
    "expires_at" => $expiresAt
]);
