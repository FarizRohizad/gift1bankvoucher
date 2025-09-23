<?php
session_start();
include('connect.php'); // Your database connection

if (!isset($_SESSION['UserID'])) {
    header("Location: /../landingpage.php");
    exit();
}

// Include the TCPDF library (adjust path if you installed manually)
require_once __DIR__ . '/vendor/autoload.php'; // If using Composer
// OR if manual: require_once __DIR__ . '/lib/tcpdf/tcpdf.php';

$userID = $_SESSION['UserID'];
$userName = isset($_SESSION['UserName']) ? htmlspecialchars($_SESSION['UserName']) : 'N/A';
$transactionDate = date("Y-m-d H:i:s"); // Overall transaction date

// Get the list of history IDs for the last checkout from session
$historyIds = isset($_SESSION['last_checkout_history_ids']) ? $_SESSION['last_checkout_history_ids'] : [];

if (empty($historyIds)) {
    die("No recent checkout items found for receipt generation.");
}

$placeholders = implode(',', array_fill(0, count($historyIds), '?'));

// Fetch all items from the History table for the given history IDs
$sql = "SELECT h.historyID, h.voucherID, h.quantity, h.pointCost, v.name AS voucherName, v.categoryName 
        FROM History h
        JOIN Voucher v ON h.voucherID = v.voucherID
        WHERE h.historyID IN ({$placeholders}) AND h.userId = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Failed to prepare statement: " . $conn->error);
}

// Bind parameters dynamically
$types = str_repeat('i', count($historyIds)) . 'i'; // All history IDs are int, plus the userID
$params = array_merge($historyIds, [$userID]);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$redeemedItems = [];
$totalPointsSpent = 0;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $redeemedItems[] = $row;
        $totalPointsSpent += $row['pointCost'];
    }
} else {
    die("Failed to fetch redeemed items: " . $conn->error);
}

if (empty($redeemedItems)) {
    die("No items found for the given history IDs.");
}

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('OptimaBank Loyalty Program');
$pdf->SetTitle('Voucher Redemption Receipt');
$pdf->SetSubject('Your Redeemed Vouchers');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Set font
$pdf->SetFont('helvetica', '', 10);

// Add a page
$pdf->AddPage();

// Generate a unique transaction ID for the entire receipt
$transactionCode = 'TRX' . date("Ymd") . '-' . substr(md5(uniqid(rand(), true)), 0, 10);

$html = <<<EOD
<h1 style="color:#0e499f; text-align:center;">OptimaBank Loyalty - Redemption Receipt</h1>
<p style="text-align:center;"><img src="/../img/optima_logo.png" alt="OptimaBank Logo" width="100" height="100"></p>
<p>&nbsp;</p>
<table cellspacing="0" cellpadding="5" border="0" style="width:100%;">
    <tr>
        <td style="width:30%; font-weight:bold; color:#555;">Transaction ID:</td>
        <td style="width:70%;"><strong>{$transactionCode}</strong></td>
    </tr>
    <tr>
        <td style="width:30%; font-weight:bold; color:#555;">Redeemed By:</td>
        <td style="width:70%;">{$userName}</td>
    </tr>
    <tr>
        <td style="width:30%; font-weight:bold; color:#555;">Redemption Date:</td>
        <td style="width:70%;">{$transactionDate}</td>
    </tr>
</table>
<p>&nbsp;</p>
<h2 style="color:#31c6f6; text-align:center;">Items Redeemed</h2>
<table cellspacing="0" cellpadding="5" border="1" style="width:100%; border-collapse: collapse;">
    <tr style="background-color:#f0f7ff;">
        <th style="width:35%; font-weight:bold; text-align:left; border:1px solid #ccc;">Voucher Name</th>
        <th style="width:15%; font-weight:bold; text-align:center; border:1px solid #ccc;">Category</th>
        <th style="width:10%; font-weight:bold; text-align:center; border:1px solid #ccc;">Qty</th>
        <th style="width:20%; font-weight:bold; text-align:right; border:1px solid #ccc;">Points/Item</th>
        <th style="width:20%; font-weight:bold; text-align:right; border:1px solid #ccc;">Total Points</th>
    </tr>
EOD;

foreach ($redeemedItems as $item) {
    $voucherName = htmlspecialchars($item['voucherName']);
    $categoryName = htmlspecialchars($item['categoryName']);
    $quantity = $item['quantity'];
    $pointCostPerItem = number_format($item['pointCost'] / $item['quantity']); // Calculate per item cost
    $itemTotalCost = number_format($item['pointCost']);

    $html .= <<<EOD
    <tr>
        <td style="width:35%; border:1px solid #eee;">{$voucherName}</td>
        <td style="width:15%; text-align:center; border:1px solid #eee;">{$categoryName}</td>
        <td style="width:10%; text-align:center; border:1px solid #eee;">{$quantity}</td>
        <td style="width:20%; text-align:right; border:1px solid #eee;">{$pointCostPerItem}</td>
        <td style="width:20%; text-align:right; border:1px solid #eee;">{$itemTotalCost}</td>
    </tr>
EOD;
}

$html .= <<<EOD
    <tr style="background-color:#e0f2f7;">
        <td colspan="4" style="text-align:right; font-weight:bold; border:1px solid #ccc;">GRAND TOTAL POINTS:</td>
        <td style="text-align:right; font-weight:bold; font-size:12pt; color:#0e499f; border:1px solid #ccc;">
            <strong>{$totalPointsSpent} Points</strong>
        </td>
    </tr>
</table>
<p>&nbsp;</p>
<h2 style="color:#31c6f6; text-align:center;">Voucher Redemption Codes</h2>
<p style="font-size:10pt; color:#777;">
    Please present the unique code for each voucher at the participating merchant to redeem your offer. 
    Terms and conditions apply. Each voucher is valid for 30 days from the transaction date.
</p>
<table cellspacing="0" cellpadding="5" border="1" style="width:100%; border-collapse: collapse;">
    <tr style="background-color:#f0f7ff;">
        <th style="width:50%; font-weight:bold; text-align:left; border:1px solid #ccc;">Voucher Item</th>
        <th style="width:50%; font-weight:bold; text-align:center; border:1px solid #ccc;">Unique Redemption Code</th>
    </tr>
EOD;

// List each unique code
foreach ($redeemedItems as $item) {
    for ($i = 0; $i < $item['quantity']; $i++) {
        // Generate a unique redemption code for each instance of the voucher
        // In a real system, these would be generated and stored at checkout time.
        // For demonstration, we generate them here.
        $uniqueItemCode = 'VCH-' . $item['historyID'] . '-' . $i . '-' . substr(md5(uniqid(rand(), true)), 0, 6);
        $html .= <<<EOD
        <tr>
            <td style="width:50%; border:1px solid #eee;">{$item['voucherName']} (Qty: 1)</td>
            <td style="width:50%; text-align:center; font-weight:bold; color:#0e499f; border:1px solid #eee;">{$uniqueItemCode}</td>
        </tr>
EOD;
    }
}

$html .= <<<EOD
</table>
<p>&nbsp;</p>
<p style="font-size:10pt; text-align:center; color:#999;">Thank you for being a valued OptimaBank customer!</p>
EOD;

// Print text
$pdf->writeHTML($html, true, false, true, false, '');

// Clear history IDs from session after generating PDF
unset($_SESSION['last_checkout_history_ids']);

// Close and output PDF document
$pdf->Output("OptimaBank_Receipt_{$transactionCode}.pdf", 'D'); // 'D' for download

$conn->close();
?>
