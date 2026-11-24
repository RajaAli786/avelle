# Avelle email setup

SMTP support is ready. The current MAIL_MAILER=log saves rendered emails to storage/logs/mail.log without sending them externally. Password-reset links in that local log are sensitive: do not share it.

Edit C:/xampp/htdocs/avelle/.env. Replace MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD and MAIL_FROM_ADDRESS with values from your email provider. Set MAIL_MAILER=smtp to enable delivery. Use MAIL_SCHEME=smtp for STARTTLS on port 587, or smtps for implicit TLS on port 465 when required by the provider. Certificate verification remains enabled. MAIL_FROM_ADDRESS should be a sender your provider permits. MAIL_ADMIN_ADDRESS is optional and receives new-order alerts. SMTP usually requires a provider SMTP/app password rather than your normal account password.

After editing, run from the project directory:

.runtime/php/php.exe artisan config:clear
.runtime/php/php.exe artisan store:mail-test your-email@example.com

The test command sends exactly one test email to the address you provide. In log mode it only previews locally. Initial sends are synchronous. Store transactional failures use the encrypted database outbox and scheduler; a queue worker is not required. Order emails send after the database transaction commits; a mail failure does not cancel an order. Admin → Email delivery shows delivery attempts and manual Retry. Failed store transactional emails retry automatically up to five attempts while the scheduler is running. The explicit mail-test command remains a single immediate setup test.

Included: welcome email, customer order confirmation, optional admin new-order notification, order status/cancellation update and password-reset email. Reset tokens expire after 60 minutes, are stored hashed, are single-use and invalidate previous account sessions after reset. Disabled accounts cannot reset.

APP_URL must match the site address; for current development use http://localhost/avelle. Localhost email links work on this computer only. Set your HTTPS domain when publishing. Replace demo policy/contact details before public launch. Payment gateway remains deferred.


Start `Start-StoreTasks.ps1` after XAMPP starts (or use `Start-Local.ps1`). Stop it with `Stop-StoreTasks.ps1` before shutting down XAMPP. Restart it after Windows restarts. On a server, configure `php artisan schedule:run` every minute. Never commit `.env`, mail logs, database mail payloads or verification/reset links. See README for complete verification, wallet and scheduler behavior.
