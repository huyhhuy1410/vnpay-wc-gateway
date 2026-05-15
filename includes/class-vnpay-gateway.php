<?php

/**
 * VNPAY Payment Gateway
 */

defined('ABSPATH') || exit;

/**
 * WC_VNPAY_Gateway Class
 */
if (!class_exists('WC_Payment_Gateway')) {
    return;
}
class WC_VNPAY_Gateway extends \WC_Payment_Gateway
{
    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $enabled;

    /**
     * @var bool
     */
    public $testmode;

    /**
     * @var string
     */
    public $terminal_id;

    /**
     * @var string
     */
    public $secret_key;

    /**
     * @var string
     */
    public $locale;

    /**
     * @var bool
     */
    public $debug;
    /**
     * @var bool
     */
    public $admin_only;

    /**
     * @var string
     */
    public $api_url;
    // fix for logger too
    /**
     * @var \WC_Logger
     */
    protected $logger;
    /**
     * Constructor for the gateway class
     */
    public function __construct()
    {
        // Setup general properties
        $this->id                 = 'vnpay';
        if ($this->get_option('show_logo') != 'no') {
            $this->icon               = apply_filters('woocommerce_vnpay_icon', VNPAY_WOO_PLUGIN_URL . 'assets/images/logo.png');
        }
        $this->has_fields         = false;
        $this->method_title       = __('VNPAY', 'vnpay-wc-gateway');
        $this->method_description = __('Accept payments via VNPAY gateway', 'vnpay-wc-gateway');
        $this->order_button_text  = __('Proceed to VNPAY', 'vnpay-wc-gateway');
        $this->supports           = array('products', 'refunds');

        // Load the form fields
        $this->init_form_fields();

        // Load the settings
        $this->init_settings();

        // Define properties from settings
        $this->title              = $this->get_option('title');
        $this->description        = $this->get_option('description');
        $this->enabled            = $this->get_option('enabled');
        $this->testmode           = 'yes' === $this->get_option('testmode', 'no');
        $this->terminal_id        = $this->get_option('terminal_id');
        $this->secret_key         = $this->get_option('secret_key');
        $this->locale             = $this->get_option('locale', 'vn');


        $this->admin_only         = 'yes' === $this->get_option('admin_only', 'no');
        $this->debug              = 'yes' === $this->get_option('debug', 'no');

        // Set API URLs based on test mode
        $this->api_url = $this->testmode ?
            'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html' :
            'https://pay.vnpay.vn/vpcpay.html';

        // Register hooks
        $this->register_hooks();

        // Initialize logger
        $this->init_logger();
    }

    /**
     * Register necessary hooks
     */
    private function register_hooks()
    {
        // Save settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // API callback hooks
        add_action('woocommerce_api_vnpay_ipn', array($this, 'handle_ipn'));
        add_action('woocommerce_api_vnpay_return', array($this, 'handle_return'));

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }

