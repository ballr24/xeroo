<?php

if ( ! class_exists( 'Xero' ) ) {
	/**
	 * Class Xero
	 *
	 * Xero library
	 */
	class Xero {
		const ENDPOINT = 'https://api.xero.com/api.xro/2.0/';
		private $key;
		private $secret;
		private $public_cert;
		private $private_key;
		private $consumer;
		private $token;
		private $signature_method;
		private $format;
		private $xero_oauth2;

		/**
		 * Xero constructor.
		 *
		 * @param bool $key
		 * @param bool $secret
		 * @param bool $public_cert
		 * @param bool $private_key
		 * @param string $format
		 *
		 * @throws XeroException
		 * @throws Exception
		 */
		public function __construct( $key = false, $secret = false, $public_cert = false, $private_key = false, $format = 'json', $oauth2 = false ) {
			$this->key         = $key;
			$this->secret      = $secret;
			$this->public_cert = $public_cert;
			$this->private_key = $private_key;
			$this->xero_oauth2 = $oauth2;
			if ( ! ( $this->key ) || ! ( $this->secret ) || ! ( $this->public_cert ) || ! ( $this->private_key ) ) {
				return false;
			}
			if ( ! file_exists( $this->public_cert ) ) {
				throw new XeroException( 'Public cert does not exist: ' . $this->public_cert );
			}
			if ( ! file_exists( $this->private_key ) ) {
				throw new XeroException( 'Private key does not exist: ' . $this->private_key );
			}

			if ( ! $this->xero_oauth2 ) {
				$this->consumer = new OAuthConsumerXeroom( $this->key, $this->secret );
				$this->token    = new OAuthTokenXeroom( $this->key, $this->secret );
			}

			$this->signature_method = new OAuthSignatureMethod_Xero( $this->public_cert, $this->private_key );
			$this->format           = ( in_array( $format, array( 'xml', 'json' ) ) ) ? $format : 'json';
		}

		/**
		 * @param $name
		 * @param $arguments
		 *
		 * @return array|bool|SimpleXMLElement
		 * @throws XeroException
		 */
		public function __call( $name, $arguments ) {
			$name = strtolower( $name );

			$valid_methods      = array(
				'accounts',
				'account',
				'contacts',
				'creditnotes',
				'currencies',
				'invoices',
				'organisation',
				'payments',
				'taxrates',
				'taxrate',
				'trackingcategories',
				'items',
				'item',
				'banktransactions',
				'brandingthemes',
				'receipts',
				'expenseclaims'
			);
			$valid_post_methods = array( 'banktransactions', 'contacts', 'creditnotes', 'expenseclaims', 'invoices', 'items', 'item', 'manualjournals', 'receipts', 'taxrates' );
			$valid_put_methods  = array( 'payments', 'accounts', 'account', 'taxrates', 'taxrate', 'item', 'currencies' );
			$valid_get_methods  = array(
				'accounts',
				'banktransactions',
				'brandingthemes',
				'contacts',
				'creditnotes',
				'currencies',
				'employees',
				'expenseclaims',
				'invoices',
				'items',
				'item',
				'journals',
				'manualjournals',
				'organisation',
				'payments',
				'receipts',
				'taxrates',
				'trackingcategories',
				'users'
			);
			$methods_map        = array(
				'accounts'           => 'Accounts',
				'banktransactions'   => 'BankTransactions',
				'brandingthemes'     => 'BrandingThemes',
				'contacts'           => 'Contacts',
				'creditnotes'        => 'CreditNotes',
				'currencies'         => 'Currencies',
				'employees'          => 'Employees',
				'expenseclaims'      => 'ExpenseClaims',
				'invoices'           => 'Invoices',
				'items'              => 'Items',
				'journals'           => 'Journals',
				'manualjournals'     => 'ManualJournals',
				'organisation'       => 'Organisation',
				'payments'           => 'Payments',
				'receipts'           => 'Receipts',
				'taxrates'           => 'TaxRates',
				'trackingcategories' => 'TrackingCategories',
				'users'              => 'Users'
			);
			if ( ! in_array( $name, $valid_methods ) ) {
				throw new XeroException( 'The selected method does not exist. Please use one of the following methods: ' . implode( ', ', $methods_map ) );
			}

			if ( ( count( $arguments ) == 0 ) || ( is_string( $arguments[0] ) ) || ( is_numeric( $arguments[0] ) ) || ( $arguments[0] === false ) ) {
				if ( ! in_array( $name, $valid_get_methods ) ) {
					return false;
				}
				$filterid = ( count( $arguments ) > 0 ) ? strip_tags( strval( $arguments[0] ) ) : false;
				if ( ! empty( $arguments ) && isset( $arguments[1] ) ) {
					$modified_after = ( count( $arguments ) > 1 ) ? str_replace( 'X', 'T', date( 'Y-m-dXH:i:s', strtotime( $arguments[1] ) ) ) : false;
				}
				if ( ! empty( $arguments ) && isset( $arguments[2] ) ) {
					$where = ( count( $arguments ) > 2 ) ? $arguments[2] : false;
				}
				if ( isset( $where ) && is_array( $where ) && ( count( $where ) > 0 ) ) {
					$temp_where = '';
					foreach ( $where as $wf => $wv ) {
						if ( is_bool( $wv ) ) {
							$wv = ( $wv ) ? "%3d%3dtrue" : "%3d%3dfalse";
						} else if ( is_array( $wv ) ) {
							if ( is_bool( $wv[1] ) ) {
								$wv = ( $wv[1] ) ? rawurlencode( $wv[0] ) . "true" : rawurlencode( $wv[0] ) . "false";
							} else {
								$wv = rawurlencode( $wv[0] ) . "%22{$wv[1]}%22";
							}
						} else {
							$wv = "%3d%3d%22$wv%22";
						}
						$temp_where .= "%26%26$wf$wv";
					}
					$where = strip_tags( substr( $temp_where, 6 ) );
				} elseif ( isset( $where ) ) {
					$where = strip_tags( strval( $where ) );
				}
				$order        = ( count( $arguments ) > 3 ) ? strip_tags( strval( $arguments[3] ) ) : false;
				$acceptHeader = ( ! empty( $arguments[4] ) ) ? $arguments[4] : '';
				$method       = $methods_map[ $name ];
				$xero_url     = self::ENDPOINT . $method;
				if ( $filterid ) {
					$xero_url .= "/$filterid";
				}
				if ( isset( $where ) ) {
					$xero_url .= "?where=$where";
				}
				if ( $order ) {
					$xero_url .= "&order=$order";
				}

				if ( $this->xero_oauth2 ) {
					$request_url = $xero_url;
				} else {
					$req = OAuthRequestXeroom::from_consumer_and_token( $this->consumer, $this->token, 'GET', $xero_url );
					$req->sign_request( $this->signature_method, $this->consumer, $this->token );
					$request_url = $req->to_url();
				}

				$ch = curl_init();
				if ( $acceptHeader == 'pdf' ) {
					curl_setopt( $ch, CURLOPT_HTTPHEADER,
						array(
							"Accept: application/" . $acceptHeader
						)
					);
				}

				if ( $this->xero_oauth2 ) {
					$now = time();
					if ( null !== $this->xero_oauth2['expires'] && ( $now < $this->xero_oauth2['expires'] ) ) {
						$the_token = $this->xero_oauth2['token'];
					} else {
						$the_token = xeroom_refresh_token();
					}

					$headers = [
						'Authorization: Bearer ' . $the_token,
						'Xero-tenant-id: ' . $this->xero_oauth2['tenant_id'],
                        'User-Agent: Xero',
					];
					curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
				}

				curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
				curl_setopt( $ch, CURLOPT_URL, $request_url );
				if ( isset( $modified_after ) && $modified_after != false ) {
					curl_setopt( $ch, CURLOPT_HTTPHEADER, array( "If-Modified-Since: $modified_after" ) );
				}

				// TLS 1.2
				curl_setopt( $ch, CURLOPT_SSLVERSION, 6 );

				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
				$temp_xero_response = curl_exec( $ch );

				curl_close( $ch );

				if ( $acceptHeader == 'pdf' ) {
					return $temp_xero_response;
				}
				try {
					if ( @simplexml_load_string( $temp_xero_response ) == false ) {
						throw new XeroException( $temp_xero_response );
						$xero_xml = false;
					} else {
						$xero_xml = simplexml_load_string( $temp_xero_response );
					}
				} catch ( XeroException $e ) {
					return $e->getMessage() . "<br/>";
				}
				if ( $this->format == 'xml' && isset( $xero_xml ) ) {
					return $xero_xml;
				} elseif ( isset( $xero_xml ) ) {
					return ArrayToXML::toArray( $xero_xml );
				}
			} elseif ( ( count( $arguments ) == 1 ) || ( is_array( $arguments[0] ) ) || ( is_a( $arguments[0], 'SimpleXMLElement' ) ) ) {
				if ( ! ( in_array( $name, $valid_post_methods ) || in_array( $name, $valid_put_methods ) ) ) {
					return false;
				}
				$method = $methods_map[ $name ];

				// Used for Credit Note Allocate
				$do_put = false;
				if ( ! empty( $arguments[1] ) ) {
					if ( array_key_exists( 'Allocations', $arguments[1] ) || array_key_exists( 'TaxRates', $arguments[1] ) ) {
						$do_put = true;
					}
				}
				// Used for Credit Note Allocate
				if ( $do_put ) {
					$method = 'Allocations';
				}

				if ( is_a( $arguments[0], 'SimpleXMLElement' ) ) {
					$post_body = $arguments[0]->asXML();
				} elseif ( is_array( $arguments[0] ) ) {
					$post_body = ArrayToXML::toXML( $arguments[0], $rootNodeName = $method );
				}

				// Used for Credit Note Allocate
				if ( $do_put ) {
					$method = 'CreditNotes/' . $arguments[1]['Allocations'] . '/Allocations';
				}

				if ( ! empty( $arguments[1] ) ) {
					if ( array_key_exists( 'TaxRate', $arguments[1] ) ) {
						$do_put = false;
					}
				}

				if ( ! empty( $arguments[1] ) ) {
					if ( array_key_exists( 'InvoiceResend', $arguments[1] ) ) {
						$do_put = true;
					}
				}

				$post_body = trim( substr( $post_body, ( stripos( $post_body, ">" ) + 1 ) ) );
				if ( in_array( $name, $valid_post_methods ) && ! $do_put ) {
					$xero_url = self::ENDPOINT . $method;

					if ( ! $this->xero_oauth2 ) {
						$req = OAuthRequestXeroom::from_consumer_and_token( $this->consumer, $this->token, 'POST', $xero_url, array( 'xml' => $post_body ) );
						$req->sign_request( $this->signature_method, $this->consumer, $this->token );
					}

					$ch = curl_init();
					curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
					curl_setopt( $ch, CURLOPT_URL, $xero_url );
					curl_setopt( $ch, CURLOPT_POST, true );

					if ( $this->xero_oauth2 ) {
						$now = time();
						if ( null !== $this->xero_oauth2['expires'] && ( $now < $this->xero_oauth2['expires'] ) ) {
							$the_token = $this->xero_oauth2['token'];
						} else {
							$the_token = xeroom_refresh_token();
						}

						$headers = [
							'Authorization: Bearer ' . $the_token,
							'Xero-tenant-id: ' . $this->xero_oauth2['tenant_id'],
                            'User-Agent: Xero',
						];
						curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
						curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_body );
					} else {
						curl_setopt( $ch, CURLOPT_HEADER, $req->to_header() );
						curl_setopt( $ch, CURLOPT_POSTFIELDS, $req->to_postdata() );
					}
				} else {
					$xero_url = self::ENDPOINT . $method;

					if ( $this->xero_oauth2 ) {
						$request_url = $xero_url;
					} else {
						$req = OAuthRequestXeroom::from_consumer_and_token( $this->consumer, $this->token, 'PUT', $xero_url );
						$req->sign_request( $this->signature_method, $this->consumer, $this->token );
						$request_url = $req->to_url();
					}

					$xml = $post_body;
					$fh  = fopen( 'php://temp', 'rw+' );
					fwrite( $fh, $xml );
					rewind( $fh );
					$ch = curl_init( $request_url );
					curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
					curl_setopt( $ch, CURLOPT_PUT, true );
					curl_setopt( $ch, CURLOPT_INFILE, $fh );
					curl_setopt( $ch, CURLOPT_INFILESIZE, strlen( $xml ) );

					if ( $this->xero_oauth2 ) {
						$now = time();
						if ( null !== $this->xero_oauth2['expires'] && ( $now < $this->xero_oauth2['expires'] ) ) {
							$the_token = $this->xero_oauth2['token'];
						} else {
							$the_token = xeroom_refresh_token();
						}

						$headers = [
							'Authorization: Bearer ' . $the_token,
							'Xero-tenant-id: ' . $this->xero_oauth2['tenant_id'],
                            'User-Agent: Xero',
						];
						curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
					}
				}
				// TLS 1.2
				curl_setopt( $ch, CURLOPT_SSLVERSION, 6 );

				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
				curl_setopt( $ch, CURLOPT_TIMEOUT, 120 );
				$xero_response = curl_exec( $ch );

				if ( isset( $fh ) ) {
					fclose( $fh );
				}
				try {
					if ( @simplexml_load_string( $xero_response ) == false ) {
						throw new XeroException( $xero_response );

					} else {
						$xero_xml = simplexml_load_string( $xero_response );
					}
				} catch ( XeroException $e ) {
					return $e->getMessage();
//				return $e->getMessage() . "<br/>";
				}
				curl_close( $ch );
				if ( ! isset( $xero_xml ) ) {
					return false;
				}
				if ( $this->format == 'xml' && isset( $xero_xml ) ) {
					return $xero_xml;
				} elseif ( isset( $xero_xml ) ) {
					return ArrayToXML::toArray( $xero_xml );
				}
			} else {
				return false;
			}
		}

		public function __get( $name ) {
			return $this->$name();
		}
	}
}

