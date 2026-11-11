<?php
/**
 * Burst Statistics – MainWP Reporting Integration
 *
 * Integrates Burst Statistics metrics and tables into MainWP Pro Reports
 * and Client Reports via custom template tokens.
 *
 * @package Burst_Statistics_MainWP
 */

namespace BurstMainWP;

defined( 'ABSPATH' ) || exit;

class Reports_Integration {

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = null;

	/**
	 * In-memory cache for child report payloads to prevent duplicate HTTP calls
	 * within a single report generation batch.
	 *
	 * Keyed by "site_{site_id}_{start_date}_{end_date}".
	 *
	 * @var array<string, array<string, mixed>|null> Mixed: cached report payload contains nested metric values and tables.
	 */
	private array $cache = [];

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Register hooks.
	 */
	private function __construct() {
		// Modern MainWP Pro Reports filter.
		add_filter( 'mainwp_pro_reports_custom_tokens', [ $this, 'filter_pro_reports_tokens' ], 10, 3 );

		// Legacy MainWP Client Reports filter.
		add_filter( 'mainwp_client_reports_custom_tokens', [ $this, 'filter_pro_reports_tokens' ], 10, 3 );

		// Register token groups and navigation in MainWP reports token modal.
		add_filter( 'mainwp_client_reports_tokens_groups', [ $this, 'register_token_group' ], 10, 1 );
		add_filter( 'mainwp_client_reports_tokens_nav_top', [ $this, 'register_token_nav' ], 10, 1 );
		add_filter( 'mainwp_pro_reports_tokens_groups', [ $this, 'register_token_group' ], 10, 1 );
		add_filter( 'mainwp_pro_reports_tokens_nav_top', [ $this, 'register_token_nav' ], 10, 1 );

		// Inject Burst Statistics tokens into the TinyMCE "Insert Tokens" toolbar dropdown.
		add_action( 'admin_print_footer_scripts', [ $this, 'print_tinymce_token_scripts' ], 20 );

		// Seed starter report and format templates if not already created.
		add_action( 'admin_init', [ $this, 'maybe_seed_starter_templates' ] );
	}

	/**
	 * Clear the in-process report cache (useful during testing).
	 */
	public function clear_cache(): void {
		$this->cache = [];
	}

	/**
	 * Populate Burst Statistics tokens for MainWP Pro Reports and Client Reports.
	 *
	 * @param array<string, string> $tokens_values Associative array of token replacements.
	 * @param mixed                 $report        Report details. Mixed: MainWP passes either a stdClass object or array with date_from/date_to.
	 * @param mixed                 $website       Child website row. Mixed: MainWP passes either a stdClass object or array with site id.
	 * @return array<string, string> Updated token values array.
	 */
	public function filter_pro_reports_tokens( array $tokens_values, mixed $report, mixed $website ): array {
		$site_id = 0;
		if ( is_object( $website ) && isset( $website->id ) ) {
			$site_id = absint( $website->id );
		} elseif ( is_array( $website ) && isset( $website['id'] ) ) {
			$site_id = absint( $website['id'] );
		}

		if ( 0 === $site_id ) {
			return $tokens_values;
		}

		if ( ! $this->report_contains_burst_tokens( $report ) ) {
			return $tokens_values;
		}

		$date_from = 0;
		$date_to   = 0;
		if ( is_object( $report ) ) {
			$date_from = isset( $report->date_from ) ? absint( $report->date_from ) : 0;
			$date_to   = isset( $report->date_to ) ? absint( $report->date_to ) : 0;
		} elseif ( is_array( $report ) ) {
			$date_from = isset( $report['date_from'] ) ? absint( $report['date_from'] ) : 0;
			$date_to   = isset( $report['date_to'] ) ? absint( $report['date_to'] ) : 0;
		}

		$day_in_seconds = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
		if ( 0 === $date_to ) {
			$date_to = time();
		}
		if ( 0 === $date_from || $date_from > $date_to ) {
			$date_from = $date_to - ( 30 * $day_in_seconds );
		}

		$fetch_date_to = (int) strtotime( gmdate( 'Y-m-d', $date_to ) . ' 23:59:59 UTC' );
		$data          = $this->get_report_data( $site_id, $date_from, $fetch_date_to );

		return $this->populate_tokens( $tokens_values, $data, $date_from, $date_to );
	}

