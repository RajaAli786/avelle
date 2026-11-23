# Avelle seller marketplace

Register: http://localhost/avelle/seller/apply
Login: http://localhost/avelle/seller/login
Dashboard: http://localhost/avelle/seller

Admin → Sellers: approve or suspend applications, set commission and review seller products. New applications start pending at 10% commission. No seller can choose their own approval, owner ID or commission. Existing customer accounts can apply while signed in.

After approval, sellers can add their own product images, descriptions, additional information and size/colour inventory. Product and gallery changes require moderation again. Changing business details resubmits the seller profile. Public products require both active seller approval and product approval.

A mixed bag creates one customer order with separate fulfilment records for each seller and the Avelle store. Every seller sees only its items and necessary delivery details. Each shipment progresses placed → confirmed → shipped → delivered. Carrier and tracking number are required at shipping. The main order follows the least-progressed shipment. Full-order cancellation is blocked after any dispatch. Existing historical orders are not automatically split or assigned to sellers.

Commission is captured at checkout on the discounted merchandise value (not shipping), rounded to whole rupees. Changing commission later does not rewrite old orders. Seller earnings are estimates, not a withdrawable balance. Admin can record externally processed payouts after delivery, payment collection and expiry of the return window. Any non-rejected return blocks unadjusted settlement. Adjusted return settlements, banking/KYC, automatic transfers and seller return case management remain pending. Return handling stays with admin.

Upgrade an existing checkout: back up database and media, pull changes, run php artisan migrate and php artisan view:clear. Never run migrate:fresh on existing data.

Test: php tests/sellers.php (37 rollback checks). Existing auth/checkout, image catalogue and email suites also passed. No real money is transferred by these tests or the payout record form.

Seller Central now uses a dedicated AdminLTE sidebar interface. Offers & coupons lets approved sellers target their own products/categories, set percentage/fixed values, minimum eligible subtotal, cap, expiry and usage limit, and advertise publicly. Admin can lock offers; seller edits cannot bypass that lock. One code per order; coupon stacking is not supported. Used codes cannot be renamed.

Account & password now opens Seller Central → Account & Security (/seller#seller-account), with dedicated seller account and password forms. Email changes require the current password; password changes require at least 12 characters and confirmation. Existing account passwords are not changed by installing this update. Validation: php tests/seller-account.php (13 rollback checks).

Product editors include optional SEO title, meta description and comma-separated keywords. Sellers can change only their own product metadata. Blank fields use automatic fallbacks. Any product save submits it for approval again. Category and site-wide metadata stays with admin. See README SEO controls for production indexing setup.
