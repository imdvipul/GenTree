<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/jwt_helper.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();
$userId = (int)$user['id'];

$input = json_decode(file_get_contents("php://input"), true);
$docId = $input['id'] ?? null;

if (!$docId) {
    response(false, "Document ID is required");
}

try {
    $stmt = $pdo->prepare("
        UPDATE documents 
        SET isdelete = 1 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$docId, $userId]);

    if ($stmt->rowCount() == 0) {
        response(false, "Document not found or access denied");
    }

    // Soft delete associated reminders
    $remStmt = $pdo->prepare("
        DELETE FROM document_reminders 
        WHERE document_id = ? AND user_id = ?
    ");
    $remStmt->execute([$docId, $userId]);

    response(true, "Document deleted successfully");

} catch (Exception $e) {
    response(false, "Failed to delete document: " . $e->getMessage());
}
