<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../models/FamilyMember.php";

$input = json_decode(file_get_contents("php://input"), true);

$id = $input['id'] ?? null;

if (!$id) {
    response(false, "Member ID is required");
}

$member = new FamilyMember($pdo);

$success = $member->update($id, [
    "first_name" => $input['first_name'] ?? '',
    "last_name" => $input['last_name'] ?? null,
    "nickname" => $input['nickname'] ?? null,
    "gender" => $input['gender'] ?? null,
    "birth_date" => $input['birth_date'] ?? null,
    "bio" => $input['bio'] ?? null,
    "avatar" => $input['avatar'] ?? null,
    "is_default_viewpoint" => $input['is_default_viewpoint'] ?? 0,
]);

if (!$success) {
    response(false, "Failed to update family member");
}

response(true, "Family member updated successfully");
