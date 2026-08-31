<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";

$user = getAuthUser();
$userId = (int)$user['id'];

$stmt = $pdo->prepare("
  SELECT 
    id,
    first_name,
    last_name,
    email,
    phone_number,
    avatar,
    role,
    provider
  FROM users
  WHERE id = ? AND isdelete = 0
  LIMIT 1
");

$stmt->execute([$userId]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
  response(false, "User not found");
}

response(true, "Profile loaded", $data);
