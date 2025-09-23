<?php
session_start();
include('connect.php'); // Your database connection file

header('Content-Type: application/json'); // Indicate that this script returns JSON

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit();
}

$userID = $_SESSION['UserID'];
$voucherId = isset($_POST['voucher_id']) ? intval($_POST['voucher_id']) : 0;

if ($voucherId === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid voucher ID.']);
    exit();
}

// 1. Fetch voucher details (name and cost)
$sql_voucher = "SELECT name, cost FROM Voucher WHERE voucherID = ?";
$stmt_voucher = $conn->prepare($sql_voucher);
if (!$stmt_voucher) {
    error_log("SQL Prepare Error (voucher): " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error preparing voucher query.']);
    exit();
}
$stmt_voucher->bind_param("i", $voucherId);
$stmt_voucher->execute();
$result_voucher = $stmt_voucher->get_result();

if ($result_voucher->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Voucher not found.']);
    exit();
}
$voucher = $result_voucher->fetch_assoc();
$voucherCost = $voucher['cost'];
$voucherName = $voucher['name'];
$stmt_voucher->close();

// 2. Fetch current user points
$sql_user = "SELECT User_Points FROM users WHERE User_ID = ?";
$stmt_user = $conn->prepare($sql_user);
if (!$stmt_user) {
    error_log("SQL Prepare Error (user): " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database error preparing user query.']);
    exit();
}
$stmt_user->bind_param("i", $userID);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    exit();
}
$user = $result_user->fetch_assoc();
$currentUserPoints = $user['User_Points'];
$stmt_user->close();

// 3. Check if user has enough points
if ($currentUserPoints < $voucherCost) {
    echo json_encode(['status' => 'error', 'message' => 'Insufficient points to redeem this voucher. You have ' . number_format($currentUserPoints) . ' points, but this voucher costs ' . number_format($voucherCost) . ' points.']);
    exit();
}

// Start a transaction for atomicity
$conn->begin_transaction();

try {
    // 4. Deduct points from user
    $newPoints = $currentUserPoints - $voucherCost;
    $sql_update_points = "UPDATE users SET User_Points = ? WHERE User_ID = ?";
    $stmt_update_points = $conn->prepare($sql_update_points);
    if (!$stmt_update_points) {
        throw new Exception("Database error preparing points update: " . $conn->error);
    }
    $stmt_update_points->bind_param("ii", $newPoints, $userID);
    $stmt_update_points->execute();

    if ($stmt_update_points->affected_rows === 0) {
        throw new Exception("Failed to update user points. No rows affected.");
    }
    $stmt_update_points->close();

    // 5. (IMPORTANT: In a real system) Record the redemption in a dedicated table.
    // This example *assumes* you will implement a 'user_redeemed_vouchers' table.
    // For now, we'll just indicate successful point deduction.
    // If you don't have this table, you'll need to create it!
    // Example table structure:
    // CREATE TABLE user_redeemed_vouchers (
    //     redemptionID INT AUTO_INCREMENT PRIMARY KEY,
    //     userID INT,
    //     voucherID INT,
    //     redemptionDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    //     uniqueCode VARCHAR(255) UNIQUE,
    //     status VARCHAR(50) DEFAULT 'redeemed', -- e.g., 'redeemed', 'used', 'expired'
    //     FOREIGN KEY (userID) REFERENCES users(User_ID),
    //     FOREIGN KEY (voucherID) REFERENCES Voucher(voucherID)
    // );
    //
    // For now, we're skipping the actual insert here, but this is where it would go:
    // $uniqueRedemptionCode = generateUniqueRedemptionCode(); // Function defined below
    // $sql_record_redemption = "INSERT INTO user_redeemed_vouchers (userID, voucherID, uniqueCode) VALUES (?, ?, ?)";
    // $stmt_record_redemption = $conn->prepare($sql_record_redemption);
    // $stmt_record_redemption->bind_param("iis", $userID, $voucherId, $uniqueRedemptionCode);
    // $stmt_record_redemption->execute();
    // if ($stmt_record_redemption->affected_rows === 0) {
    //     throw new Exception("Failed to record voucher redemption.");
    // }
    // $stmt_record_redemption->close();


    $conn->commit(); // Commit the transaction

    // Update session points to reflect the change
    $_SESSION['UserPoints'] = $newPoints;

    // Return success response with data needed for PDF download
    echo json_encode([
        'status' => 'success',
        'message' => 'Voucher redeemed successfully!',
        'redeemedVoucherId' => $voucherId, // Pass original voucher ID for PDF generation
        'redeemedVoucherName' => $voucherName, // Pass name for display
        'newPoints' => $newPoints // Pass new points balance for client-side update
    ]);

} catch (Exception $e) {
    $conn->rollback(); // Rollback on error
    error_log("Voucher Redemption Error for User ID {$userID}, Voucher ID {$voucherId}: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An error occurred during redemption. Please try again. (' . $e->getMessage() . ')']);
}

$conn->close();

// Helper function for generating a unique code (if you implement user_redeemed_vouchers)
// This is a basic example; for a real system, you might need more robust uniqueness
// and collision checking.
function generateUniqueRedemptionCode() {
    return 'OPT-VCH-' . strtoupper(bin2hex(random_bytes(8))); // 16 character hex code
}
?>