if ( ! class_exists( 'OAuthExceptionXeroom' ) ) {
	/**
	 * Class OAuthExceptionXeroom
	 */
	class OAuthExceptionXeroom extends Exception {
	}
}


if ( ! class_exists( 'OAuthConsumerXeroom' ) ) {
	/**
	 * Class OAuthConsumerXeroom
	 */
	class OAuthConsumerXeroom {
		public $key;
		public $secret;

		/**
		 * OAuthConsumerXeroom constructor.
		 *
		 * @param $key
		 * @param $secret
		 * @param null $callback_url
		 */
		function __construct( $key, $secret, $callback_url = null ) {
			$this->key          = $key;
			$this->secret       = $secret;
			$this->callback_url = $callback_url;
		}

		/**
		 * @return string
		 */
		function __toString() {
			return "OAuthConsumerXeroom[key=$this->key,secret=$this->secret]";
		}
	}
}


if ( ! class_exists( 'OAuthTokenXeroom' ) ) {
	/**
	 * Class OAuthTokenXeroom
	 */
	class OAuthTokenXeroom {
		public $key;
		public $secret;

		/**
		 * OAuthTokenXeroom constructor.
		 *
		 * @param $key
		 * @param $secret
		 */
		function __construct( $key, $secret ) {
			$this->key    = $key;
			$this->secret = $secret;
		}

		/**
		 * @return string
		 */
		function to_string() {
			return "oauth_token=" .
			       OAuthUtilXeroom::urlencode_rfc3986( $this->key ) .
			       "&oauth_token_secret=" .
			       OAuthUtilXeroom::urlencode_rfc3986( $this->secret );
		}

		/**
		 * @return string
		 */
		function __toString() {
			return $this->to_string();
		}
	}
}

