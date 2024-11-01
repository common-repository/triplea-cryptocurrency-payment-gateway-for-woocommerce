<?php

namespace Triplea\WcTripleaCryptoPayment\API;
use Triplea\WcTripleaCryptoPayment\Logger;

class API {

	/**
	 * @var API
	 */
	private static $instance;
    protected $logger;

	/**
	 * Get class instance.
	 *
	 * I'm not entirely fond of this approach but added it to make the class available to the WC_Gateway.
	 * Since WooCommerce initializes the gateway, we can't pass this in as a constructor argument.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @param string    $order_status               One of paid_expired|paid|paid_too_much|failed_paid_too_little
	 * @param string[]  $notes                      Array of notes to later be added to the order (passsed by reference).
	 * @param \WC_Order $wc_order                   The relevant order whose status will be updated.
	 * @param string    $addr                       The Bitcoin address
	 * @param string    $tx_status                  String enum whose only value we care about is "confirmed".
	 * @param float     $crypto_amount_paid_total   The Bitcoin value paid
	 * @param float     $crypto_amount              The order total expressed in Bitcoin
	 * @param string    $local_currency
	 * @param float     $order_amount               The order total in local currency.
	 * @param string    $exchange_rate
	 */
	public function triplea_update_crypto_payment_order_status( $order_status, array &$notes, $wc_order, $addr, $tx_status, $crypto_amount_paid_total, $crypto_amount, $local_currency, $order_amount, $exchange_rate ) {

		$plugin_options  = 'woocommerce_triplea_payment_gateway_settings';             // Format: $wc_plugin_id + $plugin_own_id + option key
		$plugin_settings = get_option( $plugin_options );
		$debugLog        = ( $plugin_settings['debug_log'] == 'yes' ) ? true : false;
		$this->logger    = Logger::get_instance();

        $this->logger->write_log( 'update_order_status : checking...', $debugLog );

		if ( isset( $plugin_settings['triplea_woocommerce_order_states'] ) && isset( $plugin_settings['triplea_woocommerce_order_states']['paid'] ) ) {
            $this->logger->write_log( 'update_order_status : using custom order status values', $debugLog );
			$order_status_paid      = $plugin_settings['triplea_woocommerce_order_states']['paid'];
			$order_status_confirmed = $plugin_settings['triplea_woocommerce_order_states']['confirmed'];
			$order_status_invalid   = $plugin_settings['triplea_woocommerce_order_states']['invalid'];
		} else {
            $this->logger->write_log( 'update_order_status : fallback to default order status values', $debugLog );
			// default values returned by get_status()
			$order_status_paid      = 'wc-on-hold'; // paid but still unconfirmed
			$order_status_confirmed = 'wc-processing';
			$order_status_invalid   = 'wc-failed';
		}
		$order_status_on_hold = 'wc-on-hold';

		if ( empty( $wc_order ) ) {
            $this->logger->write_log( 'update_order_status : ERROR! Empty woocommerce order. Order was not placed.', $debugLog );
			return;
		}

		if ( 'paid_expired' === $order_status ) {
			// If payment form expires, order does not get placed.
			// However, leaving this here to document possible options.
            $this->logger->write_log( 'update_order_status : payment expired.', $debugLog );
			$notes[] = 'Payment time expired. No payment detected during checkout.';

			$wc_order->update_status( $order_status_invalid );

			// TODO consider whether this should be 'failed' ?
			// User let the payment form expire. Usually this means the user did not make
			// a bitcoin payment in time. However in rare cases (due to user's browser plugins
			// or internet connection), it could happen that the user made a payment
			// which did not get detected before the payment form timer expired.
			// Which is why we place the order anyway and mark it as failed.
			// The user should be able (WooCommerce functionality) to select a different payment
			// option and make another payment.
			// With status 'on hold', hopefully it is possible for the user to choose
			// to try payment again or pick another payment method.
		} else {

			if ( 'confirmed' === $tx_status ) {
                $this->logger->write_log( 'pdate_order_status : Transaction confirmed.', $debugLog );

				if ( 'paid' === $order_status ) {
					// Transactions all confirmed, paid enough. Order payment fully done.
                    $this->logger->write_log( 'update_order_status : Order paid (confirmed payment)', $debugLog );

					// Order has been paid, and the payment transaction(s) are all confirmed.
					$wc_order->update_status( $order_status_confirmed );
					$wc_order->payment_complete( 'BTC address ' . $addr );

					$payment_status_message = 'Correct amount paid.<br>Order completed.';
				} elseif ( 'paid_too_much' === $order_status ) {
					// Transactions all confirmed, paid enough. Order payment fully done.
                    $this->logger->write_log( 'update_order_status : Paid too much for order (payment confirmed).', $debugLog );

					// Order has been paid, and the payment transaction(s) are all confirmed.
					$wc_order->update_status( $order_status_confirmed );
					$wc_order->payment_complete( 'BTC address ' . $addr . '' );

					$payment_status_message = 'User <u>paid too much</u>.<br>Order completed.';
				} elseif ( 'failed_paid_too_little' === $order_status ) {
					// Transactions all confirmed, paid too little. Order payment failed.
                    $this->logger->write_log( 'update_order_status : Paid too little (payment confirmed).', $debugLog );
					$notes[] = '<strong>Crypto amount paid is insufficient!</strong>';

					// Possible edge case to be aware of:
					// If 1 tx gets confirmed, paid too little.
					// Then before expiry another tx is made.
					$wc_order->update_status( $order_status_invalid );

					$payment_status_message = 'User <u>paid too little</u>.<br>Order <strong>failed</strong>.';
				} else {
					// Transactions all confirmed. Unknown order result however.
					// Should never happen. Adding as backup anyway.
                    $this->logger->write_log( 'update_order_status : ERROR! Order status unknown. Please contact us at plugin.support@triple-a.io', $debugLog );

					$wc_order->update_status( $order_status_invalid );

					$payment_status_message = 'Code error, unknown order status value.<br>Order <strong>failed</strong>.';
				}

				$notes[] =
					'Transaction confirmation received.<br>' .
					'<br>' .
					'<strong>Amount due:</strong><br>' .
					'order_currency ' . number_format( $order_amount, 2 ) . '<br>' .
					"<small>1 BTC = $exchange_rate $local_currency</small><br>" .
					'BTC ' . number_format( $crypto_amount, 8 ) . '<br>' .
					'<br>' .
					'<strong>Amount paid:</strong> <br>' .
					'BTC ' . number_format( $crypto_amount_paid_total, 8 ) . '<br>' .
					'<br>' .
					"$payment_status_message";
			} else {
                $this->logger->write_log( 'update_order_status : Unconfirmed order status: ' . $order_status, $debugLog );

				// Status: unconfirmed
				// No matter if at the moment the order status is paid/too little/too much..
				// ..we need to wait for the confirmed transaction(s).

				if ( substr( $addr, 0, 1 ) === 'n' || substr( $addr, 0, 1 ) === 'm' ) {
					$blockchain_epxlorer_url = "https://www.blockchain.com/btc-testnet/address/$addr";
				} else {
					$blockchain_epxlorer_url = "https://www.blockchain.com/btc/address/$addr";
				}

				$notes[] = 'Cryptocurrency payment made, awaiting validation. '
						. "(<a href='$blockchain_epxlorer_url' target='_blank'>transaction details</a>).<br> <br>"
						. '<strong>Amount due:</strong><br>'
						. "$local_currency " . number_format( $order_amount, 2 ) . '<br>'
						. "<small>1 BTC = $exchange_rate $local_currency</small><br>"
						. 'BTC ' . number_format( $crypto_amount, 8 ) . '<br>'
						. '<br>'
						. "<strong>Amount awaiting validation:</strong> <br>BTC ". number_format($crypto_amount_paid_total, 8) ." <br>"
						. ' <br>'
						. 'Payment to crypto address: ' . $addr . '<br>';

				$wc_order->update_status( $order_status_paid );
			}
		}
		// Reminder: $notes array is passed by reference, the calling function will add all notes to the woocommerce order.
	}

