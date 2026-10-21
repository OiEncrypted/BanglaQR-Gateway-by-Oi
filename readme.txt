=== BanglaQR Gateway by Oi ===
Contributors: oitechnologies
Tags: banglaqr, qrpayment, woocommerce, bkash, nagad
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 0.3.1
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A modern WooCommerce payment gateway supporting Bangladesh Bank's unified BanglaQR standard, MFS (bKash, Nagad, Rocket, Upay, CellFin), and bank QR payments with scan-to-pay popup, live countdown timer, and receipt verification.

== Description ==

**BanglaQR Gateway by Oi** is an interoperable payment gateway plugin for WooCommerce stores operating in Bangladesh. Built to support the unified **BanglaQR** national payment framework established by Bangladesh Bank, this plugin enables merchants to accept payments from any bank app or mobile financial service (MFS) including **bKash, Nagad, Rocket, Upay, CellFin, and participating bank apps** without monthly merchant contract fees.

During checkout, customers are presented with an interactive, responsive scan-to-pay modal. Customers can scan the QR code directly from their mobile banking apps, complete the payment, input their Transaction ID, or upload a payment receipt screenshot.

Backend administrators can configure multiple QR codes via a modern accordion interface, assign customized processing fee percentages per QR account, monitor pending verifications with a live Admin Bar counter and Dashboard Widget, and review customer payment receipts directly inside WooCommerce orders.

== Features ==

* **National BanglaQR Standard:** Fully compatible with Bangladesh Bank's unified interoperable QR system.
* **Universal MFS & Bank App Compatibility:** Works seamlessly with bKash, Nagad, Rocket, Upay, CellFin, and all Bangladeshi bank apps.
* **Interactive Scan-to-Pay Modal:** Sleek, accessible checkout popup with payable amount display, copy buttons, and customizable brand theme color.
* **Customizable Countdown Timer:** Configurable session security timer (5, 10, 15, 20, 30 min, or disable) with single-click extension option.
* **Anti-Fraud Duplicate TrxID Prevention:** Automatically blocks fraudulent re-use of previously submitted Transaction IDs and enforces minimum character requirements.
* **Full-Screen Interactive QR Zoom:** Lightbox zoom view enabling customers to enlarge the QR code for instant, reliable scanning.
* **One-Click QR Code Download:** Mobile-friendly "Save / Download QR Code" button with instant haptic and visual confirmation so mobile shoppers can save the image and scan from gallery.
* **Manual Payment Numbers Support:** Optionally display direct personal/merchant numbers for bKash, Nagad, Rocket, Upay, and CellFin with one-click copy.
* **Flexible Verification Rules:** Configure receipt screenshot and Transaction ID requirements as required, optional, or disabled.
* **1-Click Admin Order Verification:** Approve orders directly into Processing or Reject with notes without navigating away from the order screen.
* **In-Page Receipt Lightbox with Rotation:** Admin lightbox viewer with 90-degree image rotation to inspect receipts conveniently.
* **Admin Audio Alerts:** Synthesizes a clean chime tone notification via Web Audio API whenever new pending orders arrive in the admin dashboard.
* **EXIF Auto-Rotate & Server Optimization:** Automatically detects mobile camera photo orientation, rotates sideways/inverted photos, scales images to 1200px, and compresses files to save disk storage.
* **Storage Protection:** Daily automated cleanup cron for abandoned temporary customer uploads older than 24 hours.
* **WooCommerce HPOS Ready:** 100% compatible with High-Performance Order Storage (HPOS) and classic post orders.
* **Merchant Dashboard Widget & Admin Bar Counter:** Live badge displaying pending BanglaQR payment verifications in the WordPress admin bar and dashboard.
* **Sortable QR Account Manager:** Manage multiple bank accounts or MFS QR codes with custom processing charges.

== Installation ==

1. Upload the `banglaqr-payment-gateway-by-oi` folder to the `/wp-content/plugins/` directory, or install the plugin via the WordPress 'Add New Plugin' screen.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **WooCommerce -> Settings -> Payments -> Bangla QR Payment** to configure your gateway settings and QR accounts.

== Frequently Asked Questions ==

= How does the customer make payment? =
When customers select "Bangla QR Payment" and click Place Order, an interactive scan-to-pay popup opens. The popup displays the QR code, total payable amount (including any payment processing fee), and instructions. The customer scans the QR code using their banking or MFS app, inputs their Transaction ID (TrxID) or uploads a payment slip screenshot, and clicks "Confirm & Place Order".

= Does this require a paid merchant gateway contract? =
No. BanglaQR Gateway by Oi allows merchants to display their standard BanglaQR code (provided by any bank or MFS in Bangladesh) without expensive gateway aggregator contracts.

= Can I charge different processing fees for different QR accounts? =
Yes. You can specify a percentage processing charge (e.g. 1.85% for bKash or 0% for bank transfer) inside each QR account. The fee is dynamically calculated on the net cart total and clearly itemized.

= Are uploaded receipts optimized to save disk space? =
Yes. Uploaded images are compressed client-side, auto-rotated server-side based on EXIF camera orientation, scaled to a maximum of 1200px, and saved without redundant thumbnail generation.

= Where do administrators verify payments? =
Store administrators can view the attached receipt preview and copy the Transaction ID directly on the WooCommerce -> Orders page.

== Screenshots ==

1. QR Accounts Manager & Accordion settings.
2. Core gateway configuration and color theme selector.
3. Interactive checkout scan-to-pay modal with timer and QR zoom.
4. Mobile helper instructions and manual account number options.
5. WooCommerce order review screen with receipt slip and copyable Transaction ID.

== Changelog ==

= 0.3.1 =
* Improvement: Added server-side EXIF auto-rotation to correct mobile camera receipt orientations automatically.
* Improvement: Optimized receipt image storage by resizing to max 1200px, applying 75% quality compression, and bypassing redundant thumbnail sizes.
* Improvement: Enhanced theme CSS isolation for modal close buttons, zoom close icons, and action buttons across third-party WordPress themes.
* Update: Updated WordPress tested compatibility up to 7.0 and WooCommerce tested compatibility up to 9.8.
* Added: Official WordPress.org repository standard readme.txt and icon assets.

= 0.3.0 =
* Added: Live 15-minute countdown timer with time extension capability.
* Added: Mobile-friendly QR code download button and "Scan from Gallery" helper instructions.
* Added: Manual payment fallback accounts (bKash, Nagad, Rocket, Upay, CellFin) with one-click copy.
* Added: Brand theme color picker for checkout modal.
* Added: WordPress Dashboard Widget and Admin Bar pending orders count.
* Added: Daily cleanup cron for orphaned temporary receipt uploads.

= 0.2.0 =
* Added: Full HPOS (High-Performance Order Storage) support.
* Added: Sortable Accordion QR accounts manager.
* Added: Transaction ID input option and configurable verification rules.

= 0.1.0 =
* Initial release of BanglaQR Gateway by Oi.
