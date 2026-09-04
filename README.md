# StageDoor — v1 (event listing + checkout + M-Pesa)

## Setup

1. Create the database: `mysql -u root -p < schema.sql`
   (this also seeds one sample event so index.php isn't empty)
   If you already have the database from before, run this migration instead
   to add the new columns without losing data:
   ```sql
   ALTER TABLE orders
     MODIFY status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
     ADD COLUMN mpesa_checkout_request_id VARCHAR(60),
     ADD INDEX idx_checkout_request (mpesa_checkout_request_id);
   ```
2. Update the four values at the top of `config/database.php` to match your MySQL setup.
3. Set up email: sign up free at brevo.com, verify a sender email under
   Settings > Senders, then create an API key under Settings > SMTP & API.
   Paste it into `config/email.php` along with your verified sender address.
4. Set up M-Pesa (sandbox): create an app at developer.safaricom.co.ke under
   "Lipa Na M-Pesa Online", copy the Consumer Key/Secret into `config/mpesa.php`,
   and copy the sandbox shortcode + passkey from the Daraja docs' sandbox page.
5. Get a public HTTPS callback URL. Safaricom cannot call back to localhost.
   While developing locally, install ngrok (ngrok.com) and run:
   ```
   ngrok http 80
   ```
   Copy the `https://xxxx.ngrok-free.app` URL it gives you, and set
   `MPESA_CALLBACK_URL` in `config/mpesa.php` to
   `https://xxxx.ngrok-free.app/stagedoor/mpesa-callback.php` (adjust the path
   to wherever your project actually sits). You'll need to update this every
   time you restart ngrok on the free plan, since the URL changes.
6. Point your web server at this folder — Apache via XAMPP's htdocs, or:
   ```
   php -S localhost:8000
   ```
7. Visit `http://localhost:8000/index.php` (or your ngrok URL, so the whole
   flow — including the callback — is reachable the same way Safaricom sees it).

## Flow

```
index.php          browse published events
  └─ event.php?id=  pick ticket tiers → stores selection in $_SESSION['cart']
       └─ checkout.php        buyer details + order summary → creates a PENDING order,
                               reserves stock, triggers the M-Pesa STK Push
            └─ payment-status.php   "check your phone" screen, polls check-payment-status.php
                 
       (meanwhile, off to the side:)
       mpesa-callback.php      Safaricom calls this directly once the buyer enters
                               their PIN. Marks the order paid/failed, issues ticket
                               codes only on success, sends the confirmation email.

            └─ confirmation.php   buyer lands here once payment-status.php detects "paid"
```

## What's real vs stubbed

- **Real**: schema, event browsing, ticket tier selection, checkout form validation,
  stock reservation with row-locking (`SELECT ... FOR UPDATE`) so two people can't both
  buy the last ticket, M-Pesa STK Push initiation, the payment callback that confirms
  or fails an order, per-ticket unique code generation (only after real payment),
  and confirmation emails sent via Brevo's transactional API.
- **Stubbed / simplified**:
  - Only M-Pesa is wired up — the Card/Bank transfer options from the original
    mockup aren't functional.
  - Abandoned pending orders (buyer never enters their PIN, or the STK prompt
    times out) leave their reserved stock locked until you build a cleanup job —
    e.g. a scheduled script that releases stock for any 'pending' order older
    than ~5 minutes.
  - You (the platform) collect 100% of the payment into your own M-Pesa account —
    there's no automatic split to individual creators yet. `service_fee` in
    checkout.php is your cut; the rest (`total`) is what you'd owe the creator,
    tracked per order but not yet paid out automatically.
- **Not built yet**: creator accounts/login and a dashboard to create events (the schema
  has `users` and a `creator_id` on `events`, but there's no UI for it — events are
  currently added via phpMyAdmin, like the seed row in `schema.sql`).

## Files

```
schema.sql                 database schema + seed data
config/database.php        PDO connection
config/email.php           Brevo API key + sender address
config/mpesa.php           Daraja consumer key/secret, shortcode, passkey, callback URL
includes/functions.php     formatting helpers, ticket code generator
includes/mailer.php        sends the confirmation email via Brevo's API
includes/mpesa.php         Daraja auth token, STK push, and post-payment ticket issuance
includes/header.php        shared <head> + nav
includes/footer.php        shared closing tags
assets/style.css           shared stylesheet
index.php                  browse events
event.php                   event detail + ticket tier picker
checkout.php                buyer form → creates pending order → triggers STK push
payment-status.php          "check your phone" waiting screen (polls for status)
check-payment-status.php    JSON endpoint the waiting screen polls
mpesa-callback.php          Safaricom's server calls this — confirms/fails the order
confirmation.php            shows the buyer their ticket codes
```
