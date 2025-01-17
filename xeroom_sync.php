<?php

use PhpOffice\PhpSpreadsheet\Helper\Sample;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_action( 'wp_ajax_xero_black_list', 'xero_black_list' );
/**
 * Sync Stock Settings
 */
function xero_black_list() {
	check_ajax_referer( 'xero-ajax', 'nonce' );

	$black_list = esc_attr( $_POST['black_list'] );

	if ( empty( trim( $black_list ) ) ) {
		update_xero_option( 'xeroom_blacklisted_sku', '' );
	} else {
		update_xero_option( 'xeroom_blacklisted_sku', explode( ',', $black_list ) );
	}

	wp_die( __( 'Black list updated!', 'xeroom' ) );
}

add_action( 'wp_ajax_xero_sync_stock', 'xero_sync_stock_data' );
/**
 * Sync Stock Settings
 */
function xero_sync_stock_data() {
	check_ajax_referer( 'xero-ajax', 'nonce' );

	delete_option( 'xeroom_total_batches' );
	delete_option( 'xeroom_current_batch' );
	delete_option( 'xeroom_executing_stock_synch' );
	delete_option( 'xeroom_synch_type' );
	delete_option( 'xeroom_synch_recurrence' );

	$sync_master     = esc_attr( $_POST['master'] );
	$sync_schedule   = esc_attr( $_POST['schedule'] );
	$batch_sync_size = absint( $_POST['size'] );
	$debug_mode      = absint( $_POST['synch_debug_mode'] );

	if ( empty( $batch_sync_size ) ) {
		wp_die( __( 'Please fill Batch Sync Size!', 'xeroom' ) );
	}

	$sync_data = array();
	if ( ! empty( $sync_master ) ) {
		$sync_data['sync_master'] = $sync_master;
	}

	if ( $debug_mode ) {
		$sync_data['debug_mode'] = 1;
	} else {
		$sync_data['debug_mode'] = 0;
	}

	$message = '';
	if ( ! empty( $sync_schedule ) ) {
		update_option( 'xeroom_executing_stock_synch', 1 );
		xeroom_reset_products_sync();

		$sync_data['sync_schedule'] = $sync_schedule;

		if ( ! empty( $batch_sync_size ) ) {
			$sync_data['batch_sync_size'] = $batch_sync_size;
		}

		update_xero_option( 'sync_stock_data', $sync_data );
		if ( false === get_option( 'xeroom_total_batches' ) ) {
			$woo_products  = xeroom_get_total_products_count();
			$total_batches = ceil( $woo_products / $sync_data['batch_sync_size'] );

			update_option( 'xeroom_total_batches', $total_batches );
		}

		update_option( 'xeroom_current_batch', 1 );

		if ( 'n' != $sync_schedule ) {
			// Remove first, if exists
			wp_clear_scheduled_hook( 'xeroom_main_stock_sync_schedule' );
			wp_clear_scheduled_hook( 'xeroom_sync_schedule' );

			// Create new cron job
			switch ( $sync_schedule ) {
				case 'm':
					$recurrence = 'five_minutes';
					$synch_type = '5 min';
					break;
				case 'h':
					$recurrence = 'hourly';
					$synch_type = 'hourly';
					break;
				case 'd':
					$recurrence = 'daily';
					$synch_type = 'daily';
					break;
			}

			// Create new cron job
			$message = sprintf(
				esc_html__( 'Syncing %d of %d batches at 1 batch/min', 'xeroom' ),
				1,
				$total_batches
			);

			update_option( 'xeroom_synch_type', $synch_type );
			update_option( 'xeroom_synch_recurrence', $recurrence );

			wp_schedule_event( time(), 'per_minute', 'xeroom_sync_schedule' );
		} else if ( $debug_mode ) {
			// Remove first, if exists
			wp_clear_scheduled_hook( 'xeroom_main_stock_sync_schedule' );
			wp_clear_scheduled_hook( 'xeroom_sync_schedule' );
			// Create new cron job
			$message = __( 'Sync job started with Debug Mode!', 'xeroom' );

			wp_schedule_event( time(), 'per_minute', 'xeroom_sync_schedule' );
		} else {
			wp_clear_scheduled_hook( 'xeroom_main_stock_sync_schedule' );
			wp_clear_scheduled_hook( 'xeroom_sync_schedule' );

			wp_schedule_event( time(), 'per_minute', 'xeroom_sync_schedule' );

			$message = sprintf(
				esc_html__( 'Syncing %d of %d batches at 1 batch/min', 'xeroom' ),
				1,
				$total_batches
			);
			update_option( 'xeroom_synch_type', 'every min' );
		}
	}

	wp_die( $message );
}

/**
 * Reset products sync to not sent
 */
function xeroom_reset_products_sync() {
	global $wpdb;

	$wpdb->query(
		$wpdb->prepare( "
			UPDATE $wpdb->postmeta
			SET meta_value = %d
			WHERE meta_key = %s
			",
			0, 'wpr_stock_updated'
		)
	);

	update_option( 'xeroom_current_batch', 1 );
}

add_action( 'xeroom_sync_schedule', 'xeroom_sync_products_stock' );
/**
 * Sync Products Stock
 */