	/**
	 * Populate token values array from child site payload or safe fallbacks.
	 *
	 * When the fetch fails ($data is null or empty), summary metrics and table
	 * tokens are left empty rather than displaying false zeros.
	 *
	 * @param array<string, string>     $tokens_values Existing token replacements.
	 * @param array<string, mixed>|null $data          Child site report data, or null on failure. Mixed: child response contains metrics, tables, and pro data arrays.
	 * @param int                       $date_from     Start timestamp.
	 * @param int                       $date_to       End timestamp.
	 * @return array<string, string>
	 */
	public function populate_tokens( array $tokens_values, ?array $data, int $date_from, int $date_to ): array {
		$metrics = is_array( $data['metrics'] ?? null ) ? $data['metrics'] : [];
		$tables  = is_array( $data['tables'] ?? null ) ? $data['tables'] : [];
		$pro     = is_array( $data['pro'] ?? null ) ? $data['pro'] : [];

		$date_format = (string) get_option( 'date_format', 'Y-m-d' );
		$start_date  = $data['start_date_formatted'] ?? wp_date( $date_format, $date_from );
		$end_date    = $data['end_date_formatted'] ?? wp_date( $date_format, $date_to );

		// Summary metrics tokens (mapped via [formatted key, raw key, default]).
		// If $data is null or empty, metrics are empty rather than showing 0.
		$is_valid_payload = ! empty( $data ) && ( ! empty( $metrics ) || ! empty( $tables ) );
		$metric_tokens    = [
			'[burst.pageviews]'           => [ 'pageviews_formatted', 'pageviews', '' ],
			'[burst.visitors]'            => [ 'visitors_formatted', 'visitors', '' ],
			'[burst.sessions]'            => [ 'sessions_formatted', 'sessions', '' ],
			'[burst.bounces]'             => [ 'bounces_formatted', 'bounces', '' ],
			'[burst.bounce.rate]'         => [ 'bounce_rate_formatted', 'bounce_rate', '' ],
			'[burst.avg.time]'            => [ 'avg_time_formatted', 'avg_time_seconds', '' ],
			'[burst.first_time_visitors]' => [ 'first_time_formatted', 'first_time_visitors', '' ],
		];

		foreach ( $metric_tokens as $token => [ $formatted_key, $raw_key, $default ] ) {
			if ( ! $is_valid_payload ) {
				$tokens_values[ $token ] = '';
				continue;
			}

			$val = isset( $metrics[ $formatted_key ] )
				? (string) $metrics[ $formatted_key ]
				: ( isset( $metrics[ $raw_key ] ) ? (string) $metrics[ $raw_key ] : $default );

			$tokens_values[ $token ] = esc_html( $val );
		}

		// Tabular data tokens (sanitized with wp_kses_post).
		$tokens_values['[burst.top.pages]']     = isset( $tables['top_pages'] ) ? wp_kses_post( (string) $tables['top_pages'] ) : '';
		$tokens_values['[burst.top.referrers]'] = isset( $tables['top_referrers'] ) ? wp_kses_post( (string) $tables['top_referrers'] ) : '';
		$tokens_values['[burst.devices]']       = isset( $tables['devices'] ) ? wp_kses_post( (string) $tables['devices'] ) : '';
		$tokens_values['[burst.browsers]']      = isset( $tables['browsers'] ) ? wp_kses_post( (string) $tables['browsers'] ) : '';
		$tokens_values['[burst.sales]']         = isset( $tables['sales'] ) ? wp_kses_post( (string) $tables['sales'] ) : '';

		// Pro features tokens: AI summary (HTML allowed via wp_kses_post).
		$tokens_values['[burst.ai.summary]'] = isset( $pro['ai_summary'] ) ? wp_kses_post( (string) $pro['ai_summary'] ) : '';

		// Pro ecommerce tokens: read directly from pro.sales or pro.ecommerce.
		$sales   = is_array( $pro['sales'] ?? null ) ? $pro['sales'] : ( is_array( $pro['ecommerce'] ?? null ) ? $pro['ecommerce'] : null );
		$revenue = is_array( $sales ) ? (string) ( $sales['revenue'] ?? '' ) : '';
		$orders  = is_array( $sales ) ? (string) ( $sales['orders'] ?? '' ) : '';

		$tokens_values['[burst.ecommerce.revenue]'] = esc_html( $revenue );
		$tokens_values['[burst.ecommerce.orders]']  = esc_html( $orders );

		// Date tokens.
		$tokens_values['[burst.startdate]'] = esc_html( (string) $start_date );
		$tokens_values['[burst.enddate]']   = esc_html( (string) $end_date );

		return $tokens_values;
	}

