<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";

$input = json_decode(file_get_contents("php://input"), true);

$email = trim($input['email'] ?? '');
$otp = trim($input['otp'] ?? '');

if (!$email || !$otp) response(false, "Missing data");

$stmt = $pdo->prepare("SELECT * FROM email_otps WHERE email = ? AND otp = ? AND expires_at > NOW()");
$stmt->execute([$email, $otp]);
$valid = $stmt->fetch();

if (!$valid) {
    response(false, "Invalid or expired OTP");
}

// Delete OTP after use
$pdo->prepare("DELETE FROM email_otps WHERE email = ?")->execute([$email]);

response(true, "OTP verified");