function xeroom_sync_products_stock() {
	$sync_data = get_xero_option( 'sync_stock_data' );

	if ( ! empty( $sync_data ) ) {
		$sync_master = esc_attr( $sync_data['sync_master'] );
		$batch_size  = absint( $sync_data['batch_sync_size'] );
		$errorType   = "orderProduct";
		$xeroTime    = date( 'Y-m-d H:i:s' );

		if ( isset( $sync_data['debug_mode'] ) && $sync_data['debug_mode'] > 0 ) {
			$batch_size = 10;
		}

		global $wpdb;
		$xeroCredentialTable    = $wpdb->prefix . "xeroom_credentials";
		$sql                    = "SELECT xero_api_key, xero_api_secret, asset_code, product_master, sold_code, sales_account FROM " . $xeroCredentialTable . " WHERE id=1";
		$xeroCredentialsFromTbl = $wpdb->get_results( $sql );
		$xeroApiKey             = sanitize_text_field( $xeroCredentialsFromTbl[0]->xero_api_key );
		$xeroApiSecret          = sanitize_text_field( $xeroCredentialsFromTbl[0]->xero_api_secret );
		$asset_code             = sanitize_text_field( $xeroCredentialsFromTbl[0]->asset_code );
		$SoldCode               = sanitize_text_field( $xeroCredentialsFromTbl[0]->sold_code );
		$product_master         = sanitize_text_field( $xeroCredentialsFromTbl[0]->product_master );
		$salesAccount           = sanitize_text_field( $xeroCredentialsFromTbl[0]->sales_account );

		$oauth2 = get_xero_option( 'xero_oauth_options' );
		xeroom_check_xero_token( $oauth2 );

		include_once( XEROOM_ROOT_PATH . "library/xeroom_indexManager.php" );
		if ( ! defined( 'BASE_PATH' ) ) {
			define( 'BASE_PATH', dirname( __FILE__ ) );
		}

		if ( ! defined( 'XERO_KEY' ) ) {
			define( 'XERO_KEY', $xeroApiKey );
		}

		if ( ! defined( 'XERO_SECRET' ) ) {
			define( 'XERO_SECRET', $xeroApiSecret );
		}

		if ( ! defined( 'PUBLIC_KEY' ) ) {
			define( 'PUBLIC_KEY', '' );
		}

		if ( ! defined( 'PRIVATE_KEY' ) ) {
			define( 'PRIVATE_KEY', '' );
		}

		if ( ! defined( 'FORMAT' ) ) {
			define( 'FORMAT', 'json' );
		}

		$oauth2   = get_xero_option( 'xero_oauth_options' );
		$xero_api = new Xero( XERO_KEY, XERO_SECRET, PUBLIC_KEY, PRIVATE_KEY, FORMAT, $oauth2 );

		$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $oauth2['token'] );

		$config->setHost( "https://api.xero.com/api.xro/2.0" );

		$apiInstance = new XeroAPI\XeroPHP\Api\AccountingApi(
			new GuzzleHttp\Client(),
			$config
		);

		$xero_tenant_id = $oauth2['tenant_id'];
		$current_batch  = get_option( 'xeroom_current_batch', 1 );

		$xero_items = array();
		try {
			$xero_result_item = $apiInstance->getItems( $xero_tenant_id, null, null, null, $current_batch );
			if ( $xero_result_item ) {
				foreach ( $xero_result_item as $data_item ) {
					if ( $data_item->getIsTrackedAsInventory() ) {
						$sku_code = $data_item->getCode();

						$xero_items[ $sku_code ] = [
							'Code'            => $sku_code,
							'Name'            => $data_item->getName(),
							'Description'     => $data_item->getDescription(),
							'QuantityOnHand'  => $data_item->getQuantityOnHand(),
							'SalesDetails'    => $data_item->getSalesDetails(),
							'PurchaseDetails' => $data_item->getPurchaseDetails(),
						];
					}
				}
			}
		} catch ( Exception $e ) {
			$exportErrorXero = 'Exception when calling AccountingApi->getItems: ' . $e->getMessage();
			xeroom_generate_sync_log( array( 'xeroom_error' => maybe_serialize( $exportErrorXero ) ) );
			update_xero_option( 'xero_synch_error_log', maybe_serialize( $exportErrorXero ) );

			return;
		}

		if ( empty( $xero_items ) ) {
			xeroom_reset_products_sync();

			return;
		}

		update_xero_option( 'xero_synch_error_log', '' );
		if ( ! empty( $xero_items ) ) {
			$woo_products = xeroom_fetch_products();

			$woo_products = array_filter( $woo_products, function ( $woo_product ) use ( $xero_items ) {
				return isset( $xero_items[ $woo_product['product_sku'] ] );
			} );

			if ( empty( $woo_products ) ) {
				xeroom_reset_products_sync();

				return;
			}

			switch ( $sync_master ) {
				case 'w':
					if ( isset( $sync_data['debug_mode'] ) && 1 === $sync_data['debug_mode'] ) {
						include( plugin_dir_path( __FILE__ ) . 'sync/woo-to-xero-debug.php' );
					} else {
						include( plugin_dir_path( __FILE__ ) . 'sync/woo-to-xero.php' );
					}

					break;
				case 'x':
					include( plugin_dir_path( __FILE__ ) . 'sync/xero-to-woo.php' );
					break;
			}

			if ( count( $woo_products ) < $batch_size ) {
				if ( isset( $sync_data['debug_mode'] ) && 1 === $sync_data['debug_mode'] || 'n' === $sync_data['sync_schedule'] ) {
					delete_option( 'xeroom_executing_stock_synch' );
					update_option( 'xeroom_stock_synch_completed', true );
				}
				xeroom_reset_products_sync();
				wp_clear_scheduled_hook( 'xeroom_sync_schedule' );

				$get_recurrence = get_option( 'xeroom_synch_recurrence' );
				if ( $get_recurrence ) {
					$recurrence_interval_seconds = get_recurrence_interval_seconds( $get_recurrence );
					$next_execution_time         = time() + $recurrence_interval_seconds;

					wp_schedule_event( $next_execution_time, $get_recurrence, 'xeroom_main_stock_sync_schedule' );
				}

				delete_option( 'xeroom_executing_stock_batch_sync' );
			} else {
				update_option( 'xeroom_current_batch', $current_batch + 1 );
			}
		}
	}
}