	/**
	 * @param $balance_payload_full
	 * @param $client_secret_enc_key
	 * @param $triplea_public_enc_key
	 *
	 * @return array {
	 *             $status
	 *             $status_msg
	 *             $balance_payload_decrypted
	 * }
	 * @throws \SodiumException
	 *
	 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	 */
	public function triplea_cryptocurrency_payment_gateway_for_woocommerce_decrypt_payload( $balance_payload_full, $client_secret_enc_key, $triplea_public_enc_key ) {

		$status                    = 'ok';
		$status_msg                = '';
		$balance_payload_decrypted = false;

		// Format: $wc_plugin_id + $plugin_own_id + option key
		$plugin_options  = 'woocommerce_triplea_payment_gateway_settings';
		$plugin_settings = get_option( $plugin_options );
		$debugLog        = ( $plugin_settings['debug_log'] == 'yes' ) ? true : false;
		$this->logger    = Logger::get_instance();

        $this->logger->write_log( 'decrypt_payload : checking, preparing to decrypt payload', $debugLog );

		if ( empty( $balance_payload_full ) ) {
            $this->logger->write_log( 'decrypt_payload : ERROR! Empty encrypted balance payload.', $debugLog );

			$status                    = 'failed';
			$status_msg                = 'Empty encrypted balance payload.';
			$balance_payload_decrypted = false;
		} else {
			$balance_payload_parts = explode( ':', $balance_payload_full );
			if ( count( $balance_payload_parts ) < 2 ) {
                $this->logger->write_log( 'decrypt_payload : ERROR. Encrypted balance payload wrong format or missing nonce.', $debugLog );

				$status                    = 'failed';
				$status_msg                = 'Encrypted balance payload wrong format or missing nonce.';
				$balance_payload_decrypted = false;
			} else {
				$balance_payload = $balance_payload_parts[0];
				$message_nonce   = $balance_payload_parts[1];

				// triplea_write_log('decrypt_payload : balance_payload ' . print_r($balance_payload, true));
				// triplea_write_log('decrypt_payload : message_nonce ' . print_r($message_nonce, true));
				// triplea_write_log('decrypt_payload : client_secret_enc_key ' . print_r($client_secret_enc_key, true));
				// triplea_write_log('decrypt_payload : triplea_public_enc_key ' . print_r($triplea_public_enc_key, true));

				$triplea_to_client_keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey(
					base64_decode( $client_secret_enc_key ),
					base64_decode( $triplea_public_enc_key )
				);
				$balance_payload_decrypted = sodium_crypto_box_open(
					base64_decode( $balance_payload ),
					base64_decode( $message_nonce ),
					$triplea_to_client_keypair
				);
				if ( false === $balance_payload_decrypted ) {
                    $this->logger->write_log( 'decrypt_payload : ERROR! Problem decrypting balance payload.', $debugLog );

					$status     = 'failed';
					$status_msg = 'Problem decrypting balance payload.';
				} else {
					// triplea_write_log('decrypt_payload : Decrypted: ', $debug_log_enabled);
					// triplea_write_log($balance_payload_decrypted, $debug_log_enabled);
				}
			}
		}
        $this->logger->write_log( '_decrypt_payload() : Result: ' . wp_json_encode( $balance_payload_decrypted, JSON_PRETTY_PRINT ), $debugLog );

		return array(
			'status'     => $status,
			'status_msg' => $status_msg,
			'payload'    => $balance_payload_decrypted,
		);
	}

}