if ( ! class_exists( 'XeroomOAuthSignatureMethodXeroom' ) ) {
	/**
	 * Class OAuthSignatureMethod
	 */
	abstract class XeroomOAuthSignatureMethodXeroom{
		/**
		 * @return mixed
		 */
		abstract public function get_name();

		/**
		 * @param $request
		 * @param $consumer
		 * @param $token
		 *
		 * @return mixed
		 */
		abstract public function build_signature( $request, $consumer, $token );

		/**
		 * @param $request
		 * @param $consumer
		 * @param $token
		 * @param $signature
		 *
		 * @return bool
		 */
		public function check_signature( $request, $consumer, $token, $signature ) {
			$built = $this->build_signature( $request, $consumer, $token );

			return $built == $signature;
		}
	}
}

if ( ! class_exists( 'OAuthSignatureMethod_HMAC_SHA1_Xeroom' ) ) {
	/**
	 * Class OAuthSignatureMethod_HMAC_SHA1_Xeroom
	 */
	class OAuthSignatureMethod_HMAC_SHA1_Xeroom extends XeroomOAuthSignatureMethodXeroom{
		/**
		 * @return mixed|string
		 */
		function get_name() {
			return "HMAC-SHA1";
		}

		/**
		 * @param OAuthRequestXeroom $request
		 * @param OAuthConsumerXeroom $consumer
		 * @param OAuthTokenXeroom $token
		 *
		 * @return mixed|string
		 */
		public function build_signature( $request, $consumer, $token ) {
			$base_string          = $request->get_signature_base_string();
			$request->base_string = $base_string;
			$key_parts            = array(
				$consumer->secret,
				( $token ) ? $token->secret : ""
			);
			$key_parts            = OAuthUtilXeroom::urlencode_rfc3986( $key_parts );
			$key                  = implode( '&', $key_parts );

			return base64_encode( hash_hmac( 'sha1', $base_string, $key, true ) );
		}
	}
}

