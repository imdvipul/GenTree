<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";

$user = getAuthUser();
$userId = (int)$user['id'];

$data = json_decode(file_get_contents("php://input"), true);

$first = trim($data['first_name'] ?? '');
$last  = trim($data['last_name'] ?? '');
$phone = trim($data['phone_number'] ?? '');
$avatar = $data['avatar'] ?? null;

// Validation
if ($first === '') {
  response(false, "First name is required");
}

$stmt = $pdo->prepare("
  UPDATE users
  SET 
    first_name = ?,
    last_name = ?,
    phone_number = ?,
    avatar = ?
  WHERE id = ? AND isdelete = 0
");

$stmt->execute([$first, $last, $phone, $avatar, $userId]);

response(true, "Profile updated successfully");
