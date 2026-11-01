<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/jwt_helper.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();
$userId = (int)$user['id'];

$input = json_decode(file_get_contents("php://input"), true);
$docId        = (int)($input['document_id'] ?? 0);
$paymentStatus = $input['payment_status'] ?? 'paid';

if (!$docId) {
    response(false, "Document ID is required");
}

// Only allow valid values
$allowed = ['paid', 'unpaid', 'partial'];
if (!in_array($paymentStatus, $allowed)) {
    response(false, "Invalid payment status");
}

try {
    // Verify the document belongs to this user and has a bill record
    $check = $pdo->prepare("
        SELECT d.id, b.id AS bill_id
        FROM documents d
        LEFT JOIN document_bills b ON b.document_id = d.id
        WHERE d.id = ? AND d.user_id = ? AND d.isdelete = 0
        LIMIT 1
    ");
    $check->execute([$docId, $userId]);
    $row = $check->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        response(false, "Document not found or access denied");
    }

    if (!$row['bill_id']) {
        response(false, "This document has no bill record to update");
    }

    // Update payment status
    $stmt = $pdo->prepare("
        UPDATE document_bills 
        SET payment_status = ?, updated_at = NOW()
        WHERE document_id = ?
    ");
    $stmt->execute([$paymentStatus, $docId]);

    response(true, "Payment status updated", [
        'document_id'    => $docId,
        'payment_status' => $paymentStatus,
    ]);
} catch (PDOException $e) {
    response(false, "Database error: " . $e->getMessage());
}
