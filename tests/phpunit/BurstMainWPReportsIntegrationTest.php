<?php
/**
 * Tests for BurstMainWP\Reports_Integration.
 */

use PHPUnit\Framework\TestCase;

// WordPress function stubs for isolated PHPUnit testing.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

global $wp_filter_test_storage;
$wp_filter_test_storage = [];

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		global $wp_filter_test_storage;
		$wp_filter_test_storage[ $hook_name ][ $priority ][] = [
			'function'      => $callback,
			'accepted_args' => $accepted_args,
		];
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook_name, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'has_filter' ) ) {
	function has_filter( $hook_name, $callback_to_check = false ) {
		global $wp_filter_test_storage;
		if ( ! isset( $wp_filter_test_storage[ $hook_name ] ) ) {
			return false;
		}
		if ( false === $callback_to_check ) {
			return true;
		}
		foreach ( $wp_filter_test_storage[ $hook_name ] as $priority => $callbacks ) {
			foreach ( $callbacks as $cb ) {
				if ( $cb['function'] === $callback_to_check ) {
					return $priority;
				}
			}
		}
		return false;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}

global $test_options;
$test_options = [];

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

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		global $test_options;
		$test_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp = null, $timezone = null ) {
		return date( $format, $timestamp ?? time() );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return is_string( $str ) ? trim( strip_tags( $str ) ) : '';
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( $text ) {
		return addcslashes( (string) $text, "\\'\"&\n\r<>" );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $text ) {
		return strip_tags( (string) $text, '<table><thead><tbody><tr><th><td><p><br><span><div><strong><em>' );
	}
}

global $test_can_manage, $test_doing_ajax;
$test_can_manage = true;
$test_doing_ajax = false;

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		global $test_can_manage;
		return $test_can_manage;
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax() {
		global $test_doing_ajax;
		return $test_doing_ajax;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		global $test_options;
		return $test_options[ '_transient_' . $transient ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		global $test_options;
		$test_options[ '_transient_' . $transient ] = $value;
		return true;
	}
}

/**
 * Minimal wpdb double that records queries and reports every table as missing.
 */
class Burst_MainWP_Test_Wpdb {
	public $prefix  = 'wp_';
	public $queries = [];

	public function prepare( $query, ...$args ) {
		return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
	}

	public function get_var( $query ) {
		$this->queries[] = $query;
		return null;
	}
}

require_once dirname( __DIR__, 2 ) . '/class/class-reports-integration.php';

class BurstMainWPReportsIntegrationTest extends TestCase {

	private \BurstMainWP\Reports_Integration $integration;

	protected function setUp(): void {
		parent::setUp();
		$this->integration = \BurstMainWP\Reports_Integration::instance();
		$this->integration->clear_cache();
	}

	public function test_instance_returns_singleton(): void {
		$instance1 = \BurstMainWP\Reports_Integration::instance();
		$instance2 = \BurstMainWP\Reports_Integration::instance();

		$this->assertInstanceOf( \BurstMainWP\Reports_Integration::class, $instance1 );
		$this->assertSame( $instance1, $instance2 );
	}

	public function test_hooks_are_registered(): void {
		$this->assertTrue( (bool) has_filter( 'mainwp_pro_reports_custom_tokens' ) );
		$this->assertTrue( (bool) has_filter( 'mainwp_client_reports_custom_tokens' ) );
		$this->assertTrue( (bool) has_filter( 'mainwp_client_reports_tokens_groups' ) );
		$this->assertTrue( (bool) has_filter( 'mainwp_client_reports_tokens_nav_top' ) );
		$this->assertTrue( (bool) has_filter( 'mainwp_pro_reports_tokens_groups' ) );
		$this->assertTrue( (bool) has_filter( 'mainwp_pro_reports_tokens_nav_top' ) );
	}

