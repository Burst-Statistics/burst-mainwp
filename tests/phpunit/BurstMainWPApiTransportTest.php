<?php
/**
 * Tests for the signed dashboard→child transport in BurstMainWP\API.
 */

namespace MainWP\Dashboard {
	if ( ! class_exists( 'MainWP\Dashboard\MainWP_Connect' ) ) {
		/**
		 * Signs with OpenSSL like MainWP core does, so signatures can be verified in the test.
		 */
		class MainWP_Connect {
			public static function connect_sign( $data, &$signature, $privkey, $algorithm, $site_id ) {
				return openssl_sign( $data, $signature, $privkey, OPENSSL_ALGO_SHA256 );
			}
		}
	}
	if ( ! class_exists( 'MainWP\Dashboard\MainWP_System_Utility' ) ) {
		class MainWP_System_Utility {
			public static function get_connect_sign_algorithm( $site ) {
				return OPENSSL_ALGO_SHA256;
			}
		}
	}
	if ( ! class_exists( 'MainWP\Dashboard\MainWP_DB' ) ) {
		class MainWP_DB {
			public static function instance() {
				return new self();
			}
			public function get_website_by_id( $site_id ) {
				return $GLOBALS['test_mainwp_site'];
			}
		}
	}
}

namespace {
	use PHPUnit\Framework\TestCase;

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	}

	// Same option store as BurstMainWPReportsIntegrationTest: whichever file loads first defines the stub.
	if ( ! function_exists( 'get_option' ) ) {
		function get_option( $option, $default = false ) {
			global $test_options;
			if ( isset( $test_options[ $option ] ) ) {
				return $test_options[ $option ];
			}
			if ( 'date_format' === $option ) {
				return 'Y-m-d';
			}
			return $default;
		}
	}
	if ( ! function_exists( 'trailingslashit' ) ) {
		function trailingslashit( $value ) {
			return rtrim( $value, '/\\' ) . '/';
		}
	}
	if ( ! function_exists( 'home_url' ) ) {
		function home_url( $path = '' ) {
			return 'https://dashboard.example' . $path;
		}
	}
	if ( ! function_exists( 'wp_parse_url' ) ) {
		function wp_parse_url( $url, $component = -1 ) {
			return parse_url( $url, $component );
		}
	}
	if ( ! function_exists( 'wp_json_encode' ) ) {
		function wp_json_encode( $data, $options = 0, $depth = 512 ) {
			return json_encode( $data, $options, $depth );
		}
	}
	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $thing ) {
			return $thing instanceof WP_Error;
		}
	}
	if ( ! function_exists( 'wp_remote_post' ) ) {
		function wp_remote_post( $url, $args = [] ) {
			$GLOBALS['test_remote_posts'][] = [
				'url'  => $url,
				'args' => $args,
			];
			// Per-URL canned responses, e.g. a redirect; everything else answers 200.
			if ( isset( $GLOBALS['test_remote_responses'][ $url ] ) ) {
				return $GLOBALS['test_remote_responses'][ $url ];
			}
			return [
				'response' => [ 'code' => 200 ],
				'body'     => json_encode(
					[
						'token'             => 'token',
						'root_url'          => 'https://child.example/wp-json/',
						'localization_data' => [ 'a' => 1 ],
						'nonce'             => 'n',
					]
				),
			];
		}
	}
	if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
		function wp_remote_retrieve_response_code( $response ) {
			return $response['response']['code'] ?? 0;
		}
	}
	if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
		function wp_remote_retrieve_body( $response ) {
			return $response['body'] ?? '';
		}
	}
	if ( ! function_exists( 'wp_remote_retrieve_header' ) ) {
		function wp_remote_retrieve_header( $response, $header ) {
			return $response['headers'][ strtolower( $header ) ] ?? '';
		}
	}

	require_once dirname( __DIR__, 2 ) . '/class/class-api.php';

	class BurstMainWPApiTransportTest extends TestCase {

		/**
		 * Dashboard private key used to sign requests.
		 *
		 * @var \OpenSSLAsymmetricKey
		 */
		private $private_key;

		protected function setUp(): void {
			parent::setUp();
			$this->private_key = openssl_pkey_new(
				[
					'private_key_bits' => 2048,
					'private_key_type' => OPENSSL_KEYTYPE_RSA,
				]
			);
			openssl_pkey_export( $this->private_key, $pem );
			$GLOBALS['test_mainwp_site']  = (object) [
				'id'                 => 1,
				'url'                => 'https://child.example/',
				'adminname'          => 'admin',
				'privkey'            => base64_encode( $pem ),
				'verify_certificate' => 1,
			];
			$GLOBALS['test_remote_posts']     = [];
			$GLOBALS['test_remote_responses'] = [];
		}

		/**
		 * Answer the first auth endpoint with a redirect to the given location.
		 *
		 * @param int    $code     HTTP status code.
		 * @param string $location Location header.
		 */
		private function redirect_auth_endpoint( int $code, string $location ): void {
			$GLOBALS['test_remote_responses']['https://child.example/wp-json/burst/v1/mainwp-auth'] = [
				'response' => [ 'code' => $code ],
				'headers'  => [ 'location' => $location ],
				'body'     => '',
			];
		}

		/**
		 * URLs the signed body was posted to.
		 *
		 * @return string[]
		 */
		private function posted_urls(): array {
			return array_column( $GLOBALS['test_remote_posts'], 'url' );
		}

		public function test_redirect_to_www_variant_over_https_is_followed_with_fresh_body(): void {
			$this->redirect_auth_endpoint( 301, 'https://www.child.example/wp-json/burst/v1/mainwp-auth' );

			$this->assertIsArray( \BurstMainWP\API::instance()->get_child_auth( 1 ) );
			$this->assertSame( [ 'https://child.example/wp-json/burst/v1/mainwp-auth', 'https://www.child.example/wp-json/burst/v1/mainwp-auth' ], $this->posted_urls() );

			$first  = json_decode( $GLOBALS['test_remote_posts'][0]['args']['body'], true );
			$second = json_decode( $GLOBALS['test_remote_posts'][1]['args']['body'], true );
			$this->assertNotSame( $first['nonce'], $second['nonce'] );
		}

		public function test_redirect_to_http_is_not_followed(): void {
			$this->redirect_auth_endpoint( 308, 'http://www.child.example/wp-json/burst/v1/mainwp-auth' );

			\BurstMainWP\API::instance()->get_child_auth( 1 );

			foreach ( $this->posted_urls() as $url ) {
				$this->assertStringStartsWith( 'https://child.example/', $url );
			}
		}

		public function test_redirect_to_other_host_is_not_followed(): void {
			$this->redirect_auth_endpoint( 307, 'https://attacker.example/collect' );

			\BurstMainWP\API::instance()->get_child_auth( 1 );

			$this->assertNotContains( 'https://attacker.example/collect', $this->posted_urls() );
		}

		public function test_auth_request_is_not_sent_to_http_child(): void {
			$GLOBALS['test_mainwp_site']->url = 'http://child.example/';

			$this->assertFalse( \BurstMainWP\API::instance()->get_child_auth( 1 ) );
			$this->assertSame( [], $GLOBALS['test_remote_posts'] );
		}

		public function test_signed_request_does_not_follow_redirects(): void {
			\BurstMainWP\API::instance()->get_child_auth( 1 );

			$this->assertNotEmpty( $GLOBALS['test_remote_posts'] );
			foreach ( $GLOBALS['test_remote_posts'] as $post ) {
				$this->assertSame( 0, $post['args']['redirection'] ?? null );
			}
		}

		public function test_v2_signature_covers_issue_time_and_origin(): void {
			\BurstMainWP\API::instance()->get_child_auth( 1 );
			$body       = json_decode( $GLOBALS['test_remote_posts'][0]['args']['body'], true );
			$public_key = openssl_pkey_get_details( $this->private_key )['key'];
			$payload    = implode( '|', [ 'v2', $body['function'], $body['nonce'], (string) $body['nonce_issued_at'], $body['user'], $body['dashboard_origin'] ] );

			// Same payload the Burst child verifies in MainWP_Proxy::verify_signature().
			$this->assertSame( 1, openssl_verify( $payload, base64_decode( $body['mainwpsignature_v2'] ), $public_key, OPENSSL_ALGO_SHA256 ) );
			$this->assertSame( 1, openssl_verify( 'burst_proxy|' . $body['nonce'] . '|admin', base64_decode( $body['mainwpsignature'] ), $public_key, OPENSSL_ALGO_SHA256 ) );

			$tampered = implode( '|', [ 'v2', $body['function'], $body['nonce'], (string) ( $body['nonce_issued_at'] + 400 ), $body['user'], $body['dashboard_origin'] ] );
			$this->assertSame( 0, openssl_verify( $tampered, base64_decode( $body['mainwpsignature_v2'] ), $public_key, OPENSSL_ALGO_SHA256 ) );
		}
	}
}
