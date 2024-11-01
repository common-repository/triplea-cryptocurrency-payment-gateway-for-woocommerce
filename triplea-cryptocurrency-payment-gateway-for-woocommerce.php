<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the
 * plugin admin area. This file also includes all of the dependencies used by
 * the plugin, registers the activation and deactivation functions, and defines
 * a function that starts the plugin.
 *
 * @link              https://triple-a.io
 * @since             1.0.0
 * @package           TripleA_Cryptocurrency_Payment_Gateway_for_WooCommerce
 *
 * @wordpress-plugin
 * Plugin Name:       Crypto Payment Gateway for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/triplea-cryptocurrency-payment-gateway-for-woocommerce/
 * Description:       Offer cryptocurrency as a payment option on your website and get access to even more clients. Receive payments in cryptocurrency or in your local currency, directly in your bank account. Enjoy an easy setup, no cryptocurrency expertise required. Powered by Triple-A.
 * Version:           2.0.22
 * Author:            Triple-A Team
 * Author URI:        https://triple-a.io
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-triplea-crypto-payment
 * Domain Path:       /languages
 *
 * WC requires at least: 5.0.0
 * WC tested up to: 7.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
require_once __DIR__ . '/vendor/autoload.php';

/*
 * Main plugin class
 */
final class WC_Tripla_Crypto_Payment
{
    /*
     * Plugin version
     *
     * $var string
     */
    public const version = '2.0.22';

    /*
     * Plugin constructor
     */
    private function __construct()
    {

        $this->define_constants();
        $this->check_older_version();

        register_activation_hook(__FILE__, [$this, 'activate']);

        add_action('plugins_loaded', [$this, 'init_plugin']);
    }

