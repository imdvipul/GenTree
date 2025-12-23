<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

try {
    $user = getAuthUser(); // from JWT

    $sql = "
    SELECT 
        id,
        name,
        bio,
        cover_image,
        created_by,
        created_at
    FROM families
    WHERE iddelete = 0
    ORDER BY created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(); // ✅ NO parameters


    $families = $stmt->fetchAll(PDO::FETCH_ASSOC);

    response(true, "Families fetched successfully", $families);

} catch (PDOException $e) {
    response(false, "Failed to fetch families", [
        "error" => $e->getMessage()
    ], 500);
}
