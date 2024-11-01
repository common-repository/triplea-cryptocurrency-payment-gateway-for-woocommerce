<?php

namespace Triplea\WcTripleaCryptoPayment\WooCommerce;

use WC_Payment_Gateway;
use WC_Subscriptions_Cart;
use WC_AJAX;
use Datetime;
use WC_HTTPS;
use Triplea\WcTripleaCryptoPayment\Logger;
use Triplea\WcTripleaCryptoPayment\API\API;
use Triplea\WcTripleaCryptoPayment\API\REST;
use Automattic\WooCommerce\Utilities\OrderUtil;

class TripleA_Payment_Gateway extends WC_Payment_Gateway
{
    protected $merchantKey;
    protected $clientID;
    protected $clientSecret;
    protected $oauthToken;
    protected $oauthTokenExpiry;
    protected $debugLog;
    protected $logger;
    protected $triplea_client_secret_key;
    protected $triplea_client_public_key;
    protected $test_mode;

    /**
     * Load the key variables through constructor to let WC know about the plugin options
     */
    public function __construct()
    {

        $this->id                 = 'triplea_payment_gateway';
        $this->title              = __('Cryptocurrency Payment Gateway', 'wc-triplea-crypto-payment');
        $this->method_title       = __('Cryptocurrency Payment Gateway', 'wc-triplea-crypto-payment');
        // $this->description        = __('Secure and easy payment with Cryptocurrency using the Triple-A.io service.', 'wc-triplea-crypto-payment');
        $this->method_description = __('Secure and easy payment with Cryptocurrency using the Triple-A.io service.', 'wc-triplea-crypto-payment');
        $this->has_fields         = true;
        $this->supports           = [
            'products',
        ];

        // load backend options fields
        $this->init_form_fields();

        // load the settings.
        $this->init_settings();

        $this->enabled          = $this->get_option('enabled');
        $this->test_mode        = 'yes' === $this->get_option('test_mode');
        $this->merchantKey      = $this->get_option('merchant_key');
        $this->debugLog         = ($this->get_option('debug_log') == 'yes') ? true : false;
        $this->clientID         = $this->get_option('client_id');
        $this->clientSecret     = $this->get_option('client_secret');
        $this->oauthToken       = $this->get_option('oauth_token');
        $this->oauthTokenExpiry = $this->get_option('oauth_token_expiry');

        $this->logger           = Logger::get_instance();

        $this->triplea_set_api_endpoint_token();

        // Action hook to saves the settings
        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        // Action hook to load custom JavaScript
        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);

