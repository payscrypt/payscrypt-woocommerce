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
     * @var string Payscrypt Create Order API url
     */
    public static $create_order_api_url = "/apis/pg/create_order";

    /**
     * @var string Payscrypt Get Order API url
     */
    public static $get_order_api_url = "/apis/pg/get_public_order";

    /**
     * @var string Payscrypt API key.
     */
    public static $api_key;

    /**
     * @var string $pg_wallet_id
     */
    public static $pg_wallet_id;

    /**
     * Create a new charge request.
     *
     * @param int $order_id
     * @param string $description
     * @param  int $amount
     * @param  string $currency
     * @param  string $redirect
     * @return array
     */
    public static function create_charge($order_id = null, $description = null, $amount = null, $currency = null, $redirect = null)
    {
        if (is_null($order_id)) {
            self::log('Error: missing order_id.', 'error');
            return array(false, 'Missing order_id.');
        }
        if (is_null($amount)) {
            self::log('Error: missing amount.', 'error');
            return array(false, 'Missing amount.');
        }
        if (is_null($currency)) {
            self::log('Error: missing currency.', 'error');
            return array(false, 'Missing currency.');
        }

        // only for ETH
        $args = array(
            "merchant_order_id" => $order_id . "", // string
            "description" => $description, // string
            "asset_name" => $currency, // string
            "target_value" => bcmul($amount . "", "1000000000000000000", 0), // string
            "callback_url" => $redirect, // string
            "pg_wallet_id" => self::$pg_wallet_id // int
        );

        $result = self::create_order($args, 'POST');

        self::log("create_charge get response from create_order: " . print_r($result, true));

        return $result;
    }


    /**
     * Create Order in Payscrypt
     *
     * @param  array $params
     * @param  string $method
     * @return array
     */
    public static function create_order($params = array(), $method = 'POST')
    {
        $url = self::$api_endpoint . self::$create_order_api_url;

        $args = array(
            'method' => $method,
            'headers' => array(
                'apikey' => self::$api_key,
                'Content-Type' => 'application/json'
            )
        );

        $args['body'] = json_encode($params);

        self::log("create_order request body: " . print_r($params, true));

        $response = wp_remote_request(esc_url_raw($url), $args);
        if (is_wp_error($response)) {
            self::log('WP response error(create order): ' . $response->get_error_message(), "error");
            return array(false, $response->get_error_message());
        } else {
            $code = $response['response']['code'];

            // 成功状态码200
            if ($code == 200) {
                self::log("create_order response body: " . print_r($response["body"], true));

                $result = json_decode($response['body'], true);
                return array(true, $result);
            } else {
                // 失败状态码有400、401、404，系统返回的报错信息都在header里面，暂时不处理
                self::log('create_order error, response code: ' . $code, "error");

                return array(false, $code);
            }
        }
    }


    /**
     * Get Order detail from Payscrypt
     *
     * @param array $params
     * @param string $method
     * @return array
     */
    public static function get_order($params = array(), $method = 'POST')
    {
        $args = array(
            'method' => $method,
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        );

        $url = self::$api_endpoint . self::$get_order_api_url;

        $args['body'] = json_encode($params);

        self::log('get_order request body: ' . print_r($params, true));

        $response = wp_remote_request(esc_url_raw($url), $args);
        if (is_wp_error($response)) {
            self::log('WP response error(get order): ' . $response->get_error_message(), "error");
            return array(false, $response->get_error_message());
        } else {
            $code = $response['response']['code'];

            // 查询成功状态码200
            if ($code == 200) {
                self::log("get_order response body: " . print_r($response["body"], true));

                $result = json_decode($response['body'], true);
                return array(true, $result);
            } else {
                // 查询失败状态码404
                self::log('get_order error, response code: ' . $code);

                return array(false, $code);
            }
        }
    }
}