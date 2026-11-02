# Avelle email setup

SMTP support is ready. The current MAIL_MAILER=log saves rendered emails to storage/logs/mail.log without sending them externally. Password-reset links in that local log are sensitive: do not share it.

Edit C:/xampp/htdocs/avelle/.env. Replace MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD and MAIL_FROM_ADDRESS with values from your email provider. Set MAIL_MAILER=smtp to enable delivery. Use MAIL_SCHEME=smtp for STARTTLS on port 587, or smtps for implicit TLS on port 465 when required by the provider. Certificate verification remains enabled. MAIL_FROM_ADDRESS should be a sender your provider permits. MAIL_ADMIN_ADDRESS is optional and receives new-order alerts. SMTP usually requires a provider SMTP/app password rather than your normal account password.

After editing, run from the project directory:

.runtime/php/php.exe artisan config:clear
.runtime/php/php.exe artisan store:mail-test your-email@example.com

The test command sends exactly one test email to the address you provide. In log mode it only previews locally. No queue worker is required; messages send synchronously. Order emails send after the database transaction commits; a mail failure does not cancel an order and is recorded without SMTP credentials in storage/logs/laravel.log. Failed transactional emails are not automatically retried.

Included: welcome email, customer order confirmation, optional admin new-order notification, order status/cancellation update and password-reset email. Reset tokens expire after 60 minutes, are stored hashed, are single-use and invalidate previous account sessions after reset. Disabled accounts cannot reset.

APP_URL must match the site address; for current development use http://localhost/avelle. Localhost email links work on this computer only. Set your HTTPS domain when publishing. Replace demo policy/contact details before public launch. Payment gateway remains deferred.