if ( ! class_exists( 'OAuthSignatureMethod_PLAINTEXT_Xeroom' ) ) {
	/**
	 * Class OAuthSignatureMethod_PLAINTEXT_Xeroom
	 */
	class OAuthSignatureMethod_PLAINTEXT_Xeroom extends XeroomOAuthSignatureMethodXeroom{
		/**
		 * @return mixed|string
		 */
		public function get_name() {
			return "PLAINTEXT";
		}

		/**
		 * @param OAuthRequestXeroom $request
		 * @param OAuthConsumerXeroom $consumer
		 * @param OAuthTokenXeroom $token
		 *
		 * @return mixed|string
		 */
		public function build_signature( $request, $consumer, $token ) {
			$key_parts            = array(
				$consumer->secret,
				( $token ) ? $token->secret : ""
			);
			$key_parts            = OAuthUtilXeroom::urlencode_rfc3986( $key_parts );
			$key                  = implode( '&', $key_parts );
			$request->base_string = $key;

			return $key;
		}
	}
}


if ( ! class_exists( 'OAuthSignatureMethod_RSA_SHA1_Xeroom' ) ) {
	/**
	 * Class OAuthSignatureMethod_RSA_SHA1_Xeroom
	 */
	abstract class OAuthSignatureMethod_RSA_SHA1_Xeroom extends XeroomOAuthSignatureMethodXeroom{
		/**
		 * @return mixed|string
		 */
		public function get_name() {
			return "RSA-SHA1";
		}

		/**
		 * @param $request
		 *
		 * @return mixed
		 */
		protected abstract function fetch_public_cert( &$request );

		/**
		 * @param $request
		 *
		 * @return mixed
		 */
		protected abstract function fetch_private_cert( &$request );

		/**
		 * @param OAuthRequestXeroom $request
		 * @param OAuthConsumerXeroom $consumer
		 * @param OAuthTokenXeroom $token
		 *
		 * @return mixed|string
		 */
		public function build_signature( $request, $consumer, $token ) {
			$base_string          = $request->get_signature_base_string();
			$request->base_string = $base_string;
			$cert                 = $this->fetch_private_cert( $request );
			$privatekeyid         = openssl_get_privatekey( $cert );
			$ok                   = openssl_sign( $base_string, $signature, $privatekeyid );
			openssl_free_key( $privatekeyid );

			return base64_encode( $signature );
		}

		/**
		 * @param OAuthRequestXeroom $request
		 * @param OAuthConsumerXeroom $consumer
		 * @param OAuthTokenXeroom $token
		 * @param string $signature
		 *
		 * @return bool
		 */
		public function check_signature( $request, $consumer, $token, $signature ) {
			$decoded_sig = base64_decode( $signature );
			$base_string = $request->get_signature_base_string();
			$cert        = $this->fetch_public_cert( $request );
			$publickeyid = openssl_get_publickey( $cert );
			$ok          = openssl_verify( $base_string, $decoded_sig, $publickeyid );
			openssl_free_key( $publickeyid );

			return $ok == 1;
		}
	}
}

