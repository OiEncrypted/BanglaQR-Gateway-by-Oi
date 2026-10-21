# 🛡️ Security Policy

## 📋 Supported Versions

Security updates and patches are actively maintained for the latest stable release of the **BanglaQR Gateway by Oi** plugin:

| Version | Status | Supported PHP | Supported WordPress | WooCommerce HPOS |
|:---:|:---:|:---:|:---:|:---:|
| **0.3.x** | :white_check_mark: Active | 7.4 – 8.3+ | 5.6 – 6.7+ | Compatible |
| **< 0.3.0** | :x: End of Life | — | — | — |

---

## 🚨 Reporting a Vulnerability

The **Oi** security team takes all vulnerabilities seriously. If you discover a security issue or vulnerability within this plugin:

> [!CAUTION]
> **Do NOT disclose vulnerabilities publicly.**  
> Please do **not** open a public GitHub issue, discussion, or post on social media. Responsible coordinated disclosure protects store owners and their customers.

### How to Report Privately:
1. **Email:** Send a detailed report to **[support@oitech.com.bd](mailto:support@oitech.com.bd)**.
2. **VDP Portal:** Submit via our [Vulnerability Disclosure Program (VDP)](https://oitech.com.bd/vdp).

### What to Include in Your Report:
- Plugin version affected (e.g. `0.2.0`).
- Vulnerability classification (e.g., IDOR, XSS, CSRF, File Upload bypass, Privilege Escalation).
- Step-by-step reproduction steps or a Proof of Concept (PoC).
- Potential impact and suggested mitigations (if available).

### Response Timeline
- **Initial Acknowledgement:** Within **48 hours** of report receipt.
- **Triage & Assessment:** Within **3 business days**.
- **Fix & Release:** We aim to deploy a security patch promptly and will coordinate disclosure timing with you.

---

## 🔒 Security Architecture & Measures Implemented

**BanglaQR Gateway by Oi** adheres strictly to WordPress, WooCommerce, and OWASP security best practices:

### 1. File Upload Security & Validation
- **AJAX Nonce Verification:** Every receipt upload request (`oi_banglaqr_upload_slip`) requires a verified WordPress nonce token (`oi_banglaqr_upload_slip_action`).
- **Server-Side MIME & Magic Byte Validation:** File payloads are verified using WordPress core's `wp_check_filetype_and_ext()` against real file contents. Only verified image types (`image/jpeg`, `image/png`, `image/webp`, `image/gif`) are accepted.
- **Randomized File Names:** Uploaded files are saved with randomized unique strings (e.g., `receipt_Ab3X9z...jpg`), preventing directory brute-forcing, filename collisions, and path traversal attacks.
- **Non-Executable Storage:** Uploads are stored within the standard WordPress `uploads` directory structure as non-executable media attachments.

### 2. IDOR (Insecure Direct Object Reference) Protection
- **Session-Locked Uploads:** Every uploaded receipt is tagged with the user's active WooCommerce session token (`_oi_banglaqr_uploader_token`) and flagged as a temporary pending upload (`_oi_banglaqr_pending_upload = 1`).
- **Order-Level Ownership Verification:** When checkout is submitted, attachment ownership is validated against the customer's session token using `hash_equals()` before binding to the order (`_oi_banglaqr_receipt_id`). Unrelated visitors cannot bind another user's receipt to their order.
- **Attachment Locking:** Once attached to an order, the receipt is permanently linked to the order as its parent (`post_parent = $order_id`).

### 3. Automated Storage Protection & Cleanup (WP-Cron)
- **Daily Orphan Purge:** A daily background WP-Cron job (`oi_banglaqr_daily_cleanup`) scans for orphaned pending uploads older than 24 hours (from abandoned checkout sessions) and purges both the attachment metadata and the file from disk.
- **Placed Order Protection:** Completed order receipts are strictly protected and excluded from cleanup.

### 4. Direct Access Prevention & Privilege Enforcement
- **Direct Access Guard:** Every PHP file starts with `defined('ABSPATH') || exit;` to prevent direct script execution.
- **Capability Checks:** Gateway settings and administrative interfaces require the `manage_woocommerce` capability.
- **Input Sanitization:** All incoming user data (Transaction IDs, selected QR accounts, customer notes) is sanitized via `sanitize_text_field()`, `sanitize_key()`, and `wp_unslash()`.
- **Output Escaping:** All rendered data attributes and HTML strings are escaped via WordPress native functions (`esc_html`, `esc_attr`, `esc_url`).

---

## 💡 Best Practices for Store Administrators

To maximize the security of your WooCommerce store:

1. **Keep Software Updated:** Regularly update WordPress, WooCommerce, PHP, and this plugin to their latest versions.
2. **Secure Uploads Directory:** Ensure your web server (Nginx/Apache/LiteSpeed) disables script execution (`.php`, `.phtml`) inside the `wp-content/uploads/` directory.
3. **Use HTTPS:** Ensure SSL/TLS is active across your entire store and forced during checkout.
4. **Enforce Strong Admin Credentials:** Protect your `wp-admin` with strong passwords and multi-factor authentication (2FA).

---

## 🏢 Contact & Credits

- **Security Contact:** [support@oitech.com.bd](mailto:support@oitech.com.bd)
- **Developer:** [Oi](https://oitech.com.bd/)
- **Repository:** [BanglaQR Gateway by Oi](https://github.com/Oi-Technologies-Official/BanglaQR-Payment-Gateway-by-Oi)