/**
 * Generate Product Stock Sync Log
 *
 * @param $log_data
 * @param string $type
 *
 * @throws \PhpOffice\PhpSpreadsheet\Exception
 * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
 * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
 */
function xeroom_generate_sync_log( $log_data, $type = '' ) {
	if ( empty( $log_data ) || ! is_array( $log_data ) ) {
		return;
	}
	require_once( XEROOM_ROOT_PATH . 'library/Classes/vendor/autoload.php' );

	wp_mkdir_p( XEROOM_LOG_PATH );

	$current_batch   = get_option( 'xeroom_current_batch' );
	$xeroomExcelFile = 'inventory-sync-log-' . date( "H:i-dmy" ) . '-' . $type . '-batch-' . $current_batch . '.xlsx';
	if ( 'woo-to-xero-p' === $type ) {
		$current_batch   = get_option( 'xeroom_current_product_batch' );
		$xeroomExcelFile = 'product-sync-log-' . date( "H:i-dmy" ) . '-woo-to-xero-batch-' . $current_batch . '.xlsx';
	}
	if ( 'xero-to-woo-price' === $type ) {
		$current_batch   = get_option( 'xeroom_current_product_batch' );
		$xeroomExcelFile = 'product-sync-log-' . date( "H:i-dmy" ) . '-xero-to-woo-batch-' . $current_batch . '.xlsx';
	}

	$objPHPExcel = new Spreadsheet();

	$objPHPExcel->getProperties()->setCreator( "WooCommerce" )->setLastModifiedBy( "WooCommerce" )->setTitle( "Office 2007 XLSX Product Sync List Document" )->setSubject( "Office 2007 XLSX Product Sync List Document" )->setDescription( "Product Sync List." )->setKeywords( "office 2007 openxml php" )->setCategory( "Product Sync List File" );

	if ( is_array( $log_data ) && array_key_exists( 'xeroom_error', $log_data ) ) {
		$objPHPExcel->setActiveSheetIndex( 0 )
		            ->setCellValue( 'A1', 'Error' )
		            ->setCellValue( 'B1', $log_data['xeroom_error'] );
	} else {
		if ( array_column( $log_data, 'price' ) ) {
			$objPHPExcel->setActiveSheetIndex( 0 )
			            ->setCellValue( 'A1', 'S.No' )
			            ->setCellValue( 'B1', 'Product SKU' )
			            ->setCellValue( 'C1', 'Stock' )
			            ->setCellValue( 'D1', 'Price' )
			            ->setCellValue( 'E1', 'Sync Status' );
		} else {
			$objPHPExcel->setActiveSheetIndex( 0 )
			            ->setCellValue( 'A1', 'S.No' )
			            ->setCellValue( 'B1', 'Product SKU' )
			            ->setCellValue( 'C1', 'Stock' )
			            ->setCellValue( 'D1', 'Sync Status' );
		}


		$it = 2;
		foreach ( $log_data as $item ) {
			if ( array_column( $log_data, 'price' ) ) {
				$objPHPExcel->setActiveSheetIndex( 0 )
				            ->setCellValue( "A" . $it, $it - 1 )
				            ->setCellValue( "B" . $it, $item['sku'] )
				            ->setCellValue( "C" . $it, $item['stock'] )
				            ->setCellValue( "D" . $it, $item['price'] )
				            ->setCellValue( "E" . $it, $item['status'] );
			} else {
				$objPHPExcel->setActiveSheetIndex( 0 )
				            ->setCellValue( "A" . $it, $it - 1 )
				            ->setCellValue( "B" . $it, $item['sku'] )
				            ->setCellValue( "C" . $it, $item['stock'] )
				            ->setCellValue( "D" . $it, $item['status'] );
			}

			$it ++;
		}
	}

	$objPHPExcel->getActiveSheet()->setTitle( 'Xero Stock Sync List' );
	$objPHPExcel->setActiveSheetIndex( 0 );
	$objWriter = IOFactory::createWriter( $objPHPExcel, 'Xlsx' );
	$objWriter->save( XEROOM_LOG_PATH . $xeroomExcelFile );
}

/**
 * Generate product sync log
 *
 * @param array $log_data
 * @param string $type
 *
 * @return void
 */
