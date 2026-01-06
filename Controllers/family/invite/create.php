<?php
$token = bin2hex(random_bytes(24));

$stmt = $pdo->prepare("
INSERT INTO family_invites (family_id, token, role, created_by)
VALUES (?, ?, ?, ?)
");

$stmt->execute([
  $familyId,
  $token,
  $_POST['role'] ?? 'viewer',
  $user['id']
]);

response(true, "Invite created", [
  "invite_url" => "https://yourapp.com/join/$token",
  "token" => $token
]);
