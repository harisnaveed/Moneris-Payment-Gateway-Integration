<?php
session_start();

// Guard: only accessible after verified payment
if (empty($_SESSION['success'])) {
    header('Location: index.php');
    exit;
}

$d = $_SESSION['success'];
unset($_SESSION['success']); // One-time display

// FIX 3: Use null-coalescing safely — $d['language'] may not exist
$language = $_GET['language'] ?? ($d['language'] ?? 'en');
?>
<!DOCTYPE html>
<?php if ($language == "fr"): ?>
    <html lang="fr">
<?php else: ?>
    <html lang="en">
<?php endif; ?>

<head>
    <meta charset="UTF-8">
    <title>Payment Successful</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 480px;
            width: 100%;
            text-align: center;
        }
        .icon { font-size: 56px; margin-bottom: 12px; }
        h2 { color: #2e7d32; margin-bottom: 8px; font-size: 24px; }
        .sub { color: #666; font-size: 14px; margin-bottom: 28px; }
        .receipt {
            background: #f8fff8;
            border: 1px solid #c8e6c9;
            border-radius: 8px;
            padding: 20px;
            text-align: left;
            margin-bottom: 24px;
        }
        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 7px 0;
            border-bottom: 1px solid #e8f5e9;
            font-size: 14px;
        }
        .receipt-row:last-child { border-bottom: none; }
        .receipt-row span:first-child { color: #777; }
        .receipt-row span:last-child  { font-weight: 600; color: #222; }
        .btn-group {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
        }
        .btn {
            display: inline-block;
            padding: 12px 32px;
            background: #EE5F21;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
        }
        .btn:hover { background: #d45219; }
        .secure { font-size: 12px; color: #aaa; margin-top: 16px; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">✅</div>

    <h2
        data-lang-en="Payment Successful!"
        data-lang-fr="Paiement réussi !">
        Payment Successful!
    </h2>
    <p class="sub">
        <span data-lang-en="Thank you," data-lang-fr="Merci,">Thank you,</span>
        <strong><?= htmlspecialchars($d['full_name']) ?></strong>
        <!-- FIX 1: Replaced &nbsp; HTML entity with a plain space character.
             getAttribute() returns the raw attribute string, so &nbsp; would
             appear as literal text when assigned via textContent. -->
        <span data-lang-en="! Your payment has been confirmed." data-lang-fr=" ! Votre paiement a été confirmé.">! Your payment has been confirmed.</span>
    </p>
    <?php if (!empty($d['label_pdf']) && $d['label_pdf'] != 'N/A') { ?>
    
    <?php } else { ?>
    <p class="sub">
        <!-- FIX 1 (same): Replaced &nbsp; with a plain space in French string -->
        <span data-lang-en="Your shipping label will be sent manually to the email used at checkout in 24h." data-lang-fr=" Votre étiquette d'expédition sera envoyée manuellement à l'adresse courriel utilisée lors du paiement dans 24h.">Your shipping label will be sent manually to the email used at checkout in 24h.</span>
    </p>
    <?php } ?>
    <div class="receipt">
        <div class="receipt-row">
            <span data-lang-en="Order ID" data-lang-fr="Numéro de commande">Order ID</span>
            <span><?= htmlspecialchars($d['order_id']) ?></span>
        </div>
        <div class="receipt-row">
            <span data-lang-en="Transaction #" data-lang-fr="Transaction #">Transaction #</span>
            <span><?= htmlspecialchars($d['txn_no']) ?></span>
        </div>
        <div class="receipt-row">
            <span data-lang-en="Amount Paid" data-lang-fr="Montant payé">Amount Paid</span>
            <span>$<?= htmlspecialchars($d['amount']) ?> CAD</span>
        </div>
        <div class="receipt-row">
            <span data-lang-en="Card" data-lang-fr="Carte">Card</span>
            <span><?= htmlspecialchars($d['card_type']) ?> — <?= htmlspecialchars($d['card_mask']) ?></span>
        </div>
        <div class="receipt-row">
            <span data-lang-en="Email" data-lang-fr="E-mail">Email</span>
            <span><?= htmlspecialchars($d['email']) ?></span>
        </div>
    </div>
    <div class="btn-group">
        <a class="btn" href="index.php"
            data-lang-en="← Back to Home"
            data-lang-fr="← Retour à l'accueil">← Back to Home</a>

        <?php if (!empty($d['label_pdf']) && $d['label_pdf'] != 'N/A') { ?>
            <!-- FIX 2: Removed Bootstrap's mx-3 class (Bootstrap is not loaded).
                 Spacing is already handled by the .btn-group gap property. -->
            <a class="btn"
               href="<?= htmlspecialchars($d['label_pdf']) ?>"
               data-lang-en="Download Label"
               data-lang-fr="Télécharger l'étiquette"
               target="_blank"
               download>
                Download Label
            </a>
        <?php } ?>
    </div>
</div>

<script>
    const lang = document.documentElement.lang;

    document.querySelectorAll("[data-lang-en]").forEach(el => {
        el.textContent = lang === "fr"
            ? el.getAttribute("data-lang-fr")
            : el.getAttribute("data-lang-en");
    });
</script>

</body>
</html>