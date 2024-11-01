<?php

namespace Triplea\WcTripleaCryptoPayment;

class Admin_Notice {

    /**
     * Print notices if required plugins are not installed or active
     * @return void
     */
    public function check_require_plugin_notice(){

        $wc_title = __('WooCommerce', 'wc-triplea-crypto-payment' );
        $wc_url   = wp_nonce_url( 'https://wordpress.org/plugins/woocommerce/' );

        $notice = sprintf(
            /* translators: 1: Plugin name 2: WC title & installation link 3: WCS title & installation link */
            __('%1$s requires %2$s & %3$s to be installed and activated to function properly.', 'wc-triplea-crypto-payment'),
            '<strong>' . __( 'Crypto Payment Gateway for WooCommerce', 'wc-triplea-crypto-payment' ) . '</strong>',
            '<a href="' . esc_url( $wc_url ) . '" target="_blank">' . $wc_title . '</a>'
        );

        printf('<div class="notice notice-warning is-dismissible"><p style="padding: 13px 0">%1$s</p></div>', $notice);
    }
}