<?php

namespace Triplea\WcTripleaCryptoPayment;

class Logger {

    /**
	 * @var Logger
	 */
	private static $instance;

    public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

    /**
     * TripleA Logger to log all the debugs
     */
    public function write_log( $log, $log_enabled = false ) {

        if ( $log_enabled ) {
            $logger = wc_get_logger();
            $context = [ 'source' => 'triplea-crypto-payment' ];
            $logger->info( $log, $context );
        }
    }
}