if ( ! class_exists( 'OAuthRequestXeroom' ) ) {
	/**
	 * Class OAuthRequestXeroom
	 */
	class OAuthRequestXeroom {
		private $parameters;
		private $http_method;
		private $http_url;
		public $base_string;
		public static $version = '1.0';
		public static $POST_INPUT = 'php://input';

		/**
		 * OAuthRequestXeroom constructor.
		 *
		 * @param $http_method
		 * @param $http_url
		 * @param null $parameters
		 */
		function __construct( $http_method, $http_url, $parameters = null ) {
			@$parameters or $parameters = array();
			$parameters        = array_merge( OAuthUtilXeroom::parse_parameters( parse_url( $http_url, PHP_URL_QUERY ) ), $parameters );
			$this->parameters  = $parameters;
			$this->http_method = $http_method;
			$this->http_url    = $http_url;
		}

		/**
		 * @param null $http_method
		 * @param null $http_url
		 * @param null $parameters
		 *
		 * @return OAuthRequestXeroom
		 */
		public static function from_request( $http_method = null, $http_url = null, $parameters = null ) {
			$scheme = ( ! isset( $_SERVER['HTTPS'] ) || $_SERVER['HTTPS'] != "on" )
				? 'http'
				: 'https';
			@$http_url or $http_url = $scheme .
			                          '://' . $_SERVER['HTTP_HOST'] .
			                          ':' .
			                          $_SERVER['SERVER_PORT'] .
			                          $_SERVER['REQUEST_URI'];
			@$http_method or $http_method = $_SERVER['REQUEST_METHOD'];
			if ( ! $parameters ) {
				$request_headers = OAuthUtilXeroom::get_headers();
				$parameters      = OAuthUtilXeroom::parse_parameters( $_SERVER['QUERY_STRING'] );
				if ( $http_method == "POST"
				     && @strstr( $request_headers["Content-Type"],
						"application/x-www-form-urlencoded" )
				) {
					$post_data  = OAuthUtilXeroom::parse_parameters(
						file_get_contents( self::$POST_INPUT )
					);
					$parameters = array_merge( $parameters, $post_data );
				}
				if ( @substr( $request_headers['Authorization'], 0, 6 ) == "OAuth " ) {
					$header_parameters = OAuthUtilXeroom::split_header(
						$request_headers['Authorization']
					);
					$parameters        = array_merge( $parameters, $header_parameters );
				}
			}

			return new OAuthRequestXeroom( $http_method, $http_url, $parameters );
		}

		/**
		 * @param $consumer
		 * @param $token
		 * @param $http_method
		 * @param $http_url
		 * @param null $parameters
		 *
		 * @return OAuthRequestXeroom
		 */
		public static function from_consumer_and_token( $consumer, $token, $http_method, $http_url, $parameters = null ) {
			@$parameters or $parameters = array();
			$defaults = array(
				"oauth_version"      => OAuthRequestXeroom::$version,
				"oauth_nonce"        => OAuthRequestXeroom::generate_nonce(),
				"oauth_timestamp"    => OAuthRequestXeroom::generate_timestamp(),
				"oauth_consumer_key" => ( $consumer->key ) ? $consumer->key : '',
			);
			if ( $token ) {
				$defaults['oauth_token'] = $token->key;
			}

			$parameters = array_merge( $defaults, $parameters );

			return new OAuthRequestXeroom( $http_method, $http_url, $parameters );
		}

		/**
		 * @param $name
		 * @param $value
		 * @param bool $allow_duplicates
		 */
		public function set_parameter( $name, $value, $allow_duplicates = true ) {
            if(!isset( $this->parameters[ $name ] )) {
                return;
            }
			if ( $allow_duplicates ) {
				if ( is_scalar( $this->parameters[ $name ] ) ) {
					$this->parameters[ $name ] = array( $this->parameters[ $name ] );
				}
				$this->parameters[ $name ][] = $value;
			} else {
				$this->parameters[ $name ] = $value;
			}
		}

		/**
		 * @param $name
		 *
		 * @return null
		 */
		public function get_parameter( $name ) {
			return isset( $this->parameters[ $name ] ) ? $this->parameters[ $name ] : null;
		}

		/**
		 * @return array
		 */
		public function get_parameters() {
			return $this->parameters;
		}

		/**
		 * @param $name
		 */
		public function unset_parameter( $name ) {
			unset( $this->parameters[ $name ] );
		}

		/**
		 * @return string
		 */
		public function get_signable_parameters() {
			$params = $this->parameters;
			if ( isset( $params['oauth_signature'] ) ) {
				unset( $params['oauth_signature'] );
			}

			return OAuthUtilXeroom::build_http_query( $params );
		}

		/**
		 * @return string
		 */
		public function get_signature_base_string() {
			$parts = array(
				$this->get_normalized_http_method(),
				$this->get_normalized_http_url(),
				$this->get_signable_parameters()
			);
			$parts = OAuthUtilXeroom::urlencode_rfc3986( $parts );

			return implode( '&', $parts );
		}

		/**
		 * @return string
		 */
		public function get_normalized_http_method() {
			return strtoupper( $this->http_method );
		}

		/**
		 * @return string
		 */
		public function get_normalized_http_url() {
			$parts  = parse_url( $this->http_url );
			$port   = isset( $parts['port'] ) ? $parts['port'] : null;
			$scheme = $parts['scheme'];
			$host   = $parts['host'];
			$path   = isset( $parts['path'] ) ? $parts['path'] : null;
			$port or $port = ( $scheme == 'https' ) ? '443' : '80';
			if ( ( $scheme == 'https' && $port != '443' )
			     || ( $scheme == 'http' && $port != '80' ) ) {
				$host = "$host:$port";
			}

			return "$scheme://$host$path";
		}

		/**
		 * @return string
		 */
		public function to_url() {
			$post_data = $this->to_postdata();
			$out       = $this->get_normalized_http_url();
			if ( $post_data ) {
				$out .= '?' . $post_data;
			}

			return $out;
		}

		/**
		 * @return string
		 */
		public function to_postdata() {
			return OAuthUtilXeroom::build_http_query( $this->parameters );
		}

		/**
		 * @param null $realm
		 *
		 * @return string
		 * @throws OAuthExceptionXeroom
		 */
		public function to_header( $realm = null ) {
			$first = true;
			if ( $realm ) {
				$out   = 'Authorization: OAuth realm="' . OAuthUtilXeroom::urlencode_rfc3986( $realm ) . '"';
				$first = false;
			} else {
				$out = 'Authorization: OAuth';
			}

			$total = array();
			foreach ( $this->parameters as $k => $v ) {
				if ( substr( $k, 0, 5 ) != "oauth" ) {
					continue;
				}
				if ( is_array( $v ) ) {
					throw new OAuthExceptionXeroom( 'Arrays not supported in headers' );
				}
				$out   .= ( $first ) ? ' ' : ',';
				$out   .= OAuthUtilXeroom::urlencode_rfc3986( $k ) .
				          '="' .
				          OAuthUtilXeroom::urlencode_rfc3986( $v ) .
				          '"';
				$first = false;
			}

			return $out;
		}

		/**
		 * @return string
		 */
		public function __toString() {
			return $this->to_url();
		}

		/**
		 * @param $signature_method
		 * @param $consumer
		 * @param $token
		 */
		public function sign_request( $signature_method, $consumer, $token ) {
			if ( $signature_method ) {
				$this->set_parameter(
					"oauth_signature_method",
					$signature_method->get_name(),
					false
				);
			}

			$signature = $this->build_signature( $signature_method, $consumer, $token );
			$this->set_parameter( "oauth_signature", $signature, false );
		}

		/**
		 * @param $signature_method
		 * @param $consumer
		 * @param $token
		 *
		 * @return mixed
		 */
		public function build_signature( $signature_method, $consumer, $token ) {
            $signature = '';
			if ( $signature_method ) {
				$signature = $signature_method->build_signature( $this, $consumer, $token );
			}

			return $signature;
		}

		/**
		 * @return int
		 */
		private static function generate_timestamp() {
			return time();
		}

		/**
		 * @return string
		 */
		private static function generate_nonce() {
			$mt   = microtime();
			$rand = mt_rand();

			return md5( $mt . $rand );
		}
	}
}

