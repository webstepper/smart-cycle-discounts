<?php
/**
 * Performance Settings Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/settings/tabs/class-performance-settings.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Performance Settings Tab Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/settings/tabs
 */
class SCD_Performance_Settings extends SCD_Settings_Page_Base {

	/**
	 * Container instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object    $container    Container instance.
	 */
	private object $container;

	/**
	 * Initialize performance settings.
	 *
	 * @since    1.0.0
	 * @param    SCD_Settings_Manager $settings_manager  Settings manager.
	 * @param    SCD_Logger           $logger            Logger instance.
	 * @param    object               $container         Container instance.
	 */
	public function __construct( SCD_Settings_Manager $settings_manager, SCD_Logger $logger, object $container ) {
		parent::__construct( 'performance', $settings_manager, $logger );
		$this->container = $container;
	}

	/**
	 * Register settings sections and fields.
	 *
	 * @since    1.0.0
	 * @param    string $current_tab    Current active tab.
	 * @return   void
	 */
	public function register_sections( string $current_tab ): void {
		if ( $current_tab !== $this->tab_slug ) {
			return;
		}

		// Cache Configuration Section
		$this->add_section(
			'scd_performance_cache',
			'<span class="dashicons dashicons-performance"></span> ' . __( 'Cache Configuration', 'smart-cycle-discounts' ),
			'render_cache_section'
		);

		$this->add_field(
			'campaign_cache_duration',
			__( 'Campaign Cache Duration', 'smart-cycle-discounts' ),
			'render_campaign_cache_duration_field',
			'scd_performance_cache',
			array(
				'tooltip' => __( 'How long to cache campaign rules and settings. Lower = more fresh data, higher = better performance.', 'smart-cycle-discounts' ),
				'min'     => 300,
				'max'     => 86400,
				'suffix'  => __( 'seconds', 'smart-cycle-discounts' ),
			)
		);

		$this->add_field(
			'discount_cache_duration',
			__( 'Discount Cache Duration', 'smart-cycle-discounts' ),
			'render_discount_cache_duration_field',
			'scd_performance_cache',
			array(
				'tooltip' => __( 'How long to cache discount calculation results. Recommended: 1800-3600 seconds.', 'smart-cycle-discounts' ),
				'min'     => 300,
				'max'     => 7200,
				'suffix'  => __( 'seconds', 'smart-cycle-discounts' ),
			)
		);

		$this->add_field(
			'product_cache_duration',
			__( 'Product Cache Duration', 'smart-cycle-discounts' ),
			'render_product_cache_duration_field',
			'scd_performance_cache',
			array(
				'tooltip' => __( 'How long to cache product eligibility checks. Recommended: 3600 seconds.', 'smart-cycle-discounts' ),
				'min'     => 300,
				'max'     => 86400,
				'suffix'  => __( 'seconds', 'smart-cycle-discounts' ),
			)
		);

		// Cache Warming Section
		$this->add_section(
			'scd_performance_warming',
			'<span class="dashicons dashicons-update"></span> ' . __( 'Cache Warming', 'smart-cycle-discounts' ),
			'render_warming_section'
		);

		$this->add_field(
			'enable_cache_warming',
			__( 'Auto Warm Cache', 'smart-cycle-discounts' ),
			'render_enable_cache_warming_field',
			'scd_performance_warming',
			array(
				'tooltip' => __( 'Pre-load cache with frequently accessed data. Improves performance for first visitors.', 'smart-cycle-discounts' ),
			)
		);

		$this->add_field(
			'warm_on_campaign_changes',
			__( 'Warm on Campaign Changes', 'smart-cycle-discounts' ),
			'render_warm_on_campaign_changes_field',
			'scd_performance_warming',
			array(
				'tooltip' => __( 'Automatically rebuild cache when campaigns are created, updated, or deleted.', 'smart-cycle-discounts' ),
			)
		);

		// Cache Statistics Section
		$this->add_section(
			'scd_performance_stats',
			'<span class="dashicons dashicons-chart-line"></span> ' . __( 'Cache Statistics', 'smart-cycle-discounts' ),
			'render_stats_section'
		);

		$this->add_field(
			'cache_statistics',
			__( 'Current Status', 'smart-cycle-discounts' ),
			'render_cache_statistics_field',
			'scd_performance_stats',
			array()
		);
	}