    /**
     * Initialize form fields for admin settings
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'vnpay-wc-gateway'),
                'type'        => 'checkbox',
                'label'       => __('Enable VNPAY Payment', 'vnpay-wc-gateway'),
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => __('Title', 'vnpay-wc-gateway'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'vnpay-wc-gateway'),
                'default'     => __('VNPAY Payment', 'vnpay-wc-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'vnpay-wc-gateway'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'vnpay-wc-gateway'),
                'default'     => __('Pay securely via VNPAY', 'vnpay-wc-gateway'),
                'desc_tip'    => true,
            ),
            'testmode' => array(
                'title'       => __('Test mode', 'vnpay-wc-gateway'),
                'type'        => 'checkbox',
                'label'       => __('Enable test mode', 'vnpay-wc-gateway'),
                'default'     => 'yes',
                'description' => __('Use sandbox API for testing.', 'vnpay-wc-gateway'),
            ),
            'terminal_id' => array(
                'title'       => __('Terminal ID', 'vnpay-wc-gateway'),
                'type'        => 'text',
                'description' => __('Enter your VNPAY Terminal ID.', 'vnpay-wc-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'secret_key' => array(
                'title'       => __('Secret Key', 'vnpay-wc-gateway'),
                'type'        => 'password',
                'description' => __('Enter your VNPAY Secret Key.', 'vnpay-wc-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'locale' => array(
                'title'       => __('Language', 'vnpay-wc-gateway'),
                'type'        => 'select',
                'description' => __('Select language for the VNPAY payment page.', 'vnpay-wc-gateway'),
                'default'     => 'vn',
                'options'     => array(
                    'vn' => __('Vietnamese', 'vnpay-wc-gateway'),
                    'en' => __('English', 'vnpay-wc-gateway'),
                ),
                'desc_tip'    => true,
            ),
            'show_logo' => array(
                'title' => __('Show logo', 'vnpay-wc-gateway'),
                'type' => 'checkbox',
                'label' => __('Show logo in checkout', 'vnpay-wc-gateway'),
                'default' => 'yes',
            ),
            'admin_only' => array(
                'title'       => __('Only Admin Can See', 'vnpay-wc-gateway'),
                'type'        => 'checkbox',
                'label'       => __('Show this payment method only to admin/store manager on frontend', 'vnpay-wc-gateway'),
                'default'     => 'no',
                'description' => __('When enabled, non-admin users will not see VNPAY at checkout.', 'vnpay-wc-gateway'),
            ),
            'debug' => array(
                'title'       => __('Debug log', 'vnpay-wc-gateway'),
                'type'        => 'checkbox',
                'label'       => __('Enable logging', 'vnpay-wc-gateway'),
                'default'     => 'no',
                'description' => __('Log VNPAY events, such as IPN requests, inside WooCommerce logs.', 'vnpay-wc-gateway'),
            ),

        );
    }

    /**
     * Initialize logger
     */
    protected function init_logger()
    {
        if ($this->debug) {
            if (empty($this->logger)) {
                $this->logger = wc_get_logger();
            }
        }
    }

    /**
     * Log messages if debug is enabled
     *
     * @param string $message Message to log
     */
    protected function log($message)
    {
        if ($this->debug) {
            $this->logger->info($message, array('source' => 'vnpay'));
        }
    }

    /**
     * Check if gateway is available for use
     *
     * @return bool
     */
    public function is_available()
    {
        if ($this->enabled === 'no') {
            return false;
        }

        // If admin-only option enabled, hide gateway on frontend for non-admins
        // Keep available in wp-admin (e.g., for manual orders/tests)
        if ($this->admin_only && !is_admin()) {
            if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
                return false;
            }
        }

        if (empty($this->terminal_id) || empty($this->secret_key)) {
            return false;
        }

        // Only support VND currency
        if (get_woocommerce_currency() !== 'VND') {
            return false;
        }

