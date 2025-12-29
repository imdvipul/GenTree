<?php
header("Content-Type: application/json");

// Get requested path
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Remove leading slash
$uri = trim($uri, '/');

// ROUTES
$routes = [

    // AUTH
    'POST auth/login'    => '../Controllers/auth/login.php',
    'POST auth/register' => '../Controllers/auth/register.php',

    // USER
    'GET user/profile'   => '../Controllers/user/profile.php',

    // FAMILY
    'POST family/create' => '../Controllers/family/create.php',
    'POST family/update' => '../Controllers/family/update.php',
    'GET family/list'   => '../Controllers/family/list.php',
    'POST family/delete'   => '../Controllers/family/delete.php',
    'POST family/tree_by_family' => '../Controllers/family/tree_by_family.php',

    // FAMILY MEMBERS
    'POST members/list'  => '../Controllers/members/list.php',
    'POST members/create' => __DIR__ . '/../Controllers/members/create.php',
    'POST members/update' => '../Controllers/members/update.php',
    'POST members/delete' => '../Controllers/members/delete.php',
    'POST members/detail' => '../Controllers/members/detail.php',
    'POST members/relationship_list' => '../Controllers/members/relationship_list.php',

    // THOUGHTFUL
    'POST thoughtful/create' => '../Controllers/thoughtful/create.php',
    'GET thoughtful/list'     => '../Controllers/thoughtful/list.php',
    'POST thoughtful/update'   => '../Controllers/thoughtful/update.php',
    'POST thoughtful/toggle_like'        => '../Controllers/thoughtful/toggle_like.php',
    'POST thoughtful/upload'   => '../Controllers/thoughtful/upload.php',

    // LIFE EVENTS
    'POST events/create' => '../Controllers/events/create.php',
    'POST events/update' => '../Controllers/events/update.php',
    'POST events/delete' => '../Controllers/events/delete.php',
    'POST events/list'   => '../Controllers/events/list.php',
    'POST events/upload_image' => '../Controllers/events/upload_image.php',
];

// Create route key
$routeKey = $method . ' ' . $uri;

if (!isset($routes[$routeKey])) {
    http_response_code(404);
    echo json_encode([
        "status" => false,
        "message" => "API route not found"
    ]);
    exit;
}

// Load matched file
require_once $routes[$routeKey];
