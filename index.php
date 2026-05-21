<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<!--<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">-->
<title>Detail Form</title>

<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Segoe UI', Arial, sans-serif;
    /* background: #f0f2f5; */
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 20px;
}

.card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    padding: 35px;
    width: 100%;
    max-width: 500px;
}

h2 { margin-bottom: 20px; }

.form-group { margin-bottom: 15px; }

label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    font-size: 14px;
}

input, select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
}

.row {
    display: flex;
    gap: 10px;
}

.row .form-group {
    flex: 1;
}

.btn {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    color:#ffffff;
}

.btn-next {
    background: #f76222;
}

.btn-pay {
    background: #0066cc;
    color: #fff;
}

.amount-box {
    background: #f0f7ff;
    border: 1px solid #c0dcff;
    padding: 14px;
    border-radius: 6px;
    margin: 20px 0;
    display: flex;
    justify-content: space-between;
}

.step { display: none; }
.step.active { display: block; }

.secure-note{
    text-align: center;
    margin-top: 10px;
}

.radio-group {
    display: flex;
    flex-wrap: wrap;
    /* gap: 20px; */
}

.radio-option {
    align-items: center;
    cursor: pointer;
    font-size: 15px;
    color: #ff6600; /* orange text */
}

.radio-option input {
    margin-right: 6px;
    cursor: pointer;
}

/* optional: better radio look */
.radio-option input[type="radio"] {
    accent-color: #ff6600;
}

.shipmentRadio{
    width: auto;
}

@media (min-width: 480px) {
    .radio-group {
        gap: 20px;
    }

}

</style>
</head>

<body>

<div class="card">

    <form action="checkout.php" method="POST" id="multiStepForm">
        <!-- STEP 1 -->
        <div class="step active" id="step1">
            <h2 
                data-lang-en="Contact Information" 
                data-lang-fr="Informations de contact">
                Contact Information
            </h2>

            <div id="errorMsg" style="display:none; color:red; margin-bottom:10px;">
                <span style="color:#ffcc00">&#9888;</span>
                <span 
                    data-lang-en="• Please fill all required fields" 
                    data-lang-fr="• Veuillez remplir tous les champs obligatoires">
                    • Please fill all required fields
                </span>
            </div>

            <div class="form-group">
                <label 
                    data-lang-en="Full Name *" 
                    data-lang-fr="Nom et prénom *">
                    Full Name *
                </label>
                <input type="text" name="full_name" required>
            </div>

            <div class="form-group">
                <label 
                    data-lang-en="Email Address *" 
                    data-lang-fr="Adresse email *">
                    Email Address *
                </label>
                <input type="email" name="email" required>
            </div>

            <div class="form-group">
                <label 
                    data-lang-en="Phone Number *" 
                    data-lang-fr="Numéro de téléphone *">
                    Phone Number *
                </label>
                <input type="tel" name="phone" required>
            </div>

            <div class="form-group">
                <label 
                    data-lang-en="Street Address *" 
                    data-lang-fr="Adresse de la rue *">
                    Street Address *
                </label>
                <input type="text" name="address" required>
            </div>

            <div class="form-group">
                <label 
                    data-lang-en="Apartment (optional)" 
                    data-lang-fr="Appartement (en option)">
                    Apartment (optional)
                </label>
                <input type="text" name="apartment">
            </div>

            <div class="row">
                <div class="form-group">
                    <label 
                        data-lang-en="City *" 
                        data-lang-fr="Ville *">
                        City *
                    </label>
                    <input type="text" name="city" required>
                </div>

                <!-- <div class="form-group">
                    <label>State *</label>
                    <select class="form-select" id="state" name="state" required=""> 
                    <option value="">Choose...</option> <option>AL</option> <option>AK</option> 
                    <option>AZ</option> <option>AR</option> <option>CA</option> <option>CO</option> <option>CT</option> <option>DE</option> <option>FL</option> <option>GA</option> <option>HI</option> <option>ID</option> <option>IL</option> <option>IN</option> <option>IA</option> <option>KS</option> <option>KY</option> <option>LA</option> <option>ME</option> <option>MD</option> <option>MA</option> <option>MI</option> <option>MN</option> <option>MS</option> <option>MO</option> <option>MT</option> <option>NE</option> <option>NV</option> <option>NH</option> <option>NJ</option> <option>NM</option> <option>NY</option> <option>NC</option> <option>ND</option> <option>OH</option> <option>OK</option> <option>OR</option> <option>PA</option> <option>RI</option> <option>SC</option> <option>SD</option> <option>TN</option> <option>TX</option> <option>UT</option> <option>VT</option> <option>VA</option> <option>WA</option> <option>WV</option> <option>WI</option> <option>WY</option> </select>
                </div> -->

                <div class="form-group">
                    <label 
                        data-lang-en="State *" 
                        data-lang-fr="État *">
                        State *
                    </label>
                    <input type="text" name="state" required>
                </div>

                <div class="form-group">
                    <label 
                        data-lang-en="Zipcode *" 
                        data-lang-fr="Code Postal *">
                        Zipcode *
                    </label>
                    <input type="text" name="zip" required>
                </div>
            </div>

            <button type="button" class="btn btn-next" data-lang-en="Next" data-lang-fr="Suivant" onclick="nextStep()">Next</button>
        </div>


        <!-- STEP 2 -->
        <div class="step" id="step2">

            <h2 
                data-lang-en="Device Information" 
                data-lang-fr="Informations sur l'appareil">
                Device Information
            </h2>


            <div class="form-group">
                <label 
                    data-lang-en="Device Model *" 
                    data-lang-fr="Modèle d'appareil *">
                    Device Model *
                </label>
                <input type="text" name="device_model" required>
            </div>

            <div class="form-group">
                <label 
                    data-lang-en="Issue *" 
                    data-lang-fr="Problème *">
                    Issue *
                </label>
                <input type="text" name="issue" required>
            </div>

            <div class="form-group">
                <label 
                    data-lang-en="Additional Message *" 
                    data-lang-fr="Message supplémentaire *">
                    Additional Message *
                </label>
                <textarea name="message" rows="4" required
                    style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;"></textarea>
            </div>

             <div class="form-group">
                <label 
                    data-lang-en="Device Password (optional)" 
                    data-lang-fr="Mot de passe de l'appareil (en option)">
                    Device Password (optional)
                </label>
                <input type="text" name="device_password">
                <input type="hidden" name="total_amount" id="total_amount">
                <input type="hidden" name="language" id="language" value="auto">
                </div>

                 <div class="radio-group">
                    
                    <label class="radio-option">
                        <input class="shipmentRadio" type="radio" name="shipping_method" value="Standard Shipping" checked>
                        <span 
                            style="color: black !important;"
                            data-lang-en="Standard Shipping (Free)" 
                            data-lang-fr="Livraison standard (gratuite)">
                            Standard Shipping (Free)
                        </span>
                    </label>

                    <label class="radio-option">
                        <input class="shipmentRadio" type="radio" name="shipping_method" value="Faster Shipping">
                        <span 
                            style="color: black !important;"
                            data-lang-en="Faster Shipping (+$20)" 
                            data-lang-fr="Livraison plus rapide (+20 $)">
                            Faster Shipping (+$20)
                        </span>
                    </label>

                </div>

            <p 
                style="font-size:13px; color:#555; margin-top:10px;"
                data-lang-en="Please include your power adapter when sending in laptops or other rechargeable devices. This allows our technicians to perform full diagnostics and prevents delays in your repair." 
                data-lang-fr="Veuillez inclure votre adaptateur secteur lorsque vous nous envoyez un ordinateur portable ou tout autre appareil rechargeable. Cela permettra à nos techniciens d'effectuer un diagnostic complet et d'éviter tout retard dans la réparation.">
                <strong style="color:red;">Note:</strong>
                Please include your power adapter when sending in laptops or other rechargeable devices. This allows our technicians to perform full diagnostics and prevents delays in your repair.
            </p>

            <!-- Amount -->
            <div class="amount-box">
                <span 
                    data-lang-en="💳 Amount" 
                    data-lang-fr="💳 Montant">
                    💳 Amount
                </span>
                <strong>$<span id="amount"><?= CHECKOUT_AMOUNT ?></span> CAD</strong>
            </div>

            <!-- BUTTONS SAME LINE -->
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-next" onclick="prevStep()" style="flex:1;"
                data-lang-en="← Back"
                data-lang-fr="← Retour"
                >
                    ← Back
                </button>

                <button type="submit" class="btn btn-pay" style="flex:1;"
                data-lang-en="🔒 Pay Now"
                data-lang-fr="🔒 Payer maintenant"
                >
                    🔒 Pay Now
                </button>
                
            </div>
            <!--<p class="secure-note">🛡️ Powered by Moneris — PCI DSS Compliant</p>-->
        </div>

    </form>
