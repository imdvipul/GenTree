<?php
ob_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

set_exception_handler(function (\Throwable $e) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(500);
    header("Content-Type: application/json");
    echo json_encode([
        "status"  => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
    exit;
});

header("Content-Type: application/json");
date_default_timezone_set('Asia/Kolkata');

// Get requested path
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Remove leading slash
$uri = trim($uri, '/');

// Root & Health Check Endpoint
if ($uri === '' || $uri === 'health') {
    echo json_encode([
        'status' => true,
        'message' => 'GenTree Family API is online!',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// ROUTES
$routes = [

    // AUTH
    'POST auth/login'    => '../Controllers/auth/login.php',
    'POST auth/register' => '../Controllers/auth/register.php',
    'POST auth/google'   => '../Controllers/auth/google.php',
    'POST auth/phone'    => '../Controllers/auth/phone.php',

    // USER
    'GET user/profile'   => '../Controllers/user/profile.php',
    'POST user/save_fcm'        => '../Controllers/user/save_fcm.php',
    'POST user/update_profile'  => '../Controllers/user/update_profile.php',

    // FAMILY
    'POST family/create' => '../Controllers/family/create.php',
    'POST family/update' => '../Controllers/family/update.php',
    'GET family/list'   => '../Controllers/family/list.php',
    'POST family/delete'   => '../Controllers/family/delete.php',
    'POST family/tree_by_family' => '../Controllers/family/tree_by_family.php',
    'POST family/share' => '../Controllers/family/share.php',
    'POST family/accept-invite' => '../Controllers/family/accept-invite.php',
    'POST family/create-invite' => '../Controllers/family/create-invite.php',

    // FAMILY MEMBERS
    'POST members/list'  => '../Controllers/members/list.php',
    'POST members/create' => __DIR__ . '/../Controllers/members/create.php',
    'POST members/update' => '../Controllers/members/update.php',
    'POST members/delete' => '../Controllers/members/delete.php',
    'POST members/detail' => '../Controllers/members/detail.php',
    'POST members/relationship_list' => '../Controllers/members/relationship_list.php',
    'GET members/listforfamilycreate' => '../Controllers/members/listforfamilycreate.php',
    'POST members/addtofamily' => '../Controllers/members/addtofamily.php',
    'POST members/removefromfamily' => '../Controllers/members/removefromfamily.php',
    'POST members/upload-avatar' => '../Controllers/members/upload-avatar.php',

    // RELATIONSHIPS
    'POST relationships/create' => '../Controllers/relationships/create.php',
    'POST relationships/delete' => '../Controllers/relationships/delete.php',
    'POST relationships/list'   => '../Controllers/relationships/list.php',

    // THOUGHTFUL (HOME SCREEN DAILY CAROUSEL)
    'GET thoughtful/daily'   => '../Controllers/thoughtful/daily.php',
    'POST thoughtful/create' => '../Controllers/thoughtful/create.php',
    'POST thoughtful/like'   => '../Controllers/thoughtful/like.php',

    // LIFE EVENTS (BIRTHDAYS, ANNIVERSARIES)
    'POST events/create'       => '../Controllers/events/create.php',
    'POST events/list'         => '../Controllers/events/list.php',
    'POST events/detail'       => '../Controllers/events/detail.php',
    'POST events/update'       => '../Controllers/events/update.php',
    'POST events/delete'       => '../Controllers/events/delete.php',
    'POST events/upload_image' => '../Controllers/events/upload_image.php',

    // NOTIFICATION
    'GET notifications/list'        => '../Controllers/notifications/list.php',
    'POST notifications/mark-read'  => '../Controllers/notifications/mark-read.php',

    // Chat
    'GET Chat/get_contacts'   => '../Controllers/Chat/get_contacts.php',
    'POST Chat/create_or_get' => '../Controllers/Chat/create_or_get.php',
    'GET Chat/list'           => '../Controllers/Chat/list.php',

    // SOCIETY notice board and suvichar
    'GET society/suvichar'       => '../Controllers/society/daily_suvichar.php',
    'GET society/notices'        => '../Controllers/society/notices_list.php',
    'GET society/notices/detail' => '../Controllers/society/notice_detail.php',

    // SMART DOCUMENT INTELLIGENCE
    'POST documents/upload'    => '../Controllers/documents/upload.php',
    'POST documents/analyze'   => '../Controllers/documents/analyze.php',
    'POST documents/save'      => '../Controllers/documents/save.php',
    'GET documents/list'       => '../Controllers/documents/list.php',
    'GET documents/detail'     => '../Controllers/documents/detail.php',
    'POST documents/delete'    => '../Controllers/documents/delete.php',
    'POST documents/reminder'  => '../Controllers/documents/reminders.php',
    'POST documents/mark-paid' => '../Controllers/documents/mark-paid.php',
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
$targetPath = $routes[$routeKey];
if (!str_starts_with($targetPath, '/')) {
    $targetPath = __DIR__ . '/' . $targetPath;
}
require_once $targetPath;
