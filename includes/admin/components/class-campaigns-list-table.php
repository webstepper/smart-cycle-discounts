<?php
/**
 * Campaigns List Table Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/components/class-campaigns-list-table.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Removed strict types for PHP compatibility

// Load WordPress list table if not already loaded
// This is only loaded in admin context
if ( ! class_exists( 'WP_List_Table' ) && is_admin() ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Campaigns List Table
 *
 * Displays campaigns in a WordPress-style list table.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/tables
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Campaigns_List_Table extends WP_List_Table {

	/**
	 * Campaign manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Manager    $campaign_manager    Campaign manager.
	 */
	private $campaign_manager;

	/**
	 * Capability manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Admin_Capability_Manager    $capability_manager    Capability manager.
	 */
	private $capability_manager;

	/**
	 * Service container.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object    $container    Service container.
	 */
	private $container;

	/**
	 * Initialize the list table.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign_Manager         $campaign_manager     Campaign manager.
	 * @param    SCD_Admin_Capability_Manager $capability_manager   Capability manager.
	 */
	public function __construct( $campaign_manager, $capability_manager ) {
		$this->campaign_manager   = $campaign_manager;
		$this->capability_manager = $capability_manager;

		// Get container instance
		if ( class_exists( 'Smart_Cycle_Discounts' ) ) {
			$plugin          = Smart_Cycle_Discounts::get_instance();
			$this->container = $plugin->get_container();
		}

		parent::__construct(
			array(
				'singular' => 'campaign',
				'plural'   => 'campaigns',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get list table columns.
	 *
	 * @since    1.0.0
	 * @return   array    Column definitions.
	 */
	public function get_columns() {
		$columns = array(
			'cb'          => '<input type="checkbox" />',
			'name'        => __( 'Campaign Name', 'smart-cycle-discounts' ),
			'discount'    => __( 'Discount', 'smart-cycle-discounts' ),
			'products'    => __( 'Products', 'smart-cycle-discounts' ),
			'status'      => __( 'Status', 'smart-cycle-discounts' ),
			'schedule'    => __( 'Schedule', 'smart-cycle-discounts' ),
			'priority'    => __( 'Priority', 'smart-cycle-discounts' ),
			'health'      => __( 'Health', 'smart-cycle-discounts' ),
			'performance' => __( 'Performance', 'smart-cycle-discounts' ),
			'created'     => __( 'Created', 'smart-cycle-discounts' ),
		);

		return apply_filters( 'scd_campaigns_list_table_columns', $columns );
	}

	/**
	 * Get sortable columns.
	 *
	 * @since    1.0.0
	 * @return   array    Sortable column definitions.
	 */
	public function get_sortable_columns() {
		return array(
			'name'     => array( 'name', false ),
			'status'   => array( 'status', false ),
			'created'  => array( 'created_at', true ),
			'schedule' => array( 'start_date', false ),
			'priority' => array( 'priority', false ),
		);
	}

	/**
	 * Get bulk actions.
	 *
	 * @since    1.0.0
	 * @return   array    Bulk action definitions.
	 */
	public function get_bulk_actions() {
		$actions = array();

		// Check if viewing trash
		$viewing_trash = isset( $_REQUEST['status'] ) && 'trash' === $_REQUEST['status'];

		if ( $viewing_trash ) {
			// Trash view actions
			if ( $this->capability_manager->current_user_can( 'scd_edit_campaigns' ) ) {
				$actions['restore'] = __( 'Restore', 'smart-cycle-discounts' );
			}

			if ( $this->capability_manager->current_user_can( 'scd_delete_campaigns' ) ) {
				$actions['delete_permanently'] = __( 'Delete Permanently', 'smart-cycle-discounts' );
			}
		} else {
			// Normal view actions
			if ( $this->capability_manager->current_user_can( 'scd_activate_campaigns' ) ) {
				$actions['activate']   = __( 'Activate', 'smart-cycle-discounts' );
				$actions['deactivate'] = __( 'Deactivate', 'smart-cycle-discounts' );
			}

			if ( $this->capability_manager->current_user_can( 'scd_delete_campaigns' ) ) {
				$actions['delete'] = __( 'Move to Trash', 'smart-cycle-discounts' );
			}

			if ( $this->capability_manager->current_user_can( 'scd_edit_campaigns' ) ) {
				$actions['stop_recurring'] = __( 'Stop Recurring', 'smart-cycle-discounts' );
			}
		}

		return $actions;
	}

	/**
	 * Prepare table items.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function prepare_items() {
		// Set up columns
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);

		// Handle bulk actions
		$this->process_bulk_action();

		// Get current page and per page settings
		$current_page = $this->get_pagenum();
		$per_page     = $this->get_items_per_page( 'scd_campaigns_per_page', 20 );

		// Check if viewing trash
		$viewing_trash = isset( $_REQUEST['status'] ) && 'trash' === $_REQUEST['status'];

		if ( $viewing_trash ) {
			// Get trashed campaigns directly from repository
			$repository = $this->campaign_manager->get_repository();
			$options    = array(
				'limit'           => $per_page,
				'offset'          => ( $current_page - 1 ) * $per_page,
				'order_by'        => sanitize_text_field( isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'deleted_at' ),
				'order_direction' => sanitize_text_field( isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'DESC' ),
			);

			$campaigns   = $repository->find_trashed( $options );
			$total_items = $repository->count_trashed();
		} else {
			// Prepare query arguments for non-trash campaigns
			$args = array(
				'limit'   => $per_page,
				'offset'  => ( $current_page - 1 ) * $per_page,
				'orderby' => sanitize_text_field( isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'created_at' ),
				'order'   => sanitize_text_field( isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'DESC' ),
			);

			// Add search filter
			if ( ! empty( $_REQUEST['s'] ) ) {
				$args['search'] = sanitize_text_field( $_REQUEST['s'] );
			}

			// Add status filter
			if ( ! empty( $_REQUEST['status'] ) ) {
				$args['status'] = sanitize_text_field( $_REQUEST['status'] );
			}

			// Get campaigns and total count
			$campaigns   = $this->campaign_manager->get_campaigns( $args );
			$total_items = $this->campaign_manager->count_campaigns( $args );
		}

		// Set items and pagination
		$this->items = $campaigns;
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Render checkbox column.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   string                       Checkbox HTML.
	 */
	public function column_cb( $item ) {
		$campaign = $item; // Type clarity - $item is SCD_Campaign object

		return sprintf(
			'<input type="checkbox" name="campaign[]" value="%d" />',
			$campaign->get_id()
		);
	}

	/**
	 * Render name column.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   string                       Name column HTML.
	 */
	public function column_name( $item ) {
		$campaign = $item; // Type clarity - $item is SCD_Campaign object

		$campaign_id   = $campaign->get_id();
		$campaign_name = esc_html( $campaign->get_name() );

		// Check if campaign has recurring settings
		$recurring_badge    = '';
		$recurring_settings = null;
		if ( isset( $this->container ) && $this->container->has( 'recurring_handler' ) ) {
			$recurring_handler  = $this->container->get( 'recurring_handler' );
			$recurring_settings = $recurring_handler->get_recurring_settings( $campaign_id );

			if ( $recurring_settings ) {
				// Check if this is a parent or child campaign
				if ( empty( $recurring_settings['parent_campaign_id'] ) ) {
					// Parent campaign with recurring enabled
					if ( ! empty( $recurring_settings['is_active'] ) ) {
						$pattern      = $recurring_settings['recurrence_pattern'];
						$pattern_text = __( 'Recurring', 'smart-cycle-discounts' );
						if ( 'daily' === $pattern ) {
							$pattern_text = __( 'Daily', 'smart-cycle-discounts' );
						} elseif ( 'weekly' === $pattern ) {
							$pattern_text = __( 'Weekly', 'smart-cycle-discounts' );
						} elseif ( 'monthly' === $pattern ) {
							$pattern_text = __( 'Monthly', 'smart-cycle-discounts' );
						}
						$recurring_badge = sprintf(
							' <span class="scd-recurring-badge scd-recurring-active" title="%s">🔄 %s</span>',
							esc_attr( __( 'This campaign repeats automatically', 'smart-cycle-discounts' ) ),
							esc_html( $pattern_text )
						);
					} else {
						// Recurring but inactive
						$recurring_badge = sprintf(
							' <span class="scd-recurring-badge scd-recurring-inactive" title="%s">⏸️ %s</span>',
							esc_attr( __( 'Recurring stopped', 'smart-cycle-discounts' ) ),
							esc_html( __( 'Stopped', 'smart-cycle-discounts' ) )
						);
					}
				} else {
					// Child campaign
					$recurring_badge = sprintf(
						' <span class="scd-recurring-badge scd-recurring-child" title="%s">↳ %s #%d</span>',
						esc_attr( __( 'Created by recurring campaign', 'smart-cycle-discounts' ) ),
						esc_html( __( 'Occurrence', 'smart-cycle-discounts' ) ),
						intval( $recurring_settings['occurrence_number'] )
					);
				}
			}
		}

		// Build row actions
		$actions = array();

		// Check if campaign is in trash
		$deleted_at = $campaign->get_deleted_at();
		$is_trashed = null !== $deleted_at;

		if ( $is_trashed ) {
			// Trash view actions
			if ( $this->capability_manager->current_user_can( 'edit_campaign', $campaign_id ) ) {
				$actions['restore'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url(
						wp_nonce_url(
							admin_url( 'admin.php?page=scd-campaigns&action=restore&id=' . $campaign_id ),
							'scd_restore_campaign_' . $campaign_id
						)
					),
					__( 'Restore', 'smart-cycle-discounts' )
				);
			}

			if ( $this->capability_manager->current_user_can( 'delete_campaign', $campaign_id ) ) {
				$confirm_message               = esc_js( __( 'Are you sure you want to permanently delete this campaign? This action cannot be undone.', 'smart-cycle-discounts' ) );
				$actions['delete_permanently'] = sprintf(
					'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\')">%s</a>',
					esc_url(
						wp_nonce_url(
							admin_url( 'admin.php?page=scd-campaigns&action=delete_permanently&id=' . $campaign_id ),
							'scd_delete_permanently_' . $campaign_id
						)
					),
					$confirm_message,
					__( 'Delete Permanently', 'smart-cycle-discounts' )
				);
			}
		} else {
			// Normal view actions
			if ( $this->capability_manager->current_user_can( 'edit_campaign', $campaign_id ) ) {
				$actions['edit'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=scd-campaigns&action=wizard&intent=edit&id=' . $campaign_id ) ),
					__( 'Edit', 'smart-cycle-discounts' )
				);
			}

			if ( $this->capability_manager->current_user_can( 'activate_campaign', $campaign_id ) ) {
				$status = $campaign->get_status();

				// Only show deactivate for truly active campaigns
				if ( 'active' === $status ) {
					$actions['deactivate'] = sprintf(
						'<a href="%s">%s</a>',
						esc_url(
							wp_nonce_url(
								admin_url( 'admin.php?page=scd-campaigns&action=deactivate&id=' . $campaign_id ),
								'scd-campaign-action-deactivate-' . $campaign_id
							)
						),
						__( 'Deactivate', 'smart-cycle-discounts' )
					);
				}
				// Show activate for draft, scheduled, and paused campaigns only
				// Expired campaigns should not be reactivated directly
				elseif ( in_array( $status, array( 'draft', 'scheduled', 'paused' ), true ) ) {
					$actions['activate'] = sprintf(
						'<a href="%s">%s</a>',
						esc_url(
							wp_nonce_url(
								admin_url( 'admin.php?page=scd-campaigns&action=activate&id=' . $campaign_id ),
								'scd-campaign-action-activate-' . $campaign_id
							)
						),
						__( 'Activate', 'smart-cycle-discounts' )
					);
				}
			}

			// Quick Edit action - not available for expired campaigns
			if ( $this->capability_manager->current_user_can( 'edit_campaign', $campaign_id ) && 'expired' !== $status ) {
				$actions['inline hide-if-no-js'] = sprintf(
					'<button type="button" class="button-link editinline" data-campaign-id="%d">%s</button>',
					$campaign_id,
					__( 'Quick Edit', 'smart-cycle-discounts' )
				);
			}

			if ( $this->capability_manager->current_user_can( 'scd_create_campaigns' ) ) {
				$actions['duplicate'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url(
						wp_nonce_url(
							admin_url( 'admin.php?page=scd-campaigns&action=duplicate&id=' . $campaign_id ),
							'duplicate_campaign_' . $campaign_id
						)
					),
					__( 'Duplicate', 'smart-cycle-discounts' )
				);
			}

			if ( $this->capability_manager->current_user_can( 'delete_campaign', $campaign_id ) ) {
				$confirm_message   = esc_js( __( 'Are you sure you want to move this campaign to trash?', 'smart-cycle-discounts' ) );
				$actions['delete'] = sprintf(
					'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\')">%s</a>',
					esc_url(
						wp_nonce_url(
							admin_url( 'admin.php?page=scd-campaigns&action=delete&id=' . $campaign_id ),
							'scd-campaign-action-delete-' . $campaign_id
						)
					),
					$confirm_message,
					__( 'Move to Trash', 'smart-cycle-discounts' )
				);
			}

			// Add stop recurring action for active recurring campaigns
			if ( $recurring_settings && empty( $recurring_settings['parent_campaign_id'] ) && ! empty( $recurring_settings['is_active'] ) ) {
				if ( $this->capability_manager->current_user_can( 'edit_campaign', $campaign_id ) ) {
					$actions['stop_recurring'] = sprintf(
						'<a href="%s" class="stop-recurring" onclick="return confirm(\'%s\')">%s</a>',
						esc_url(
							wp_nonce_url(
								admin_url( 'admin-post.php?action=scd_stop_recurring&id=' . $campaign_id ),
								'scd_stop_recurring'
							)
						),
						esc_js( __( 'Are you sure you want to stop this campaign from recurring?', 'smart-cycle-discounts' ) ),
						__( 'Stop Recurring', 'smart-cycle-discounts' )
					);
				}
			}
		}

		// Build title with edit link
		$title = $this->capability_manager->current_user_can( 'edit_campaign', $campaign_id )
			? sprintf(
				'<a href="%s" class="row-title"><strong>%s</strong></a>%s',
				esc_url( admin_url( 'admin.php?page=scd-campaigns&action=wizard&intent=edit&id=' . $campaign_id ) ),
				esc_html( $campaign_name ),
				$recurring_badge
			)
			: sprintf( '<strong>%s</strong>%s', esc_html( $campaign_name ), $recurring_badge );

		// Add description if available
		if ( $campaign->get_description() ) {
			$title .= '<br><span class="description">' . esc_html( $campaign->get_description() ) . '</span>';
		}

		return $title . $this->row_actions( $actions );
	}

	/**
	 * Render status column.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   string                       Status column HTML.
	 */
	public function column_status( $item ) {
		$campaign     = $item;
		$status       = $campaign->get_status();
		$status_label = ucfirst( $status );

		// Add status-specific information
		$status_info = '';
		switch ( $status ) {
			case 'scheduled':
				$start_date = $campaign->get_starts_at();
				if ( $start_date ) {
					// Convert from UTC to WordPress timezone before displaying
					$status_info = sprintf(
						'<br><small>%s: %s</small>',
						__( 'Starts', 'smart-cycle-discounts' ),
						wp_date( 'M j, Y g:i A', $start_date->getTimestamp() )
					);
				}
				break;
			case 'active':
				$end_date = $campaign->get_ends_at();
				if ( $end_date ) {
					// Convert from UTC to WordPress timezone before displaying
					$status_info = sprintf(
						'<br><small>%s: %s</small>',
						__( 'Ends', 'smart-cycle-discounts' ),
						wp_date( 'M j, Y g:i A', $end_date->getTimestamp() )
					);
				}
				break;
			case 'paused':
				$end_date = $campaign->get_ends_at();
				if ( $end_date ) {
					$now            = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
					$time_remaining = $end_date->getTimestamp() - $now->getTimestamp();

					// Calculate days remaining
					$days_remaining = floor( $time_remaining / DAY_IN_SECONDS );

					// Determine if expiring soon (< 7 days)
					$is_expiring_soon = $days_remaining < 7 && $days_remaining >= 0;
					$warning_class    = $is_expiring_soon ? ' scd-expiring-soon' : '';

					// Format countdown message
					if ( $days_remaining < 0 ) {
						// Already past end date (will expire on next cron run)
						$countdown_text = __( 'Expiring soon', 'smart-cycle-discounts' );
					} elseif ( $days_remaining === 0 ) {
						$hours_remaining = floor( $time_remaining / HOUR_IN_SECONDS );
						if ( $hours_remaining > 0 ) {
							$countdown_text = sprintf(
								_n( 'Expires in %d hour', 'Expires in %d hours', $hours_remaining, 'smart-cycle-discounts' ),
								$hours_remaining
							);
						} else {
							// Less than 1 hour remaining
							$minutes_remaining = floor( $time_remaining / MINUTE_IN_SECONDS );
							if ( $minutes_remaining > 0 ) {
								$countdown_text = sprintf(
									_n( 'Expires in %d minute', 'Expires in %d minutes', $minutes_remaining, 'smart-cycle-discounts' ),
									$minutes_remaining
								);
							} else {
								$countdown_text = __( 'Expires very soon', 'smart-cycle-discounts' );
							}
						}
					} else {
						$countdown_text = sprintf(
							_n( 'Expires in %d day', 'Expires in %d days', $days_remaining, 'smart-cycle-discounts' ),
							$days_remaining
						);
					}

					// Convert from UTC to WordPress timezone before displaying
					$status_info = sprintf(
						'<br><small class="%s">⏰ %s (%s)</small>',
						esc_attr( $warning_class ),
						esc_html( $countdown_text ),
						wp_date( 'M j, Y', $end_date->getTimestamp() )
					);
				}
				break;
			case 'expired':
				$end_date = $campaign->get_ends_at();
				if ( $end_date ) {
					// Convert from UTC to WordPress timezone before displaying
					$status_info = sprintf(
						'<br><small>%s: %s</small>',
						__( 'Ended', 'smart-cycle-discounts' ),
						wp_date( 'M j, Y g:i A', $end_date->getTimestamp() )
					);
				}
				break;
		}

		return sprintf(
			'<span class="scd-status-badge scd-status-badge--%s">%s</span>%s',
			esc_attr( $status ),
			esc_html( $status_label ),
			$status_info
		);
	}

	/**
	 * Render discount column.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   string                       Discount column HTML.
	 */
	public function column_discount( $item ) {
		$campaign       = $item;
		$discount_type  = $campaign->get_discount_type();
		$discount_value = $campaign->get_discount_value();

		// Flag to track if discount_display contains HTML (from wc_price)
		$discount_has_html = false;

		switch ( $discount_type ) {
			case 'percentage':
				$discount_display = sprintf( '%s%%', number_format( $discount_value, 1 ) );
				$discount_label   = __( 'Percentage', 'smart-cycle-discounts' );
				break;
			case 'fixed':
				$discount_display  = wc_price( $discount_value );
				$discount_has_html = true; // wc_price returns HTML
				$discount_label    = __( 'Fixed Amount', 'smart-cycle-discounts' );
				break;
			case 'tiered':
				$discount_rules = $campaign->get_discount_rules();
				$tiers          = isset( $discount_rules['tiers'] ) ? $discount_rules['tiers'] : array();
				$tier_mode      = isset( $discount_rules['tier_mode'] ) ? $discount_rules['tier_mode'] : 'percentage';

				if ( ! empty( $tiers ) ) {
					$tier_count       = count( $tiers );
					$discount_display = sprintf(
						_n( '%d tier', '%d tiers', $tier_count, 'smart-cycle-discounts' ),
						$tier_count
					);
					$discount_label   = __( 'Volume Discount', 'smart-cycle-discounts' );
				} else {
					$discount_display = __( 'Not configured', 'smart-cycle-discounts' );
					$discount_label   = '';
				}
				break;
			case 'bogo':
				$discount_rules = $campaign->get_discount_rules();
				$buy_qty        = isset( $discount_rules['buy_quantity'] ) ? $discount_rules['buy_quantity'] : 1;
				$get_qty        = isset( $discount_rules['get_quantity'] ) ? $discount_rules['get_quantity'] : 1;
				$bogo_discount  = isset( $discount_rules['discount'] ) ? $discount_rules['discount'] : 100;

				$discount_display = sprintf(
					__( 'Buy %1$d Get %2$d', 'smart-cycle-discounts' ),
					$buy_qty,
					$get_qty
				);
				$discount_label   = sprintf( '%s%% off', number_format( $bogo_discount, 0 ) );
				break;
			case 'spend_threshold':
				$discount_rules = $campaign->get_discount_rules();
				$thresholds     = isset( $discount_rules['thresholds'] ) ? $discount_rules['thresholds'] : array();
				$threshold_mode = isset( $discount_rules['threshold_mode'] ) ? $discount_rules['threshold_mode'] : 'percentage';

				if ( ! empty( $thresholds ) ) {
					$threshold_count  = count( $thresholds );
					$discount_display = sprintf(
						_n( '%d threshold', '%d thresholds', $threshold_count, 'smart-cycle-discounts' ),
						$threshold_count
					);
					$discount_label   = __( 'Spend Threshold', 'smart-cycle-discounts' );
				} else {
					$discount_display = __( 'Not configured', 'smart-cycle-discounts' );
					$discount_label   = '';
				}
				break;
			default:
				$discount_display = __( 'Not configured', 'smart-cycle-discounts' );
				$discount_label   = '';
		}

		// Use appropriate escaping based on content type
		if ( $discount_has_html ) {
			// wc_price() returns safe HTML - use wp_kses_post()
			$output = sprintf( '<strong>%s</strong>', wp_kses_post( $discount_display ) );
		} else {
			// Plain text - use esc_html()
			$output = sprintf( '<strong>%s</strong>', esc_html( $discount_display ) );
		}

		if ( $discount_label ) {
			$output .= sprintf( '<br><small>%s</small>', esc_html( $discount_label ) );
		}

		return $output;
	}

	/**
	 * Render products column.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   string                       Products column HTML.
	 */
	public function column_products( $item ) {
		$campaign       = $item;
		$selection_type = $campaign->get_product_selection_type();
		$product_ids    = $campaign->get_product_ids();
		$category_ids   = $campaign->get_category_ids();

		$output = '';

		switch ( $selection_type ) {
			case 'all_products':
				$output = '<strong>' . __( 'All Products', 'smart-cycle-discounts' ) . '</strong>';
				break;

			case 'specific_products':
				$count = is_array( $product_ids ) ? count( $product_ids ) : 0;
				if ( $count > 0 ) {
					$product_names = $this->get_product_names( $product_ids, 3 );
					$output        = sprintf(
						'<strong>%d</strong> %s',
						$count,
						_n( 'Product', 'Products', $count, 'smart-cycle-discounts' )
					);
					if ( ! empty( $product_names['display'] ) ) {
						$output .= '<br><small>' . esc_html( $product_names['display'] ) . '</small>';
					}
				} else {
					$output = '<span class="description">' . __( 'No products selected', 'smart-cycle-discounts' ) . '</span>';
				}
				break;

			case 'random_products':
				$metadata     = $campaign->get_metadata();
				$random_count = $metadata['random_count'] ?? 0;
				$actual_count = is_array( $product_ids ) ? count( $product_ids ) : 0;

				if ( $actual_count > 0 ) {
					$product_names = $this->get_product_names( $product_ids, 3 );
					$output        = sprintf(
						'<strong>%d</strong> %s<br><small>%s</small>',
						$actual_count,
						_n( 'Random Product', 'Random Products', $actual_count, 'smart-cycle-discounts' ),
						__( 'Selected', 'smart-cycle-discounts' )
					);
					if ( ! empty( $product_names['display'] ) ) {
						$output .= '<br><small>' . esc_html( $product_names['display'] ) . '</small>';
					}
				} else {
					$output = sprintf(
						'<strong>%d</strong> %s<br><small class="description">%s</small>',
						$random_count,
						_n( 'Random Product', 'Random Products', $random_count, 'smart-cycle-discounts' ),
						__( 'Not compiled yet', 'smart-cycle-discounts' )
					);
				}
				break;

			case 'smart_selection':
				$count = is_array( $product_ids ) ? count( $product_ids ) : 0;
				if ( $count > 0 ) {
					$product_names = $this->get_product_names( $product_ids, 3 );
					$output        = sprintf(
						'<strong>%d</strong> %s<br><small>%s</small>',
						$count,
						_n( 'Product', 'Products', $count, 'smart-cycle-discounts' ),
						__( 'Smart Selection', 'smart-cycle-discounts' )
					);
					if ( ! empty( $product_names['display'] ) ) {
						$output .= '<br><small>' . esc_html( $product_names['display'] ) . '</small>';
					}
				} else {
					$output = '<strong>' . __( 'Smart Selection', 'smart-cycle-discounts' ) . '</strong><br><small class="description">' . __( 'Not compiled yet', 'smart-cycle-discounts' ) . '</small>';
				}
				break;

			default:
				$output = '<span class="description">' . __( 'Not configured', 'smart-cycle-discounts' ) . '</span>';
		}

		// Add categories info if applicable
		if ( ! empty( $category_ids ) && is_array( $category_ids ) ) {
			$cat_count      = count( $category_ids );
			$category_names = $this->get_category_names( $category_ids, 3 );
			$output        .= sprintf(
				'<br><strong>%d</strong> %s',
				$cat_count,
				_n( 'Category', 'Categories', $cat_count, 'smart-cycle-discounts' )
			);
			if ( ! empty( $category_names['display'] ) ) {
				$output .= '<br><small>' . esc_html( $category_names['display'] ) . '</small>';
			}
		} elseif ( 'all_products' !== $selection_type && empty( $category_ids ) ) {
			$output .= '<br><small>' . __( 'All Categories', 'smart-cycle-discounts' ) . '</small>';
		}

		return $output;
	}

	/**
	 * Get product names for display.
	 *
	 * @since    1.0.0
	 * @param    array $product_ids    Product IDs.
	 * @param    int   $limit          Number of products to show (0 = show all).
	 * @return   array                    Array with 'display' key.
	 */
	private function get_product_names( $product_ids, $limit = 0 ) {
		if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
			return array( 'display' => '' );
		}

		$names = array();
		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$names[] = $product->get_name();
			}
		}

		if ( empty( $names ) ) {
			return array( 'display' => '' );
		}

		$total_count = count( $names );

		// If limit is 0 or greater than total, show all names
		if ( $limit === 0 || $limit >= $total_count ) {
			$display = implode( ', ', $names );
		} else {
			// Show limited number with "and X more"
			$display_names = array_slice( $names, 0, $limit );
			$display       = implode( ', ', $display_names );

			$remaining = $total_count - $limit;
			$display  .= sprintf(
				' ' . _n( 'and %d more', 'and %d more', $remaining, 'smart-cycle-discounts' ),
				$remaining
			);
		}

		return array( 'display' => $display );
	}

	/**
	 * Get category names for display.
	 *
	 * @since    1.0.0
	 * @param    array $category_ids    Category IDs.
	 * @param    int   $limit           Number of categories to show (0 = show all).
	 * @return   array                     Array with 'display' key.
	 */
	private function get_category_names( $category_ids, $limit = 0 ) {
		if ( empty( $category_ids ) || ! is_array( $category_ids ) ) {
			return array( 'display' => '' );
		}

		$names = array();
		foreach ( $category_ids as $category_id ) {
			$term = get_term( $category_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}

		if ( empty( $names ) ) {
			return array( 'display' => '' );
		}

		$total_count = count( $names );

		// If limit is 0 or greater than total, show all names
		if ( $limit === 0 || $limit >= $total_count ) {
			$display = implode( ', ', $names );
		} else {
			// Show limited number with "and X more"
			$display_names = array_slice( $names, 0, $limit );
			$display       = implode( ', ', $display_names );

			$remaining = $total_count - $limit;
			$display  .= sprintf(
				' ' . _n( 'and %d more', 'and %d more', $remaining, 'smart-cycle-discounts' ),
				$remaining
			);
		}

		return array( 'display' => $display );
	}

	/**
	 * Render schedule column.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   string                       Schedule column HTML.
	 */
	public function column_schedule( $item ) {
		$campaign   = $item;
		$start_date = $campaign->get_starts_at();
		$end_date   = $campaign->get_ends_at();

		$output = '';

		if ( $start_date ) {
			// Convert from UTC to WordPress timezone before displaying
			$output .= sprintf(
				'<strong>%s:</strong> %s<br>',
				__( 'Start', 'smart-cycle-discounts' ),
				wp_date( 'M j, Y g:i A', $start_date->getTimestamp() )
			);
		}

		if ( $end_date ) {
			// Convert from UTC to WordPress timezone before displaying
			$output .= sprintf(
				'<strong>%s:</strong> %s',
				__( 'End', 'smart-cycle-discounts' ),
				wp_date( 'M j, Y g:i A', $end_date->getTimestamp() )
			);
		} else {
			$output .= sprintf(
				'<strong>%s:</strong> %s',
				__( 'End', 'smart-cycle-discounts' ),
				__( 'No end date', 'smart-cycle-discounts' )
			);
		}

		return $output;
	}

	/**
	 * Render performance column.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   string                       Performance column HTML.
	 */
	public function column_performance( $item ) {
		$campaign = $item;
		// Get campaign performance metrics
		$metrics = $campaign->get_performance_metrics();

		if ( empty( $metrics ) ) {
			return '<span class="description">' . __( 'No data yet', 'smart-cycle-discounts' ) . '</span>';
		}

		$output = '';

		if ( isset( $metrics['total_revenue'] ) ) {
			$output .= sprintf(
				'<strong>%s:</strong> %s<br>',
				__( 'Revenue', 'smart-cycle-discounts' ),
				wc_price( $metrics['total_revenue'] )
			);
		}

		if ( isset( $metrics['total_orders'] ) ) {
			$output .= sprintf(
				'<strong>%s:</strong> %s<br>',
				__( 'Orders', 'smart-cycle-discounts' ),
				number_format( $metrics['total_orders'] )
			);
		}

		if ( isset( $metrics['conversion_rate'] ) ) {
			$output .= sprintf(
				'<strong>%s:</strong> %s%%',
				__( 'Conversion', 'smart-cycle-discounts' ),
				number_format( $metrics['conversion_rate'], 1 )
			);
		}

		return $output ? $output : '<span class="description">' . __( 'No data yet', 'smart-cycle-discounts' ) . '</span>';
	}

	/**
	 * Render created column.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   string                       Created column HTML.
	 */
	public function column_created( $item ) {
		$campaign   = $item;
		$created_at = $campaign->get_created_at();
		$created_by = $campaign->get_created_by();

		// Convert string to DateTime if needed
		if ( is_string( $created_at ) ) {
			$created_at = new DateTime( $created_at );
		}

		// Convert from UTC to WordPress timezone before displaying
		$output = sprintf(
			'<strong>%s</strong><br>',
			wp_date( 'M j, Y', $created_at->getTimestamp() )
		);

		$output .= sprintf(
			'<small>%s</small><br>',
			wp_date( 'g:i A', $created_at->getTimestamp() )
		);

		if ( $created_by ) {
			$user = get_user_by( 'id', $created_by );
			if ( $user ) {
				$output .= sprintf(
					'<small>%s: %s</small>',
					__( 'by', 'smart-cycle-discounts' ),
					esc_html( $user->display_name )
				);
			}
		}

		return $output;
	}

	/**
	 * Render priority column.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   string                       Priority column HTML.
	 */
	public function column_priority( $item ) {
		$campaign = $item;
		$priority = $campaign->get_priority();

		return sprintf(
			'<span class="scd-priority-badge scd-priority-%d" title="%s">%d</span>',
			$priority,
			esc_attr( sprintf( __( 'Priority: %d', 'smart-cycle-discounts' ), $priority ) ),
			$priority
		);
	}

	/**
	 * Render health column.
	 *
	 * Uses unified Campaign Health Service for consistent health analysis.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign    Campaign object.
	 * @return   string                       Health column HTML.
	 */
	public function column_health( $item ) {
		$campaign = $item;

		// Get health service from container
		$health_service = null;
		if ( isset( $this->container ) && $this->container->has( 'campaign_health_service' ) ) {
			$health_service = $this->container->get( 'campaign_health_service' );
		}

		if ( $health_service ) {
			// Calculate coverage data using existing coverage handler service
			$coverage_data = array();
			if ( class_exists( 'SCD_Preview_Coverage_Handler' ) ) {
				$coverage_handler = new SCD_Preview_Coverage_Handler();

				// Build campaign data array for coverage handler
				$basic_data     = array(
					'priority' => $campaign->get_priority(),
				);
				$products_data  = array(
					'product_selection_type' => $campaign->get_product_selection_type(),
					'product_ids'            => $campaign->get_product_ids(),
					'category_ids'           => $campaign->get_category_ids(),
					'random_count'           => $campaign->get_setting( 'random_count', 0 ),
				);
				$discounts_data = array(
					'apply_to_sale_items' => (bool) $campaign->get_setting( 'apply_to_sale_items', false ),
				);

				// Use coverage handler's internal method via reflection
				$reflection = new ReflectionClass( $coverage_handler );
				$method     = $reflection->getMethod( '_calculate_coverage' );
				$method->setAccessible( true );
				$coverage_data = $method->invoke( $coverage_handler, $basic_data, $products_data, $discounts_data );
			}

			// Build context array with coverage data
			$context = array(
				'coverage_data' => $coverage_data,
				'view_context'  => 'dashboard',
			);

			// Use unified health service with full context data
			$health_analysis = $health_service->analyze_health( $campaign, 'standard', $context );

			$health_score  = $health_analysis['score'];
			$health_status = $health_analysis['status'];

			// Collect issues for tooltip
			$health_issues = array();
			foreach ( $health_analysis['critical_issues'] as $issue ) {
				$health_issues[] = isset( $issue['message'] ) ? $issue['message'] : '';
			}
			foreach ( $health_analysis['warnings'] as $warning ) {
				$health_issues[] = isset( $warning['message'] ) ? $warning['message'] : '';
			}
		} else {
			// Fallback if service not available
			$health_score  = 100;
			$health_status = 'excellent';
			$health_issues = array();
		}

		// Get icon based on status
		$health_icons = array(
			'critical'  => '🔴',  // Critical issues - campaign broken
			'excellent' => '🟢',
			'good'      => '🟡',
			'fair'      => '🟠',
			'poor'      => '⚠️',   // Poor but not critical
		);

		$icon = isset( $health_icons[ $health_status ] ) ? $health_icons[ $health_status ] : '⚪';

		$title = sprintf(
			__( 'Health Score: %1$d/100 (%2$s)', 'smart-cycle-discounts' ),
			$health_score,
			ucfirst( $health_status )
		);

		if ( ! empty( $health_issues ) ) {
			$title .= ' - ' . implode( ', ', $health_issues );
		}

		return sprintf(
			'<span class="scd-health-indicator scd-health-%s" title="%s">%s <strong>%d</strong></span>',
			esc_attr( $health_status ),
			esc_attr( $title ),
			$icon,
			$health_score
		);
	}

	/**
	 * Render default column.
	 *
	 * @since    1.0.0
	 * @param    SCD_Campaign $campaign       Campaign object.
	 * @param    string       $column_name    Column name.
	 * @return   string                          Column HTML.
	 */
	public function column_default( $item, $column_name ) {
		$campaign = $item;
		return apply_filters( 'scd_campaigns_list_table_column_' . $column_name, '', $campaign );
	}

	/**
	 * Process bulk actions.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function process_bulk_action() {
		$action = $this->current_action();

		if ( ! $action ) {
			return;
		}

		// Skip if this is not a bulk action request
		if ( empty( $_REQUEST['action'] ) && empty( $_REQUEST['action2'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! wp_verify_nonce( isset( $_REQUEST['_wpnonce'] ) ? $_REQUEST['_wpnonce'] : '', 'bulk-campaigns' ) ) {
			return; // Silent fail on nonce verification to avoid issues during page load
		}

		$campaign_ids = array_map( 'intval', isset( $_REQUEST['campaign'] ) ? $_REQUEST['campaign'] : array() );

		if ( empty( $campaign_ids ) ) {
			// If no campaigns selected, redirect with error message
			$redirect_args = array(
				'page'  => 'scd-campaigns',
				'error' => 'no_campaigns_selected',
			);
			$redirect_url  = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );
			wp_redirect( $redirect_url );
			exit;
		}

		$processed = 0;
		$errors    = array();

		foreach ( $campaign_ids as $campaign_id ) {
			try {
				switch ( $action ) {
					case 'activate':
						if ( $this->capability_manager->current_user_can( 'scd_activate_campaigns' ) ) {
							// Check if campaign is expired - skip with clear message
							$campaign = $this->campaign_manager->find( $campaign_id );
							if ( $campaign && 'expired' === $campaign->get_status() ) {
								$errors[] = sprintf(
									__( 'Campaign "%s": Cannot activate expired campaigns. Please edit the campaign to update the schedule first.', 'smart-cycle-discounts' ),
									$campaign->get_name()
								);
							} else {
								$result = $this->campaign_manager->activate( $campaign_id );
								if ( ! is_wp_error( $result ) ) {
									++$processed;
								} else {
									$errors[] = sprintf(
										__( 'Campaign ID %1$d: %2$s', 'smart-cycle-discounts' ),
										$campaign_id,
										$result->get_error_message()
									);
								}
							}
						}
						break;
					case 'deactivate':
						if ( $this->capability_manager->current_user_can( 'scd_activate_campaigns' ) ) {
							$result = $this->campaign_manager->pause( $campaign_id );
							if ( ! is_wp_error( $result ) ) {
								++$processed;
							} else {
								$errors[] = sprintf(
									__( 'Campaign ID %1$d: %2$s', 'smart-cycle-discounts' ),
									$campaign_id,
									$result->get_error_message()
								);
							}
						}
						break;
					case 'delete':
						if ( $this->capability_manager->current_user_can( 'scd_delete_campaigns' ) ) {
							$result = $this->campaign_manager->delete( $campaign_id );
							if ( ! is_wp_error( $result ) ) {
								++$processed;
							} else {
								$errors[] = sprintf(
									__( 'Campaign ID %1$d: %2$s', 'smart-cycle-discounts' ),
									$campaign_id,
									$result->get_error_message()
								);
							}
						}
						break;
					case 'restore':
						if ( $this->capability_manager->current_user_can( 'scd_edit_campaigns' ) ) {
							$repository = $this->campaign_manager->get_repository();
							$result     = $repository->restore( $campaign_id );
							if ( $result ) {
								++$processed;
							} else {
								$errors[] = sprintf(
									__( 'Campaign ID %d: Failed to restore', 'smart-cycle-discounts' ),
									$campaign_id
								);
							}
						}
						break;
					case 'delete_permanently':
						if ( $this->capability_manager->current_user_can( 'scd_delete_campaigns' ) ) {
							$repository = $this->campaign_manager->get_repository();
							$result     = $repository->force_delete( $campaign_id );
							if ( $result ) {
								++$processed;
							} else {
								$errors[] = sprintf(
									__( 'Campaign ID %d: Failed to delete permanently', 'smart-cycle-discounts' ),
									$campaign_id
								);
							}
						}
						break;
					case 'stop_recurring':
						if ( $this->capability_manager->current_user_can( 'scd_edit_campaigns' ) ) {
							// Stop recurring for this campaign
							if ( isset( $this->container ) && $this->container->has( 'database_manager' ) ) {
								$db         = $this->container->get( 'database_manager' );
								$table_name = $db->get_table_name( 'campaign_recurring' );

								$result = $db->update(
									'campaign_recurring',
									array( 'is_active' => 0 ),
									array(
										'campaign_id' => $campaign_id,
										'parent_campaign_id' => 0,
									)
								);

								if ( false !== $result ) {
									++$processed;
								} else {
									$errors[] = sprintf(
										__( 'Campaign ID %d: Failed to stop recurring', 'smart-cycle-discounts' ),
										$campaign_id
									);
								}
							}
						}
						break;
				}
			} catch ( Exception $e ) {
				$errors[] = sprintf(
					__( 'Campaign ID %1$d: %2$s', 'smart-cycle-discounts' ),
					$campaign_id,
					$e->getMessage()
				);
			}
		}

		// Redirect with appropriate messages
		if ( $processed > 0 || ! empty( $errors ) ) {
			$redirect_args = array( 'page' => 'scd-campaigns' );

			if ( $processed > 0 ) {
				$redirect_args['message']     = 'bulk_action_success';
				$redirect_args['count']       = $processed;
				$redirect_args['bulk_action'] = $action;
			}

			if ( ! empty( $errors ) ) {
				$redirect_args['error']       = 'bulk_action_error';
				$redirect_args['error_count'] = count( $errors );
				// Store errors in transient for display after redirect
				set_transient( 'scd_bulk_action_errors_' . get_current_user_id(), $errors, 60 );
			}

			$redirect_url = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );
			wp_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Get bulk action success message.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $action      Action performed.
	 * @param    int    $processed   Number of items processed.
	 * @return   string                 Success message.
	 */
	private function get_bulk_action_message( $action, $processed ) {
		switch ( $action ) {
			case 'activate':
				return sprintf(
					_n(
						'%d campaign activated.',
						'%d campaigns activated.',
						$processed,
						'smart-cycle-discounts'
					),
					$processed
				);
			case 'deactivate':
				return sprintf(
					_n(
						'%d campaign deactivated.',
						'%d campaigns deactivated.',
						$processed,
						'smart-cycle-discounts'
					),
					$processed
				);
			case 'delete':
				return sprintf(
					_n(
						'%d campaign moved to trash.',
						'%d campaigns moved to trash.',
						$processed,
						'smart-cycle-discounts'
					),
					$processed
				);
			case 'restore':
				return sprintf(
					_n(
						'%d campaign restored from trash.',
						'%d campaigns restored from trash.',
						$processed,
						'smart-cycle-discounts'
					),
					$processed
				);
			case 'delete_permanently':
				return sprintf(
					_n(
						'%d campaign permanently deleted.',
						'%d campaigns permanently deleted.',
						$processed,
						'smart-cycle-discounts'
					),
					$processed
				);
			case 'stop_recurring':
				return sprintf(
					_n(
						'%d campaign recurring stopped.',
						'%d campaigns recurring stopped.',
						$processed,
						'smart-cycle-discounts'
					),
					$processed
				);
			default:
				return sprintf(
					_n(
						'%d campaign processed.',
						'%d campaigns processed.',
						$processed,
						'smart-cycle-discounts'
					),
					$processed
				);
		}
	}

	/**
	 * Add admin notice.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $message    Notice message.
	 * @param    string $type       Notice type.
	 * @return   void
	 */
	private function add_admin_notice( $message, $type = 'info' ) {
		$notices   = get_transient( 'scd_admin_notices' );
		$notices   = $notices ? $notices : array();
		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);
		set_transient( 'scd_admin_notices', $notices, 300 );
	}

	/**
	 * Display extra table navigation.
	 *
	 * @since    1.0.0
	 * @param    string $which    Position (top or bottom).
	 * @return   void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		// Check if viewing trash
		$viewing_trash = isset( $_REQUEST['status'] ) && 'trash' === $_REQUEST['status'];

		?>
		<div class="alignleft actions">
			<?php $this->status_filter_dropdown(); ?>
			<?php submit_button( __( 'Filter', 'smart-cycle-discounts' ), '', 'filter_action', false, array( 'id' => 'post-query-submit' ) ); ?>

			<?php if ( $viewing_trash && $this->capability_manager->current_user_can( 'scd_delete_campaigns' ) ) : ?>
				<?php
				// Get trash count
				$repository = $this->campaign_manager->get_repository();
				if ( $repository ) {
					$trash_count = $repository->count_trashed();
					if ( $trash_count > 0 ) :
						?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=scd-campaigns&action=empty_trash' ), 'scd_empty_trash' ) ); ?>"
							class="button"
							onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to permanently delete all campaigns in the trash? This action cannot be undone.', 'smart-cycle-discounts' ) ); ?>');">
							<?php echo esc_html__( 'Empty Trash', 'smart-cycle-discounts' ); ?>
						</a>
						<?php
					endif;
				}
				?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Display status filter dropdown.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function status_filter_dropdown() {
		$current_status = isset( $_REQUEST['status'] ) ? $_REQUEST['status'] : '';
		$statuses       = array(
			''          => __( 'All statuses', 'smart-cycle-discounts' ),
			'active'    => __( 'Active', 'smart-cycle-discounts' ),
			'scheduled' => __( 'Scheduled', 'smart-cycle-discounts' ),
			'paused'    => __( 'Paused', 'smart-cycle-discounts' ),
			'expired'   => __( 'Expired', 'smart-cycle-discounts' ),
			'draft'     => __( 'Draft', 'smart-cycle-discounts' ),
		);

		?>
		<select name="status" id="filter-by-status">
			<?php foreach ( $statuses as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_status, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Display when no items found.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function no_items() {
		if ( ! empty( $_REQUEST['s'] ) ) {
			esc_html_e( 'No campaigns found matching your search.', 'smart-cycle-discounts' );
		} else {
			esc_html_e( 'No campaigns found.', 'smart-cycle-discounts' );

			if ( $this->capability_manager->current_user_can( 'scd_create_campaigns' ) ) {
				echo ' ';
				printf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=scd-campaigns&action=wizard&intent=new' ) ),
					esc_html__( 'Create your first campaign', 'smart-cycle-discounts' )
				);
			}
		}
	}

	/**
	 * Get views for the list table.
	 *
	 * @since    1.0.0
	 * @return   array    Views array.
	 */
	protected function get_views() {
		$views          = array();
		$current_status = isset( $_REQUEST['status'] ) ? $_REQUEST['status'] : '';

		// Get status counts
		$status_counts = $this->campaign_manager->get_status_counts();

		// Get trash count
		$repository  = $this->campaign_manager->get_repository();
		$trash_count = $repository->count_trashed();

		// All campaigns (excluding trash)
		$class        = ( empty( $current_status ) || 'trash' !== $current_status ) ? 'current' : '';
		$views['all'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url( admin_url( 'admin.php?page=scd-campaigns' ) ),
			$class,
			__( 'All', 'smart-cycle-discounts' ),
			isset( $status_counts['total'] ) ? $status_counts['total'] : 0
		);

		// Individual statuses
		$statuses = array(
			'active'    => __( 'Active', 'smart-cycle-discounts' ),
			'scheduled' => __( 'Scheduled', 'smart-cycle-discounts' ),
			'paused'    => __( 'Paused', 'smart-cycle-discounts' ),
			'expired'   => __( 'Expired', 'smart-cycle-discounts' ),
			'draft'     => __( 'Draft', 'smart-cycle-discounts' ),
		);

		foreach ( $statuses as $status => $label ) {
			$count = isset( $status_counts[ $status ] ) ? $status_counts[ $status ] : 0;
			if ( 0 < $count ) {
				$class            = $current_status === $status ? 'current' : '';
				$views[ $status ] = sprintf(
					'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
					esc_url( admin_url( 'admin.php?page=scd-campaigns&status=' . $status ) ),
					$class,
					$label,
					$count
				);
			}
		}

		// Trash view
		if ( 0 < $trash_count ) {
			$class          = 'trash' === $current_status ? 'current' : '';
			$views['trash'] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( admin_url( 'admin.php?page=scd-campaigns&status=trash' ) ),
				$class,
				__( 'Trash', 'smart-cycle-discounts' ),
				$trash_count
			);
		}

		return $views;
	}

	/**
	 * Output quick edit inline form.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function inline_edit() {
		?>
		<table style="display: none">
			<tbody id="scd-quick-edit">
				<tr id="scd-quick-edit-row" class="inline-edit-row inline-edit-row-campaign quick-edit-row quick-edit-row-campaign inline-edit-campaign" style="display: none">
					<td colspan="<?php echo esc_attr( $this->get_column_count() ); ?>" class="colspanchange">
						<fieldset class="inline-edit-col-left">
							<legend class="inline-edit-legend"><?php echo esc_html__( 'Quick Edit', 'smart-cycle-discounts' ); ?></legend>
							<div class="inline-edit-col">
								<label>
									<span class="title"><?php echo esc_html__( 'Name', 'smart-cycle-discounts' ); ?></span>
									<span class="input-text-wrap">
										<input type="text" name="name" class="ptitle" value="" />
									</span>
								</label>

								<label>
									<span class="title"><?php echo esc_html__( 'Status', 'smart-cycle-discounts' ); ?></span>
									<span class="input-text-wrap">
										<select name="status">
											<option value="draft"><?php echo esc_html__( 'Draft', 'smart-cycle-discounts' ); ?></option>
											<option value="active"><?php echo esc_html__( 'Active', 'smart-cycle-discounts' ); ?></option>
											<option value="paused"><?php echo esc_html__( 'Paused', 'smart-cycle-discounts' ); ?></option>
											<option value="scheduled"><?php echo esc_html__( 'Scheduled', 'smart-cycle-discounts' ); ?></option>
										</select>
									</span>
								</label>

								<label>
									<span class="title"><?php echo esc_html__( 'Priority', 'smart-cycle-discounts' ); ?></span>
									<span class="input-text-wrap">
										<input type="number" name="priority" value="" min="1" max="5" />
									</span>
								</label>
							</div>
						</fieldset>

						<fieldset class="inline-edit-col-right">
							<div class="inline-edit-col">
								<label>
									<span class="title"><?php echo esc_html__( 'Discount Value', 'smart-cycle-discounts' ); ?></span>
									<span class="input-text-wrap">
										<input type="number" name="discount_value" value="" step="0.01" min="0" />
									</span>
								</label>

								<label>
									<span class="title"><?php echo esc_html__( 'Start Date', 'smart-cycle-discounts' ); ?></span>
									<span class="input-text-wrap">
										<input type="datetime-local" name="start_date" value="" />
									</span>
								</label>

								<label>
									<span class="title"><?php echo esc_html__( 'End Date', 'smart-cycle-discounts' ); ?></span>
									<span class="input-text-wrap">
										<input type="datetime-local" name="end_date" value="" />
									</span>
								</label>
							</div>
						</fieldset>

						<div class="inline-edit-save submit">
							<button type="button" class="button button-primary save"><?php echo esc_html__( 'Update', 'smart-cycle-discounts' ); ?></button>
							<button type="button" class="button cancel"><?php echo esc_html__( 'Cancel', 'smart-cycle-discounts' ); ?></button>
							<span class="spinner"></span>
							<input type="hidden" name="campaign_id" value="" />
							<?php wp_nonce_field( 'scd_quick_edit', 'nonce' ); ?>
							<span class="error" style="display:none;color:red;margin-left:10px;"></span>
						</div>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}
}

