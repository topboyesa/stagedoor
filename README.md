# StageDoor — v1 (event listing + checkout)

## Setup

1. Create the database: `mysql -u root -p < schema.sql`
   (this also seeds one sample event so index.php isn't empty)
2. Update the four values at the top of `config/database.php` to match your MySQL setup.
3. Point your web server (Apache/Nginx/`php -S`) at this folder. Quick local test:
   ```
   php -S localhost:8000
   ```
4. Visit `http://localhost:8000/index.php`.

## Flow

```
index.php          browse published events
  └─ event.php?id=  pick ticket tiers → stores selection in $_SESSION['cart']
       └─ checkout.php   buyer details + order summary → creates order/tickets in a transaction
            └─ confirmation.php   shows generated ticket codes
```

## What's real vs stubbed

- **Real**: schema, event browsing, ticket tier selection, checkout form validation,
  order creation with row-locking (`SELECT ... FOR UPDATE`) so two people can't both
  buy the last ticket, and per-ticket unique code generation.
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
includes/functions.php  formatting helpers, ticket code generator
includes/header.php     shared <head> + nav
includes/footer.php     shared closing tags
assets/style.css        shared stylesheet (matches the mockup's visual direction)
index.php               browse events
event.php                event detail + ticket tier picker
checkout.php             buyer form + order summary + order creation
confirmation.php         shows the buyer their ticket codes
```
