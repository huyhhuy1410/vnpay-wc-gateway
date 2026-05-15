<?php

defined('ABSPATH') || exit;

class VNPAY_Logger
{
    const TABLE = 'vnpay_logs';

    public static function is_enabled(): bool
    {
        return (bool) get_option('vnpay_logging_enabled', 1);
    }

    public static function get_client_ip(): string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        // If X-Forwarded-For has multiple IPs, take the first
        if (strpos($ip, ',') !== false) {
            $parts = explode(',', $ip);
            $ip = trim($parts[0]);
        }
        return sanitize_text_field($ip);
    }

    protected static function sanitize_payload($payload): string
    {
        if (is_array($payload) || is_object($payload)) {
            $payload = wp_json_encode($payload);
        }
        $payload = (string) $payload;
        // Truncate to 32KB to avoid huge rows
        if (strlen($payload) > 32768) {
            $payload = substr($payload, 0, 32768);
        }
        return $payload;
    }

    protected static function insert(array $data): void
    {
        if (!self::is_enabled()) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $data = array_merge([
            'created_at' => current_time('mysql'),
        ], $data);
        $wpdb->insert($table, $data);
    }

    public static function log_init($order_id, $params, $ip, $url, $amount = null): void
    {
        self::insert([
            'flow' => 'init',
            'order_id' => $order_id ? (int) $order_id : null,
            'amount' => $amount ? (int) $amount : null,
            'ip' => sanitize_text_field($ip),
            'url' => esc_url_raw((string) $url),
            'status' => 'pending',
            'payload_request' => self::sanitize_payload($params),
        ]);
    }

    public static function log_return($order_id, $query, $ip, $status, $code, $sig_ok, $transaction_no = null, $amount = null): void
    {
        self::insert([
            'flow' => 'return',
            'order_id' => $order_id ? (int) $order_id : null,
            'transaction_no' => $transaction_no ? sanitize_text_field($transaction_no) : null,
            'amount' => $amount ? (int) $amount : null,
            'ip' => sanitize_text_field($ip),
            'status' => sanitize_text_field($status),
            'code' => sanitize_text_field($code),
            'signature_valid' => $sig_ok ? 1 : 0,
            'payload_request' => self::sanitize_payload($query),
        ]);
    }

    public static function log_ipn($order_id, $body, $ip, $status, $code, $sig_ok, $transaction_no = null, $amount = null): void
    {
        self::insert([
            'flow' => 'ipn',
            'order_id' => $order_id ? (int) $order_id : null,
            'transaction_no' => $transaction_no ? sanitize_text_field($transaction_no) : null,
            'amount' => $amount ? (int) $amount : null,
            'ip' => sanitize_text_field($ip),
            'status' => sanitize_text_field($status),
            'code' => sanitize_text_field($code),
            'signature_valid' => $sig_ok ? 1 : 0,
            'payload_request' => self::sanitize_payload($body),
        ]);
    }

    public static function log_error($flow, $message, $order_id = null, $context = null): void
    {
        self::insert([
            'flow' => sanitize_text_field($flow),
            'order_id' => $order_id ? (int) $order_id : null,
            'status' => 'error',
            'message' => wp_kses_post((string) $message),
            'payload_request' => self::sanitize_payload($context),
        ]);
    }
}

?>