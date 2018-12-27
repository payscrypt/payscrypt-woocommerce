<?php

/*
Plugin Name: Payscrypt Woocommerce
Plugin URI: https://payscrypt.com
Description: Payscrypt payment gateway plugin
Version: 1.0
Author: Payscrypt
Author URI: https://payscrypt.com
License: A "Slug" license name e.g. GPL2
*/

add_action('plugins_loaded', 'init_payscrypt_payment_gateway');

function init_payscrypt_payment_gateway()
{
    // If WooCommerce is available, initialise WC parts.
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        require_once dirname(__FILE__) . '/includes/wc-gateway-payscrypt.php';
        add_filter('woocommerce_payment_gateways', 'wc_add_payscrypt');
        add_action('woocommerce_admin_order_data_after_order_details', 'ps_order_meta_general');
        add_action('woocommerce_order_details_after_order_table', 'ps_order_meta_general');
    }
}

// WooCommerce
function wc_add_payscrypt($methods)
{
    $methods[] = 'WC_Gateway_Payscrypt';
    return $methods;
}

/**
 * Add order Payscrypt meta after General and before Billing
 *
 * @see: https://rudrastyh.com/woocommerce/customize-order-details.html
 *
 * @param WC_Order $order WC order instance
 */
function ps_order_meta_general($order)
{ ?>

    <br class="clear"/>
    <h3>Payscrypt Data</h3>
    <div class="">
        <p>Payscrypt Reference # <?php echo esc_html($order->get_meta('_payscrypt_charge_id')); ?></p>
    </div>

    <?php
}
