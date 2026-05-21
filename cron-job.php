<?php
session_start();
/**
 * Mail-In Shipment Label Processor — Cron Job
 *
 * Responsibilities:
 *  - Fetches pending mail-in records that lack a shipping label
 *  - Polls ClickShip API for rate / shipment / label data
 *  - Updates the database record
 *  - Emails the customer with their label (or a manual-label notice)
 *
 * Run via cron, NOT via HTTP — remove session_start / cache headers.
 *
 * @author   Your Name
 * @version  2.1.0
 */


// ── Bootstrap ────────────────────────────────────────────────────────────────

header('Expires: Thu, 01-Jan-70 00:00:01 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

require_once __DIR__ . '/config.php';   // provides: $connect (PDO), DB_HOST, DB_NAME, DB_USER, DB_PASS
require_once __DIR__ . '/helper.php';   // provides: get_rate_detail(), shipment_detail(), get_shipment_detail()

require_once __DIR__ . '/../assets1/vendor/autoload.php';
require_once __DIR__ . '/../assets1/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../assets1/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../assets1/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

// ── Constants ─────────────────────────────────────────────────────────────────

const MAX_RETRIES  = 10;
const RETRY_SLEEP  = 2;          // seconds between polling attempts
const LOG_FILE     = __DIR__ . '/mailin-cron-log.txt';
const LABEL_SIZE   = 'letter';
const LABEL_FORMAT = 'pdf';

// ── Config (prefer environment variables; fall back to config.php constants) ──

$smtpConfig = [
    'email'    => defined('SMTP_EMAIL')    ? SMTP_EMAIL    : $sendingEmail,
    'password' => defined('SMTP_PASSWORD') ? SMTP_PASSWORD : $sendingPassword,
    'host'     => defined('SMTP_HOST')     ? SMTP_HOST     : 'smtp.gmail.com',
    'port'     => defined('SMTP_PORT')     ? SMTP_PORT     : 465,
    'fromName' => 'Business Name',
];

// $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : $adminEmail;
$adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'afaq.ahmed.awan729@gmail.com'; // for testing

// ── Logger ────────────────────────────────────────────────────────────────────

/**
 * Append a timestamped line (or pretty-printed array) to the log file.
 */
function log_message(string|array $data, string $level = 'INFO'): void
{
    $timestamp = date('Y-m-d H:i:s');
    $line      = is_array($data)
        ? "[{$timestamp}] [{$level}]\n" . print_r($data, true)
        : "[{$timestamp}] [{$level}] {$data}";

    $logDir = dirname(LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents(LOG_FILE, $line . "\n", FILE_APPEND | LOCK_EX);
}

// ── Database Layer ────────────────────────────────────────────────────────────

/**
 * Return a live PDO connection, transparently reconnecting if MySQL has
 * dropped the connection (error 2006 "server has gone away").
 *
 * This is necessary because long-running cron jobs with sleep() calls can
 * exceed MySQL's wait_timeout, causing the connection to be silently closed.
 *
 * Prerequisites: config.php must define the constants DB_HOST, DB_NAME,
 * DB_USER, and DB_PASS (or adjust the DSN below to match your setup).
 *
 * @param  PDO  $pdo   Passed by reference so the caller's variable is updated.
 * @return PDO         A guaranteed-live connection.
 */
function get_pdo_connection(PDO &$pdo): PDO
{
    try {
        // Lightweight ping — if the connection is alive this costs almost nothing.
        $pdo->query('SELECT 1');
    } catch (PDOException $e) {
        $msg = $e->getMessage();

        // 2006 = CR_SERVER_GONE_ERROR  |  2013 = CR_SERVER_LOST
        if (
            str_contains($msg, '2006')       ||
            str_contains($msg, '2013')       ||
            str_contains($msg, 'gone away')  ||
            str_contains($msg, 'server has gone')
        ) {
            log_message('MySQL connection lost — reconnecting…', 'WARN');

            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT         => false,
                ]
            );

            log_message('MySQL reconnected successfully.', 'INFO');
        } else {
            // Unrelated PDO error — re-throw so the caller sees it.
            throw $e;
        }
    }

    return $pdo;
}

