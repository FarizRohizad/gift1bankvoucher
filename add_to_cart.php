<?php
session_start();

header('Content-Type: application/json');

// Check if cart exists in session, if not, initialize it
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$response = ['status' => 'error', 'message' => 'Invalid request.', 'cartCount' => count($_SESSION['cart'])];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId   = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $itemName = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $itemPoints = filter_input(INPUT_POST, 'points', FILTER_VALIDATE_INT);

    if ($itemId !== false && $itemId !== null && $itemName && $itemPoints !== false && $itemPoints !== null) {
        // Check if item already exists in cart, increment quantity
        if (isset($_SESSION['cart'][$itemId])) {
            $_SESSION['cart'][$itemId]['quantity']++;
            $response['message'] = htmlspecialchars($itemName) . " quantity updated in cart!";
        } else {
            // Add new item to cart
            $_SESSION['cart'][$itemId] = [
                'id'       => $itemId,
                'name'     => $itemName,
                'points'   => $itemPoints,
                'quantity' => 1
            ];
            $response['message'] = htmlspecialchars($itemName) . " added to cart!";
        }
        $response['status'] = 'success';
        $response['cartCount'] = count($_SESSION['cart']); // Return the number of unique items
    } else {
        $response['message'] = "Invalid item data received.";
    }
}

echo json_encode($response);
exit();
?>
