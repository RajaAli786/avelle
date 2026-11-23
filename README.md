# AVELLE — Women & Girls Ecommerce

A Laravel ecommerce project for women's and girls' fashion, beauty, accessories and everyday products. Includes a marketplace-style storefront and an AdminLTE administration panel.

**Status:** Core shopping and administration features are implemented. Checkout currently uses Cash on Delivery (COD). Online payments are planned for a later phase. Email code is implemented; actual delivery requires SMTP credentials.

## Technology

- Laravel 13, PHP 8.3+ (the current local installation uses PHP 8.4).
- MySQL / MariaDB; SQLite is not used.
- Blade templates, CSS and JavaScript.
- AdminLTE 4, Bootstrap and Bootstrap Icons for administration.
- Composer for PHP dependencies. No Node build step is required for the current frontend.

## Laravel implementation details

This is a custom Laravel application. The direct Composer requirements are `php: ^8.3` and `laravel/framework: ^13.0`; exact installed dependency versions are recorded in `composer.lock`.

| Area | What this project uses |
| --- | --- |
| Application structure | Laravel controllers, web routes, middleware, service providers and custom support classes for store rules. |
| Frontend rendering | Server-rendered Blade layouts, views and partials; custom CSS and vanilla JavaScript. Admin and Seller Central use AdminLTE 4, Bootstrap and Bootstrap Icons. |
| Database | MySQL/MariaDB, Laravel migrations and seeders. Most commerce queries use Laravel's `DB` Query Builder; the `User` model uses Eloquent. |
| Authentication | Laravel session authentication (`Auth`), custom customer/admin/seller screens and role/ownership checks. No Breeze, Jetstream, Fortify, Sanctum or third-party role package is directly installed. |
| Password storage | Laravel hashing with bcrypt (12 rounds), including the User model's hashed password cast. Passwords are matched against database hashes, not an `.env` login password. |
| Account/session controls | Session regeneration at login, logout invalidation and custom `EnsureAccountIsActive` middleware. An account version invalidates older sessions after credential changes or administrative account actions. |
| Request protection | Laravel request validation, web CSRF protection and route throttling for login, recovery, reviews and other sensitive submissions. |
| Cart | Custom cart logic, not a Composer cart package. Guests use browser `localStorage`; signed-in carts use the `carts` database table through GET/PUT `/api/cart`. Guest items merge at sign-in. Lines include product, size/variant and quantity. |
| Wishlist | Custom browser/database persistence and authenticated wishlist endpoints. |
| Prices and checkout | Custom server-side stock, pricing, coupon/referral and seller-offer rules; database transactions and row locks protect critical order/stock updates. Browser totals are not the checkout authority. |
| Marketplace | Custom seller approval, product ownership, split fulfilment, commission snapshots and manual settlement records. |
| Reviews and returns | Custom delivered-purchase eligibility, moderation, customer review/return pages and admin workflows. |
| Email/recovery | Laravel Mail with the custom `StoreMessage` mailable, Blade email views, log/SMTP configuration and Laravel password-recovery integration. Mail sends are synchronous in the current implementation. |
| Product/category images | Custom validated multiple-image upload/gallery handling and local public media; no external media-management package. |
| Sessions and cache | File-based session and cache defaults in `.env.example`; no Redis service is required for this setup. |
| Verification | Artisan commands, Blade compilation and standalone PHP regression scripts booting Laravel. Database fixtures roll back; email tests use fakes. |
| Dependency/build tools | Composer and `composer.lock` for PHP dependencies. Frontend assets are served directly; the current setup has no npm/Vite build requirement. |

Online payment SDKs, courier APIs and automatic bank payout/refund integrations are not installed. Checkout currently supports COD; recording a payment/refund/payout status does not itself transfer money.

## Implemented features

### Shopping frontend

