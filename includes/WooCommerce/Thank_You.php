<?php

/**
 * Handle hooks for the WooCommerce Thank You page.
 *
 * @see templates/checkout/thankyou.php
 */

namespace Triplea\WcTripleaCryptoPayment\WooCommerce;

use WC_Order;
use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Add a note to the Thank You page.
 *
 * Class Thank_You
 *
 * @package Triplea\WcTripleaCryptoPayment\WooCommerce
 */
class Thank_You
{

    /**
     * TODO If payment method was Cryptocurrency, and if our payment gateway was used, and tx result is paid too little...
     * then display a message.
     *
     * @hooked woocommerce_thankyou_order_received_text
     * @see templates/checkout/thankyou.php
     *
     * @param string   $str
     * @param WC_Order $order
     *
     * @return string
     */
    public static function triplea_change_order_received_text($str, $order)
    {

        if (isset($order) && $order && $order->has_status('failed')) {

            $new_str = $str . '<br>'
                . 'Your order was placed. However, your payment was either insufficient or not detected.';
            return $new_str;
        }

        return $str;
    }

    public static function thankyou_page_payment_details($order_id)
    {

        $wc_order = wc_get_order($order_id);
        if (empty($wc_order)) {
            return;
        }

        if (isset($triplea->settings['triplea_woocommerce_order_states']) && isset($triplea->settings['triplea_woocommerce_order_states']['paid'])) {
            $order_status_invalid = $triplea->settings['triplea_woocommerce_order_states']['invalid'];
        } else {
            $order_status_invalid = 'wc-failed';
        }

        $block_order_status_update = FALSE;
        $order_status = $wc_order->get_status();
        if ('wc-' . $order_status == $order_status_invalid || $order_status == 'failed' || strpos($order_status, 'fail') !== FALSE || strpos($order_status, 'invalid') !== FALSE) {
            $block_order_status_update = TRUE;
        }

        if (OrderUtil::custom_orders_table_usage_is_enabled()) {
            $order = wc_get_order($order_id);

            $payment_tier       = $order->get_meta('_triplea_payment_tier', true);
            $crypto_amount      = $order->get_meta('_triplea_order_crypto_amount', true);
            $order_amount       = $order->get_meta('_triplea_order_amount', true);
            $amount_paid        = $order->get_meta('_triplea_amount_paid', true);
            $crypto_amount_paid = $order->get_meta('_triplea_crypto_amount_paid', true);
            $crypto_currency    = $order->get_meta('_triplea_crypto_currency', true);
            $order_currency     = $order->get_meta('_triplea_order_currency', true);
        } else {
            $payment_tier       = get_post_meta($order_id, '_triplea_payment_tier', true);
            $crypto_amount      = get_post_meta($order_id, '_triplea_order_crypto_amount', true);
            $order_amount       = get_post_meta($order_id, '_triplea_order_amount', true);
            $amount_paid        = get_post_meta($order_id, '_triplea_amount_paid', true);
            $crypto_amount_paid = get_post_meta($order_id, '_triplea_crypto_amount_paid', true);
            $crypto_currency    = get_post_meta($order_id, '_triplea_crypto_currency', true);
            $order_currency     = get_post_meta($order_id, '_triplea_order_currency', true);
        }


        if ($block_order_status_update) {
            echo '<p style="font-size: 115%">';
            echo 'Your cryptocurrency payment was detected.' . '<br>';
            echo 'However, an irregularity has been detected. Please reach out to us for a refund.' . '<br>';
            echo '</p>';
            echo '<br>';
        } elseif ($payment_tier === 'short') {
            echo '<p style="font-size: 115%">';
            echo 'Your order was placed.' . '<br>';
            echo '<strong>It seems you paid too little</strong>. ' . '<br>';
            echo 'You paid: ' . '<strong>' . $crypto_currency . ' ' . number_format($crypto_amount_paid, 8) . '</strong>' . ' (' . $order_currency . ' ' . $amount_paid . ')' . '<br>';
            echo 'instead of: ' . '<strong>' . $crypto_currency . ' ' . number_format($crypto_amount, 8) . '</strong>' . ' (' . $order_currency . ' ' . $order_amount . ').' . '<br>';
            echo '</p>';
            echo '<br>';

            // TODO Come up with information for the user : top up or get refund or so?
        } elseif ($payment_tier === 'good' || $payment_tier === 'hold') {
            echo '<p style="font-size: 115%">';
            echo 'Your payment was detected.' . '<br>';
            echo 'As soon as your payment has been validated, your order will be processed.' . '<br>';
            echo '</p>';
            echo '<br>';
        } elseif (!empty($payment_tier)) {
            echo '<p style="font-size: 115%;">';
            echo '<span style="color: red;">There was an error detecting your payment.</span>' . '<br>';
            echo 'It might take a while for your payment transaction to be detected, after which your order will automatically be updated.' . '<br>';
            echo '</p>';
            echo '<br>';
        }
    }
}
