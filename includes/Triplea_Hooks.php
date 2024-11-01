<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://triple-a.io
 * @since      1.0.0
 *
 * @package    TripleA_Cryptocurrency_Payment_Gateway_for_WooCommerce
 * @subpackage TripleA_Cryptocurrency_Payment_Gateway_for_WooCommerce/includes
 */

namespace Triplea\WcTripleaCryptoPayment;

use Triplea\WcTripleaCryptoPayment\API\API;
use Triplea\WcTripleaCryptoPayment\API\REST;
use Triplea\WcTripleaCryptoPayment\WooCommerce\Thank_You;
use Triplea\WcTripleaCryptoPayment\WooCommerce\TripleA_Payment_Gateway;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 */
class Triplea_Hooks
{
    /**
     * @var API
     */
    private $api;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     *
     */
    public function __construct()
    {

        // The guts of the plugin.
        $this->api = API::get_instance();

        $this->define_woocommerce_hooks();
        $this->define_rest_hooks();
    }

    /**
     * Enable endpoints for the return_url used by TripleA API for Tx validation updates.
     */
    public function define_rest_hooks()
    {

        // $rest = REST::get_instance();
        $rest = new REST($this->api);
        // echo '<pre>';
        // print_r(add_action( 'rest_api_init', [ $rest, 'rest_api_init' ] ));
        // echo '</pre>'; exit;
        // echo "ba";
        // exit;
        add_action('rest_api_init', [ $rest, 'rest_api_init' ]);
    }

    /**
     * Add actions for WooCommerce gateway registration, checkout ajax and thank you page.
     */
    public function define_woocommerce_hooks()
    {

        add_action('wc_ajax_wc_triplea_start_checkout', [ TripleA_Payment_Gateway::class, 'wc_ajax_start_checkout' ]);
        add_action('wc_ajax_wc_triplea_get_payment_form_data', [ TripleA_Payment_Gateway::class, 'triplea_ajax_get_payment_form_data' ]);
        // add_action( 'woocommerce_checkout_update_order_review', TripleA_Payment_Gateway::class, 'triplea_checkout_update_order_review' );

        //Ajax action for placing payment request to triple A api
        add_action('wp_ajax_triplea_orderpay_payment_request', [TripleA_Payment_Gateway::class, 'triplea_orderpay_payment_request']);
        add_action('wp_ajax_nopriv_triplea_orderpay_payment_request', [TripleA_Payment_Gateway::class, 'triplea_orderpay_payment_request']);

        add_filter('woocommerce_thankyou_order_received_text', [ Thank_You::class, 'triplea_change_order_received_text' ], 10, 2);
        add_filter('woocommerce_thankyou_triplea_payment_gateway', [ Thank_You::class, 'thankyou_page_payment_details' ], 10, 2);
    }
}