	public function test_register_token_group(): void {
		$existing_groups = [ 'other_plugin' => [ 'foo' => 'bar' ] ];
		$groups          = $this->integration->register_token_group( $existing_groups );

		$this->assertArrayHasKey( 'other_plugin', $groups );
		$this->assertArrayHasKey( 'burst', $groups );

		$burst_group = $groups['burst'];
		$this->assertArrayHasKey( 'nav_group_tokens', $burst_group );
		$this->assertArrayHasKey( 'summary', $burst_group );
		$this->assertArrayHasKey( 'tables', $burst_group );
		$this->assertArrayHasKey( 'pro', $burst_group );

		// Check key summary tokens.
		$summary_names = array_column( $burst_group['summary'], 'name' );
		$this->assertContains( 'burst.pageviews', $summary_names );
		$this->assertContains( 'burst.visitors', $summary_names );
		$this->assertContains( 'burst.sessions', $summary_names );
		$this->assertContains( 'burst.bounce.rate', $summary_names );
		$this->assertContains( 'burst.startdate', $summary_names );
		$this->assertContains( 'burst.enddate', $summary_names );

		// Check table tokens.
		$table_names = array_column( $burst_group['tables'], 'name' );
		$this->assertContains( 'burst.top.pages', $table_names );
		$this->assertContains( 'burst.top.referrers', $table_names );
		$this->assertContains( 'burst.devices', $table_names );
		$this->assertContains( 'burst.browsers', $table_names );
		$this->assertContains( 'burst.sales', $table_names );

		// Check Pro tokens.
		$pro_names = array_column( $burst_group['pro'], 'name' );
		$this->assertContains( 'burst.ai.summary', $pro_names );
		$this->assertContains( 'burst.ecommerce.revenue', $pro_names );
		$this->assertContains( 'burst.ecommerce.orders', $pro_names );
	}

	public function test_register_token_nav(): void {
		$existing_nav = [ 'client' => 'Client Tokens' ];
		$nav          = $this->integration->register_token_nav( $existing_nav );

		$this->assertArrayHasKey( 'client', $nav );
		$this->assertArrayHasKey( 'burst', $nav );
		$this->assertSame( 'Burst Statistics', $nav['burst'] );
	}

	public function test_populate_tokens_with_full_child_payload(): void {
		$date_from = 1727740800; // 2024-10-01
		$date_to   = 1730332800; // 2024-10-31

		$mock_data = [
			'metrics'              => [
				'pageviews'             => 12500,
				'pageviews_formatted'   => '12.5k',
				'visitors'              => 4200,
				'visitors_formatted'    => '4.2k',
				'sessions'              => 6100,
				'sessions_formatted'    => '6.1k',
				'bounces'               => 1500,
				'bounces_formatted'     => '1.5k',
				'bounce_rate_formatted' => '24.6%',
				'avg_time_formatted'    => '2m 15s',
				'first_time_formatted'  => '3.8k',
			],
			'tables'               => [
				'top_pages'     => '<table class="burst-report-table"><tr><td>/home</td><td>5000</td></tr></table>',
				'top_referrers' => '<table class="burst-report-table"><tr><td>google.com</td><td>3200</td></tr></table>',
				'devices'       => '<table class="burst-report-table"><tr><td>Desktop</td><td>60%</td></tr></table>',
				'browsers'      => '<table class="burst-report-table"><tr><td>Chrome</td><td>70%</td></tr></table>',
			],
			'pro'                  => [
				'is_pro'     => true,
				'ai_summary' => 'Traffic increased by 18% month over month with strong engagement.',
				'ecommerce'  => [
					'revenue' => '$14,250.00',
					'orders'  => '184',
				],
			],
			'start_date_formatted' => 'Oct 1, 2024',
			'end_date_formatted'   => 'Oct 31, 2024',
		];

		$tokens = $this->integration->populate_tokens( [], $mock_data, $date_from, $date_to );

		$this->assertSame( '12.5k', $tokens['[burst.pageviews]'] );
		$this->assertSame( '4.2k', $tokens['[burst.visitors]'] );
		$this->assertSame( '6.1k', $tokens['[burst.sessions]'] );
		$this->assertSame( '1.5k', $tokens['[burst.bounces]'] );
		$this->assertSame( '24.6%', $tokens['[burst.bounce.rate]'] );
		$this->assertSame( '2m 15s', $tokens['[burst.avg.time]'] );
		$this->assertSame( '3.8k', $tokens['[burst.first_time_visitors]'] );

		$this->assertSame( '<table class="burst-report-table"><tr><td>/home</td><td>5000</td></tr></table>', $tokens['[burst.top.pages]'] );
		$this->assertSame( '<table class="burst-report-table"><tr><td>google.com</td><td>3200</td></tr></table>', $tokens['[burst.top.referrers]'] );
		$this->assertSame( '<table class="burst-report-table"><tr><td>Desktop</td><td>60%</td></tr></table>', $tokens['[burst.devices]'] );
		$this->assertSame( '<table class="burst-report-table"><tr><td>Chrome</td><td>70%</td></tr></table>', $tokens['[burst.browsers]'] );

		$this->assertSame( 'Traffic increased by 18% month over month with strong engagement.', $tokens['[burst.ai.summary]'] );
		$this->assertSame( '$14,250.00', $tokens['[burst.ecommerce.revenue]'] );
		$this->assertSame( '184', $tokens['[burst.ecommerce.orders]'] );

		$this->assertSame( 'Oct 1, 2024', $tokens['[burst.startdate]'] );
		$this->assertSame( 'Oct 31, 2024', $tokens['[burst.enddate]'] );
	}

