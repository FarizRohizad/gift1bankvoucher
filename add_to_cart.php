<?php
session_start();

header('Content-Type: application/json');

// Check if cart exists in session, if not, initialize it
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$response = ['status' => 'error', 'message' => 'Invalid request.', 'cartTotalQuantity' => 0];

// Calculate current total quantity for the initial response
$currentCartTotalQuantity = 0;
foreach ($_SESSION['cart'] as $item) {
    $currentCartTotalQuantity += $item['quantity'];
}
$response['cartTotalQuantity'] = $currentCartTotalQuantity;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId   = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $itemName = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $itemPoints = filter_input(INPUT_POST, 'points', FILTER_VALIDATE_INT);

    // Ensure user is logged in before adding to cart
    if (!isset($_SESSION['UserID'])) {
        $response['message'] = 'Please log in to add items to your cart.';
        echo json_encode($response);
        exit();
    }

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
        
        // Recalculate total quantity after updating cart
        $newCartTotalQuantity = 0;
        foreach ($_SESSION['cart'] as $item) {
            $newCartTotalQuantity += $item['quantity'];
        }
        $response['cartTotalQuantity'] = $newCartTotalQuantity;

    } else {
        $response['message'] = "Invalid item data received.";
    }
}

echo json_encode($response);
exit();
?>