	/**
	 * Render cache configuration section.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_cache_section(): void {
		echo '<p class="scd-section-description">';
		echo esc_html__( 'Configure cache duration for different types of data. Caching improves performance by storing frequently accessed data temporarily.', 'smart-cycle-discounts' );
		echo '</p>';

		// Detect cache backend
		$cache_backend = wp_using_ext_object_cache() ? __( 'Redis/Memcached', 'smart-cycle-discounts' ) : __( 'Database Transients', 'smart-cycle-discounts' );
		echo '<div class="scd-cache-backend-info">';
		echo '<strong>' . esc_html__( 'Cache Backend:', 'smart-cycle-discounts' ) . '</strong> ';
		echo esc_html( $cache_backend );
		if ( wp_using_ext_object_cache() ) {
			echo ' <span class="dashicons dashicons-yes-alt scd-cache-active-icon"></span>';
		}
		echo '</div>';
	}

	/**
	 * Render campaign cache duration field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_campaign_cache_duration_field( array $args ): void {
		$this->render_number_field( $args );
		echo '<p class="description">';
		echo esc_html__( 'Recommended: 3600 (1 hour). Higher values for high-traffic sites, lower for frequently updated campaigns.', 'smart-cycle-discounts' );
		echo '</p>';
	}

	/**
	 * Render discount cache duration field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_discount_cache_duration_field( array $args ): void {
		$this->render_number_field( $args );
		echo '<p class="description">';
		echo esc_html__( 'Higher values improve cart performance but may show slightly outdated prices during sales.', 'smart-cycle-discounts' );
		echo '</p>';
	}

	/**
	 * Render product cache duration field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_product_cache_duration_field( array $args ): void {
		$this->render_number_field( $args );
	}

	/**
	 * Render cache warming section.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_warming_section(): void {
		echo '<p class="scd-section-description">';
		echo esc_html__( 'Cache warming pre-loads data before visitors need it, ensuring fast response times from the first request.', 'smart-cycle-discounts' );
		echo '</p>';
	}

	/**
	 * Render enable cache warming field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_enable_cache_warming_field( array $args ): void {
		$this->render_toggle_field( $args );
		echo '<p class="description">';
		echo esc_html__( 'When enabled, the cache will be pre-loaded automatically every hour. Recommended for production sites.', 'smart-cycle-discounts' );
		echo '</p>';
	}

	/**
	 * Render warm on campaign changes field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_warm_on_campaign_changes_field( array $args ): void {
		$this->render_toggle_field( $args );
		echo '<p class="description">';
		echo esc_html__( 'Ensures visitors see updated campaigns immediately after you save changes.', 'smart-cycle-discounts' );
		echo '</p>';
	}

	/**
	 * Render cache statistics section.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_stats_section(): void {
		echo '<p class="scd-section-description">';
		echo esc_html__( 'View current cache status and performance metrics.', 'smart-cycle-discounts' );
		echo '</p>';
	}

	/**
	 * Render cache statistics field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_cache_statistics_field( array $args ): void {
		$cache_manager = null;
		if ( $this->container->has( 'cache_manager' ) ) {
			$cache_manager = $this->container->get( 'cache_manager' );
		}

		if ( ! $cache_manager ) {
			echo '<p>' . esc_html__( 'Cache statistics not available.', 'smart-cycle-discounts' ) . '</p>';
			return;
		}

		$stats = $cache_manager->get_stats();

		echo '<div class="scd-cache-stats">';
		echo '<table class="widefat scd-cache-stats-table">';
		echo '<tbody>';

		// Cache Status
		echo '<tr>';
		echo '<td class="scd-cache-stats-label"><strong>' . esc_html__( 'Cache Status', 'smart-cycle-discounts' ) . '</strong></td>';
		echo '<td>';
		if ( $stats['enabled'] ) {
			echo '<span class="scd-status-enabled"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Enabled', 'smart-cycle-discounts' ) . '</span>';
		} else {
			echo '<span class="scd-status-disabled"><span class="dashicons dashicons-dismiss"></span> ' . esc_html__( 'Disabled', 'smart-cycle-discounts' ) . '</span>';
		}
		echo '</td>';
		echo '</tr>';

		// Object Cache
		echo '<tr>';
		echo '<td><strong>' . esc_html__( 'Object Cache', 'smart-cycle-discounts' ) . '</strong></td>';
		echo '<td>';
		if ( $stats['object_cache_available'] ) {
			echo '<span class="scd-status-active"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Active (Redis/Memcached)', 'smart-cycle-discounts' ) . '</span>';
		} else {
			echo '<span class="scd-status-warning"><span class="dashicons dashicons-info"></span> ' . esc_html__( 'Not available (using transients)', 'smart-cycle-discounts' ) . '</span>';
		}
		echo '</td>';
		echo '</tr>';

		// Cached Entries
		echo '<tr>';
		echo '<td><strong>' . esc_html__( 'Cached Entries', 'smart-cycle-discounts' ) . '</strong></td>';
		echo '<td id="scd-cached-entries-count">' . esc_html( number_format_i18n( $stats['transient_count'] ) ) . ' ' . esc_html__( 'items', 'smart-cycle-discounts' ) . '</td>';
		echo '</tr>';

		// Cache Prefix
		echo '<tr>';
		echo '<td><strong>' . esc_html__( 'Cache Prefix', 'smart-cycle-discounts' ) . '</strong></td>';
		echo '<td><code>' . esc_html( $stats['prefix'] ) . '</code></td>';
		echo '</tr>';

		// Default Expiration
		echo '<tr>';
		echo '<td><strong>' . esc_html__( 'Default Expiration', 'smart-cycle-discounts' ) . '</strong></td>';
		echo '<td>' . esc_html( $stats['default_expiration'] ) . ' ' . esc_html__( 'seconds', 'smart-cycle-discounts' ) . ' (' . esc_html( human_time_diff( 0, $stats['default_expiration'] ) ) . ')</td>';
		echo '</tr>';

		echo '</tbody>';
		echo '</table>';
		echo '</div>';

		echo '<div class="scd-cache-clear-actions">';
		echo '<button type="button" id="scd-clear-cache-btn" class="button button-secondary">';
		echo '<span class="dashicons dashicons-trash"></span> ';
		echo esc_html__( 'Clear All Cache', 'smart-cycle-discounts' );
		echo '</button>';
		echo '<span id="scd-clear-cache-status"></span>';
		echo '</div>';
	}

	/**
	 * Sanitize performance settings.
	 *
	 * @since    1.0.0
	 * @param    array $input    Raw input data.
	 * @return   array              Sanitized data.
	 */
	protected function sanitize_settings( array $input ): array {
		$sanitized = array();

		// Campaign cache duration
		$sanitized['campaign_cache_duration'] = isset( $input['campaign_cache_duration'] )
			? max( 300, min( 86400, absint( $input['campaign_cache_duration'] ) ) )
			: 3600;

		// Discount cache duration
		$sanitized['discount_cache_duration'] = isset( $input['discount_cache_duration'] )
			? max( 300, min( 7200, absint( $input['discount_cache_duration'] ) ) )
			: 1800;

		// Product cache duration
		$sanitized['product_cache_duration'] = isset( $input['product_cache_duration'] )
			? max( 300, min( 86400, absint( $input['product_cache_duration'] ) ) )
			: 3600;

		$sanitized['enable_cache_warming']     = isset( $input['enable_cache_warming'] ) && '1' === $input['enable_cache_warming'];
		$sanitized['warm_on_campaign_changes'] = isset( $input['warm_on_campaign_changes'] ) && '1' === $input['warm_on_campaign_changes'];

		return $sanitized;
	}
}