if ( ! class_exists( 'OAuthServerXeroom' ) ) {
	/**
	 * Class OAuthServerXeroom
	 */
	class OAuthServerXeroom {
		protected $timestamp_threshold = 300;
		protected $version = '1.0';
		protected $signature_methods = array();
		protected $data_store;

		/**
		 * OAuthServerXeroom constructor.
		 *
		 * @param $data_store
		 */
		function __construct( $data_store ) {
			$this->data_store = $data_store;
		}

		/**
		 * @param $signature_method
		 */
		public function add_signature_method( $signature_method ) {
			$this->signature_methods[ $signature_method->get_name() ] =
				$signature_method;
		}

		/**
		 * @param $request
		 *
		 * @return mixed
		 * @throws OAuthExceptionXeroom
		 */
		public function fetch_request_token( &$request ) {
			$this->get_version( $request );

			$consumer = $this->get_consumer( $request );
			$token    = null;
			$this->check_signature( $request, $consumer, $token );
			$callback  = $request->get_parameter( 'oauth_callback' );
			$new_token = $this->data_store->new_request_token( $consumer, $callback );

			return $new_token;
		}

		/**
		 * @param $request
		 *
		 * @return mixed
		 * @throws OAuthExceptionXeroom
		 */
		public function fetch_access_token( &$request ) {
			$this->get_version( $request );
			$consumer = $this->get_consumer( $request );
			$token    = $this->get_token( $request, $consumer, "request" );
			$this->check_signature( $request, $consumer, $token );
			$verifier  = $request->get_parameter( 'oauth_verifier' );
			$new_token = $this->data_store->new_access_token( $token, $consumer, $verifier );

			return $new_token;
		}

		/**
		 * @param $request
		 *
		 * @return array
		 * @throws OAuthExceptionXeroom
		 */
		public function verify_request( &$request ) {
			$this->get_version( $request );
			$consumer = $this->get_consumer( $request );
			$token    = $this->get_token( $request, $consumer, "access" );
			$this->check_signature( $request, $consumer, $token );

			return array( $consumer, $token );
		}

		/**
		 * @param $request
		 *
		 * @return string
		 * @throws OAuthExceptionXeroom
		 */
		private function get_version( &$request ) {
			$version = $request->get_parameter( "oauth_version" );
			if ( ! $version ) {
				$version = '1.0';
			}
			if ( $version !== $this->version ) {
				throw new OAuthExceptionXeroom( "OAuth version '$version' not supported" );
			}

			return $version;
		}

		/**
		 * @param $request
		 *
		 * @return mixed
		 * @throws OAuthExceptionXeroom
		 */
		private function get_signature_method( &$request ) {
			$signature_method =
				@$request->get_parameter( "oauth_signature_method" );
			if ( ! $signature_method ) {
				throw new OAuthExceptionXeroom( 'No signature method parameter. This parameter is required' );
			}
			if ( ! in_array( $signature_method,
				array_keys( $this->signature_methods ) ) ) {
				throw new OAuthExceptionXeroom(
					"Signature method '$signature_method' not supported " .
					"try one of the following: " .
					implode( ", ", array_keys( $this->signature_methods ) )
				);
			}

			return $this->signature_methods[ $signature_method ];
		}

		/**
		 * @param $request
		 *
		 * @return mixed
		 * @throws OAuthExceptionXeroom
		 */
		private function get_consumer( &$request ) {
			$consumer_key = @$request->get_parameter( "oauth_consumer_key" );
			if ( ! $consumer_key ) {
				throw new OAuthExceptionXeroom( "Invalid consumer key" );
			}
			$consumer = $this->data_store->lookup_consumer( $consumer_key );
			if ( ! $consumer ) {
				throw new OAuthExceptionXeroom( "Invalid consumer" );
			}

			return $consumer;
		}

		/**
		 * @param $request
		 * @param $consumer
		 * @param string $token_type
		 *
		 * @return mixed
		 * @throws OAuthExceptionXeroom
		 */
		private function get_token( &$request, $consumer, $token_type = "access" ) {
			$token_field = @$request->get_parameter( 'oauth_token' );
			$token       = $this->data_store->lookup_token(
				$consumer, $token_type, $token_field
			);
			if ( ! $token ) {
				throw new OAuthExceptionXeroom( "Invalid $token_type token: $token_field" );
			}

			return $token;
		}

		/**
		 * @param $request
		 * @param $consumer
		 * @param $token
		 *
		 * @throws OAuthExceptionXeroom
		 */
		private function check_signature( &$request, $consumer, $token ) {
			$timestamp = @$request->get_parameter( 'oauth_timestamp' );
			$nonce     = @$request->get_parameter( 'oauth_nonce' );
			$this->check_timestamp( $timestamp );
			$this->check_nonce( $consumer, $token, $nonce, $timestamp );
			$signature_method = $this->get_signature_method( $request );
			$signature        = $request->get_parameter( 'oauth_signature' );
			$valid_sig        = $signature_method->check_signature(
				$request,
				$consumer,
				$token,
				$signature
			);
			if ( ! $valid_sig ) {
				throw new OAuthExceptionXeroom( "Invalid signature" );
			}
		}

		/**
		 * @param $timestamp
		 *
		 * @throws OAuthExceptionXeroom
		 */
		private function check_timestamp( $timestamp ) {
			if ( ! $timestamp ) {
				throw new OAuthExceptionXeroom(
					'Missing timestamp parameter. The parameter is required'
				);
			}
			$now = time();
			if ( abs( $now - $timestamp ) > $this->timestamp_threshold ) {
				throw new OAuthExceptionXeroom(
					"Expired timestamp, yours $timestamp, ours $now"
				);
			}
		}

		/**
		 * @param $consumer
		 * @param $token
		 * @param $nonce
		 * @param $timestamp
		 *
		 * @throws OAuthExceptionXeroom
		 */
		private function check_nonce( $consumer, $token, $nonce, $timestamp ) {
			if ( ! $nonce ) {
				throw new OAuthExceptionXeroom(
					'Missing nonce parameter. The parameter is required'
				);
			}
			$found = $this->data_store->lookup_nonce(
				$consumer,
				$token,
				$nonce,
				$timestamp
			);
			if ( $found ) {
				throw new OAuthExceptionXeroom( "Nonce already used: $nonce" );
			}
		}
	}
}

