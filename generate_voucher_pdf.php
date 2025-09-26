<?php
session_start();
include('connect.php'); // Your database connection

if (!isset($_SESSION['UserID'])) {
    // If not logged in, redirect or show error
    header("Location: /group1GIFT/landingpage.php");
    exit();
}

// Include the TCPDF library (adjust path if you installed manually)
require_once __DIR__ . '/vendor/autoload.php'; // If using Composer
// OR if manual: require_once __DIR__ . '/lib/tcpdf/tcpdf.php';

// Get the voucher ID from the URL parameter
$voucherId = isset($_GET['voucher_id']) ? intval($_GET['voucher_id']) : 0;
// You might also pass a unique redemption code instead of just voucherID
// e.g., $redemptionCode = isset($_GET['code']) ? $_GET['code'] : '';

if ($voucherId === 0) {
    die("Invalid voucher ID.");
}

// Fetch voucher details from the database
// IMPORTANT: This query needs to link to a table that stores *redeemed* vouchers
// for a specific user, potentially including a unique redemption code and date.
// For simplicity, I'm fetching from the main Voucher table, but in a real system,
// you'd fetch from a 'user_redeemed_vouchers' table.
$sql = "SELECT name, cost, categoryName FROM Voucher WHERE voucherID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $voucherId);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $voucher = $result->fetch_assoc()) {
    $voucherName = htmlspecialchars($voucher['name']);
    $voucherCost = number_format($voucher['cost']);
    $categoryName = htmlspecialchars($voucher['categoryName']);
    $userName = htmlspecialchars($_SESSION['UserName']); // Get user name from session
    $redemptionDate = date("Y-m-d H:i:s");
    // Generate a unique redemption code (in a real scenario, this would be generated
    // and stored during the redemption process)
    $uniqueCode = 'OPT' . $voucherId . '-' . substr(md5(uniqid(rand(), true)), 0, 8);


    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('OptimaBank Loyalty Program');
    $pdf->SetTitle('Voucher Redemption Details');
    $pdf->SetSubject('Your Redeemed Voucher');

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
    $pdf->SetFont('helvetica', '', 12);

    // Add a page
    $pdf->AddPage();

    // Set some content to print
    $html = <<<EOD
    <h1 style="color:#0e499f; text-align:center;">OptimaBank Loyalty Voucher</h1>
    <p style="text-align:center;"><img src="/../img/optima_logo.png" alt="OptimaBank Logo" width="100" height="100"></p>
    <p>&nbsp;</p>
    <table cellspacing="0" cellpadding="5" border="0" style="width:100%;">
        <tr>
            <td style="width:30%; font-weight:bold; color:#555;">Voucher Name:</td>
            <td style="width:70%; border-bottom:1px solid #eee;">{$voucherName}</td>
        </tr>
        <tr>
            <td style="width:30%; font-weight:bold; color:#555;">Category:</td>
            <td style="width:70%; border-bottom:1px solid #eee;">{$categoryName}</td>
        </tr>
        <tr>
            <td style="width:30%; font-weight:bold; color:#555;">Redeemed By:</td>
            <td style="width:70%; border-bottom:1px solid #eee;">{$userName}</td>
        </tr>
        <tr>
            <td style="width:30%; font-weight:bold; color:#555;">Redemption Date:</td>
            <td style="width:70%; border-bottom:1px solid #eee;">{$redemptionDate}</td>
        </tr>
        <tr>
            <td style="width:30%; font-weight:bold; color:#555;">Points Spent:</td>
            <td style="width:70%; border-bottom:1px solid #eee;">{$voucherCost} Points</td>
        </tr>
        <tr>
            <td style="width:30%; font-weight:bold; color:#555;">Unique Code:</td>
            <td style="width:70%; border-bottom:1px solid #eee; font-size:14pt; font-weight:bold; color:#0e499f;">{$uniqueCode}</td>
        </tr>
    </table>
    <p>&nbsp;</p>
    <p style="font-size:10pt; color:#777;"><strong>Instructions:</strong> Present this voucher PDF, along with your OptimaBank ID, at the participating merchant to redeem your offer. Terms and conditions apply. Voucher is valid for 30 days from the redemption date.</p>
    <p style="font-size:10pt; text-align:center; color:#999;">Thank you for being a valued OptimaBank customer!</p>
EOD;

    // Print text
    $pdf->writeHTML($html, true, false, true, false, '');

    // Close and output PDF document
    $pdf->Output("OptimaBank_Voucher_{$voucherId}.pdf", 'D'); // 'D' for download

} else {
    die("Voucher not found or an error occurred.");
}

$conn->close();
?>
