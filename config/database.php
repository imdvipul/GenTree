<?php
$rawHost = getenv('DB_HOST') ?: "127.0.0.1";
$db      = getenv('DB_DATABASE') ?: "family_db";
$user    = getenv('DB_USERNAME') ?: "root";
$pass    = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : "";
$port    = getenv('DB_PORT') ?: null;

// If host contains port (e.g. mysql-xxx.aivencloud.com:12345), parse it
if (str_contains($rawHost, ':')) {
    [$host, $extractedPort] = explode(':', $rawHost, 2);
    if (!$port) {
        $port = $extractedPort;
    }
} else {
    $host = $rawHost;
}

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
if ($port) {
    $dsn .= ";port=$port";
}

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
];

// Determine if SSL is required (Aiven, cloud databases, or explicit DB_SSL=true)
$dbSsl = getenv('DB_SSL');
$isCloudHost = str_contains($host, 'aivencloud.com') || str_contains($host, 'render.com') || ($port && $port != 3306);
$useSsl = ($dbSsl !== false && filter_var($dbSsl, FILTER_VALIDATE_BOOLEAN)) || ($dbSsl === null && $isCloudHost);

if ($useSsl) {
    // Aiven and cloud databases mandate SSL transport
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    $sslCa = getenv('DB_SSL_CA');
    if ($sslCa && file_exists($sslCa)) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
    } elseif (file_exists('/etc/ssl/certs/ca-certificates.crt')) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
    }
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status"  => false,
        "message" => "Database connection failed",
        "error"   => $e->getMessage()
    ]);
    exit;
}
