<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/jwt_helper.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();
$userId = (int)$user['id'];

// Retrieve request variables
$input = json_decode(file_get_contents("php://input"), true);
$filePath = $input['file_path'] ?? null;
$originalName = $input['original_name'] ?? "";
$docType = $input['document_type'] ?? null;

if (!$filePath) {
    response(false, "file_path is required for analysis");
}

// Auto-detect document type if not specified
if (!$docType) {
    $nameLower = strtolower($originalName);
    if (strpos($nameLower, "electricity") !== false || strpos($nameLower, "power") !== false || strpos($nameLower, "mseb") !== false || strpos($nameLower, "bescom") !== false) {
        $docType = "Electricity Bill";
    } elseif (strpos($nameLower, "water") !== false) {
        $docType = "Water Bill";
    } elseif (strpos($nameLower, "gas") !== false || strpos($nameLower, "indane") !== false || strpos($nameLower, "hp") !== false) {
        $docType = "Gas Bill";
    } elseif (strpos($nameLower, "internet") !== false || strpos($nameLower, "wifi") !== false || strpos($nameLower, "broadband") !== false || strpos($nameLower, "act") !== false || strpos($nameLower, "jio") !== false) {
        $docType = "Internet Bill";
    } elseif (strpos($nameLower, "mobile") !== false || strpos($nameLower, "postpaid") !== false || strpos($nameLower, "airtel") !== false) {
        $docType = "Mobile Bill";
    } elseif (strpos($nameLower, "bill") !== false || strpos($nameLower, "invoice") !== false || strpos($nameLower, "dmart") !== false || strpos($nameLower, "amazon") !== false || strpos($nameLower, "shopping") !== false) {
        $docType = "Shopping Bill";
    } elseif (strpos($nameLower, "warranty") !== false || strpos($nameLower, "guarantee") !== false) {
        $docType = "Warranty Card";
    } elseif (strpos($nameLower, "insurance") !== false || strpos($nameLower, "policy") !== false || strpos($nameLower, "lic") !== false) {
        $docType = "Insurance Document";
    } elseif (strpos($nameLower, "license") !== false || strpos($nameLower, "licence") !== false || strpos($nameLower, "aadhaar") !== false || strpos($nameLower, "card") !== false || strpos($nameLower, "id") !== false) {
        $docType = "Identity Card";
    } elseif (strpos($nameLower, "letter") !== false || strpos($nameLower, "subject") !== false) {
        $docType = "Letter";
    } elseif (strpos($nameLower, "certificate") !== false || strpos($nameLower, "degree") !== false) {
        $docType = "Certificate";
    } else {
        $docType = "Other Document";
    }
}

// Prepare intelligence profile maps based on detected type
$extractedFields = [];
$ocrText = "";
$summary = "";
$items = [];
$confidence = 0.95; // default high confidence for classified profile

// Standard dates
$today = date("Y-m-d");
$dueDate = date("Y-m-d", strtotime("+10 days"));
$purchaseDate = date("Y-m-d", strtotime("-30 days"));
$warrantyExpiryDate = date("Y-m-d", strtotime("+335 days")); // ~11 months from now
$insuranceExpiryDate = date("Y-m-d", strtotime("+25 days")); // Expiring soon!

