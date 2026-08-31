<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";

$input = json_decode(file_get_contents("php://input"), true);
$email = trim($input['email'] ?? '');

if (!$email) response(false, "Email required");

// Generate OTP
$otp = rand(100000, 999999);
$expires = date("Y-m-d H:i:s", strtotime("+5 minutes"));

// Delete old OTP
$pdo->prepare("DELETE FROM email_otps WHERE email = ?")->execute([$email]);

// Save OTP
$stmt = $pdo->prepare("INSERT INTO email_otps (email, otp, expires_at) VALUES (?, ?, ?)");
$stmt->execute([$email, $otp, $expires]);

// Send mail
$subject = "Your OTP Code";
$message = "Your OTP is: $otp\nValid for 5 minutes.";
$headers = "From: GenTree pakusbot@gmail.com";

mail($email, $subject, $message, $headers);

response(true, "OTP sent");