</div>

<script>
function nextStep() {
    let step1 = document.getElementById('step1');
    let requiredFields = step1.querySelectorAll('[required]');
    let isValid = true;

    // reset styles
    requiredFields.forEach(field => {
        field.style.borderColor = '#ddd';
    });

    // validate
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = 'red';
            isValid = false;
        }
    });

    if (!isValid) {
        document.getElementById('errorMsg').style.display = 'block';
        return;
    }

    // hide error
    document.getElementById('errorMsg').style.display = 'none';

    // go to step 2
    document.getElementById('step1').classList.remove('active');
    document.getElementById('step2').classList.add('active');
}

document.querySelectorAll('#step1 input, #step1 select').forEach(field => {
    field.addEventListener('input', function () {
        if (this.value.trim() !== '') {
            this.style.borderColor = '#ddd';
        }
    });
});

function prevStep() {
    document.getElementById('step2').classList.remove('active');
    document.getElementById('step1').classList.add('active');
}


// ============================================================
// STEP 2 — Calculate Total Amount
// ============================================================

document.querySelectorAll('input[name="shipping_method"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        const amountElement = document.getElementById('amount');
        const totalAmountInput = document.getElementById('total_amount');
        const selectedValue = this.value;

        let baseAmount = <?= CHECKOUT_AMOUNT ?>;
        let shippingCost = 0;

        if (selectedValue === 'Faster Shipping') {
            shippingCost = 20;
        }

        const total = baseAmount + shippingCost;

        amountElement.textContent = total.toFixed(2);
        totalAmountInput.value = total.toFixed(2);
    });
});
</script>

<script>
window.addEventListener("message", function(e) {
    if (typeof e.data === "string" && e.data.startsWith("lang::")) {
        var lang = e.data.replace("lang::", "");
        console.log("iFrame Language: ",lang);
        applyLanguage(lang);
        
    }
});

function applyLanguage(lang) {
    // ✅ SET HTML LANG ATTRIBUTE
    document.documentElement.lang = lang;
    document.getElementById("language").value=lang;

    // Example logic for text replacement
    if (lang === "fr") {
        document.querySelectorAll("[data-lang-en]").forEach(el => {
            el.textContent = el.getAttribute("data-lang-fr");
        });
    } else {
        document.querySelectorAll("[data-lang-en]").forEach(el => {
            el.textContent = el.getAttribute("data-lang-en");
        });
    }
}
</script>
<script src="height-responsive.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>