function xeroom_generate_product_sync_log( $log_data, $type = '' ) {
	if ( empty( $log_data ) || ! is_array( $log_data ) ) {
		return;
	}
	require_once( XEROOM_ROOT_PATH . 'library/Classes/vendor/autoload.php' );

	wp_mkdir_p( XEROOM_LOG_PATH );

	if ( 'woo-to-xero-p' === $type ) {
		$current_batch   = get_option( 'xeroom_current_product_batch' );
		$xeroomExcelFile = 'product-sync-log-' . date( "H:i-dmy" ) . '-woo-to-xero-batch-' . $current_batch . '.xlsx';
	}
	if ( 'xero-to-woo-price' === $type ) {
		$current_batch   = get_option( 'xeroom_current_product_batch' );
		$xeroomExcelFile = 'product-sync-log-' . date( "H:i-dmy" ) . '-xero-to-woo-batch-' . $current_batch . '.xlsx';
	}

	$objPHPExcel = new Spreadsheet();

	$objPHPExcel->getProperties()->setCreator( "WooCommerce" )->setLastModifiedBy( "WooCommerce" )->setTitle( "Office 2007 XLSX Product Sync List Document" )->setSubject( "Office 2007 XLSX Product Sync List Document" )->setDescription( "Product Sync List." )->setKeywords( "office 2007 openxml php" )->setCategory( "Product Sync List File" );

	if ( is_array( $log_data ) && array_key_exists( 'xeroom_error', $log_data ) ) {
		$objPHPExcel->setActiveSheetIndex( 0 )
		            ->setCellValue( 'A1', 'Error' )
		            ->setCellValue( 'B1', $log_data['xeroom_error'] );
	} else {
		if ( array_column( $log_data, 'price' ) ) {
			$objPHPExcel->setActiveSheetIndex( 0 )
			            ->setCellValue( 'A1', 'No' )
			            ->setCellValue( 'B1', 'SKU' )
			            ->setCellValue( 'C1', 'Desc. New' )
			            ->setCellValue( 'D1', 'Price New' )
			            ->setCellValue( 'E1', 'Status' );
		} else {
			$objPHPExcel->setActiveSheetIndex( 0 )
			            ->setCellValue( 'A1', 'No' )
			            ->setCellValue( 'B1', 'SKU' )
			            ->setCellValue( 'C1', 'Desc. New' )
			            ->setCellValue( 'D1', 'Status' );
		}


		$it = 2;
		foreach ( $log_data as $item ) {
			if ( array_column( $log_data, 'price' ) ) {
				$objPHPExcel->setActiveSheetIndex( 0 )
				            ->setCellValue( "A" . $it, $it - 1 )
				            ->setCellValue( "B" . $it, $item['sku'] )
				            ->setCellValue( "C" . $it, $item['description'] )
				            ->setCellValue( "D" . $it, $item['price'] )
				            ->setCellValue( "E" . $it, $item['status'] );
			} else {
				$objPHPExcel->setActiveSheetIndex( 0 )
				            ->setCellValue( "A" . $it, $it - 1 )
				            ->setCellValue( "B" . $it, $item['sku'] )
				            ->setCellValue( "C" . $it, $item['description'] )
				            ->setCellValue( "D" . $it, $item['status'] );
			}

			$it ++;
		}
	}

	$objPHPExcel->getActiveSheet()->setTitle( 'Xero Product Sync List' );
	$objPHPExcel->setActiveSheetIndex( 0 );
	$objWriter = IOFactory::createWriter( $objPHPExcel, 'Xlsx' );
	$objWriter->save( XEROOM_LOG_PATH . $xeroomExcelFile );
    
	// Call the cleanup function after generating the log.
	xeroom_cleanup_old_logs();
}

/**
 * Fetch Woo Product Stock & SKU
 * who is not sync yet
 *
 * @return array
 */
function xeroom_fetch_products() {
	$sync_data  = get_xero_option( 'sync_stock_data' );
	$batch_size = 100;
	if ( ! empty( $sync_data ) ) {
		$batch_size = absint( $sync_data['batch_sync_size'] );
	}

	if ( isset( $sync_data['debug_mode'] ) && $sync_data['debug_mode'] > 0 ) {
		$batch_size = 10;
	}

	global $wpdb;

	$sql = "
        SELECT p.ID
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm
        ON p.ID = pm.post_id AND pm.meta_key = 'wpr_stock_updated'
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
        AND (pm.meta_value IS NULL OR pm.meta_value = '0')
        LIMIT %d
    ";

	// Prepare the SQL query with the batch size
	$prepared_sql = $wpdb->prepare( $sql, $batch_size );

	// Execute the SQL query
	$product_ids = $wpdb->get_col( $prepared_sql );

	$product_data = array();

	if ( ! empty( $product_ids ) ) {
		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( $product->is_type( 'variable' ) ) {
				$variations = $product->get_available_variations();
				foreach ( $variations as $variation ) {
					$variation_obj            = new WC_Product_Variation( $variation['variation_id'] );
					$variation_managing_stock = $variation_obj->get_manage_stock() ? 'yes' : 'no';
					$product_data[]           = array(
						'product_id'     => $variation['variation_id'],
						'product_stock'  => $variation_obj->get_stock_quantity(),
						'product_sku'    => $variation_obj->get_sku(),
						'product_price'  => $variation_obj->get_price(),
						'parent_id'      => $product->get_id(),
						'managing_stock' => $variation_managing_stock,
					);
				}
			} else {
				$was_managing_stock = $product->get_manage_stock() ? 'yes' : 'no';
				$product_data[]     = array(
					'product_id'     => $product->get_id(),
					'product_stock'  => $product->get_stock_quantity(),
					'product_sku'    => $product->get_sku(),
					'product_price'  => $product->get_price(),
					'managing_stock' => $was_managing_stock,
				);
			}
		}
	}

	return $product_data;
}

add_action( 'wp_ajax_xero_sync_products', 'xero_sync_products_data' );
/**
 * Sync Products Settings
 */