	/**
	 * Register Burst Statistics token group in MainWP reports tokens manager.
	 *
	 * @param array<string, mixed> $groups Existing token groups. Mixed: MainWP groups array contains nested tabs and token label arrays.
	 * @return array<string, mixed> Updated token groups. Mixed: returns the token groups array with Burst tokens included.
	 */
	public function register_token_group( array $groups ): array {
		$groups['burst'] = [
			'nav_group_tokens' => [
				'summary' => __( 'Summary', 'burst-mainwp' ),
				'tables'  => __( 'Tables', 'burst-mainwp' ),
				'pro'     => __( 'Pro Features', 'burst-mainwp' ),
			],
			'summary'          => [
				[
					'name' => 'burst.pageviews',
					'desc' => __( 'Total Pageviews in the selected date range', 'burst-mainwp' ),
				],
				[
					'name' => 'burst.visitors',
					'desc' => __( 'Unique Visitors in the selected date range', 'burst-mainwp' ),
				],
				[
					'name' => 'burst.sessions',
					'desc' => __( 'Total Sessions', 'burst-mainwp' ),
				],
				[
					'name' => 'burst.bounce.rate',
					'desc' => __( 'Bounce Rate percentage (e.g. 24.5%)', 'burst-mainwp' ),
				],
				[
					'name' => 'burst.bounces',
					'desc' => __( 'Total Bounced Sessions count', 'burst-mainwp' ),
				],
				[
					'name' => 'burst.avg.time',
					'desc' => __( 'Average Time on Page (e.g. 2m 14s)', 'burst-mainwp' ),
				],
				[
					'name' => 'burst.first_time_visitors',
					'desc' => __( 'First Time Visitors count', 'burst-mainwp' ),
				],
				[
					'name' => 'burst.startdate',
					'desc' => __( 'Report Start Date', 'burst-mainwp' ),
				],
				[
					'name' => 'burst.enddate',
					'desc' => __( 'Report End Date', 'burst-mainwp' ),
				],
			],
			'tables'           => [
				[
					'name' => 'burst.top.pages',
					'desc' => __( 'Top Visited Pages (HTML table)', 'burst-mainwp' ),
				],
				[
					'name' => 'burst.top.referrers',
					'desc' => __( 'Top Referrers / Traffic Sources (HTML table)', 'burst-mainwp' ),
				],
				[
					'name' => 'burst.devices',
					'desc' => __( 'Devices Breakdown: Desktop, Mobile, Tablet (HTML table)', 'burst-mainwp' ),
				],
				[
					'name' => 'burst.browsers',
					'desc' => __( 'Browsers Breakdown: Chrome, Safari, etc. (HTML table)', 'burst-mainwp' ),
				],
				[
					'name' => 'burst.sales',
					'desc' => __( 'Sales Breakdown: Revenue, Orders, Conversion rate, etc. (HTML table)', 'burst-mainwp' ),
				],
			],
			'pro'              => [
				[
					'name' => 'burst.ai.summary',
					'desc' => __( 'AI-Generated Performance Summary (Burst Pro)', 'burst-mainwp' ),
				],
				[
					'name' => 'burst.ecommerce.revenue',
					'desc' => __( 'Total Ecommerce Revenue (Burst Pro)', 'burst-mainwp' ),
				],
				[
					'name' => 'burst.ecommerce.orders',
					'desc' => __( 'Total Ecommerce Orders count (Burst Pro)', 'burst-mainwp' ),
				],
			],
		];

		return $groups;
	}

	/**
	 * Register Burst Statistics in the top navigation of MainWP reports tokens manager.
	 *
	 * @param array<string, string> $nav Existing navigation items.
	 * @return array<string, string>
	 */
	public function register_token_nav( array $nav ): array {
		$nav['burst'] = __( 'Burst Statistics', 'burst-mainwp' );
		return $nav;
	}

