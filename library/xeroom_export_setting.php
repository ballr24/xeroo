<?php
/**
 * Expor Settings Template
 */
global $wpdb;
$message            = "";
$allowed_extensions = array( 'xlsx' );
$exportOrderArray   = array();

$apijqpath = esc_url( XEROOM_JS_PATH . 'latestjquery_min.js' );
$apijspath = esc_url( XEROOM_JS_PATH . 'xeroom_api_fields.js' );

wp_enqueue_script( 'xeroom_api_jquery', $apijqpath );
wp_enqueue_script( 'xeroom_api_fields', $apijspath );

$xeroomJsOrderId = array();
/**
 * Export in xls file
 */
if ( sanitize_text_field( isset( $_POST['zeroom_export_orders'] ) ) && sanitize_text_field( $_POST['zeroom_export_orders'] == "export" ) ) {

	$xeroomOrderStatus = 'Paid' == $_POST['xeroom_payment_type'] ? array( 'completed' ) : array( 'on-hold', 'processing', 'pending' );

	$batch_size = ! empty( $_REQUEST['xeroom_export_batch_size'] ) ? $_REQUEST['xeroom_export_batch_size'] : 100;

	$xeroomJsOrderId = wc_get_orders(
		array(
			'limit'   => absint( $batch_size ),
			'status'  => $xeroomOrderStatus,
			'orderby' => 'ID',
			'order'   => 'ASC',
			'return'  => 'ids',
		)
	);

	$xeroomPaymentStatus = sanitize_text_field( $_POST['xeroom_payment_type'] );
	$xeroomIgnoreStatus  = sanitize_text_field( $_POST['xeroom_ignore_type'] );
	if ( sanitize_text_field( isset( $_POST['xeroom_export_type'] ) ) && sanitize_text_field( $_POST['xeroom_export_type'] ) == "downloadExcel" ) {
		if ( count( $xeroomJsOrderId ) > 0 ) {
			xeroomDownloadExportOrders( $xeroomJsOrderId, $xeroomPaymentStatus, $xeroomIgnoreStatus );
		} else {
			echo "No order exist in wooCommerce.";
		}
	}
}

if ( sanitize_text_field( isset( $_POST['xeroom_upload_file'] ) ) && sanitize_text_field( $_POST['xeroom_upload_file'] == "yes" ) ) {
	if ( ! empty( $_FILES['file'] ) ) {
		if ( $_FILES['file']['error'] == 0 ) {
			$file      = explode( ".", $_FILES['file']['name'] );
			$extension = array_pop( $file );
			if ( in_array( $extension, $allowed_extensions ) ) {
				if ( move_uploaded_file( $_FILES['file']['tmp_name'], XEROOM_ROOT_PATH . 'library/excel/upload_this_data.xlsx' ) ) {
					echo "<script>alert('Successfully uploaded your file & now click to send on xero button.')</script>";
				} else {
					echo "<script>alert('File not uploaded, due to some error.')</script>";
				}
			} else {
				echo "<script>alert('Only excel file format is allowed.')</script>";
			}
		} else {
			echo "<script>alert('There was a problem with your file.')</script>";
		}
	}
}
$exportOrderArray = array();
if ( is_dir( XEROOM_EXCEL_PATH ) ) {
	if ( $xeroomExcelFiles = opendir( XEROOM_EXCEL_PATH ) ) {
		while ( ( $logFiles = readdir( $xeroomExcelFiles ) ) !== false ) {
			$xeroomExcelFilesArray[] = $logFiles;
		}
		closedir( $xeroomExcelFiles );
	}
	$xeroomExcelFilesArray = array_diff( $xeroomExcelFilesArray, array( '..', '.' ) );
	if ( in_array( 'upload_this_data.xlsx', $xeroomExcelFilesArray ) ) {
		$fileName   = XEROOM_ROOT_PATH . 'library/excel/upload_this_data.xlsx';
		$xlsx       = new XLSXReader( $fileName );
		$sheetNames = $xlsx->getSheetNames();
		foreach ( $sheetNames as $sheetName ) {
			$sheet            = $xlsx->getSheet( $sheetName );
			$exportOrderArray = $sheet->getData();
		}
		unset( $exportOrderArray[0] );
		$exportOrderArray = array_values( $exportOrderArray );
	}
}
?>
<div class="common_order_xeroom">
    <div id="overlay"></div>
    <div id="xeroomCounter">Export <span id="orderCounter">0</span> orders out of <span id="totalOutSpin"><?php echo count( $xeroomJsOrderId ); ?></span></div>
    <div class="loader"><img src="<?php echo XEROOM_HTTP_PATH; ?>images/ajax-loader.gif"></div>
