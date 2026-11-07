<?php
/**
 * Burst Statistics – MainWP API
 *
 * Handles all communication between the MainWP dashboard and child sites.
 *
 * Responsibility split:
 *   • `get_site_data()`  – read MainWP site data through MainWP APIs.
 *   • `get_child_auth()` – fetch a signed auth token + nonce from the child's
 *                          REST endpoint.  Must be called during a full WP page
 *                          load so MainWP signing classes are available.
 *
 * The token and nonce are then forwarded to React via `wp_localize_script` so
 * that `apiFetch` can call the child's REST API directly from the browser.
 *
 * @package Burst_Statistics_MainWP
 */

namespace BurstMainWP;

defined( 'ABSPATH' ) || exit;

class API {

	private static ?self $instance = null;

	/**
	 * Stores last child-auth debug data keyed by site ID.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $last_auth_debug = [];

	/**
	 * Singleton instance accessor.
	 *
	 * @return self Singleton instance of the API class.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use instance() instead.
	 * No hooks: this class is called explicitly during page render.
	 */
	private function __construct() {}

	// ── Site Data ─────────────────────────────────────────────────────────────

	/**
	 * Retrieve a MainWP website row by ID.
	 *
	 * Uses MainWP's official DB API class when available.
	 *
	 * @param int $site_id MainWP site (wp) ID.
	 * @return object|null Database row or null when not found.
	 */
	public function get_site_data( int $site_id ): ?object {
		if ( class_exists( 'MainWP\Dashboard\MainWP_DB' ) ) {
			$site = \MainWP\Dashboard\MainWP_DB::instance()->get_website_by_id( $site_id );
			return $site ?: null;
		}

		return null;
	}

	/**
	 * Get the last auth debug payload for a site.
	 *
	 * @param int $site_id MainWP site ID.
	 * @return array<string,mixed>
	 */
	public function get_last_auth_debug( int $site_id ): array {
		return $this->last_auth_debug[ $site_id ] ?? [];
	}

	// ── Auth / Token Exchange ─────────────────────────────────────────────────

	/**
	 * Fetch a REST auth token and nonce from the child site.
	 *
	 * Flow:
	 *  1. Load site data (private key, admin username, URL).
	 *  2. Build a signed request body using MainWP's signing infrastructure.
	 *  3. POST to the child's `burst/v1/mainwp-auth` endpoint.
	 *  4. Validate the response and return the structured auth array.
	 *
	 * Not cached across requests — nonces are single-use and page loads that
	 * need them are infrequent.
	 *
	 * @param int $site_id MainWP site ID.
	 * @return array{nonce:string,token:string,root_url:string,localization_data:array}|false
	 *         Auth payload on success, false on any failure.
	 */
	public function get_child_auth( int $site_id ): array|false {
		$debug = [
			'site_id'       => $site_id,
			'step'          => 'bootstrap',
			'reason'        => 'unknown',
			'http_attempts' => [],
		];

		if ( ! class_exists( 'MainWP\Dashboard\MainWP_Connect' ) ) {
			$debug['step']   = 'bootstrap';
			$debug['reason'] = 'mainwp_connect_class_missing';
			$this->set_last_auth_debug( $site_id, $debug );
			return false;
		}

		$debug['step'] = 'load_site_data';
		$site_data     = $this->get_site_data( $site_id );
		if ( ! $site_data ) {
			$debug['reason'] = 'site_data_not_found';
			$this->set_last_auth_debug( $site_id, $debug );
			return false;
		}

		$debug['step'] = 'request_child_auth';
		$response      = $this->send_signed_child_request( $site_data, 'burst_proxy', [], 'burst/v1/mainwp-auth', $debug );

		if ( null === $response ) {
			$this->set_last_auth_debug( $site_id, $debug );
			return false;
		}

		if ( is_wp_error( $response ) ) {
			$debug['reason'] = 'all_endpoints_returned_wp_error';
			$this->set_last_auth_debug( $site_id, $debug );
			return false;
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $http_code ) {
			$debug['reason']    = 'auth_endpoint_non_200_response';
			$debug['http_code'] = $http_code;
			$this->set_last_auth_debug( $site_id, $debug );
			return false;
		}

		$debug['step'] = 'parse_response';
		$data          = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			$debug['reason'] = 'invalid_json_response';
			$this->set_last_auth_debug( $site_id, $debug );
			return false;
		}