- Separate home, shop, category, product detail, wishlist, cart, checkout, offers and account pages.
- Theme-matched storefront footer with higher-contrast navigation, readable desktop/mobile type and responsive columns.
- Women / girls collection filters, search, sorting and product filters.
- Category and subcategory navigation, category descriptions and multiple category images.
- Product galleries with multiple images, cover ordering, thumbnails and image enlargement.
- Product descriptions, highlights and editable additional information such as fabric, care and fit.
- Product SKU, brand, selling price, original price, badges and featured-product selection.
- Size / colour variants with individual inventory and optional price overrides.
- Guest cart and wishlist in the browser; signed-in cart and wishlist stored in the database.
- Active-offer marquee, pulse or static display; animation pause and reduced-motion support.

### Customer accounts and orders

- Registration, login, logout, profile editing and password changes.
- Checkout requires sign-in or account creation first. Customers return to their original page after authentication, with their cart preserved.
- Saved delivery addresses and default address selection.
- COD checkout with server-calculated prices, shipping, discounts and inventory checks.
- Transactional stock deduction and checks against duplicate cart lines exceeding stock.
- Checkout retry key support to avoid repeating an authenticated order submission.
- Customer order history, order detail pages and status history.
- Printable order receipt (not a GST / tax invoice).
- Customer cancellation before dispatch; cancellation restores stock and releases coupon usage.
- Tracking lookup using order reference and email. Carrier / tracking details are entered manually by admin.
- Reviews restricted to customers with a delivered purchase, with admin moderation and reply.
- Return requests within a configurable delivery-based window, selected quantities and admin processing.
- Manual refund amount / reference records and optional one-time inventory restocking. This does not transfer money.

### Coupons and referrals

- Fixed-amount and percentage discounts.
- Store-wide, category-specific or product-specific targeting.
- Minimum subtotal, maximum savings, expiry, usage cap and pause / reactivate controls.
- Public banner visibility, headline and animation settings.
- One code per order; referral codes are private, first-order only and cannot be used by their owner email.
- Automatic referral commissions, wallets and reward payouts are not implemented.

### Admin panel

- Dashboard, catalogue, categories, inventory and order management.
- Multiple image uploads for both products and categories, image ordering, image descriptions and removal.
- Product details, additional attributes, visibility and featured-product controls.
- Size / colour variant stock and pricing controls.
- Order status, manual tracking details and COD payment collection records.
- Customer name / email editing, password resets and enable / disable controls.
- Admin name, email and password editing; own password changes require the current password.
- Credential changes revoke old sessions; disabled users cannot sign in. Own-account and last-active-admin protections apply.
- Review moderation, returns, support inquiries and editable information / policy pages.
- Store name, shipping fee, free-shipping threshold, return window, COD availability and support details.

### Email

- Welcome email.
- Customer order confirmation and optional owner new-order notification.
- Order status / cancellation emails.
- Forgot-password and reset-password pages with email links.
- Hashed, single-use reset tokens with a 60-minute expiry; reset revokes previous account sessions.
- Disabled accounts cannot request a reset.
- SMTP configuration and an email test command.
- Default local preview mode writes emails to `storage/logs/mail.log` without external delivery.

## Seller marketplace

Seller Central uses a dedicated AdminLTE shell with a responsive sidebar, overview, product editor, orders, offers, earnings, business-profile and seller Account & Security sections. Counts and amounts come from the seller’s own records; no sample revenue charts are presented.

