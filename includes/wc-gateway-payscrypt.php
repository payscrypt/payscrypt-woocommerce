<?php


if (!defined('ABSPATH')) {
    exit;
}


class WC_Gateway_Payscrypt extends WC_Payment_Gateway
{
    /**
     * @var bool Whether or not logging is enabled
     */
    public static $log_enabled = false;

    private $debug;

    /**
     * @var WC_Logger Logger instance
     */
    public static $log = false;


    public function __construct()
    {
        $this->id = 'payscrypt';
        $this->has_fields = false;
        $this->order_button_text = __('Proceed to Payscrypt', 'payscrypt');
        $this->method_title = __('Payscrypt', 'payscrypt');
        $this->method_description = '<p>' .
            __('A payment gateway that sends your customers to Payscrypt to pay with cryptocurrency.', 'payscrypt')
            . '</p><p>' .
            sprintf(
                __('If you do not currently have a Payscrypt account, you can set one up here: %s', 'payscrypt'),
                '<a target="_blank" href="https://www.payscrypt.com/">https://www.payscrypt.com/</a>'
            );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->debug = 'yes' === $this->get_option('debug', 'no');
        self::$log_enabled = $this->debug;

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_filter('woocommerce_order_data_store_cpt_get_orders_query', array($this, '_custom_query_var'), 10, 2);
        add_action('woocommerce_api_wc_gateway_payscrypt', array($this, 'handle_webhook'));
    }


    /**
     * Init Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Payscrypt', 'payscrypt'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default' => __('Bitcoin and other cryptocurrencies', 'payscrypt'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                'default' => __('Pay with Bitcoin or other cryptocurrencies.', 'payscrypt'),
            ),
            "api_endpoint" => array(
                "title" => _("API Endpoint", "payscrypt"),
                "type" => "text",
                "default" => "https://payscrypt.com",
                'description' => sprintf(__('Payscrypt api endpoint', 'payscrypt')),
            ),
            "api_key" => array(
                "title" => _("API Key", "payscrypt"),
                "type" => "text",
                "default" => "",
                'description' => sprintf(
                    __('You can manage your API Key within the PayScrypt settings page, available here: %s', 'payscrypt'),
                    esc_url('https://www.payscrypt.com/')),
            ),
            "pg_wallet_id" => array(
                "title" => _("PG Wallet Id", "payscrypt"),
                "type" => "text",
                "default" => "",
                'description' => sprintf(__('You can get your PG Wallet Id within the Payscrypt settings page', 'payscrypt')),
            ),
            'webhook_setting' => array(
                'title' => __('Webhook Setting', 'payscrypt'),
                "type" => "text",
                "default" => __("Do not change me! Read the information below.", "payscrypt"),
                'description' =>
                    __('Using webhook allows Payscrypt to send payment confirmation messages to the website. To fill this out:', 'payscrypt')
                    . '<br /><br />' .
                    __('1. In your Payscrypt settings page', 'payscrypt')
                    . '<br />' .
                    sprintf(__('2. Click \'Modify Webhook\' and paste the following URL: %s', 'payscrypt'), add_query_arg('wc-api', 'WC_Gateway_Payscrypt', home_url('/', 'https')))
            ),
            'show_icons' => array(
                'title' => __('Show icons', 'payscrypt'),
                'type' => 'checkbox',
                'label' => __('Display currency icons on checkout page.', 'payscrypt'),
                'default' => 'yes',
            ),
            'debug' => array(
                'title' => __('Debug log', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable logging', 'woocommerce'),
                'default' => 'no',
                'description' => sprintf(__('Log Payscrypt API events inside %s', 'payscrypt'), '<code>' . WC_Log_Handler_File::get_log_file_path('payscrypt') . '</code>'),
            ),
        );
    }


    /**
     * Process the payment and return the result.
     *
     * @param  int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        $this->init_api();

        // Create description for charge based on order's products.
        // Ex: 1 x Product1, 2 x Product2
        try {
            $order_items = array_map(function ($item) {
                return $item['quantity'] . ' x ' . $item['name'];
            }, $order->get_items());

            $description = mb_substr(implode(', ', $order_items), 0, 200);
        } catch (Exception $e) {
            $description = null;
        }

        // Create a new charge.
        $result = Payscrypt_API_Handler::create_charge(
            $order_id,
            $description,
            $order->get_total(),
            get_woocommerce_currency(),  // ETH
            $this->get_return_url($order)
        );

        self::log("process_payment get response from create_charge: " . print_r($result, true));

        if (!$result[0]) {
            return array('result' => 'fail');
        }

        $charge = $result[1]['order'];

        $order->update_meta_data('_payscrypt_charge_id', $charge['code']);
        $order->save();

        return array(
            'result' => 'success',
            'redirect' => $charge["redirect_url"],
        );
    }


    /**
     * Init the API class and set the API key etc.
     */
    protected function init_api()
    {
        include_once dirname(__FILE__) . '/payscrypt-api-handler.php';

        Payscrypt_API_Handler::$log = get_class($this) . '::log';
        Payscrypt_API_Handler::$api_endpoint = $this->get_option('api_endpoint');
        Payscrypt_API_Handler::$api_key = $this->get_option('api_key');
        Payscrypt_API_Handler::$pg_wallet_id = $this->get_option("pg_wallet_id");
    }