</div>
<div class="xero_left xero_inputdiv">
    <div class="xeroom-logo" style="height: 75px;"><img src="<?php echo XEROOM_HTTP_PATH; ?>images/logo/<?php if('lite' !== XEROOM_TYPE) {echo 'Xeroom_premium_logo.png';} else {echo 'xeroom.png';} ?>"></div>
    <div class="xero_clear"></div>
    <div style="text-align: center; margin-top: 51px; width: 100%; margin-bottom: -40px;font-size: 24px;margin-left: 135px;">
            <h3><?php echo sprintf( '%s %s', __( 'Version' ), XEROOM_VERSION ); ?></h3></div>
    <div class="xero_clear"></div>
    <span class="xero_heading" style="margin-left:0!important;"><?php echo $message; ?></span>
    <div class="clear"></div>
</div>
<div class="xero_clear"></div>
<div class="content">
    <div class="clear"></div>
    <h3>Export All Historic Orders To Xero</h3>
    <div class="xero_clear"></div>
    <div class="xero_license">
        <form method="post" name="xeroomExportOrderToXero" id="xeroomExportOrderToXero" action="">
            <div class="xero_clear"></div>
            <div class="xero_left xero_label">Export Order List Source</div>
            <div class="xero_left xero_inputdiv" style=" margin-top: 8px;">
                <input type="radio" value="downloadExcel" class="xero_input" name="xeroom_export_type" id="xeroom_export_type" checked="checked">Dowload Excel SS of all orders for selection and upload below</input>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <input type="radio" value="sendToXero" class="xero_input" name="xeroom_export_type" id="xeroom_export_type">Export All Orders To Xero</input>
            </div>
            <div class="xero_clear"></div>
            <div class="xero_left xero_label">Xero Invoices Status</div>
            <div class="xero_left xero_inputdiv" style=" margin-top: 8px;">
                <input type="hidden" name="zeroom_export_orders" value="export">
                <input type="radio" value="Unpaid" class="xero_input" id="xeroom_payment_type" name="xeroom_payment_type" checked="checked">Unpaid</input>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <input type="radio" value="Paid" class="xero_input" id="xeroom_payment_type" name="xeroom_payment_type">Paid</input>
                <br>
                <div style="color: red; margin-left: 314px;">Warning: Paid invoices cannot be deleted in xero</div>
            </div>
            <div class="xero_clear"></div>
            <div class="xero_left xero_label">Exported Ignore Status</div>
            <div class="xero_left xero_inputdiv" style=" margin-top: 8px;">
                <input type="radio" value="Yes repeat" class="xero_input" id="xeroom_ignore_type" name="xeroom_ignore_type" checked="checked">Repeat Export</input>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <input type="radio" value="No repeat" class="xero_input" id="xeroom_ignore_type" name="xeroom_ignore_type">Ignore If Previously Exported</input>
            </div>
            <div class="xero_clear"></div>
            <div class="xero_left xero_label">Batch size for sync</div>
            <div class="xero_left xero_inputdiv" style=" margin-top: 8px;">
                <input type="number" value="100" name="xeroom_export_batch_size" id="xeroom_export_batch_size"/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            </div>
            <div class="xero_clear"></div>
            <br/>
            <div class="button-primary xero-primery" id="xeroom-export-all" onclick="zeroom_check_order_data();">Run Job</div>
        </form>
    </div>
</div>
<div class="xero_clear"></div>
<div class="content" style="margin-top: 30px;">
    <div class="xero_clear"></div>
    <h3>Export Selected Historic Orders To Xero</h3>
    <div class="xero_clear"></div>
    <div class="xero_license">
        <form action="" method="post" enctype="multipart/form-data" name="zeroom_upload_excel" id="zeroom_upload_excel">
            <div class="xero_clear"></div>
            <div class="xero_left xero_label">Upload xlsx File</div>
            <div class="xero_left xero_inputdiv" style=" margin-top: 8px;">
                <input type="file" name="file" id="file"
                       style="border: 1px solid rgb(0, 133, 186); background: rgb(0, 133, 186) none repeat scroll 0% 0%; border-radius: 5px; color: rgb(255, 255, 255);"/>
            </div>
			<?php if ( count( $exportOrderArray ) > 0 ) { ?>
                <div class="xero_left xero_inputdiv" style=" margin-top: 8px;">
                    <input id="exportsendtoxero" type="button" style="background:red none repeat scroll 0 0;border:1px solid red;border-radius:5px;color:#fff;height:34px;cursor:pointer;"
                           name="senttoxero" value="CLIK HERE TO EXPORT ORDERS TO XERO" onClick="xlsxSendToXeroNow();">
                </div>
			<?php } ?>
            <input type="hidden" name="xeroom_upload_file" value="yes">
            <div class="xero_clear"></div>
            <br/>
            <div class="button-primary xero-primery" onClick="return zeroom_check_xls_upload();">Upload</div>
        </form>
    </div>
