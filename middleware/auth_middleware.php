<?php

require_once __DIR__ . "/../helpers/response.php";
require_once __DIR__ . "/../helpers/jwt_helper.php";

$headers = getallheaders();

if (!isset($headers['Authorization'])) {
    response(false, "Authorization token missing");
}

$authHeader = $headers['Authorization'];
$token = str_replace("Bearer ", "", $authHeader);

$decoded = verifyJWT($token);

if (!$decoded) {
    response(false, "Invalid or expired token");
}

// Make user data available
$GLOBALS['auth_user'] = $decoded;