	/**
	 * Inject Burst Statistics tokens into the TinyMCE "Insert Tokens" toolbar dropdown.
	 *
	 * MainWP Client Reports hardcodes its TinyMCE toolbar menubutton in includes/functions.php
	 * without a filter hook. By listening to the 'tinymce-editor-setup' event after MainWP's
	 * script registers the button, we append a 'Burst Statistics' submenu holding all our
	 * tokens, making them directly selectable from the editor toolbar dropdown.
	 */
	public function print_tinymce_token_scripts(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = sanitize_text_field( wp_unslash( $_GET['tab'] ?? '' ) );

		$is_client_reports = 'Extensions-Mainwp-Client-Reports-Extension' === $page && 'report' === $tab;
		$is_pro_reports    = 'Extensions-Mainwp-Pro-Reports-Extension' === $page && ( 'report' === $tab || empty( $tab ) );

		if ( ! $is_client_reports && ! $is_pro_reports ) {
			return;
		}

		$groups = $this->register_token_group( [] );
		if ( empty( $groups['burst'] ) || ! is_array( $groups['burst'] ) ) {
			return;
		}

		$burst_group = $groups['burst'];
		$menu_items  = [];

		$labels = [
			'summary' => __( 'Summary', 'burst-mainwp' ),
			'tables'  => __( 'Tables', 'burst-mainwp' ),
			'pro'     => __( 'Pro Features', 'burst-mainwp' ),
		];

		foreach ( $labels as $key => $title ) {
			if ( empty( $burst_group[ $key ] ) || ! is_array( $burst_group[ $key ] ) ) {
				continue;
			}
			$sub_menu = [];
			foreach ( $burst_group[ $key ] as $token ) {
				$name       = '[' . $token['name'] . ']';
				$sub_menu[] = [
					'text'  => $name . ' - ' . $token['desc'],
					'token' => $name,
				];
			}
			$menu_items[] = [
				'text' => $title,
				'menu' => $sub_menu,
			];
		}

		$burst_menu_json = wp_json_encode( $menu_items );
		?>
		<script type="text/javascript">
		jQuery(document).on('tinymce-editor-setup', function (event, editor) {
			var burstMenuRaw = <?php echo $burst_menu_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
			if (!Array.isArray(burstMenuRaw)) {
				return;
			}
			var burstMenu = burstMenuRaw.map(function (section) {
				return {
					text: section.text,
					menu: section.menu.map(function (item) {
						return {
							text: item.text,
							onclick: function () {
								editor.insertContent(item.token);
							}
						};
					})
				};
			});

			if (editor.buttons) {
				var targetBtn = editor.buttons.insertreporttoken || editor.buttons.insertproreporttoken;
				if (targetBtn && targetBtn.menu) {
					targetBtn.menu.push({
						text: '<?php echo esc_js( __( 'Burst Statistics', 'burst-mainwp' ) ); ?>',
						menu: burstMenu
					});
				}
			}
		});
		</script>
		<?php
	}

