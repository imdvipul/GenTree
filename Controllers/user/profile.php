<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../middleware/auth_middleware.php";

response(true, "Protected profile data", [
    "user" => $GLOBALS['auth_user']
]);
