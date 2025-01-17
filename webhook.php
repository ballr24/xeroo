<?php
// Based on Xero developer document from
// https://developer.xero.com/documentation/webhooks/overview
// Returning data in the response body to Xero will cause the webhook
// verification to fail, to get around this for testing store all the
//information we needed into a text file to helps us debug any issues.

// ----------------------------------------------------------------------------

// The payload in webhook MUST be read as raw data format,
// even thought the webhook is sent with the
// Content-Type header with 'application/json'.
//
// Otherwise any preprocess payload could be lead to incorrectly
// computed signature key.

// Get payload
$rawPayload = file_get_contents('php://input');

// ------------------------------------
// Compute hashed signature key with our webhook key

// Update your webhooks key here
$webhookKey = '4CT0s7AJsRkTLcO9gH43PxRDUHppYz+cY+tBsyPqBJsOuaqayP04FN7+JqeiOkK/sTgfxRHZF7vjPQJln9g/ZA==';

// Compute the payload with HMACSHA256 with base64 encoding
$computedSignatureKey = base64_encode(
	hash_hmac('sha256', $rawPayload, $webhookKey, true)
);

// Signature key from Xero request
$xeroSignatureKey = $_SERVER['HTTP_X_XERO_SIGNATURE'];

// Response HTTP status code when:
//   200: Correctly signed payload
//   401: Incorrectly signed payload
$isEqual = false;
if (hash_equals($computedSignatureKey, $xeroSignatureKey)) {
	$isEqual = true;
	http_response_code(200);
} else {
	http_response_code(401);
}