	public function test_populate_tokens_with_free_plugin_payload(): void {
		$date_from = 1727740800; // 2024-10-01
		$date_to   = 1730332800; // 2024-10-31

		$mock_free_data = [
			'metrics'              => [
				'pageviews_formatted'   => '5.0k',
				'visitors_formatted'    => '1.2k',
				'sessions_formatted'    => '2.1k',
				'bounces_formatted'     => '400',
				'bounce_rate_formatted' => '19.0%',
				'avg_time_formatted'    => '1m 10s',
				'first_time_formatted'  => '950',
			],
			'tables'               => [
				'top_pages'     => '<table class="burst-report-table"><tr><td>/about</td><td>500</td></tr></table>',
				'top_referrers' => '<table class="burst-report-table"><tr><td>bing.com</td><td>150</td></tr></table>',
				'devices'       => '<table class="burst-report-table"><tr><td>Mobile</td><td>80%</td></tr></table>',
				'browsers'      => '<table class="burst-report-table"><tr><td>Safari</td><td>60%</td></tr></table>',
			],
			'pro'                  => [],
			'start_date_formatted' => '2024-10-01',
			'end_date_formatted'   => '2024-10-31',
		];

		$tokens = $this->integration->populate_tokens( [], $mock_free_data, $date_from, $date_to );

		// Free metrics and tables populate correctly.
		$this->assertSame( '5.0k', $tokens['[burst.pageviews]'] );
		$this->assertSame( '1.2k', $tokens['[burst.visitors]'] );
		$this->assertSame( '2.1k', $tokens['[burst.sessions]'] );
		$this->assertSame( '400', $tokens['[burst.bounces]'] );
		$this->assertSame( '19.0%', $tokens['[burst.bounce.rate]'] );
		$this->assertSame( '1m 10s', $tokens['[burst.avg.time]'] );
		$this->assertSame( '950', $tokens['[burst.first_time_visitors]'] );
		$this->assertSame( '<table class="burst-report-table"><tr><td>/about</td><td>500</td></tr></table>', $tokens['[burst.top.pages]'] );
		$this->assertSame( '<table class="burst-report-table"><tr><td>bing.com</td><td>150</td></tr></table>', $tokens['[burst.top.referrers]'] );
		$this->assertSame( '<table class="burst-report-table"><tr><td>Mobile</td><td>80%</td></tr></table>', $tokens['[burst.devices]'] );
		$this->assertSame( '<table class="burst-report-table"><tr><td>Safari</td><td>60%</td></tr></table>', $tokens['[burst.browsers]'] );

		// Pro tokens are safely empty for Free child sites.
		$this->assertSame( '', $tokens['[burst.ai.summary]'] );
		$this->assertSame( '', $tokens['[burst.ecommerce.revenue]'] );
		$this->assertSame( '', $tokens['[burst.ecommerce.orders]'] );
	}

