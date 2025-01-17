<?php
/**
 * Parse Xero Items
 */
$line_items = array();
if ( isset( $xero_items['Items']['Item'] ) ) {
	foreach ( $xero_items['Items']['Item'] as $xero_item ) {
		if ( array_key_exists( 'ItemID', $xero_item ) ) {
			$line_items[ $xero_item['Code'] ] = [
				'Name'           => isset( $xero_item['Name'] ) ? $xero_item['Name'] : '',
				'QuantityOnHand' => isset( $xero_item['QuantityOnHand'] ) ? $xero_item['QuantityOnHand'] : 0,
				'Description'    => isset( $xero_item['Description'] ) ? $xero_item['Description'] : '',
				'UnitPrice'      => isset( $xero_item['SalesDetails']['UnitPrice'] ) ? $xero_item['SalesDetails']['UnitPrice'] : 0,
			];
		}
	}
}

/**
 * Update/Create Products
 */
if ( ! empty( $line_items ) ) {
	$created_products = array();
	$api_items        = array();
	$it               = 1;

	foreach ( $line_items as $product_sku => $xero_data ) {
		$product_sku = trim( $product_sku );

		if ( in_array( $product_sku, $created_products ) ) {
			error_log( 'Product with SKU ' . $product_sku . ' is already processed, skipping duplicate creation.' );
			continue;
		}

		$product_id = wc_get_product_id_by_sku( $product_sku );
		$product    = wc_get_product( $product_id );

		$xero_price    = isset( $xero_data['UnitPrice'] ) ? $xero_data['UnitPrice'] : '';
		$xero_describe = isset( $xero_data['Description'] ) ? $xero_data['Description'] : '';
		$xero_aty      = isset( $xero_data['QuantityOnHand'] ) ? $xero_data['QuantityOnHand'] : 0;
		$xero_name     = isset( $xero_data['Name'] ) ? $xero_data['Name'] : '';
		if ( empty( $xero_name ) ) {
			$xero_name = esc_html( $product_sku );
		}

		if ( '' != $xero_price ) {
			if ( $product ) {
                error_log('SKU Product Updated : '.print_r($product_sku,true));
				// Update product.
				update_post_meta( absint( $product_id ), 'wpr_item_sent', 1 );

				$product->set_name( $xero_name );

				if ( '' != $xero_price && ( 'p' == $what_to_update || 'pd' == $what_to_update ) ) {
					$product->set_price( wc_format_decimal( $xero_price, wc_get_price_decimals() ) );
					$product->set_regular_price( wc_format_decimal( $xero_price, wc_get_price_decimals() ) );
				}

				if ( 'pd' == $what_to_update && '' !== $xero_describe ) {
					$product->set_description( $xero_describe );
				}

				$product->save();

				$created_products[] = $product_sku;

				$api_items[] = [
					'no'          => $it,
					'sku'         => $product_sku,
					'description' => $xero_describe,
					'price'       => $xero_price,
					'status'      => 'Updated',
				];
			} else {
				if( $xero_price > 0 ) {
					error_log('SKU $new_product : '.print_r($product_sku,true));
					//Create new product
					$new_product = new WC_Product();

					$new_product->set_name( $xero_name );
					$new_product->set_sku( $product_sku );
					$new_product->set_stock_quantity( $xero_aty );

					if ( '' != $xero_price && ( 'p' == $what_to_update || 'pd' == $what_to_update ) ) {
						$new_product->set_price( wc_format_decimal( $xero_price, wc_get_price_decimals() ) );
						$new_product->set_regular_price( wc_format_decimal( $xero_price, wc_get_price_decimals() ) );
					}

					if ( 'pd' == $what_to_update && '' !== $xero_describe ) {
						$new_product->set_description( $xero_describe );
					}

					$new_product_id = $new_product->save();

					$created_products[] = $product_sku;

					$api_items[] = [
						'no'          => $it,
						'sku'         => $product_sku,
						'description' => $xero_describe,
						'price'       => $xero_price,
						'status'      => 'Created',
					];
				} else {
					$api_items[] = [
						'no'          => $it,
						'sku'         => $product_sku,
						'description' => $xero_describe,
						'price'       => $xero_price,
						'status'      => 'Not Created. Price is 0.',
					];
				}
			}
		} else {
			$api_items[] = array(
				'no'          => $it,
				'sku'         => $product_sku,
				'description' => $xero_describe,
				'price'       => $xero_price,
				'status'      => 'Not updated/Created',
			);
		}

		$it ++;
	}

	// Generate Log
	xeroom_generate_product_sync_log( $api_items, 'xero-to-woo-price' );
} else {
	xeroom_reset_products_sync();
}
