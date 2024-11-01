<?php

namespace Triplea\WcTripleaCryptoPayment;

/**
 * Installer class
 */
class Installer {

    /**
     * Run the installer
     * @return void
     */
    public function run() {
        $this->add_version();
        $this->load_plugin_textdomain();
    }

    /**
     * Add time and version on DB
     */
    public function add_version(){
        $installed = get_option( 'wc_triplea_crypto_payment_installed' );

        if ( ! $installed ) {
            update_option( 'wc_triplea_crypto_payment_installed', strtotime("now" ) );
        }

        update_option( 'wc_triplea_crypto_payment_version', WC_TRIPLEA_CRYPTO_PAYMENT_VERSION );
    }

    /**
     * Load plugin text domain
     * @return void
     */
    public function load_plugin_textdomain() {

        load_plugin_textdomain(
            'wc-triplea-crypto-payment',
            false,
            WC_TRIPLEA_CRYPTO_PAYMENT_URL . '/languages/'
        );

    }
}