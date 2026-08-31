<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";
require_once __DIR__ . "/../../helpers/response.php";

$user = getAuthUser();
$userId = $user['id'];

$data = json_decode(file_get_contents("php://input"), true);
$token = $data['fcm_token'] ?? null;

if (!$token) response(false, "Token required");

$stmt = $pdo->prepare("UPDATE users SET fcm_token=? WHERE id=?");
$stmt->execute([$token, $userId]);

response(true, "Token saved");