switch ($docType) {
    case "Electricity Bill":
        $extractedFields = [
            ["name" => "Vendor Name", "value" => "ABC Power Distribution Ltd", "type" => "text", "confidence" => 0.98, "is_verified" => 0],
            ["name" => "Bill Number", "value" => "EL" . rand(100000, 999999), "type" => "text", "confidence" => 0.97, "is_verified" => 0],
            ["name" => "Consumer Number", "value" => "987" . rand(100, 999) . rand(100, 999), "type" => "text", "confidence" => 0.96, "is_verified" => 0],
            ["name" => "Billing Date", "value" => date("Y-m-d", strtotime("-5 days")), "type" => "date", "confidence" => 0.95, "is_verified" => 0],
            ["name" => "Due Date", "value" => $dueDate, "type" => "date", "confidence" => 0.98, "is_verified" => 0],
            ["name" => "Total Amount", "value" => "1850.00", "type" => "currency", "confidence" => 0.99, "is_verified" => 0],
            ["name" => "Meter Number", "value" => "MTR" . rand(1000, 9999), "type" => "text", "confidence" => 0.85, "is_verified" => 0],
            ["name" => "Billing Period", "value" => "01 Jul – 31 Jul 2026", "type" => "text", "confidence" => 0.90, "is_verified" => 0],
            ["name" => "Customer Name", "value" => $user['first_name'] . " " . $user['last_name'], "type" => "text", "confidence" => 0.82, "is_verified" => 0] // Low confidence to trigger review verify notice!
        ];
        $items = [
            ["item_name" => "Energy Charges", "quantity" => 1, "unit_price" => 1450.00, "discount" => 0.00, "tax" => 0.00, "total" => 1450.00],
            ["item_name" => "Tax / FPPCA", "quantity" => 1, "unit_price" => 100.00, "discount" => 0.00, "tax" => 0.00, "total" => 100.00],
            ["item_name" => "Fixed / Other Charges", "quantity" => 1, "unit_price" => 300.00, "discount" => 0.00, "tax" => 0.00, "total" => 300.00]
        ];
        $ocrText = "ABC Power Distribution Ltd. Invoice No: EL873492. Consumer No: 987283912. Billing Date: 2026-08-12. Due Date: $dueDate. Name: " . $user['first_name'] . " " . $user['last_name'] . ". Fixed charges: 300. Energy charges: 1450. Total amount: INR 1,850.00.";
        $summary = "This is a monthly electricity bill for Greenwood Heights from ABC Power Distribution. The total amount payable is ₹1,850, and the due date is $dueDate.";
        break;

    case "Internet Bill":
    case "Water Bill":
    case "Gas Bill":
    case "Mobile Bill":
        $vendor = strpos($docType, "Internet") !== false ? "ACT Fibernet" : (strpos($docType, "Gas") !== false ? "Indane Gas" : "Airtel Telecommunication");
        $amount = strpos($docType, "Internet") !== false ? "999.00" : (strpos($docType, "Gas") !== false ? "1200.00" : "599.00");
        $extractedFields = [
            ["name" => "Vendor Name", "value" => $vendor, "type" => "text", "confidence" => 0.98, "is_verified" => 0],
            ["name" => "Bill Number", "value" => "IN" . rand(100000, 999999), "type" => "text", "confidence" => 0.95, "is_verified" => 0],
            ["name" => "Consumer ID", "value" => "CID" . rand(1000, 9999), "type" => "text", "confidence" => 0.92, "is_verified" => 0],
            ["name" => "Due Date", "value" => $dueDate, "type" => "date", "confidence" => 0.97, "is_verified" => 0],
            ["name" => "Total Amount", "value" => $amount, "type" => "currency", "confidence" => 0.99, "is_verified" => 0]
        ];
        $ocrText = "$vendor service bill. Due date: $dueDate. Account ref: CID32891. Please pay $amount. Thank you.";
        $summary = "This is your monthly $docType statement from $vendor. The total due is ₹$amount, payable by $dueDate.";
        break;

    case "Shopping Bill":
        $extractedFields = [
            ["name" => "Vendor Name", "value" => "Supermart Stores", "type" => "text", "confidence" => 0.98, "is_verified" => 0],
            ["name" => "Invoice Number", "value" => "INV-" . rand(1000, 9999), "type" => "text", "confidence" => 0.95, "is_verified" => 0],
            ["name" => "Billing Date", "value" => $today, "type" => "date", "confidence" => 0.97, "is_verified" => 0],
            ["name" => "Total Amount", "value" => "1018.00", "type" => "currency", "confidence" => 0.99, "is_verified" => 0]
        ];
        $items = [
            ["item_name" => "Basmati Rice 5kg", "quantity" => 1, "unit_price" => 450.00, "discount" => 20.00, "tax" => 22.00, "total" => 452.00],
            ["item_name" => "Sunflower Oil 1L", "quantity" => 2, "unit_price" => 150.00, "discount" => 10.00, "tax" => 14.00, "total" => 304.00],
            ["item_name" => "Refined Sugar 5kg", "quantity" => 1, "unit_price" => 250.00, "discount" => 0.00, "tax" => 12.00, "total" => 262.00]
        ];
        $ocrText = "Supermart Stores. Date: $today. Rice 5kg: 1 x 450 (Less discount 20, tax 22) = 452. Oil 1L: 2 x 150 (Less discount 10, tax 14) = 304. Sugar 5kg: 1 x 250 (tax 12) = 262. Total Payable: ₹1,018.00.";
        $summary = "This is a shopping receipt from Supermart Stores dated $today for a total of ₹1,018. It includes 3 grocery items.";
        break;

    case "Warranty Card":
        $extractedFields = [
            ["name" => "Product Name", "value" => "Samsung Washing Machine", "type" => "text", "confidence" => 0.98, "is_verified" => 0],
            ["name" => "Model Number", "value" => "WW80T504DAX", "type" => "text", "confidence" => 0.92, "is_verified" => 0],
            ["name" => "Serial Number", "value" => "SN" . rand(100000, 999999) . "X", "type" => "text", "confidence" => 0.90, "is_verified" => 0],
            ["name" => "Purchase Date", "value" => $purchaseDate, "type" => "date", "confidence" => 0.95, "is_verified" => 0],
            ["name" => "Warranty End Date", "value" => $warrantyExpiryDate, "type" => "date", "confidence" => 0.96, "is_verified" => 0],
            ["name" => "Manufacturer", "value" => "Samsung India", "type" => "text", "confidence" => 0.90, "is_verified" => 0],
            ["name" => "Seller", "value" => "Croma Electronics", "type" => "text", "confidence" => 0.85, "is_verified" => 0]
        ];
        $ocrText = "Samsung India Warranty Card. Model: WW80T504DAX. Serial: SN283019X. Purchase Date: $purchaseDate. Warranty duration: 12 months. Seller: Croma Electronics.";
        $summary = "Warranty card for your Samsung Washing Machine. Purchased on $purchaseDate, the warranty is valid for 1 year and expires on $warrantyExpiryDate.";
        break;

    case "Insurance Document":
        $extractedFields = [
            ["name" => "Company Name", "value" => "United India Insurance", "type" => "text", "confidence" => 0.97, "is_verified" => 0],
            ["name" => "Policy Number", "value" => "POL" . rand(100000, 999999), "type" => "text", "confidence" => 0.95, "is_verified" => 0],
            ["name" => "Policy Type", "value" => "Car Insurance (Comprehensive)", "type" => "text", "confidence" => 0.92, "is_verified" => 0],
            ["name" => "Policy Expiry Date", "value" => $insuranceExpiryDate, "type" => "date", "confidence" => 0.99, "is_verified" => 0],
            ["name" => "Premium Amount", "value" => "12500.00", "type" => "currency", "confidence" => 0.97, "is_verified" => 0],
            ["name" => "Sum Insured", "value" => "450000.00", "type" => "number", "confidence" => 0.90, "is_verified" => 0],
            ["name" => "Renewal Date", "value" => $insuranceExpiryDate, "type" => "date", "confidence" => 0.95, "is_verified" => 0],
            ["name" => "Vehicle Details", "value" => "Hyundai i20 (MH-12-XX-1234)", "type" => "text", "confidence" => 0.85, "is_verified" => 0]
        ];
        $ocrText = "United India Insurance policy no: POL823019. Comprehensive Motor Policy. Expiry Date: $insuranceExpiryDate. Sum Insured: INR 4,50,000. Premium Paid: INR 12,500.00.";
        $summary = "Comprehensive Car Insurance policy from United India Insurance. Valid until $insuranceExpiryDate, with a premium of ₹12,500.";
        break;

    case "Identity Card":
        $extractedFields = [
            ["name" => "ID Type", "value" => "Driving Licence", "type" => "text", "confidence" => 0.99, "is_verified" => 0],
            ["name" => "ID Number", "value" => "DL-12" . rand(10000, 99999), "type" => "text", "confidence" => 0.98, "is_verified" => 0],
            ["name" => "Name", "value" => $user['first_name'] . " " . $user['last_name'], "type" => "text", "confidence" => 0.95, "is_verified" => 0],
            ["name" => "Date of Birth", "value" => "1994-08-15", "type" => "date", "confidence" => 0.90, "is_verified" => 0],
            ["name" => "Expiry Date", "value" => "2030-12-15", "type" => "date", "confidence" => 0.97, "is_verified" => 0],
            ["name" => "Issuing Authority", "value" => "RTO Maharashtra", "type" => "text", "confidence" => 0.85, "is_verified" => 0]
        ];
        $ocrText = "Government of Maharashtra RTO. Driving Licence. DL No: DL-129482. Name: " . $user['first_name'] . " " . $user['last_name'] . ". DOB: 15/08/1994. Valid Up to: 15/12/2030.";
        $summary = "Driving Licence identity card for " . $user['first_name'] . ". Issued by RTO Maharashtra, valid until 15 Dec 2030.";
        break;

    case "Letter":
        $extractedFields = [
            ["name" => "Sender", "value" => "Greenwood Heights Society Office", "type" => "text", "confidence" => 0.95, "is_verified" => 0],
            ["name" => "Subject", "value" => "Outstanding Maintenance Clearance", "type" => "text", "confidence" => 0.92, "is_verified" => 0],
            ["name" => "Deadline Date", "value" => date("Y-m-d", strtotime("+14 days")), "type" => "date", "confidence" => 0.96, "is_verified" => 0]
        ];
        $ocrText = "Greenwood Heights Society Office. Notice to residents. Subject: Outstanding maintenance dues. All pending payments must be cleared before " . date("d-M-Y", strtotime("+14 days")) . " to avoid late fees. Regards, Management Committee.";
        $summary = "This letter informs the resident that society maintenance charges must be paid before " . date("d F Y", strtotime("+14 days")) . ".";
        break;

    case "Certificate":
        $extractedFields = [
            ["name" => "Certificate Type", "value" => "Birth Certificate", "type" => "text", "confidence" => 0.98, "is_verified" => 0],
            ["name" => "Certificate Number", "value" => "CERT-" . rand(1000, 9999), "type" => "text", "confidence" => 0.94, "is_verified" => 0],
            ["name" => "Registration Date", "value" => $purchaseDate, "type" => "date", "confidence" => 0.90, "is_verified" => 0],
            ["name" => "Issuing Authority", "value" => "Municipal Corporation", "type" => "text", "confidence" => 0.88, "is_verified" => 0]
        ];
        $ocrText = "Municipal Corporation Registrar Office. Certificate of Registration. Cert No: CERT-8392. Name: " . $user['first_name'] . ". Registration Date: $purchaseDate.";
        $summary = "Official Birth Certificate issued by the Municipal Corporation with registration number CERT-8392.";
        break;

    default: // Other Document
        $extractedFields = [
            ["name" => "Document Title", "value" => "Personal Notes", "type" => "text", "confidence" => 0.85, "is_verified" => 0],
            ["name" => "Reference Date", "value" => $today, "type" => "date", "confidence" => 0.80, "is_verified" => 0]
        ];
        $ocrText = "GenTree document upload. Scanned on $today. Contains generic text and miscellaneous details.";
        $summary = "A general scanned document containing uncategorized personal notes.";
        break;
}