</div>
<script type="text/javascript">
    //$ordersArray = <?php //echo json_encode( $xeroomJsOrderId ); ?>//;
    $xlsxOrdersArray = <?php echo json_encode( $exportOrderArray ); ?>;
    $spinCounter = 0;
    $xlsxSpinCounter = 0;
    $check = true;
    $xlsxCheck = true;

    function xlsxSendToXeroNow() {
        $('.common_order_xeroom').css('display', 'block');
        $('#orderCounter').html(0);
        $('#totalOutSpin').html(<?php echo count( $exportOrderArray ); ?>);
        xlsxSendToXero(0);
    }

    function zeroom_check_order_data() {
        $selectedType = $('input[name=xeroom_export_type]:checked').val();
        $paymentType = $('input[name=xeroom_payment_type]:checked').val();
        $ignoreType = $('input[name=xeroom_ignore_type]:checked').val();
        $orders_size = $('input[name=xeroom_export_batch_size]').val();

        if ($selectedType == 'downloadExcel') {
            document.xeroomExportOrderToXero.submit();
        } else if ($selectedType == 'sendToXero') {
            $.ajax({
                type: "post",
                url: ajaxurl,
                data: {
                    'action': 'get_orders_for_xero',
                    'orders_no': $orders_size,
                    'payment': $paymentType,
                    'ignore': $ignoreType
                },
                success: function (response) {
                    $('.common_order_xeroom').css('display', 'block');
                    $('#orderCounter').html(0);
                    $('#totalOutSpin').html($orders_size);
                    // xeroomSendToXero($.parseJSON(response), 0);
                    setTimeout(function () {
                        xeroomSendToXero($.parseJSON(response), 0);
                    }, 5000);
                }
            });
        } else {
            alert('Something went wrong, Please contact with administrator!');
            return false;
        }
    }

    function xeroomSendToXero(orders, $cOI) {
        if ($check == true) {
            $check = false;
            $spinCounter++;
            $xeroomLogFile = 'orderId=' + orders[$cOI] + '&ignore=' + $ignoreType + '&payment=' + $paymentType + '&spinTime=' + $spinCounter;
            $.ajax({
                type: "post",
                url: ajaxurl,
                data: $xeroomLogFile + '&action=exportDataWooToXero',
                success: function (response) {
                    $('#orderCounter').html($spinCounter);
                    $check = true;
                    if ($spinCounter < orders.length) {
                        setTimeout(function () {
                            xeroomSendToXero(orders, $spinCounter)
                        }, 5000);
                    } else {
                        $('.common_order_xeroom').css('display', 'none');
                        alert('Historic dataload complete.  Please check the log file for the xero status of each order.');
                        $wpAdminUrl = "<?php echo admin_url() . 'admin.php?page=xeroom_log_woo_xero&upload_excel'; ?>";
                        document.location.href = $wpAdminUrl;
                    }
                }
            });
        }
    }

    function xlsxSendToXero($cOI) {
        if ($xlsxCheck == true) {
            $xlsxCheck = false;
            $xlsxSpinCounter++;
            $xeroomLogFile = 'orderId=' + $xlsxOrdersArray[$cOI][2] + '&ignore=' + $xlsxOrdersArray[$cOI][4] + '&payment=' + $xlsxOrdersArray[$cOI][3] + '&spinTime=' + $xlsxSpinCounter;
            $.ajax({
                type: "post",
                url: ajaxurl,
                data: $xeroomLogFile + '&action=exportDataWooToXero',
                success: function (response) {
                    $('#orderCounter').html($xlsxSpinCounter);
                    $xlsxCheck = true;
                    if ($xlsxSpinCounter < $xlsxOrdersArray.length) {
                        setTimeout(function () {
                            xlsxSendToXero($xlsxSpinCounter)
                        }, 5000);
                    } else {
                        $.ajax({
                            url: ajaxurl,
                            type: "post",
                            async: false,
                            data: 'action=unlinkxeroomexcel',
                            success: function () {
                                $('.common_order_xeroom').css('display', 'none');
                                alert('Historic dataload complete.  Please check the log file for the xero status of each order.');
                                $wpAdminUrl = "<?php echo admin_url() . 'admin.php?page=xeroom_log_woo_xero&upload_excel'; ?>";
                                document.location.href = $wpAdminUrl;
                            }
                        });
                    }
                }
            });
        }
    }
</script>