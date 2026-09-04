# StageDoor — v1 (event listing + checkout + M-Pesa)

## Setup

1. Create the database: `mysql -u root -p < schema.sql`
   (this also seeds one sample event so index.php isn't empty)
   If you already have the database from before, run this migration instead
   to add the new columns without losing data:
   ```sql
   ALTER TABLE orders
     MODIFY status ENUM('pending','paid','failed','expired','cancelled') NOT NULL DEFAULT 'pending',
     ADD COLUMN mpesa_checkout_request_id VARCHAR(60),
     ADD COLUMN platform_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
     ADD INDEX idx_checkout_request (mpesa_checkout_request_id);

   CREATE TABLE payouts (
       id              INT AUTO_INCREMENT PRIMARY KEY,
       creator_id      INT NOT NULL,
       event_id        INT NOT NULL,
       amount          DECIMAL(10,2) NOT NULL,
       notes           VARCHAR(255),
       paid_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       FOREIGN KEY (creator_id) REFERENCES users(id),
       FOREIGN KEY (event_id) REFERENCES events(id)
   );
   ```
2. Update the four values at the top of `config/database.php` to match your MySQL setup.
3. Set up email: sign up free at brevo.com, verify a sender email under
   Settings > Senders, then create an API key under Settings > SMTP & API.
   Paste it into `config/email.php` along with your verified sender address.
4. Set an admin password: open `config/admin.php` and change `ADMIN_PASSWORD`
   to something only you know. This gates `/admin/` — the payout-tracking
   area — and isn't a full account system, just a single shared password
   since you're the only admin.
5. Add `config/email.php`, `config/admin.php`, and `config/mpesa.php` to your
   `.gitignore` — they all hold real secrets.
6. Set up M-Pesa (sandbox): create an app at developer.safaricom.co.ke under
   "Lipa Na M-Pesa Online", copy the Consumer Key/Secret into `config/mpesa.php`,
   and copy the sandbox shortcode + passkey from the Daraja docs' sandbox page.
7. Get a public HTTPS callback URL. Safaricom cannot call back to localhost.
   While developing locally, install ngrok (ngrok.com) and run:
   ```
   ngrok http 80
   ```
   Copy the `https://xxxx.ngrok-free.app` URL it gives you, and set
   `MPESA_CALLBACK_URL` in `config/mpesa.php` to
   `https://xxxx.ngrok-free.app/stagedoor/mpesa-callback.php` (adjust the path
   to wherever your project actually sits). You'll need to update this every
   time you restart ngrok on the free plan, since the URL changes.
8. Point your web server at this folder — Apache via XAMPP's htdocs, or:
   ```
   php -S localhost:8000
   ```
9. Visit `http://localhost:8000/index.php` (or your ngrok URL, so the whole
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

creator/register.php   creator signs up
creator/login.php       creator logs in
creator/dashboard.php    lists their events with tickets sold, revenue, and payout status
  └─ creator/create-event.php   form to publish a new event + up to 4 ticket tiers at once

admin/login.php          single shared password (config/admin.php) — you, the platform owner
admin/dashboard.php       every event across all creators, what's owed, what's been paid,
                          and a form to record a payout when you actually send money
```

## What's real vs stubbed

- **Real**: schema, event browsing, ticket tier selection, checkout form validation,
  stock reservation with row-locking (`SELECT ... FOR UPDATE`) so two people can't both
  buy the last ticket, M-Pesa STK Push initiation, the payment callback that confirms
  or fails an order, per-ticket unique code generation (only after real payment),
  confirmation emails sent via Brevo's transactional API, a creator dashboard
  (signup/login, event creation with ticket tiers, sales-so-far per event), abandoned
  checkout cleanup, and creator payout tracking — every paid order records your platform
  fee separately from the creator's share, and the admin dashboard lets you record actual
  payouts, with both sides seeing an accurate running "still owed" figure.
- **Stubbed / simplified**:
  - Only M-Pesa is wired up — the Card/Bank transfer options from the original
    mockup aren't functional.
  - Payouts are manual — you still have to actually send the money (via M-Pesa,
    bank transfer, whatever) yourself; the admin dashboard only *records* that you
    did, it doesn't move money. Automatic splitting (e.g. Paystack subaccounts)
    would be the next step up from this.
  - Creators can't edit or unpublish an event once created — only add new ones.

## Abandoned order cleanup

If a buyer starts checkout and never enters their PIN (or the STK prompt
times out), their reserved ticket stock would otherwise stay locked forever.
`includes/cleanup.php` releases stock from any `pending` order older than
5 minutes and marks it `expired`.

This runs two ways:
- **Opportunistically** — `event.php` and `checkout.php` call it on every page
  load, so stock self-heals whenever anyone browses. No setup required, and
  fine for most traffic levels.
- **On a schedule (optional)** — `cleanup-expired-orders.php` is a CLI script
  you can run independently of site traffic, e.g. via Windows Task Scheduler:
  1. Open Task Scheduler > Create Basic Task.
  2. Trigger: Daily, recurring every few minutes (set "Repeat task every" to
     5 minutes under Advanced Settings).
  3. Action: Start a program — point it at `C:\xampp\php\php.exe` with the
     argument `C:\xampp\htdocs\stagedoor\cleanup-expired-orders.php`.
  This isn't necessary unless you want cleanup to happen even during quiet
  periods with no visitors.

## Files

```
schema.sql                 database schema + seed data
config/database.php        PDO connection
config/email.php           Brevo API key + sender address
config/admin.php           admin password gating /admin/
config/mpesa.php           Daraja consumer key/secret, shortcode, passkey, callback URL
includes/functions.php     formatting helpers, ticket code generator
includes/mailer.php        sends the confirmation email via Brevo's API
includes/mpesa.php         Daraja auth token, STK push, and post-payment ticket issuance
includes/cleanup.php       releases stock from abandoned pending orders
includes/auth.php          creator login guard (require_creator_login, current_creator_id)
includes/header.php        shared <head> + nav (uses $base for subfolder-safe asset paths)
includes/footer.php        shared closing tags
assets/style.css           shared stylesheet
index.php                  browse events
event.php                   event detail + ticket tier picker
checkout.php                buyer form → creates pending order → triggers STK push
payment-status.php          "check your phone" waiting screen (polls for status)
check-payment-status.php    JSON endpoint the waiting screen polls
mpesa-callback.php          Safaricom's server calls this — confirms/fails the order
confirmation.php            shows the buyer their ticket codes
creator/register.php        creator signup
creator/login.php            creator login
creator/logout.php            clears the creator session
creator/dashboard.php          lists the creator's events + sales
creator/create-event.php        form to publish a new event + ticket tiers
cleanup-expired-orders.php       optional CLI script for scheduled cleanup (see above)
admin/login.php                  admin password login
admin/logout.php                  clears the admin session
admin/dashboard.php                every creator's owed/paid figures + record-payout form
```
