<?php

namespace Triplea\WcTripleaCryptoPayment;

defined( 'ABSPATH' ) || die();

class Reviews {

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'triplea_wc_void_check_installation_time' ] );
        add_action( 'admin_init', [ __CLASS__, 'triplea_wc_void_spare_me' ], 5 );
    }

    //check if review notice should be shown or not
    public static function triplea_wc_void_check_installation_time() {

        $letalone = get_option( 'triplea_wc_spare_me', "0" );

        if ( $letalone == "1" || $letalone == "3" ) {
            return;
        }

        $install_date = get_option( 'wc_triplea_crypto_payment_installed', strtotime("now") );
        $past_date    = strtotime( '-10 days' );

        $remind_time = get_option( 'triplea_wc_remind_me', strtotime( "now" ) );
        $remind_due  = strtotime( '+15 days', $remind_time );
        $now         = strtotime( "now" );

        if ( $now >= $remind_due ) {
            add_action( 'admin_notices', [ __CLASS__, 'triplea_wc_void_grid_display_admin_notice'] );
        } else if ( ( $past_date >= $install_date ) &&  $letalone !== "2" ) {
            add_action( 'admin_notices', [ __CLASS__, 'triplea_wc_void_grid_display_admin_notice' ] );
        }
    }

    /**
     * Display Admin Notice, asking for a review
     **/
    public static function triplea_wc_void_grid_display_admin_notice() {
        // wordpress global variable
        global $pagenow;

        $exclude = [ 'themes.php', 'users.php', 'tools.php', 'options-general.php', 'options-writing.php', 'options-reading.php', 'options-discussion.php', 'options-media.php', 'options-permalink.php', 'options-privacy.php', 'edit-comments.php', 'upload.php', 'media-new.php', 'admin.php', 'import.php', 'export.php', 'site-health.php', 'export-personal-data.php', 'erase-personal-data.php','profile.php','user-edit.php' ];

        if ( ! in_array( $pagenow, $exclude ) && current_user_can( 'administrator' ) ) {

            wp_enqueue_style( 'wctriplea-admin-style' );

            $dont_disturb = esc_url( add_query_arg( 'spare_me', '1', self::triplea_wc_current_admin_url() ) );
            $remind_me    = esc_url( add_query_arg( 'remind_me', '1', self::triplea_wc_current_admin_url() ) );
            $rated        = esc_url( add_query_arg( 'triplea_wc_rated', '1', self::triplea_wc_current_admin_url() ) );
            $reviewurl    = esc_url( 'https://wordpress.org/support/plugin/triplea-cryptocurrency-payment-gateway-for-woocommerce/reviews/?rate=5#new-post' );
            $title        = __( 'Enjoying Crypto Payment Gateway for WooCommerce?', 'wc-triplea-crypto-payment' );
            $subtitle     = __( 'Thank you for choosing Crypto Payment Gateway for WooCommerce. If you have found our plugin useful and makes you smile, please consider giving us a 5-star rating on WordPress.org. It would mean the world to us.', 'wc-triplea-crypto-payment' );
            $cta1         = __( '👍 Yes, You Deserve It!', 'wc-triplea-crypto-payment' );
            $cta2         = __( '🙌 Already Rated!', 'wc-triplea-crypto-payment' );
            $cta3         = __( '🔔 Remind Me Later', 'wc-triplea-crypto-payment' );
            $cta4         = __( '💔 No Thanks', 'wc-triplea-crypto-payment' );

            $notice = sprintf( '<div class="notice triplea_wc-review-notice triplea_wc-review-notice--extended">
                <div class="triplea_wc-review-notice__content">
                    <h3>%s</h3>
                    <p>%s</p>
                    <div class="triplea_wc-review-notice__actions">
                        <a href="%s" class="triplea_wc-review-button triplea_wc-review-button--cta" target="_blank"><span>%s</span></a>
                        <a href="%s" class="triplea_wc-review-button triplea_wc-review-button--cta triplea_wc-review-button--outline"><span>%s</span></a>
                        <a href="%s" class="triplea_wc-review-button triplea_wc-review-button--cta triplea_wc-review-button--outline"><span>%s</span></a>
                        <a href="%s" class="triplea_wc-review-button triplea_wc-review-button--cta triplea_wc-review-button--error triplea_wc-review-button--outline"><span>%s</span></a>
                    </div>
                </div>
            </div>', $title, $subtitle, $reviewurl, $cta1, $rated, $cta2, $remind_me, $cta3, $dont_disturb, $cta4 );
            echo $notice;
        }
    }

    // remove the notice for the user if review already done or if the user does not want to
    public static function triplea_wc_void_spare_me() {
        if ( isset( $_GET['spare_me'] ) && ! empty( $_GET['spare_me'] ) ) {
            $spare_me = $_GET['spare_me'];
            if ( 1 == $spare_me ) {
                update_option( 'triplea_wc_spare_me', "1" );
            }
        }

        if ( isset( $_GET['remind_me'] ) && ! empty( $_GET['remind_me'] ) ) {
            $remind_me = $_GET['remind_me'];
            if ( 1 == $remind_me ) {
                $get_activation_time = strtotime( "now" );
                update_option( 'triplea_wc_remind_me', $get_activation_time );
                update_option( 'triplea_wc_spare_me', "2" );
            }
        }

        if ( isset( $_GET['ha_rated'] ) && ! empty( $_GET['ha_rated'] ) ) {
            $ha_rated = $_GET['ha_rated'];
            if ( 1 == $ha_rated ) {
                update_option( 'triplea_wc_rated', 'yes' );
                update_option( 'triplea_wc_spare_me', "3" );
                wp_redirect( admin_url( 'plugins.php' ) );
            }
        }
    }

    protected static function triplea_wc_current_admin_url() {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $uri = preg_replace( '|^.*/wp-admin/|i', '', $uri );

        if ( ! $uri ) {
            return '';
        }
        return remove_query_arg( [ '_wpnonce', '_wc_notice_nonce', 'wc_db_update', 'wc_db_update_nonce', 'wc-hide-notice' ], admin_url( $uri ) );
    }
}

Reviews::init();