function xero_sync_products_data() {
	check_ajax_referer( 'xero-ajax', 'nonce' );

	delete_option( 'xeroom_total_product_batches' );
	delete_option( 'xeroom_current_product_batch' );
	delete_option( 'xeroom_executing_product_synch' );
	delete_option( 'xeroom_synch_product_type' );
	delete_option( 'xeroom_synch_product_recurrence' );

	$sync_master     = esc_attr( $_POST['master'] );
	$what_to_update  = esc_attr( $_POST['what_to_update'] );
	$sync_schedule   = esc_attr( $_POST['schedule'] );
	$batch_sync_size = isset( $_POST['size'] ) ? absint( $_POST['size'] ) : 100;

	if ( empty( $batch_sync_size ) ) {
		wp_die( __( 'Please fill Batch Sync Size!', 'xeroom' ) );
	}

	$sync_data = array();
	if ( ! empty( $sync_master ) ) {
		$sync_data['sync_prod_master'] = $sync_master;
	}

	if ( ! empty( $what_to_update ) ) {
		$sync_data['what_to_update'] = $what_to_update;
	} else {
		$sync_data['what_to_update'] = 'p';
	}
	$message = '';
	if ( ! empty( $sync_schedule ) ) {
		update_option( 'xeroom_executing_product_synch', 1 );
		xeroom_reset_sync_products();

		$sync_data['sync_prod_schedule'] = $sync_schedule;

		if ( ! empty( $batch_sync_size ) ) {
			$sync_data['batch_product_sync_size'] = $batch_sync_size;
		}

		if ( false === get_option( 'xeroom_total_product_batches' ) ) {
			$woo_products  = xeroom_get_total_products_to_sync_count();
			$total_batches = ceil( $woo_products / $sync_data['batch_product_sync_size'] );

			update_option( 'xeroom_total_product_batches', $total_batches );
		}

		update_option( 'xeroom_current_product_batch', 1 );

		update_xero_option( 'sync_product_data', $sync_data );

		if ( 'n' != $sync_schedule ) {
			// Remove first, if exists
			wp_clear_scheduled_hook( 'xeroom_main_product_sync_schedule' );

			switch ( $sync_schedule ) {
				case 'm':
					$recurrence = 'five_minutes';
					$synch_type = '5 min';
					break;
				case 'h':
					$recurrence = 'hourly';
					$synch_type = '1 hour';
					break;
				case 'd':
					$recurrence = 'daily';
					$synch_type = '1 day';
					break;
			}

			// Create new cron job
			$message = sprintf(
				esc_html__( 'Syncing %d of %d batches at 1 batch/min', 'xeroom' ),
				1,
				$total_batches
			);

			update_option( 'xeroom_synch_product_type', $synch_type );
			update_option( 'xeroom_synch_product_recurrence', $recurrence );

			wp_schedule_event( time(), 'per_minute', 'xeroom_sync_product_schedule' );
		} else {
			wp_clear_scheduled_hook( 'xeroom_main_product_sync_schedule' );
			wp_clear_scheduled_hook( 'xeroom_sync_product_schedule' );

			wp_schedule_event( time(), 'per_minute', 'xeroom_sync_product_schedule' );

			$message = sprintf(
				esc_html__( 'Syncing %d of %d batches at 1 batch/min', 'xeroom' ),
				1,
				$total_batches
			);
		}
	}

	wp_die( $message );
}

add_action( 'xeroom_sync_product_schedule', 'xeroom_sync_products_items' );
/**
 * Sync Products Stock
 */
