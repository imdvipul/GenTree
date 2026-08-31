<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/jwt_helper.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();
$userId = (int)$user['id'];

$input = json_decode(file_get_contents("php://input"), true);

$filePath = $input['file_path'] ?? null;
$docType = $input['document_type'] ?? 'Other Document';
$title = $input['title'] ?? 'New Document';
$mimeType = $input['mime_type'] ?? null;
$docDate = $input['document_date'] ?? null;
$expiryDate = $input['expiry_date'] ?? null;
$ocrText = $input['ocr_text'] ?? null;
$summary = $input['summary'] ?? null;
$confidence = floatval($input['confidence'] ?? 1.0);

$fields = $input['fields'] ?? [];
$billData = $input['bill'] ?? null;
$items = $input['items'] ?? [];
$warrantyData = $input['warranty'] ?? null;
$insuranceData = $input['insurance'] ?? null;
$reminders = $input['reminders'] ?? [];

if (!$filePath) {
    response(false, "file_path is required to save document");
}

try {
    $pdo->beginTransaction();

    // 1. Calculate validity status based on expiry dates
    $status = 'none';
    if ($expiryDate) {
        $today = new DateTime(date("Y-m-d"));
        $exp = new DateTime($expiryDate);
        $diff = $today->diff($exp);
        $days = (int)$diff->format("%r%a");

        if ($days < 0) {
            $status = 'expired';
        } elseif ($days <= 30) {
            $status = 'expiring';
        } else {
            $status = 'active';
        }
    }

    // 2. Insert Base Document
    $stmt = $pdo->prepare("
        INSERT INTO documents (
            user_id, document_type, title, file_path, mime_type, 
            document_date, expiry_date, status, ocr_text, summary, confidence
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId, $docType, $title, $filePath, $mimeType,
        $docDate, $expiryDate, $status, $ocrText, $summary, $confidence
    ]);
    
    $documentId = $pdo->lastInsertId();

    // 3. Insert Generic Fields
    if (count($fields) > 0) {
        $fieldStmt = $pdo->prepare("
            INSERT INTO document_fields (
                document_id, field_name, field_value, field_type, confidence, is_verified
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($fields as $f) {
            $fieldName = $f['name'] ?? '';
            $fieldVal = $f['value'] ?? '';
            $fieldType = $f['type'] ?? 'text';
            $fieldConf = floatval($f['confidence'] ?? 1.0);
            $fieldVer = intval($f['is_verified'] ?? 0);
            
            if (!empty($fieldName)) {
                $fieldStmt->execute([$documentId, $fieldName, $fieldVal, $fieldType, $fieldConf, $fieldVer]);
            }
        }
    }

    // 4. Insert Document Specific Data
    // ── BILLS ──
    if (($docType == "Electricity Bill" || $docType == "Internet Bill" || $docType == "Shopping Bill" || $docType == "Gas Bill" || $docType == "Water Bill" || $docType == "Mobile Bill" || strpos(strtolower($docType), "bill") !== false) && $billData) {
        $billStmt = $pdo->prepare("
            INSERT INTO bills (
                document_id, vendor_name, bill_number, consumer_number, 
                billing_date, due_date, subtotal, tax, discount, total_amount, payment_status, currency
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $billStmt->execute([
            $documentId,
            $billData['vendor_name'] ?? '',
            $billData['bill_number'] ?? null,
            $billData['consumer_number'] ?? null,
            $billData['billing_date'] ?? null,
            $billData['due_date'] ?? null,
            floatval($billData['subtotal'] ?? 0.00),
            floatval($billData['tax'] ?? 0.00),
            floatval($billData['discount'] ?? 0.00),
            floatval($billData['total_amount'] ?? 0.00),
            $billData['payment_status'] ?? 'unpaid',
            $billData['currency'] ?? 'INR'
        ]);
        
        $billId = $pdo->lastInsertId();

        // Insert individual shopping items if present
        if (count($items) > 0) {
            $itemStmt = $pdo->prepare("
                INSERT INTO bill_items (
                    bill_id, item_name, quantity, unit_price, discount, tax, total
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($items as $item) {
                $itemStmt->execute([
                    $billId,
                    $item['item_name'] ?? 'Item',
                    intval($item['quantity'] ?? 1),
                    floatval($item['unit_price'] ?? 0.00),
                    floatval($item['discount'] ?? 0.00),
                    floatval($item['tax'] ?? 0.00),
                    floatval($item['total'] ?? 0.00)
                ]);
            }
        }
    }

    // ── WARRANTY CARD ──
    if (($docType == "Warranty Card" || strpos(strtolower($docType), "warranty") !== false) && $warrantyData) {
        $warStatus = 'active';
        if ($expiryDate && strtotime($expiryDate) < time()) {
            $warStatus = 'expired';
        }
        
        $warrantyStmt = $pdo->prepare("
            INSERT INTO warranties (
                document_id, product_name, model_number, serial_number, 
                purchase_date, start_date, end_date, seller, manufacturer, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $warrantyStmt->execute([
            $documentId,
            $warrantyData['product_name'] ?? 'Product',
            $warrantyData['model_number'] ?? null,
            $warrantyData['serial_number'] ?? null,
            $warrantyData['purchase_date'] ?? null,
            $warrantyData['start_date'] ?? null,
            $warrantyData['end_date'] ?? null,
            $warrantyData['seller'] ?? null,
            $warrantyData['manufacturer'] ?? null,
            $warStatus
        ]);
    }

    // ── INSURANCE DOCUMENT ──
    if (($docType == "Insurance Document" || strpos(strtolower($docType), "insurance") !== false) && $insuranceData) {
        $insuranceStmt = $pdo->prepare("
            INSERT INTO insurance_policies (
                document_id, company_name, policy_number, policy_type, 
                start_date, end_date, premium_amount, sum_insured, renewal_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insuranceStmt->execute([
            $documentId,
            $insuranceData['company_name'] ?? 'Insurance Company',
            $insuranceData['policy_number'] ?? '',
            $insuranceData['policy_type'] ?? null,
            $insuranceData['start_date'] ?? null,
            $insuranceData['end_date'] ?? null,
            floatval($insuranceData['premium_amount'] ?? 0.00),
            floatval($insuranceData['sum_insured'] ?? 0.00),
            $insuranceData['renewal_date'] ?? null
        ]);
    }

    // 5. Insert Reminders & Sync Notifications
    if (count($reminders) > 0) {
        $remStmt = $pdo->prepare("
            INSERT INTO document_reminders (
                document_id, user_id, reminder_type, reminder_date, message, status
            ) VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        
        $notifyStmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id, title, message, type, reference_token, is_read
            ) VALUES (?, ?, ?, 'document_reminder', ?, 0)
        ");

        foreach ($reminders as $r) {
            $remDate = $r['reminder_date'] ?? '';
            $remType = $r['reminder_type'] ?? 'due_date';
            $msg = $r['message'] ?? 'Document Reminder';
            
            if (!empty($remDate)) {
                // Save reminder mapping
                $remStmt->execute([$documentId, $userId, $remType, $remDate, $msg]);
                
                // If reminder is for today or past, immediately fire notification, else queue it
                if (strtotime($remDate) <= strtotime(date("Y-m-d"))) {
                    $titlePrefix = "🔔 ";
                    if ($docType == "Warranty Card") $titlePrefix = "🛡 Warranty Expiry: ";
                    if ($docType == "Insurance Document") $titlePrefix = "🚗 Insurance Renewal: ";
                    if (strpos(strtolower($docType), "bill") !== false) $titlePrefix = "💰 Bill Due Soon: ";
                    
                    $notifyStmt->execute([
                        $userId,
                        $titlePrefix . $title,
                        $msg,
                        (string)$documentId
                    ]);
                }
            }
        }
    }

    $pdo->commit();
    response(true, "Document saved successfully", [
        "document_id" => (int)$documentId,
        "status" => $status
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    response(false, "Database saving failed: " . $e->getMessage());
}
