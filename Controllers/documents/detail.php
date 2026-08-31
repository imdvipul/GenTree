<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/jwt_helper.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();
$userId = (int)$user['id'];

$docId = $_GET['id'] ?? null;

if (!$docId) {
    response(false, "Document ID is required");
}

try {
    // 1. Fetch Document Base details
    $stmt = $pdo->prepare("
        SELECT id, user_id, document_type, title, file_path, mime_type, 
               document_date, expiry_date, status, ocr_text, summary, confidence, created_at
        FROM documents 
        WHERE id = ? AND user_id = ? AND isdelete = 0
        LIMIT 1
    ");
    $stmt->execute([$docId, $userId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        response(false, "Document not found or access denied");
    }

    // 2. Fetch Generic Key-Value Fields
    $fieldsStmt = $pdo->prepare("
        SELECT id, field_name, field_value, field_type, confidence, is_verified 
        FROM document_fields
        WHERE document_id = ?
    ");
    $fieldsStmt->execute([$docId]);
    $fields = $fieldsStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Specific Details based on document type
    $bill = null;
    $items = [];
    $warranty = null;
    $insurance = null;

    $docType = $doc['document_type'];

    // ── BILL DETAILS ──
    if (strpos(strtolower($docType), "bill") !== false || strpos(strtolower($docType), "invoice") !== false) {
        $billStmt = $pdo->prepare("
            SELECT id, vendor_name, bill_number, consumer_number, billing_date, due_date, 
                   subtotal, tax, discount, total_amount, payment_status, currency
            FROM bills
            WHERE document_id = ?
            LIMIT 1
        ");
        $billStmt->execute([$docId]);
        $bill = $billStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bill) {
            $billId = $bill['id'];
            // Fetch bill items
            $itemStmt = $pdo->prepare("
                SELECT id, item_name, quantity, unit_price, discount, tax, total
                FROM bill_items
                WHERE bill_id = ?
            ");
            $itemStmt->execute([$billId]);
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // ── WARRANTY DETAILS ──
    if (strpos(strtolower($docType), "warranty") !== false) {
        $warrantyStmt = $pdo->prepare("
            SELECT id, product_name, model_number, serial_number, purchase_date, 
                   start_date, end_date, seller, manufacturer, status
            FROM warranties
            WHERE document_id = ?
            LIMIT 1
        ");
        $warrantyStmt->execute([$docId]);
        $warranty = $warrantyStmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── INSURANCE DETAILS ──
    if (strpos(strtolower($docType), "insurance") !== false) {
        $insuranceStmt = $pdo->prepare("
            SELECT id, company_name, policy_number, policy_type, start_date, 
                   end_date, premium_amount, sum_insured, renewal_date
            FROM insurance_policies
            WHERE document_id = ?
            LIMIT 1
        ");
        $insuranceStmt->execute([$docId]);
        $insurance = $insuranceStmt->fetch(PDO::FETCH_ASSOC);
    }

    // 4. Fetch Reminders
    $remStmt = $pdo->prepare("
        SELECT id, reminder_type, reminder_date, message, status
        FROM document_reminders
        WHERE document_id = ? AND user_id = ?
    ");
    $remStmt->execute([$docId, $userId]);
    $reminders = $remStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate days remaining
    $daysRemaining = null;
    if ($doc['expiry_date']) {
        $today = new DateTime(date("Y-m-d"));
        $exp = new DateTime($doc['expiry_date']);
        $diff = $today->diff($exp);
        $daysRemaining = (int)$diff->format("%r%a");
    }

    response(true, "Document details loaded successfully", [
        "id" => (int)$doc['id'],
        "document_type" => $doc['document_type'],
        "title" => $doc['title'],
        "file_path" => $doc['file_path'],
        "mime_type" => $doc['mime_type'],
        "document_date" => $doc['document_date'],
        "expiry_date" => $doc['expiry_date'],
        "status" => $doc['status'],
        "ocr_text" => $doc['ocr_text'],
        "summary" => $doc['summary'],
        "confidence" => floatval($doc['confidence']),
        "created_at" => $doc['created_at'],
        "days_remaining" => $daysRemaining,
        "fields" => $fields,
        "bill" => $bill,
        "items" => $items,
        "warranty" => $warranty,
        "insurance" => $insurance,
        "reminders" => $reminders
    ]);

} catch (Exception $e) {
    response(false, "Failed to load document detail: " . $e->getMessage());
}