    /**
     * Initializes a singleton instance
     *
     * @return \WC_Tripla_Crypto_Payment
     */
    public static function init()
    {
        static $instance = false;

        if (!$instance) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Define the required plugin constants
     *
     * @return void
     */
    public function define_constants()
    {
        define('WC_TRIPLEA_CRYPTO_PAYMENT_VERSION', self::version);
        define('WC_TRIPLEA_CRYPTO_PAYMENT_FILE', __FILE__);
        define('WC_TRIPLEA_CRYPTO_PAYMENT_PATH', __DIR__);
        define('WC_TRIPLEA_CRYPTO_PAYMENT_URL', plugins_url('', WC_TRIPLEA_CRYPTO_PAYMENT_FILE));
        define('WC_TRIPLEA_CRYPTO_PAYMENT_ASSETS', WC_TRIPLEA_CRYPTO_PAYMENT_URL . '/assets');
    }

    /**
     * Check older version & update DB accordingly
     */
    public function check_older_version()
    {
        if (!get_option('wc_triplea_crypto_payment_installed')) {
            $installer = new Triplea\WcTripleaCryptoPayment\Installer();
            $installer->run();

            //Set older plugin options into new one
            $plugin_options           = 'woocommerce_' . 'triplea_payment_gateway' . '_settings';
            $plugin_settings_defaults = array();
            $plugin_settings          = get_option($plugin_options, $plugin_settings_defaults);

            $new_plugin_settings = [
                'merchant_key'       => (isset($plugin_settings['triplea_btc2fiat_merchant_key']) && !empty($plugin_settings['triplea_btc2fiat_merchant_key'])) ? $plugin_settings['triplea_btc2fiat_merchant_key'] : '',
                'client_id'          => (isset($plugin_settings['triplea_btc2fiat_client_id']) && !empty($plugin_settings['triplea_btc2fiat_client_id'])) ? $plugin_settings['triplea_btc2fiat_client_id'] : '',
                'client_secret'      => (isset($plugin_settings['triplea_btc2fiat_client_secret']) && !empty($plugin_settings['triplea_btc2fiat_client_secret'])) ? $plugin_settings['triplea_btc2fiat_client_secret'] : '',
                'oauth_token'        => (isset($plugin_settings['triplea_btc2fiat_oauth_token']) && !empty($plugin_settings['triplea_btc2fiat_oauth_token'])) ? $plugin_settings['triplea_btc2fiat_oauth_token'] : '',
                'oauth_token_expiry' => (isset($plugin_settings['triplea_btc2fiat_oauth_token_expiry']) && !empty($plugin_settings['triplea_btc2fiat_oauth_token_expiry'])) ? $plugin_settings['triplea_btc2fiat_oauth_token_expiry'] : '',
                'debug_log'          => (isset($plugin_settings['debug_log_enabled']) && !empty($plugin_settings['debug_log_enabled'])) ? $plugin_settings['debug_log_enabled'] : '',
                'crypto_text'        => (isset($plugin_settings['triplea_bitcoin_text_custom_value']) && !empty($plugin_settings['triplea_bitcoin_text_custom_value'])) ? $plugin_settings['triplea_bitcoin_text_custom_value'] : '',
                'crypto_logo'        => 'show_logo',
                'enabled'            => (isset($plugin_settings['enabled']) && !empty($plugin_settings['enabled'])) ? $plugin_settings['enabled'] : 'yes',
            ];
            update_option($plugin_options, $new_plugin_settings);
        } else {
            if (get_option('wc_triplea_crypto_payment_version') < self::version) {
                update_option('wc_triplea_crypto_payment_version', self::version);
            }
        }
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init_plugin()
    {

        new Triplea\WcTripleaCryptoPayment\Assets();
        new Triplea\WcTripleaCryptoPayment\Reviews();
        new Triplea\WcTripleaCryptoPayment\Triplea_Hooks();
        $this->appsero_init_tracker_triplea_cryptocurrency_payment_gateway_for_woocommerce();
        add_filter('woocommerce_payment_gateways', [$this, 'triplea_wc_add_gateway_class']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_extra_links']);
    }

    public function triplea_wc_add_gateway_class($gateways)
    {
        $gateways[] = new Triplea\WcTripleaCryptoPayment\WooCommerce\TripleA_Payment_Gateway();
        return $gateways;
    }

    /**
     * Adds plugin page links
     *
     * @since 1.0.0
     * @param array $links all plugin links
     * @return array $links all plugin links + our custom links (i.e., "Settings")
     */
    public function add_extra_links($links)
    {

        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=triplea_payment_gateway') . '">' . __('Configure', 'wc-triplea-crypto-payment') . '</a>'
        );

        return array_merge($plugin_links, $links);
    }


    /**
     * Do stuff upon plugin activation
     *
     * @return void
     */
    public function activate()
    {

        $checkWC   = in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));

        if (!$checkWC) {
            $admin_notice = new Triplea\WcTripleaCryptoPayment\Admin_Notice();
            add_action('admin_notices', [$admin_notice, 'check_require_plugin_notice']);
        } else {
            $installer = new Triplea\WcTripleaCryptoPayment\Installer();
            $installer->run();

            //Set older plugin options into new one
            $plugin_options           = 'woocommerce_' . 'triplea_payment_gateway' . '_settings';
            $plugin_settings_defaults = array();
            $plugin_settings          = get_option($plugin_options, $plugin_settings_defaults);

            $new_plugin_settings = [
                'merchant_key'       => (isset($plugin_settings['triplea_btc2fiat_merchant_key']) && !empty($plugin_settings['triplea_btc2fiat_merchant_key'])) ? $plugin_settings['triplea_btc2fiat_merchant_key'] : '',
                'client_id'          => (isset($plugin_settings['triplea_btc2fiat_client_id']) && !empty($plugin_settings['triplea_btc2fiat_client_id'])) ? $plugin_settings['triplea_btc2fiat_client_id'] : '',
                'client_secret'      => (isset($plugin_settings['triplea_btc2fiat_client_secret']) && !empty($plugin_settings['triplea_btc2fiat_client_secret'])) ? $plugin_settings['triplea_btc2fiat_client_secret'] : '',
                'oauth_token'        => (isset($plugin_settings['triplea_btc2fiat_oauth_token']) && !empty($plugin_settings['triplea_btc2fiat_oauth_token'])) ? $plugin_settings['triplea_btc2fiat_oauth_token'] : '',
                'oauth_token_expiry' => (isset($plugin_settings['triplea_btc2fiat_oauth_token_expiry']) && !empty($plugin_settings['triplea_btc2fiat_oauth_token_expiry'])) ? $plugin_settings['triplea_btc2fiat_oauth_token_expiry'] : '',
                'debug_log'          => (isset($plugin_settings['debug_log_enabled']) && !empty($plugin_settings['debug_log_enabled'])) ? $plugin_settings['debug_log_enabled'] : '',
                'crypto_text'        => (isset($plugin_settings['triplea_bitcoin_text_custom_value']) && !empty($plugin_settings['triplea_bitcoin_text_custom_value'])) ? $plugin_settings['triplea_bitcoin_text_custom_value'] : '',
                'crypto_logo'        => 'show-logo',
            ];
            update_option($plugin_options, $new_plugin_settings);
        }
    }

    /**
     * Initialize the tracker
     *
     * @return void
     */
    public function appsero_init_tracker_triplea_cryptocurrency_payment_gateway_for_woocommerce()
    {

        $client = new Appsero\Client('66058477-e72e-4dac-9d5b-3b5e028a5cbb', 'Cryptocurrency Payment Gateway for WooCommerce', __FILE__);

        // Active insights
        $client->insights()->init();
    }
}

/**
 * Initializes the main plugin
 *
 * @return \WC_Tripla_Crypto_Payment
 */
function wc_triplea_crypto_payment()
{
    return WC_Tripla_Crypto_Payment::init();
}

// kick-off the plugin
wc_triplea_crypto_payment();