/**
 * Fetch all pending mail-in records that still need a valid label.
 *
 * "Pending" = label_pdf is NULL / empty / 'N/A'  OR  shipment_status is not 200.
 *
 * @param  PDO        $pdo
 * @return array<int,array<string,mixed>>
 * @throws RuntimeException on query failure
 */
function fetch_pending_records(PDO $pdo): array
{
    $sql = "
        SELECT *
        FROM   mail_in
        WHERE  (label_pdf IS NULL OR label_pdf = '' OR label_pdf = 'N/A')
        AND   (clickship_tran_id IS NULL OR clickship_tran_id = '' OR clickship_tran_id = 'N/A')
        ORDER BY id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Update a mail_in row after a ClickShip API cycle.
 *
 * Only non-null / non-empty optional values are written to avoid
 * accidentally overwriting data that was already saved on a prior run.
 *
 * @param  PDO         $pdo
 * @param  int         $id              mail_in.id
 * @param  string      $label_pdf       URL or 'N/A'
 * @param  string|null $clickship_tran_id
 * @param  string|null $request_id
 * @param  string|null $service_id
 * @param  string|null $shipment_id
 * @param  int|null    $shipment_status  HTTP status from ClickShip
 * @param  int         $retry
 * @return bool
 */
function update_mailin_record(
    PDO     $pdo,
    int     $id,
    string  $label_pdf,
    ?string $clickship_tran_id = null,
    ?string $request_id        = null,
    ?string $service_id        = null,
    ?string $shipment_id       = null,
    ?int    $shipment_status   = null,
    int     $retry             = 0
): bool {
    $setClauses = [
        'label_pdf         = :label_pdf',
        'shipment_status   = :shipment_status',
        'retry             = :retry',
    ];

    $params = [
        ':label_pdf'       => $label_pdf,
        ':shipment_status' => $shipment_status,
        ':retry'           => $retry,
        ':id'              => $id,
    ];

    // Only overwrite saved IDs if we obtained a new value this cycle
    if (!empty($clickship_tran_id)) {
        $setClauses[]                  = 'clickship_tran_id = :clickship_tran_id';
        $params[':clickship_tran_id']  = $clickship_tran_id;
    }
    if (!empty($request_id)) {
        $setClauses[]           = 'request_id  = :request_id';
        $params[':request_id']  = $request_id;
    }
    if (!empty($service_id)) {
        $setClauses[]           = 'service_id  = :service_id';
        $params[':service_id']  = $service_id;
    }
    if (!empty($shipment_id)) {
        $setClauses[]           = 'shipment_id = :shipment_id';
        $params[':shipment_id'] = $shipment_id;
    }

    $sql  = 'UPDATE mail_in SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);

    return $stmt->execute($params);
}

// ── HTTP / File Helpers ───────────────────────────────────────────────────────

/**
 * Execute a cURL GET request and return the response body.
 *
 * @throws RuntimeException on network/HTTP errors
 */
function curl_get(string $url, array $headers = [], int $timeout = 30): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    $body     = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($body === false || $curlErr) {
        throw new RuntimeException("cURL error for {$url}: {$curlErr}");
    }
    if ($httpCode !== 200) {
        throw new RuntimeException("HTTP {$httpCode} for {$url}");
    }

    return $body;
}

/**
 * Execute a cURL POST and return the response body.
 *
 * @throws RuntimeException
 */
function curl_post(string $url, string $postBody, array $headers = [], int $timeout = 30): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postBody,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $timeout,
    ]);

    $body     = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($body === false || $curlErr) {
        throw new RuntimeException("cURL error for {$url}: {$curlErr}");
    }
    if ($httpCode !== 200) {
        throw new RuntimeException("HTTP {$httpCode} for {$url}");
    }

    return $body;
}

/**
 * Convert a ZPL label URL to PDF bytes via Labelary.
 *
 * @throws RuntimeException
 */
function convert_zpl_to_pdf(string $zplUrl): string
{
    $zplContent = curl_get($zplUrl);

    return curl_post(
        'http://api.labelary.com/v1/printers/8dpmm/labels/4x6/0/',
        $zplContent,
        ['Accept: application/pdf', 'Content-Type: application/x-www-form-urlencoded']
    );
}

