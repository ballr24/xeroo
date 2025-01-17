<?php
$raw_payload = file_get_contents( "php://input" );
$webhook_key = get_xero_option( 'sync_invoice_data' );

$computed_signature_key = base64_encode(
	hash_hmac( 'sha256', $raw_payload, $webhook_key, true )
);

$xero_signature_key = $_SERVER['HTTP_X_XERO_SIGNATURE'];

$is_equal = false;

if ( hash_equals( $computed_signature_key, $xero_signature_key ) ) {
	$is_equal = true;
	http_response_code( 200 );
	$data = json_decode( $raw_payload );

	wp_schedule_single_event(
		time() + 10,
		'send_xero_api_call',
		[ 'data' => $data ]
	);
} else {
	http_response_code( 401 );
}
