<?php
/**
 * Xeroom Debug Page template
 */
global $wpdb;

?>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.0/css/jquery.dataTables.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs/dt-1.11.0/datatables.min.css">
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.0/js/jquery.dataTables.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/v/dt/dt-1.11.0/datatables.min.js"></script>

<script>
    jQuery(document).ready(function($) {
        var datatablesajax = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
        var xeroomtable = $('#xeroom-table').DataTable({
            "ordering": false,
            "info":     false,
            ajax: {
                url: datatablesajax + '?action=get_debug_info'
            },
            columns: [
                { data: 'id' },
                { data: 'order_no' },
                { data: 'xero_no' },
                { data: 'message' },
                { data: 'date' }
            ]
        });
    });
</script>
<div class="xero_left xero_inputdiv">
    <div class="xeroom-logo"><img src="<?php echo XEROOM_HTTP_PATH; ?>images/logo/<?php if('lite' !== XEROOM_TYPE) {echo 'Xeroom_premium_logo.png';} else {echo 'xeroom.png';} ?>"></div>
    <div style="text-align: center; margin-top: 51px; width: 100%; margin-bottom: -40px;font-size: 24px;margin-left: 135px;">
    <h3><?php echo sprintf( '%s %s', __( 'Version' ), XEROOM_VERSION ); ?></h3></div>
    <div style="padding-top: 30px;">
    <?php
        // Check if the daily invoice limit for Orders has been reached
		$order_limit = get_option( 'xeroom_daily_invoice_limit', 50 );
		$order_count = get_transient( 'xeroom_daily_invoice_count' );

		$remaining_orders = $order_limit;
		if ( $order_count ) {
			$order_count      = ( false === $order_count ) ? 0 : $order_count;
			$remaining_orders = $order_limit - $order_count;
		}

		if ( ! can_post_invoice_to_xero() ) {
			/* translators: Notice message */
			$admin_notice_invoice_content = esc_html__( 'Number of Orders sent daily reached.' );
			/* translators: Notice HTML */
			printf( '<div>%s</div>', wp_kses_post( $admin_notice_invoice_content ) );
		} else {
			/* translators: Notice message */
			$admin_notice_invoice_content = sprintf(
				esc_html__( 'You can still send %d more Orders today.' ),
				$remaining_orders
			);
			/* translators: Notice HTML */
			printf( '<div>%s</div>', wp_kses_post( $admin_notice_invoice_content ) );
		}

		// Check if the daily invoice limit for Credit Notes has been reached
		$cn_limit = get_option( 'xeroom_daily_invoice_limit', 50 ); // Assuming you have a similar option for Credit Notes
		$cn_count = get_transient( 'xeroom_daily_cn_count' );

		$remaining_cn = $cn_limit;
		if ( $cn_count ) {
			$cn_count     = $cn_count === false ? 0 : $cn_count;
			$remaining_cn = $cn_limit - $cn_count;
		}
		
		if ( ! can_post_credit_note_to_xero() ) {
			/* translators: Notice message */
			$admin_cn_invoice_content = esc_html__( 'Number of Credit Notes sent daily reached.' );
			/* translators: Notice HTML */
			printf( '<div>%s</div>', wp_kses_post( $admin_cn_invoice_content ) );
		} else {
			/* translators: Notice message */
			$admin_cn_invoice_content = sprintf(
				esc_html__( 'You can still send %d more Credit Notes today.' ),
				$remaining_cn
			);
			/* translators: Notice HTML */
			printf( '<div>%s</div>', wp_kses_post( $admin_cn_invoice_content ) );
		}
        
        printf( '<div>%s</div>', wp_kses_post( esc_html('To increase daily limit go to settings.') ) );
    ?>
    </div>
</div>
<div class="xero_clear"></div>
<div class="content" id="showMyDeb">
    <table id="xeroom-table" width="100%">
        <thead>
        <tr role="row">
            <th><?php echo esc_html__('ID'); ?></th>
            <th><?php echo esc_html__('Order No.'); ?></th>
            <th><?php echo esc_html__('Xero No.'); ?></th>
            <th><?php echo esc_html__('Debug Message'); ?></th>
            <th><?php echo esc_html__('Created Date'); ?></th>
        </tr>
        </thead>
        <tbody></tbody>
    </table>

    <div class="clear"></div>
</div>