		if ( ! $this->is_valid_auth_response( $data ) ) {
			$missing = [];
			foreach ( [ 'token', 'root_url', 'localization_data' ] as $required_key ) {
				if ( empty( $data[ $required_key ] ) ) {
					$missing[] = $required_key;
				}
			}
			$debug['reason']       = 'invalid_auth_payload';
			$debug['missing_keys'] = $missing;
			$this->set_last_auth_debug( $site_id, $debug );
			return false;
		}

		$debug['step']   = 'completed';
		$debug['reason'] = 'success';
		$this->set_last_auth_debug( $site_id, $debug );

		return $data;
	}

	/**
	 * Send a signed POST request to a child site.
	 *
	 * Loops over endpoints until one returns a response. Stops immediately after
	 * the first endpoint that answers (HTTP 200). Never retries with SSL verification
	 * disabled, preventing credential leakage over cleartext or to forged certificates.
	 *
	 * @param object               $site_data Site database object.
	 * @param string               $action    Signature action (e.g. 'burst_proxy', 'burst_report_data').
	 * @param array<string, mixed> $params    Additional parameters for signed body. Mixed: parameter values include integers, booleans, and strings.
	 * @param string               $route     Child REST route without leading slash (e.g. 'burst/v1/mainwp-auth').
	 * @param array<string, mixed> $debug     Debug accumulator by reference. Mixed: debug dictionary holds strings, integers, and nested attempt arrays.
	 * @return array<string, mixed>|\WP_Error|null Response array from wp_remote_post, WP_Error, or null if the child URL is not HTTPS or the build failed. Mixed: wp_remote_post response array contains headers, body string, and status code.
	 */
	private function send_signed_child_request( object $site_data, string $action, array $params, string $route, array &$debug ): array|\WP_Error|null {
		// Security: a signed body (and the auth token in the reply) never travels over plain HTTP.
		if ( ! str_starts_with( strtolower( (string) ( $site_data->url ?? '' ) ), 'https://' ) ) {
			$debug['reason'] = 'child_url_not_https';
			return null;
		}

		$endpoint_base = trailingslashit( $site_data->url );
		$endpoints     = [
			$endpoint_base . 'wp-json/' . $route,
			$endpoint_base . '?rest_route=/' . $route,
		];

		$sslverify = 'burst_proxy' === $action || $this->resolve_ssl_verify( $site_data );
		$response  = null;

		foreach ( $endpoints as $endpoint ) {
			$url = $endpoint;
			// One redirect hop is allowed, and only to the same site over HTTPS (e.g. example.com → www.example.com).
			for ( $hop = 0; $hop < 2; $hop++ ) {
				$body = $this->build_signed_body( $site_data, $action, $params );
				if ( ! $body ) {
					$debug['reason'] = 'signed_body_generation_failed';
					return null;
				}

				$attempt = [ 'endpoint' => $url ];

				$response = wp_remote_post(
					$url,
					[
						'headers'     => [
							'Content-Type'  => 'application/json',
							'X-BURSTMAINWP' => '1',
						],
						'body'        => wp_json_encode( $body ),
						'timeout'     => 15,
						'sslverify'   => $sslverify,
						// WordPress would re-send this signed body to any redirect target, including http:// or another host.
						'redirection' => 0,
					]
				);

				if ( is_wp_error( $response ) ) {
					$attempt['result']        = 'wp_error';
					$attempt['code']          = $response->get_error_code();
					$attempt['message']       = $response->get_error_message();
					$debug['http_attempts'][] = $attempt;
					break;
				}

				$http_code                = (int) wp_remote_retrieve_response_code( $response );
				$attempt['result']        = 'http';
				$attempt['http_code']     = $http_code;
				$attempt['body_excerpt']  = substr( wp_remote_retrieve_body( $response ), 0, 220 );
				$debug['http_attempts'][] = $attempt;

				$redirect_url = 0 === $hop ? $this->get_same_site_redirect_url( $url, $response ) : '';
				if ( '' === $redirect_url ) {
					break;
				}
				$url = $redirect_url;
			}

			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) && null !== json_decode( wp_remote_retrieve_body( $response ) ) ) {
				break;
			}
		}

		return $response;
	}

	/**
	 * Return the target of a POST-preserving redirect when it stays on the same site over HTTPS.
	 *
	 * Only 307/308 (and 301, which WordPress re-sends as POST) qualify. The host must match the
	 * requested host, ignoring a leading "www.", so a redirect can never move the signed body to
	 * another domain or to plain HTTP.
	 *
	 * @param string               $requested_url URL the request was sent to.
	 * @param array<string, mixed> $response      Response from wp_remote_post(). Mixed: headers, body string and status code.
	 * @return string Redirect URL, or an empty string when the response must not be followed.
	 */
	private function get_same_site_redirect_url( string $requested_url, array $response ): string {
		if ( ! in_array( (int) wp_remote_retrieve_response_code( $response ), [ 301, 307, 308 ], true ) ) {
			return '';
		}

		$location = (string) wp_remote_retrieve_header( $response, 'location' );
		if ( ! str_starts_with( strtolower( $location ), 'https://' ) ) {
			return '';
		}

		$requested_host = strtolower( (string) wp_parse_url( $requested_url, PHP_URL_HOST ) );
		$redirect_host  = strtolower( (string) wp_parse_url( $location, PHP_URL_HOST ) );
		if ( '' === $redirect_host || preg_replace( '/^www\./', '', $requested_host ) !== preg_replace( '/^www\./', '', $redirect_host ) ) {
			return '';
		}

		return $location;
	}

	/**
	 * Resolve SSL certificate verification for a site.
	 *
	 * Honors site-level setting: 1 = verify, 0 = do not verify.
	 * Only falls back to global setting when set to 2 ("use global").
	 *
	 * @param object $site_data MainWP website row.
	 * @return bool Whether to verify SSL certificate.
	 */
	private function resolve_ssl_verify( object $site_data ): bool {
		$site_verify = isset( $site_data->verify_certificate ) ? (int) $site_data->verify_certificate : 2;
		if ( 0 === $site_verify ) {
			return false;
		}
		if ( 2 === $site_verify ) {
			return 0 !== (int) get_option( 'mainwp_sslVerifyCertificate', 1 );
		}
		return true;
	}

	/**
	 * Persist auth debug payload for later reporting.
	 */
	private function set_last_auth_debug( int $site_id, array $debug ): void {
		$this->last_auth_debug[ $site_id ] = $debug;
	}

	/**
	 * Fetch Burst Statistics report data from a child site.
	 *
	 * Uses MainWP asymmetric RSA signing over `burst_report_data|$nonce|$user`
	 * to authenticate server-to-server with the child site's `/burst/v1/mainwp-report-data`
	 * endpoint.
	 *
	 * Rejects non-HTTPS child URLs to prevent signing replay over unencrypted HTTP.
	 *
	 * @param int $site_id    MainWP site ID.
	 * @param int $start_date Unix timestamp for start of reporting window.
	 * @param int $end_date   Unix timestamp for end of reporting window.
	 * @return array<string, mixed>|null Structured report data or null on failure. Mixed: report payload contains integers, strings, and nested arrays.
	 */
	public function get_child_report_data( int $site_id, int $start_date, int $end_date ): ?array {
		if ( ! class_exists( 'MainWP\Dashboard\MainWP_Connect' ) ) {
			return null;
		}

		$site_data = $this->get_site_data( $site_id );
		if ( ! $site_data || empty( $site_data->url ) ) {
			return null;
		}

		$debug    = [ 'http_attempts' => [] ];
		$params   = [
			'start_date'            => $start_date,
			'end_date'              => $end_date,
			'should_load_ecommerce' => true,
		];
		$response = $this->send_signed_child_request( $site_data, 'burst_report_data', $params, 'burst/v1/mainwp-report-data', $debug );

		$this->set_last_auth_debug( $site_id, $debug );

		if ( ! is_array( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$raw_body = wp_remote_retrieve_body( $response );
		$data     = json_decode( $raw_body, true );

		if ( is_array( $data ) && ! empty( $data['success'] ) && isset( $data['data'] ) && is_array( $data['data'] ) ) {
			return $data['data'];
		}

		$debug['reason'] = 'invalid_report_data_payload';
		$this->set_last_auth_debug( $site_id, $debug );

		return null;
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Validate that an auth response contains all required fields.
	 *
	 * @param mixed $data Decoded JSON from the child endpoint.
	 * @return bool True if the response is valid, false otherwise.
	 */
	private function is_valid_auth_response( mixed $data ): bool {
		if ( ! is_array( $data ) ) {
			return false;
		}
		foreach ( [ 'token', 'root_url', 'localization_data' ] as $key ) {
			if ( empty( $data[ $key ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Build a signed request body using MainWP's asymmetric signing infrastructure.
	 *
	 * The nonce is generated as `bin2hex(random_bytes(16))` — a 32-char
	 * cryptographically random hex string.  A `nonce_issued_at` timestamp is
	 * included alongside it so the child can enforce a time-based acceptance
	 * window in addition to the single-use check.
	 *
	 * The signed payload is `$_function|$nonce|$adminname` — all three fields
	 * are cryptographically bound so neither can be substituted after signing.
	 *
	 * @param object $site_data MainWP website row (must include `privkey`, `adminname`, `id`).
	 * @param string $_function  MainWP function name included in the signing payload.
	 * @param array  $extra     Additional fields merged into the body.
	 * @return array<string,mixed>|false Signed body ready to JSON-encode, or false on failure.
	 */
	private function build_signed_body( object $site_data, string $_function, array $extra = [] ): array|false {
		// 32-char hex, cryptographically random.
		$nonce            = bin2hex( random_bytes( 16 ) );
		$issued_at        = time();
		$dashboard_origin = $this->get_dashboard_origin();
		// Legacy signature for children on Burst versions that only verify `function|nonce|user`.
		$signed = $this->sign_payload( $_function . '|' . $nonce . '|' . $site_data->adminname, $site_data );
		// The v2 signature also covers the issue time and origin, so a body replayed after the child purges its nonce fails the TTL check.
		$signed_v2 = $this->sign_payload( implode( '|', [ 'v2', $_function, $nonce, (string) $issued_at, (string) $site_data->adminname, $dashboard_origin ] ), $site_data );

		if ( ! $signed || ! $signed_v2 ) {
			return false;
		}

		return array_merge(
			[
				'user'               => $site_data->adminname,
				'nonce'              => $nonce,
				'nonce_issued_at'    => $issued_at,
				'mainwpsignature'    => $signed['signature'],
				'mainwpsignature_v2' => $signed_v2['signature'],
				'function'           => $_function,
				'verifylib'          => $signed['use_seclib'] ? 1 : 0,
				'dashboard_origin'   => $dashboard_origin,
			],
			$extra
		);
	}

	/**
	 * Build the dashboard site origin used by child-side CORS allowlisting.
	 */
	private function get_dashboard_origin(): string {
		$home  = home_url( '/' );
		$parts = wp_parse_url( $home );

		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$origin = strtolower( $parts['scheme'] . '://' . $parts['host'] );
		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . (int) $parts['port'];
		}

		return $origin;
	}

	/**
	 * Sign a string payload using whichever signing library MainWP has configured.
	 *
	 * MainWP supports both OpenSSL and a phpseclib-based fallback.  This method
	 * detects which one to use by consulting MainWP_Connect_Lib::is_use_fallback_sec_lib().
	 *
	 * @param string $payload   The raw string to sign (`$_function|$nonce|$user`).
	 * @param object $site_data MainWP website row (must include `privkey`).
	 * @return array{signature:string,use_seclib:bool}|false Encoded signature, or false on failure.
	 */
	private function sign_payload( string $payload, object $site_data ): array|false {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$raw_privkey = base64_decode( $site_data->privkey, true );
		if ( false === $raw_privkey ) {
			return false;
		}

		$signature    = '';
		$sign_success = false;
		$use_seclib   = false;

		if (
			class_exists( 'MainWP\Dashboard\MainWP_Connect_Lib' ) &&
			\MainWP\Dashboard\MainWP_Connect_Lib::is_use_fallback_sec_lib( $site_data )
		) {
			$sign_success = \MainWP\Dashboard\MainWP_Connect_Lib::connect_sign(
				$payload,
				$signature,
				$raw_privkey,
				$site_data->id
			);
			$use_seclib   = true;
		} else {
			$alg          = \MainWP\Dashboard\MainWP_System_Utility::get_connect_sign_algorithm( $site_data );
			$sign_success = \MainWP\Dashboard\MainWP_Connect::connect_sign(
				$payload,
				$signature,
				$raw_privkey,
				$alg,
				$site_data->id
			);
		}

		if ( ! $sign_success || '' === $signature ) {
			return false;
		}

		return [
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'signature'  => base64_encode( $signature ),
			'use_seclib' => $use_seclib,
		];
	}
}
