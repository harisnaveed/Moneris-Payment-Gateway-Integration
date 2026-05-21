<?php
session_start();
header('Expires: Thu, 01-Jan-70 00:00:01 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
require_once 'config.php';
require_once 'helper.php';
require '../assets1/vendor/autoload.php';
require '../assets1/PHPMailer/src/Exception.php';
require '../assets1/PHPMailer/src/PHPMailer.php';
require '../assets1/PHPMailer/src/SMTP.php';

use Twilio\Rest\Client;
use Twilio\Exceptions\RestException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ================================================================
//  SMTP CONFIG — move these to config.php / .env in production
// ================================================================
$smtpEmail    = "info@boostmyrepair.com";
$smtpPassword = "qrwryrgcvyjfiens";
$smtpHost     = "smtp.gmail.com";
$smtpPort     = 465;


// ================================================================
//  LOGGING HELPER
// ================================================================
function moneris_log(string $type, array $data): void
{
    $log_dir = __DIR__ . '/logs';

    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $filename = $type === 'success'
        ? $log_dir . '/payment-success-log.txt'
        : $log_dir . '/payment-failed-log.txt';

    $entry  = str_repeat('=', 80) . PHP_EOL;
    $entry .= '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($type) . '] [ENV: ' . strtoupper(MONERIS_ENV) . ']' . PHP_EOL;
    $entry .= str_repeat('-', 80) . PHP_EOL;
    $entry .= print_r($data, true);
    $entry .= PHP_EOL;

    file_put_contents($filename, $entry, FILE_APPEND | LOCK_EX);
}


// ================================================================
//  FIELD RESOLVER — defined at top level (not inside if block)
// ================================================================
function moneris_field(string $key, array $cc, array $receipt, array $response_body, string $default = 'N/A'): string {
    return (string)($cc[$key] ?? $receipt[$key] ?? $response_body[$key] ?? $default);
}


// ================================================================
//  DATABASE SAVE — defined at top level
// ================================================================
function save_data(
    $connect,
    $name,
    $email,
    $phone,
    $apartment,
    $city,
    $state,
    $zip,
    $device_models,
    $problem,
    $clientNote,
    $device_password,
    $orderId,
    $paidAmount,
    $transaction_id,
    $shipping_method,
    $label_pdf,
    $clickship_tran_id,
    $request_id,
    $service_id,
    $shipment_id,
    $shipment_status,
    $retry,
    $lang
) {
    $date = date("Y-m-d H:i:s");

    try {
        $sql = "INSERT INTO mail_in 
            (customer_name, customer_email, customer_phone, apartment, city, state, zip,
             device_model, issues, message, device_password, order_id, paid_amount, date,
             transaction_id, shipping_method, label_pdf, clickship_tran_id,
             request_id, service_id, shipment_id, shipment_status, retry,lang)
            VALUES 
            (:customer_name, :customer_email, :customer_phone, :apartment, :city, :state, :zip,
             :device_model, :issues, :message, :device_password, :order_id, :paid_amount, :date,
             :transaction_id, :shipping_method, :label_pdf, :clickship_tran_id,
             :request_id, :service_id, :shipment_id, :shipment_status, :retry,:lang)";

        $stmt = $connect->prepare($sql);

        $stmt->bindParam(':customer_name',    $name);
        $stmt->bindParam(':customer_email',   $email);
        $stmt->bindParam(':customer_phone',   $phone);
        $stmt->bindParam(':apartment',        $apartment);
        $stmt->bindParam(':city',             $city);
        $stmt->bindParam(':state',            $state);
        $stmt->bindParam(':zip',              $zip);
        $stmt->bindParam(':device_model',     $device_models);
        $stmt->bindParam(':issues',           $problem);
        $stmt->bindParam(':message',          $clientNote);
        $stmt->bindParam(':device_password',  $device_password);
        $stmt->bindParam(':order_id',         $orderId);
        $stmt->bindParam(':paid_amount',      $paidAmount);
        $stmt->bindParam(':date',             $date);
        $stmt->bindParam(':transaction_id',   $transaction_id);
        $stmt->bindParam(':shipping_method',  $shipping_method);
        $stmt->bindParam(':label_pdf',        $label_pdf);
        $stmt->bindParam(':clickship_tran_id',$clickship_tran_id);
        $stmt->bindParam(':request_id',       $request_id);
        $stmt->bindParam(':service_id',       $service_id);
        $stmt->bindParam(':shipment_id',      $shipment_id);
        $stmt->bindParam(':shipment_status',  $shipment_status);
        $stmt->bindParam(':retry',            $retry);
        $stmt->bindParam(':lang',             $lang);

        return $stmt->execute();

    } catch (Exception $e) {
        error_log("DB Error: " . $e->getMessage());
        return false;
    }
}