- Apply at /seller/apply, sign in at /seller/login, and manage the business at /seller. Existing customers can sign in and apply using the same account.
- Business profiles start pending. Admin reviews them under Sellers and sets a commission rate (new applications default to 10%). Pending, rejected, suspended or disabled sellers cannot publish shoppable products.
- Approved sellers manage only their own products, multiple images, descriptions, specifications and variant inventory. Seller edits require product moderation again. Categories and platform coupons remain admin-managed.
- Seller Account & Security stays inside Seller Central. Sellers can edit their name and login email; email changes require the current password. Password changes require the current password, matching confirmation and at least 12 characters. Email/password changes revoke other sessions while preserving the current session.
- Updating business details submits the profile for reapproval and hides its products until approval.
- New mixed-cart orders create separate seller/store shipment records. A seller sees only their own items and the delivery details needed for fulfilment. Existing historical orders remain in the original order workflow.
- Each shipment moves placed → confirmed → shipped → delivered. Dispatch requires carrier and tracking number. Main order status follows the least-progressed active shipment; full-order cancellation is blocked after any shipment dispatches.
- Product/category coupons allocate discounts only to eligible items. Commission is captured per shipment at checkout, on discounted merchandise, excluding platform delivery fees, and rounded to whole INR. Later commission changes do not alter existing orders.
- Sellers see estimated earnings and externally recorded payouts. These are not a wallet balance. Admin records an already-processed payout only after all delivery completes, payment collection is recorded, the return window expires and there are no non-rejected returns. Duplicate payout recording is blocked. Orders with returns require separate reconciliation; adjusted settlement is not implemented.
- Sellers can create, edit and pause percentage/fixed coupons for all their products, their own selected categories or individual product IDs. Supports eligible-subtotal minimums, savings caps, expiry, usage limits and public marquee/pulse/static display. Ownership, commission and usage counts cannot be set by a seller.
- Seller coupons discount only that seller’s eligible items, including in mixed carts. Platform coupons keep the original bag-wide minimum rule; seller coupons use only eligible seller subtotal for their minimum. One code per order; coupon stacking is not supported.
- Admin can lock seller coupons; a seller cannot reactivate a locked offer. Suspension/disable hides seller offers, and banners require an eligible shoppable product. Used seller coupon codes cannot be renamed; cancellation releases usage.
- Automated payouts, payout bank-account onboarding/KYC, seller-specific courier integrations and seller-facing return case management are not implemented. Returns remain admin-managed. No money is transferred by the payout form.

### Upgrade an existing installation

Back up the database and uploaded images, pull the updated source, then run:

```powershell
php artisan migrate
php artisan view:clear
```

This adds seller profiles, product ownership/moderation and seller shipment records without resetting current products or users. Existing products remain owned by the Avelle store; new seller products must be approved.

## Pending / requires configuration

| Item | Current state |
| --- | --- |
| Online payments | Deferred; no live gateway, payment verification or automatic online refunds. |
| SMTP delivery | Replace placeholder settings with your provider credentials, then enable SMTP. |
| Delivery / courier API | Manual carrier and tracking entry works; booking, labels and live courier sync are pending. |
| Refund transfers | Admin records are available; actual refunds are processed separately. |
| SMS / WhatsApp / OTP | Not integrated. |
| Email verification | Not implemented; password recovery is implemented separately. |
| Referral wallet / commissions | Not implemented. |
| GST / tax invoices | Printable order receipt only; tax calculation and compliant invoicing are pending. |
| Failed email retry | Failures are logged; automatic retry / queue processing is not configured. |
| Product content | Replace demo images and descriptions; add real specifications, size charts and stock. |
| Store policies | Replace starter about, shipping, returns, privacy, terms and FAQ content before launch. |
| Production deployment | Configure hosting, HTTPS, domain, database permissions, backups and monitoring. |

## Fresh local setup

### 1. Requirements

Install Git, Composer, a compatible PHP 8.3+ runtime, and MySQL / MariaDB. Enable PHP extensions required by Composer, including `pdo_mysql`, `mbstring`, `openssl` and `fileinfo`. Composer will report missing requirements.

Some XAMPP installations ship an older PHP version. PHP 8.0 cannot run this project. The existing development computer uses a separate compatible PHP runtime in `.runtime/php`; that runtime is not included in Git.

Confirm the PHP used by your terminal and Composer:

```powershell
php -v
composer --version
```

### 2. Clone and install