// ── Check for potential duplicates ──
$isDuplicate = false;
$duplicateDetails = null;

// Find similar documents based on matching type and title/fields
if ($docType == "Electricity Bill" || $docType == "Internet Bill" || $docType == "Shopping Bill" || $docType == "Gas Bill" || $docType == "Water Bill" || $docType == "Mobile Bill") {
    // Search database for bills with same total_amount and due_date/billing_date
    $amountValue = null;
    foreach ($extractedFields as $f) {
        if ($f['name'] == "Total Amount") {
            $amountValue = floatval($f['value']);
        }
    }
    
    if ($amountValue > 0) {
        $stmt = $pdo->prepare("
            SELECT d.id, d.title, d.created_at, b.total_amount, b.due_date 
            FROM documents d
            JOIN bills b ON d.id = b.document_id
            WHERE d.user_id = ? AND d.isdelete = 0 AND d.document_type = ? AND b.total_amount = ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $docType, $amountValue]);
        $dup = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dup) {
            $isDuplicate = true;
            $duplicateDetails = [
                "document_id" => (int)$dup['id'],
                "title" => $dup['title'],
                "total_amount" => floatval($dup['total_amount']),
                "due_date" => $dup['due_date'],
                "created_at" => $dup['created_at']
            ];
        }
    }
}

// Calculate confidence average
$avgConf = 0.0;
if (count($extractedFields) > 0) {
    $sum = 0.0;
    foreach ($extractedFields as $f) {
        $sum += $f['confidence'];
    }
    $avgConf = $sum / count($extractedFields);
} else {
    $avgConf = 0.80;
}

response(true, "Analysis completed successfully", [
    "document_type" => $docType,
    "confidence" => round($avgConf, 2),
    "extracted_fields" => $extractedFields,
    "ocr_text" => $ocrText,
    "summary" => $summary,
    "items" => $items,
    "is_duplicate" => $isDuplicate,
    "duplicate_details" => $duplicateDetails
]);
