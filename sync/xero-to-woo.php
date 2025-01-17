<?php
/**
 * Parse Xero Items
 */
$line_items = array();
if( isset( $xero_items ) ) {
    $xero_items = array_values(array_reduce($xero_items, function ($carry, $item) {
        $code = $item['Code'];
        if (!isset($carry[$code])) {
            $carry[$code] = $item;
        }
        return $carry;
    }, []));
	foreach ( $xero_items as $xero_item ) {
		if ( array_key_exists( 'QuantityOnHand', $xero_item ) ) {
			$line_items[ $xero_item['Code'] ]['Name']           = isset($xero_item['Name']) ? $xero_item['Name'] : '';
			$line_items[ $xero_item['Code'] ]['QuantityOnHand'] = isset($xero_item['QuantityOnHand']) ? $xero_item['QuantityOnHand'] : 0;
			$line_items[ $xero_item['Code'] ]['Description']    = isset($xero_item['Description']) ? $xero_item['Description'] : '';
			$line_items[ $xero_item['Code'] ]['UnitPrice']      = isset($xero_item['SalesDetails']['UnitPrice']) ? $xero_item['SalesDetails']['UnitPrice'] : 0;
		}
	}
}

/**
 * Update Product Stock
 */
$woo_products = array_values(array_reduce($woo_products, function ($carry, $product) {
    $sku = $product['product_sku'];
    if (!isset($carry[$sku])) {
        $carry[$sku] = $product;
    }
    return $carry;
}, []));
if ( ! empty( $woo_products ) ) {
	$api_items = array();
	$it        = 1;
	foreach ( $woo_products as $woo_product ) {
		$product_sku   = $woo_product['product_sku'];
		$product_id    = $woo_product['product_id'];
		$product_stock = (int) $woo_product['product_stock'];

		$xero_stock = '';
		if ( ! empty( $line_items[ $product_sku ]['QuantityOnHand'] ) ) {
			$xero_stock = $line_items[ $product_sku ]['QuantityOnHand'];
		}
        
        $product_id_sent = isset( $woo_product['parent_id'] ) ? $woo_product['parent_id'] : $woo_product['product_id'];
        update_post_meta( absint( $product_id_sent ), 'wpr_stock_updated', 1 );

		if ( '' == $product_sku ) {
			$product_sku = sprintf( '%s %s', __( 'Product ID ' ), $product_id );
		}
		$is_managed = $woo_product['managing_stock'] === 'yes';

		if ( $is_managed && '' != $xero_stock ) {
			$new_quantity = wc_update_product_stock( $product_id, $xero_stock );

			$api_items[] = array(
				'no'     => $it,
				'sku'    => $product_sku,
				'stock'  => $new_quantity,
				'status' => 'Updated',
			);
		} else {
			$api_items[] = array(
				'no'     => $it,
				'sku'    => $product_sku,
				'stock'  => $product_stock,
				'status' => 'Not tracked in WC',
			);
		}

		$it ++;
	}

	// Generate Log
	xeroom_generate_sync_log( $api_items, 'xero-to-woo' );
} else {
	xeroom_reset_products_sync();
}