// ================================================================
//  FILE DOWNLOAD & LABEL HELPERS — defined at top level
// ================================================================
function download_file(string $url): string|false
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $content  = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $content !== false) {
        return $content;
    }

    error_log("Failed to download file: $url (HTTP $httpCode)");
    return false;
}

function convert_zpl_to_pdf(string $zplUrl): string|false
{
    $zplContent = download_file($zplUrl);
    if ($zplContent === false) {
        return false;
    }

    $apiUrl = 'http://api.labelary.com/v1/printers/8dpmm/labels/4x6/0/';

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $zplContent);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/pdf',
        'Content-Type: application/x-www-form-urlencoded',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $pdfContent = curl_exec($ch);
    $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $pdfContent !== false) {
        return $pdfContent;
    }

    error_log("ZPL to PDF conversion failed (HTTP $httpCode)");
    return false;
}

function download_and_convert_to_pdf(string $url): string|false
{
    $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

    if ($extension === 'pdf') {
        return download_file($url);
    } elseif ($extension === 'zpl') {
        return convert_zpl_to_pdf($url);
    }

    error_log("Unsupported label file type: $extension");
    return false;
}


// ================================================================
//  SMTP MAILER — defined at top level
// ================================================================
function smtp_mailer(string $to, string $subject, string $msg, ?string $label_pdf = null): bool
{
    global $smtpEmail, $smtpPassword, $smtpHost, $smtpPort;

    $mail    = new PHPMailer(true);
    $tmpFile = null;

    try {
        $mail->isSMTP();
        $mail->SMTPAuth   = true;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Host       = $smtpHost;
        $mail->Port       = $smtpPort;
        $mail->Username   = $smtpEmail;
        $mail->Password   = $smtpPassword;
        $mail->setFrom($smtpEmail, 'Business Name');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $msg;

        if (!empty($label_pdf)) {
            $pdfContent = download_and_convert_to_pdf($label_pdf);

            if ($pdfContent !== false) {
                $tmpFile = tempnam(sys_get_temp_dir(), 'label_') . '.pdf';
                file_put_contents($tmpFile, $pdfContent);
                $mail->addAttachment($tmpFile, 'shipping-label.pdf');
            }
        }

        $mail->send();

        if ($tmpFile && file_exists($tmpFile)) {
            @unlink($tmpFile);
        }

        return true;

    } catch (Exception $e) {
        if ($tmpFile && file_exists($tmpFile)) {
            @unlink($tmpFile);
        }
        error_log("Email Error: " . $mail->ErrorInfo);
        return false;
    }
}


// ================================================================
//  1. Validate Incoming Ticket
// ================================================================
$ticket = trim($_POST['ticket'] ?? '');

