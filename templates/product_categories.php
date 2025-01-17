<?php
/**
 * DIsplay Product Categories Settings
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Display product categories
 */
$taxonomy     = 'product_cat';
$orderby      = 'name';
$show_count   = 0;
$pad_counts   = 0;
$hierarchical = 1;
$title        = '';
$empty        = 0;

$args               = array(
	'taxonomy'     => $taxonomy,
	'orderby'      => $orderby,
	'show_count'   => $show_count,
	'pad_counts'   => $pad_counts,
	'hierarchical' => $hierarchical,
	'title_li'     => $title,
	'hide_empty'   => $empty,
);
$product_categories = get_categories( $args );

if ( ! empty( $product_categories ) ) {
	$associated_categories = get_xero_option( 'xero_associate_product_categories' );
	$selected_category     = $account_value = '';
	$xero_default_accounts = get_xero_option( 'xero_default_accounts' );

	if ( ! empty( $associated_categories ) ) {
		for ( $i = 0; $i < count( $associated_categories ); $i ++ ) {
			echo sprintf( '<div class="xeroom-category-input-%d">', $i );
			echo '<div><select class="xeroom-select" name="product_categories[]" style="width:165px">';
			echo sprintf( '<option value="">%s</option>', __( 'Select product category', 'xeroom' ) );

			foreach ( $product_categories as $category ) {
				if ( 'uncategorized' === $category->slug ) {
					continue;
				}
				$selected = isset( $_POST['product_categories_'] ) && absint( $category->term_id ) == $_POST[ $i ]['product_categories_'] ? ' selected' : '';
				if ( xeroom_is_associated_category( $i, $category->term_id ) ) {
					$selected          = ' selected';
					$selected_category = absint( $category->term_id );
				}

				if ( 0 === $category->category_parent ) {
					echo sprintf( '<option value="%d"%s>%s</option>', absint( $category->term_id ), $selected, esc_attr( $category->name ) );
				}
			}
			echo '</select></div>';

			// Replace the text input with the dropdown for product account categories
			echo '<div><select class="xero_input" name="product_account_categories[]" id="product_account_categories">';

			if ( $xero_default_accounts ) {
				foreach ( $xero_default_accounts as $sale_account ) {
					if ( 'REVENUE' !== $sale_account['Type'] && 'SALES' !== $sale_account['Type'] ) {
						continue;
					}

					if ( ! isset( $sale_account['Code'] ) ) {
						continue;
					}

					$selected = '';
					if ( isset( $_POST['product_account_categories'][ $i ] ) && $sale_account['Code'] == $_POST['product_account_categories'][ $i ] ) {
						$selected = ' selected';
					}
                    
                    $categoryId = key($associated_categories[$i]); 
                    if ( isset($associated_categories[$i][$categoryId]) &&  $sale_account['Code'] == $associated_categories[$i][$categoryId] ) {
                        $selected = ' selected';
                    }
                    
					echo sprintf( '<option value="%s"%s>%s</option>', $sale_account['Code'], $selected, $sale_account['Code'] . ' - ' . $sale_account['Name'] );
				}
			} else {
				echo sprintf( '<option value="%s">%s</option>', $account_value, $account_value );
			}
			echo '</select></div>';

			if ( $i > 0 ) {
				echo sprintf( '<div><button type="button" name="add" id="xeroom-category-input-%d" class="button button-secondary btn_remove">%s</button></div>', $i, __( 'Remove', 'xeroom' ) );
			} else {
				echo sprintf( '<div><button type="button" name="add" id="xeroom-add-more-category" class="button button-primary">%s</button></div>', __( 'Add More', 'xeroom' ) );
			}

			echo '</div>';
		}
	} else {
		$i = 0;
		echo sprintf( '<div class="xeroom-category-input-">', $i );
		echo '<div><select class="xeroom-select" name="product_categories[]" style="width:165px">';
		echo sprintf( '<option value="">%s</option>', __( 'Select product category', 'xeroom' ) );

		foreach ( $product_categories as $category ) {
			if ( 'uncategorized' === $category->slug ) {
				continue;
			}

			if ( 0 === $category->category_parent ) {
				echo sprintf( '<option value="%d"%s>%s</option>', absint( $category->term_id ), '', esc_attr( $category->name ) );
			}
		}
		echo '</select></div>';

		// Replace the text input with the dropdown for product account categories
		echo '<div><select class="xero_input" name="product_account_categories[]" id="product_account_categories">';

		if ( $xero_default_accounts ) {
			foreach ( $xero_default_accounts as $sale_account ) {
				if ( 'REVENUE' !== $sale_account['Type'] && 'SALES' !== $sale_account['Type'] ) {
					continue;
				}

				if ( ! isset( $sale_account['Code'] ) ) {
					continue;
				}

				$selected = '';
				if ( isset( $_POST['product_account_categories'][ $i ] ) && $sale_account['Code'] == $_POST['product_account_categories'][ $i ] ) {
					$selected = ' selected';
				}
				echo sprintf( '<option value="%s"%s>%s</option>', $sale_account['Code'], $selected, $sale_account['Code'] . ' - ' . $sale_account['Name'] );
			}
		} else {
			echo sprintf( '<option value="%s">%s</option>', $account_value, $account_value );
		}
		echo '</select></div>';

		echo sprintf( '<div><button type="button" name="add" id="xeroom-add-more-category" class="button button-primary">%s</button></div>', __( 'Add More', 'xeroom' ) );

		echo '</div>';
	}
}
?>
<script>
    jQuery(document).ready(function ($) {
        var xcat = <?php echo $i == 0 ? 1 : $i; ?>;
        $('#xeroom-add-more-category').click(function () {
            var geo_html = '<div class="xeroom-category-input-' + xcat + '">';
            geo_html += '<div><select class="xeroom-select" name="product_categories[]" style="width:165px">';
            geo_html += '<?php echo sprintf( '<option value="">%s</option>', __( 'Select product category', 'xeroom' ) ); ?>';
			<?php foreach ( $product_categories as $category ) {
			if ( 'uncategorized' === $category->slug ) {
				continue;
			}
			if ( 0 === $category->category_parent ) { ?>
            geo_html += '<?php echo sprintf( '<option value="%d">%s</option>', absint( $category->term_id ), esc_attr( $category->name ) );?>';
			<?php } } ?>
            geo_html += '</select></div>';

            // Add the dropdown for product account categories dynamically.
            geo_html += '<div><select class="xero_input" name="product_account_categories[]" id="product_account_categories">';

			<?php
			$xero_default_accounts = get_xero_option( 'xero_default_accounts' );
			if ( $xero_default_accounts ) {
			foreach ( $xero_default_accounts as $sale_account ) {
			if ( 'REVENUE' !== $sale_account['Type'] && 'SALES' !== $sale_account['Type'] ) {
				continue;
			}
			if ( ! isset( $sale_account['Code'] ) ) {
				continue;
			}
			?>
            geo_html += '<?php echo sprintf( '<option value="%s">%s</option>', $sale_account['Code'], $sale_account['Code'] . ' - ' . esc_attr( $sale_account['Name'] ) ); ?>';
			<?php
			}
			} else {
			echo sprintf( '<option value="%s">%s</option>', $account_value, $account_value );
		}
			?>
            geo_html += '</select></div>';

            geo_html += '<div><button type="button" name="add" id="xeroom-category-input-' + xcat + '" class="button button-secondary btn_remove"><?php echo __( 'Remove', 'xeroom' ); ?></button></div>';
            geo_html += '</div>';

            $('#product_categories_content').append(geo_html);
            xcat++;
        });

        $(document).on('click', '.btn_remove', function () {
            var button_id = $(this).attr("id");
            $('.' + button_id + '').remove();
        });
    });
</script>
