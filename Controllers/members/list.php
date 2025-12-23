<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../models/FamilyMember.php";

$family_id = $_GET['family_id'] ?? null;

if (!$family_id) {
    response(false, "Family ID is required");
}

$member = new FamilyMember($pdo);
$list = $member->listByFamily($family_id);

response(true, "Family members fetched successfully", $list);
