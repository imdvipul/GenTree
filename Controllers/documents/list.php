<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/jwt_helper.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();
$userId = (int)$user['id'];

// Fetch query filters
$search = $_GET['search'] ?? null;
$category = $_GET['category'] ?? null; // Bills, Warranties, Insurance, Cards, Certificates, Letters, Expiring Soon, All
$status = $_GET['status'] ?? null; // active, expiring, expired

try {
    // 1. Load Category Counts dynamically
    $counts = [
        "All" => 0,
        "Bills" => 0,
        "Warranties" => 0,
        "Insurance" => 0,
        "Cards" => 0,
        "Certificates" => 0,
        "Letters" => 0,
        "Expiring Soon" => 0
    ];

    $countStmt = $pdo->prepare("
        SELECT document_type, status, COUNT(*) as cnt 
        FROM documents 
        WHERE user_id = ? AND isdelete = 0 
        GROUP BY document_type, status
    ");
    $countStmt->execute([$userId]);
    $rows = $countStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $type = $row['document_type'];
        $st = $row['status'];
        $c = (int)$row['cnt'];

        $counts["All"] += $c;

        if ($st == "expiring") {
            $counts["Expiring Soon"] += $c;
        }

        if (strpos(strtolower($type), "bill") !== false) {
            $counts["Bills"] += $c;
        } elseif (strpos(strtolower($type), "warranty") !== false) {
            $counts["Warranties"] += $c;
        } elseif (strpos(strtolower($type), "insurance") !== false) {
            $counts["Insurance"] += $c;
        } elseif (strpos(strtolower($type), "card") !== false || strpos(strtolower($type), "id") !== false) {
            $counts["Cards"] += $c;
        } elseif (strpos(strtolower($type), "certificate") !== false) {
            $counts["Certificates"] += $c;
        } elseif (strpos(strtolower($type), "letter") !== false) {
            $counts["Letters"] += $c;
        }
    }

    // 2. Fetch upcoming unpaid bills amount
    $upcomingStmt = $pdo->prepare("
        SELECT COALESCE(SUM(b.total_amount), 0) as total 
        FROM bills b
        JOIN documents d ON d.id = b.document_id
        WHERE d.user_id = ? AND d.isdelete = 0 AND b.payment_status = 'unpaid'
    ");
    $upcomingStmt->execute([$userId]);
    $upcomingTotal = (float)$upcomingStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // 3. Build documents query list based on filters
    $sql = "
        SELECT d.id, d.document_type, d.title, d.file_path, d.document_date, d.expiry_date, d.status, d.confidence, d.summary, d.created_at,
               b.total_amount, b.due_date, b.payment_status, b.vendor_name,
               w.product_name, w.end_date as warranty_end,
               i.company_name, i.end_date as insurance_end
        FROM documents d
        LEFT JOIN bills b ON b.document_id = d.id
        LEFT JOIN warranties w ON w.document_id = d.id
        LEFT JOIN insurance_policies i ON i.document_id = d.id
        WHERE d.user_id = ? AND d.isdelete = 0
    ";
    
    $params = [$userId];

    // Search filter across titles, OCR text, vendors, amounts
    if ($search) {
        $sql .= " AND (d.title LIKE ? OR d.ocr_text LIKE ? OR d.document_type LIKE ? OR b.vendor_name LIKE ? OR b.bill_number LIKE ? OR w.product_name LIKE ? OR i.company_name LIKE ?)";
        $searchParam = "%$search%";
        array_push($params, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
    }

    // Category filter mapping
    if ($category && $category != "All") {
        if ($category == "Expiring Soon") {
            $sql .= " AND d.status = 'expiring'";
        } elseif ($category == "Bills") {
            $sql .= " AND (d.document_type LIKE '%bill%' OR d.document_type LIKE '%invoice%')";
        } elseif ($category == "Warranties") {
            $sql .= " AND d.document_type LIKE '%warranty%'";
        } elseif ($category == "Insurance") {
            $sql .= " AND d.document_type LIKE '%insurance%'";
        } elseif ($category == "Cards") {
            $sql .= " AND (d.document_type LIKE '%card%' OR d.document_type LIKE '%id%' OR d.document_type LIKE '%licence%')";
        } elseif ($category == "Certificates") {
            $sql .= " AND d.document_type LIKE '%certificate%'";
        } elseif ($category == "Letters") {
            $sql .= " AND d.document_type LIKE '%letter%'";
        }
    }

    // Expiry status filter
    if ($status) {
        $sql .= " AND d.status = ?";
        array_push($params, $status);
    }

    $sql .= " ORDER BY d.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format output fields to match client model
    $formattedDocs = [];
    foreach ($documents as $doc) {
        // Calculate days remaining
        $daysRemaining = null;
        if ($doc['expiry_date']) {
            $today = new DateTime(date("Y-m-d"));
            $exp = new DateTime($doc['expiry_date']);
            $diff = $today->diff($exp);
            $daysRemaining = (int)$diff->format("%r%a");
        }

        $formattedDocs[] = [
            "id" => (int)$doc['id'],
            "document_type" => $doc['document_type'],
            "title" => $doc['title'],
            "file_path" => $doc['file_path'],
            "document_date" => $doc['document_date'],
            "expiry_date" => $doc['expiry_date'],
            "status" => $doc['status'],
            "confidence" => floatval($doc['confidence']),
            "summary" => $doc['summary'],
            "created_at" => $doc['created_at'],
            "days_remaining" => $daysRemaining,
            "bill_details" => $doc['total_amount'] !== null ? [
                "total_amount" => floatval($doc['total_amount']),
                "due_date" => $doc['due_date'],
                "payment_status" => $doc['payment_status'],
                "vendor_name" => $doc['vendor_name']
            ] : null,
            "warranty_details" => $doc['product_name'] !== null ? [
                "product_name" => $doc['product_name'],
                "end_date" => $doc['warranty_end']
            ] : null,
            "insurance_details" => $doc['company_name'] !== null ? [
                "company_name" => $doc['company_name'],
                "end_date" => $doc['insurance_end']
            ] : null
        ];
    }

    response(true, "Documents loaded successfully", [
        "counts" => $counts,
        "total_upcoming_bills_amount" => $upcomingTotal,
        "documents" => $formattedDocs
    ]);

} catch (Exception $e) {
    response(false, "Failed to load documents: " . $e->getMessage());
}