        return true;
    }

    /**
     * Process the payment
     *
     * @param int $order_id Order ID
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        // Mark as on-hold (we're awaiting the payment)
        $order->update_status('on-hold', __('Awaiting VNPAY payment', 'vnpay-wc-gateway'));

        // Generate payment URL
        $payment_url = $this->generate_payment_url($order);

        // Reduce stock levels
        wc_reduce_stock_levels($order_id);

        // Remove cart
        // WC()->cart->empty_cart(); // Let VNPAY return handler do this

        // Return thank you redirect
        return array(
            'result'   => 'success',
            'redirect' => $payment_url,
        );
    }

    /**
     * Generate VNPAY payment URL
     *
     * @param WC_Order $order Order object
     * @return string Payment URL
     */
    protected function generate_payment_url($order)
    {
        // Set timezone to Vietnam
        date_default_timezone_set('Asia/Ho_Chi_Minh');

        // Generate unique transaction reference
        // $txn_ref = 'BIOHEALTH ORDER#' . $order->get_id();
        // get order number instead of id
        // $order_number = $order->get_order_number();
        $txn_ref = 'BH ' . $order->get_order_number();

        // Save transaction reference to order meta
        $order->update_meta_data('_vnpay_txn_ref', $txn_ref);
        $order->save();

        // Format amount (VNPAY requires amount in smallest unit - VND doesn't have decimals)
        $amount = (int)($order->get_total() * 100);

        // Prepare return URLs
        $return_url = home_url('/wc-api/vnpay_return');
        $ipn_url = home_url('/wc-api/vnpay_ipn');

        // Prepare order info
        $order_info = sprintf(__('BH %s', 'vnpay-wc-gateway'), $order->get_order_number());

        // Build input data
        $input_data = array(

            "vnp_TmnCode" => $this->terminal_id,
            "vnp_Amount" => $amount,
            "vnp_Command" => "pay",
            //make wrong time
            // "vnp_CreateDate" => date('YmdHis', strtotime('-1 hour')),
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $_SERVER['REMOTE_ADDR'],
            "vnp_Locale" => $this->locale,
            "vnp_OrderInfo" => $order_info,
            "vnp_OrderType" => "other",
            "vnp_ReturnUrl" => $return_url,
            "vnp_TxnRef" => $txn_ref,
            "vnp_Version" => "2.1.0",
        );

        // Add IPN URL
        // $input_data['vnp_IpnUrl'] = $ipn_url;

        // Sort array by key for hash calculation
        ksort($input_data);

        // Build hash data
        $hash_data = '';
        $query = '';
        $i = 0;
        foreach ($input_data as $key => $value) {
            if ($i == 1) {
                $hash_data .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hash_data .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        // Generate secure hash
        $secure_hash = hash_hmac('sha512', $hash_data, $this->secret_key);

        // Build final URL
        $payment_url = $this->api_url . '?' . $query . 'vnp_SecureHash=' . $secure_hash;

        // Log the request (Woo logs)
        $this->log('VNPAY Payment URL: ' . $payment_url);
        // Structured DB log
        if (class_exists('VNPAY_Logger')) {
            VNPAY_Logger::log_init(
                $order->get_id(),
                $input_data,
                VNPAY_Logger::get_client_ip(),
                $payment_url,
                (int) $order->get_total()
            );
        }
        // error_log('VNPAY Payment URL: ' . $payment_url);
        return $payment_url;
    }
    /**
     * Validate that the amount hasn't been tampered with
     */
    protected function validate_payment_amount($data, $order)
    {
        // Get the amount from VNPAY (divided by 100 as VNPAY multiplies by 100)
        $vnpay_amount = isset($data['vnp_Amount']) ? (int)($data['vnp_Amount'] / 100) : 0;

        // Get the original order amount (rounded to handle floating point precision)
        $order_amount = (int)$order->get_total();

        // Compare the amounts
        if ($vnpay_amount !== $order_amount) {
            $this->log(sprintf(
                'VNPAY: Amount mismatch for order #%s. Expected: %s, Got: %s',
                $order->get_id(),
                $order_amount,
                $vnpay_amount
            ));
            return false;
        }

        return true;
    }
    /**
     * Handle IPN (Instant Payment Notification) requests
     */
    public function handle_ipn()
    {
        $this->log('VNPAY IPN Request: ' . wc_print_r($_GET, true));

        // Get payment data
        $data = $this->get_payment_data();
        if (empty($data)) {
            if (class_exists('VNPAY_Logger')) {
                VNPAY_Logger::log_ipn(null, $_GET, VNPAY_Logger::get_client_ip(), 'invalid', '99', false);
            }
            echo json_encode(['RspCode' => '99', 'Message' => 'Invalid request']);
            exit;
        }

        // Validate hash
        $sig_ok = $this->validate_payment_hash($data);
        if (!$sig_ok) {
            if (class_exists('VNPAY_Logger')) {
                VNPAY_Logger::log_ipn(null, $data, VNPAY_Logger::get_client_ip(), 'hash_invalid', '97', false);
            }
            echo json_encode(['RspCode' => '97', 'Message' => 'Invalid hash']);
            exit;
        }

        // Get order
        $order = $this->get_order_from_txn_ref($data['vnp_TxnRef']);
        if (!$order) {
            if (class_exists('VNPAY_Logger')) {
                VNPAY_Logger::log_ipn(null, $data, VNPAY_Logger::get_client_ip(), 'order_not_found', '01', true);
            }
            echo json_encode(['RspCode' => '01', 'Message' => 'Order not found']);
            exit;
        }
        // Validate amount
        if (!$this->validate_payment_amount($data, $order)) {
            if (class_exists('VNPAY_Logger')) {
                VNPAY_Logger::log_ipn($order->get_id(), $data, VNPAY_Logger::get_client_ip(), 'amount_mismatch', '04', true);
            }
            echo json_encode(['RspCode' => '04', 'Message' => 'Invalid amount']);
            exit;
        }
        // Check if order is already processed
        if ($order->get_status() !== 'on-hold') {
            if (class_exists('VNPAY_Logger')) {
                VNPAY_Logger::log_ipn($order->get_id(), $data, VNPAY_Logger::get_client_ip(), 'already_processed', '02', true);
            }
            echo json_encode(['RspCode' => '02', 'Message' => 'Order already processed']);
            exit;
        }

        // Process payment response
        if ($data['vnp_ResponseCode'] === '00') {
            // Payment successful
            $this->log('VNPAY IPN: Payment successful for order #' . $order->get_id());

            // Update order status
            $order->payment_complete($data['vnp_TransactionNo']);

            // Add order note
            $order->add_order_note(
                sprintf(__('VNPAY payment completed. Transaction ID: %s', 'vnpay-wc-gateway'), $data['vnp_TransactionNo'])
            );

            // Store payment info
            $this->save_payment_info($order, $data);

            if (class_exists('VNPAY_Logger')) {
                VNPAY_Logger::log_ipn(
                    $order->get_id(),
                    $data,
                    VNPAY_Logger::get_client_ip(),
                    'success',
                    '00',
                    true,
                    isset($data['vnp_TransactionNo']) ? $data['vnp_TransactionNo'] : null,
                    isset($data['vnp_Amount']) ? (int) ($data['vnp_Amount'] / 100) : null
                );
            }
            echo json_encode(['RspCode' => '00', 'Message' => 'Success']);
        } else {
            // Payment failed
            $this->log('VNPAY IPN: Payment failed for order #' . $order->get_id() . ' with code ' . $data['vnp_ResponseCode']);

            // Update order status
            $order->update_status('failed', sprintf(
                __('VNPAY payment failed. Response Code: %s', 'vnpay-wc-gateway'),
                $data['vnp_ResponseCode']
            ));

            if (class_exists('VNPAY_Logger')) {
                VNPAY_Logger::log_ipn(
                    $order->get_id(),
                    $data,
                    VNPAY_Logger::get_client_ip(),
                    'failed',
                    isset($data['vnp_ResponseCode']) ? $data['vnp_ResponseCode'] : '',
                    true,
                    isset($data['vnp_TransactionNo']) ? $data['vnp_TransactionNo'] : null,
                    isset($data['vnp_Amount']) ? (int) ($data['vnp_Amount'] / 100) : null
                );
            }
            echo json_encode(['RspCode' => '00', 'Message' => 'Confirmed fail']);
        }

        exit;
    }

    /**
     * Handle customer return from VNPAY
     */
    public function handle_return()
    {
        $this->log('VNPAY Return Request: ' . wc_print_r($_GET, true));

        // Get payment data
        $data = $this->get_payment_data();
        if (empty($data)) {
            wc_add_notice(__('Payment data is missing.', 'vnpay-wc-gateway'), 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        // Get order
        $order = $this->get_order_from_txn_ref($data['vnp_TxnRef']);
        if (!$order) {
            wc_add_notice(__('Order not found.', 'vnpay-wc-gateway'), 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        // Validate amount - do not change status here (redirect flow must be non-mutating)
        if (!$this->validate_payment_amount($data, $order)) {
            wc_add_notice(__('Payment verification failed: Amount mismatch', 'vnpay-wc-gateway'), 'error');
            if (class_exists('VNPAY_Logger')) {
                $sig_ok = isset($data['vnp_SecureHash']) ? $this->validate_payment_hash($data) : false;
                VNPAY_Logger::log_return(
                    $order->get_id(),
                    $data,
                    VNPAY_Logger::get_client_ip(),
                    'amount_mismatch',
                    '04',
                    $sig_ok,
                    isset($data['vnp_TransactionNo']) ? $data['vnp_TransactionNo'] : null,
                    isset($data['vnp_Amount']) ? (int) ($data['vnp_Amount'] / 100) : null
                );
            }
            wp_redirect($order->get_checkout_payment_url());
            exit;
        }

        // Empty the cart
        WC()->cart->empty_cart();

        // Process based on response code
        if (isset($data['vnp_ResponseCode']) && $data['vnp_ResponseCode'] === '00') {
            // IPN should handle actual payment processing, just redirect to thank you page
            $this->log('VNPAY Return: Customer returning from successful payment for order #' . $order->get_id());
            if (class_exists('VNPAY_Logger')) {
                $sig_ok = $this->validate_payment_hash($data);
                VNPAY_Logger::log_return(
                    $order->get_id(),
                    $data,
                    VNPAY_Logger::get_client_ip(),
                    'received',
                    '00',
                    $sig_ok,
                    isset($data['vnp_TransactionNo']) ? $data['vnp_TransactionNo'] : null,
                    isset($data['vnp_Amount']) ? (int) ($data['vnp_Amount'] / 100) : null
                );
            }
            wp_redirect($order->get_checkout_order_received_url());
        } else {
            // Payment failed or was cancelled
            $this->log('VNPAY Return: Customer returning from failed payment for order #' . $order->get_id());

            // Add notice
            wc_add_notice(
                __('Your payment was not successful. Please try again.', 'vnpay-wc-gateway'),
                'error'
            );
            if (class_exists('VNPAY_Logger')) {
                $sig_ok = isset($data['vnp_SecureHash']);
                VNPAY_Logger::log_return(
                    $order->get_id(),
                    $data,
                    VNPAY_Logger::get_client_ip(),
                    'received',
                    isset($data['vnp_ResponseCode']) ? $data['vnp_ResponseCode'] : '',
                    $sig_ok,
                    isset($data['vnp_TransactionNo']) ? $data['vnp_TransactionNo'] : null,
                    isset($data['vnp_Amount']) ? (int) ($data['vnp_Amount'] / 100) : null
                );
            }

            // Redirect to pay page
            wp_redirect($order->get_checkout_payment_url(true));
        }

        exit;
    }

    /**
     * Process refunds
     * 
     * @param int $order_id Order ID
     * @param float $amount Refund amount
     * @param string $reason Refund reason
     * @return bool|WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        // VNPAY doesn't support API refunds, so we'll just log it
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error('invalid_order', __('Invalid order ID', 'vnpay-wc-gateway'));
        }

        $this->log('VNPAY Manual Refund Processed for Order #' . $order_id . ' - Amount: ' . $amount);

        $order->add_order_note(
            sprintf(
                __('Refund of %s processed manually. VNPAY does not support automatic refunds.', 'vnpay-wc-gateway'),
                wc_price($amount)
            )
        );

        return true;
    }

    /**
     * Get order from transaction reference
     *
     * @param string $txn_ref Transaction reference
     * @return WC_Order|false
     */
    protected function get_order_from_txn_ref($txn_ref)
    {
        if (empty($txn_ref)) {
            return false;
        }

        // Extract order ID from the transaction reference
        // Format: "BIOHEALTH ORDER#123"
        $order_id = 0;
$txn_ref = trim($txn_ref); // Loại bỏ khoảng trắng thừa 2 đầu

if (strpos($txn_ref, 'BH') === 0) {
    // Dùng str_ireplace để không phân biệt hoa thường nếu cần
    $id_part = str_replace('BH', '', $txn_ref);
    $order_id = intval(trim($id_part)); 
} else {
    $parts = explode('_', $txn_ref);
    $order_id = isset($parts[0]) ? intval($parts[0]) : 0;
}


        if (!$order_id) {
            return false;
        }

        // Get order
        $order = wc_get_order($order_id);

        // Verify transaction reference
        if ($order && $order->get_meta('_vnpay_txn_ref') === $txn_ref) {
            return $order;
        }

        return false;
    }

    /**
     * Get payment data from request
     *
     * @return array
     */
    protected function get_payment_data()
    {
        $data = array();

        foreach ($_GET as $key => $value) {
            if (strpos($key, 'vnp_') === 0) {
                $data[$key] = sanitize_text_field($value);
            }
        }

        return $data;
    }

    /**
     * Validate payment hash
     *
     * @param array $data Payment data
     * @return bool
     */
    protected function validate_payment_hash($data)
    {
        if (empty($data['vnp_SecureHash'])) {
            return false;
        }

        $secure_hash = $data['vnp_SecureHash'];
        unset($data['vnp_SecureHash'], $data['vnp_SecureHashType']);

        // Sort data
        ksort($data);

        // Build hash data
        $hash_data = '';
        foreach ($data as $key => $value) {
            if (!empty($hash_data)) {
                $hash_data .= '&';
            }
            $hash_data .= urlencode($key) . '=' . urlencode($value);
        }

        // Calculate hash
        $calculated_hash = hash_hmac('sha512', $hash_data, $this->secret_key);

        return hash_equals($calculated_hash, $secure_hash);
    }

    /**
     * Save payment information to order
     *
     * @param WC_Order $order Order object
     * @param array $data Payment data
     */
    protected function save_payment_info($order, $data)
    {
        // Save transaction ID
        if (!empty($data['vnp_TransactionNo'])) {
            $order->set_transaction_id($data['vnp_TransactionNo']);
            $order->save();
        }

        // Save bank code
        if (!empty($data['vnp_BankCode'])) {
            $order->update_meta_data('_vnpay_bank_code', $data['vnp_BankCode']);
            $order->save();
        }

        // Save card type
        if (!empty($data['vnp_CardType'])) {
            $order->update_meta_data('_vnpay_card_type', $data['vnp_CardType']);
            $order->save();
        }

        // Save payment date
        if (!empty($data['vnp_PayDate'])) {
            $order->update_meta_data('_vnpay_pay_date', $data['vnp_PayDate']);
            $order->save();
        }
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    protected function get_client_ip()
    {
        return WC_Geolocation::get_ip_address();
    }

    /**
     * Enqueue scripts
     */
    public function payment_scripts()
    {
        // Only on checkout page
        if (!is_checkout()) {
            return;
        }

        // Only if this gateway is enabled
        if ($this->enabled === 'no') {
            return;
        }

        // Use minified libraries if SCRIPT_DEBUG is turned off
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        // Enqueue scripts
        wp_enqueue_style('vnpay-wc-gateway-styles', VNPAY_WOO_PLUGIN_URL . 'assets/css/vnpay' . $suffix . '.css', array(), VNPAY_WOO_VERSION);
    }

    /**
     * Get payment response description
     *
     * @param string $response_code Response code
     * @return string
     */
    protected function get_payment_description($response_code)
    {
        $descriptions = array(
            '00' => __('Successful transaction', 'vnpay-wc-gateway'),
            '01' => __('Bank declined transaction', 'vnpay-wc-gateway'),
            '02' => __('Bank declined transaction', 'vnpay-wc-gateway'),
            '03' => __('Merchant not found', 'vnpay-wc-gateway'),
            '04' => __('Invalid transaction', 'vnpay-wc-gateway'),
            '05' => __('Merchant account does not exist', 'vnpay-wc-gateway'),
            '06' => __('Transaction expired', 'vnpay-wc-gateway'),
            '07' => __('Bank declined transaction', 'vnpay-wc-gateway'),
            '08' => __('Invalid card information', 'vnpay-wc-gateway'),
            '09' => __('Invalid card holder', 'vnpay-wc-gateway'),
            '10' => __('Card verification failed', 'vnpay-wc-gateway'),
            '11' => __('Insufficient funds', 'vnpay-wc-gateway'),
            '12' => __('Invalid OTP', 'vnpay-wc-gateway'),
            '13' => __('Transaction limit exceeded', 'vnpay-wc-gateway'),
            '24' => __('User cancelled transaction', 'vnpay-wc-gateway'),
            '51' => __('Insufficient funds', 'vnpay-wc-gateway'),
            '65' => __('Transaction limit exceeded', 'vnpay-wc-gateway'),
            '75' => __('Too many wrong OTP attempts', 'vnpay-wc-gateway'),
            '79' => __('Authentication failed', 'vnpay-wc-gateway'),
            '99' => __('Other errors', 'vnpay-wc-gateway'),
        );

        return isset($descriptions[$response_code]) ? $descriptions[$response_code] : __('Unknown error', 'vnpay-wc-gateway');
    }
}
