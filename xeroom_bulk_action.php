<?php
use Automattic\WooCommerce\Utilities\OrderUtil;
use GuzzleHttp\Client;
use GuzzleHttp\json_encode;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Xeroom_Batch_Request
 */
if ( ! class_exists( 'Xeroom_Batch_Request' ) ) {
	/**
	 * Class Xeroom_Batch_Request
	 */
	class Xeroom_Batch_Request {
		/**
		 * @var string
		 */
		protected $action = 'xeroom_background_request';
		/**
		 * Start time of current process.
		 *
		 * (default value: 0)
		 *
		 * @var int
		 * @access protected
		 */
		protected $start_time = 0;

		/**
		 * Number of orders for current process.
		 *
		 * (default value: 4)
		 *
		 * @var int
		 * @access protected
		 */
		protected $orders_per_request = 5;

		/**
		 * Cron_hook_identifier
		 *
		 * @var mixed
		 * @access protected
		 */
		protected $cron_hook_identifier;

		/**
		 * Cron_interval_identifier
		 *
		 * @var mixed
		 * @access protected
		 */
		protected $cron_interval_identifier;
		/**
		 * Prefix
		 *
		 * (default value: 'wp')
		 *
		 * @var string
		 * @access protected
		 */
		protected $prefix = 'wp';

		/**
		 * Identifier
		 *
		 * @var mixed
		 * @access protected
		 */
		protected $identifier;
		/**
		 * @var null
		 */
		public $_error_report = null;

		/**
		 * Xeroom_Batch_Request constructor.
		 */
		public function __construct() {
			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				add_filter( 'woocommerce_shop_order_list_table_columns', array( &$this, 'xeroom_new_order_column' ), 10, 1 );
				add_action( 'woocommerce_shop_order_list_table_custom_column', array( &$this, 'xeroom_add_order_xero_status_content' ), 10, 2 );
				add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( &$this, 'register_xeroom_bulk_actions' ), 10, 1 );
				add_action( 'handle_bulk_actions-woocommerce_page_wc-orders', array( &$this, 'xeroo_bulk_action' ), 10, 3 );
			} else {
				add_filter( 'manage_edit-shop_order_columns', array( &$this, 'xeroom_new_order_column' ), 10, 1 );
				add_action( 'manage_shop_order_posts_custom_column', array( &$this, 'xeroom_add_order_xero_status_content' ), 10, 2 );
				add_filter( 'bulk_actions-edit-shop_order', array( &$this, 'register_xeroom_bulk_actions' ), 10, 1 );
				add_action( 'handle_bulk_actions-edit-shop_order', array( &$this, 'xeroo_bulk_action' ), 10, 3 );
			}
			add_action( 'admin_notices', array( &$this, 'xeroo_bulk_admin_notices' ) );
			add_action( 'wp_ajax_xero_sync_bulk_info', array( &$this, 'xeroo_sync_bulk_info' ) );
			add_action( 'wp_ajax_xero_cancel_jobs', array( &$this, 'xero_cancel_jobs' ) );

			// DO THE CRON
			$this->identifier = $this->prefix . '_' . $this->action;

			$this->cron_hook_identifier     = $this->identifier . '_cron';
			$this->cron_interval_identifier = $this->identifier . '_cron_interval';

			add_action( $this->cron_hook_identifier, array( &$this, 'handle_cron_healthcheck' ) );
			add_filter( 'cron_schedules', array( &$this, 'schedule_cron_healthcheck' ) );

//            if( get_option( 'xero_order_number_size' ) ) {
//                $this->orders_per_request = absint( get_option( 'xero_order_number_size' ) );
//            } else {
//                $this->orders_per_request = 4;
//            }

			add_action( 'wp_ajax_nopriv_xeroom_bulk_callback_action', array( &$this, 'handle_cron_healthcheck' ) );
			add_action( 'wp_ajax_xeroom_bulk_callback_action', array( &$this, 'handle_cron_healthcheck' ) );
		}

		/**
		 * Cancel all the bulk jobs
		 */
		public function xero_cancel_jobs() {
			check_ajax_referer( 'xero-ajax', 'nonce' );

			$this->complete();
			delete_transient( 'xero_bulkit' );

			global $wpdb;

			$table  = $wpdb->options;
			$column = 'option_name';

			if ( is_multisite() ) {
				$table  = $wpdb->sitemeta;
				$column = 'meta_key';
			}

			$key = $this->identifier . '_batch_%';

			$wpdb->get_results(
				$wpdb->prepare(
					"
		                DELETE FROM {$table}
		                WHERE {$column} LIKE %s
		            ",
					$key
				)
			);
		}

		/**
		 * Fetch remaining bulk invoices
		 */
		public function xeroo_sync_bulk_info() {
			check_ajax_referer( 'xero-ajax', 'nonce' );
			$bulk_status = get_transient( 'xero_bulkit' );
			if ( ! $bulk_status ) {
				wp_die();
			}

			if ( $this->is_queue_empty() ) {
				echo __( 'All the orders have been sent to Xero.', 'xeroom' );
				delete_transient( 'xero_bulkit' );
			} else {
				printf(
					_n(
						'%s order/invoice remaining to be sent to Xero.',
						'%s orders/invoices remaining to be sent to Xero.',
						$this->xero_fetch_items_in_queue(),
						'xeroom'
					) . ' <img src="' . plugin_dir_url( __FILE__ ) . 'images/ajax-loader.gif"> &raquo; <a href="#" class="button" id="xeroo-cancel-jobs">' . __( 'Cancel All Bulk Jobs', 'xeroom' ) . '</a>',
					$this->xero_fetch_items_in_queue()
				);
			}

			wp_die();
		}

		/**
		 * Add new column on Order Listing
		 *
		 * @param $columns
		 *
		 * @return mixed
		 */
		public function xeroom_new_order_column( $columns ) {
			$new_columns = array();

			foreach ( $columns as $column_name => $column_info ) {
				$new_columns[ $column_name ] = $column_info;

				if ( 'order_total' === $column_name ) {
					$new_columns['xero_status'] = __( 'Xero Status', 'xeroom' );
				}
			}

			return $new_columns;
		}

		/**
		 * Display Order Status on Order Listing
		 *
		 * @param $column
		 */
		public function xeroom_add_order_xero_status_content( $column, $order ) {
			if ( 'xero_status' === $column ) {
				$have_option_date = '2018-09-20 13:32:35';
				if ( $have_option_date ) {
					$activate_date = strtotime( $have_option_date );
					if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
						$post_date = strtotime( $order->get_date_created() );
					} else {
                        global $post;
						$post_date = strtotime( $post->post_date );
					}
                    
					if ( $activate_date <= $post_date ) {
                        if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
                            $invoice_sent    = $order->get_meta( 'xeroom_order_sent' );
                            $payment_sent    = $order->get_meta( 'xeroom_payment_sent' );
                            $credit_note     = $order->get_meta( 'xeroom_cred_note_generated' );
                            $email_invoice   = $order->get_meta( 'xeroom_invoice_emailed' );
                            $payment_webhook = $order->get_meta( 'xero_payment_webhook' );
                        } else {
                            $invoice_sent = get_post_meta( $post->ID, 'xeroom_order_sent', true );
                            $payment_sent = get_post_meta( $post->ID, 'xeroom_payment_sent', true );
                            $credit_note  = get_post_meta( $post->ID, 'xeroom_cred_note_generated', true );
                            $email_invoice  = get_post_meta( $post->ID, 'xeroom_invoice_emailed', true );
                            $payment_webhook  = get_post_meta( $post->ID, 'xero_payment_webhook', true );
                        }
						
                        
                        $email_invoice_class = ' style="color: #761919"';
						if ( $email_invoice ) {
							$email_invoice_class = ' style="border:2px solid #01ace7; color: #761919"';
						}

						$invoice_status = sprintf( '<mark class="order-status status-failed"%s><span>%s</span></mark>', $email_invoice_class, __( 'Not sent', 'xeroom' ) );

						if ( $invoice_sent ) {
                            $email_invoice_class = str_replace( '#761919', '#94660c', $email_invoice_class);
							$invoice_status = sprintf( '<mark class="order-status status-on-hold"%s><span>%s</span></mark>', $email_invoice_class, __( 'Sent unpaid', 'xeroom' ) );
						}

						if ( $payment_sent ) {
                            $email_invoice_class = str_replace( '#761919', '#5b841b', $email_invoice_class);
							$invoice_status = sprintf( '<mark class="order-status status-processing"%s><span>%s</span></mark>', $email_invoice_class, __( 'Sent and paid', 'xeroom' ) );
						}

						if ( $credit_note ) {
                            $email_invoice_class = str_replace( '#761919', '#777', $email_invoice_class);
							$invoice_status = sprintf( '<mark class="order-status status-cancelled"%s><span>%s</span></mark>', $email_invoice_class, __( 'Paid & Credit Note', 'xeroom' ) );
						}
                        
						if ( $credit_note && ! $payment_sent ) {
                            $email_invoice_class = ' style="color: #000; background-color:#CCC"';
							$invoice_status = sprintf( '<mark class="order-status status-cancelled"%s><span>%s</span></mark>', $email_invoice_class, __( 'Credit Note', 'xeroom' ) );
						}
                        
                        if ( $payment_webhook ) {
                            $custom_css = ' style="background-color: #0078c8; color:white"';
                            if( $email_invoice ) {
                                $custom_css = ' style="border:2px solid #01ace7; background-color: #0078c8; color:white"';
                            }
							$invoice_status = sprintf( '<mark class="order-status status-xero-paid"%s><span>%s</span></mark>', $custom_css, __( 'Paid in Xero', 'xeroom' ) );
						}

						echo $invoice_status;
					}
				}
			}
		}

		/**
		 * Add Xeroom Actions to Bulk DropDown
		 *
		 * @param $bulk_actions
		 *
		 * @return mixed
		 */
		public function register_xeroom_bulk_actions( $bulk_actions ) {
			$bulk_actions['xero_with_payment']              = __( 'Send orders to Xero with payment', 'xeroom' );
			$bulk_actions['xero_without_payment']           = __( 'Send orders to Xero without payment', 'xeroom' );
			$bulk_actions['xero_resend_without_payment']    = __( 'Resend orders to Xero without payment', 'xeroom' );
			$bulk_actions['xero_send_invoice_to_customers'] = __( 'Send Invoices to Customers from Xero', 'xeroom' );

			return $bulk_actions;
		}

		/**
		 * Dispatch to cron job the bulk action
		 *
		 * @param $redirect_to
		 * @param $doaction
		 * @param $post_ids
		 *
		 * @return string
		 */
		public function xeroo_bulk_action( $redirect_to, $doaction, $post_ids ) {
			if ( 'xero_with_payment' !== $doaction && 'xero_without_payment' !== $doaction && 'xero_resend_without_payment' !== $doaction && 'xero_send_invoice_to_customers' !== $doaction ) {
				return $redirect_to;
			}

			$orders_count = count( $post_ids );

			if ( 1 == $orders_count ) {
				// If there is only one selected, send it instantly.
				$this->xeroo_send_data_to_xero_now( $doaction, $post_ids );

			} else {
				// If there are more than 1 Order selected, send by WP Background Processing.
				$this->xeroo_send_data_to_xero_by_cron( $doaction, $post_ids );
				set_transient( 'xero_bulkit', $orders_count, 12 * HOUR_IN_SECONDS );
			}

//			$redirect_to = esc_url( remove_query_arg( 'xero_bulk' ) );
//
//			// build the redirect url
//			$redirect_to = add_query_arg(
//				array(
//					'xero_bulk' => $orders_count,
//					count( $post_ids ),
//					$redirect_to,
//				)
//			);
            
			return $redirect_to;
		}

		/**
		 * Send data to Xero Now
		 *
		 * @param $doaction
		 * @param $post_ids
		 */
		function xeroo_send_data_to_xero_now( $doaction, $post_ids ) {
			switch ( $doaction ) {
				case 'xero_with_payment':
					foreach ( $post_ids as $post_id ) {
						xero_send_invoice_data( absint( $post_id ), 'invoice' );
						xero_send_invoice_data( absint( $post_id ), 'payment' );
					}
					break;
				case 'xero_without_payment':
					foreach ( $post_ids as $post_id ) {
						xero_send_invoice_data( absint( $post_id ), 'invoice' );
					}
					break;
				case 'xero_resend_without_payment':
					foreach ( $post_ids as $post_id ) {
						xero_send_invoice_data( absint( $post_id ), 'resend-invoice' );
					}
					break;
				case 'xero_send_invoice_to_customers':
					foreach ( $post_ids as $post_id ) {
						$this->xero_send_invoice_to_client( absint( $post_id ) );
					}
					break;
			}
		}

		/**
		 * Send data to Xero by WP Cron
		 *
		 * @param $doaction
		 * @param $post_ids
		 */
		function xeroo_send_data_to_xero_by_cron( $doaction, $post_ids ) {
			$xero_time = date( 'Y-m-d H:i:s' );
			switch ( $doaction ) {
				case 'xero_with_payment':
					foreach ( $post_ids as $post_id ) {
						try {
							// Queue Invoice
							$result_invoice = $this->data(
								array(
									'invoice' => $post_id,
								)
							)->save()->dispatch()->xeroom_generate_error();

							// Queue Payment
							$result_payment = $this->data(
								array(
									'payment' => $post_id,
								)
							)->save()->dispatch()->xeroom_generate_error();

							if ( ! empty( $result_invoice ) || ! empty( $result_payment ) ) {
								$message = sprintf( __( 'Invoice or Payment not Added for Order ID %d in Queue for Xero' ), $post_id );
								returnErrorMessageByXero( $post_id, $message, $xero_time, 'productOrder' );
							}
						} catch ( Exception $e ) {
							$message = sprintf( __( 'Invoice or Payment not Added for Order ID %d in Queue for Xero, try again later.' ), $post_id );
							returnErrorMessageByXero( $post_id, $message, $xero_time, 'productOrder' );
						}
					}

					break;
				case 'xero_without_payment':
					foreach ( $post_ids as $post_id ) {
						try {
							// Queue Invoice
							$result = $this->data(
								array(
									'invoice' => $post_id,
								)
							)->save()->dispatch()->xeroom_generate_error();

							if ( ! empty( $result ) ) {
								$message = sprintf( __( 'Invoice not Added for Order ID %d in Queue for Xero' ), $post_id );
								returnErrorMessageByXero( $post_id, $message, $xero_time, 'productOrder' );
							}
						} catch ( Exception $e ) {
							$message = sprintf( __( 'Invoice not Added for Order ID %d in Queue for Xero, try again later.' ), $post_id );
							returnErrorMessageByXero( $post_id, $message, $xero_time, 'productOrder' );
						}
					}

					break;
				case 'xero_resend_without_payment':
					foreach ( $post_ids as $post_id ) {
						try {
							// Queue Re-Sending Invoice
							$result = $this->data(
								array(
									're_invoice' => $post_id,
								)
							)->save()->dispatch()->xeroom_generate_error();

							if ( ! empty( $result ) ) {
								$message = sprintf( __( 'Invoice not Added for Order ID %d in Queue for Xero' ), $post_id );
								returnErrorMessageByXero( $post_id, $message, $xero_time, 'productOrder' );
							}
						} catch ( Exception $e ) {
							$message = sprintf( __( 'Invoice not Added for Order ID %d in Queue for Xero, try again later.' ), $post_id );
							returnErrorMessageByXero( $post_id, $message, $xero_time, 'productOrder' );
						}
					}

					break;
				case 'xero_send_invoice_to_customers' :
					foreach ( $post_ids as $post_id ) {
						try {
							// Queue Sending Xero Client Invoice
							$result = $this->data(
								array(
									'client_invoice' => $post_id,
								)
							)->save()->dispatch()->xeroom_generate_error();

							if ( ! empty( $result ) ) {
								$message = sprintf( __( 'Invoice not Added for Order ID %d in Queue for Xero' ), $post_id );
								returnErrorMessageByXero( $post_id, $message, $xero_time, 'productOrder' );
							}
						} catch ( Exception $e ) {
							$message = sprintf( __( 'Invoice not Added for Order ID %d in Queue for Xero, try again later.' ), $post_id );
							returnErrorMessageByXero( $post_id, $message, $xero_time, 'productOrder' );
						}
					}
					break;
			}
		}

		/**
		 * Display Message
		 */
		public function xeroo_bulk_admin_notices() {
            $screen = get_current_screen();
            if ( 'woocommerce_page_wc-orders' != $screen->id ) {
				return;
			}
			$bulk_status = get_transient( 'xero_bulkit' );
			if ( $bulk_status ) {
				if ( $this->is_queue_empty() ) {
					printf( '<div id="message" class="updated notice notice-success is-dismissible xero-bulk-info"><p>%s</p></div>', __( 'All the orders have been sent to Xero.', 'xeroom' ) );
					delete_transient( 'xero_bulkit' );
				} else {
					printf(
						'<div id="message" class="updated notice notice-success is-dismissible xero-bulk-info"><p>' .
						_n(
							'%s order/invoice scheduled scheduled to be sent to Xero.',
							'%s orders/invoices are scheduled to be sent to Xero.',
							$this->xero_fetch_items_in_queue(),
							'xeroom'
						) . '  <img src="' . plugin_dir_url( __FILE__ ) . 'images/ajax-loader.gif"> &raquo; <a href="#" class="button" id="xeroo-cancel-jobs">' . __( 'Cancel All Bulk Jobs', 'xeroom' ) . '</a></p></div>',
						$this->xero_fetch_items_in_queue()
					);
				}
			}
		}

		/**
		 * Memory exceeded
		 *
		 * Ensures the batch process never exceeds 90%
		 * of the maximum WordPress memory.
		 *
		 * @return bool
		 */
		protected function memory_exceeded() {
			$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
			$current_memory = memory_get_usage( true );
			$return         = false;

			if ( $current_memory >= $memory_limit ) {
				$return = true;
			}

			return apply_filters( $this->identifier . '_memory_exceeded', $return );
		}

		/**
		 * Get memory limit
		 *
		 * @return int
		 */
		protected function get_memory_limit() {
			if ( function_exists( 'ini_get' ) ) {
				$memory_limit = ini_get( 'memory_limit' );
			} else {
				// Sensible default.
				$memory_limit = '128M';
			}

			if ( ! $memory_limit || - 1 === $memory_limit ) {
				// Unlimited, set to 32GB.
				$memory_limit = '32000M';
			}

			return intval( $memory_limit ) * 1024 * 1024;
		}

		/**
		 * Time exceeded.
		 *
		 * Ensures the batch never exceeds a sensible time limit.
		 * A timeout limit of 30s is common on shared hosting.
		 *
		 * @return bool
		 */
		protected function time_exceeded() {
			$finish = $this->start_time + apply_filters( $this->identifier . '_default_time_limit', 20 ); // 20 seconds
			$return = false;

			if ( time() >= $finish ) {
				$return = true;
			}

			return apply_filters( $this->identifier . '_time_exceeded', $return );
		}

		/**
		 * Handle cron healthcheck
		 *
		 * Restart the background process if not already running
		 * and data exists in the queue.
		 */
		public function handle_cron_healthcheck() {
			delete_site_transient( $this->identifier . '_process_lock' );

			if ( $this->is_process_running() ) {
				// Background process already running.
				exit;
			}

			if ( $this->is_queue_empty() ) {
				// No data to process.
				$this->clear_scheduled_event();
				exit;
			}

			$this->handle();

			exit;
		}

		/**
		 * Clear scheduled event
		 */
		protected function clear_scheduled_event() {
			$timestamp = wp_next_scheduled( $this->cron_hook_identifier );

			if ( $timestamp ) {
//				wp_unschedule_event( $timestamp, $this->cron_hook_identifier );
			}
		}

		/**
		 * Complete.
		 *
		 * Override if applicable, but ensure that the below actions are
		 * performed, or, call parent::complete().
		 */
		protected function complete() {
			// Un-schedule the cron health-check.
			$this->clear_scheduled_event();
		}

		/**
		 * Generate key
		 *
		 * Generates a unique key based on microtime. Queue items are
		 * given a unique key so that they can be merged upon save.
		 *
		 * @param int $length Length.
		 *
		 * @return string
		 */
		protected function generate_key( $length = 64 ) {
			$unique  = md5( microtime() . rand() );
			$prepend = $this->identifier . '_batch_';

			return substr( $prepend . $unique, 0, $length );
		}

		/**
		 * Is queue empty
		 *
		 * @return bool
		 */
		protected function is_queue_empty() {
			global $wpdb;

			$table  = $wpdb->options;
			$column = 'option_name';

			if ( is_multisite() ) {
				$table  = $wpdb->sitemeta;
				$column = 'meta_key';
			}

			$key = $this->identifier . '_batch_%';

			$count = $wpdb->get_var(
				$wpdb->prepare(
					"
                    SELECT COUNT(*)
                    FROM {$table}
                    WHERE {$column} LIKE %s
                ",
					$key
				)
			);

			return ( $count > 0 ) ? false : true;
		}

		/**
		 * Is queue empty
		 *
		 * @return null|string
		 */
		private function xero_fetch_items_in_queue() {
			global $wpdb;

			$table        = $wpdb->options;
			$column       = 'option_name';
			$column_value = 'option_value';

			if ( is_multisite() ) {
				$table        = $wpdb->sitemeta;
				$column       = 'meta_key';
				$column_value = 'meta_value';
			}

			$key = $this->identifier . '_batch_%';

			$count = $wpdb->get_var(
				$wpdb->prepare(
					"
                    SELECT COUNT(*)
                    FROM {$table}
                    WHERE {$column} LIKE %s
                    AND {$column_value} LIKE %s
                ",
					$key,
					'%invoice%'
				)
			);

			return $count;
		}

		/**
		 * Is process running
		 *
		 * Check whether the current process is already running
		 * in a background process.
		 */
		protected function is_process_running() {
			if ( get_site_transient( $this->identifier . '_process_lock' ) ) {
				// Process already running.
				return true;
			}

			return false;
		}

		/**
		 * Lock process
		 *
		 * Lock the process so that multiple instances can't run simultaneously.
		 * Override if applicable, but the duration should be greater than that
		 * defined in the time_exceeded() method.
		 */
		protected function lock_process() {
			$this->start_time = time(); // Set start time of current process.

			$lock_duration = ( property_exists( $this, 'queue_lock_time' ) ) ? $this->queue_lock_time : 60; // 1 minute
			$lock_duration = apply_filters( $this->identifier . '_queue_lock_time', $lock_duration );

			set_site_transient( $this->identifier . '_process_lock', microtime(), $lock_duration );
		}

		/**
		 * Unlock process
		 *
		 * Unlock the process so that other instances can spawn.
		 *
		 * @return $this
		 */
		protected function unlock_process() {
			delete_site_transient( $this->identifier . '_process_lock' );

			return $this;
		}

		/**
		 * Dispatch cron job
		 *
		 * @return $this
		 */
		public function dispatch() {
			// Schedule the cron health-check.
			$this->xeroom_register_schedule_event();

			return $this;
		}

		/**
		 * Set data used during the request
		 *
		 * @param array $data Data.
		 *
		 * @return $this
		 */
		public function data( $data ) {
			$this->_error_report = null;

			$this->data = $data;

			return $this;
		}

		/**
		 * Schedule cron healthcheck
		 *
		 * @access public
		 *
		 * @param mixed $schedules Schedules.
		 *
		 * @return mixed
		 */
		public function schedule_cron_healthcheck( $schedules ) {
			$interval = apply_filters( $this->identifier . '_cron_interval', 5 );

			if ( property_exists( $this, 'cron_interval' ) ) {
				$interval = apply_filters( $this->identifier . '_cron_interval', $this->cron_interval_identifier );
			}

			// Adds every 1 minutes to the existing schedules.
			$schedules[ $this->identifier . '_cron_interval' ] = array(
				'interval' => MINUTE_IN_SECONDS * $interval,
				'display'  => sprintf( __( 'Every %d Minutes', 'wpr-dealers' ), $interval ),
			);

			return $schedules;
		}

		/**
		 * Register Cron if not exists
		 */
		function xeroom_register_schedule_event() {
			if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
				$time = time() + 20;
				wp_schedule_event( $time, $this->cron_interval_identifier, $this->cron_hook_identifier );
			}
		}

		/**
		 * @return null
		 */
		function xeroom_generate_error() {
			return $this->_error_report;
		}

		/**
		 * Save queue
		 *
		 * @return $this
		 */
		public function save() {
			$key = $this->generate_key();

			if ( ! empty( $this->data ) ) {
				$insert = update_site_option( $key, $this->data );

				if ( ! $insert ) {
					$this->_error_report = 'Error';
				}
			} else {
				$this->_error_report = 'Error';
			}

			return $this;
		}

		/**
		 * Update queue
		 *
		 * @param string $key Key.
		 * @param array $data Data.
		 *
		 * @return $this
		 */
		public function update( $key, $data ) {
			if ( ! empty( $data ) ) {
				update_site_option( $key, $data );
			}

			return $this;
		}

		/**
		 * Delete queue
		 *
		 * @param string $key Key.
		 *
		 * @return $this
		 */
		public function delete( $key ) {
			delete_site_option( $key );

			return $this;
		}

		/**
		 * Get batch
		 *
		 * @return stdClass Return the first batch from the queue
		 */
		protected function get_batch() {
			global $wpdb;

			$table        = $wpdb->options;
			$column       = 'option_name';
			$key_column   = 'option_id';
			$value_column = 'option_value';

			if ( is_multisite() ) {
				$table        = $wpdb->sitemeta;
				$column       = 'meta_key';
				$key_column   = 'meta_id';
				$value_column = 'meta_value';
			}

			$key = $this->identifier . '_batch_%';

			$query = $wpdb->get_results(
				$wpdb->prepare(
					"
		                SELECT *
		                FROM {$table}
		                WHERE {$column} LIKE %s
		                ORDER BY {$key_column} ASC
		                LIMIT {$this->orders_per_request}
		            ",
					$key
				)
			);

			$batch = new stdClass();

			if ( $query ) {
				foreach ( $query as $entry ) {
					$batch->key[]  = $entry->$column;
					$batch->data[] = maybe_unserialize( $entry->$value_column );
				}
			}

			return $batch;
		}

		/**
		 * Handle
		 *
		 * Pass each queue item to the task handler, while remaining
		 * within server memory and time limit constraints.
		 */
		protected function handle() {
			$this->lock_process();
			// Allow only one entry at a time
			$x = 1;

			do {
				$batch = $this->get_batch();

				// Process tasks
				$i = 0;
				if ( isset( $batch->data ) ) {
					foreach ( $batch->data as $batch_data ) {
						foreach ( $batch_data as $key => $value ) {
							if ( 'invoice' == $key ) {
								$task = $this->send_invoice_to_xero( $value );
							} elseif ( 'payment' == $key ) {
								$task = $this->send_payment_to_xero( $value );
							} elseif ( 're_invoice' == $key ) {
								$task = $this->re_send_payment_to_xero( $value );
							} elseif ( 'client_invoice' == $key ) {
								$task = $this->xero_send_invoice_to_client( $value );
							} else {
								$task = $this->task( $value );
							}

							if ( false !== $task ) {
								$batch->data[ $i ] = $value;
							} else {
								unset( $batch->data[ $i ] );
							}

							if ( $this->time_exceeded() || $this->memory_exceeded() ) {
								// Batch limits reached.
								break;
							}
							sleep( 1 );
						}

						// Update or delete current batch.
						if ( ! empty( $batch->data[ $i ] ) ) {
							$this->update( $batch->key[ $i ], $batch->data[ $i ] );
						} else {
							$this->delete( $batch->key[ $i ] );
						}
						$i ++;
					}
				}

				$x ++;
			} while ( $x <= 2 && ! $this->time_exceeded() && ! $this->memory_exceeded() && ! $this->is_queue_empty() );

			$this->unlock_process();

			// Start next batch or complete process.
			if ( ! $this->is_queue_empty() ) {
				$result = $this->dispatch()->xeroom_generate_error();
				if ( ! empty( $result ) ) {

				}
			} else {
				$this->complete();
			}

			wp_die();
		}

		/**
		 * Send Invoice to Xero
		 *
		 * @param $item
		 *
		 * @return bool
		 */
		protected function send_invoice_to_xero( $item ) {
			try {
				$response = xero_send_invoice_data( absint( $item ), 'invoice' );

				if ( strpos( $response, 'Xero Server Response is empty' ) ) {
					$order     = new WC_Order( absint( $item ) );
					$old_value = $order->get_meta( 'xeroom_api_try_no' );
					if ( $old_value ) {
						$order->update_meta_data( 'xeroom_api_try_no', $old_value + 1 );
                        $order->save();
					}

					if( $old_value > 5 ) {
						// Remove from queue, the response will be saved in Logs.
						return false;
					}
                    
					return true;
				} else {
					// Remove from queue, the response will be saved in Logs.
					return false;
				}
			} catch ( Exception $e ) {
				error_log( 'Cron task encountered an exception: ' );
				error_log( $e );

				// Remove from queue
				return false;
			}
		}

		/**
		 * Re-Send Invoice to Xero
		 *
		 * @param $item
		 *
		 * @return bool
		 */
		protected function re_send_payment_to_xero( $item ) {
			try {
				xero_send_invoice_data( absint( $item ), 'resend-invoice' );

				// Remove from queue, the response will be saved in Logs.
				return false;
			} catch ( Exception $e ) {
				error_log( 'Cron task encountered an exception: ' );
				error_log( $e );

				// Remove from queue
				return false;
			}
		}

		/**
		 * Send Payment to Xero
		 *
		 * @param $item
		 *
		 * @return bool
		 */
		protected function send_payment_to_xero( $item ) {
			try {
				xero_send_invoice_data( absint( $item ), 'payment' );

				// Remove from queue, the response will be saved in Logs.
				return false;
			} catch ( Exception $e ) {
				error_log( 'Cron task encountered an exception: ' );
				error_log( $e );

				// Remove from queue
				return false;
			}
		}

		/**
		 * Send client invoice from Xero
		 *
		 * @param $item
		 *
		 * @return false
		 */
		public function xero_send_invoice_to_client( $item ) {
			try {
				$oauth2 = get_xero_option( 'xero_oauth_options' );
				$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $oauth2['token'] );

				$config->setHost( "https://api.xero.com/api.xro/2.0" );

				$apiInstance = new XeroAPI\XeroPHP\Api\AccountingApi(
					new GuzzleHttp\Client(),
					$config
				);

				$xero_tenant_id = $oauth2['tenant_id'];

				$order     = new WC_Order( $item );
				if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
					$invoiceID = $order->get_meta( 'post_content_filtered' );
				} else {
					$post           = get_post( $item );
					$invoiceID      = sanitize_text_field( $post->post_content_filtered );
				}

				$requestEmpty = new XeroAPI\XeroPHP\Models\Accounting\RequestEmpty;

				try {
					$return = $apiInstance->emailInvoice( $xero_tenant_id, $invoiceID, $requestEmpty );
					$order->update_meta_data( 'xeroom_invoice_emailed', 1 );
                    $order->save();
				} catch ( Exception $e ) {
					echo 'Exception when calling AccountingApi->emailInvoice: ', $e->getMessage(), PHP_EOL;
					$order->update_meta_data( 'xeroom_invoice_emailed', 0 );
                    $order->save();
				}

				// Remove from queue, the response will be saved in Logs.
				return false;
			} catch ( Exception $e ) {
				error_log( 'Cron task encountered an exception: ' );
				error_log( $e );

				// Remove from queue
				return false;
			}
		}

		/**
		 * @param $item
		 *
		 * @return bool
		 */
		protected function task( $item ) {
			// Actions to perform

			return false;
		}
	}
}
