# AVELLE — storefront and store administration
Laravel 13.39, PHP 8.4.27, MySQL/MariaDB, AdminLTE 4.10.0.

## On this computer
Project: C:/xampp/htdocs/avelle
Store: http://localhost/avelle/
Admin: http://localhost/avelle/admin
Customer profile: http://localhost/avelle/account
Start Apache and MySQL from XAMPP. The /avelle alias serves the public directory with the isolated .runtime/php PHP 8.4 CGI handler. Other XAMPP projects retain their original PHP handler.
Database: avelle_store on 127.0.0.1:3306. Current root/blank credentials are for local development.

## Administrator access
Authentication now uses the users database table. The original configured owner password was imported without changing it; the imported login email is owner@avelle.local unless ADMIN_EMAIL was supplied during migration.
The legacy ADMIN_PASSWORD_HASH setting is not used for sign-in.
To create/reset an admin from a local terminal:
cd C:/xampp/htdocs/avelle
./.runtime/php/php.exe artisan store:admin
This prompts for admin email, display name, password and confirmation. No default password is shipped.

In the admin:
- My admin account: edit your name, login email, mobile and password. Changing your own password requires the current password and a new password of at least 12 characters.
- Users & customers: manage names, login email and mobile; set a new password with confirmation; enable or disable accounts.
- Disabling an account blocks sign-in and terminates its sessions. Password resets, email changes and status changes rotate a credential version to revoke previous sessions.
- The current administrator cannot disable their own account. At least one active administrator is required.
- Passwords are stored as bcrypt hashes and are never displayed.

## Customer authentication and profiles
Customers can register, sign in, sign out, edit their profile/delivery address, and change their password with their current password.
A new guest checkout requires a confirmed password of at least 10 characters. The successful order transaction creates the customer account, associates the order by user_id and signs the customer in.
An existing email must sign in first; guest checkout cannot claim an existing profile.
A signed-in customer's email is taken from their authenticated account, not trusted from checkout input.
The account page shows only that customer's orders, statuses, delivery snapshots and totals. Legacy guest orders are not automatically attached by email.
Email verification, password reset by email, OTP and outbound email/SMS still need a configured delivery service. There is no simulated password-reset flow.

## Storefront and administration
New shopping layout with search, category navigation, product cards, size selection, wishlist, bag and COD checkout.
AdminLTE 4 sidebar, dashboard, catalogue, inventory, order status updates, customer/user management and live offer settings. The core AdminLTE, Bootstrap and icon assets are served locally.
Stock and order totals are validated and calculated on the server, using database transactions.
Cancellation restores inventory and releases offer usage. Delivered and cancelled orders are final.

## Active offers
Coupons/referrals support fixed INR or percentage discounts, store/category/product scope, minimum subtotal, discount cap, expiry, total usage limit and pause/reactivate.
Public coupons have a storefront headline, visibility switch and display mode:
- Marquee + soft pulse
- Marquee only
- Soft pulse only
- Static card
Public offers appear in the scrolling strip, offer cards and matching product highlights. Pause/hover/focus stops scrolling; reduced-motion preference disables animation.
The storefront refreshes offer data every 30 seconds and on returning to the tab; expiry is also checked locally each second. Paused, expired, exhausted and hidden coupons are not advertised. Referral codes remain private.
Product/category targeting controls which products an offer applies to. Minimum subtotal and caps still apply at checkout.
One code per order. Referrals are first-order only, checked against both account ID and email, and cannot match the owner email. Automatic referrer rewards/wallet payouts are not included.
Example seeded code HELLO10: 10%, minimum ₹499, maximum ₹300. Existing owner-managed offers are preserved.
Shipping is ₹79, free above ₹1,499 subtotal before discount.

## Clean installation
PHP 8.3+ (8.4 recommended), Composer, MySQL; enable pdo_mysql, openssl, mbstring, curl, fileinfo, intl, zip.
Run composer install; copy .env.example to .env; configure database; php artisan key:generate; php artisan migrate --seed; php artisan store:admin.
Apache DocumentRoot/Alias must target public, never the project root. deploy/httpd-avelle.conf shows this computer's alias/CGI setup; a compatible PHP runtime is required in .runtime/php.
public/.htaccess uses RewriteBase /avelle/; change it for a different deployment path.
For a development-only server, php artisan serve also works with the compatible PHP binary.

## Validation
Run ./.runtime/php/php.exe tests/smoke.php in the project folder. 48 checks cover authentication, account ownership, server totals, stock, discount scopes/limits, public offer visibility, user management, session revocation and administrator protections. Test fixtures are rolled back.
Also verified in the browser: admin DB sign-in, AdminLTE navigation and offer controls, customer registration/profile, narrow-mobile and desktop layouts.
An actual local HTTP checkout verified automatic profile creation and order visibility. Its synthetic order/accounts were removed and stock restored.

## Before launch
Replace sample product photos/descriptions/ratings and configure real support, delivery and returns policies. Payment gateway, courier integration, email/SMS, email verification/recovery, tax invoicing, returns/refunds and referral rewards remain separate integrations.
Use HTTPS, APP_ENV=production, APP_DEBUG=false, secure cookies and a dedicated database user. Keep .env and runtime internals private. Back up your database.
Illustrative photos: Unsplash. Fonts: Google Fonts.
AdminLTE: https://docs.adminlte.io/html/introduction — MIT license bundled with the local assets.
