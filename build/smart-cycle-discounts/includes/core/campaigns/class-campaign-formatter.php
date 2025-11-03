<?php
/**
 * Campaign Formatter Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns/class-campaign-formatter.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Campaign Formatter Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/database/models
 */
class SCD_Campaign_Formatter {

	/**
	 * Date format.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	private string $date_format;

	/**
	 * Time format.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	private string $time_format;

	/**
	 * Currency symbol.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	private string $currency_symbol;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->date_format     = get_option( 'date_format', 'Y-m-d' );
		$this->time_format     = get_option( 'time_format', 'H:i' );
		$this->currency_symbol = get_woocommerce_currency_symbol();
	}

	/**
	 * Format campaign for display.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @param    string       $context     Display context.
	 * @return   array                        Formatted data.
	 */
	public function format( SCD_Campaign $campaign, string $context = 'list' ): array {
		$base_data = array(
			'id'       => $campaign->get_id(),
			'name'     => $this->format_name( $campaign ),
			'status'   => $this->format_status( $campaign ),
			'discount' => $this->format_discount( $campaign ),
			'schedule' => $this->format_schedule( $campaign ),
			'priority' => $this->format_priority( $campaign ),
		);

		switch ( $context ) {
			case 'detail':
				return array_merge( $base_data, $this->format_detail_data( $campaign ) );

			case 'edit':
				return array_merge( $base_data, $this->format_edit_data( $campaign ) );

			case 'api':
				return $this->format_api_data( $campaign );

			case 'export':
				return $this->format_export_data( $campaign );

			default:
				return $base_data;
		}
	}

	/**
	 * Format campaign name.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   string                       Formatted name.
	 */
	private function format_name( SCD_Campaign $campaign ): string {
		$name = esc_html( $campaign->get_name() );

		// Add status indicator if draft
		if ( $campaign->get_status() === 'draft' ) {
			$name .= ' <span class="status-indicator">' . __( '(Draft)', 'smart-cycle-discounts' ) . '</span>';
		}

		return $name;
	}

	/**
	 * Format campaign status.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   array                        Formatted status.
	 */
	private function format_status( SCD_Campaign $campaign ): array {
		$status        = $campaign->get_status();
		$status_labels = $this->get_status_labels();

		return array(
			'value' => $status,
			'label' => $status_labels[ $status ] ?? ucfirst( $status ),
			'class' => 'status-' . $status,
			'icon'  => $this->get_status_icon( $status ),
		);
	}

	/**
	 * Get status labels.
	 *
	 * @since    1.0.0
	 * @return   array    Status labels.
	 */
	private function get_status_labels(): array {
		return array(
			'draft'     => __( 'Draft', 'smart-cycle-discounts' ),
			'active'    => __( 'Active', 'smart-cycle-discounts' ),
			'paused'    => __( 'Paused', 'smart-cycle-discounts' ),
			'scheduled' => __( 'Scheduled', 'smart-cycle-discounts' ),
			'expired'   => __( 'Expired', 'smart-cycle-discounts' ),
			'archived'  => __( 'Archived', 'smart-cycle-discounts' ),
		);
	}

	/**
	 * Get status icon.
	 *
	 * @since    1.0.0
	 * @param    string $status    Status value.
	 * @return   string               Icon class.
	 */
	private function get_status_icon( string $status ): string {
		$icons = array(
			'draft'     => 'dashicons-edit',
			'active'    => 'dashicons-yes-alt',
			'paused'    => 'dashicons-controls-pause',
			'scheduled' => 'dashicons-clock',
			'expired'   => 'dashicons-dismiss',
			'archived'  => 'dashicons-archive',
		);

		return $icons[ $status ] ?? 'dashicons-marker';
	}

	/**
	 * Format discount display.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   array                        Formatted discount.
	 */
	private function format_discount( SCD_Campaign $campaign ): array {
		$type  = $campaign->get_discount_type();
		$value = $campaign->get_discount_value();

		return array(
			'type'    => $type,
			'value'   => $value,
			'display' => $this->format_discount_display( $type, $value ),
			'badge'   => $this->format_discount_badge( $type, $value ),
		);
	}

