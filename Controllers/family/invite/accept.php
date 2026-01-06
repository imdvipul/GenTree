<?php
$user = getAuthUser();
$token = $_POST['token'] ?? '';

$stmt = $pdo->prepare("
SELECT * FROM family_invites
WHERE token = ? AND (expires_at IS NULL OR expires_at > NOW())
");
$stmt->execute([$token]);

$invite = $stmt->fetch();
if (!$invite) response(false, "Invalid invite");

$familyId = $invite['family_id'];

/* add membership */
$stmt = $pdo->prepare("
INSERT IGNORE INTO family_memberships (family_id, user_id, role)
VALUES (?, ?, ?)
");
$stmt->execute([$familyId, $user['id'], $invite['role']]);

/* auto match user → member */
$stmt = $pdo->prepare("
SELECT id FROM family_members
WHERE family_id = ?
  AND first_name = ?
LIMIT 1
");
$stmt->execute([$familyId, $user['name']]);
$member = $stmt->fetch();

if ($member) {
    $pdo->prepare("
        INSERT IGNORE INTO user_member_links (user_id, family_id, member_id)
        VALUES (?, ?, ?)
    ")->execute([$user['id'], $familyId, $member['id']]);
}

/* mark invite used */
$pdo->prepare("
UPDATE family_invites SET used_by=?, used_at=NOW()
WHERE id=?
")->execute([$user['id'], $invite['id']]);

response(true, "Joined family successfully");
