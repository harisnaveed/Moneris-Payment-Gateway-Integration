<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => 'your_domain', // his code help working with session in iframe too
    'secure' => true,              // HTTPS required
    'httponly' => true,
    'samesite' => 'None'
]);
// ================================================================
//  MONERIS PAYMENT GATEWAY — MASTER CONFIGURATION
// ================================================================
//  ⚡ ONE LINE TO SWITCH ENVIRONMENT:
//     'qa'   → Sandbox  (testing, no real money)
//     'prod' → Production (LIVE, real transactions)
// ================================================================

define('MONERIS_ENV', 'qa'); // ← Change to 'prod' to go live and 'qa' for sandbox


// ================================================================
//  CREDENTIALS (auto-selected based on MONERIS_ENV above)
// ================================================================

if (MONERIS_ENV === 'prod') {

    // ============================================================
    //  🔴 PRODUCTION — LIVE CREDENTIALS
    //  Source: https://www3.moneris.com/mpg
    //          Admin → Store Settings → API Token
    //          Admin → Moneris Checkout Config → Checkout ID
    // ============================================================
    define('MONERIS_STORE_ID',    'store_id');
    define('MONERIS_API_TOKEN',   'api_token');
    define('MONERIS_CHECKOUT_ID', 'checkout_id');
    define('CHECKOUT_AMOUNT',     '79.99');         // ← Your real price

} else {

    // ============================================================
    //  🟡 SANDBOX — TESTING CREDENTIALS
    //  Source: https://esqa.moneris.com/mpg
    //          Admin → Moneris Checkout Config → Checkout ID
    // ============================================================
    define('MONERIS_STORE_ID',    'store5');
    define('MONERIS_API_TOKEN',   'yesguy');
    define('MONERIS_CHECKOUT_ID', 'chktPXPX7tore5');
    define('CHECKOUT_AMOUNT',     '79.99');          // ← Must end in .00 for approval in sandbox

}


// ================================================================
//  ENDPOINTS (auto-resolved — DO NOT EDIT)
// ================================================================

define('MONERIS_BASE_URL',
    MONERIS_ENV === 'prod'
        ? 'https://gateway.moneris.com'     // 🔴 LIVE
        : 'https://gatewayt.moneris.com'    // 🟡 SANDBOX
);

define('MONERIS_PRELOAD_URL', MONERIS_BASE_URL . '/chkt/request/request.php');
define('MONERIS_RECEIPT_URL', MONERIS_BASE_URL . '/chkt/request/request.php');
define('MONERIS_JS_URL',      MONERIS_BASE_URL . '/chkt/js/chkt_v1.00.js');


// ================================================================
//  SAFETY GUARD (auto-runs — DO NOT EDIT)
//  Blocks server start if prod is ON but credentials are missing
// ================================================================

if (MONERIS_ENV === 'prod') {
    $missing = [];
    if (empty(MONERIS_STORE_ID)    || MONERIS_STORE_ID    === 'YOUR_LIVE_STORE_ID')    $missing[] = 'MONERIS_STORE_ID';
    if (empty(MONERIS_API_TOKEN)   || MONERIS_API_TOKEN   === 'YOUR_LIVE_API_TOKEN')   $missing[] = 'MONERIS_API_TOKEN';
    if (empty(MONERIS_CHECKOUT_ID) || MONERIS_CHECKOUT_ID === 'YOUR_LIVE_CHECKOUT_ID') $missing[] = 'MONERIS_CHECKOUT_ID';

    // Block sandbox credentials from being used in production
    if (MONERIS_STORE_ID === 'store5' || MONERIS_API_TOKEN === 'yesguy') {
        $missing[] = 'SANDBOX CREDENTIALS IN PRODUCTION MODE';
    }

    if (!empty($missing)) {
        error_log('[Moneris] ⛔ FATAL: Missing or invalid production credentials: ' . implode(', ', $missing));
        http_response_code(500);
        die('<h2 style="color:red;font-family:Arial;padding:30px;">
             ⛔ Payment gateway configuration error.<br>
             <small>Please contact the site administrator.</small>
             </h2>');
    }
}

// ==================================================================
// Click Ship Details
// ==================================================================

//testing
// $freightcom_api_key = "yCvd6AwgKQfKd6WkFBfCP56oiP0FcDJBqxYnqatDH4oMve1h0374uh96PID2Q6Ev";
// $freightcom_base_url = "https://customer-external-api.ssd-test.freightcom.com/";
// $payment_method_id = "NB9yHhspvzcg7JpU3QFQPUy2oL1Uj23B"; // for testing

// production
$freightcom_api_key = "BHDpRDmHLI2GTUkPHlbFEtyQkTbLYSmXuuZhDtt1gveaoPxEkHtcaNBdD72ACJxf";
$freightcom_base_url = "https://external-api.freightcom.com/";
$payment_method_id = "fnn3IvwvM7PrwZRFwmERmrqYmtBlvQpb"; // for live

// destination details
$destination_name = "Fix Moi mtl microsoldering";
$destination_address = "4-5700 rue de cahmbery";
$destination_city = "Brossard";
$destination_state = "QC";
$destination_country = "CA";
$destination_postal_code = "J4Z0N9";
$destination_phone = "4389690305";

// ===================================================================
// Database 
// ===================================================================

$host = "localhost";
$user = "uwtn3bsodpoqs";
$password = "vtazs1sewbba";
$database = "dbgfevitvxajkc";

// Using mysqli
$conn = mysqli_connect($host, $user, $password, $database);
if (mysqli_connect_errno()) {
    echo "Failed to connect to MySQL using mysqli: " . mysqli_connect_error();
    exit();
}

// Using PDO
try {
    $connect = new PDO("mysql:host=$host;dbname=$database", $user, $password);
    // Set PDO to throw exceptions on error
    $connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Failed to connect to MySQL using PDO: " . $e->getMessage();
    exit();
}

$sendingEmail="your_email";
$sendingPassword="email_password";


$adminEmail = "admin_email";
$storeName = 'Business Name';
$storePhone = '+14078800518'; 
$storeAddress = 'address';
$direction = "map link";
$warranty = "30 Days Warranty";