if ( ! class_exists( 'OAuthDataStoreXeroom' ) ) {
	/**
	 * Class OAuthDataStoreXeroom
	 */
	class OAuthDataStoreXeroom {
		/**
		 * @param $consumer_key
		 */
		function lookup_consumer( $consumer_key ) {
		}

		/**
		 * @param $consumer
		 * @param $token_type
		 * @param $token
		 */
		function lookup_token( $consumer, $token_type, $token ) {
		}

		/**
		 * @param $consumer
		 * @param $token
		 * @param $nonce
		 * @param $timestamp
		 */
		function lookup_nonce( $consumer, $token, $nonce, $timestamp ) {
		}

		/**
		 * @param $consumer
		 * @param null $callback
		 */
		function new_request_token( $consumer, $callback = null ) {
		}

		/**
		 * @param $token
		 * @param $consumer
		 * @param null $verifier
		 */
		function new_access_token( $token, $consumer, $verifier = null ) {
		}
	}
}

if ( ! class_exists( 'OAuthUtilXeroom' ) ) {
	/**
	 * Class OAuthUtilXeroom
	 */
	class OAuthUtilXeroom {
		/**
		 * @param $input
		 *
		 * @return array|mixed|string
		 */
		public static function urlencode_rfc3986( $input ) {
			if ( is_array( $input ) ) {
				return array_map( array( 'OAuthUtilXeroom', 'urlencode_rfc3986' ), $input );
			} else if ( is_scalar( $input ) ) {
				return str_replace(
					'+',
					' ',
					str_replace( '%7E', '~', rawurlencode( $input ) )
				);
			} else {
				return '';
			}
		}

		/**
		 * @param $string
		 *
		 * @return string
		 */
		public static function urldecode_rfc3986( $string ) {
			return urldecode( $string );
		}

		/**
		 * @param $header
		 * @param bool $only_allow_oauth_parameters
		 *
		 * @return array
		 */
		public static function split_header( $header, $only_allow_oauth_parameters = true ) {
			$pattern = '/(([-_a-z]*)=("([^"]*)"|([^,]*)),?)/';
			$offset  = 0;
			$params  = array();
			while ( preg_match( $pattern, $header, $matches, PREG_OFFSET_CAPTURE, $offset ) > 0 ) {
				$match          = $matches[0];
				$header_name    = $matches[2][0];
				$header_content = ( isset( $matches[5] ) ) ? $matches[5][0] : $matches[4][0];
				if ( preg_match( '/^oauth_/', $header_name ) || ! $only_allow_oauth_parameters ) {
					$params[ $header_name ] = OAuthUtilXeroom::urldecode_rfc3986( $header_content );
				}
				$offset = $match[1] + strlen( $match[0] );
			}
			if ( isset( $params['realm'] ) ) {
				unset( $params['realm'] );
			}

			return $params;
		}

		/**
		 * @return array
		 */
		public static function get_headers() {
			if ( function_exists( 'apache_request_headers' ) ) {
				$headers = apache_request_headers();
				$out     = array();
				foreach ( $headers as $key => $value ) {
					$key         = str_replace(
						" ",
						"-",
						ucwords( strtolower( str_replace( "-", " ", $key ) ) )
					);
					$out[ $key ] = $value;
				}
			} else {
				$out = array();
				if ( isset( $_SERVER['CONTENT_TYPE'] ) ) {
					$out['Content-Type'] = $_SERVER['CONTENT_TYPE'];
				}
				if ( isset( $_ENV['CONTENT_TYPE'] ) ) {
					$out['Content-Type'] = $_ENV['CONTENT_TYPE'];
				}

				foreach ( $_SERVER as $key => $value ) {
					if ( substr( $key, 0, 5 ) == "HTTP_" ) {
						$key         = str_replace(
							" ",
							"-",
							ucwords( strtolower( str_replace( "_", " ", substr( $key, 5 ) ) ) )
						);
						$out[ $key ] = $value;
					}
				}
			}

			return $out;
		}

		/**
		 * @param $input
		 *
		 * @return array
		 */
		public static function parse_parameters( $input ) {
			if ( ! isset( $input ) || ! $input ) {
				return array();
			}
			$pairs             = explode( '&', $input );
			$parsed_parameters = array();
			foreach ( $pairs as $pair ) {
				$split     = explode( '=', $pair, 2 );
				$parameter = OAuthUtilXeroom::urldecode_rfc3986( $split[0] );
				$value     = isset( $split[1] ) ? OAuthUtilXeroom::urldecode_rfc3986( $split[1] ) : '';

				if ( isset( $parsed_parameters[ $parameter ] ) ) {
					if ( is_scalar( $parsed_parameters[ $parameter ] ) ) {
						$parsed_parameters[ $parameter ] = array( $parsed_parameters[ $parameter ] );
					}
					$parsed_parameters[ $parameter ][] = $value;
				} else {
					$parsed_parameters[ $parameter ] = $value;
				}
			}

			return $parsed_parameters;
		}

		/**
		 * @param $params
		 *
		 * @return string
		 */
		public static function build_http_query( $params ) {
			if ( ! $params ) {
				return '';
			}
			$keys   = OAuthUtilXeroom::urlencode_rfc3986( array_keys( $params ) );
			$values = OAuthUtilXeroom::urlencode_rfc3986( array_values( $params ) );
			$params = array_combine( $keys, $values );
			uksort( $params, 'strcmp' );
			$pairs = array();
			foreach ( $params as $parameter => $value ) {
				if ( is_array( $value ) ) {
					natsort( $value );
					foreach ( $value as $duplicate_value ) {
						$pairs[] = $parameter . '=' . $duplicate_value;
					}
				} else {
					$pairs[] = $parameter . '=' . $value;
				}
			}

			return implode( '&', $pairs );
		}
	}
}