	/**
	 * Format discount display string.
	 *
	 * @since    1.0.0
	 * @param    string $type     Discount type.
	 * @param    float  $value    Discount value.
	 * @return   string              Formatted display.
	 */
	private function format_discount_display( string $type, float $value ): string {
		switch ( $type ) {
			case 'percentage':
				return sprintf( '%g%%', $value );

			case 'fixed':
				return wc_price( $value );

			case 'buy_x_get_y':
				return __( 'BOGO', 'smart-cycle-discounts' );

			case 'tiered':
				return __( 'Tiered', 'smart-cycle-discounts' );

			case 'bulk':
				return __( 'Bulk', 'smart-cycle-discounts' );

			default:
				return $value;
		}
	}

	/**
	 * Format discount badge.
	 *
	 * @since    1.0.0
	 * @param    string $type     Discount type.
	 * @param    float  $value    Discount value.
	 * @return   string              Badge HTML.
	 */
	private function format_discount_badge( string $type, float $value ): string {
		$display = $this->format_discount_display( $type, $value );
		$class   = 'discount-badge discount-' . $type;

		if ( $type === 'percentage' && $value >= 50 ) {
			$class .= ' discount-high';
		}

		return sprintf( '<span class="%s">%s OFF</span>', esc_attr( $class ), esc_html( $display ) );
	}

	/**
	 * Format schedule display.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   array                        Formatted schedule.
	 */
	private function format_schedule( SCD_Campaign $campaign ): array {
		$starts_at = $campaign->get_starts_at();
		$ends_at   = $campaign->get_ends_at();

		return array(
			'starts_at' => $starts_at ? $this->format_datetime( $starts_at ) : null,
			'ends_at'   => $ends_at ? $this->format_datetime( $ends_at ) : null,
			'display'   => $this->format_schedule_display( $starts_at, $ends_at ),
			'status'    => $this->get_schedule_status( $campaign ),
		);
	}

	/**
	 * Format datetime.
	 *
	 * @since    1.0.0
	 * @param    DateTime $datetime    DateTime object.
	 * @return   array                    Formatted datetime.
	 */
	private function format_datetime( DateTime $datetime ): array {
		return array(
			'date'     => wp_date( $this->date_format, $datetime->getTimestamp() ),
			'time'     => wp_date( $this->time_format, $datetime->getTimestamp() ),
			'full'     => wp_date( $this->date_format . ' ' . $this->time_format, $datetime->getTimestamp() ),
			'relative' => human_time_diff( $datetime->getTimestamp() ),
			'iso'      => $datetime->format( 'c' ),
		);
	}

	/**
	 * Format schedule display string.
	 *
	 * @since    1.0.0
	 * @param    DateTime|null $starts_at    Start date.
	 * @param    DateTime|null $ends_at      End date.
	 * @return   string                         Schedule display.
	 */
	private function format_schedule_display( ?DateTime $starts_at, ?DateTime $ends_at ): string {
		if ( ! $starts_at && ! $ends_at ) {
			return __( 'Always active', 'smart-cycle-discounts' );
		}

		if ( $starts_at && ! $ends_at ) {
			return sprintf(
				__( 'Starts %s', 'smart-cycle-discounts' ),
				wp_date( $this->date_format, $starts_at->getTimestamp() )
			);
		}

		if ( ! $starts_at && $ends_at ) {
			return sprintf(
				__( 'Ends %s', 'smart-cycle-discounts' ),
				wp_date( $this->date_format, $ends_at->getTimestamp() )
			);
		}

		return sprintf(
			'%s - %s',
			wp_date( $this->date_format, $starts_at->getTimestamp() ),
			wp_date( $this->date_format, $ends_at->getTimestamp() )
		);
	}

	/**
	 * Get schedule status.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   string                       Schedule status.
	 */
	private function get_schedule_status( SCD_Campaign $campaign ): string {
		// Use UTC timezone to match campaign dates (which are stored in UTC)
		$now       = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$starts_at = $campaign->get_starts_at();
		$ends_at   = $campaign->get_ends_at();

		if ( $starts_at && $starts_at > $now ) {
			return 'upcoming';
		}

		if ( $ends_at && $ends_at <= $now ) {
			return 'ended';
		}

		if ( $starts_at && $ends_at && $starts_at <= $now && $ends_at > $now ) {
			return 'running';
		}

		return 'always';
	}

	/**
	 * Format priority display.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   array                        Formatted priority.
	 */
	private function format_priority( SCD_Campaign $campaign ): array {
		$priority = $campaign->get_priority();

		return array(
			'value' => $priority,
			'label' => $this->get_priority_label( $priority ),
			'class' => 'priority-' . $priority,
			'stars' => str_repeat( '★', $priority ) . str_repeat( '☆', 5 - $priority ),
		);
	}

