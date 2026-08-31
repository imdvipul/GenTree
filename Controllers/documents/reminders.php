<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/jwt_helper.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();
$userId = (int)$user['id'];

$input = json_decode(file_get_contents("php://input"), true);
$docId = $input['document_id'] ?? null;
$remType = $input['reminder_type'] ?? 'custom';
$remDate = $input['reminder_date'] ?? null;
$message = $input['message'] ?? 'Document Reminder Alert';

if (!$docId || !$remDate || !$message) {
    response(false, "document_id, reminder_date, and message are required");
}

try {
    // Verify document ownership
    $checkStmt = $pdo->prepare("SELECT id FROM documents WHERE id = ? AND user_id = ? AND isdelete = 0 LIMIT 1");
    $checkStmt->execute([$docId, $userId]);
    if (!$checkStmt->fetch()) {
        response(false, "Document not found or access denied");
    }

    // Insert reminder record
    $stmt = $pdo->prepare("
        INSERT INTO document_reminders (document_id, user_id, reminder_type, reminder_date, message, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$docId, $userId, $remType, $remDate, $message]);

    // Immediately trigger notification if due date is today or past
    if (strtotime($remDate) <= strtotime(date("Y-m-d"))) {
        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, reference_token, is_read)
            VALUES (?, '🔔 Document Reminder', ?, 'document_reminder', ?, 0)
        ");
        $notifStmt->execute([$userId, $message, (string)$docId]);
    }

    response(true, "Reminder configured successfully", [
        "reminder_id" => (int)$pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    response(false, "Failed to configure reminder: " . $e->getMessage());
}