```powershell
git clone https://github.com/RajaAli786/avelle.git
cd avelle
New-Item -ItemType Directory -Force -Path bootstrap/cache,storage/framework/cache/data,storage/framework/sessions,storage/framework/views,storage/logs | Out-Null
composer install
Copy-Item .env.example .env
```

Create the local writable directories if they are missing from the checkout:

```powershell
New-Item -ItemType Directory -Force -Path bootstrap/cache,storage/framework/cache/data,storage/framework/sessions,storage/framework/views,storage/logs | Out-Null
```

### 3. Configure the database

Start MySQL from XAMPP. Create an empty database named `avelle_store` in phpMyAdmin, or run:

```sql
CREATE DATABASE avelle_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Edit `.env`:

```dotenv
APP_NAME=AVELLE
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=avelle_store
DB_USERNAME=root
DB_PASSWORD=
```

Use your own database username / password if different. A blank root password is only the existing local XAMPP convention.

### 4. Create tables and admin

```powershell
php artisan key:generate
php artisan migrate
php artisan store:admin
```

The admin command asks for name, email and a password of at least 12 characters. No shared admin password is included in Git. Passwords are stored in the users table as hashes; `.env` is not the admin login database.

A fresh migrated database has starter content pages and store settings, but no customer accounts or populated catalogue. Add categories first, then products and images from admin. The legacy demo seeder is not a complete marketplace bootstrap and is not part of the recommended fresh setup. Do not run it against a populated store: it can overwrite matching demo product IDs and stock.

If Windows reports a package / service cache write error after directories are created, run the project helper and retry:

```powershell
php scripts/warm-cache.php
php artisan migrate
```

### 5. Run the development server

```powershell
php artisan serve
```

Open:

- Store: http://127.0.0.1:8000/
- Admin: http://127.0.0.1:8000/admin
- Account: http://127.0.0.1:8000/account
- Password recovery: http://127.0.0.1:8000/forgot-password

Keep `APP_URL` matched to the address you use.

### XAMPP Apache at localhost/avelle

On the original computer, the project lives at `C:/xampp/htdocs/avelle` and opens at http://localhost/avelle/ with Apache and MySQL running.

On another computer, configure an Apache Alias / virtual host so `/avelle` serves the project's **public** directory. Enable `mod_rewrite`, allow the public `.htaccess`, and make sure Apache uses PHP 8.3+ for this application. The project root must not be publicly served. The current `public/.htaccess` has `RewriteBase /avelle/`; adjust it if deploying at a different URL path.

Set `APP_URL=http://localhost/avelle`. The Apache alias and isolated PHP configuration on the original computer are machine-specific and are not automatically installed by cloning Git.

## Database files and restoration

- `database/migrations/` is the recommended source of truth for creating and upgrading the database.
- `database/schema.sql` is a schema-only export for inspecting / manually importing the table structure. It contains no existing customers, orders, passwords or SMTP credentials.
- The SQL schema does not include populated migration-history rows, store settings, policies or catalogue data. It is not a complete ready-to-run store backup. Prefer `php artisan migrate` for fresh setup.
- Do not import the schema and then blindly run all migrations against those same existing tables. Use either migrations for a fresh database, or a proper full backup with its matching migration history for a full restoration.
- Moving a populated store requires a separate private database backup plus the corresponding `public/uploads` images. Git source alone does not preserve those records.

## Email setup

Local preview is enabled by default:

```dotenv
MAIL_MAILER=log
```

