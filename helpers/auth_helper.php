
<?php
// require_once __DIR__ . "/jwt_helper.php";
$jwtHelper = __DIR__ . "/jwt_helper.php";
if (!file_exists($jwtHelper)) {
    response(false, "JWT helper missing", null, 500);
    exit;
}
require_once $jwtHelper;

require_once __DIR__ . "/response.php";
require_once __DIR__ . "/../config/database.php";

function getAuthUser()
{
    $authHeader = null;

    // 1️⃣ Standard header
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    // 2️⃣ FastCGI / Apache fallback
    elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    // 3️⃣ getallheaders fallback
    elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        }
    }

    if (!$authHeader) {
        response(false, "Authorization token missing", null, 401);
    }

    $token = str_replace("Bearer ", "", $authHeader);

    $payload = verifyJWT($token);
    if (!$payload || !isset($payload['user_id'])) {
        response(false, "Invalid or expired token", null, 401);
    }

    global $pdo;

    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email, role, family_id
        FROM users
        WHERE id = ? AND isdelete = 0
        LIMIT 1
    ");
    $stmt->execute([$payload['user_id']]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        response(false, "User not found", null, 401);
    }

    return [
        'id'         => (int)$user['id'],
        'first_name' => $user['first_name'],
        'last_name'  => $user['last_name'],
        'email'      => $user['email'],
        'family_id'  => $user['family_id'],
        'role'       => $user['role'],
    ];
}







// require_once __DIR__ . "/jwt_helper.php";
// require_once __DIR__ . "/response.php";
// require_once __DIR__ . "/../config/database.php";

// function getAuthUser()
// {
//     $headers = [];

//     if (function_exists('getallheaders')) {
//         $headers = getallheaders();
//     }

//     if (!isset($headers['Authorization'])) {
//         if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
//             $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
//         } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
//             $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
//         }
//     }

//     if (!isset($headers['Authorization'])) {
//         response(false, "Authorization token missing", null, 401);
//     }

//     $token = str_replace("Bearer ", "", $headers['Authorization']);

//     try {
//         $payload = verifyJWT($token);
//     } catch (Exception $e) {
//         response(false, "Invalid or expired token", null, 401);
//     }

//     // 🔹 Load full user from DB
//     global $pdo;

//     $stmt = $pdo->prepare("
//         SELECT 
//             id,
//             first_name,
//             last_name,
//             email,
//             role,
//             family_id
//         FROM users
//         WHERE id = ? AND isdelete = 0
//         LIMIT 1
//     ");

//     $stmt->execute([$payload['user_id']]);
//     $user = $stmt->fetch(PDO::FETCH_ASSOC);

//     if (!$user) {
//         response(false, "User not found", null, 401);
//     }

//     return [
//         'id'         => (int)$user['id'],
//         'first_name' => $user['first_name'],
//         'last_name'  => $user['last_name'],
//         'email'      => $user['email'],
//         'family_id'  => $user['family_id'],
//         'role'       => $user['role'],
//     ];
// }



