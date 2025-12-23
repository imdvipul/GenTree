<?php
require_once __DIR__ . "/jwt_helper.php";
require_once __DIR__ . "/response.php";

function getAuthUser() {
    $headers = [];

    // Apache / Nginx compatibility
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }

    // Fallback for Authorization header
    if (!isset($headers['Authorization'])) {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
    }

    if (!isset($headers['Authorization'])) {
        response(false, "Authorization token missing");
    }

    $token = str_replace("Bearer ", "", $headers['Authorization']);

    try {
        $payload = verifyJWT($token);
    } catch (Exception $e) {
        response(false, "Invalid or expired token");
    }

    return [
        'id'        => $payload['user_id'],
        'family_id'=> $payload['family_id'] ?? null,
        'email'     => $payload['email'] ?? null,
        'role'      => $payload['role'] ?? 'member',
    ];
}