/**
 * Download a label URL as PDF bytes (handles both .pdf and .zpl sources).
 *
 * @param  string $url
 * @return string  Raw PDF bytes
 * @throws RuntimeException on unsupported extension or network failure
 */
function fetch_label_as_pdf(string $url): string
{
    $extension = strtolower(
        pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)
    );

    return match ($extension) {
        'pdf'   => curl_get($url),
        'zpl'   => convert_zpl_to_pdf($url),
        default => throw new RuntimeException("Unsupported label format: .{$extension}"),
    };
}

// ── ClickShip Polling Helpers ─────────────────────────────────────────────────

/**
 * Poll get_rate_detail() until we receive a usable service_id or exhaust retries.
 *
 * The PDO connection is pinged on every iteration to prevent "server has
 * gone away" errors caused by accumulated sleep() time.
 *
 * @return array{service_id:string|null, status:int|null, retries:int}
 */
function poll_for_service_id(string $requestId, string $servicePickup, PDO &$pdo): array
{
    $retries = 0;
    $result  = [];

    do {
        // Keep the DB connection alive during long polling sequences.
        get_pdo_connection($pdo);

        $result    = get_rate_detail($requestId, $servicePickup);
        $status    = (int) ($result['status']     ?? 0);
        $serviceId = $result['service_id'] ?? null;

        if ($status === 200 || $status === 400) {
            break;
        }

        $retries++;
        sleep(RETRY_SLEEP);

    } while ($retries < MAX_RETRIES);

    return [
        'service_id' => $serviceId,
        'status'     => $status ?? null,
        'retries'    => $retries,
    ];
}

/**
 * Poll get_shipment_detail() until we receive the full detail or exhaust retries.
 *
 * The PDO connection is pinged on every iteration to prevent "server has
 * gone away" errors caused by accumulated sleep() time.
 *
 * @return array{detail:array,status:int|null,retries:int}
 */
function poll_for_shipment_detail(string $shipmentId, PDO &$pdo): array
{
    $retries = 0;
    $result  = [];

    do {
        // Keep the DB connection alive during long polling sequences.
        get_pdo_connection($pdo);

        $result = get_shipment_detail($shipmentId);
        $status = (int) ($result['status'] ?? 0);
        $detail = $result['detail']        ?? [];

        if ($status === 200 || $status === 400) {
            break;
        }

        $retries++;
        sleep(RETRY_SLEEP);

    } while ($retries < MAX_RETRIES);

    return [
        'detail'  => $detail,
        'status'  => $status ?? null,
        'retries' => $retries,
    ];
}

/**
 * Pick the preferred letter-PDF label URL from ClickShip label array.
 */
function extract_label_url(array $labels): ?string
{
    foreach ($labels as $label) {
        if (($label['size'] ?? '') === LABEL_SIZE && ($label['format'] ?? '') === LABEL_FORMAT) {
            return $label['url'] ?? null;
        }
    }
    return null;
}

// ── Email Helpers ─────────────────────────────────────────────────────────────

/**
 * Wrap an HTML email body in the standard branded shell.
 */