        // Save settings page options as defined in nested/injected HTML content.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [
            $this,
            'save_plugin_options',
        ]);


        //will be called only for checkout page
        if (is_checkout()) {


            $this->logger->write_log('refreshOauthToken() will be called now from checkout page', $this->debugLog);

            $this->refreshOauthTokens();
        }
        add_filter('http_headers_useragent', [$this, 'custom_user_agent'], 10, 2);
        add_filter('woocommerce_available_payment_gateways', [$this, 'triplea_exclude_if_subscription_product']);
    }

    /**
     * Plugin options, we deal with it in Step 3 too
     */
    public function init_form_fields()
    {

        $this->form_fields = [
            'triplea_payment_form' => [
                'id'    => 'settings_page',
                'type'  => 'settings_page',
                'title'       => __('Payment mode', 'wc-triplea-crypto-payment'),
            ],
            'merchant_key' => [
                'type' => 'hidden'
            ],
            'client_id' => [
                'type' => 'hidden'
            ],
            'client_secret' => [
                'type' => 'hidden'
            ],
            'oauth_token' => [
                'type' => 'hidden'
            ],
            'oauth_token_expiry' => [
                'type' => 'hidden'
            ],
            'enabled' => [
                'type' => 'hidden'
            ],
            'test_mode' => [
                'type' => 'hidden'
            ],
            'debug_log' => [
                'type' => 'hidden'
            ],
            'crypto_logo' => [
                'type' => 'hidden'
            ],
            'crypto_text' => [
                'type' => 'hidden'
            ],
            'triplea_client_public_key' => [
                'type' => 'hidden'
            ],
            'triplea_client_secret_key' => [
                'type' => 'hidden'
            ],
        ];
    }

    public function generate_settings_page_html($key, $value)
    {
        include_once 'views/triplea_options.php';
    }

    public function save_plugin_options()
    {

        if (!empty($_POST['clientID']) && (isset($_POST['oAuthToken']) || isset($_POST['oAuthTokenExpiry']))) {

            // {@see https://codex.wordpress.org/HTTP_API}
            $response = wp_remote_post('https://api.triple-a.io/api/v2/oauth/token', array(
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body' => array(
                    'client_id' => $_POST['woocommerce_triplea_payment_gateway_client_id'],
                    'client_secret' => $_POST['woocommerce_triplea_payment_gateway_client_secret'],
                    'grant_type' => 'client_credentials',
                ),
            ));

            if (!is_wp_error($response)) {
                // The request went through successfully, check the response code against
                // what we're expecting
                if (200 == wp_remote_retrieve_response_code($response)) {
                    // Do something with the response
                    $body = json_decode(wp_remote_retrieve_body($response));
                } else {
                    // The response code was not what we were expecting, record the message
                    $error_message = wp_remote_retrieve_response_message($response);
                }
            } else {
                // There was an error making the request
                $error_message = $response->get_error_message();
            }
            //exit;

            $this->settings['oauth_token'] = $body->access_token;
            $this->settings['oauth_token_expiry'] = $body->expires_in;
        }

        $triplea_statuses = [
            'paid'      => 'Paid (awaiting confirmation)',
            'confirmed' => 'Paid (confirmed)',
            'invalid'   => 'Invalid',
        ];

        $wcStatuses = wc_get_order_statuses();

        if (isset($_POST['triplea_woocommerce_order_states'])) {

            if (isset($this->settings['triplea_woocommerce_order_states'])) {
                $orderStates = $this->settings['triplea_woocommerce_order_states'];
            } else {
                $orderStates = [];
            }

            foreach ($triplea_statuses as $triplea_state => $triplea_name) {
                if (false === isset($_POST['triplea_woocommerce_order_states'][$triplea_state])) {
                    continue;
                }

                $wcState = $_POST['triplea_woocommerce_order_states'][$triplea_state];

                if (true === array_key_exists($wcState, $wcStatuses)) {
                    $orderStates[$triplea_state] = $wcState;
                }
            }

            $this->settings['triplea_woocommerce_order_states'] = $orderStates;
        }

        // Update the settings saved as an option,
        update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');
    }

    /**
     *  Check if endpoint token has been generated.
     *  Endpoint token is part of the web hook URL provided to the TripleA API.
     *  Incoming requests without a correct token are filtered out (spam filter).
     *
     * @param $debug_log_enabled
     */
    public function triplea_set_api_endpoint_token()
    {
        if (empty(get_option('triplea_api_endpoint_token'))) {
            if (function_exists('openssl_random_pseudo_bytes')) {
                $api_endpoint_token = md5(bin2hex(openssl_random_pseudo_bytes(16)) . (uniqid(rand(), true)));
            } else {
                $api_endpoint_token = md5((uniqid(rand(), true)) . (uniqid(rand(), true)));
            }
            add_option('triplea_api_endpoint_token', $api_endpoint_token);
            update_option('triplea_api_endpoint_token', $api_endpoint_token);
            $this->logger->write_log('Setting new endpoint token.', $this->debugLog);
        } else {
            // $this->logger->write_log( 'API Endpoint token found.', $this->debugLog );
        }
    }

    /**
     * You will need it if you want your custom credit card form, Step 4 is about it
     */
    public function payment_fields()
    {
        echo $this->get_description('');
        echo $this->display_embedded_payment_form_button('');
        $cart_totals_hash = (!empty(WC()->cart->get_cart_contents_total()) ? WC()->cart->get_cart_contents_total() : '2') . '_' . (!empty(WC()->cart->get_cart_discount_total()) ? WC()->cart->get_cart_discount_total() : '3') . '_' . (!empty(WC()->cart->get_cart_shipping_total()) ? WC()->cart->get_cart_shipping_total() : '4');
        echo "<!-- anti-checkout.js-fragment-cache '" . md5($cart_totals_hash) . "' -->";
    }

    public function get_title()
    {
        if (!empty($this->get_option('crypto_text'))) {
            $title_text = stripcslashes($this->get_option('crypto_text'));
            $title      = __($title_text, 'wc-triplea-crypto-payment');
        } else {
            $title = __('Cryptocurrency', 'wc-triplea-crypto-payment');
        }

        return apply_filters('woocommerce_gateway_title', $title, $this->id);
    }

    public function get_icon()
    {

        $logo = $this->get_option('crypto_logo');

        switch ($logo) {
            case null:
            case 'show_logo':
                $iconfile = 'crypto-icon.png';
                $style    = 'style="max-width: 100px !important;max-height: none !important;"';
                break;
            case 'no_logo':
            default:
                return;
        }

        $icon_url = WC_TRIPLEA_CRYPTO_PAYMENT_ASSETS . '/images/';
        if (is_ssl()) {
            $icon_url = WC_HTTPS::force_https_url($icon_url);
        }
        $icon = '<img src="' . $icon_url . $iconfile . '" alt="Cryptocurrency logo" ' . $style . ' />';

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    /**
     * TripleA APIv2 new code
     */
    public function display_embedded_payment_form_button($button_html)
    {
        global $wp;

        $this->logger->write_log('display_embedded_payment_form_button() starting', $this->debugLog);

        $nonce_action               = '_wc_triplea_get_payment_form_data';
        $nonce_action_order_pay     = '_triplea_orderpay_payment_request';

        $paymentform_ajax_url       = WC_AJAX::get_endpoint('wc_triplea_get_payment_form_data');
        $paymentform_ajax_url_order_pay = WC_AJAX::get_endpoint('triplea_orderpay_payment_request');

        $paymentform_ajax_nonce_url = wp_nonce_url($paymentform_ajax_url, $nonce_action);
        $paymentform_ajax_nonce_url_order_pay = wp_nonce_url($paymentform_ajax_url_order_pay, $nonce_action_order_pay);

        $output_paymentform_url     = '<div id="triplea-payment-gateway-payment-form-request-ajax-url" data-value="' . $paymentform_ajax_nonce_url . '" style="display:none;"></div>';
        $output_paymentform_url_order_pay = '<div id="triplea-payment-gateway-payment-form-request-ajax-url_order_pay" data-value="' . $paymentform_ajax_nonce_url_order_pay . '" style="display:none;"></div>';

        $nonce_action             = '_wc_triplea_start_checkout_nonce';
        $start_checkout_url       = WC_AJAX::get_endpoint('wc_triplea_start_checkout');
        $start_checkout_nonce_url = wp_nonce_url($start_checkout_url, $nonce_action);
        $output_startcheckoutcheck = "<div id='triplea-payment-gateway-start-checkout-check-url' style='display:none;' data-value='$start_checkout_nonce_url'></div>";

        $hostedURL = '';
        $orderID = '';
        if (is_wc_endpoint_url('order-pay')) {
            $order   = wc_get_order(get_query_var('order-pay'));
            $orderID = $order->get_id();
        }

        $order_pay_checkout_class = (is_wc_endpoint_url('order-pay')) ? ' triplea-order-pay' : '';
        $order_button_text = __('Pay with Cryptocurrency', 'wc-triplea-crypto-payment');
        $order_button_desc = __('Please pay the exact amount. Avoid paying from a crypto exchange, use your personal wallet.', 'wc-triplea-crypto-payment');
        $order_button_desc_msg = __('Please make sure to finalize your payment before closing the tab, as failing to do so will prevent your order from being completed.', 'wc-triplea-crypto-payment');
        $output            = '<button type="button"
        style="margin: 0 auto; display: block;"
        class="button alt' . $order_pay_checkout_class . '"
        onclick="triplea_validateCheckout(this)"
        name="triplea_embedded_payment_form_btn"
        id="triplea_embedded_payment_form_btn"
        value="' . esc_attr($order_button_text) . '"
        data-id="' . $orderID . '"
        data-value="' . esc_attr($order_button_text) . '">' . esc_html($order_button_text) . '</button>
        <span>' . $order_button_desc . '</span><span class="triplea-span-msg v2022">' . $order_button_desc_msg . '</span>';

        $output .= '<div id="triplea_embedded_payment_form_loading_txt"><img src="' . WC_TRIPLEA_CRYPTO_PAYMENT_ASSETS . '/images/checkout-loader-x.svg"></div>';

        return $button_html . $output . $output_paymentform_url . $output_paymentform_url_order_pay . $output_startcheckoutcheck;
    }

    /**
     * Handle AJAX request to start checkout flow, first triggering form
     * validation if necessary.
     *
     * @since 1.6.0
     */
    public static function wc_ajax_start_checkout()
    {

        $self = new TripleA_Payment_Gateway();
        $self->logger->write_log('wc_ajax_start_checkout() called.', $self->debugLog);

        if (!wp_verify_nonce($_GET['_wpnonce'], '_wc_triplea_start_checkout_nonce')) {
            $self->logger->write_log('wc_ajax_start_checkout() ERROR: wrong nonce.', $self->debugLog);
            wp_die(__('Bad attempt, invalid nonce for checkout_start', 'wc-triplea-crypto-payment'));
        }

        add_action('woocommerce_after_checkout_validation', [
            self::class,
            'triplea_checkout_check',
        ], PHP_INT_MAX, 2);
        WC()->checkout->process_checkout();
    }

    /**
     * Report validation errors if any, or else save form data in session and
     * proceed with checkout flow.
     *
     * @param      $data
     * @param null $errors
     *
     * @since 1.5.0
     */
    public static function triplea_checkout_check($data, $errors = null)
    {

        $self = new TripleA_Payment_Gateway();
        $self->logger->write_log('triplea_checkout_check() called.', $self->debugLog);

        $validation_done = false;
        if (!is_null($errors)) {
            $validation_done = true;
        }

        if (is_null($errors)) {
            $self->logger->write_log('triplea_checkout_check() Form errors found.', $self->debugLog);
            // Compatibility with WC <3.0: get notices and clear them so they don't re-appear.
            $error_messages = wc_get_notices('error');
            wc_clear_notices();
        } else {
            $error_messages = $errors->get_error_messages();
        }

        if (empty($error_messages)) {
            $self->logger->write_log('triplea_checkout_check() success.', $self->debugLog);

            if (has_action('woocommerce_checkout_process')) {

                $self->logger->write_log('triplea_checkout_check() site has custom validation on woocommerce_checkout_process hoook.', $self->debugLog);

                // Run the custom validation
                do_action('woocommerce_checkout_process');

                $error_messages = $errors->get_error_messages();

                $wc_notices = wc_get_notices();

                // Merge the WooCommerce notices with the validation errors
                $error_messages = array_merge($error_messages, $wc_notices);
                foreach ($error_messages as $message) {
                    $errors->add('validation', $message);
                }
                wc_clear_notices();
                if (!empty($error_messages)) {

                    if (isset($error_messages['error']) && count($error_messages['error']) > 1) {
                        $map = [];
                        $dup = [];
                        foreach ($error_messages['error'] as $key => $val) {
                            if (!array_key_exists($val['notice'], $map)) {
                                $map[$val['notice']] = $key;
                            } else {
                                $dup[] = $key;
                            }
                        }
                        foreach ($dup as $key => $val) {
                            unset($error_messages['error'][$val]);
                        }
                        sort($error_messages['error']);
                    }
                    $self->logger->write_log('triplea_checkout_check() custom validation: Form errors found.', $self->debugLog);
                    //$self->logger->write_log(json_encode($error_messages), $self->debugLog );
                    $self->logger->write_log('triplea_checkout_check()  custom validationfailed.', $self->debugLog);
                    wp_send_json_error(
                        [
                            'messages' => $error_messages,
                            'status'   => 'notok',
                            'from '   => 'triplea_checkout_check custom validation'
                        ]
                    );
                }
            }
            wp_send_json_success(
                [
                    'status'   => 'ok',
                ]
            );
        } else {
            $self->logger->write_log('triplea_checkout_check(): Form errors found.', $self->debugLog);
            //$self->logger->write_log(json_encode($error_messages)), $self->debugLog );
            // $self->logger->write_log(json_encode($error_messages), $self->debugLog );
            $self->logger->write_log('triplea_checkout_check() failed.', $self->debugLog);
            wp_send_json_error(
                [
                    'messages' => $error_messages,
                    'status'   => 'notok',
                    'from '   => 'triplea_checkout_check',
                ]
            );
        }
        exit;
    }

    /**
     * Handle AJAX request to start checkout flow, first triggering form
     * validation if necessary.
     *
     * @since 1.6.0
     */
    public static function triplea_ajax_get_payment_form_data()
    {

        if (!wp_verify_nonce($_REQUEST['_wpnonce'], '_wc_triplea_get_payment_form_data')) {
            wp_die(__('Bad attempt, invalid nonce for payment form data request', 'wc-triplea-crypto-payment'));
        }

        $user_firstname = wc_get_var($_REQUEST['billing_first_name'], null);
        $user_lastname  = wc_get_var($_REQUEST['billing_last_name'], null);
        $user_email     = wc_get_var($_REQUEST['billing_email'], null);
        $user_phone     = wc_get_var($_REQUEST['billing_phone'], null);

        $user_address_company  = wc_get_var($_REQUEST['billing_company'], null);
        $user_address_address1 = wc_get_var($_REQUEST['billing_address_1'], null);
        $user_address_address2 = wc_get_var($_REQUEST['billing_address_2'], null);
        $user_address_city     = wc_get_var($_REQUEST['billing_city'], null);
        $user_address_state    = wc_get_var($_REQUEST['billing_state'], null);
        $user_address_postcode = wc_get_var($_REQUEST['billing_postcode'], null);
        $user_address_country  = wc_get_var($_REQUEST['billing_country'], null);
        $user_address_temp     = join(', ', array($user_address_company, $user_address_address1, $user_address_address2, $user_address_city, $user_address_state, $user_address_country, $user_address_postcode));
        $user_address          = ltrim(rtrim($user_address_temp, ', '), ', ');

        $triplea  = new TripleA_Payment_Gateway();

        $payment_reference        = $access_token = $hosted_url = $data_order_txid = null;
        $need_data                = true;
        $payment_form_data_exists = false;

        $loop_count = 2;
        do {
            if (
                !WC()->session->has_session() ||
                WC()->session->get('triplea_cart_total') != WC()->cart->total ||
                WC()->session->get('triplea_payment_order_currency') != get_woocommerce_currency()
            ) {
                $session_exists           = false;
                $payment_form_data_exists = false;

                $data_order_txid = $triplea->generate_order_txid();
                WC()->session->set('generate_order_txid', $data_order_txid);

                $triplea->logger->write_log('triplea_ajax_get_payment_form_data() : Generated new order_txid as there was no session yet : ' . $data_order_txid, $triplea->debugLog);
            } else {
                $session_exists = true;

                $payment_reference   = WC()->session->get('triplea_payment_reference');
                $access_token        = WC()->session->get('triplea_payment_access_token');
                $hosted_url          = WC()->session->get('triplea_payment_hosted_url');
                $access_token_expiry = WC()->session->get('triplea_payment_access_token_expiry');
                $cart_total = WC()->session->get('triplea_cart_total');


                if (
                    !empty($payment_reference)
                    && !empty($access_token)
                    && !empty($hosted_url)
                    && !empty($access_token_expiry)
                ) {
                    $date_now = (new DateTime())->getTimestamp();
                    // Just to avoid loading a second before expiry of token.
                    $five_minutes = 5 * 60;
                    //if ($access_token_expiry > $date_now + $five_minutes) {
                    if ($access_token_expiry < $date_now + $five_minutes) {
                        $triplea->logger->write_log('triplea_ajax_get_payment_form_data() : access token expired, ' . $access_token_expiry . ' < ' . ($date_now + $five_minutes), $triplea->debugLog);
                        $need_data = true;
                    } elseif ($cart_total != WC()->cart->total) {
                        $triplea->logger->write_log('triplea_ajax_get_payment_form_data(): updating cart total! ' . WC()->cart->total . ' != ' . $cart_total, $triplea->debugLog);
                        $need_data = true;
                        WC()->session->set('triplea_cart_total', WC()->cart->total);
                    } else {
                        $need_data = false;
                    }
                    $payment_form_data_exists = true;
                }

                $data_order_txid = WC()->session->get('generate_order_txid');
                if (empty($data_order_txid)) {
                    $data_order_txid = $triplea->generate_order_txid();
                    WC()->session->set('generate_order_txid', $data_order_txid);
                    $triplea->logger->write_log('triplea_ajax_get_payment_form_data() : Generated new order_txid because there was none yet in the existing session: ' . $data_order_txid, $triplea->debugLog);
                }
            }

            $is_data_expired = false;
            if ($need_data) {
                $triplea->logger->write_log('Preparing to make payment form request using order_txid "' . $data_order_txid . '".', $triplea->debugLog);

                $payment_form_data = $triplea->get_payment_form_request(
                    $data_order_txid,
                    null,
                    $user_firstname,
                    $user_lastname,
                    $user_email,
                    $user_phone,
                    $user_address
                );

                if (is_string($payment_form_data)) {
                    $payment_form_data = json_decode($payment_form_data);
                }

                if (isset($payment_form_data->error) || !isset($payment_form_data->payment_reference)) {

                    $triplea->logger->write_log('Error. Ajax payment form request failed', $triplea->debugLog);
                    echo json_encode(
                        [
                            'status'  => 'failed',
                            'code'    => isset($payment_form_data->code) ? $payment_form_data->code : 'Unknown error code.',
                            'message' => isset($payment_form_data->message) ? $payment_form_data->message : 'Unknown error message.',
                            'error'   => isset($payment_form_data->error) ? $payment_form_data->error : 'Unknown error.',
                        ]
                    );
                    return;
                }

                $triplea->logger->write_log('Ajax payment form request succeeded', $triplea->debugLog);

                // $payment_mode = $triplea->get_option('triplea_payment_mode');

                // Needed in the checkout front-end page
                WC()->session->set('triplea_payment_hosted_url', $payment_form_data->hosted_url);
                // TODO ! Get this from session during process_payment order placing call.
                WC()->session->set('triplea_payment_reference', $payment_form_data->payment_reference);
                WC()->session->set('triplea_payment_access_token', $payment_form_data->access_token);
                WC()->session->set('triplea_payment_access_token_expiry', (new DateTime())->getTimestamp() + $payment_form_data->expires_in);
                WC()->session->set('triplea_payment_notify_secret', $payment_form_data->notify_secret);

                $payment_reference             = $payment_form_data->payment_reference;
                $access_token                  = $payment_form_data->access_token;
                $hosted_url                    = $payment_form_data->hosted_url;
                $access_token_expiry_time_left = $payment_form_data->expires_in;
                if ($access_token_expiry_time_left < (5 * 60)) {
                    $is_data_expired = true;
                }
            }


            // TODO verify payment status, make sure the session's data hasn't expired yet..
            $triplea->logger->write_log('Checking payment status to make sure we dont use expired cached form data', $triplea->debugLog);

            // Access token expiry is what we're interested in, so the below check can be avoided?
            //$is_data_expired = $triplea->get_payment_form_status_update();

            if ($is_data_expired) {
                $triplea->logger->write_log('Cached payment status has expired. Resetting form data to force refresh.', $triplea->debugLog);
                WC()->session->set('triplea_payment_reference', null);
                WC()->session->set('triplea_payment_access_token', null);
                WC()->session->set('triplea_payment_hosted_url', null);
            } else {
                $triplea->logger->write_log('Payment status data is up-to-date, ready to use for the checkout page.', $triplea->debugLog);
            }

            $loop_count -= 1;
        } while ($is_data_expired && $loop_count >= 0);

        echo json_encode(
            [
                'status'            => 'ok',
                'message'           => 'Payment form data ready.',
                'payment_reference' => $payment_reference,
                'access_token'      => $access_token,
                'url'               => $hosted_url,
                'order_txid'        => $data_order_txid,
                'meta'              => [
                    'session_exists'           => $session_exists,
                    'payment_form_data_exists' => $payment_form_data_exists,
                ],
            ]
        );
    }
    /**
     * @return boolean  true if the token is expired or invalid or null
     * @return boolean  false if the token is valid.
     */
    private function isOauthTokenInvalid()
    {

        $date_now       = (new DateTime())->getTimestamp();
        $buffer_time     = 600; // 10 min buffer time, so new token will generated after 50 min
        $current_token_expiry   = intval($this->get_option('oauth_token_expiry'));
        $current_token   = $this->get_option('oauth_token');

        if (
            isset($this->clientID) && !empty($this->clientID)
            && isset($this->clientSecret) && !empty($this->clientSecret)
            && (!isset($current_token) || empty($current_token) ||
                $date_now >= ($current_token_expiry - $buffer_time))
        ) {
            //time to gernerate new token
            return true;
        } else {
            //current token is valid
            return false;
        }
    }
    /**
     * Store new token into database if token validity expired
     */
    private function refreshOauthTokens($refresh_for_api_id = false)
    {

        $date_now       = (new DateTime())->getTimestamp();
        $buffer_time     = 600; // 10 min buffer time, so new token will generated after 50 min
        $current_token_expiry   = intval($this->get_option('oauth_token_expiry'));
        $current_token   = $this->get_option('oauth_token');

        if ($this->isOauthTokenInvalid()) {
            if ($date_now >= ($current_token_expiry - $buffer_time)) {
                $this->logger->write_log('refreshOauthToken() OAuth token (for local currency settlement) expires in less than 10 minutes. Requesting a new oauth token.', $this->debugLog);
            } else {
                $this->logger->write_log('refreshOauthToken() OAuth token (for local currency settlement) is missing. Requesting a new oauth token.', $this->debugLog);
            }


            $new_token_data = $this->getOauthToken($this->clientID, $this->clientSecret);
            $this->logger->write_log('refreshOauthToken() OAuth token data received : ' . wc_print_r(json_encode($new_token_data), true), $this->debugLog);

            if (
                $new_token_data !== false
                && isset($new_token_data->access_token)
                && !empty($new_token_data->access_token)
                && isset($new_token_data->expires_in)
                && !empty($new_token_data->expires_in)
            ) {
                $this->oauthToken       = $new_token_data->access_token;
                $this->oauthTokenExpiry = $date_now + $new_token_data->expires_in;
                $this->update_option('oauth_token', $this->oauthToken);
                $this->update_option('oauth_token_expiry', $this->oauthTokenExpiry);
                $this->logger->write_log('refreshOauthToken() Obtained and saved a new oauth token.', $this->debugLog);
            } else {
                $this->logger->write_log('refreshOauthToken() A problem happened, could not get a new oauth token. \n' . wc_print_r(json_encode($new_token_data), true), $this->debugLog);
                $this->oauthToken       = null;
                $this->oauthTokenExpiry = null;
                $this->update_option('oauth_token', $this->oauthToken);
                $this->update_option('oauth_token_expiry', $this->oauthTokenExpiry);
            }
        } else {
            if (!isset($this->clientID) || empty($this->clientID)) {
                $this->logger->write_log('refreshOauthToken() Client ID is not set ', $this->debugLog);
            }
            if (!isset($this->clientSecret) ||  empty($this->clientSecret)) {
                $this->logger->write_log('refreshOauthToken() Client Secret ID is not set ', $this->debugLog);
            }
            if ($date_now < ($current_token_expiry - $buffer_time)) {

                //$this->logger->write_log('refreshOauthToken() OAuth token (for local currency settlement) is still valid, so not requesting for a new one.', $this->debugLog);
            }
        }
    }

    /**
     * Generate new OAuth token if validity expired
     */
    private function refreshOauthTokensForForms($refresh_for_api_id = false)
    {
        $date_now       = (new DateTime())->getTimestamp();
        $buffer_time     = 600; // 10 min buffer time, so new token will generated after 50 min
        $current_token_expiry   = intval($this->get_option('oauth_token_expiry'));
        $current_token   = $this->get_option('oauth_token');

        if ($this->isOauthTokenInvalid()) {
            if ($date_now >= ($current_token_expiry - $buffer_time)) {
                $this->logger->write_log('refreshOauthTokensForForms() OAuth token (for local currency settlement) expires in less than 10 minutes. Requesting a new oauth token.', $this->debugLog);
            } else {
                $this->logger->write_log('refreshOauthTokensForForms() OAuth token (for local currency settlement) is missing. Requesting a new oauth token.', $this->debugLog);
            }

            $new_token_data = $this->getOauthToken($this->clientID, $this->clientSecret);
            $this->logger->write_log('refreshOauthTokensForForms() OAuth token data received : ' . wc_print_r(json_encode($new_token_data), true), $this->debugLog);

            if (
                $new_token_data !== false
                && isset($new_token_data->access_token)
                && !empty($new_token_data->access_token)
                && isset($new_token_data->expires_in)
                && !empty($new_token_data->expires_in)
            ) {
                $this->oauthToken       = $new_token_data->access_token;
                $this->oauthTokenExpiry = $date_now + $new_token_data->expires_in;
                $this->update_option('oauth_token', $this->oauthToken);
                $this->update_option('oauth_token_expiry', $this->oauthTokenExpiry);

                $this->logger->write_log('refreshOauthTokensForForms() Obtained and saved a new oauth token.', $this->debugLog);

                return $new_token_data->access_token;
            } else {
                $this->logger->write_log('refreshOauthTokensForForms() A problem happened, could not get a new oauth token. \n' . wc_print_r(json_encode($new_token_data), true), $this->debugLog);
                $this->oauthToken       = null;
                $this->oauthTokenExpiry = null;
                $this->update_option('oauth_token', $this->oauthToken);
                $this->update_option('oauth_token_expiry', $this->oauthTokenExpiry);

                return false;
            }
        } else {
            if (!isset($this->clientID) || empty($this->clientID)) {
                $this->logger->write_log('refreshOauthTokensForForms() Client ID is not set ', $this->debugLog);
            }
            if (!isset($this->clientSecret) ||  empty($this->clientSecret)) {
                $this->logger->write_log('refreshOauthTokensForForms() Client Secret ID is not set ', $this->debugLog);
            }
            if ($date_now < ($current_token_expiry - $buffer_time)) {
                //   $this->logger->write_log('refreshOauthTokensForForms() OAuth token (for local currency settlement) is still valid, so not requesting for a new one.', $this->debugLog);
            }
            return $current_token;
        }
    }
    private function getOauthToken($client_id, $client_secret)
    {

        $post_url = 'https://api.triple-a.io/api/v2/oauth/token';
        $body     = [
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'grant_type'    => 'client_credentials',
        ];

        // $this->logger->write_log( 'Making an oauth token request with body: \n' . wc_print_r($body, TRUE), $this->debugLog );
        $this->logger->write_log('Making an oauth token request with clinet id: \n' . wc_print_r($client_id, true), $this->debugLog);

        $result = wp_remote_post($post_url, [
            'method'      => 'POST',
            'headers'     => [
                'content-type' => 'application/x-www-form-urlencoded; charset=utf-8',
            ],
            //'sslverify' => false,
            'body'        => $body,
            'data_format' => 'body',
        ]);

        if (is_wp_error($result)) {
            return ['error' => 'Error happened, could not complete the oauth token request.'];
        }

        $this->logger->write_log("Oauth token request object: \n" . wc_print_r($result['body'], true), $this->debugLog);

        return json_decode($result['body']);
    }
    public static function custom_user_agent($user_agent, $url)
    {


        if (strpos($url, '/oauth/token') !== false || strpos($url, 'v2/payment') !== false) {
            // Modify the user agent to your desired value
            $version = get_option('wc_triplea_crypto_payment_version');
            $user_agent = $user_agent . "; wc_triplea_crypto_payment:" . $version;
        }
        return $user_agent;
    }
    /**
     *
     * Make a payment form request to TripleA API.
     * Returns a object containing (amongst others)
     * a payment_reference, access_token, notify_secret and hosted_url.
     *
     * @return mixed|string[]|object
     */
    private function get_payment_form_request(
        $order_txid,
        $order_id,
        $user_firstname,
        $user_lastname,
        $user_email,
        $user_phone,
        $user_address
    ) {
        $self = new TripleA_Payment_Gateway();

        // validity checking oauth tokens, if validity expires, create new token.
        $oauth_token = $self->refreshOauthTokensForForms();

        //if token is null or false, aboirt the request
        if (empty($oauth_token)) {
            wp_die('Missing oauth token for cryptocurrency payments with local currency settlement.');
        }



        $post_url = 'https://api.triple-a.io/api/v2/payment';

        $body     = $this->preparePaymentFormRequestBody(
            $order_txid,
            $order_id,
            $user_firstname,
            $user_lastname,
            $user_email,
            $user_phone,
            $user_address
        );
        $self->logger->write_log('Making a payment form API request with body: ' . wc_print_r($body, true), $self->debugLog);

        $result = wp_remote_post($post_url, [
            'method'      => 'POST',
            'headers'     => [
                'Authorization' => 'Bearer ' . $oauth_token,
                'Content-Type'  => 'application/json; charset=utf-8',
            ],
            //'sslverify' => false,
            'body'        => json_encode($body),
            'data_format' => 'body',
        ]);

        if (is_wp_error($result)) {
            return ['error' => 'Error happened, could not complete the payment form request.'];
        }
        $self->logger->write_log('Payment form request response: \n' . wc_print_r($result['body'], true), $self->debugLog);

        if ($result['response']['code'] > 299) {
            return json_encode([
                'error'   => 'Error happened, could not complete the payment form request.',
                'code'    => $result['response']['code'],
                'message' => $result['response']['message'],
            ]);
        }

        $json_result = json_decode($result['body']);
        if (!isset($json_result->payment_reference)) {
            return json_encode([
                'error' => 'Error happened, wrong payment form request data format received.',
            ]);
        }

        return $json_result;
    }

    /**
     * Returns an array containing all required data (request body) about the
     * order for which a payment form request will be sent.
     *
     * @return array
     */
    private function preparePaymentFormRequestBody(
        $order_txid,
        $order_id,
        $user_firstname,
        $user_lastname,
        $user_email,
        $user_phone,
        $user_address
    ) {

        $payment_form_type = 'widget';

        if ($order_id != null) {
            $order = wc_get_order($order_id);

            $this->logger->write_log('Order Pay Page Cart Total:' . $order->get_total(), $this->debugLog);

            $order_amount   = esc_attr(((WC()->version < '2.7.0') ? $order->order_total : $order->get_total()));
            $order_currency = esc_attr(((WC()->version < '2.7.0') ? $order->order_currency : $order->get_currency()));
        } else {
            $order_amount   = esc_attr(WC()->cart->total);
            $order_currency = esc_attr(strtoupper(get_woocommerce_currency()));
        }

        $this->logger->write_log('Order WC()->cart->total: ' . WC()->cart->total, $this->debugLog);
        $this->logger->write_log('Order WC()->cart->get_cart_total(): ' . WC()->cart->get_cart_total(), $this->debugLog);
        $this->logger->write_log('Order WC()->cart->get_cart_contents_total(): ' . WC()->cart->get_cart_contents_total(), $this->debugLog);
        $this->logger->write_log('Order WC()->cart->get_cart_discount_total(): ' . WC()->cart->get_cart_discount_total(), $this->debugLog);
        $this->logger->write_log('Order WC()->cart->get_cart_shipping_total(): ' . WC()->cart->get_cart_shipping_total(), $this->debugLog);

        $tax_cost          = null; //WC()->cart->get_tax_totals();
        $shipping_cost     = empty(WC()->cart->get_cart_shipping_total()) ? null : WC()->cart->get_cart_shipping_total();
        $shipping_discount = null;

        $extra_metadata = [
            //'order_txid' => WC()->session->get('generate_order_txid'),
            'order_txid' => $order_txid,
        ];

        //$notify_url = 'https://webhook.site/5c8682ea-267d-4cb5-a720-af31670e8fcf';
        $notify_url = get_rest_url(null, 'triplea/v2/triplea_webhook/' . get_option('triplea_api_endpoint_token'));

        if (isset(WC()->customer) && WC()->customer->get_id()) {
            $payer_id    = WC()->customer->get_id() . '__' . WC()->customer->get_username();
            $payer_name  = (WC()->customer->get_first_name() ? WC()->customer->get_first_name() : WC()->customer->get_billing_first_name()) . ' ' . (WC()->customer->get_last_name() ? WC()->customer->get_last_name() : WC()->customer->get_billing_last_name());
            $payer_email = empty(WC()->customer->get_email()) ? WC()->customer->get_billing_email() : WC()->customer->get_email();
            // phone number validation could too easily cause problem, add in metadata
            $payer_phone   = null;
            $payer_address = join(',', [
                WC()->customer->get_billing_address(),
                WC()->customer->get_billing_address_1(),
                WC()->customer->get_billing_address_2(),
                WC()->customer->get_billing_city(),
                WC()->customer->get_billing_state(),
                WC()->customer->get_billing_country(),
                WC()->customer->get_billing_postcode(),
            ]) ?: 'no address';

            if (!empty(WC()->customer->get_username())) {
                $extra_metadata['username'] = WC()->customer->get_username();
            }
            if (!empty(WC()->customer->get_id())) {
                $extra_metadata['userid'] = WC()->customer->get_id();
            }
            if (!empty(WC()->customer->get_billing_phone())) {
                $extra_metadata['payer_phone'] = WC()->customer->get_billing_phone();
            }
            if (!empty(WC()->customer->get_billing_email())) {
                $extra_metadata['billing_email'] = WC()->customer->get_billing_email();
            }
            if (!empty(WC()->customer->get_billing_city())) {
                $extra_metadata['billing_city'] = WC()->customer->get_billing_city();
            }
            if (!empty(WC()->customer->get_billing_country())) {
                $extra_metadata['billing_country'] = WC()->customer->get_billing_country();
            }
            if (!empty(WC()->customer->get_billing_company())) {
                $extra_metadata['billing_company'] = WC()->customer->get_billing_company();
            }
            if (!empty(WC()->customer->get_billing_first_name())) {
                $extra_metadata['billing_first_name'] = WC()->customer->get_billing_first_name();
            }
            if (!empty(WC()->customer->get_billing_last_name())) {
                $extra_metadata['billing_last_name'] = WC()->customer->get_billing_last_name();
            }
            if (!empty(WC()->customer->get_shipping_address())) {
                $extra_metadata['shipping_address'] = WC()->customer->get_shipping_address();
            }
            if (!empty(WC()->customer->get_shipping_address_1())) {
                $extra_metadata['shipping_address_1'] = WC()->customer->get_shipping_address_1();
            }
            if (!empty(WC()->customer->get_shipping_address_2())) {
                $extra_metadata['shipping_address_2'] = WC()->customer->get_shipping_address_2();
            }
            if (!empty(WC()->customer->get_shipping_city())) {
                $extra_metadata['shipping_city'] = WC()->customer->get_shipping_city();
            }
            if (!empty(WC()->customer->get_shipping_country())) {
                $extra_metadata['shipping_country'] = WC()->customer->get_shipping_country();
            }
            if (!empty(WC()->customer->get_shipping_company())) {
                $extra_metadata['shipping_company'] = WC()->customer->get_shipping_company();
            }
            if (!empty(WC()->customer->get_shipping_postcode())) {
                $extra_metadata['shipping_postcode'] = WC()->customer->get_shipping_postcode();
            }
            if (!empty(WC()->customer->get_shipping_state())) {
                $extra_metadata['shipping_state'] = WC()->customer->get_shipping_state();
            }
            if (!empty(WC()->customer->get_shipping_first_name())) {
                $extra_metadata['shipping_first_name'] = WC()->customer->get_shipping_first_name();
            }
            if (!empty(WC()->customer->get_shipping_last_name())) {
                $extra_metadata['shipping_last_name'] = WC()->customer->get_shipping_last_name();
            }
        } else {

            if (!empty($user_email)) {
                $payer_id      = 'guest_' . $user_email;
            } else {
                $payer_id      = 'guest_' . $this->randomString() . '.';
            }
            $payer_name    = (!empty($user_firstname) || !empty($user_lastname)) ? $user_firstname . ' ' . $user_lastname : '';
            $payer_email   = $user_email ?: '';
            $payer_phone   = null;
            $payer_address = $user_address ?: '';

            $extra_metadata['payer_id'] = $payer_id;

            if (!empty(WC()->customer->get_username())) {
                $extra_metadata['username'] = 'anonymous';
            }
        }

        $cart_items = [];
        if ($order_id == null) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product = $cart_item['data'];
                if (!empty($product)) {
                    $new_item = [];

                    $new_item['label']      = !empty($product->get_name()) ? $product->get_name() : 'unknown product name';
                    $new_item['sku']        = !empty($product->get_sku()) ? $product->get_sku() : 'no_sku';
                    $new_item['quantity']   = !empty(!empty($cart_item['quantity'])) ? floatval($cart_item['quantity']) : 0;
                    $new_item['amount']     = !empty($product->get_price()) ? floatval($product->get_price()) : 0.0;

                    if (!empty($new_item)) {
                        $cart_items[] = $new_item;
                    }
                }
            }
        } else {
            $order = wc_get_order($order_id);
            foreach ($order->get_items() as  $item_key => $item_values) {
                $item_data = $item_values->get_data();
                $new_item = [];

                $new_item['label']      = (isset($item_data['name']) && $item_data['name']) ? $item_data['name'] : 'no_name';
                $new_item['sku']        = (isset($item_data['sku']) && $item_data['sku']) ? $item_data['sku'] : 'no_sku';
                $new_item['quantity']   = floatval($item_data['quantity']);
                $new_item['amount']     = floatval($item_data['total']);
                if (!empty($new_item)) {
                    $cart_items[] = $new_item;
                }
            }
        }

        //For ecom that used multi crypto currency is not supported yet by triple A so we manually handles this request for now
        if (in_array(strtolower($order_currency), ['tbtc', 'ltc', 'ada', 'dot', 'bch', 'xlm', 'link', 'bnb', 'tusd', 'xmr', 'doge', 'nano', 'dash'])) {
            $order_currency = 'USD';
        }

        $body = [
            "type"            => $payment_form_type,
            "order_amount"    => $order_amount,
            "order_currency"  => $order_currency,
            "merchant_key"    => $this->merchantKey,
            //"notify_email"    => $notify_email,
            "notify_url"      => $notify_url,
            //"notify_secret"   => $notify_secret,
            // either user_id or guest+random token
            "payer_id"        => !empty($payer_id) ? $payer_id : null,
            // only if available
            "payer_name"      => !empty($payer_name) ? $payer_name : null,
            // only if available
            "payer_email"     => !empty($payer_email) ? $payer_email : null,
            // only if available
            "payer_phone"     => !empty($payer_phone) ? $payer_phone : null,
            // only if available
            "payer_address"   => !empty($payer_address) ? $payer_address : null,
            //"payer_poi"       => $payer_poi,
            //"success_url"     => "https://www.success.io/success.html",
            //"cancel_url"      => "https://www.failure.io/cancel.html",
            "cart"            =>
            [
                "items"             => $cart_items,
                "shipping_cost"     => 0,
                "tax_cost"          => 0,
                "shipping_discount" => 0,
            ],
            "webhook_data"    => $extra_metadata,
            "sandbox"         => $this->test_mode
        ];

        if (!empty($cart_items)) {
            $body['cart']['items'] = $cart_items;

            if (!empty($shipping_cost)) {
                $body['cart']['shipping_cost'] = floatval($shipping_cost);
            }
            if (!empty($shipping_discount)) {
                $body['cart']['shipping_discount'] = floatval($shipping_discount);
            }
            if (!empty($tax_cost)) {
                $body['cart']['tax_cost'] = floatval($tax_cost);
            }
        }

        return $body;
    }

    /**
     * Payment request function for order pay page
     */
    public static function triplea_orderpay_payment_request()
    {

        $orderID    = $_POST['orderid'];
        $order      = wc_get_order($orderID);
        $order_data = $order->get_data();
        $triplea         = new TripleA_Payment_Gateway();
        $data_order_txid = $triplea->generate_order_txid();
        $user_address_company  = $order_data['billing']['company'];
        $user_address_address1 = $order_data['billing']['address_1'];
        $user_address_address2 = $order_data['billing']['address_2'];
        $user_address_city     = $order_data['billing']['city'];
        $user_address_state    = $order_data['billing']['state'];
        $user_address_postcode = $order_data['billing']['postcode'];
        $user_address_country  = $order_data['billing']['country'];
        $user_address_temp     = join(', ', array($user_address_company, $user_address_address1, $user_address_address2, $user_address_city, $user_address_state, $user_address_country, $user_address_postcode));
        $user_address          = ltrim(rtrim($user_address_temp, ', '), ', ');

        $payment_form_data = $triplea->get_payment_form_request(
            $data_order_txid,
            $orderID,
            $order_data['billing']['first_name'],
            $order_data['billing']['last_name'],
            $order_data['billing']['email'],
            $order_data['billing']['phone'],
            $user_address
        );


        if (OrderUtil::custom_orders_table_usage_is_enabled()) {
            $order = wc_get_order($orderID);

            $dataOrderTxid = $order->get_meta('_triplea_tx_id');
            if (empty($dataOrderTxid)) {
                $order->add_meta_data('_triplea_tx_id', $data_order_txid);
            } else {
                $order->update_meta_data('_triplea_tx_id', $data_order_txid);
            }

            $paymentReference = $order->get_meta('_triplea_payment_reference');
            if (empty($paymentReference)) {
                $order->add_meta_data('_triplea_payment_reference', $payment_form_data->payment_reference);
            } else {
                $order->update_meta_data('_triplea_payment_reference', $payment_form_data->payment_reference);
            }

            $accessToken = $order->get_meta('_triplea_access_token');
            if (empty($accessToken)) {
                $order->add_meta_data('_triplea_access_token', $payment_form_data->access_token);
            } else {
                $order->update_meta_data('_triplea_access_token', $payment_form_data->access_token);
            }

            $notifySecret = $order->get_meta('_triplea_notify_secret');
            if (empty($notifySecret)) {
                $order->add_meta_data('_triplea_notify_secret', $payment_form_data->notify_secret);
            } else {
                $order->update_meta_data('_triplea_notify_secret', $payment_form_data->notify_secret);
            }

            $cryptoAddress = $order->get_meta('_triplea_crypto_address');
            if (empty($cryptoAddress)) {
                $order->add_meta_data('_triplea_crypto_address', $payment_form_data->crypto_address);
            } else {
                $order->update_meta_data('_triplea_crypto_address', $payment_form_data->crypto_address);
            }

            $order->save();
        } else {

            if (0 === count(get_post_meta($orderID, '_triplea_tx_id'))) {
                add_post_meta($orderID, '_triplea_tx_id', $data_order_txid);
            } else {
                update_post_meta($orderID, '_triplea_tx_id', $data_order_txid);
            }
            if (0 === count(get_post_meta($orderID, '_triplea_payment_reference'))) {
                add_post_meta($orderID, '_triplea_payment_reference', $payment_form_data->payment_reference);
            } else {
                update_post_meta($orderID, '_triplea_payment_reference', $payment_form_data->payment_reference);
            }
            if (0 === count(get_post_meta($orderID, '_triplea_access_token'))) {
                add_post_meta($orderID, '_triplea_access_token', $payment_form_data->access_token);
            } else {
                update_post_meta($orderID, '_triplea_access_token', $payment_form_data->access_token);
            }
            if (0 === count(get_post_meta($orderID, '_triplea_notify_secret'))) {
                add_post_meta($orderID, '_triplea_notify_secret', $payment_form_data->notify_secret);
            } else {
                update_post_meta($orderID, '_triplea_notify_secret', $payment_form_data->notify_secret);
            }
            if (0 === count(get_post_meta($orderID, '_triplea_crypto_address'))) {
                add_post_meta($orderID, '_triplea_crypto_address', $payment_form_data->crypto_address);
            } else {
                update_post_meta($orderID, '_triplea_crypto_address', $payment_form_data->crypto_address);
            }
        }



        $hostedURL = $payment_form_data->hosted_url;
        echo $hostedURL;
        wp_die();
    }

    /**
     * Generate a strong randomly generated token,
     * used to identify a user's cart order before and after the order has been
     * placed.
     *
     * @return string
     */
    protected function generate_order_txid()
    {
        return md5((uniqid(rand(), true)) . (uniqid(rand(), true)));
    }

    /*
     * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
     */
    public function payment_scripts()
    {
        // we need JavaScript to process a token only on cart/checkout pages, right?
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }

        // if our payment gateway is disabled, we do not have to enqueue JS too
        if ('no' === $this->enabled) {
            return;
        }

        // do not work with card detailes without SSL unless your website is in a test mode
        if (!$this->test_mode && !is_ssl()) {
            //return;
        }

        wp_enqueue_style('wctriplea-checkout-style');
        wp_enqueue_script('wctriplea-checkout-script');
    }

    public static function triplea_exclude_if_subscription_product($available_gateways)
    {

        if (is_admin()) {
            return $available_gateways;
        }
        if (!is_checkout()) {
            return $available_gateways;
        }

        if (!class_exists('WC_Subscriptions')) {
            return $available_gateways;
        }
        if (!class_exists('WC_Subscriptions_Cart')) {
            return $available_gateways;
        }

        if (WC_Subscriptions_Cart::cart_contains_subscription()) {
            unset($available_gateways['triplea_payment_gateway']);
        }

        return $available_gateways;
    }

    /*
     * Fields validation, more in Step 5
     */
    public function validate_fields()
    {
        return true;
    }

    /*
     * We're processing the payments here, everything about it is in Step 5
     */
    public function process_payment($order_id)
    {
        global $wp_version; // or use //include( ABSPATH . WPINC . '/version.php' );

        $this->logger->write_log('process_payment() : Order ' . $order_id . ' placed. Updating payment information.', $this->debugLog);

        $wc_order = wc_get_order($order_id);
        if (empty($wc_order)) {

            $this->logger->write_log('process_payment() : ERROR! Empty woocommerce order. Order was not placed.', $this->debugLog);
            return [
                'reload'   => false,
                'refresh'  => false,
                'result'   => 'failure',
                'messages' => 'Empty woocommerce order. Order was not placed.',
            ];
        }

        if (isset($this->settings['triplea_woocommerce_order_states']) && isset($this->settings['triplea_woocommerce_order_states']['paid'])) {
            $order_status_paid      = $this->settings['triplea_woocommerce_order_states']['paid'];
            $order_status_confirmed = $this->settings['triplea_woocommerce_order_states']['confirmed'];
            $order_status_invalid   = $this->settings['triplea_woocommerce_order_states']['invalid'];
        } else {
            // default values returned by get_status()
            $order_status_paid      = 'wc-on-hold';     // paid but still unconfirmed
            $order_status_confirmed = 'wc-processing';
            $order_status_invalid   = 'wc-failed';
        }
        if (is_wc_endpoint_url('order-pay')) {


            if (OrderUtil::custom_orders_table_usage_is_enabled()) {

                $order = wc_get_order($order_id);
                $payment_data = $this->get_payment_form_status_update($order->get_meta('_triplea_payment_reference', true));
            } else {

                $payment_data = $this->get_payment_form_status_update(get_post_meta($order_id, '_triplea_payment_reference', true));
            }

            $this->logger->write_log('Order pay page process_payment() : payment status check, received data: ' . wc_print_r($payment_data, true), $this->debugLog);
            $status_info = null;
            if (!isset($payment_data->error)) {
                $status_info = self::update_order_status($payment_data, $wc_order, true);
            }
            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url($wc_order),
            ];
        }

        /*
         *  We set a transaction id token, securely randomly generated.
         *  This helps when receiving payment update notifications from the API,
         *  to match the notification with the related order.
         *  (No order ID is available for matching until after payment and order creation, which explains the need for this.)
         */
        $tx_id = WC()->session->get('generate_order_txid'); // $_POST['triplea_order_txid']; // dont trust frontend !
        if (empty($tx_id)) {
            $this->logger->write_log('Order pay page process_payment() : Empty generate_order_txid ', $this->debugLog);

            return [
                'reload'   => false,
                'refresh'  => false,
                'result'   => 'failure',
                'messages' => 'Session is missing order tx id.',
            ];
        }

        if (OrderUtil::custom_orders_table_usage_is_enabled()) {

            $order = wc_get_order($order_id);
            $this->logger->write_log('count IDD' . $order->get_meta('_triplea_tx_id'), $this->debugLog);

            $tx_id = $tx_id;

            $dataOrderTxid = $order->get_meta('_triplea_tx_id');
            if (empty($dataOrderTxid)) {
                $order->add_meta_data('_triplea_tx_id', $tx_id);
                $this->logger->write_log('process_payment() : Adding order_txid to new order metadata', $this->debugLog);
            } else {
                $order->update_meta_data('_triplea_tx_id', $tx_id);
                $this->logger->write_log('process_payment() : Updating order_txid in new order metadata', $this->debugLog);
            }

            $order->save();
        } else {

            if (0 === count(get_post_meta($order_id, '_triplea_tx_id'))) {
                add_post_meta($order_id, '_triplea_tx_id', $tx_id);
                $this->logger->write_log('process_payment() : Adding order_txid to new order metadata', $this->debugLog);
            } else {
                update_post_meta($order_id, '_triplea_tx_id', $tx_id);
                $this->logger->write_log('process_payment() : Updating order_txid in new order metadata', $this->debugLog);
            }
        }

        // Get payment reference from session (don't trust front-end form data).
        $payment_reference = WC()->session->get('triplea_payment_reference');
        if (empty($payment_reference)) {
            $this->logger->write_log('process_payment() : Session is missing payment reference', $this->debugLog);

            return [
                'reload'   => false,
                'refresh'  => false,
                'result'   => 'failure',
                'messages' => 'Session is missing payment reference.',
            ];
        }

        if (OrderUtil::custom_orders_table_usage_is_enabled()) {
            $order = wc_get_order($order_id);

            if (empty($order->get_meta('_triplea_payment_reference'))) {
                $order->add_meta_data('_triplea_payment_reference', $payment_reference);
                $this->logger->write_log('process_payment() : Adding payment_reference to new order metadata', $this->debugLog);
            } else {
                $order->update_meta_data('_triplea_payment_reference', $payment_reference);
                $this->logger->write_log('process_payment() : Updating payment_reference in new order metadata', $this->debugLog);
            }

            $order->save();
        } else {
            if (0 === count(get_post_meta($order_id, '_triplea_payment_reference'))) {
                add_post_meta($order_id, '_triplea_payment_reference', $payment_reference);
                $this->logger->write_log('process_payment() : Adding payment_reference to new order metadata', $this->debugLog);
            } else {
                update_post_meta($order_id, '_triplea_payment_reference', $payment_reference);
                $this->logger->write_log('process_payment() : Updating payment_reference in new order metadata', $this->debugLog);
            }
        }

        // Get access token from session (don't trust front-end form data).
        $access_token = WC()->session->get('triplea_payment_access_token');
        if (empty($access_token)) {
            return [
                'reload'   => false,
                'refresh'  => false,
                'result'   => 'failure',
                'messages' => 'Session is missing access token.',
            ];
        }

        if (OrderUtil::custom_orders_table_usage_is_enabled()) {
            $order = wc_get_order($order_id);

            $meta_key = '_triplea_access_token';
            $logger_message = 'process_payment() : Adding ' . $meta_key . ' to new order metadata';

            if (empty($order->get_meta($meta_key))) {
                $order->add_meta_data($meta_key, $access_token);
                $this->logger->write_log($logger_message, $this->debugLog);
            } else {
                $order->update_meta_data($meta_key, $access_token);
                $this->logger->write_log('process_payment() : Updating ' . $meta_key . ' in new order metadata', $this->debugLog);
            }

            $order->save();
        } else {
            $meta_key = '_triplea_access_token';
            $logger_message = 'process_payment() : Adding ' . $meta_key . ' to new order metadata';

            if (0 === count(get_post_meta($order_id, $meta_key))) {
                add_post_meta($order_id, $meta_key, $access_token);
                $this->logger->write_log($logger_message, $this->debugLog);
            } else {
                update_post_meta($order_id, $meta_key, $access_token);
                $this->logger->write_log('process_payment() : Updating ' . $meta_key . ' in new order metadata', $this->debugLog);
            }
        }


        // Get notify_secret from session (don't trust front-end form data).
        $notify_secret = WC()->session->get('triplea_payment_notify_secret');
        if (empty($notify_secret)) {
            return [
                'reload'   => false,
                'refresh'  => false,
                'result'   => 'failure',
                'messages' => 'Session is missing a notify secret.',
            ];
        }

        if (OrderUtil::custom_orders_table_usage_is_enabled()) {
            $order = wc_get_order($order_id);

            $meta_key = '_triplea_notify_secret';
            $logger_message = 'process_payment() : Adding ' . $meta_key . ' to new order metadata';

            if (empty($order->get_meta($meta_key))) {
                $order->add_meta_data($meta_key, $notify_secret);
                $this->logger->write_log($logger_message, $this->debugLog);
            } else {
                $order->update_meta_data($meta_key, $notify_secret);
                $this->logger->write_log('process_payment() : Updating ' . $meta_key . ' in new order metadata', $this->debugLog);
            }

            $order->save();
        } else {
            $meta_key = '_triplea_notify_secret';
            $logger_message = 'process_payment() : Adding ' . $meta_key . ' to new order metadata';

            if (0 === count(get_post_meta($order_id, $meta_key))) {
                add_post_meta($order_id, $meta_key, $notify_secret);
                $this->logger->write_log($logger_message, $this->debugLog);
            } else {
                update_post_meta($order_id, $meta_key, $notify_secret);
                $this->logger->write_log('process_payment() : Updating ' . $meta_key . ' in new order metadata', $this->debugLog);
            }
        }



        // Could repeat the above, if needed, for order currency, order amount, exchange rate, and more.


        // Call TripleA API to get payment details (paid or not? enough or too little?).
        $payment_data = $this->get_payment_form_status_update($payment_reference);

        $this->logger->write_log('process_payment() : payment status check, received data: ' . wc_print_r($payment_data, true), $this->debugLog);

        $status_info = null;
        if (!isset($payment_data->error)) {
            $status_info = self::update_order_status($payment_data, $wc_order, true);
        }


        /**
         * security check regarding shopping cart contents tampering *
         */
        // Which amount was paid? What what the shopping cart's total
        // value (+tax,shipping,etc) when the payment form was displayed?
        $cart_total_paid = $payment_data->order_amount; // WC()->session->get('triplea_cart_total');
        $cart_total_paid_str = sprintf("%.4f", $cart_total_paid);

        // What is the actual value of the order? Did anything get added to the shopping cart in a separate tab?
        $order_total_due = $wc_order->get_total();
        $order_total_due_str = sprintf("%.4f", $order_total_due);
        $CART_TAMPERED = false;
        if ($cart_total_paid_str != $order_total_due_str) {
            $CART_TAMPERED = true;
            $this->logger->write_log('process_payment() : shopping cart value during cryptocurrency payment: ' . $cart_total_paid_str, $this->debugLog);
            $this->logger->write_log('process_payment() : shopping cart value after order placement : ' . $order_total_due_str, $this->debugLog);
            $this->logger->write_log('process_payment() : WARNING! Shopping cart contents were modified between the moment the cryptocurrency payment form was displayed and the moment the cryptocurrency payment was made.' . $order_total_due_str, $this->debugLog);
            $this->logger->write_log('process_payment() : WARNING! Most likely ill intent on the customer\'s part. Order will be marked as invalid, you may refund the customer through your TripleA dashboard.', $this->debugLog);

            // Save the order notes, empty the cart, inform the Checkout page the order has been saved.
            $wc_order->add_order_note(__("Shopping cart contents were modified between the moment the cryptocurrency payment form was displayed and the moment the cryptocurrency payment was made.", 'triplea-cryptocurrency-payment-gateway-for-woocommerce'));
            $wc_order->add_order_note(__("Order will be marked as invalid, you may choose to refund the customer through your TripleA dashboard.", 'triplea-cryptocurrency-payment-gateway-for-woocommerce'));

            $wc_order->update_status($order_status_invalid);
        }
        /** /security check regarding shopping cart contents tampering **/


        if (isset($payment_data->error) || $status_info['error']) {
            wc_add_notice(__('Payment Failed', 'triplea-cryptocurrency-payment-gateway-for-woocommerce'), $notice_type = 'error');

            return [
                'reload'   => false,
                'refresh'  => false,
                'result'   => 'failure',
                'messages' => 'Exception occured. Message: failed API status payment status check',
            ];
        }

        WC()->cart->empty_cart();

        // Clearing session data for payment, so that a new payment could be made.
        WC()->session->set('triplea_payment_hosted_url', null);
        WC()->session->set('triplea_payment_reference', null);
        WC()->session->set('triplea_payment_access_token', null);
        WC()->session->set('triplea_payment_access_token_expiry', null);
        WC()->session->set('triplea_payment_notify_secret', null);
        WC()->session->set('triplea_payment_crypto_currency', null);
        WC()->session->set('triplea_payment_crypto_address', null);
        WC()->session->set('triplea_payment_crypto_amount', null);
        WC()->session->set('triplea_payment_order_currency', null);
        WC()->session->set('triplea_payment_order_amount', null);
        WC()->session->set('triplea_payment_exchange_rate', null);
        WC()->session->set('triplea_cart_total', null);
        WC()->session->set('generate_order_txid', null);

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($wc_order),
        ];
    }

    private function get_payment_form_status_update($payment_reference)
    {

        $oauth_token = $this->get_option('oauth_token');
        if (empty($oauth_token)) {
            wp_die('Missing oauth token for bitcoin payments with local currency settlement.');
        }

        $post_url = "https://api.triple-a.io/api/v2/payment/$payment_reference";

        $result = wp_remote_get($post_url, [
            'headers'     => [
                'Authorization' => 'Bearer ' . $oauth_token,
            ],
            //'sslverify' => false,
            //'body'        => json_encode($body),
            'data_format' => 'body',
        ]);

        if (is_wp_error($result)) {
            wp_die('Could not complete the payment status API request.');
        }

        if ($result['response']['code'] > 299) {
            return (object) [
                'error'   => 'Error happened, could not complete the payment form request.',
                'code'    => $result['response']['code'],
                'message' => $result['response']['message'],
            ];
        }

        $json_result = json_decode($result['body']);
        if (!isset($json_result->payment_reference)) {
            return [
                'error' => 'Error happened, wrong payment form request data format received.',
            ];
        }

        return $json_result;
    }

    /**
     * @param array|object     $payment_data
     * @param \WC_Order|null $wc_order
     * @param bool      $placing_new_order
     *
     * @return array|void
     */
    public static function update_order_status($payment_data, $wc_order, $placing_new_order = false, $unix_timestamp = null, $hex_signature = null)
    {
        $triplea = new TripleA_Payment_Gateway();

        $triplea->logger->write_log('update_order_status():', $triplea->debugLog);

        $notes               = [];
        $return_error        = false;
        $return_payment_tier = '';
        $return_order_status = '';

        if (!isset($wc_order) || empty($wc_order)) {
            // No order provided. If the payment data contains an order_txid,
            // we can use it to find the matching order.
            if (
                isset($payment_data->webhook_data)
                && isset($payment_data->webhook_data->order_txid)
                && !empty($payment_data->webhook_data->order_txid)
            ) {

                // Get the WooCommerce order ID with the matching client tx ID.
                $rest = new REST($triplea->api);
                $order_id = $rest->triplea_get_orderid_from_txid($payment_data->webhook_data->order_txid, $debug_log_enabled);
                if ($order_id < 0) {
                    $triplea->logger->write_log('update_order_status() : ERROR. No matching order found for tx id ' . $payment_data->webhook_data->order_txid . '.', $triplea->debugLog);
                } else {
                    $wc_order = wc_get_order($order_id);
                    $triplea->logger->write_log('update_order_status() : Found matching order ' . $order_id . ' found for tx id ' . $payment_data->webhook_data->order_txid . '.', $triplea->debugLog);
                }
            }
        }
        // Else given an existing (newly placed) order


        if (!isset($wc_order) || empty($wc_order)) {
            $triplea->logger->write_log('process_payment() : ERROR! Missing WooCommerce order, cannot continue.', $triplea->debugLog);
            $return_error = true;

            if ($placing_new_order) {
                $return_values = [
                    'result'       => 'failure',
                    'payment_tier' => $return_payment_tier,
                    'order_status' => $return_order_status,
                    'error'        => $return_error,
                ];
                $triplea->logger->write_log('update_order_status() : Return values: ' . $return_values, $triplea->debugLog);
                return $return_values;
            } else {
                return;
            }
        }
        $order_id = $wc_order->get_id();
        //$tx_id = get_post_meta($order_id, '_triplea_tx_id');
        //fix //improve

        if (OrderUtil::custom_orders_table_usage_is_enabled()) {
            $order = wc_get_order($order_id);
            $tx_id = $order->get_meta('_triplea_tx_id', true);
        } else {
            $tx_id = get_post_meta($order_id, '_triplea_tx_id', true);
        }

        // default values returned by get_status()
        if (isset($triplea->settings['triplea_woocommerce_order_states']) && isset($triplea->settings['triplea_woocommerce_order_states']['paid'])) {
            $order_status_paid      = $triplea->settings['triplea_woocommerce_order_states']['paid'];
            $order_status_confirmed = $triplea->settings['triplea_woocommerce_order_states']['confirmed'];
            $order_status_invalid   = $triplea->settings['triplea_woocommerce_order_states']['invalid'];
        } else {
            // default values returned by get_status()
            $order_status_paid      = 'wc-on-hold';     // paid but still unconfirmed
            $order_status_confirmed = 'wc-processing';
            $order_status_invalid   = 'wc-failed';
        }

        $block_order_status_update = false;
        $order_status = $wc_order->get_status();
        if ('wc-' . $order_status === $order_status_invalid || $order_status === 'failed' || strpos($order_status, 'fail') !== false || strpos($order_status, 'invalid') !== false) {
            $block_order_status_update = true;
            $triplea->logger->write_log("update_order_status() : order status is " . $order_status . ". The following information is added for logging purposes, the order status will not be updated. Contact us at plugin.support@triple-a.io if you think there has been a mistake.", $triplea->debugLog);
        }


        if (isset($payment_data->error)) {

            triplea_write_log("update_order_status() : payment status check returned an ERROR : \n" . print_r($payment_data, true), $debug_log_enabled);

            $return_order_status = $order_status_paid;
            $return_error = true;

            $notes[] = 'Could not verify the payment status, server returned an error. If the cryptocurrency payments debug log is enabled, the error will be in the log. Please <a href="mailto:plugin.support@triple-a.io">share that with us at plugin.support@triple-a.io</a>';

            if ($placing_new_order) {
                // We tried to verify the payment status (anything paid or not? how much?).
                // However something went wrong. That does not mean the user did not pay...
                // We save the order, mark it as ON HOLD.
                // but!!! we add a note to indicate to the merchant that there might be a problem with this order, not sure if a payment was made or not.
                if (!$block_order_status_update) {
                    $wc_order->update_status($order_status_paid); // on hold, might be paid (but not confirmed)
                }

                $notes[] = 'There was a problem when connecting to the TripleA server. The user may or may not have paid.' . '<br>'
                    . 'If a payment was made, this order should automatically update within 10 minutes to 1 hour.';

                $notes[] = 'If the order does not get updated or if you have any question, please contact us at <a href="mailto:plugin.support@triple-a.io">plugin.support@triple-a.io</a> and share with us the order transaction id = \'' . $tx_id . '\'.';
            }
        } else {
            $bitcoin_address = $payment_data->crypto_address;

            // (un)confirmed_crypto_amt is now DEPRECATED
            //         $unconf_crypto_amount = floatval( $payment_data->unconfirmed_crypto_amt ) ? floatval( $payment_data->unconfirmed_crypto_amt ) : 0.0;
            //         $conf_crypto_amount = floatval( $payment_data->confirmed_crypto_amt ) ? floatval( $payment_data->confirmed_crypto_amt ) : 0.0;
            //         $crypto_amount_paid = $unconf_crypto_amount + $conf_crypto_amount;
            $crypto_amount_paid = $unconf_crypto_amount = floatval($payment_data->payment_crypto_amount) ? floatval($payment_data->payment_crypto_amount) : 0.0;

            $formatted_note_crypto_amount = '';
            $formatted_crypto_amount_paid = '';
            if ($payment_data->crypto_currency == 'LNBC') {
                $formatted_note_crypto_amount = number_format($payment_data->crypto_amount);
                $formatted_crypto_amount_paid = number_format($crypto_amount_paid);
            } else {
                $formatted_note_crypto_amount = number_format($payment_data->crypto_amount, 8);
                $formatted_crypto_amount_paid = number_format($crypto_amount_paid, 8);
            }

            // (un)confirmed_order_amt is now DEPRECATED
            //         $unconf_order_amount = floatval( $payment_data->unconfirmed_order_amt ) ? floatval( $payment_data->unconfirmed_order_amt ) : 0.0;
            //         $conf_order_amount = floatval( $payment_data->confirmed_order_amt ) ? floatval( $payment_data->confirmed_order_amt ) : 0.0;
            //         $order_amount_paid = $unconf_order_amount + $conf_order_amount;
            $order_amount_paid = floatval($payment_data->receive_amount) ? floatval($payment_data->receive_amount) : 0.0;

            if ($placing_new_order) {
                $notes[] = 'Amount due: <strong>' . $payment_data->crypto_currency . ' ' . $formatted_note_crypto_amount . '</strong>' . '<br>'
                    . 'Value: ' . $payment_data->order_currency . ' ' . $payment_data->order_amount . '<br>'
                    . ' <br> '
                    . 'To be paid to ' . $payment_data->crypto_currency . ' address:' . '<br>'
                    . $bitcoin_address . "<br>"
                    . "Payment reference: <br>"
                    . $payment_data->payment_reference . "<br>"
                    . "<a href='https://www.blockchain.com/search?search=" . $bitcoin_address . "' target='_blank'>(View details on the blockchain)</a>";
            }

            // Depending on the results, update the order state.
            switch ($payment_data->payment_tier) {
                case 'none':
                    // No payment received yet.
                    // Order was placed by front-end but we don't know if there will be a payment or not.
                    if (!$block_order_status_update) {
                        $wc_order->update_status($order_status_paid); // on hold, might be paid (but not confirmed)
                    }

                    $return_payment_tier = $payment_data->payment_tier;
                    $return_order_status = $order_status_paid;

                    if ($placing_new_order) {
                        $triplea->logger->write_log('update_order_status() : No payment received (yet). Order was placed by front-end despite no payment having been detected.', $triplea->debugLog);

                        $notes[] = 'No payment detected yet for ' . $payment_data->crypto_currency . ' address ' . $bitcoin_address . '.' . '<br>' . 'The user may have paid, payment form could have expired before the transaction was detected. (This can happen with some exchanges that delay transactions.)'
                            . '<br>' . 'It may also be that the user <strong>did not pay</strong> and just placed the order.';
                        $notes[] = 'If a payment was made, this order will be updated automatically. If you have any doubt, <a href="mailto:plugin.support@triple-a.io">feel free to contact us</a>.';
                    } else {
                        $triplea->logger->write_log('update_order_status() : No payment received (yet).', $triplea->debugLog);
                    }
                    break;

                case 'hold':

                    if (!$block_order_status_update) {
                        $wc_order->update_status($order_status_paid); // on hold, might be paid (but not confirmed)
                    }

                    $return_payment_tier = $payment_data->payment_tier;
                    $return_order_status = $order_status_paid;

                    if ($placing_new_order) {
                        $triplea->logger->write_log('update_order_status() : Confirmed that a payment was made, order payment still waiting for validation.', $triplea->debugLog);
                        $triplea->logger->write_log('update_order_status() : Current payment status: ' . $payment_data->status, $triplea->debugLog);
                        $notes[] = 'Payment detected, awaiting validation.';
                    } else {
                        $triplea->logger->write_log('update_order_status() : Order payment still waiting for validation.', $triplea->debugLog);
                    }

                    // TODO add a message specifying details about how much was paid (enough or not? how much paid or missing?)

                    break;

                case 'short':

                    if (!$block_order_status_update) {
                        $wc_order->update_status($order_status_invalid);
                    }

                    $return_payment_tier = $payment_data->payment_tier;
                    $return_order_status = $order_status_invalid;

                    $notes[] = 'Paid: <strong>' . $payment_data->crypto_currency . '</strong>'
                        . ' <strong>' . $formatted_crypto_amount_paid . '</strong>' . '<br>'
                        . 'Value: ' . $payment_data->order_currency . ' ' . $order_amount_paid . '<br>'
                        . '<br>'
                        . 'Paid to ' . $payment_data->crypto_currency . ' address:' . '<br>'
                        . $bitcoin_address . "<br>"
                        . "<a href='https://www.blockchain.com/search?search=" . $bitcoin_address . "' target='_blank'>(View details on the blockchain)</a>";

                    $notes[] = '<strong>' . $payment_data->crypto_currency . ' amount paid is insufficient!</strong>' . '<br>'
                        . 'Missing ' . ($formatted_note_crypto_amount - $formatted_crypto_amount_paid) . ' ' . $payment_data->crypto_currency;
                    $triplea->logger->write_log('update_order_status() : Confirmed that a payment was made, for an insufficient amount.', $triplea->debugLog);

                    // TODO come up with a process to help merchants handle this scenario (request extra payment or refund or ..?)

                    break;

                case 'good':

                    if (!$block_order_status_update) {
                        $wc_order->update_status($order_status_confirmed); // on hold, might be paid (but not confirmed)
                        $return_order_status = $order_status_confirmed;
                    } else {
                        $return_order_status = $order_status_invalid;
                    }

                    $return_payment_tier = $payment_data->payment_tier;

                    $notes[] = 'Paid: <strong>' . $payment_data->crypto_currency . ' ' . $formatted_crypto_amount_paid . '</strong>' . '<br>'
                        . 'Value: ' . $payment_data->order_currency . ' ' . $order_amount_paid . '<br>'
                        . '<br>'
                        . 'Paid to ' . $payment_data->crypto_currency . ' address:' . '<br>'
                        . $bitcoin_address . "<br>"
                        . "<a href='https://www.blockchain.com/search?search=" . $bitcoin_address . "' target='_blank'>(View details on the blockchain)</a>";

                    if ($crypto_amount_paid > $payment_data->crypto_amount) {
                        $notes[] = 'User paid too much.' . '<br>'
                            . 'The user may contact you to ask for a refund.' . '<br>'
                            . 'If you need assistance, <a href="mailto:plugin.support@triple-a.io">simply email us at plugin.support@triple-a.io</a>.';
                    } else {
                        if (!$block_order_status_update) {
                            $notes[] = 'Correct amount paid.';
                        }
                    }

                    if (!$block_order_status_update) {
                        $notes[] = 'Order completed.';
                        $triplea->logger->write_log('update_order_status() : Confirmed that a sufficient payment was made.', $triplea->debugLog);
                    }

                    break;

                case 'invalid':

                    if (!$block_order_status_update) {
                        $wc_order->update_status($order_status_invalid);
                    }

                    $return_payment_tier = $payment_data->payment_tier;
                    $return_order_status = $order_status_invalid;

                    $notes[] = 'Payment failed or is invalid.' . '<br>';
                    //. 'Payment might have expired due to a very low transaction fee paid by the user or a double-spend attempt might have occurred.';
                    $triplea->logger->write_log('update_order_status() : Payment failed or invalid. Payment might have expired due to super low transaction fee or a double-spend attempt might have occurred.', $triplea->debugLog);

                    break;

                default:
                    $triplea->logger->write_log('update_order_status() : Unknown payment_tier received. Value:"' . $payment_data->payment_tier . '".', $triplea->debugLog);

                    $return_payment_tier = $payment_data->payment_tier;
                    $return_error = true;
            }
            /*
                if (0 === count(get_post_meta($order_id, '_triplea_order_status'))) {
                    add_post_meta($order_id, '_triplea_order_status', $return_order_status);
                }
                if (0 === count(get_post_meta($order_id, '_triplea_payment_tier'))) {
                    add_post_meta($order_id, '_triplea_payment_tier', $return_payment_tier);
                }
                if (0 === count(get_post_meta($order_id, '_triplea_payment_status'))) {
                    add_post_meta($order_id, '_triplea_payment_status', $payment_data->status);
                }
                if (0 === count(get_post_meta($order_id, '_triplea_order_amount'))) {
                    add_post_meta($order_id, '_triplea_order_amount', $payment_data->order_amount);
                }
                if (0 === count(get_post_meta($order_id, '_triplea_order_crypto_amount'))) {
                    add_post_meta($order_id, '_triplea_order_crypto_amount', $payment_data->crypto_amount);
                }
                if (0 === count(get_post_meta($order_id, '_triplea_amount_paid'))) {
                    add_post_meta($order_id, '_triplea_amount_paid', $order_amount_paid);
                }
                if (0 === count(get_post_meta($order_id, '_triplea_crypto_amount_paid'))) {
                    add_post_meta($order_id, '_triplea_crypto_amount_paid', $crypto_amount_paid);
                }
                if (0 === count(get_post_meta($order_id, '_triplea_crypto_currency'))) {
                    add_post_meta($order_id, '_triplea_crypto_currency', $payment_data->crypto_currency);
                }
                if (0 === count(get_post_meta($order_id, '_triplea_order_currency'))) {
                    add_post_meta($order_id, '_triplea_order_currency', $payment_data->order_currency);
                }
            */
            //fix
            if (OrderUtil::custom_orders_table_usage_is_enabled()) {
                $order = wc_get_order($order_id);

                if (!$order->get_meta('_triplea_order_status')) {
                    $order->add_meta_data('_triplea_order_status', $return_order_status);
                }

                if (!$order->get_meta('_triplea_payment_tier')) {
                    $order->add_meta_data('_triplea_payment_tier', $return_payment_tier);
                }

                if (!$order->get_meta('_triplea_payment_status')) {
                    $order->add_meta_data('_triplea_payment_status', $payment_data->status);
                }

                if (!$order->get_meta('_triplea_order_amount')) {
                    $order->add_meta_data('_triplea_order_amount', $payment_data->order_amount);
                }

                if (!$order->get_meta('_triplea_order_crypto_amount')) {
                    $order->add_meta_data('_triplea_order_crypto_amount', $payment_data->crypto_amount);
                }

                if (!$order->get_meta('_triplea_amount_paid')) {
                    $order->add_meta_data('_triplea_amount_paid', $order_amount_paid);
                }

                if (!$order->get_meta('_triplea_crypto_amount_paid')) {
                    $order->add_meta_data('_triplea_crypto_amount_paid', $crypto_amount_paid);
                }

                if (!$order->get_meta('_triplea_crypto_currency')) {
                    $order->add_meta_data('_triplea_crypto_currency', $payment_data->crypto_currency);
                }

                if (!$order->get_meta('_triplea_order_currency')) {
                    $order->add_meta_data('_triplea_order_currency', $payment_data->order_currency);
                }

                $order->save();
            } else {
                if (0 === count(get_post_meta($order_id, '_triplea_order_status'))) {
                    add_post_meta($order_id, '_triplea_order_status', $return_order_status);
                }

                if (0 === count(get_post_meta($order_id, '_triplea_payment_tier'))) {
                    add_post_meta($order_id, '_triplea_payment_tier', $return_payment_tier);
                }

                if (0 === count(get_post_meta($order_id, '_triplea_payment_status'))) {
                    add_post_meta($order_id, '_triplea_payment_status', $payment_data->status);
                }

                if (0 === count(get_post_meta($order_id, '_triplea_order_amount'))) {
                    add_post_meta($order_id, '_triplea_order_amount', $payment_data->order_amount);
                }

                if (0 === count(get_post_meta($order_id, '_triplea_order_crypto_amount'))) {
                    add_post_meta($order_id, '_triplea_order_crypto_amount', $payment_data->crypto_amount);
                }

                if (0 === count(get_post_meta($order_id, '_triplea_amount_paid'))) {
                    add_post_meta($order_id, '_triplea_amount_paid', $order_amount_paid);
                }

                if (0 === count(get_post_meta($order_id, '_triplea_crypto_amount_paid'))) {
                    add_post_meta($order_id, '_triplea_crypto_amount_paid', $crypto_amount_paid);
                }

                if (0 === count(get_post_meta($order_id, '_triplea_crypto_currency'))) {
                    add_post_meta($order_id, '_triplea_crypto_currency', $payment_data->crypto_currency);
                }

                if (0 === count(get_post_meta($order_id, '_triplea_order_currency'))) {
                    add_post_meta($order_id, '_triplea_order_currency', $payment_data->order_currency);
                }
            }
        }

        // Save the order notes, empty the cart, inform the Checkout page the order has been saved.
        foreach ($notes as $note) {
            $wc_order->add_order_note(__($note, 'triplea-cryptocurrency-payment-gateway-for-woocommerce'));
        }

        if ($placing_new_order) {
            $return_values = [
                'result'       => 'success',
                'payment_tier' => $return_payment_tier,
                'order_status' => $return_order_status,
                'error'        => $return_error,
            ];
            $triplea->logger->write_log('update_order_status() : Return values: ' . wc_print_r($return_values, true), $triplea->debugLog);
            return $return_values;
        } else {
            return;
        }
    }

    private function randomString($length = 24)
    {
        if (PHP_VERSION >= 7) {
            $bytes = random_bytes($length);
        } else {
            $bytes = openssl_random_pseudo_bytes($length);
        }

        return bin2hex($bytes); // 48 characters
    }

    /**
     *   Order hasn't been created yet.
     *   We can generate a payload and save it in the session of the current user or visitor.
     */
    protected function prepare_encrypted_order_payload($client_txid)
    {

        $user_id = '';

        if (function_exists('is_checkout_pay_page') && is_checkout_pay_page()) {
            $order_id = get_query_var('order-pay');
            $order    = wc_get_order($order_id);

            $amount   = esc_attr(((WC()->version < '2.7.0') ? $order->order_total : $order->get_total()));
            $currency = esc_attr(((WC()->version < '2.7.0') ? $order->order_currency : $order->get_currency()));
        } else {
            $amount   = esc_attr(WC()->cart->total);
            $currency = esc_attr(strtoupper(get_woocommerce_currency()));
        }

        $payload_cleartext = [
            'client_txid'    => $client_txid,
            'user_id'        => $user_id,
            'order_amount'   => $amount,
            //  'api_id'         => $api_id,
            'local_currency' => $currency,
        ];

        $payload_jsontext = json_encode($payload_cleartext);

        $client_secret_key = $this->triplea_client_secret_key;
        if (!isset($client_secret_key) || empty($client_secret_key)) {
            $this->logger->write_log('ERROR. No client keypair found', $this->debugLog);
            return '[missing client keypair]';
        }

        $server_public_key = null;

        if (empty($server_public_key)) {
            $fallback          = true;
            $server_public_key = 'A4cxSkcL/QLPaEE5AKFevgGgSLe+/RtAov7iDf0e1Rw=';
        } else {
            $fallback = false;
        }

        $client_to_server_keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey(
            base64_decode($client_secret_key),
            base64_decode($server_public_key)
        );

        $message_nonce     = random_bytes(SODIUM_CRYPTO_BOX_NONCEBYTES);
        $payload_encrypted = sodium_crypto_box(
            $payload_jsontext,
            $message_nonce,
            $client_to_server_keypair
        );

        if ($fallback) {
            return base64_encode($payload_encrypted) . ':' . base64_encode($message_nonce) . ':' . 'fallback';
        }
        return base64_encode($payload_encrypted) . ':' . base64_encode($message_nonce);
    }

    /**
     * @return string
     * @throws SodiumException
     */
    protected function prepare_encrypted_public_key_shared()
    {

        $client_public_key = $this->triplea_client_public_key;
        $client_secret_key = $this->triplea_client_secret_key;

        if (
            !isset($client_public_key) || empty($client_public_key)
            || !isset($client_secret_key) || empty($client_secret_key)
        ) {
            $this->logger->write_log('Prepare_encrypted_public_key_shared(): No keypair found', $this->debugLog);
            return '[missing client keypair]';
        }

        $server_public_key = null;

        if (empty($server_public_key)) {
            $fallback          = true;
            $server_public_key = 'A4cxSkcL/QLPaEE5AKFevgGgSLe+/RtAov7iDf0e1Rw=';
        } else {
            $fallback = false;
        }

        if (!function_exists('sodium_crypto_box_keypair_from_secretkey_and_publickey')) {
            $this->logger->write_log('ERROR! Missing sodium_crypto_ functions', $this->debugLog);
            return '[missing crypto functions]';
        }

        $message = $client_public_key; // We're providing the public key of the client (= us here)

        $message_nonce = random_bytes(SODIUM_CRYPTO_BOX_NONCEBYTES);
        $ciphertext    = sodium_crypto_secretbox(
            base64_decode($message),
            $message_nonce,
            base64_decode($server_public_key)
        );
        if ($fallback) {
            $encrypted_public_key_shared = base64_encode($ciphertext) . ':' . base64_encode($message_nonce) . ':' . 'fallback';
        } else {
            $encrypted_public_key_shared = base64_encode($ciphertext) . ':' . base64_encode($message_nonce);
        }

        return $encrypted_public_key_shared;
    }

    /**
     * @param $balance_payload_full
     * @param $wc_order
     *
     * @return array
     */
    protected function decrypt_payload($balance_payload_full, $wc_order)
    {

        // Init needed values
        $server_public_enc_key_conversion = null;
        $client_secret_key                = $this->triplea_client_secret_key;


        $triplea_public_enc_key = $server_public_enc_key_conversion;

        if (empty($triplea_public_enc_key)) {
            $triplea_public_enc_key = 'A4cxSkcL/QLPaEE5AKFevgGgSLe+/RtAov7iDf0e1Rw=';
        }

        $client_secret_enc_key = $client_secret_key;

        $payload_status_data = $this->api->triplea_cryptocurrency_payment_gateway_for_woocommerce_decrypt_payload($balance_payload_full, $client_secret_enc_key, $triplea_public_enc_key);

        return $payload_status_data;
    }

    /**
     * Process refund.
     *
     * If the gateway declares 'refunds' support, this will allow it to refund.
     * a passed in amount.
     *
     * @param int    $order_id Order ID.
     * @param float  $amount   Refund amount.
     * @param string $reason   Refund reason.
     *
     * @return boolean True or false based on success, or a WP_Error object.
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        return false;
    }

    /**
     * Extending WooCommerce settings fields, adding our specific script
     * for the TripleA Pubkey ID request.
     *
     * @param string $key  Field key.
     * @param array  $data Field data.
     *
     * @return string
     * @since  1.0.0
     */
    public function generate_triplea_pubkeyid_script_html($key, $data)
    {

        $field_key = $this->get_field_key($key);

        $TRIPLEA_CRYPTOCURRENCY_PAYMENT_GATEWAY_FOR_WOOCOMMERCE_API_ENDPOINT = site_url() . '?rest_route=/triplea/v2/tx_update/' . get_option('triplea_api_endpoint_token');

        ob_start();
        ?>
        </table>

        <table class="triplea-form-table">
        <?php
                return ob_get_clean();
    }

    /**
     * Generate normal text (not in a table, rather a single line of text,
     * paragraph style).
     *
     * @param string $key  Field key.
     * @param array  $data Field data.
     *
     * @return string
     * @since  1.0.0
     */
    public function generate_paragraph_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults  = [
            'title' => '',
            'class' => '',
        ];
        $data      = wp_parse_args($data, $defaults);
        ob_start();
        ?>
        </table>
        <p class="wc-settings-sub-title <?php echo esc_attr($data['class']); ?>" id="<?php echo esc_attr($field_key); ?>">
            <?php echo wp_kses_post($data['title']); ?></p>
        <table class="triplea-form-table">
        <?php
        return ob_get_clean();
    }

    public function generate_anchor_html($key, $data)
    {
        $defaults = [
            'title' => '',
            'class' => '',
        ];
        $data     = wp_parse_args($data, $defaults);
        ob_start();
        ?>
        </table>
        <a id="<?php echo wp_kses_post($data['title']); ?>"></a>
        <a name="<?php echo wp_kses_post($data['title']); ?>"></a>
        <table class="triplea-form-table">
        <?php
        return ob_get_clean();
    }

    public function generate_custom_html($key, $data)
    {
        $defaults = [
            'markup' => '',
        ];
        $data     = wp_parse_args($data, $defaults);
        ob_start();
        ?>
        </table>
        <?php echo $data['markup']; ?>
        <table class="triplea-form-table">
        <?php
        return ob_get_clean();
    }

    public function generate_text_ifnotempty_html($key, $data)
    {
        if (empty($this->get_option($key))) {
            return '';
        } else {
            return $this->generate_text_html($key, $data);
        }
    }

    /**
     * Generate hidden Input HTML.
     *
     * @param string $key  Field key.
     * @param array  $data Field data.
     *
     * @return string
     * @since  1.0.0
     */
    public function generate_hidden_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults  = [
            'type' => 'hidden',
        ];
        $data      = wp_parse_args($data, $defaults);

        ob_start();
        ?>
            <input type="<?php echo esc_attr($data['type']); ?>" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" value="<?php echo esc_attr($this->get_option($key)); ?>" />
        <?php
        return ob_get_clean();
    }

    public function generate_table_markup_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults  = [
            'title'       => '',
            'class'       => '',
            'description' => '',
            'markup'      => '',
        ];
        $data      = wp_parse_args($data, $defaults);
        ob_start();
        ?>
            <tr valign="top" class="<?php echo esc_attr($data['class']); ?>">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?></label>
                </th>
                <td class="forminp">
                    <?php echo $data['markup']; ?>
                </td>
            </tr>
        <?php
        return ob_get_clean();
    }

    public function generate_order_states_html($data)
    {
        $defaults = [
            'markup' => '',
        ];
        $data     = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        </table>
        <?php echo $data['markup']; ?>
        <table class="triplea-form-table">
    <?php
        return ob_get_clean();
    }
}
