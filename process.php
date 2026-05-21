<?php
session_start();

$order_id = htmlspecialchars($_GET['order_id'] ?? 'N/A');
$txn_no   = htmlspecialchars($_GET['txn']      ?? 'N/A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Successful</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 60px auto; text-align: center; }
        .success-box { background: #e8f5e9; border: 2px solid #4caf50; border-radius: 8px; padding: 30px; }
        h2 { color: #2e7d32; }
    </style>
</head>
<body>
    <div class="success-box">
        <h2>✅ Payment Successful!</h2>
        <p>Thank you! Your registration is confirmed.</p>
        <hr>
        <p><strong>Order ID:</strong> <?= $order_id ?></p>
        <p><strong>Transaction #:</strong> <?= $txn_no ?></p>
        <p>A confirmation email will be sent shortly.</p>
    </div>
<script src="height-responsive.js"></script>
</body>
</html>