if (
    empty($ticket) ||
    !isset($_SESSION['moneris_ticket']) ||
    !hash_equals($_SESSION['moneris_ticket'], $ticket)
) {
    http_response_code(403);
    die('<p style="color:red;font-family:Arial;padding:20px;">
         ❌ Invalid or expired session. <a href="index.php">Start over</a>.
         </p>');
}


// ================================================================
//  2. Build Receipt Verification Payload
// ================================================================
$receipt_payload = [
    'store_id'    => MONERIS_STORE_ID,
    'api_token'   => MONERIS_API_TOKEN,
    'checkout_id' => MONERIS_CHECKOUT_ID,
    'ticket'      => $ticket,
    'environment' => MONERIS_ENV,
    'action'      => 'receipt',
];


// ================================================================
//  3. Send Receipt Request via cURL
// ================================================================
$ch = curl_init(MONERIS_RECEIPT_URL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($receipt_payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);

$raw = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    moneris_log('failed', [
        'error_type' => 'CURL_ERROR',
        'curl_error' => $err,
        'ticket'     => $ticket,
        'timestamp'  => date('Y-m-d H:i:s'),
    ]);
    die('<p style="color:red;font-family:Arial;padding:20px;">
         ❌ Could not verify payment. Contact support.
         </p>');
}


// ================================================================
//  4. Parse Response
// ================================================================
$resp = json_decode($raw, true);

$response_body = $resp['response']                              ?? [];
$success_flag  = strtolower((string)($response_body['success'] ?? 'false'));
$result        = strtolower((string)($response_body['result']  ?? ''));

$receipt       = $response_body['receipt']                      ?? [];
$cc            = $receipt['cc']                                 ?? [];

$txn_no        = moneris_field('transaction_no',    $cc, $receipt, $response_body);
$resp_code     = moneris_field('response_code',     $cc, $receipt, $response_body, '999');
$iso_code      = moneris_field('iso_response_code', $cc, $receipt, $response_body);
$approval_code = moneris_field('approval_code',     $cc, $receipt, $response_body);
$amount_paid   = moneris_field('amount',            $cc, $receipt, $response_body);
$card_type     = moneris_field('card_type',         $cc, $receipt, $response_body);
$message       = moneris_field('message',           $cc, $receipt, $response_body);
$card_mask     = moneris_field('first6last4',       $cc, $receipt, $response_body);


// ================================================================
//  5. Approval Logic
// ================================================================
$resp_code_int = (int) ltrim((string)$resp_code, '0') ?: 0;
$code_approved = ($resp_code !== '999' && is_numeric($resp_code) && $resp_code_int >= 0 && $resp_code_int <= 49);

$approved = (
    $result === 'a'
    || ($success_flag === 'true' && $code_approved)
    || ($result === '' && $code_approved && $success_flag === 'true')
);


// ================================================================
//  6. Handle Result
// ================================================================
$form_data = $_SESSION['form_data'] ?? [];
$order_id  = $_SESSION['order_id']  ?? 'N/A';

// FIX 1: Resolve language early from $form_data so it is always defined.
$language = $form_data['language'] ?? 'en';

if ($approved) {

    // ============================================================
    //  ✅ PAYMENT APPROVED — Write Success Log
    // ============================================================
    moneris_log('success', [
        'environment' => strtoupper(MONERIS_ENV),
        'timestamp'   => date('Y-m-d H:i:s'),
        'order_id'    => $order_id,
        'customer'    => [
            'full_name' => $form_data['full_name'] ?? 'N/A',
            'email'     => $form_data['email']     ?? 'N/A',
            'phone'     => $form_data['phone']     ?? 'N/A',
        ],
        'transaction' => [
            'txn_no'        => $txn_no,
            'amount'        => $amount_paid,
            'card_type'     => $card_type,
            'card_mask'     => $card_mask,
            'approval_code' => $approval_code,
            'response_code' => $resp_code,
            'iso_code'      => $iso_code,
            'message'       => $message,
            'result'        => $result,
            'success'       => $success_flag,
        ],
        'moneris_raw_response' => $resp,
    ]);


    // ============================================================
    //  ClickShip Integration
    // ============================================================
    $unique_id      = $order_id;
    $service_pickup = $form_data['shipping_method'];

    $detail = get_detail($_SESSION['form_data']);

    $request_id        = '';
    $service_id        = '';
    $shipment_ids      = '';
    $shipments_detail  = '';
    $shipments_status  = '';
    $retryCount        = 0;
    $retryCountService = 0;
    $maxRetries        = 10;
    $service           = [];
    $service_status    = 0;
    $shipment_status   = 0;
    $shipments         = [];

    // FIX 2: $payment_method_id was never defined. Declare it before use.
    // Set to null (or pull from config/session if your ClickShip account requires a specific ID).
    $payment_method_id = $payment_method_id ?? null;

    // Request rates
    $request        = get_freightcom_rates($detail);
    $request_id     = $request['request_id'];
    $request_status = $request['status'];

    // Poll for service rate
    do {
        $service = get_rate_detail($request_id, $service_pickup);

        $service_id     = $service['service_id'] ?? null;
        $service_status = $service['status']     ?? null;

        if ($service_status == 200 || $service_status == 400) {
            break;
        }

        $retryCountService++;
        sleep(2);

    } while ($service_status != 200 && $retryCountService < $maxRetries);

    // Create shipment
    $shipment        = shipment_detail($service_id, $payment_method_id, $unique_id, $detail);
    $shipment_ids    = $shipment['shipment_id'] ?? null;
    $shipment_status = $shipment['status']      ?? null;

    // Poll for shipment details
    do {
        $shipments = get_shipment_detail($shipment_ids);

        $shipments_detail = $shipments['detail'] ?? null;
        $shipments_status = $shipments['status'] ?? null;

        if ($shipments_status == 200 || $shipments_status == 400) {
            break;
        }

        $retryCount++;
        sleep(2);

    } while ($shipments_status != 200 && $retryCount < $maxRetries);

    $shipment_details = $shipments_detail['shipment'] ?? [];

    // maintain logs
    $shipmentAPIsFlow = [
        "Post Rate: Request ID"                    => $request_id,
        "Post Rate: Request ID Status"             => $request_status,
        "Number of cycles to get Rates"            => $retryCountService,
        "Service ID"                               => $service_id,
        "Service Status"                           => $service_status,
        "Post Shipment: Shipment ID"               => $shipment_ids,
        "Post Shipment: Shipment Status"           => $shipment_status,
        "Status shipment detail"                   => $shipments_status,
        "Number of cycles to get shipment detail"  => $retryCount,
        "Last and final Result"                    => $shipments_detail,
    ];

    file_put_contents("whole-data-log.txt", print_r($shipmentAPIsFlow, true), FILE_APPEND);

    $clickship_tran_id = $shipment_details['transaction_number']      ?? 'N/A';
    $tracking_number   = $shipment_details['primary_tracking_number'] ?? 'N/A';

    // FIX 3: Guard the foreach — iterate only when 'labels' is actually an array.
    $selectedLabel = null;
    if (!empty($shipment_details['labels']) && is_array($shipment_details['labels'])) {
        foreach ($shipment_details['labels'] as $label) {
            if (($label['size'] ?? '') === 'letter' && ($label['format'] ?? '') === 'pdf') {
                $selectedLabel = $label['url'];
                break;
            }
        }
    }

    $label_pdf_url = $selectedLabel ?? 'N/A';

    $logPDF = "PDF Url => " . $label_pdf_url;
    file_put_contents("whole-data-log.txt", $logPDF, FILE_APPEND);
    file_put_contents("whole-data-log.txt", "\n\r============================= END =============================", FILE_APPEND);


    // ============================================================
    //  Build $_SESSION['success'] BEFORE unsetting form_data
    // ============================================================
    $_SESSION['success'] = [
        'order_id'        => $order_id,
        'txn_no'          => $txn_no,
        'amount'          => $amount_paid,
        'card_type'       => $card_type,
        'card_mask'       => $card_mask,
        'approval_code'   => $approval_code,
        'resp_code'       => $resp_code,
        'full_name'       => $form_data['full_name']       ?? '',
        'email'           => $form_data['email']           ?? '',
        'total_amount'    => $form_data['total_amount']    ?? 'N/A',
        'shipping_method' => $form_data['shipping_method'] ?? 'N/A',
        'label_pdf'       => $label_pdf_url,
        'tracking_number' => $tracking_number,
        'language'        => $language,  // FIX 1: use the resolved $language variable
    ];

    // Clear payment session AFTER we've finished reading from it
    unset(
        $_SESSION['moneris_ticket'],
        $_SESSION['form_data'],
        $_SESSION['order_id']
    );


    // ============================================================
    //  Save to Database
    // ============================================================
    $saveData = save_data(
        $connect,
        $form_data['full_name'],
        $form_data['email'],
        $form_data['phone'],
        $form_data['apartment'],
        $form_data['city'],
        $form_data['state'],
        $form_data['zip'],
        $form_data['device_model'],
        $form_data['issue'],
        $form_data['message'],
        $form_data['device_password'],
        $order_id,
        $amount_paid,
        $txn_no,
        $form_data['shipping_method'],
        $label_pdf_url,
        $clickship_tran_id,
        $request_id,
        $service_id,
        $shipment_ids,
        $shipments_status,
        $retryCount,
        $language
    );

    if ($saveData) {

        // ============================================================
        //  Send Emails
        // ============================================================
        $toClient      = $form_data['email'];
        $toAdmin        = "admin email";
        $subjectClient = "Your Service Request Confirmation - Order #{$order_id}";
        $subjectAdmin  = "New Service Request Received - Order #{$order_id}";

        // ============================================================
        //  EMAIL HELPERS
        // ============================================================

        function email_wrapper(string $body, string $extraStyle = ''): string {
            return "
                <html><head><style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .container { padding: 10px; }
                    h2 { color: #81A3BB; }
                    p { font-size: 14px; }
                    .table { border-collapse: collapse; width: 100%; }
                    .table td, .table th { border: 1px solid #ddd; padding: 8px; font-size: 14px; }
                    .table th { background: #f2f2f2; }
                    {$extraStyle}
                </style></head>
                <body><div class='container'>
                    {$body}
                    <p style='font-size:11px;color:#aaa;margin-top:20px;'>© " . date('Y') . " Business Name. All rights reserved.</p>
                </div></body></html>
            ";
        }

        function build_client_email(
            array $form_data,
            string $order_id,
            string $txn_no,
            string $trackingDisplay = '',
            bool $hasLabel = false,
            bool $hasManualLabel = false
        ): string {
            $trackingLine = $hasLabel
                ? "<p><strong>Click Ship Tracking #:</strong> {$trackingDisplay}</p>"
                : "<p></p>";

            $body = '';

            // FIX 4: use ?? fallback so missing 'language' key never triggers a notice
            $lang = $form_data['language'] ?? 'en';

            if ($lang === 'fr') {
                if ($hasLabel) {
                    $body = "
                        <h2>Merci, {$form_data['full_name']} !</h2>
                        <p>Votre demande de service a bien été reçue.</p>
                        <p><strong>Numéro de commande :</strong> {$order_id}</p>
                        <p><strong>Numéro de transaction :</strong> " . htmlspecialchars($txn_no) . "</p>
                        {$trackingLine}
                        <p>Notre équipe examinera votre demande et vous contactera prochainement.</p>
                        <br>
                        <p style='font-size:12px;color:#777;'>Pour toute question, veuillez répondre à ce courriel ou nous contacter.</p>
                        <hr>
                    ";
                } elseif ($hasManualLabel) {
                    $body = "
                        <p>
                            Paiement reçu — merci!<br>
                            Votre étiquette d'expédition sera envoyée manuellement à l'adresse courriel utilisée lors du paiement dans 24h.<br>
                            Vérifiez votre boîte de réception ainsi que vos courriels indésirables/spam. Si vous ne la recevez pas, contactez-nous et nous la renverrons.<br>
                            Veuillez inclure votre nom, téléphone, courriel et le problème de l'appareil dans le colis.
                        </p>
                    ";
                }
            } else {
                // Handles 'auto', 'en', and any other language fallback
                if ($hasLabel) {
                    $body = "
                        <h2>Thank You, {$form_data['full_name']}!</h2>
                        <p>Your service request has been received successfully.</p>
                        <p><strong>Order Id:</strong> {$order_id}</p>
                        <p><strong>Transaction #:</strong> " . htmlspecialchars($txn_no) . "</p>
                        {$trackingLine}
                        <p>Our team will review your request and contact you soon.</p>
                        <br>
                        <p style='font-size:12px;color:#777;'>If you have any questions, reply to this email or contact us.</p>
                        <hr>
                    ";
                } elseif ($hasManualLabel) {
                    $body = "
                        <p>
                            Payment received - thank you!<br>
                            Your shipping label will be sent manually to the email used at checkout in 24h.<br>
                            Please check your inbox and spam/junk folder. If you don't receive it, contact us and we'll resend it.<br>
                            Please include your name, phone number, email, and device issue inside the package.
                        </p>
                    ";
                }
            }

            return email_wrapper($body);
        }

        function build_admin_email(array $form_data, string $order_id, string $txn_no, string $adminTable, string $trackingDisplay = '', bool $hasLabel = false): string {
            $trackingLine = $hasLabel
                ? "<p><strong>Click Ship Tracking #:</strong> {$trackingDisplay}</p>"
                : "<p></p>";

            $body = "
                <h2>📦 New Service Request Received</h2>
                <p><strong>Order Id #:</strong> " . htmlspecialchars($order_id) . "</p>
                <p><strong>Transaction #:</strong> " . htmlspecialchars($txn_no) . "</p>
                {$trackingLine}
                {$adminTable}
            ";

            return email_wrapper($body);
        }

        function build_admin_table(array $f): string {
            $rows = [
                'Model'           => ['value' => $f['device_model'],   'required' => true],
                'Issue'           => ['value' => $f['issue'],          'required' => true],
                'Name'            => ['value' => $f['full_name'],      'required' => true],
                'Email'           => ['value' => $f['email'],          'required' => true],
                'Phone'           => ['value' => $f['phone'],          'required' => true],
                'Street'          => ['value' => $f['apartment'],      'required' => false],
                'City'            => ['value' => $f['city'],           'required' => true],
                'State'           => ['value' => $f['state'],          'required' => true],
                'Zip'             => ['value' => $f['zip'],            'required' => true],
                'Message'         => ['value' => $f['message'],        'required' => true],
                'Device Password' => ['value' => $f['device_password'],'required' => false],
            ];

            $html = "<table class='table'>";
            foreach ($rows as $label => $field) {
                $value = trim((string)($field['value'] ?? ''));
                if (!$field['required'] && empty($value)) {
                    continue;
                }
                $html .= "<tr>
                    <td><strong>{$label}</strong></td>
                    <td>" . htmlspecialchars($value ?: 'N/A') . "</td>
                </tr>";
            }

            $html .= "</table>";
            return $html;
        }

        // ============================================================
        //  SEND EMAILS
        // ============================================================

        $labelPdfToSend  = ($label_pdf_url !== 'N/A') ? $label_pdf_url : null;
        $trackingDisplay = htmlspecialchars($tracking_number);

        // $hasLabel  — automated label was successfully generated (ClickShip returned 200 + a URL)
        // FIX 5: $hasManualLabel must be the OPPOSITE of $hasLabel so the two branches are mutually
        //        exclusive. Previously both were true whenever a URL existed, making the
        //        manual-label branch dead code.
        $hasLabel       = ($labelPdfToSend !== null) && ($shipments_status == 200);
        $hasManualLabel = !$hasLabel; // show manual-label message only when automated label failed

        $adminTable    = build_admin_table($form_data);
        $messageClient = build_client_email(
            $form_data,
            $order_id,
            $txn_no,
            $trackingDisplay,
            $hasLabel,
            $hasManualLabel
        );
        $messageAdmin = build_admin_email($form_data, $order_id, $txn_no, $adminTable, $trackingDisplay, $hasLabel);

        $clientEmailSend = smtp_mailer($toClient, $subjectClient, $messageClient, $hasLabel ? $labelPdfToSend : null);
        $adminEmailSend  = smtp_mailer($toAdmin,  $subjectAdmin,  $messageAdmin,  $hasLabel ? $labelPdfToSend : null);

        if ($clientEmailSend && $adminEmailSend) {
            header('Location: success.php?language=' . urlencode($language));
            exit;
        } else {
            die("Emails could not be sent. Please try again.");
        }
    }

} else {

    // ============================================================
    //  ❌ PAYMENT DECLINED — Write Failed Log
    // ============================================================
    moneris_log('failed', [
        'environment' => strtoupper(MONERIS_ENV),
        'timestamp'   => date('Y-m-d H:i:s'),
        'order_id'    => $order_id,
        'customer'    => [
            'full_name' => $form_data['full_name'] ?? 'N/A',
            'email'     => $form_data['email']     ?? 'N/A',
            'phone'     => $form_data['phone']     ?? 'N/A',
        ],
        'decline'     => [
            'txn_no'        => $txn_no,
            'amount'        => $amount_paid,
            'card_type'     => $card_type,
            'card_mask'     => $card_mask,
            'response_code' => $resp_code,
            'iso_code'      => $iso_code,
            'message'       => $message,
            'result'        => $result,
            'success'       => $success_flag,
        ],
        'moneris_raw_response' => $resp,
    ]);

    $decline_messages = [
        '481' => 'Insufficient funds on your card.',
        '482' => 'Your card has expired.',
        '483' => 'Please call your bank to authorize this transaction.',
        '421' => 'Transaction not permitted on this card.',
        '476' => 'Transaction declined by your bank.',
        '050' => 'Transaction declined. Please try a different card.',
        '999' => 'Payment could not be verified. Please try again.',
    ];

    $decline_msg = $decline_messages[$resp_code]
        ?? (($message !== 'N/A' && $message !== '') ? $message : 'Your payment was declined by your bank.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Failed</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body {
            font-family:'Segoe UI',Arial,sans-serif;
            background:#fff5f5;
            display:flex; justify-content:center;
            align-items:center; min-height:100vh;
        }
        .box {
            background:#fff; border-radius:10px;
            box-shadow:0 4px 20px rgba(0,0,0,.1);
            padding:40px; max-width:460px;
            width:100%; text-align:center;
        }
        .icon { font-size:52px; margin-bottom:14px; }
        h2   { color:#c62828; margin-bottom:10px; font-size:22px; }
        p    { color:#555; margin-bottom:8px; font-size:15px; }
        .meta { font-size:12px; color:#bbb; margin-bottom:26px; }
        .btn {
            display:inline-block; padding:12px 30px;
            background:#0066cc; color:#fff;
            border-radius:6px; text-decoration:none;
            font-size:15px; font-weight:600;
        }
        .btn:hover { background:#0052a3; }
        .note { font-size:12px; color:#aaa; margin-top:16px; }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">❌</div>
        <h2>Payment Declined</h2>
        <p><?= htmlspecialchars($decline_msg) ?></p>
        <p class="meta">
            Code: <?= htmlspecialchars($resp_code) ?>
            <?= $txn_no !== 'N/A' ? ' &nbsp;|&nbsp; Ref: ' . htmlspecialchars($txn_no) : '' ?>
        </p>
        <a class="btn" href="index.php">← Try Again</a>
        <p class="note">If this problem persists, please contact your bank or use a different card.</p>
    </div>
    <script src="height-responsive.js"></script>
</body>
</html>
<?php } ?>