	public function test_populate_tokens_with_null_payload_fallback(): void {
		$date_from = 1727740800; // 2024-10-01
		$date_to   = 1730332800; // 2024-10-31

		$tokens = $this->integration->populate_tokens( [], null, $date_from, $date_to );

		// Metrics should be empty rather than showing false zeros.
		$this->assertSame( '', $tokens['[burst.pageviews]'] );
		$this->assertSame( '', $tokens['[burst.visitors]'] );
		$this->assertSame( '', $tokens['[burst.sessions]'] );
		$this->assertSame( '', $tokens['[burst.bounces]'] );
		$this->assertSame( '', $tokens['[burst.bounce.rate]'] );
		$this->assertSame( '', $tokens['[burst.avg.time]'] );
		$this->assertSame( '', $tokens['[burst.first_time_visitors]'] );

		$this->assertSame( '', $tokens['[burst.top.pages]'] );
		$this->assertSame( '', $tokens['[burst.top.referrers]'] );
		$this->assertSame( '', $tokens['[burst.devices]'] );
		$this->assertSame( '', $tokens['[burst.browsers]'] );
		$this->assertSame( '', $tokens['[burst.sales]'] );
		$this->assertSame( '', $tokens['[burst.ai.summary]'] );
		$this->assertSame( '', $tokens['[burst.ecommerce.revenue]'] );
		$this->assertSame( '', $tokens['[burst.ecommerce.orders]'] );

		$this->assertSame( '2024-10-01', $tokens['[burst.startdate]'] );
		$this->assertSame( '2024-10-31', $tokens['[burst.enddate]'] );
	}

	public function test_populate_tokens_with_sales(): void {
		$date_from = 1727740800;
		$date_to   = 1730332800;

		$payload = [
			'metrics'              => [
				'pageviews_formatted' => '100',
			],
			'tables'               => [
				'sales' => '<table class="burst-report-table"><tr><td>Revenue</td><td>$500.00</td></tr></table>',
			],
			'pro'                  => [
				'sales' => [
					'revenue' => '$500.00',
					'orders'  => '12',
				],
			],
			'start_date_formatted' => '2024-10-01',
			'end_date_formatted'   => '2024-10-31',
		];

		$tokens = $this->integration->populate_tokens( [], $payload, $date_from, $date_to );

		$this->assertSame( '$500.00', $tokens['[burst.ecommerce.revenue]'] );
		$this->assertSame( '12', $tokens['[burst.ecommerce.orders]'] );
		$this->assertSame( '<table class="burst-report-table"><tr><td>Revenue</td><td>$500.00</td></tr></table>', $tokens['[burst.sales]'] );
	}

	public function test_filter_pro_reports_tokens_skips_when_no_burst_tokens(): void {
		$initial_tokens = [ '[some.other.token]' => 'value' ];
		$report         = (object) [
			'header'    => 'Hello world',
			'body'      => 'No burst here',
			'footer'    => 'Footer',
			'date_from' => 1727740800,
			'date_to'   => 1730332800,
		];
		$website        = (object) [ 'id' => 1 ];

		$result = $this->integration->filter_pro_reports_tokens( $initial_tokens, $report, $website );
		$this->assertSame( $initial_tokens, $result );
	}

	public function test_filter_pro_reports_tokens_with_invalid_website_returns_untouched(): void {
		$initial_tokens = [ '[some.other.token]' => 'value' ];

		// Website with no ID.
		$result = $this->integration->filter_pro_reports_tokens( $initial_tokens, null, (object) [] );
		$this->assertSame( $initial_tokens, $result );

		// Website as array with 0 ID.
		$result = $this->integration->filter_pro_reports_tokens( $initial_tokens, null, [ 'id' => 0 ] );
		$this->assertSame( $initial_tokens, $result );

		// Non-object non-array.
		$result = $this->integration->filter_pro_reports_tokens( $initial_tokens, null, null );
		$this->assertSame( $initial_tokens, $result );
	}