function xeroom_sync_products_items() {
	$sync_data = get_xero_option( 'sync_product_data' );

	if ( ! empty( $sync_data ) ) {
		$sync_master    = esc_attr( $sync_data['sync_prod_master'] );
		$what_to_update = esc_attr( $sync_data['what_to_update'] );
		$batch_size     = absint( $sync_data['batch_product_sync_size'] );
		$errorType      = "orderProduct";
		$xeroTime       = date( 'Y-m-d H:i:s' );

		global $wpdb;
		$xeroCredentialTable    = $wpdb->prefix . "xeroom_credentials";
		$sql                    = "SELECT * FROM " . $xeroCredentialTable . " WHERE id=1";
		$xeroCredentialsFromTbl = $wpdb->get_results( $sql );
		$xeroApiKey             = sanitize_text_field( $xeroCredentialsFromTbl[0]->xero_api_key );
		$xeroApiSecret          = sanitize_text_field( $xeroCredentialsFromTbl[0]->xero_api_secret );
		$asset_code             = sanitize_text_field( $xeroCredentialsFromTbl[0]->asset_code );
		$salesAccount           = sanitize_text_field( $xeroCredentialsFromTbl[0]->sales_account );
		$SoldCode               = sanitize_text_field( $xeroCredentialsFromTbl[0]->sold_code );

		include_once( XEROOM_ROOT_PATH . "library/xeroom_indexManager.php" );
		if ( ! defined( 'BASE_PATH' ) ) {
			define( 'BASE_PATH', dirname( __FILE__ ) );
		}

		if ( ! defined( 'XERO_KEY' ) ) {
			define( 'XERO_KEY', $xeroApiKey );
		}

		if ( ! defined( 'XERO_SECRET' ) ) {
			define( 'XERO_SECRET', $xeroApiSecret );
		}

		if ( ! defined( 'PUBLIC_KEY' ) ) {
			define( 'PUBLIC_KEY', '' );
		}

		if ( ! defined( 'PRIVATE_KEY' ) ) {
			define( 'PRIVATE_KEY', '' );
		}

		if ( ! defined( 'FORMAT' ) ) {
			define( 'FORMAT', 'json' );
		}

		$oauth2 = get_xero_option( 'xero_oauth_options' );
		xeroom_check_xero_token( $oauth2 );
		$xero_api = new Xero( XERO_KEY, XERO_SECRET, PUBLIC_KEY, PRIVATE_KEY, FORMAT, $oauth2 );

		update_xero_option( 'xero_product_synch_error_log', '' );

		$xero_items = $xero_api->Items();
		if ( ! empty( $xero_items ) && is_array( $xero_items ) && array_key_exists( 'Items', $xero_items ) ) {
			if ( 'x' === $sync_master ) {
				$woo_products = xeroom_fetch_products_to_sync( array( 'x' ) );
			} else {
				$woo_products = xeroom_fetch_products_to_sync( array( 'w' ) );
			}

			if ( 'w' === $sync_master ) {
				include( XEROOM_PLUGIN_PATH . '/sync/woo-product-to-xero.php' );
			} else {
				include( XEROOM_PLUGIN_PATH . '/sync/xero-product-to-woo.php' );
			}

			if ( count( $woo_products ) < $batch_size ) {
				xeroom_reset_sync_products();
				if ( 'n' === $sync_data['sync_prod_schedule'] ) {
					delete_option( 'xeroom_executing_product_synch' );
					update_option( 'xeroom_product_synch_completed', true );
				}
				delete_option( 'xeroom_executing_product_batch_sync' );
				wp_clear_scheduled_hook( 'xeroom_sync_product_schedule' );

				$get_recurrence = get_option( 'xeroom_synch_product_recurrence' );
				if ( $get_recurrence ) {
					$recurrence_interval_seconds = get_recurrence_interval_seconds( $get_recurrence );
					$next_execution_time         = time() + $recurrence_interval_seconds;

					wp_schedule_event( $next_execution_time, $get_recurrence, 'xeroom_main_product_sync_schedule' );
				}
			} else {
				$current_batch = get_option( 'xeroom_current_product_batch' );
				update_option( 'xeroom_current_product_batch', $current_batch + 1 );
			}
		} else {
			$exportErrorXero = '';
			if ( isset( $xero_items['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
				$errD = $xero_items['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
				returnErrorMessageByXero( 0, $errD, $xeroTime, $errorType );
				$exportErrorXero .= $errD;
			} else if ( isset( $simpleXeroItem['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
				$errD = $xero_items['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
				for ( $e = 0; $e < count( $errD ); $e ++ ) {
					$errorMessage = $xero_items['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
					returnErrorMessageByXero( 0, $errorMessage, $xeroTime, $errorType );
					$exportErrorXero .= $errorMessage;
				}
			} else {
				$exportErrorXero = __( 'Please wait before retrying the Xero api' );
			}
			xeroom_generate_sync_log( array( 'xeroom_error' => maybe_serialize( $exportErrorXero ) ) );
			update_xero_option( 'xero_product_synch_error_log', maybe_serialize( $exportErrorXero ) );
		}
	}
}

/**
 * Reset products sync to not sent
 */
function xeroom_reset_sync_products() {
	global $wpdb;

	$wpdb->query(
		$wpdb->prepare( "
			UPDATE $wpdb->postmeta
			SET meta_value = %d
			WHERE meta_key = %s
			",
			0, 'wpr_item_sent'
		)
	);

	update_option( 'xeroom_current_product_batch', 1 );
}


/**
 * Fetch Woo Product Stock & SKU
 * who is not sync yet
 *
 * @return array
 */
function xeroom_fetch_products_to_sync( $extra_args = array() ) {
	global $wpdb;

	$sync_data  = get_xero_option( 'sync_product_data' );
	$batch_size = ! empty( $sync_data ) ? absint( $sync_data['batch_product_sync_size'] ) : 100;

	// Construct the base SQL query
	$sql = "
        SELECT p.ID
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm1
        ON p.ID = pm1.post_id AND pm1.meta_key = 'wpr_item_sent'
    ";

	if ( ! empty( $extra_args ) ) {
		$sql .= "
            LEFT JOIN {$wpdb->postmeta} pm2
            ON p.ID = pm2.post_id AND pm2.meta_key = 'xerrom_synch_with_xero'
        ";
	}

	$sql .= "
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
        AND (pm1.meta_value IS NULL OR pm1.meta_value = '0')
    ";

	if ( ! empty( $extra_args ) ) {
		$sql .= "
            AND pm2.meta_value = '1'
        ";
	}

	$sql .= "
        LIMIT %d
    ";

	// Prepare the SQL query with the batch size
	$prepared_sql = $wpdb->prepare( $sql, $batch_size );

	// Execute the SQL query
	$product_ids = $wpdb->get_col( $prepared_sql );

	$product_data = array();

	if ( ! empty( $product_ids ) ) {
		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( $product->is_type( 'variable' ) ) {
				$variations = $product->get_available_variations();
				foreach ( $variations as $variation ) {
					$variation_obj = new WC_Product_Variation( $variation['variation_id'] );
					if ( $product->get_sku() !== $variation_obj->get_sku() ) {
						$product_data[] = array(
							'product_id'    => $variation['variation_id'],
							'product_stock' => $variation_obj->get_stock_quantity(),
							'product_sku'   => $variation_obj->get_sku(),
							'product_name'  => substr( $variation_obj->get_name(), 0, 48 ),
							'product_desc'  => $variation_obj->get_name(),
							'product_price' => $variation_obj->get_price(),
							'product_tax'   => xeroom_fetch_product_class_type( $variation['variation_id'] ),
							'parent_id'     => $product->get_id(),
						);
					}
				}
			} else {
				$product_data[] = array(
					'product_id'    => $product->get_id(),
					'product_stock' => $product->get_stock_quantity(),
					'product_sku'   => $product->get_sku(),
					'product_name'  => substr( $product->get_name(), 0, 48 ),
					'product_desc'  => $product->get_name(),
					'product_price' => $product->get_price(),
					'product_tax'   => xeroom_fetch_product_class_type( $product->get_id() ),
				);
			}
		}
	}

	return $product_data;
}

/**
 * Fetch item tax type
 *
 * @param $product_id
 *
 * @return string
 */
function xeroom_fetch_product_class_type( $product_id ) {
	$item_tax         = '';
	$fetch_tax_method = get_xero_option( 'xero_tax_method' );
	$fetch_saved_tax  = get_xero_option( 'xero_tax_methods' );

	if ( $fetch_tax_method && wc_tax_enabled() ) {
		$simple_tax = get_post_meta( $product_id, '_tax_class', true );

		if ( ! empty( $simple_tax ) ) {
			$product_tax = $simple_tax;
		} else {
			$product_tax = 'standard';
		}

		if ( $fetch_saved_tax && is_array( $fetch_saved_tax ) && array_key_exists( 'xero_' . $product_tax . '_taxmethods', $fetch_saved_tax ) ) {
			$item_tax = xero_tax_type_code( $fetch_saved_tax[ 'xero_' . $product_tax . '_taxmethods' ] );
		}
	}

	return $item_tax;
}

/**
 * Find item by SKU
 *
 * @param array $items Array of items to search from
 * @param string $sku SKU value to search for
 *
 * @return bool            Returns true if item with specified SKU is found, false otherwise
 */
function find_item_by_SKU( $items, $sku ) {
	foreach ( $items as $item ) {
		if ( $item['Code'] === $sku ) {
			return true;
		}
	}

	return false;
}

add_action( 'wp_ajax_get_xeroom_stock_sync_status', 'get_xeroom_stock_sync_status' );
/**
 * Get Xeroom stock sync status
 *
 * @return void
 */
function get_xeroom_stock_sync_status() {
	check_ajax_referer( 'xeroom-ajax', 'nonce' );

	$cron_status = array();

	$cron_status['status'] = '';

	$cron_executing = get_option( 'xeroom_stock_synch_completed' );
	$current_batch  = get_option( 'xeroom_current_batch' );
	$total_batches  = get_option( 'xeroom_total_batches' );
	$synch_type     = get_option( 'xeroom_synch_type' );
	// Check if the cron event exists
	if ( wp_next_scheduled( 'xeroom_sync_schedule' ) ) {
		$cron_status['status'] = sprintf(
			esc_html__( 'Syncing %d of %d batches at 1 batch/min', 'xeroom' ),
			$current_batch,
			$total_batches
		);
	} elseif ( $cron_executing ) {
		$cron_status['status'] = sprintf( '%s %s <a href="%s" target="_blank">%s</a>', __( 'Sync completed.', 'xeroom' ), __( 'Please', 'xeroom' ), admin_url( 'admin.php?page=xeroom_log_woo_xero' ), __( 'check Log Files for the report.', 'xeroom' ) );
		delete_option( 'xeroom_stock_synch_completed' );
	} else {
		$cron_status['status'] = '';
	}

	wp_send_json( $cron_status );

	wp_die();
}

add_action( 'wp_ajax_get_xeroom_product_sync_status', 'get_xeroom_product_sync_status' );
/**
 * Get Xeroom product sync status
 *
 * @return void
 */
function get_xeroom_product_sync_status() {
	check_ajax_referer( 'xeroom-ajax', 'nonce' );

	$cron_status = array();

	$cron_status['status'] = '';
	$cron_executing        = get_option( 'xeroom_product_synch_completed' );
	$current_batch         = get_option( 'xeroom_current_product_batch' );
	$total_batches         = get_option( 'xeroom_total_product_batches' );
	$synch_type            = get_option( 'xeroom_synch_product_type' );
	// Check if the cron event exists
	if ( wp_next_scheduled( 'xeroom_sync_product_schedule' ) ) {
		$cron_status['status'] = sprintf(
			esc_html__( 'Syncing %d of %d batches at 1 batch/min', 'xeroom' ),
			$current_batch,
			$total_batches
		);
	} elseif ( $cron_executing ) {
		$cron_status['status'] = sprintf( '%s %s <a href="%s" target="_blank">%s</a>', __( 'Sync completed.', 'xeroom' ), __( 'Please', 'xeroom' ), admin_url( 'admin.php?page=xeroom_log_woo_xero' ), __( 'check Log Files for the report.', 'xeroom' ) );
		delete_option( 'xeroom_product_synch_completed' );
	} else {
		$cron_status['status'] = '';
	}

	wp_send_json( $cron_status );

	wp_die();
}

/**
 * Get the total count of products and variations
 *
 * @return int Total count of published products and variations
 * @global wpdb $wpdb WordPress database access object
 *
 */
function xeroom_get_total_products_count() {
	global $wpdb;

	// Query to count all published products and variations
	$total_products_query = "
        SELECT COUNT(*)
        FROM {$wpdb->posts}
        WHERE post_type IN ('product', 'product_variation')
        AND post_status = 'publish'
    ";

	// Execute the query
	$total_products = $wpdb->get_var( $total_products_query );

	return $total_products;
}

/**
 * Fetches products to be synced with Xero
 *
 * @param array $extra_args Optional arguments to filter the query
 *
 * @return array Product data to be synced
 */
function xeroom_get_total_products_to_sync_count( $extra_args = array() ) {
	global $wpdb;

	// Construct the base SQL query
	$sql = "
        SELECT COUNT(p.ID)
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm1
        ON p.ID = pm1.post_id AND pm1.meta_key = 'wpr_item_sent'
    ";

	if ( ! empty( $extra_args ) ) {
		$sql .= "
            LEFT JOIN {$wpdb->postmeta} pm2
            ON p.ID = pm2.post_id AND pm2.meta_key = 'xerrom_synch_with_xero'
        ";
	}

	$sql .= "
        WHERE p.post_type IN ('product', 'product_variation')
        AND p.post_status = 'publish'
        AND (pm1.meta_value IS NULL OR pm1.meta_value = '0')
    ";

	if ( ! empty( $extra_args ) ) {
		$sql .= "
            AND pm2.meta_value = '1'
        ";
	}

	// Execute the SQL query
	$total_products_to_sync = $wpdb->get_var( $sql );

	return $total_products_to_sync;
}

add_action( 'xeroom_main_stock_sync_schedule', 'xeroom_main_stock_sync_handler' );
/**
 * Main sync handler for Xero
 *
 * Set the transient for executing the batch sync and schedule the batch sync cron job to run every minute.
 */
function xeroom_main_stock_sync_handler() {
	// Get the transient for executing batch sync.
	$still_batching = get_option( 'xeroom_executing_stock_batch_sync' );

	// Schedule the batch sync cron job to run every minute.
	if ( ! $still_batching && ! wp_next_scheduled( 'xeroom_sync_schedule' ) ) {
		wp_schedule_event( time(), 'per_minute', 'xeroom_sync_schedule' );
		update_option( 'xeroom_executing_stock_batch_sync', true );
		wp_clear_scheduled_hook( 'xeroom_main_stock_sync_schedule' );
	}
}

add_action( 'xeroom_main_product_sync_schedule', 'xeroom_main_productsync_handler' );
/**
 * Main sync handler for Xero
 *
 * Set the transient for executing the batch sync and schedule the batch sync cron job to run every minute.
 */
function xeroom_main_productsync_handler() {
	// Get the transient for executing batch sync.
	$still_batching = get_option( 'xeroom_executing_product_batch_sync' );

	// Schedule the batch sync cron job to run every minute.
	if ( ! $still_batching && ! wp_next_scheduled( 'xeroom_sync_product_schedule' ) ) {
		wp_schedule_event( time(), 'per_minute', 'xeroom_sync_product_schedule' );
		update_option( 'xeroom_executing_product_batch_sync', true );
		wp_clear_scheduled_hook( 'xeroom_main_product_sync_schedule' );
	}
}

add_action( 'restrict_manage_posts', 'add_custom_product_filter' );
/**
 * Add custom product filter
 */
function add_custom_product_filter() {
	global $typenow;
	if ( $typenow == 'product' ) {
		?>
        <select name="xerrom_synch_with_xero" id="xerrom_synch_with_xero">
            <option value=""><?php _e( 'Show all products (Xeroom)', 'woocommerce' ); ?></option>
            <option value="1" <?php echo ( isset( $_GET['xerrom_synch_with_xero'] ) && $_GET['xerrom_synch_with_xero'] == '1' ) ? 'selected' : ''; ?>><?php _e( 'Synced with Xero', 'woocommerce' ); ?></option>
        </select>
		<?php
	}
}

add_action( 'pre_get_posts', 'filter_products_by_custom_meta' );
/**
 * Filter products by custom meta
 *
 * @param WP_Query $query The WP_Query object.
 *
 * @return void
 */
function filter_products_by_custom_meta( $query ) {
	global $pagenow, $typenow;
	if ( $typenow == 'product' && is_admin() && $pagenow == 'edit.php' && isset( $_GET['xerrom_synch_with_xero'] ) && $_GET['xerrom_synch_with_xero'] != '' ) {
		$meta_query = array(
			array(
				'key'     => 'xerrom_synch_with_xero',
				'value'   => '1',
				'compare' => '='
			)
		);
		$query->set( 'meta_query', $meta_query );
	}
}

/**
 * Get the recurrence interval in seconds
 *
 * @param string $recurrence The recurrence option
 *
 * @return int The interval in seconds
 */
function get_recurrence_interval_seconds( $recurrence ) {
	switch ( $recurrence ) {
		case 'five_minutes':
			return 300;
		case 'hourly':
			return 3600;
		case 'daily':
			return 86400;
		default:
			return 0; // Default to no interval
	}
}

/**
 * Clean up old log files
 *
 * This function deletes log files in a specified directory if there are more than 50 log files.
 *
 * @param string $log_directory The directory where log files are stored
 *
 * @return void
 */
function xeroom_cleanup_old_logs() {
	$log_directory = XEROOM_LOG_PATH;

	// Get all log files in the directory
	$log_files = glob( $log_directory . '/*.xlsx' );

	// Sort files by modification time, newest first
	usort( $log_files, function ( $a, $b ) {
		return filemtime( $b ) - filemtime( $a );
	} );

	// If there are more than 50 log files, delete the oldest ones
	if ( count( $log_files ) > 50 ) {
		$files_to_delete = array_slice( $log_files, 50 );
		foreach ( $files_to_delete as $file ) {
			unlink( $file );
		}
	}
}