For real delivery, replace the following `.env` values:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=your-email@example.com
MAIL_PASSWORD=your-smtp-or-app-password
MAIL_FROM_ADDRESS=your-email@example.com
MAIL_FROM_NAME="Avelle"
MAIL_ADMIN_ADDRESS=owner@example.com
```

Use the host, port and credentials supplied by your email provider. For implicit TLS on port 465, use `MAIL_SCHEME=smtps` when your provider requires it. Keep certificate verification enabled. The sender must be permitted by the provider. `MAIL_ADMIN_ADDRESS` is optional.

After editing:

```powershell
php artisan config:clear
php artisan store:mail-test your-email@example.com
```

In log mode, the command generates only a local preview. In SMTP mode, it sends one test email. Sending requires valid credentials; replacing only the email ID is not sufficient. Reset links use `APP_URL`, so localhost links work only on the development computer.

See [EMAIL-SETUP.md](EMAIL-SETUP.md) for details. Mail failures are logged without SMTP credentials and do not undo an already committed order. Logs may contain personal data and reset links: do not commit them.

## Validation

Standalone PHP checks are included; no separate PHPUnit installation is required for these scripts:

```powershell
php tests/smoke.php
php tests/catalogue.php
php tests/email.php
php tests/sellers.php
php tests/seller-offers.php
php tests/seller-account.php
php tests/customer-admin.php
```

Previously verified: 48 checkout / auth / coupon / admin checks, 11 catalogue checks, 16 email / recovery assertions and 37 seller marketplace checks, plus 28 seller-offer checks and 13 seller-account checks. These are targeted regression checks, not a complete production certification. The scripts use database transactions and roll fixtures back; the catalogue script also creates and cleans up temporary uploaded images. Run them on a local development database.

## Git and production notes

- Commit source, `composer.lock`, `.env.example` and migrations.
- Exclude `.env`, private database backups, runtime binaries, `vendor`, sessions, compiled caches and logs.
- `public/uploads` can contain real store images; review what you intend to share before committing it.
- Run `composer install` after cloning; dependencies and the isolated PHP runtime are not included.
- Use HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` and a dedicated database user before publishing.
- Keep `storage` and `bootstrap/cache` writable. Configure upload limits for the application's image limits: up to 5 MB per image, 10 images per save and 20 per product / category.
- Back up both database and uploaded media. Replace starter policy and business details before accepting real orders.

## Third-party assets

Illustrative photos: Unsplash. Fonts: Google Fonts. AdminLTE / Bootstrap / icon assets retain their respective upstream licences; review bundled licences before redistribution. No project-wide open-source licence is asserted by this README.

## Customer account and admin improvements

- Customer dashboard has order counts, in-progress orders, saved favourites and quick links.
- My Orders supports order-reference/product search and status filters, restricted to the signed-in customer.
- My Reviews shows eligible delivered purchases, saved feedback, moderation status and store replies. Editing sends the review for approval again. A delivered seller shipment qualifies its own items even while another shipment is pending.
- My Returns shows the customer's request status, store updates and manual refund records. Start a request from an eligible delivered order. A refund record does not perform a bank/payment transfer.
- Empty signed-in carts now persist after removing the final item. Address editing/deletion retains a usable default address.
- Admin dashboard highlights pending reviews, returns, seller applications/products and support inquiries. Reviews can be filtered by moderation status.
- Seller account links remain in Seller Central. Storefront footer contrast and responsive spacing are improved.

Validation on 4 November 2026: all seven standalone suites passed, including customer/admin regression checks. Fixtures were rolled back and email tests sent no external mail. Browser checks covered customer overview/reviews/returns, seller security and admin dashboard/pending-review navigation. Payment gateway, courier integrations and automatic payouts/refund transfers remain pending.


## Editable information pages

About, Contact, Shipping, Returns, Privacy, Terms and FAQ include sample development content and a responsive rose/lavender layout. Edit them in Admin → Content pages. Use `## Heading` on a new line to create a section; on FAQ it creates an expandable question. Text before the first heading is the introduction. Content is escaped, not rendered as arbitrary HTML.

The `[DEMO CONTENT]` marker at the start displays a demo badge. Remove it when replacing the copy with final content. The data migration fills only the original placeholder, preserving owner-written text. Run `php artisan migrate` after updating an existing installation. Contact requests save to Admin → Support with a reference; the form does not send an external notification. Sample policy/business details must be replaced before launch.

