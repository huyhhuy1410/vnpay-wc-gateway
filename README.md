# VNPAY Payment Gateway for WooCommerce

A WordPress/WooCommerce plugin that adds VNPAY as a checkout payment method. It supports payment URL generation, customer return handling, VNPAY IPN verification, order status updates, and operational logging for payment events.

## What It Does

- Adds a VNPAY payment gateway to WooCommerce checkout.
- Generates signed VNPAY payment URLs from WooCommerce orders.
- Handles customer return callbacks after payment.
- Handles VNPAY IPN callbacks for server-side payment confirmation.
- Validates payment signatures with HMAC SHA-512.
- Validates paid amount against the WooCommerce order total.
- Stores transaction metadata on the WooCommerce order.
- Provides optional debug logging into a custom database table.
- Adds automatic log retention with a daily scheduled cleanup job.
- Declares WooCommerce HPOS compatibility.

## Main Features

- WooCommerce gateway settings for enable/disable, title, description, test mode, terminal ID, secret key, language, logo display, admin-only visibility, and debug logging.
- Payment lifecycle support: initialize payment, redirect customer, validate callback, update order, empty cart on success, and show failure notices.
- IPN response mapping for VNPAY response codes.
- Manual refund note support, since VNPAY automatic refunds are not implemented.
- Client IP detection and sanitized payload logging for debugging.

## Technical Notes

- Main gateway class: `WC_VNPAY_Gateway`
- Logger class: `VNPAY_Logger`
- WooCommerce hooks:
  - `woocommerce_payment_gateways`
  - `woocommerce_update_options_payment_gateways_vnpay`
  - `woocommerce_api_vnpay_ipn`
  - `woocommerce_api_vnpay_return`
- Activation creates a `vnpay_logs` table and schedules `vnpay_log_retention`.
- Hash validation removes `vnp_SecureHash` and `vnp_SecureHashType`, sorts request fields, builds the hash string, then compares against the received secure hash.

## Installation

1. Upload the plugin folder to `wp-content/plugins/vnpay-wc-gateway`.
2. Activate the plugin in WordPress Admin.
3. Go to WooCommerce payment settings.
4. Enable VNPAY and enter the merchant terminal ID and secret key.
5. Configure VNPAY return/IPN URLs according to your WooCommerce site URL.