	/**
	 * Get priority label.
	 *
	 * @since    1.0.0
	 * @param    int $priority    Priority value.
	 * @return   string              Priority label.
	 */
	private function get_priority_label( int $priority ): string {
		if ( 5 === $priority ) {
			return __( 'Critical', 'smart-cycle-discounts' );
		} elseif ( 4 === $priority ) {
			return __( 'High', 'smart-cycle-discounts' );
		} elseif ( 3 === $priority ) {
			return __( 'Normal', 'smart-cycle-discounts' );
		} elseif ( 2 === $priority ) {
			return __( 'Low', 'smart-cycle-discounts' );
		} else {
			return __( 'Fallback', 'smart-cycle-discounts' );
		}
	}

	/**
	 * Format detail data.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   array                        Detail data.
	 */
	private function format_detail_data( SCD_Campaign $campaign ): array {
		return array(
			'description' => wp_kses_post( $campaign->get_description() ),
			'created'     => $this->format_datetime( $campaign->get_created_at() ),
			'updated'     => $this->format_datetime( $campaign->get_updated_at() ),
			'products'    => $this->format_product_selection( $campaign ),
			'settings'    => $campaign->get_settings(),
			'metadata'    => $campaign->get_metadata(),
		);
	}

	/**
	 * Format product selection.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   array                        Product selection data.
	 */
	private function format_product_selection( SCD_Campaign $campaign ): array {
		$type = $campaign->get_product_selection_type();

		return array(
			'type'       => $type,
			'type_label' => $this->get_product_selection_label( $type ),
			'products'   => $campaign->get_product_ids(),
			'categories' => $campaign->get_category_ids(),
			'tags'       => $campaign->get_tag_ids(),
			'count'      => $this->get_product_count( $campaign ),
		);
	}

	/**
	 * Get product selection label.
	 *
	 * @since    1.0.0
	 * @param    string $type    Selection type.
	 * @return   string             Type label.
	 */
	private function get_product_selection_label( string $type ): string {
		$labels = array(
			'all'        => __( 'All Products', 'smart-cycle-discounts' ),
			'individual' => __( 'Selected Products', 'smart-cycle-discounts' ),
			'categories' => __( 'Product Categories', 'smart-cycle-discounts' ),
			'tags'       => __( 'Product Tags', 'smart-cycle-discounts' ),
			'conditions' => __( 'Custom Conditions', 'smart-cycle-discounts' ),
		);

		return $labels[ $type ] ?? ucfirst( $type );
	}

	/**
	 * Get product count.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   int                          Product count.
	 */
	private function get_product_count( SCD_Campaign $campaign ): int {
		$selection_type = $campaign->get_product_selection_type();

		switch ( $selection_type ) {
			case 'specific_products':
				return count( $campaign->get_product_ids() );

			case 'all_products':
			case 'random_products':
			case 'smart_selection':
				// For dynamic selection types, we can't get exact count without querying
				// Return -1 to indicate dynamic count
				return -1;

			default:
				return 0;
		}
	}

	/**
	 * Format edit data.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   array                        Edit data.
	 */
	private function format_edit_data( SCD_Campaign $campaign ): array {
		return $campaign->to_array();
	}

	/**
	 * Format API data.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   array                        API data.
	 */
	private function format_api_data( SCD_Campaign $campaign ): array {
		$data = $campaign->to_array();

		// Add formatted fields
		$data['_formatted'] = array(
			'status'   => $this->format_status( $campaign ),
			'discount' => $this->format_discount( $campaign ),
			'schedule' => $this->format_schedule( $campaign ),
		);

		return $data;
	}

	/**
	 * Format export data.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   array                        Export data.
	 */
	private function format_export_data( SCD_Campaign $campaign ): array {
		return array(
			'ID'             => $campaign->get_id(),
			'Name'           => $campaign->get_name(),
			'Status'         => $this->get_status_labels()[ $campaign->get_status() ] ?? $campaign->get_status(),
			'Discount Type'  => $campaign->get_discount_type(),
			'Discount Value' => $campaign->get_discount_value(),
			'Start Date'     => $campaign->get_starts_at() ? $campaign->get_starts_at()->format( 'Y-m-d H:i:s' ) : '',
			'End Date'       => $campaign->get_ends_at() ? $campaign->get_ends_at()->format( 'Y-m-d H:i:s' ) : '',
			'Priority'       => $campaign->get_priority(),
			'Created'        => $campaign->get_created_at()->format( 'Y-m-d H:i:s' ),
		);
	}
}