if ( ! class_exists( 'OAuthSignatureMethod_Xero' ) ) {
	/**
	 * Class OAuthSignatureMethod_Xero
	 */
	class OAuthSignatureMethod_Xero extends OAuthSignatureMethod_RSA_SHA1_Xeroom {
		protected $public_cert;
		protected $private_key;

		/**
		 * OAuthSignatureMethod_Xero constructor.
		 *
		 * @param $public_cert
		 * @param $private_key
		 */
		public function __construct( $public_cert, $private_key ) {
			$this->public_cert = $public_cert;
			$this->private_key = $private_key;
		}

		/**
		 * @param $request
		 *
		 * @return bool|mixed|string
		 */
		protected function fetch_public_cert( &$request ) {
			return file_get_contents( $this->public_cert );
		}

		/**
		 * @param $request
		 *
		 * @return bool|mixed|string
		 */
		protected function fetch_private_cert( &$request ) {
			return file_get_contents( $this->private_key );
		}
	}
}

if ( ! class_exists( 'ArrayToXML' ) ) {
	/**
	 * Class ArrayToXML
	 */
	class ArrayToXML {
		/**
		 * @param $data
		 * @param string $rootNodeName
		 * @param null $xml
		 *
		 * @return mixed
		 */
		public static function toXML( $data, $rootNodeName = 'ResultSet', &$xml = null ) {
			if ( ini_get( 'zend.ze1_compatibility_mode' ) == 1 ) {
				ini_set( 'zend.ze1_compatibility_mode', 0 );
			}
			if ( is_null( $xml ) ) {
				$xml          = simplexml_load_string( "<$rootNodeName />" );
				$rootNodeName = rtrim( $rootNodeName, 's' );
			}
			foreach ( $data as $key => $value ) {
				$numeric = 0;
				if ( is_numeric( $key ) ) {
					$numeric = 1;
					$key     = $rootNodeName;
				}
				$key = preg_replace( '/[^a-z0-9\-\_\.\:]/i', '', $key );
				if ( is_array( $value ) ) {
					$node = ( ArrayToXML::isAssoc( $value ) || $numeric ) ? $xml->addChild( $key ) : $xml;
					if ( $numeric ) {
						$key = 'anon';
					}
					ArrayToXML::toXml( $value, $key, $node );
				} else {
					if ( is_string( $value ) ) {
						$value = htmlspecialchars($value, ENT_QUOTES);
					} else if ( is_a( $value, "DateTime" ) ) {
						$value = $value->format( "c" );
					}
//					$xml->$key = $value;
					$xml->addChild( $key, $value );
				}
			}

			return $xml->asXML();
		}

		/**
		 * @param $xml
		 *
		 * @return array|string
		 */
		public static function toArray( $xml ) {
			if ( is_string( $xml ) ) {
				$xml = new SimpleXMLElement( $xml );
			}
			$children = $xml->children();
			if ( ! $children ) {
				return (string) $xml;
			}
			$arr = array();
			foreach ( $children as $key => $node ) {
				$node = ArrayToXML::toArray( $node );
				if ( $key == 'anon' ) {
					$key = count( $arr );
				}
				if ( array_key_exists( $key, $arr ) && isset( $arr[ $key ] ) ) {
					if ( ! is_array( $arr[ $key ] ) || ! array_key_exists( 0, $arr[ $key ] ) || ( array_key_exists( 0, $arr[ $key ] ) && ( $arr[ $key ][0] == null ) ) ) {
						$arr[ $key ] = array( $arr[ $key ] );
					}
					$arr[ $key ][] = $node;
				} else {
					$arr[ $key ] = $node;
				}
			}

			return $arr;
		}

		/**
		 * @param $array
		 *
		 * @return bool
		 */
		public static function isAssoc( $array ) {
			return ( is_array( $array ) && 0 !== count( array_diff_key( $array, array_keys( array_keys( $array ) ) ) ) );
		}
	}
}

if ( ! class_exists( 'XeroException' ) ) {
	/**
	 * Class XeroException
	 */
	class XeroException extends Exception {
	}
}


if ( ! class_exists( 'XeroApiException' ) ) {
	/**
	 * Class XeroApiException
	 */
	class XeroApiException extends XeroException {
		private $xml;

		/**
		 * XeroApiException constructor.
		 *
		 * @param $xml_exception
		 */
		public function __construct( $xml_exception ) {
			$this->xml = $xml_exception;
			$xml       = new SimpleXMLElement( $xml_exception );

			list( $message ) = $xml->xpath( '/ApiException/Message' );
			list( $errorNumber ) = $xml->xpath( '/ApiException/ErrorNumber' );
			list( $type ) = $xml->xpath( '/ApiException/Type' );

			parent::__construct( (string) $type . ': ' . (string) $message, (int) $errorNumber );

			$this->type = (string) $type;
		}

		/**
		 * @return mixed
		 */
		public function getXML() {
			return $this->xml;
		}

		/**
		 * @param $xml
		 *
		 * @return false|int
		 */
		public static function isException( $xml ) {
			return preg_match( '/^<ApiException.*>/', $xml );
		}
	}
}
