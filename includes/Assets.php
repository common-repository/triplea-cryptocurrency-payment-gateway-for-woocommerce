<?php

namespace Triplea\WcTripleaCryptoPayment;

/**
 * Assets handlers class
 */
class Assets
{
    /**
     * Class constructor
     */
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [ $this, 'register_assets' ]);
        add_action('admin_enqueue_scripts', [ $this, 'register_assets' ]);
    }

    /**
     * All available scripts
     *
     * @return array
     */
    public function get_scripts()
    {
        return [
            'wctriplea-checkout-script' => [
                'src'     => WC_TRIPLEA_CRYPTO_PAYMENT_ASSETS . '/js/checkout.js',
                'version' => filemtime(WC_TRIPLEA_CRYPTO_PAYMENT_PATH . '/assets/js/checkout.js'),
                'deps'    => [ 'jquery' ]
            ],
        ];
    }

    /**
     * All available styles
     *
     * @return array
     */
    public function get_styles()
    {
        return [
            'wctriplea-admin-style' => [
                'src'     => WC_TRIPLEA_CRYPTO_PAYMENT_ASSETS . '/css/admin.css',
                'version' => filemtime(WC_TRIPLEA_CRYPTO_PAYMENT_PATH . '/assets/css/admin.css'),
            ],
            'wctriplea-checkout-style' => [
                'src'     => WC_TRIPLEA_CRYPTO_PAYMENT_ASSETS . '/css/checkout.css',
                'version' => filemtime(WC_TRIPLEA_CRYPTO_PAYMENT_PATH . '/assets/css/checkout.css'),
            ]
        ];
    }

    /**
     * Register scripts and styles
     *
     * @return void
     */
    public function register_assets()
    {
        $scripts = $this->get_scripts();
        $styles  = $this->get_styles();

        foreach ($scripts as $handle => $script) {
            $deps = isset($script['deps']) ? $script['deps'] : false;

            wp_register_script($handle, $script['src'], $deps, $script['version'], true);
            wp_localize_script($handle, 'triplea_object', [
                'ajax_url' => admin_url('admin-ajax.php'),
            ]);
        }

        foreach ($styles as $handle => $style) {
            $deps = isset($style['deps']) ? $style['deps'] : false;

            wp_register_style($handle, $style['src'], $deps, $style['version']);
        }
    }
}
