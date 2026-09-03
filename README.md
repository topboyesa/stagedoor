# StageDoor — v1 (event listing + checkout)

## Setup

1. Create the database: `mysql -u root -p < schema.sql`
   (this also seeds one sample event so index.php isn't empty)
2. Update the four values at the top of `config/database.php` to match your MySQL setup.
3. Set up email: sign up free at brevo.com, verify a sender email under
   Settings > Senders, then create an API key under Settings > SMTP & API.
   Paste it into `config/email.php` along with your verified sender address.
4. Point your web server (Apache/Nginx/`php -S`) at this folder. Quick local test:
   ```
   php -S localhost:8000
   ```
5. Visit `http://localhost:8000/index.php`.

## Flow

```
index.php          browse published events
  └─ event.php?id=  pick ticket tiers → stores selection in $_SESSION['cart']
       └─ checkout.php   buyer details + order summary → creates order/tickets in a transaction,
                          then emails the buyer their ticket codes via Brevo
            └─ confirmation.php   shows generated ticket codes as a fallback
```

## What's real vs stubbed

- **Real**: schema, event browsing, ticket tier selection, checkout form validation,
  order creation with row-locking (`SELECT ... FOR UPDATE`) so two people can't both
  buy the last ticket, per-ticket unique code generation, and confirmation emails
  sent via Brevo's transactional API.
- **Stubbed**: payment. `checkout.php` marks the order `'paid'` immediately on submit —
  there's no actual payment gateway call yet. When you're ready, that's where you'd
  call Paystack/M-Pesa Daraja/Stripe before writing the order, and only mark it `'paid'`
  once the gateway confirms.
- **Not built yet**: creator accounts/login and a dashboard to create events (the schema
  has `users` and a `creator_id` on `events`, but there's no UI for it — events are
  currently added via SQL, like the seed row in `schema.sql`).

## Files

```
schema.sql              database schema + seed data
config/database.php     PDO connection (edit your credentials here)
config/email.php        Brevo API key + sender address (edit before checkout will send email)
includes/functions.php  formatting helpers, ticket code generator
includes/mailer.php     sends the confirmation email via Brevo's API
includes/header.php     shared <head> + nav
includes/footer.php     shared closing tags
assets/style.css        shared stylesheet (matches the mockup's visual direction)
index.php               browse events
event.php                event detail + ticket tier picker
checkout.php             buyer form + order summary + order creation + sends confirmation email
confirmation.php         shows the buyer their ticket codes as a fallback
```
