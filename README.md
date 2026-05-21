# Moneris Payment Gateway Integration

Standalone Core PHP integration for the Moneris Payment Gateway with Freightcom Shipping API integration.

This project provides:

- Moneris payment processing
- Freightcom shipping label generation
- MySQL database integration
- Bootstrap responsive UI
- Core PHP standalone architecture

---

# Features

## Moneris Payment Gateway

- Hosted checkout integration
- Secure payment processing
- Transaction response handling
- Payment success/failure flow
- Easy configuration setup

## Freightcom Shipping Integration

- Shipping rate integration
- Shipping label generation
- API-based shipment handling
- Test and Production environments
- Payment method support

---

# Tech Stack

- Core PHP
- HTML5
- CSS3
- Bootstrap
- JavaScript
- MySQL

---

# Clone Repository

```bash
git clone https://github.com/harisnaveed/Moneris-Payment-Gateway-Integration.git
```

---

# Installation

## 1. Clone the Repository

```bash
git clone https://github.com/harisnaveed/Moneris-Payment-Gateway-Integration.git
```

---

## 2. Move Project to Local Server

### XAMPP

```bash
htdocs/
```

### WAMP

```bash
www/
```

---

## 3. Import Database

- Open phpMyAdmin
- Create a database
- Import:

```bash
database.sql
```

---

## 4. Configure Database

Update database credentials inside:

```bash
config.php
```

Example:

```php
$host = "localhost";
$username = "root";
$password = "";
$database = "moneris_gateway";
```

---

# Moneris Configuration

Update credentials inside:

```bash
config/config.php
```

## Moneris Variables

```php
define('MONERIS_STORE_ID',    'store_id');
define('MONERIS_API_TOKEN',   'api_token');
define('MONERIS_CHECKOUT_ID', 'checkout_id');
define('CHECKOUT_AMOUNT',     '79.99');
```

## Variable Explanation

| Variable            | Description            |
| ------------------- | ---------------------- |
| MONERIS_STORE_ID    | Moneris Store ID       |
| MONERIS_API_TOKEN   | Moneris API Token      |
| MONERIS_CHECKOUT_ID | Hosted Checkout ID     |
| CHECKOUT_AMOUNT     | Default payment amount |

---

# Freightcom API Configuration

Update Freightcom credentials inside:

```bash
config/config.php
```

---

## Freightcom Testing Environment

```php
// Testing
$freightcom_api_key = "your_test_api_key";
$freightcom_base_url = "https://customer-external-api.ssd-test.freightcom.com/";
$payment_method_id = "your_test_payment_method_id";
```

---

## Freightcom Production Environment

```php
// Production
$freightcom_api_key = "your_live_api_key";
$freightcom_base_url = "https://external-api.freightcom.com/";
$payment_method_id = "your_live_payment_method_id";
```

---

# Freightcom Variables

| Variable            | Description                       |
| ------------------- | --------------------------------- |
| freightcom_api_key  | Freightcom API authentication key |
| freightcom_base_url | Freightcom API base URL           |
| payment_method_id   | Freightcom payment method ID      |

---

# Running the Project

Start Apache and MySQL from XAMPP/WAMP and open:

```bash
http://localhost/Moneris-Payment-Gateway-Integration
```

---

# Payment Flow

1. Customer opens checkout page
2. Customer enters payment details
3. Moneris processes payment
4. Payment response is validated
5. Transaction stored in MySQL
6. Shipping label generated through Freightcom API
7. Shipment details returned

---

# Shipping Flow

1. Customer completes payment
2. Shipment data prepared
3. Freightcom API called
4. Shipping label generated
5. Tracking information returned
6. Shipment stored in database

---

# Security Notes

- Never expose API credentials publicly
- Use environment variables in production
- Use HTTPS in production
- Sanitize all user input
- Store sensitive credentials securely

---

# Moneris Developer Resources

- https://developer.moneris.com/
- https://www.moneris.com/

---

# Freightcom Developer Resources

- https://www.freightcom.com/
- https://api.freightcom.com/

---

# Future Improvements

- Refund support
- Recurring payments
- Multi-shipping providers
- Admin dashboard
- Shipment tracking
- Invoice generation

---

# Author

Haris Naveed

---

# License

This project is for development and educational purposes.
