<?php
/**
 * Log Settings Page Template
 */
global $wpdb;
error_reporting( E_ALL );
$apijqpath = esc_url( XEROOM_JS_PATH . 'latestjquery_min.js' );
$apijspath = esc_url( XEROOM_JS_PATH . 'xeroom_api_fields.js' );
wp_enqueue_script( 'xeroom_api_jquery', $apijqpath );
wp_enqueue_script( 'xeroom_api_fields', $apijspath );

if ( isset( $_REQUEST['upload_excel'] ) ) {
	$xeroomLogExport  = $wpdb->prefix . 'xeroom_export_log';
	$xeroomOrderArray = $wpdb->get_results( "SELECT * FROM $xeroomLogExport" );
	if ( count( $xeroomOrderArray ) > 0 ) {
		xeroomDownloadExportLogFile( $xeroomOrderArray );
		$wpdb->get_results( "TRUNCATE $xeroomLogExport" );
	}
	wp_redirect( admin_url() . 'admin.php?page=xeroom_log_woo_xero' );
	exit;
}

//$xeroomLogFilesArray = array();
//if ( is_dir( XEROOM_LOG_PATH ) ) {
//	if ( $xeroomLogFiles = opendir( XEROOM_LOG_PATH ) ) {
//		while ( ( $logFiles = readdir( $xeroomLogFiles ) ) !== false ) {
//			$xeroomLogFilesArray[] = $logFiles;
//		}
//		closedir( $xeroomLogFiles );
//	}
//	$xeroomLogFilesArray = array_diff( $xeroomLogFilesArray, array( '..', '.' ) );
//	natsort( $xeroomLogFilesArray );
//	arsort( $xeroomLogFilesArray );
//}

$xeroomLogFilesArray = array();

$dir = '';
if ( is_dir( XEROOM_LOG_PATH ) ) {
	$dir = new DirectoryIterator( XEROOM_LOG_PATH );
}

if ( ! empty( $dir ) ) {
	foreach ( $dir as $fileinfo ) {
		if ( ! in_array( $fileinfo, array( ".", ".." ) ) ) {
			$xeroomLogFilesArray[ $fileinfo->getMTime() ] = $fileinfo->getFilename();
		}
	}

	//krsort will sort in reverse order
	krsort( $xeroomLogFilesArray );
}

?>

<div class="xero_left xero_inputdiv">
    <div class="xeroom-logo"><img src="<?php echo XEROOM_HTTP_PATH; ?>images/logo/<?php if('lite' !== XEROOM_TYPE) {echo 'Xeroom_premium_logo.png';} else {echo 'xeroom.png';} ?>"></div>
    <div style="text-align: center; margin-top: 51px; width: 100%; margin-bottom: -40px;font-size: 24px;margin-left: 135px;">
            <h3><?php echo sprintf( '%s %s', __( 'Version' ), XEROOM_VERSION ); ?></h3></div>
</div>
<div class="xero_clear"></div>
<div class="content" id="showMyDeb">
    <table class="orderMain">
        <tr>
            <td class="orderBox" width="1">S. No</td>
            <td class="orderBox" width="226">Log File Name</td>
            <td class="orderBox" width="135">Created Date</td>
            <td class="orderBox" width="135">Action</td>
        </tr>
    </table>
    <table class="orderMain">
		<?php
		if ( count( $xeroomLogFilesArray ) > 0 ) {
			$sno = 1;
			foreach ( $xeroomLogFilesArray as $xeroomLogFileName ) {
				?>
                <tr>
                    <td class="resultBox" width="39"><?php echo $sno; ?></td>
                    <td class="resultBox" width="226">
                        <a href="<?php echo XEROOM_HTTP_PATH . 'library/log/' . $xeroomLogFileName; ?>"><?php echo $xeroomLogFileName; ?></a>
                    </td>
                    <td class="resultBox" width="135">
						<?php echo date( "F d Y H:i:s.", filemtime( XEROOM_LOG_PATH . $xeroomLogFileName ) ); ?>
                    </td>
                    <td class="resultBox" width="135">
                        <img src="<?php echo XEROOM_HTTP_PATH . 'images/delete.png'; ?>" onclick="return deleteImage('<?php echo $xeroomLogFileName; ?>');" class="delIcon" title="Delete">
                        <a href="<?php echo XEROOM_HTTP_PATH . 'library/log/' . $xeroomLogFileName; ?>">
                            <img src="<?php echo XEROOM_HTTP_PATH . 'images/download.png'; ?>" class="delIcon" title="Download">
                        </a>
                    </td>
                </tr>
				<?php $sno ++;
			}
		} else { ?>
            <tr>
                <td class="resultBox" width="100%" style="color:red">No Files Found!</td>
            </tr>
		<?php } ?>
    </table>
    <div class="clear"></div>
</div>
<script type="text/javascript">
    function deleteImage($xeroomFileName) {
        if (confirm('Are you sure wants to delete ' + $xeroomFileName + ' file.')) {
            var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
            var xeroomLogFile = 'fileName=' + $xeroomFileName;
            $.ajax({
                url: ajaxurl,
                type: "POST",
                cache: false,
                data: xeroomLogFile + '&action=unlinkxeroomlog',
                success: function () {
                    alert('Log file deleted successfully!');
                    document.location.href = document.location.href;
                }
            });
        }
    }
</script>