	/**
	 * Seed the default Burst Statistics report and format templates into MainWP.
	 *
	 * Runs on admin_init. If the starter report has not been created yet, this
	 * inserts a ready-to-use "Burst Statistics Performance Report" and its
	 * corresponding Header, Body, and Footer format templates into MainWP Client Reports.
	 */
	public function maybe_seed_starter_templates(): void {
		// Seeding writes report templates: only for admins on a regular page load, never on admin-ajax.
		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( (bool) get_option( 'burst_mainwp_starter_report_created' ) ) {
			return;
		}

		// Client Reports was missing at the last check: look again once a day, not on every admin page.
		if ( false !== get_transient( 'burst_mainwp_client_reports_missing' ) ) {
			return;
		}

		global $wpdb;

		$client_report_table = $wpdb->prefix . 'mainwp_client_report';
		// Check if MainWP Client Reports table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $client_report_table ) );
		if ( empty( $table_exists ) ) {
			set_transient( 'burst_mainwp_client_reports_missing', 1, DAY_IN_SECONDS );
			return;
		}

		$title = __( 'Burst Statistics Performance Report', 'burst-mainwp' );

		// Seed Formats (Templates) into client_report_format table if available.
		$format_table = $wpdb->prefix . 'mainwp_client_report_format';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$format_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $format_table ) );

		if ( ! empty( $format_table_exists ) ) {
			$formats = [
				[
					'title'   => $title . ' Header',
					'type'    => 'H',
					'content' => $this->get_starter_template_header(),
				],
				[
					'title'   => $title,
					'type'    => 'B',
					'content' => $this->get_starter_template_body(),
				],
				[
					'title'   => $title . ' Footer',
					'type'    => 'F',
					'content' => $this->get_starter_template_footer(),
				],
			];

			foreach ( $formats as $format ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$format_table} WHERE title = %s AND type = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$format['title'],
						$format['type']
					)
				);
				if ( ! $exists ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->insert( $format_table, $format );
				}
			}
		}

		// Check if a report with this title already exists in client_report table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$report_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$client_report_table} WHERE title = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$title
			)
		);

		if ( ! $report_exists ) {
			$now       = time();
			$date_from = $now - ( 30 * DAY_IN_SECONDS );
			$date_to   = $now;

			$new_report = [
				'title'               => $title,
				'date_from'           => $date_from,
				'date_to'             => $date_to,
				'date_from_nextsend'  => 0,
				'date_to_nextsend'    => 0,
				'client_id'           => 0,
				'header'              => $this->get_starter_template_header(),
				'body'                => $this->get_starter_template_body(),
				'footer'              => $this->get_starter_template_footer(),
				'subject'             => sprintf(
					/* translators: %s: site name token */
					__( 'Monthly Performance Report for %s', 'burst-mainwp' ),
					'[site.name]'
				),
				'recurring_schedule'  => '',
				'schedule_send_email' => '',
				'schedule_bcc_me'     => 0,
				'scheduled'           => 0,
				'schedule_nextsend'   => 0,
				'schedule_lastsend'   => 0,
				'completed'           => 0,
				'completed_sites'     => '',
				'retry_counter'       => 0,
				'sending_errors'      => '',
				'is_archived'         => 0,
				'sites'               => '',
				'groups'              => '',
				'attach_files'        => '',
			];

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert( $client_report_table, $new_report );
		}

		update_option( 'burst_mainwp_starter_report_created', 1, false );
	}

	/**
	 * Default starter template header HTML.
	 *
	 * @return string HTML content.
	 */
	public function get_starter_template_header(): string {
		return '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 24px;">' . "\n"
			. '<h1 style="color: #21ba45; margin: 0 0 8px 0; font-size: 28px; font-weight: 700;">' . esc_html__( 'Burst Statistics Performance Report', 'burst-mainwp' ) . '</h1>' . "\n"
			. '<p style="font-size: 14px; color: #666; margin: 0 0 16px 0; line-height: 1.5;">' . "\n"
			. '<strong>' . esc_html__( 'Website:', 'burst-mainwp' ) . '</strong> [site.name] ([site.url])<br/>' . "\n"
			. '<strong>' . esc_html__( 'Reporting Period:', 'burst-mainwp' ) . '</strong> [burst.startdate] – [burst.enddate]' . "\n"
			. '</p>' . "\n"
			. '<hr style="border: none; border-top: 1px solid #e0e0e0; margin: 20px 0;" />' . "\n"
			. '</div>';
	}

	/**
	 * Default starter template body HTML with Burst Statistics tokens.
	 *
	 * @return string HTML content.
	 */
	public function get_starter_template_body(): string {
		return '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 0 24px;">' . "\n"
			. '<div style="background: #f8f9fa; border-left: 4px solid #21ba45; padding: 16px 20px; margin-bottom: 28px; border-radius: 4px;">' . "\n"
			. '<h3 style="margin: 0 0 8px 0; color: #222; font-size: 16px; font-weight: 600;">' . esc_html__( 'Executive Summary', 'burst-mainwp' ) . '</h3>' . "\n"
			. '<div style="color: #444; font-size: 14px; line-height: 1.6;">[burst.ai.summary]</div>' . "\n"
			. '</div>' . "\n"
			. '<h2 style="color: #222; font-size: 18px; font-weight: 600; border-bottom: 2px solid #eee; padding-bottom: 8px; margin: 0 0 16px 0;">' . esc_html__( 'Traffic Overview', 'burst-mainwp' ) . '</h2>' . "\n"
			. '<table style="width: 100%; border-collapse: collapse; margin-bottom: 28px;">' . "\n"
			. '<tbody>' . "\n"
			. '<tr>' . "\n"
			. '<td style="width: 25%; padding: 14px 8px; background: #fafafa; border: 1px solid #eee; text-align: center;"><span style="font-size: 24px; font-weight: 700; color: #21ba45; display: block; line-height: 1.2;">[burst.visitors]</span><span style="font-size: 12px; color: #777; text-transform: uppercase;">' . esc_html__( 'Visitors', 'burst-mainwp' ) . '</span></td>' . "\n"
			. '<td style="width: 25%; padding: 14px 8px; background: #fafafa; border: 1px solid #eee; text-align: center;"><span style="font-size: 24px; font-weight: 700; color: #2185d0; display: block; line-height: 1.2;">[burst.pageviews]</span><span style="font-size: 12px; color: #777; text-transform: uppercase;">' . esc_html__( 'Pageviews', 'burst-mainwp' ) . '</span></td>' . "\n"
			. '<td style="width: 25%; padding: 14px 8px; background: #fafafa; border: 1px solid #eee; text-align: center;"><span style="font-size: 24px; font-weight: 700; color: #6435c9; display: block; line-height: 1.2;">[burst.sessions]</span><span style="font-size: 12px; color: #777; text-transform: uppercase;">' . esc_html__( 'Sessions', 'burst-mainwp' ) . '</span></td>' . "\n"
			. '<td style="width: 25%; padding: 14px 8px; background: #fafafa; border: 1px solid #eee; text-align: center;"><span style="font-size: 24px; font-weight: 700; color: #f2711c; display: block; line-height: 1.2;">[burst.bounce.rate]</span><span style="font-size: 12px; color: #777; text-transform: uppercase;">' . esc_html__( 'Bounce Rate', 'burst-mainwp' ) . '</span></td>' . "\n"
			. '</tr>' . "\n"
			. '<tr>' . "\n"
			. '<td style="padding: 12px 8px; background: #fafafa; border: 1px solid #eee; text-align: center;"><span style="font-size: 18px; font-weight: 600; color: #333; display: block;">[burst.first_time_visitors]</span><span style="font-size: 11px; color: #777; text-transform: uppercase;">' . esc_html__( 'New Visitors', 'burst-mainwp' ) . '</span></td>' . "\n"
			. '<td style="padding: 12px 8px; background: #fafafa; border: 1px solid #eee; text-align: center;"><span style="font-size: 18px; font-weight: 600; color: #333; display: block;">[burst.avg.time]</span><span style="font-size: 11px; color: #777; text-transform: uppercase;">' . esc_html__( 'Avg. Time', 'burst-mainwp' ) . '</span></td>' . "\n"
			. '<td style="padding: 12px 8px; background: #fafafa; border: 1px solid #eee; text-align: center;"><span style="font-size: 18px; font-weight: 600; color: #333; display: block;">[burst.ecommerce.revenue]</span><span style="font-size: 11px; color: #777; text-transform: uppercase;">' . esc_html__( 'Revenue', 'burst-mainwp' ) . '</span></td>' . "\n"
			. '<td style="padding: 12px 8px; background: #fafafa; border: 1px solid #eee; text-align: center;"><span style="font-size: 18px; font-weight: 600; color: #333; display: block;">[burst.ecommerce.orders]</span><span style="font-size: 11px; color: #777; text-transform: uppercase;">' . esc_html__( 'Orders', 'burst-mainwp' ) . '</span></td>' . "\n"
			. '</tr>' . "\n"
			. '</tbody>' . "\n"
			. '</table>' . "\n"
			. '<h2 style="color: #222; font-size: 18px; font-weight: 600; border-bottom: 2px solid #eee; padding-bottom: 8px; margin: 0 0 16px 0;">' . esc_html__( 'Top Pages', 'burst-mainwp' ) . '</h2>' . "\n"
			. '<div style="margin-bottom: 28px;">[burst.top.pages]</div>' . "\n"
			. '<h2 style="color: #222; font-size: 18px; font-weight: 600; border-bottom: 2px solid #eee; padding-bottom: 8px; margin: 0 0 16px 0;">' . esc_html__( 'Top Referrers', 'burst-mainwp' ) . '</h2>' . "\n"
			. '<div style="margin-bottom: 28px;">[burst.top.referrers]</div>' . "\n"
			. '<h2 style="color: #222; font-size: 18px; font-weight: 600; border-bottom: 2px solid #eee; padding-bottom: 8px; margin: 0 0 16px 0;">' . esc_html__( 'Technology & Devices', 'burst-mainwp' ) . '</h2>' . "\n"
			. '<table style="width: 100%; border-collapse: collapse; margin-bottom: 28px;">' . "\n"
			. '<tbody>' . "\n"
			. '<tr>' . "\n"
			. '<td style="width: 50%; vertical-align: top; padding-right: 12px;"><h3 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #555;">' . esc_html__( 'Devices', 'burst-mainwp' ) . '</h3>[burst.devices]</td>' . "\n"
			. '<td style="width: 50%; vertical-align: top; padding-left: 12px;"><h3 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #555;">' . esc_html__( 'Browsers', 'burst-mainwp' ) . '</h3>[burst.browsers]</td>' . "\n"
			. '</tr>' . "\n"
			. '</tbody>' . "\n"
			. '</table>' . "\n"
			. '<h2 style="color: #222; font-size: 18px; font-weight: 600; border-bottom: 2px solid #eee; padding-bottom: 8px; margin: 0 0 16px 0;">' . esc_html__( 'Ecommerce Sales Performance', 'burst-mainwp' ) . '</h2>' . "\n"
			. '<div style="margin-bottom: 28px;">[burst.sales]</div>' . "\n"
			. '</div>';
	}

	/**
	 * Default starter template footer HTML.
	 *
	 * @return string HTML content.
	 */
	public function get_starter_template_footer(): string {
		return '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 800px; margin: 24px auto 0; padding: 20px; border-top: 1px solid #e0e0e0; font-size: 12px; color: #888; text-align: center;">' . "\n"
			. esc_html__( 'Generated automatically via MainWP & Burst Statistics.', 'burst-mainwp' ) . "\n"
			. '</div>';
	}

	/**
	 * Check whether a report contains any Burst Statistics tokens.
	 *
	 * When the report content is inspectable and contains no Burst tokens,
	 * skipping the remote fetch prevents unnecessary HTTP latency, query load,
	 * and accidental paid AI summary generation on unrelated reports.
	 *
	 * @param mixed $report MainWP report object or array. Mixed: MainWP passes either a stdClass object, associative array, or null depending on the report extension version.
	 * @return bool True if Burst tokens may be needed; false otherwise.
	 */
	private function report_contains_burst_tokens( mixed $report ): bool {
		if ( ! is_object( $report ) && ! is_array( $report ) ) {
			return true;
		}

		$content_to_check = '';
		if ( is_object( $report ) ) {
			$content_to_check .= ( $report->header ?? '' ) . ' ';
			$content_to_check .= ( $report->body ?? '' ) . ' ';
			$content_to_check .= ( $report->footer ?? '' ) . ' ';
			$content_to_check .= ( $report->content ?? '' ) . ' ';
			$content_to_check .= ( $report->subject ?? '' ) . ' ';
		} elseif ( is_array( $report ) ) {
			$content_to_check .= ( $report['header'] ?? '' ) . ' ';
			$content_to_check .= ( $report['body'] ?? '' ) . ' ';
			$content_to_check .= ( $report['footer'] ?? '' ) . ' ';
			$content_to_check .= ( $report['content'] ?? '' ) . ' ';
			$content_to_check .= ( $report['subject'] ?? '' ) . ' ';
		}

		if ( '' === trim( $content_to_check ) ) {
			return true;
		}

		return str_contains( $content_to_check, '[burst.' );
	}

	/**
	 * Fetch report data from child site with per-request memoization.
	 *
	 * @param int $site_id    MainWP site ID.
	 * @param int $start_date Start timestamp.
	 * @param int $end_date   End timestamp.
	 * @return array<string, mixed>|null Report data or null on failure. Mixed: report payload contains integers, strings, and nested arrays.
	 */
	private function get_report_data( int $site_id, int $start_date, int $end_date ): ?array {
		$cache_key = "{$site_id}_{$start_date}_{$end_date}";
		if ( array_key_exists( $cache_key, $this->cache ) ) {
			return $this->cache[ $cache_key ];
		}

		$data                      = API::instance()->get_child_report_data( $site_id, $start_date, $end_date );
		$this->cache[ $cache_key ] = $data;

		return $data;
	}
}