    /**
     * Handle requests sent to webhook.
     */
    public function handle_webhook()
    {
        $payload = file_get_contents('php://input');
        if (!empty($payload)) {
            $data = json_decode($payload, true);

            self::log('Webhook received: ' . print_r($data, true));

            $pg_order_id = $data["id"]; // string
            $order_id = $data["merchant_order_id"];  // string

            // 收到webhook之后，查询最新的order信息、更新到数据库
            $args = array(
                "id" => $pg_order_id, // string
                "with_payments" => false
            );

            $this->init_api();

            $result = Payscrypt_API_Handler::get_order($args);

            self::log("handle_webhook get response from get_order: " . print_r($result, true));

            if (!$result[0]) {
                self::log('Payscrypt can not find order, pg_order_id: ' . $pg_order_id);
            }

            $new_status = $result[1]["order"]["status"];

            $this->_update_order_status(wc_get_order(intval($order_id)), $new_status);

            exit;
        }

        wp_die('Payscrypt Webhook Request Failure', 'Payscrypt Webhook', array('response' => 500));
    }


    /**
     * Update the status of an order.
     *
     * @param  WC_Order $order
     * @param string $new_status
     */
    public function _update_order_status($order, $new_status)
    {
        // TODO: 更新meta_data 这个有没有意义？
        $order->update_meta_data('_payscrypt_status', $new_status);

        self::log("status 1: " . print_r($order->get_status(), true));

        // TODO:
        // 这里的status可能不是完全对应得上WC的订单状态，暂时先这样。
        // 如果后续有需要再注册新的状态。
        if ("PENDING" === $new_status) {
            $order->update_status('pending', __('Payscrypt payment pending.', 'payscrypt'));
        } elseif ("CONFIRMING" === $new_status) {
            $order->update_status('on-hold', __('Payscrypt payment detected, but awaiting blockchain confirmation.', 'payscrypt'));
        } elseif ("SUCCESSFUL" === $new_status) {
            $order->payment_complete();
        } elseif ("EXPIRED" === $new_status) {
            $order->update_status('failed', __('Payscrypt payment expired.', 'payscrypt'));
        } elseif ("CANCELED" === $new_status) {
            $order->update_status('cancelled', __('Payscrypt payment cancelled.', 'payscrypt'));
        }

        self::log("status 2: " . print_r($order->get_status(), true));

        // Archive if in a resolved state and idle more than timeout.
        if (in_array($new_status, array('SUCCESSFUL', 'EXPIRED', 'CANCELED'), true) &&
            $order->get_date_modified() < $this->timeout) {
            self::log('Archiving order: ' . $order->get_order_number());
            $order->update_meta_data('_payscrypt_archived', true);
        }
    }


    /**
     * Get the cancel url.
     *
     * @param WC_Order $order Order object.
     * @return string
     */
    public function get_cancel_url($order)
    {
        $return_url = $order->get_cancel_order_url();

        if (is_ssl() || get_option('woocommerce_force_ssl_checkout') == 'yes') {
            $return_url = str_replace('http:', 'https:', $return_url);
        }

        return apply_filters('woocommerce_get_cancel_url', $return_url, $order);
    }


    /**
     * Handle a custom 'payscrypt_archived' query var to get orders payed through Payscrypt with the '_payscrypt_archived' meta.
     *
     * @param array $query - Args for WP_Query.
     * @param array $query_vars - Query vars from WC_Order_Query.
     * @return array modified $query
     */
    public function _custom_query_var($query, $query_vars)
    {
        if (array_key_exists('payscrypt_archived', $query_vars)) {
            $query['meta_query'][] = array(
                'key' => '_payscrypt_archived',
                'compare' => $query_vars['payscrypt_archived'] ? 'EXISTS' : 'NOT EXISTS',
            );
            // Limit only to orders payed through Coinbase.
            $query['meta_query'][] = array(
                'key' => '_payscrypt_charge_id',
                'compare' => 'EXISTS',
            );
        }

        return $query;
    }


    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level Optional. Default 'info'.
     * emergency|alert|critical|error|warning|notice|info|debug
     */
    public static function log($message, $level = 'info')
    {
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->log($level, $message, array('source' => 'payscrypt'));
        }
    }


    /**
     * Get gateway icon.
     *
     * @return string
     */
    public function get_icon()
    {
        if ($this->get_option('show_icons') === 'no') {
            return '';
        }

        $image_path = plugin_dir_path(__FILE__) . 'assets/images';
        $icon_html = '';
        $methods = get_option('payscrypt_payment_methods', array('ethereum'));

        // Load icon for each available payment method.
        foreach ($methods as $m) {
            $path = realpath($image_path . '/' . $m . '.png');
            if ($path && dirname($path) === $image_path && is_file($path)) {
                $url = WC_HTTPS::force_https_url(plugins_url('/assets/images/' . $m . '.png', __FILE__));
                $icon_html .= '<img width="26" src="' . esc_attr($url) . '" alt="' . esc_attr__($m, 'payscrypt') . '" />';
            }
        }

        return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
    }
}
