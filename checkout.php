<?php
require_once 'config.php';
require_once 'helper.php';
session_start();


// ============================================================
// STEP 1 — Collect Form Data
// ============================================================
$full_name = htmlspecialchars(trim($_POST['full_name'] ?? ''));
$email     = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone     = htmlspecialchars(trim($_POST['phone'] ?? ''));

$address   = htmlspecialchars(trim($_POST['address'] ?? ''));
$apartment = htmlspecialchars(trim($_POST['apartment'] ?? ''));
$city      = htmlspecialchars(trim($_POST['city'] ?? ''));
$state     = htmlspecialchars(trim($_POST['state'] ?? ''));
$zip       = htmlspecialchars(trim($_POST['zip'] ?? ''));

$device_model    = htmlspecialchars(trim($_POST['device_model'] ?? ''));
$issue           = htmlspecialchars(trim($_POST['issue'] ?? ''));
$message         = htmlspecialchars(trim($_POST['message'] ?? ''));
$device_password = htmlspecialchars(trim($_POST['device_password'] ?? ''));
$total_amount    = htmlspecialchars(trim($_POST['total_amount'] ?? ''));
$shipping_method = htmlspecialchars(trim($_POST['shipping_method'] ?? ''));
$language        = htmlspecialchars(trim($_POST['language'] ?? ''));

if (empty($full_name) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('<p style="color:red;">❌ Invalid input. <a href="index.php">Go back</a></p>');
}

$order_id = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

$_SESSION['form_data'] = [
    'full_name'       => $full_name,
    'email'           => $email,
    'phone'           => $phone,
    'address'         => $address,
    'apartment'       => $apartment,
    'city'            => $city,
    'state'           => $state,
    'zip'             => $zip,
    'device_model'    => $device_model,
    'issue'           => $issue,
    'message'         => $message,
    'device_password' => $device_password,
    'total_amount'    => $total_amount,
    'shipping_method' => $shipping_method,
    'language'        => $language,
];

$_SESSION['order_id'] = $order_id;

// ============================================================
// STEP 2 — Preload Payload
// ============================================================

$shippingMethod = $_SESSION['form_data']['shipping_method'] ?? '';

$paymentReceived = CHECKOUT_AMOUNT;
switch ($shippingMethod) {
    case 'Faster Shipping':
        $paymentReceived = CHECKOUT_AMOUNT + 20.00;
        break;

    default:
        $paymentReceived = CHECKOUT_AMOUNT;
        break;
}



// $paymentReceiveds = number_format($paymentReceived , 2);
$paymentFloat = round((float)$paymentReceived, 2);
$paymentFormatted = number_format($paymentFloat, 2, '.', '');

$preload_payload = [
    'store_id'    => MONERIS_STORE_ID,
    'api_token'   => MONERIS_API_TOKEN,
    'checkout_id' => MONERIS_CHECKOUT_ID,
    'txn_total'   => $paymentFormatted,
    'environment' => MONERIS_ENV,
    'action'      => 'preload',
    'order_no'    => $order_id,
];

