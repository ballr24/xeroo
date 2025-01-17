<?php
use Automattic\WooCommerce\Utilities\OrderUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'Xeroom_Paypal_Action' ) ) {
	/**
	 * Class Xeroom_Paypal_Action
	 */
	class Xeroom_Paypal_Action {
		/**
		 * @var null
		 */
		protected $_xero_api = null;
		/**
		 * @var null
		 */
		protected $xero_api_key = null;
		/**
		 * @var null
		 */
		protected $xero_api_secret = null;
		/**
		 * @var null|string
		 */
		protected $public_key = null;
		/**
		 * @var null|string
		 */
		protected $private_key = null;

		/**
		 * Xeroom_Paypal_Action constructor.
		 */
		public function __construct() {
			include_once( XEROOM_ROOT_PATH . 'library/xeroom_indexManager.php' );
			global $wpdb;
			$xero_credentials = $wpdb->get_row( $wpdb->prepare( "SELECT xero_api_key, xero_api_secret FROM {$wpdb->prefix}xeroom_credentials WHERE id=%d", 1 ) );

			$this->xero_api_key    = esc_attr( $xero_credentials->xero_api_key );
			$this->xero_api_secret = esc_attr( $xero_credentials->xero_api_secret );

			$this->public_key  = XEROOM_ROOT_PATH . '/library/certs/publickey.cer';
			$this->private_key = XEROOM_ROOT_PATH . '/library/certs/privatekey.pem';

			add_action( 'init', array( $this, 'xeroom_cancel_order_paypal_url' ), 20 );
		}

		/**
		 * Cancel a pending order
		 */
		public function xeroom_cancel_order_paypal_url() {
			if (
				isset( $_GET['cancel_order'] ) &&
				isset( $_GET['order'] ) &&
				isset( $_GET['order_id'] ) &&
				( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'woocommerce-cancel_order' ) )
			) {
				// Check if is a Cart redirect - if comes from PayPal
				$url        = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? "https" : "http" ) . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
				$url_search = parse_url( $url );

				$cart_url       = wc_get_page_permalink( 'cart' );
				$woo_url_search = parse_url( $cart_url );

				if ( $url_search['path'] != $woo_url_search['path'] ) {
					return;
				}

				wc_nocache_headers();
				$xero_time        = date( 'Y-m-d H:i:s' );
				$order_key        = $_GET['order'];
				$order_id         = absint( $_GET['order_id'] );
				$order            = new WC_Order( $order_id );
				$user_can_cancel  = current_user_can( 'cancel_order', $order_id );
				$order_can_cancel = $order->has_status( apply_filters( 'woocommerce_valid_order_statuses_for_cancel', array( 'pending', 'failed' ) ) );

				if ( $order->has_status( 'cancelled' ) ) {
					// Already cancelled - take no action
				} elseif ( $user_can_cancel && $order_can_cancel && $order->get_id() === $order_id && $order->get_order_key() === $order_key ) {
					$oauth2 = get_xero_option( 'xero_oauth_options' );
					xeroom_check_xero_token( $oauth2 );
					$this->_xero_api = new Xero( $this->xero_api_key, $this->xero_api_secret, $this->public_key, $this->private_key, 'json', $oauth2 );

					if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
						$invoice_id = $order->get_meta( 'post_content_filtered' );
					} else {
						$invoice_id = get_post_field( 'post_content', $order_id );
					}

					$update_status = array(
						'Invoice' => array(
							'InvoiceNumber' => $invoice_id,
							'Status'        => 'VOIDED',
						),
					);

					$voided_invoice = $this->_xero_api->Invoices( $update_status );

					$this->xeroom_write_log( $order_id, $voided_invoice, $xero_time, 'orderProduct' );

				}
			}
		}

		/**
		 * Write log entry
		 *
		 * @param $order_id
		 * @param $xeroom_response
		 * @param $xero_time
		 * @param $error_type
		 */
		public function xeroom_write_log( $order_id, $xeroom_response, $xero_time, $error_type ) {
			if ( ! empty( $xeroom_response ) ) {
				if ( isset( $xeroom_response['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
					$errors = $xeroom_response['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
					returnErrorMessageByXero( $order_id, $errors, $xero_time, $error_type );
				} elseif ( isset( $xeroom_response['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
					$errors = $xeroom_response['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
					for ( $e = 0; $e < count( $errors ); $e ++ ) {
						$error_message = $xeroom_response['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
						returnErrorMessageByXero( $order_id, $error_message, $xero_time, $error_type );
					}
				} elseif ( 'OK' != $xeroom_response['Status'] ) {
					returnErrorMessageByXero( $order_id, $xeroom_response, $xero_time, $error_type );
				}
			} else {
				returnErrorMessageByXero( $order_id, 'Xero Server Response is empty.', $xero_time, $error_type );
			}
		}
	}
}

$xeroom_paypal = new Xeroom_Paypal_Action();