	public function test_print_tinymce_token_scripts_skips_when_not_on_report_page(): void {
		$_GET['page'] = 'some-other-page';
		$_GET['tab']  = 'report';

		ob_start();
		$this->integration->print_tinymce_token_scripts();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
		unset( $_GET['page'], $_GET['tab'] );
	}

	public function test_print_tinymce_token_scripts_outputs_burst_menu_on_report_page(): void {
		$_GET['page'] = 'Extensions-Mainwp-Client-Reports-Extension';
		$_GET['tab']  = 'report';

		ob_start();
		$this->integration->print_tinymce_token_scripts();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'tinymce-editor-setup', $output );
		$this->assertStringContainsString( 'insertreporttoken', $output );
		$this->assertStringContainsString( 'Burst Statistics', $output );
		$this->assertStringContainsString( '[burst.visitors]', $output );
		$this->assertStringContainsString( '[burst.top.pages]', $output );
		$this->assertStringContainsString( '[burst.ai.summary]', $output );

		unset( $_GET['page'], $_GET['tab'] );
	}

	public function test_starter_template_getters(): void {
		$header = $this->integration->get_starter_template_header();
		$body   = $this->integration->get_starter_template_body();
		$footer = $this->integration->get_starter_template_footer();

		$this->assertStringContainsString( 'Burst Statistics Performance Report', $header );
		$this->assertStringContainsString( '[burst.startdate]', $header );
		$this->assertStringContainsString( '[burst.enddate]', $header );

		$this->assertStringContainsString( '[burst.visitors]', $body );
		$this->assertStringContainsString( '[burst.pageviews]', $body );
		$this->assertStringContainsString( '[burst.sessions]', $body );
		$this->assertStringContainsString( '[burst.bounce.rate]', $body );
		$this->assertStringContainsString( '[burst.top.pages]', $body );
		$this->assertStringContainsString( '[burst.top.referrers]', $body );
		$this->assertStringContainsString( '[burst.devices]', $body );
		$this->assertStringContainsString( '[burst.browsers]', $body );
		$this->assertStringContainsString( '[burst.sales]', $body );
		$this->assertStringContainsString( '[burst.ai.summary]', $body );

		$this->assertStringContainsString( 'Generated automatically via MainWP', $footer );
	}

	public function test_maybe_seed_starter_templates_early_return_when_option_set(): void {
		global $test_options;
		$test_options['burst_mainwp_starter_report_created'] = 1;

		// Should return immediately without querying or failing.
		$this->integration->maybe_seed_starter_templates();
		$this->assertSame( 1, $test_options['burst_mainwp_starter_report_created'] );

		unset( $test_options['burst_mainwp_starter_report_created'] );
	}

	public function test_maybe_seed_starter_templates_skips_users_without_manage_options_and_ajax(): void {
		global $wpdb, $test_can_manage, $test_doing_ajax, $test_options;
		$wpdb = new Burst_MainWP_Test_Wpdb();
		unset( $test_options['_transient_burst_mainwp_client_reports_missing'] );

		$test_can_manage = false;
		$this->integration->maybe_seed_starter_templates();
		$test_can_manage = true;
		$test_doing_ajax = true;
		$this->integration->maybe_seed_starter_templates();
		$test_doing_ajax = false;

		$this->assertSame( [], $wpdb->queries );
	}

	public function test_maybe_seed_starter_templates_checks_missing_client_reports_once_per_day(): void {
		global $wpdb, $test_options;
		$wpdb = new Burst_MainWP_Test_Wpdb();
		unset( $test_options['_transient_burst_mainwp_client_reports_missing'] );

		for ( $i = 0; $i < 100; $i++ ) {
			$this->integration->maybe_seed_starter_templates();
		}

		$this->assertCount( 1, $wpdb->queries );
		unset( $test_options['_transient_burst_mainwp_client_reports_missing'] );
	}
}