// ============================================================
// STEP 3 — Send Preload via cURL
// ============================================================
$ch = curl_init(MONERIS_PRELOAD_URL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($preload_payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);

$raw  = curl_exec($ch);
$err  = curl_error($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    die('<p style="color:red;">cURL Error: ' . $err . '</p>');
}

$resp = json_decode($raw, true);

// ============================================================
// STEP 4 — Check Response & Extract Ticket
// ============================================================
if (
    empty($resp['response']['success']) ||
    strtolower((string)$resp['response']['success']) !== 'true' ||
    empty($resp['response']['ticket'])
) {
    $field   = $resp['response']['error']['field']   ?? ($resp['field']   ?? 'unknown');
    $message = $resp['response']['error']['message'] ?? ($resp['message'] ?? $raw);

    die('
    <div style="font-family:Arial;padding:30px;max-width:600px;">
        <h2 style="color:#c00;">❌ Moneris Preload Failed</h2>
        <table border="1" cellpadding="8" style="border-collapse:collapse;width:100%">
            <tr><td><b>HTTP Code</b></td><td>' . $http . '</td></tr>
            <tr><td><b>Error Field</b></td><td>' . htmlspecialchars($field) . '</td></tr>
            <tr><td><b>Error Message</b></td><td>' . htmlspecialchars($message) . '</td></tr>
            <tr><td><b>Full Response</b></td><td><pre>' . htmlspecialchars($raw) . '</pre></td></tr>
            <tr><td><b>Payload Sent</b></td><td><pre>' . htmlspecialchars(json_encode($preload_payload, JSON_PRETTY_PRINT)) . '</pre></td></tr>
        </table>
        <br><a href="index.php">← Go Back</a>
    </div>');
}

$ticket = $resp['response']['ticket'];
$_SESSION['moneris_ticket'] = $ticket;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure Payment</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f0f2f5; padding:30px 20px; }
        .wrapper { max-width:580px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 4px 20px rgba(0,0,0,.1); overflow:hidden; }
        .header  { background:#0066cc; color:#fff; padding:20px 28px; }
        .header h2 { font-size:20px; }
        .order-info { padding:14px 28px; background:#f8fbff; border-bottom:1px solid #e8f0fe; display:flex; justify-content:space-between; font-size:14px; color:#555; }
        #monerisCheckout { padding:10px; min-height:450px; }
        .footer-note { text-align:center; padding:14px; font-size:12px; color:#aaa; border-top:1px solid #f0f0f0; }
         #loader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.92);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }

    .loader-box {
        background: #fff;
        padding: 40px 35px;
        border-radius: 20px;
        box-shadow: 0 10px 35px rgba(0,0,0,0.12);
        text-align: center;
        max-width: 420px;
        width: 90%;
        animation: fadeIn 0.4s ease;
    }

    .spinner {
        width: 65px;
        height: 65px;
        margin: 0 auto 25px;
        border: 6px solid #F1F1F1;
        border-top: 6px solid #F76222;
        border-radius: 50%;
        animation: spin 0.9s linear infinite;
    }

    .loader-title {
        margin: 0 0 12px;
        font-size: 24px;
        font-weight: 700;
        color: #222;
        font-family: Arial, sans-serif;
    }

    .loader-text {
        margin: 0;
        font-size: 16px;
        line-height: 1.7;
        color: #666;
        font-weight: 400;
        font-family: Arial, sans-serif;
    }

    @keyframes spin {
        100% {
            transform: rotate(360deg);
        }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<div class="wrapper">
 <!-- Loader -->
    <div id="loader" style="display: none;">
        <div class="loader-box">
            <!-- Spinner -->
            <div class="spinner"></div>
            <!-- Heading -->
            <?php
            // for english language
            if($language == "auto"){
            ?>
                <h2 class="loader-title">
                    Generating Shipping Label
                </h2>

                <!-- Message -->
                <p class="loader-text">
                    We are currently preparing your shipping label.<br>
                    Please wait a few moments while the process completes securely.
                </p>
            <?php
            }
            // for french language
            else{
            ?>
                <h2 class="loader-title">
                    Générer une étiquette d'expédition
                </h2>

                <!-- Message -->
                <p class="loader-text">
                    Nous préparons actuellement votre étiquette d'expédition.<br>
                    Veuillez patienter quelques instants pendant que le processus se termine en toute sécurité.
                </p>
            <?php    
            }
            ?>
        </div>
    </div>
</div>
    <div class="header">
        <h2>🔒 Secure Payment</h2>
        <p>Encrypted and secured by Moneris</p>
    </div>
    <div class="order-info">
        <span>👤 <strong><?= htmlspecialchars($full_name) ?></strong></span>
        <span>📋 <?= htmlspecialchars($order_id) ?></span>
        <span>💰 <strong>$<?= CHECKOUT_AMOUNT ?> CAD</strong></span>
    </div>

    <div id="monerisCheckout">
        <p style="text-align:center;padding:40px;color:#888;">Loading payment form...</p>
    </div>
    <div class="footer-note">🛡️ PCI DSS Compliant | Secured by Moneris</div>
</div>

<script src="<?= MONERIS_JS_URL ?>"></script>
<script>
(function() {
    var TICKET = "<?= htmlspecialchars($ticket, ENT_QUOTES) ?>";
    var ENV    = "<?= MONERIS_ENV ?>";

    var mco = new monerisCheckout();
    mco.setMode(ENV);
    mco.setCheckoutDiv("monerisCheckout");

    mco.setCallback("page_loaded", function(r){
        console.log('loaded', r);
    });

    mco.setCallback("cancel_transaction", function(r){
        window.location.href = 'cancelled.php';
    });

    mco.setCallback("error_event", function(r){
        console.error('error', r);
    });

    mco.setCallback("payment_receipt", function(r){
        submitToVerify(TICKET);
    });

    mco.setCallback("payment_complete", function(r){
        submitToVerify(TICKET);
    });

    mco.startCheckout(TICKET);

    function showLoader() {
        var loader = document.getElementById('loader');
        loader.style.display = 'flex';
    }

    function submitToVerify(ticket) {
        showLoader(); // 👈 loader show hoga

        setTimeout(function() { // thoda delay taake loader visible ho
            var f = document.createElement('form');
            f.method = 'POST';
            f.action = 'verify.php';

            var i = document.createElement('input');
            i.type = 'hidden';
            i.name = 'ticket';
            i.value = ticket;

            f.appendChild(i);
            document.body.appendChild(f);
            f.submit();
        }, 300); // 300ms delay
    }
})();
</script>
</body>
</html>