Validation: `php tests/information-pages.php` covers all seven content pages, FAQ markup, migration preservation, escaped content and Contact persistence/honeypot rejection. Test fixtures roll back.

## SEO controls and launch setup

Products, categories and content pages have independent `seo_title`, `seo_description` and `seo_keywords` fields. Admin can edit all three entity types. Approved sellers can edit these fields only on their own products, using the product editor; saving submits the product for moderation again. Empty fields fall back to the visible name/content. Meta keywords are optional and are not used by Google Search for ranking.

Admin → Store settings → Storefront SEO controls separate metadata for Home, Shop, Categories and Offers. The storefront outputs escaped title/description tags, canonical URLs, Open Graph and Twitter metadata, Product/Offer JSON-LD and breadcrumb JSON-LD. Aggregate ratings use approved reviews only. Pagination canonicals retain their page number, tracking parameters are removed, and search/filter pages are noindex.

`SEO_INDEXABLE=false` is the development default, including when the setting is absent. It adds noindex response headers, blocks crawling in the generated robots file and returns an empty sitemap index. Account, admin, seller, cart, checkout, wishlist, recovery, tracking and API routes remain noindex when public indexing is enabled. Demo-marked content pages remain noindex and are omitted from public sitemaps until the owner replaces their content and removes the marker.

Before launch:

1. Set `APP_URL` to the final HTTPS domain, configure the web root to `public`, replace dummy policies/business details and review product/category content and image alt text.
2. Run `php artisan migrate`, set `SEO_INDEXABLE=true` on the public production deployment and run `php artisan config:clear`. Keep staging and local installations false.
3. Verify `/robots.txt` and `/sitemap.xml`. The sitemap index generates batches of up to 1,000 public product/category/page URLs and excludes hidden products, unapproved sellers/products and demo policy pages.
4. Submit the final public sitemap to Google Search Console. Test actual public product URLs with Google's Rich Results Test and check mobile performance/Core Web Vitals. These external production checks cannot be completed against localhost and rankings/rich results are not guaranteed.

For subdirectory development (`/avelle`), SEO routes are under that prefix. Search engines request robots.txt at the domain root; configure a root robots file on the final host if deploying under a subdirectory. Do not expose configuration, logs or credentials while configuring hosting.

Validation: `php tests/seo.php` checks metadata persistence/escaping, canonical pagination, structured product offers/approved ratings, private indexing headers, sitemap filtering/XML, development guards, field limits, and seller ownership/moderation. SEO plus information-page, catalogue, seller, customer/admin and seller-offer suites passed after these changes; fixtures rolled back.

References: [Google supported meta tags](https://developers.google.com/search/docs/crawling-indexing/special-tags), [developer SEO guide](https://developers.google.com/search/docs/fundamentals/get-started-developers).

## Order, checkout and admin updates

- Customer order details use readable typography, product thumbnails and short order numbers such as AVL-00000049. Existing order URLs continue to work.
- Confirmation and status emails include every ordered product, embedded local product images, quantities, sizes, amounts, delivery address and totals.
- Customers can save multiple delivery addresses, select Deliver to this address, add a new address and edit existing addresses. Earlier orders retain their original delivery details.
- Checkout requires login or registration. Authentication returns customers to the originating local page, preserving its query and fragment. The header displays the signed-in customer's first name.
- Admin lists use searchable, paginated tables with coloured headers and status badges. View links open separate record details and existing update forms.
- Run php artisan migrate after updating. The delivery-address migration imports the latest delivery address only for customers without saved addresses.

Validation: tests/auth-return.php, tests/checkout-addresses.php and tests/order-display.php cover authentication redirects, address ownership and persistence, readable order numbers, all-product email rendering and embedded images. Fixtures roll back and no external emails are sent. Existing catalogue, customer/admin, seller, SEO and information-page regression suites also passed during this update.