function build_email_html(string $body): string
{
    $year = date('Y');

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <style>
        body      { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; }
        .wrapper  { max-width: 600px; margin: 0 auto; padding: 24px; }
        h2        { color: #81A3BB; }
        p         { font-size: 14px; line-height: 1.6; }
        table     { border-collapse: collapse; width: 100%; margin-top: 12px; }
        td, th    { border: 1px solid #ddd; padding: 8px; font-size: 14px; }
        th        { background: #f2f2f2; }
        .footer   { font-size: 11px; color: #aaa; margin-top: 24px; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
    </head>
    <body>
    <div class="wrapper">
        {$body}
        <p class="footer">&copy; {$year} Business Name. All rights reserved.</p>
    </div>
    </body>
    </html>
    HTML;
}

/**
 * Build the customer-facing confirmation email body.
 *
 * @param  array  $customerData  ['full_name', 'language', ...]
 * @param  string $orderId
 * @param  string $txnNo
 * @param  string $trackingNumber  Already HTML-escaped
 * @param  bool   $hasLabel        True = label is attached
 */
function build_customer_email(
    array  $customerData,
    string $orderId,
    string $txnNo,
    string $trackingNumber = '',
    bool   $hasLabel       = false,
): string {
    $lang         = $customerData['language'] ?? 'en';
    $name         = htmlspecialchars($customerData['full_name'] ?? 'Customer');
    $safeTxn      = htmlspecialchars($txnNo);
    $trackingLine = $hasLabel
        ? "<p><strong>Click Ship Tracking #:</strong> {$trackingNumber}</p>"
        : '';

    // ── French ──────────────────────────────────────────────────────────────
    if ($lang === 'fr') {
        if ($hasLabel) {
            $body = "
                <h2>Merci, {$name} !</h2>
                <p>Votre demande de service a bien été reçue.</p>
                <p><strong>Numéro de commande :</strong> {$orderId}</p>
                <p><strong>Numéro de transaction :</strong> {$safeTxn}</p>
                {$trackingLine}
                <p>Notre équipe examinera votre demande et vous contactera prochainement.</p>
                <p style='font-size:12px;color:#777;'>Pour toute question, veuillez répondre à ce courriel ou nous contacter.</p>
            ";
        } else {
            $body = "
                <p>
                    Paiement reçu — merci !<br>
                    Votre étiquette d'expédition sera envoyée manuellement à l'adresse courriel utilisée
                    lors du paiement dans les 24 heures.<br>
                    Vérifiez votre boîte de réception ainsi que vos courriels indésirables/spam.<br>
                    Veuillez inclure votre nom, téléphone, courriel et le problème de l'appareil dans le colis.
                </p>
            ";
        }

        return build_email_html($body);

    } elseif ($lang === 'auto') {
        // ── Auto / English ───────────────────────────────────────────────────
        if ($hasLabel) {
            $body = "
                <h2>Thank You, {$name}!</h2>
                <p>Your service request has been received successfully.</p>
                <p><strong>Order ID:</strong> {$orderId}</p>
                <p><strong>Transaction #:</strong> {$safeTxn}</p>
                {$trackingLine}
                <p>Our team will review your request and contact you soon.</p>
                <p style='font-size:12px;color:#777;'>If you have any questions, please reply to this email.</p>
            ";
        } else {
            $body = "
                <p>
                    Payment received — thank you!<br>
                    Your shipping label will be emailed manually to the address used at checkout within 24 hours.<br>
                    Please check your inbox <em>and</em> spam/junk folder. If you don't receive it, contact us and we'll resend.<br>
                    Please include your name, phone number, email, and device issue inside the package.
                </p>
            ";
        }

        return build_email_html($body);

    } else {
        // ── English (default) ────────────────────────────────────────────────
        if ($hasLabel) {
            $body = "
                <h2>Thank You, {$name}!</h2>
                <p>Your service request has been received successfully.</p>
                <p><strong>Order ID:</strong> {$orderId}</p>
                <p><strong>Transaction #:</strong> {$safeTxn}</p>
                {$trackingLine}
                <p>Our team will review your request and contact you soon.</p>
                <p style='font-size:12px;color:#777;'>If you have any questions, please reply to this email.</p>
            ";
        } else {
            $body = "
                <p>
                    Payment received — thank you!<br>
                    Your shipping label will be emailed manually to the address used at checkout within 24 hours.<br>
                    Please check your inbox <em>and</em> spam/junk folder. If you don't receive it, contact us and we'll resend.<br>
                    Please include your name, phone number, email, and device issue inside the package.
                </p>
            ";
        }

        return build_email_html($body);
    }
}

/**
 * Send an email via SMTP, optionally attaching a shipping label PDF.
 *
 * @param  string      $to
 * @param  string      $subject
 * @param  string      $htmlBody
 * @param  array       $smtpCfg    SMTP config array (host, port, email, password, fromName)
 * @param  string|null $labelUrl   Remote URL to a PDF / ZPL label
 * @return bool
 */
function send_email(
    string  $to,
    string  $subject,
    string  $htmlBody,
    array   $smtpCfg,
    ?string $labelUrl = null,
): bool {
    $mail    = new PHPMailer(true);
    $tmpFile = null;

    try {
        $mail->isSMTP();
        $mail->SMTPAuth   = true;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Host       = $smtpCfg['host'];
        $mail->Port       = (int) $smtpCfg['port'];
        $mail->Username   = $smtpCfg['email'];
        $mail->Password   = $smtpCfg['password'];

        $mail->setFrom($smtpCfg['email'], $smtpCfg['fromName']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        if (!empty($labelUrl)) {
            try {
                $pdfBytes = fetch_label_as_pdf($labelUrl);
                $tmpFile  = tempnam(sys_get_temp_dir(), 'lbl_') . '.pdf';
                file_put_contents($tmpFile, $pdfBytes);
                $mail->addAttachment($tmpFile, 'shipping-label.pdf');
            } catch (RuntimeException $e) {
                // Label download failed — send the email without attachment
                log_message("Label attachment skipped: {$e->getMessage()}", 'WARN');
            }
        }

        $mail->send();
        return true;

    } catch (MailException $e) {
        log_message("PHPMailer error to {$to}: {$mail->ErrorInfo}", 'ERROR');
        return false;

    } finally {
        if ($tmpFile && file_exists($tmpFile)) {
            @unlink($tmpFile);
        }
    }
}

// ── Main Processing Loop ──────────────────────────────────────────────────────

log_message('=== Cron run started ===');

try {
    // Ping / reconnect before the initial fetch.
    get_pdo_connection($connect);
    $pendingRecords = fetch_pending_records($connect);

} catch (Throwable $e) {
    log_message("Failed to fetch pending records: {$e->getMessage()}", 'ERROR');
    exit(1);
}

if (empty($pendingRecords)) {
    log_message('No pending records found. Exiting.');
    exit(0);
}

log_message(sprintf('Processing %d pending record(s).', count($pendingRecords)));

foreach ($pendingRecords as $record) {
    $mailInId        = (int)    $record['id'];
    $requestId       = (string) ($record['request_id']       ?? '');
    $serviceId       = (string) ($record['service_id']       ?? '');
    $shipmentId      = (string) ($record['shipment_id']      ?? '');
    $orderId         = (string) ($record['order_id']         ?? '');
    $shippingMethod  = (string) ($record['shipping_method']  ?? '');
    $customerEmail   = (string) ($record['customer_email']   ?? '');
    $customerName    = (string) ($record['customer_name']    ?? '');
    $customer_phone  = (string) ($record['customer_phone']   ?? '');
    $zip             = (string) ($record['zip']              ?? '');
    $city            = (string) ($record['city']             ?? '');
    $apartment       = (string) ($record['apartment']        ?? '');
    $state           = (string) ($record['state']            ?? '');
    $customerLang    = (string) ($record['lang']             ?? 'en');

    $dataDetails = [
        'full_name' => $customerName,
        'address'   => $apartment,
        'city'      => $city,
        'state'     => $state,
        'zip'       => $zip,
        'phone'     => $customer_phone,
    ];

    $detail = get_detail($dataDetails);

    log_message("Processing mail_in #{$mailInId} (order: {$orderId})");

    // Carry over values acquired this cycle
    $newServiceId    = null;
    $newShipmentId   = null;
    $retryShipment   = 0;
    $shipmentStatus  = null;
    $labelPdfUrl     = 'N/A';
    $clickshipTranId = 'N/A';
    $trackingNumber  = 'N/A';

    try {
        // ── Step 1: Resolve Request ID ────────────────────────────────────────
        if (empty($requestId)) {
            $request        = get_freightcom_rates($detail);
            $requestId      = $request['request_id'];
            $request_status = $request['status'];

            log_message([
                'Step'       => 'Resolve Request ID',
                'request_id' => $requestId,
                'status'     => $request_status,
            ]);

            if (empty($serviceId)) {
                log_message("Could not resolve service_id for mail_in #{$mailInId}. Skipping.", 'WARN');
                continue;
            }
        }

        // ── Step 2: Resolve Service ID ────────────────────────────────────────
        if (empty($serviceId)) {
            // $connect passed by reference so poll function can keep it alive.
            $svcResult    = poll_for_service_id($requestId, $shippingMethod, $connect);
            $serviceId    = $svcResult['service_id'] ?? '';
            $newServiceId = $serviceId ?: null;

            log_message([
                'Step'       => 'Resolve Service ID',
                'service_id' => $serviceId,
                'status'     => $svcResult['status'],
                'retries'    => $svcResult['retries'],
            ]);

            if (empty($serviceId)) {
                log_message("Could not resolve service_id for mail_in #{$mailInId}. Skipping.", 'WARN');
                continue;
            }
        }

        // ── Step 3: Create Shipment ───────────────────────────────────────────
        if (empty($shipmentId)) {
            $shipResult     = shipment_detail($serviceId, null, $orderId, $detail);
            $shipmentId     = $shipResult['shipment_id'] ?? '';
            $newShipmentId  = $shipmentId ?: null;
            $shipmentStatus = (int) ($shipResult['status'] ?? 0);

            log_message([
                'Step'        => 'Create Shipment',
                'shipment_id' => $shipmentId,
                'status'      => $shipmentStatus,
            ]);

            if (empty($shipmentId)) {
                log_message("Could not create shipment for mail_in #{$mailInId}. Skipping.", 'WARN');
                continue;
            }
        }

        // ── Step 4: Poll for Shipment Detail / Label ──────────────────────────
        // $connect passed by reference so poll function can keep it alive.
        $pollResult      = poll_for_shipment_detail($shipmentId, $connect);
        $shipmentStatus  = (int) ($pollResult['status'] ?? 0);
        $retryShipment   = $pollResult['retries'];
        $shipmentDetail  = $pollResult['detail']['shipment'] ?? [];

        $clickshipTranId = $shipmentDetail['transaction_number']      ?? 'N/A';
        $trackingNumber  = $shipmentDetail['primary_tracking_number'] ?? 'N/A';
        $labelPdfUrl     = extract_label_url($shipmentDetail['labels'] ?? []) ?? 'N/A';

        log_message([
            'Step'           => 'Shipment Detail',
            'status'         => $shipmentStatus,
            'retries'        => $retryShipment,
            'clickship_tran' => $clickshipTranId,
            'tracking'       => $trackingNumber,
            'label_url'      => $labelPdfUrl,
        ]);

    } catch (Throwable $e) {
        log_message("API error for mail_in #{$mailInId}: {$e->getMessage()}", 'ERROR');
        continue;
    }

    // ── Step 5: Persist Results ───────────────────────────────────────────────
    // Ping / reconnect right before the write — this is the critical fix for
    // "MySQL server has gone away" (PDOException 2006) that occurred at line 172.
    try {
        get_pdo_connection($connect);
    } catch (Throwable $e) {
        log_message("Could not reconnect to MySQL before update for mail_in #{$mailInId}: {$e->getMessage()}", 'ERROR');
        continue;
    }

    $saved = update_mailin_record(
        pdo:               $connect,
        id:                $mailInId,
        label_pdf:         $labelPdfUrl,
        clickship_tran_id: $clickshipTranId !== 'N/A' ? $clickshipTranId : null,
        request_id:        null,              // request_id was already in the DB
        service_id:        $newServiceId,
        shipment_id:       $newShipmentId,
        shipment_status:   $shipmentStatus,
        retry:             $retryShipment,
    );

    if (!$saved) {
        log_message("DB update failed for mail_in #{$mailInId}.", 'ERROR');
        continue;
    }

    log_message("DB updated for mail_in #{$mailInId}.");

    // ── Step 6: Email Customer ────────────────────────────────────────────────
    $hasLabel    = ($labelPdfUrl !== 'N/A') && ($shipmentStatus === 200);
    $labelToSend = $hasLabel ? $labelPdfUrl : null;

    $customerData = [
        'full_name' => $customerName,
        'language'  => $customerLang,
    ];

    $emailBody = build_customer_email(
        customerData:   $customerData,
        orderId:        $orderId,
        txnNo:          $clickshipTranId,
        trackingNumber: htmlspecialchars($trackingNumber),
        hasLabel:       $hasLabel,
    );

    $subject   = "Your Service Request Confirmation — Order #{$orderId}";
    $emailSent = send_email($customerEmail, $subject, $emailBody, $smtpConfig, $labelToSend);

    log_message(
        $emailSent
            ? "Customer email sent to {$customerEmail} for mail_in #{$mailInId}."
            : "Failed to send customer email to {$customerEmail} for mail_in #{$mailInId}.",
        $emailSent ? 'INFO' : 'ERROR'
    );
}

log_message('=== Cron run finished ===');
exit(0);