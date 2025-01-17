<?php
/**
 * Display Geography Zones Settings
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Get WooCommerce shipping zones
 */
$delivery_zones = WC_Shipping_Zones::get_zones();

if ( ! empty( $delivery_zones ) ) {
    $xero_default_accounts = get_xero_option( 'xero_default_accounts' );
	$associated_shipping_zones = get_xero_option( 'xero_associate_shipping_zones' );
	$selected_shipping         = $account_value = '';
	$x                         = 0;
	if ( $associated_shipping_zones ) {
		for ( $x = 0; $x < count( $associated_shipping_zones ); $x ++ ) {
			echo sprintf( '<div class="xeroom-zone-input-%d">', $x );
			echo '<div><select class="xeroom-select" name="xeroom_shipping_zone[]" style="width:165px">';
			echo sprintf( '<option value="">%s</option>', __( 'Select shipping zone', 'xeroom' ) );

			foreach ( (array) $delivery_zones as $key => $the_zone ) {
				$selected = isset( $_POST[ $x ]['xeroom_shipping_zone'] ) && absint( $the_zone['id'] ) == $_POST[ $x ]['xeroom_shipping_zone'] ? ' selected' : '';

				if ( xeroom_is_associated_shipping( $x, $the_zone['id'] ) ) {
					$selected          = ' selected';
					$selected_shipping = absint( $the_zone['id'] );
				}

				echo sprintf( '<option value="%d"%s>%s</option>', absint( $the_zone['id'] ), $selected, esc_attr( $the_zone['zone_name'] ) );
			}
			echo '</select></div>';

			// Replace the text input with a dropdown for shipping account associations
			echo '<div><select class="xero_input" name="xeroom_account_shipping_zone[]" id="xeroom_account_shipping_zone">';
			
			if ( $xero_default_accounts ) {
				foreach ( $xero_default_accounts as $account ) {
                    if ( 'REVENUE' !== $account['Type'] && 'SALES' !== $account['Type'] ) {
						continue;
					}

					if ( ! isset( $account['Code'] ) ) {
						continue;
					}
                    
					$selected = '';
					if ( isset($_POST['xeroom_account_shipping_zone'][$x]) && $account['Code'] == $_POST['xeroom_account_shipping_zone'][$x] ) {
						$selected = ' selected';
					}
					echo sprintf( '<option value="%s"%s>%s</option>', esc_attr( $account['Code'] ), $selected, esc_html( $account['Code'] . ' - ' . $account['Name'] ) );
				}
			} else {
				echo sprintf( '<option value="%s">%s</option>', esc_attr( $account_value ), esc_html( $account_value ) );
			}
			echo '</select></div>';

			if ( $x > 0 ) {
				echo sprintf( '<div><button type="button" name="add" id="xeroom-zone-input-%d" class="button button-secondary btn_remove">%s</button></div>', $x, __( 'Remove', 'xeroom' ) );
			} else {
				echo sprintf( '<div><button type="button" name="add" id="xeroom-add-more" class="button button-primary">%s</button></div>', __( 'Add More', 'xeroom' ) );
			}
			echo '</div>';
		}
	} else {
		$x = 0;
		echo sprintf( '<div class="xeroom-zone-input-%d">', $x );
		echo '<div><select class="xeroom-select" name="xeroom_shipping_zone[]" style="width:165px">';
		echo sprintf( '<option value="">%s</option>', __( 'Select shipping zone', 'xeroom' ) );

		foreach ( (array) $delivery_zones as $key => $the_zone ) {
			echo sprintf( '<option value="%d">%s</option>', absint( $the_zone['id'] ), esc_attr( $the_zone['zone_name'] ) );
		}
		echo '</select></div>';

		// Replace the text input with a dropdown for shipping account associations
		echo '<div><select class="xero_input" name="xeroom_account_shipping_zone[]" id="xeroom_account_shipping_zone">';
		
		if ( $xero_default_accounts ) {
			foreach ( $xero_default_accounts as $account ) {
                if ( 'REVENUE' !== $account['Type'] && 'SALES' !== $account['Type'] ) {
					continue;
				}

				if ( ! isset( $account['Code'] ) ) {
					continue;
				}

				$selected = '';
				if ( $account['Code'] == $salesAccount ) {
					$selected = ' selected';
				}
                
				$selected = '';
				echo sprintf( '<option value="%s"%s>%s</option>', esc_attr( $account['Code'] ), $selected, esc_html( $account['Code'] . ' - ' . $account['Name'] ) );
			}
		} else {
			echo sprintf( '<option value="%s">%s</option>', esc_attr( $account_value ), esc_html( $account_value ) );
		}
		echo '</select></div>';

		echo sprintf( '<div><button type="button" name="add" id="xeroom-add-more" class="button button-primary">%s</button></div>', __( 'Add More', 'xeroom' ) );
		echo '</div>';
	}
} else {
	echo __( 'There are no Shipping Zones defined', 'xeroom' );
}
?>
<script>
    jQuery(document).ready(function ($) {
        var x = <?php echo $x == 0 ? 1 : $x; ?>;
        $('#xeroom-add-more').click(function () {
            var geo_html = '<div class="xeroom-zone-input-' + x + '">';
            geo_html += '<div><select class="xeroom-select" name="xeroom_shipping_zone[]" style="width:165px">';
            geo_html += '<?php echo sprintf( '<option value="">%s</option>', __( 'Select shipping zone', 'xeroom' ) ); ?>';
            <?php foreach ( (array) $delivery_zones as $key => $the_zone ) { ?>
            geo_html += '<?php echo sprintf( '<option value="%d">%s</option>', absint( $the_zone['id'] ), esc_attr( $the_zone['zone_name'] ) );?>';
            <?php } ?>
            geo_html += '</select></div>';

            // Add the dropdown for shipping account associations dynamically
            geo_html += '<div><select class="xero_input" name="xeroom_account_shipping_zone[]" id="xeroom_account_shipping_zone">';
            <?php 
            $xero_default_accounts = get_xero_option( 'xero_default_accounts' );
            if ( $xero_default_accounts ) {
                foreach ( $xero_default_accounts as $account ) {
                    if ( 'REVENUE' !== $account['Type'] && 'SALES' !== $account['Type'] ) {
					continue;
				}

				if ( ! isset( $account['Code'] ) ) {
					continue;
				}
            ?>
            geo_html += '<?php echo sprintf( '<option value="%s">%s</option>', esc_attr( $account['Code'] ), esc_html( $account['Code'] . ' - ' . $account['Name'] ) ); ?>';
            <?php
                }
            } else {
                echo sprintf( '<option value="%s">%s</option>', esc_attr( $account_value ), esc_html( $account_value ) );
            }
            ?>
            geo_html += '</select></div>';

            geo_html += '<div><button type="button" name="add" id="xeroom-zone-input-' + x + '" class="button button-secondary btn_remove"><?php echo __( 'Remove', 'xeroom' ); ?></button></div>';
            geo_html += '</div>';

            $('#geography_zones_content').append(geo_html);
            x++;
        });

        $(document).on('click', '.btn_remove', function () {
            var button_id = $(this).attr("id");
            $('.' + button_id + '').remove();
        });
    });
</script>

