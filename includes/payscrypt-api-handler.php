<?php

if (!defined('ABSPATH')) {
    exit;
}

class Payscrypt_API_Handler
{
    /**
     * @var string/array Log variable function.
     */
    public static $log;

    /**
     * Call the $log variable function.
     *
     * @param string $message Log message.
     * @param string $level Optional. Default 'info'.
     * emergency|alert|critical|error|warning|notice|info|debug
     * @return null
     */
    public static function log($message, $level = 'info')
    {
        return call_user_func(self::$log, $message, $level);
    }

    /**
     * @var string Payscrypt API Endpoint
     */
    public static $api_endpoint;

    /**
     * @var string Payscrypt Create Invoice API url
     */
    public static $create_invoice_api_url = "/payment/v1/invoice";

    /**
     * @var string Payscrypt Get Invoice API url
     */
    public static $get_invoice_api_url = "/payment/v1/invoice/";

    /**
     * @var string Payscrypt API key.
     */
    public static $api_key;

    /**
     * @var string $wallet_id
     */
    public static $wallet_id;

    /**
     * Create a new charge request.
     *
     * @param int $invoice_id
     * @param string $description
     * @param  int $price
     * @param  string $asset
     * @param  string $redirect
     * @return array
     */
    public static function create_charge($invoice_id = null, $description = null, $price = null, $asset = null, $redirect = null)
    {
        if (is_null($invoice_id)) {
            self::log('Error: missing invoice_id.', 'error');
            return array(false, 'Missing invoice_id.');
        }
        if (is_null($price)) {
            self::log('Error: missing price.', 'error');
            return array(false, 'Missing price.');
        }
        if (is_null($asset)) {
            self::log('Error: missing asset.', 'error');
            return array(false, 'Missing asset.');
        }

        $args = array(
            "merchant_invoice_id" => $invoice_id . "", // merchant_invoice_id: string
            "wallet_id" => self::$wallet_id, // wallet_id: int
            "redirect_url" => $redirect, // callback_url: string
            "description" => $description, // description: string
            "asset" => $asset // asset: string
        );

        // $asset: string
        if ($asset == "LCCN") {
            $args["price"] = bcmul($price . "", "1000000000000000000", 0); // string
        } elseif ($asset == "ETH") {
            $args["price"] = bcmul($price . "", "1000000000000000000", 0); // string
        } elseif ($asset == "BTC") {
            $args["price"] = bcmul($price . "", "100000000", 0); // string
        } else {
            return array(false, "Unsupport asset.");
        }

        $result = self::create_invoice($args, 'POST');

        self::log("create_charge get response from create_order: " . print_r($result, true));

        return $result;
    }


    /**
     * Create Invoice in Payscrypt
     *
     * @param  array $params
     * @param  string $method
     * @return array
     */
    public static function create_invoice($params = array(), $method = 'POST')
    {
        $url = self::$api_endpoint . self::$create_invoice_api_url;

        $args = array(
            'method' => $method,
            'headers' => array(
                'apikey' => self::$api_key,
                'Content-Type' => 'application/json'
            )
        );

        $args['body'] = json_encode($params);

        self::log("create_invoice request: " . print_r($args, true));

        $response = wp_remote_request(esc_url_raw($url), $args);
        if (is_wp_error($response)) {
            self::log('WP response error(create_invoice): ' . $response->get_error_message(), "error");

            return array(false, $response->get_error_message());
        } else {
            $code = $response['response']['code'];

            // Success
            if ($code == 200) {
                self::log("create_invoice response: " . print_r($response, true));

                $result = json_decode($response['body'], true);
                return array(true, $result);
            } else {
                // Error info is in the headers
                self::log('create_invoice error, response code: ' . $code, "error");

                return array(false, $code);
            }
        }
    }


    /**
     * Get Invoice detail from Payscrypt
     *
     * @param array $params
     * @param string $method
     * @return array
     */
    public static function get_invoice($params = array(), $method = 'GET')
    {
        $args = array(
            'method' => $method,
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        );

        $url = self::$api_endpoint . self::$get_invoice_api_url . '/' . $params['id'];

        //$args['body'] = json_encode($params);

        self::log('get_invoice request: ' . print_r($args, true));

        $response = wp_remote_request(esc_url_raw($url), $args);
        if (is_wp_error($response)) {
            self::log('WP response error(get_invoice): ' . $response->get_error_message(), "error");

            return array(false, $response->get_error_message());
        } else {
            $code = $response['response']['code'];

            // Success
            if ($code == 200) {
                self::log("get_invoice response: " . print_r($response, true));

                $result = json_decode($response['body'], true);
                return array(true, $result);
            } else {
                // 404 not found
                self::log('get_invoice error, response code: ' . $code);

                return array(false, $code);
            }
        }
    }
}
