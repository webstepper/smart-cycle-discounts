<?php
/**
 * Campaigns List Table Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/components/class-campaigns-list-table.php
 * @author     Webstepper.io <contact@webstepper.io>
 * @copyright  2025 Webstepper.io
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
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
class WSSCD_Campaigns_List_Table extends WP_List_Table {

	/**
	 * Campaign manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Campaign_Manager    $campaign_manager    Campaign manager.
	 */
	private $campaign_manager;

	/**
	 * Capability manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Admin_Capability_Manager    $capability_manager    Capability manager.
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
	 * Row actions cache for current rendering cycle.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $row_actions_cache    Campaign ID => actions array mapping.
	 */
	private $row_actions_cache = array();

	/**
	 * Initialize the list table.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign_Manager         $campaign_manager     Campaign manager.
	 * @param    WSSCD_Admin_Capability_Manager $capability_manager   Capability manager.
	 */
	public function __construct( $campaign_manager, $capability_manager ) {
		$this->campaign_manager   = $campaign_manager;
		$this->capability_manager = $capability_manager;

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
			'cb'       => '<input type="checkbox" />',
			'name'     => __( 'Campaign Name', 'smart-cycle-discounts' ),
			'status'   => __( 'Status', 'smart-cycle-discounts' ),
			'schedule' => __( 'Schedule', 'smart-cycle-discounts' ),
			'health'   => __( 'Health', 'smart-cycle-discounts' ),
			'actions'  => __( 'Actions', 'smart-cycle-discounts' ),
		);

		return apply_filters( 'wsscd_campaigns_list_table_columns', $columns );
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
			'schedule' => array( 'start_date', false ),
			'health'   => array( 'health_score', false ),
		);
	}

	/**
	 * Get bulk actions.
	 *
	 * SECURITY: This method reads URL param for display context only.
	 * Capability checks are enforced below for each action type.
	 *
	 * @since    1.0.0
	 * @return   array    Bulk action definitions.
	 */
	public function get_bulk_actions() {
		// Defense in depth: verify user has base capability to view campaigns.
		if ( ! $this->capability_manager->current_user_can( 'wsscd_view_campaigns' ) ) {
			return array();
		}

		$actions = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL param for display context only. Capability checked above. Value validated against 'trash'.
		$viewing_trash = isset( $_REQUEST['status'] ) && 'trash' === sanitize_text_field( wp_unslash( $_REQUEST['status'] ) );

		if ( $viewing_trash ) {
			// Trash view actions
			if ( $this->capability_manager->current_user_can( 'wsscd_edit_campaigns' ) ) {
				$actions['restore'] = __( 'Restore', 'smart-cycle-discounts' );
			}

			if ( $this->capability_manager->current_user_can( 'wsscd_delete_campaigns' ) ) {
				$actions['delete_permanently'] = __( 'Delete Permanently', 'smart-cycle-discounts' );
			}
		} else {
			// Normal view actions
			if ( $this->capability_manager->current_user_can( 'wsscd_activate_campaigns' ) ) {
				$actions['activate']   = __( 'Activate', 'smart-cycle-discounts' );
				$actions['deactivate'] = __( 'Deactivate', 'smart-cycle-discounts' );
			}

			if ( $this->capability_manager->current_user_can( 'wsscd_delete_campaigns' ) ) {
				$actions['delete'] = __( 'Move to Trash', 'smart-cycle-discounts' );
			}

			if ( $this->capability_manager->current_user_can( 'wsscd_edit_campaigns' ) ) {
				$actions['stop_recurring'] = __( 'Stop Recurring', 'smart-cycle-discounts' );
			}
		}

		return $actions;
	}

	/**
	 * Prepare table items.
	 *
	 * SECURITY: This method reads URL params for list table display only.
	 * Capability is checked at start. All values are sanitized and validated.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function prepare_items() {
		// Defense in depth: verify user has capability to view campaigns.
		if ( ! $this->capability_manager->current_user_can( 'wsscd_view_campaigns' ) ) {
			$this->items = array();
			return;
		}

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);

		// Handle bulk actions
		$this->process_bulk_action();

		$current_page = $this->get_pagenum();
		$per_page     = $this->get_items_per_page( 'wsscd_campaigns_per_page', 20 );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading URL params for list table display. Capability checked above. Values sanitized and validated.
		$viewing_trash = isset( $_REQUEST['status'] ) && 'trash' === sanitize_text_field( wp_unslash( $_REQUEST['status'] ) );

		if ( $viewing_trash ) {
			$repository = $this->campaign_manager->get_repository();
			$options    = array(
				'limit'           => $per_page,
				'offset'          => ( $current_page - 1 ) * $per_page,
				'order_by'        => isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'deleted_at',
				'order_direction' => isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC',
			);

			$campaigns   = $repository->find_trashed( $options );
			$total_items = $repository->count_trashed();
		} else {
			$args = array(
				'limit'   => $per_page,
				'offset'  => ( $current_page - 1 ) * $per_page,
				'orderby' => isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at',
				'order'   => isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC',
			);

			if ( ! empty( $_REQUEST['s'] ) ) {
				$args['search'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
			}

			if ( ! empty( $_REQUEST['status'] ) ) {
				$args['status'] = sanitize_text_field( wp_unslash( $_REQUEST['status'] ) );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			$campaigns   = $this->campaign_manager->get_campaigns( $args );
			$total_items = $this->campaign_manager->count_campaigns( $args );
		}

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
	 * @param    WSSCD_Campaign $campaign    Campaign object.
	 * @return   string                       Checkbox HTML.
	 */
	public function column_cb( $item ) {
		$campaign = $item; // Type clarity - $item is WSSCD_Campaign object

		return sprintf(
			'<input type="checkbox" name="campaign[]" value="%d" />',
			$campaign->get_id()
		);
	}

	/**
	 * Render name column.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $campaign    Campaign object.
	 * @return   string                       Name column HTML.
	 */
	public function column_name( $item ) {
		$campaign = $item; // Type clarity - $item is WSSCD_Campaign object

		$campaign_id   = $campaign->get_id();
		$campaign_name = esc_html( $campaign->get_name() );

		$recurring_badge    = '';
		$recurring_settings = null;
		if ( isset( $this->container ) && $this->container->has( 'recurring_handler' ) ) {
			$recurring_handler  = $this->container->get( 'recurring_handler' );
			$recurring_settings = $recurring_handler->get_recurring_settings( $campaign_id );

			if ( $recurring_settings ) {
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
							' <span class="wsscd-recurring-badge wsscd-recurring-active" title="%s">🔄 %s</span>',
							esc_attr( __( 'This campaign repeats automatically', 'smart-cycle-discounts' ) ),
							esc_html( $pattern_text )
						);
					} else {
						// Recurring but inactive
						$recurring_badge = sprintf(
							' <span class="wsscd-recurring-badge wsscd-recurring-inactive" title="%s">⏸️ %s</span>',
							esc_attr( __( 'Recurring stopped', 'smart-cycle-discounts' ) ),
							esc_html( __( 'Stopped', 'smart-cycle-discounts' ) )
						);
					}
				} else {
					// Child campaign
					$recurring_badge = sprintf(
						' <span class="wsscd-recurring-badge wsscd-recurring-child" title="%s">↳ %s #%d</span>',
						esc_attr( __( 'Created by recurring campaign', 'smart-cycle-discounts' ) ),
						esc_html( __( 'Occurrence', 'smart-cycle-discounts' ) ),
						intval( $recurring_settings['occurrence_number'] )
					);
				}
			}
		}

		// Free shipping badge.
		$free_shipping_badge = '';
		if ( $campaign->is_free_shipping_enabled() ) {
			$free_shipping_config = $campaign->get_free_shipping_config();
			$methods              = isset( $free_shipping_config['methods'] ) ? $free_shipping_config['methods'] : 'all';

			if ( 'all' === $methods ) {
				$methods_info = __( 'All', 'smart-cycle-discounts' );
			} elseif ( is_array( $methods ) ) {
				$count        = count( $methods );
				$methods_info = sprintf(
					/* translators: %d: number of shipping methods */
					_n( '%d method', '%d methods', $count, 'smart-cycle-discounts' ),
					$count
				);
			} else {
				$methods_info = '';
			}

			$free_shipping_badge = ' ' . WSSCD_Badge_Helper::free_shipping_badge(
				__( 'Free shipping enabled for this campaign', 'smart-cycle-discounts' ),
				$methods_info
			);
		}

		$actions = array();

		$deleted_at = $campaign->get_deleted_at();
		$is_trashed = null !== $deleted_at;

		if ( $is_trashed ) {
			// Trash view actions
			if ( $this->capability_manager->current_user_can( 'edit_campaign', $campaign_id ) ) {
				$actions['restore'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url(
						wp_nonce_url(
							admin_url( 'admin.php?page=wsscd-campaigns&action=restore&id=' . $campaign_id ),
							'wsscd_restore_campaign_' . $campaign_id
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
							admin_url( 'admin.php?page=wsscd-campaigns&action=delete_permanently&id=' . $campaign_id ),
							'wsscd_delete_permanently_' . $campaign_id
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
					esc_url( admin_url( 'admin.php?page=wsscd-campaigns&action=wizard&intent=edit&id=' . $campaign_id ) ),
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
								admin_url( 'admin.php?page=wsscd-campaigns&action=deactivate&id=' . $campaign_id ),
								'wsscd-campaign-action-deactivate-' . $campaign_id
							)
						),
						__( 'Deactivate', 'smart-cycle-discounts' )
					);
				}
				// Show activate for draft, scheduled, and paused campaigns only
				// Expired campaigns should not be reactivated directly
				elseif ( in_array( $status, array( 'draft', 'scheduled', 'paused' ), true ) ) {
					// Check if end date has passed
					$ends_at    = $campaign->get_ends_at();
					$is_expired = false;
					if ( $ends_at ) {
						$now        = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
						$is_expired = ( $ends_at <= $now );
					}

					if ( $is_expired ) {
						// Show disabled activate button with tooltip for expired campaigns
						$end_date_display = wp_date( 'F j, Y g:i A', $ends_at->getTimestamp() );
						$actions['activate'] = sprintf(
							'<span class="disabled" title="%s" style="color: #a0a5aa; cursor: not-allowed;">%s</span>',
							esc_attr( sprintf(
								/* translators: %s: formatted end date and time */
								__( 'Cannot activate: End date has passed (%s). Please edit the campaign to set a future end date.', 'smart-cycle-discounts' ),
								$end_date_display
							) ),
							__( 'Activate', 'smart-cycle-discounts' )
						);
					} else {
						// Show normal activate button
						$actions['activate'] = sprintf(
							'<a href="%s">%s</a>',
							esc_url(
								wp_nonce_url(
									admin_url( 'admin.php?page=wsscd-campaigns&action=activate&id=' . $campaign_id ),
									'wsscd-campaign-action-activate-' . $campaign_id
								)
							),
							__( 'Activate', 'smart-cycle-discounts' )
						);
					}
				}
			}

			if ( $this->capability_manager->current_user_can( 'wsscd_create_campaigns' ) ) {
				$actions['duplicate'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url(
						wp_nonce_url(
							admin_url( 'admin.php?page=wsscd-campaigns&action=duplicate&id=' . $campaign_id ),
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
							admin_url( 'admin.php?page=wsscd-campaigns&action=delete&id=' . $campaign_id ),
							'wsscd-campaign-action-delete-' . $campaign_id
						)
					),
					$confirm_message,
					__( 'Move to Trash', 'smart-cycle-discounts' )
				);
			}

			if ( $recurring_settings && empty( $recurring_settings['parent_campaign_id'] ) && ! empty( $recurring_settings['is_active'] ) ) {
				if ( $this->capability_manager->current_user_can( 'edit_campaign', $campaign_id ) ) {
					$actions['stop_recurring'] = sprintf(
						'<a href="%s" class="stop-recurring" onclick="return confirm(\'%s\')">%s</a>',
						esc_url(
							wp_nonce_url(
								admin_url( 'admin-post.php?action=wsscd_stop_recurring&id=' . $campaign_id ),
								'wsscd_stop_recurring'
							)
						),
						esc_js( __( 'Are you sure you want to stop this campaign from recurring?', 'smart-cycle-discounts' ) ),
						__( 'Stop Recurring', 'smart-cycle-discounts' )
					);
				}
			}
		}

		$badges = $recurring_badge . $free_shipping_badge;

		$title = $this->capability_manager->current_user_can( 'edit_campaign', $campaign_id )
			? sprintf(
				'<a href="%s" class="row-title"><strong>%s</strong></a>%s',
				esc_url( admin_url( 'admin.php?page=wsscd-campaigns&action=wizard&intent=edit&id=' . $campaign_id ) ),
				esc_html( $campaign_name ),
				$badges
			)
			: sprintf( '<strong>%s</strong>%s', esc_html( $campaign_name ), $badges );

		if ( $campaign->get_description() ) {
			$title .= '<br><span class="description">' . esc_html( $campaign->get_description() ) . '</span>';
		}

		// Store row actions in cache for access in column_actions
		$this->row_actions_cache[ $campaign_id ] = $actions;

		return $title;
	}

	/**
	 * Render status column.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $campaign    Campaign object.
	 * @return   string                       Status column HTML.
	 */
	public function column_status( $item ) {
		$campaign = $item;
		$status   = $campaign->get_status();

		return wp_kses_post( WSSCD_Badge_Helper::status_badge( $status ) );
	}

	/**
	 * Render discount column.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $campaign    Campaign object.
	 * @return   string                       Discount column HTML.
	 */

	/**
	 * Render products column.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $campaign    Campaign object.
	 * @return   string                       Products column HTML.
	 */

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
			$display  .= ' ' . sprintf(
				/* translators: %d: number of additional categories not shown in the list */
				_n( 'and %d more', 'and %d more', $remaining, 'smart-cycle-discounts' ),
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
			$display  .= ' ' . sprintf(
				/* translators: %d: number of additional products not shown in the list */
				_n( 'and %d more', 'and %d more', $remaining, 'smart-cycle-discounts' ),
				$remaining
			);
		}

		return array( 'display' => $display );
	}

	/**
	 * Render schedule column.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $campaign    Campaign object.
	 * @return   string                       Schedule column HTML.
	 */
	public function column_schedule( $item ) {
		$campaign   = $item;
		$start_date = $campaign->get_starts_at();
		$end_date   = $campaign->get_ends_at();

		$output = '';

		if ( $start_date ) {
			$output .= sprintf(
				'<strong>%s:</strong> %s<br>',
				__( 'Start', 'smart-cycle-discounts' ),
				wp_date( 'M j, Y g:i A', $start_date->getTimestamp() )
			);
		}

		if ( $end_date ) {
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
	 * @param    WSSCD_Campaign $campaign    Campaign object.
	 * @return   string                       Performance column HTML.
	 */

	/**
	 * Render created column.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $campaign    Campaign object.
	 * @return   string                       Created column HTML.
	 */

	/**
	 * Render priority column.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $campaign    Campaign object.
	 * @return   string                       Priority column HTML.
	 */

	/**
	 * Render health column.
	 *
	 * Uses unified Campaign Health Service for consistent health analysis.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $campaign    Campaign object.
	 * @return   string                       Health column HTML.
	 */
	public function column_health( $item ) {
		$campaign = $item;

		$health_service = null;
		if ( isset( $this->container ) && $this->container->has( 'campaign_health_service' ) ) {
			$health_service = $this->container->get( 'campaign_health_service' );
		}

		if ( $health_service ) {
			$coverage_data = array();
			if ( class_exists( 'WSSCD_Preview_Coverage_Handler' ) ) {
				$coverage_handler = new WSSCD_Preview_Coverage_Handler();

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

		// Map status to icons
		$health_icons = array(
			'critical'  => 'dismiss',     // X icon for critical
			'poor'      => 'warning',     // Warning triangle
			'fair'      => 'info',        // Info circle
			'good'      => 'yes',         // Checkmark
			'excellent' => 'shield-alt',  // Shield with checkmark
		);

		$icon_name = isset( $health_icons[ $health_status ] ) ? $health_icons[ $health_status ] : 'info';
		$icon      = WSSCD_Icon_Helper::get( $icon_name, array( 'size' => 16 ) );

		$title = sprintf(
			/* translators: %1$d: health score number, %2$s: health status label (e.g., Excellent, Good, Fair) */
			__( 'Health Score: %1$d/100 (%2$s)', 'smart-cycle-discounts' ),
			$health_score,
			ucfirst( $health_status )
		);

		if ( ! empty( $health_issues ) ) {
			$title .= ' - ' . implode( ', ', $health_issues );
		}

		return sprintf(
			'<span class="wsscd-health-indicator wsscd-health-%s" title="%s">%s <strong>%d</strong></span>',
			esc_attr( $health_status ),
			esc_attr( $title ),
			wp_kses( $icon, WSSCD_Icon_Helper::get_allowed_svg_tags() ),
			$health_score
		);
	}

	/**
	 * Render actions column.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $item    Campaign object.
	 * @return   string                  Actions column HTML.
	 */
	public function column_actions( $item ) {
		$campaign    = $item;
		$campaign_id = $campaign->get_id();
		$status      = $campaign->get_status();

		$buttons = array();

		// Get row actions from cache (generated in column_name)
		$row_actions = isset( $this->row_actions_cache[ $campaign_id ] ) ? $this->row_actions_cache[ $campaign_id ] : array();

		$deleted_at = $campaign->get_deleted_at();
		$is_trashed = null !== $deleted_at;

		// View Details button (opens Campaign Overview Panel)
		$view_icon = class_exists( 'WSSCD_Icon_Helper' )
			? WSSCD_Icon_Helper::get( 'visibility', array( 'size' => 20, 'aria_hidden' => true ) )
			: '';

		$buttons['view'] = sprintf(
			'<button type="button" class="wsscd-button wsscd-button--primary wsscd-button--icon-only wsscd-view-campaign" data-campaign-id="%d" title="%s" aria-label="%s">%s</button>',
			$campaign_id,
			esc_attr__( 'View campaign details', 'smart-cycle-discounts' ),
			esc_attr__( 'View Details', 'smart-cycle-discounts' ),
			wp_kses( $view_icon, WSSCD_Icon_Helper::get_allowed_svg_tags() )
		);

		// Convert row actions to icon-only buttons
		foreach ( $row_actions as $action => $link ) {
			$icon        = '';
			$title       = '';
			$url         = '';
			$classes     = 'wsscd-button wsscd-button--secondary wsscd-button--icon-only';
			$confirm_msg = '';

			switch ( $action ) {
				case 'edit':
					$icon    = 'edit';
					$title   = __( 'Edit', 'smart-cycle-discounts' );
					$classes = 'wsscd-button wsscd-button--primary wsscd-button--icon-only';
					// Extract URL from link and decode HTML entities
					preg_match( '/href=["\']([^"\']+)["\']/', $link, $matches );
					$url = isset( $matches[1] ) ? html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) : '';
					break;

				case 'activate':
					$icon  = 'play';
					$title = __( 'Activate', 'smart-cycle-discounts' );
					// Check if this is a disabled button (span) or active link (a)
					if ( strpos( $link, '<span class="disabled"' ) !== false ) {
						// Disabled button - extract tooltip from title attribute
						preg_match( '/title=["\']([^"\']+)["\']/', $link, $title_matches );
						if ( isset( $title_matches[1] ) ) {
							$title = html_entity_decode( $title_matches[1], ENT_QUOTES, 'UTF-8' );
						}
						$url = ''; // No URL for disabled buttons
					} else {
						// Active link - extract URL
						preg_match( '/href=["\']([^"\']+)["\']/', $link, $matches );
						$url = isset( $matches[1] ) ? html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) : '';
					}
					break;

				case 'deactivate':
					$icon  = 'pause';
					$title = __( 'Pause', 'smart-cycle-discounts' );
					preg_match( '/href=["\']([^"\']+)["\']/', $link, $matches );
					$url = isset( $matches[1] ) ? html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) : '';
					break;

				case 'duplicate':
					$icon  = 'copy';
					$title = __( 'Duplicate', 'smart-cycle-discounts' );
					preg_match( '/href=["\']([^"\']+)["\']/', $link, $matches );
					$url = isset( $matches[1] ) ? html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) : '';
					break;

				case 'delete':
					$icon  = 'delete';
					$title = __( 'Move to Trash', 'smart-cycle-discounts' );
					preg_match( '/href=["\']([^"\']+)["\']/', $link, $matches );
					$url = isset( $matches[1] ) ? html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) : '';
					preg_match( '/confirm\([\'"]([^"\']+)[\'"]\)/', $link, $confirm_matches );
					if ( isset( $confirm_matches[1] ) ) {
						$confirm_msg = $confirm_matches[1];
					}
					break;

				case 'restore':
					$icon  = 'undo';
					$title = __( 'Restore', 'smart-cycle-discounts' );
					preg_match( '/href=["\']([^"\']+)["\']/', $link, $matches );
					$url = isset( $matches[1] ) ? html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) : '';
					break;

				case 'delete_permanently':
					$icon  = 'delete';
					$title = __( 'Delete Permanently', 'smart-cycle-discounts' );
					preg_match( '/href=["\']([^"\']+)["\']/', $link, $matches );
					$url = isset( $matches[1] ) ? html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) : '';
					preg_match( '/confirm\([\'"]([^"\']+)[\'"]\)/', $link, $confirm_matches );
					if ( isset( $confirm_matches[1] ) ) {
						$confirm_msg = $confirm_matches[1];
					}
					break;

				case 'stop_recurring':
					$icon  = 'repeat';
					$title = __( 'Stop Recurring', 'smart-cycle-discounts' );
					preg_match( '/href=["\']([^"\']+)["\']/', $link, $matches );
					$url = isset( $matches[1] ) ? html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) : '';
					preg_match( '/confirm\([\'"]([^"\']+)[\'"]\)/', $link, $confirm_matches );
					if ( isset( $confirm_matches[1] ) ) {
						$confirm_msg = $confirm_matches[1];
					}
					break;

				default:
					continue 2; // Skip unknown actions
			}

			// Get SVG icon
			$icon_html = class_exists( 'WSSCD_Icon_Helper' ) && WSSCD_Icon_Helper::has_icon( $icon )
				? WSSCD_Icon_Helper::get( $icon, array( 'size' => 20, 'aria_hidden' => true ) )
				: '';

			if ( $url ) {
				// Build onclick handler with optional confirmation
				// Note: URL has been decoded from HTML entities, now escape for JavaScript context
				$onclick_handler = $confirm_msg
					? sprintf( 'if(confirm(\'%s\')) window.location.href=\'%s\'', esc_js( $confirm_msg ), esc_js( $url ) )
					: sprintf( 'window.location.href=\'%s\'', esc_js( $url ) );

				// Render as button that navigates to URL
				$buttons[ $action ] = sprintf(
					'<button type="button" class="%s" onclick="%s" title="%s" aria-label="%s">%s</button>',
					esc_attr( $classes ),
					$onclick_handler,
					esc_attr( $title ),
					esc_attr( $title ),
					wp_kses( $icon_html, WSSCD_Icon_Helper::get_allowed_svg_tags() )
				);
			} elseif ( 'activate' === $action && ! $url ) {
				// Render disabled activate button (for expired campaigns)
				$buttons[ $action ] = sprintf(
					'<button type="button" class="%s" disabled="disabled" title="%s" aria-label="%s" style="opacity: 0.5; cursor: not-allowed;">%s</button>',
					esc_attr( $classes ),
					esc_attr( $title ),
					esc_attr( $title ),
					wp_kses( $icon_html, WSSCD_Icon_Helper::get_allowed_svg_tags() )
				);
			}
		}

		return '<div class="wsscd-actions-column">' . implode( ' ', $buttons ) . '</div>';
	}

	/**
	 * Render default column.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $campaign       Campaign object.
	 * @param    string       $column_name    Column name.
	 * @return   string                          Column HTML.
	 */
	public function column_default( $item, $column_name ) {
		$campaign = $item;
		return apply_filters( 'wsscd_campaigns_list_table_column_' . $column_name, '', $campaign );
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

		// SECURITY: Nonce verification using WordPress standard 'bulk-campaigns' action.
		// This nonce is generated by WP_List_Table and verified here before any bulk operations are processed.
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bulk-campaigns' ) ) {
			return; // Silent fail on nonce verification to avoid issues during page load.
		}

		$campaign_ids = array_map( 'intval', isset( $_REQUEST['campaign'] ) ? $_REQUEST['campaign'] : array() );

		if ( empty( $campaign_ids ) ) {
			// If no campaigns selected, redirect with error message
			$redirect_args = array(
				'page'  => 'wsscd-campaigns',
				'error' => 'no_campaigns_selected',
			);
			$redirect_url  = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$processed = 0;
		$errors    = array();

		foreach ( $campaign_ids as $campaign_id ) {
			try {
				switch ( $action ) {
					case 'activate':
						if ( $this->capability_manager->current_user_can( 'wsscd_activate_campaigns' ) ) {
							$campaign = $this->campaign_manager->find( $campaign_id );
							if ( $campaign && 'expired' === $campaign->get_status() ) {
								$errors[] = sprintf(
									/* translators: %s: campaign name */
									__( 'Campaign "%s": Cannot activate expired campaigns. Please edit the campaign to update the schedule first.', 'smart-cycle-discounts' ),
									$campaign->get_name()
								);
							} else {
								$result = $this->campaign_manager->activate( $campaign_id );
								if ( ! is_wp_error( $result ) ) {
									++$processed;
								} else {
									$errors[] = sprintf(
										/* translators: %1$d: campaign ID, %2$s: error message */
										__( 'Campaign ID %1$d: %2$s', 'smart-cycle-discounts' ),
										$campaign_id,
										$result->get_error_message()
									);
								}
							}
						}
						break;
					case 'deactivate':
						if ( $this->capability_manager->current_user_can( 'wsscd_activate_campaigns' ) ) {
							$result = $this->campaign_manager->pause( $campaign_id );
							if ( ! is_wp_error( $result ) ) {
								++$processed;
							} else {
								$errors[] = sprintf(
									/* translators: %1$d: campaign ID, %2$s: error message */
									__( 'Campaign ID %1$d: %2$s', 'smart-cycle-discounts' ),
									$campaign_id,
									$result->get_error_message()
								);
							}
						}
						break;
					case 'delete':
						if ( $this->capability_manager->current_user_can( 'wsscd_delete_campaigns' ) ) {
							$result = $this->campaign_manager->delete( $campaign_id );
							if ( ! is_wp_error( $result ) ) {
								++$processed;
							} else {
								$errors[] = sprintf(
									/* translators: %1$d: campaign ID, %2$s: error message */
									__( 'Campaign ID %1$d: %2$s', 'smart-cycle-discounts' ),
									$campaign_id,
									$result->get_error_message()
								);
							}
						}
						break;
					case 'restore':
						if ( $this->capability_manager->current_user_can( 'wsscd_edit_campaigns' ) ) {
							$repository = $this->campaign_manager->get_repository();
							$result     = $repository->restore( $campaign_id );
							if ( $result ) {
								++$processed;
							} else {
								$errors[] = sprintf(
									/* translators: %d: campaign ID */
									__( 'Campaign ID %d: Failed to restore', 'smart-cycle-discounts' ),
									$campaign_id
								);
							}
						}
						break;
					case 'delete_permanently':
						if ( $this->capability_manager->current_user_can( 'wsscd_delete_campaigns' ) ) {
							$repository = $this->campaign_manager->get_repository();
							$result     = $repository->force_delete( $campaign_id );
							if ( $result ) {
								++$processed;
							} else {
								$errors[] = sprintf(
									/* translators: %d: campaign ID */
									__( 'Campaign ID %d: Failed to delete permanently', 'smart-cycle-discounts' ),
									$campaign_id
								);
							}
						}
						break;
					case 'stop_recurring':
						if ( $this->capability_manager->current_user_can( 'wsscd_edit_campaigns' ) ) {
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
										/* translators: %d: campaign ID */
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
					/* translators: %1$d: campaign ID, %2$s: exception error message */
					__( 'Campaign ID %1$d: %2$s', 'smart-cycle-discounts' ),
					$campaign_id,
					$e->getMessage()
				);
			}
		}

		// Redirect with appropriate messages
		if ( $processed > 0 || ! empty( $errors ) ) {
			$redirect_args = array( 'page' => 'wsscd-campaigns' );

			if ( $processed > 0 ) {
				$redirect_args['message']     = 'bulk_action_success';
				$redirect_args['count']       = $processed;
				$redirect_args['bulk_action'] = $action;
			}

			if ( ! empty( $errors ) ) {
				$redirect_args['error']       = 'bulk_action_error';
				$redirect_args['error_count'] = count( $errors );
				set_transient( 'wsscd_bulk_action_errors_' . get_current_user_id(), $errors, 60 );
			}

			$redirect_url = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );
			wp_safe_redirect( $redirect_url );
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
					/* translators: %d: number of campaigns activated */
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
					/* translators: %d: number of campaigns deactivated */
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
					/* translators: %d: number of campaigns moved to trash */
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
					/* translators: %d: number of campaigns restored from trash */
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
					/* translators: %d: number of campaigns permanently deleted */
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
					/* translators: %d: number of recurring campaigns stopped */
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
					/* translators: %d: number of campaigns processed */
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
		$notices   = get_transient( 'wsscd_admin_notices' );
		$notices   = $notices ? $notices : array();
		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);
		set_transient( 'wsscd_admin_notices', $notices, 300 );
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL param for display context only.
		$viewing_trash = isset( $_REQUEST['status'] ) && 'trash' === sanitize_text_field( wp_unslash( $_REQUEST['status'] ) );

		?>
		<div class="alignleft actions">
			<?php $this->status_filter_dropdown(); ?>
			<?php submit_button( __( 'Filter', 'smart-cycle-discounts' ), '', 'filter_action', false, array( 'id' => 'post-query-submit' ) ); ?>

			<?php if ( $viewing_trash && $this->capability_manager->current_user_can( 'wsscd_delete_campaigns' ) ) : ?>
				<?php
				$repository = $this->campaign_manager->get_repository();
				if ( $repository ) {
					$trash_count = $repository->count_trashed();
					if ( $trash_count > 0 ) :
						?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wsscd-campaigns&action=empty_trash' ), 'wsscd_empty_trash' ) ); ?>"
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL param for dropdown selection.
		$current_status = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';
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
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading URL params for display context only.
		$is_search   = ! empty( $_REQUEST['s'] );
		$status      = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';
		$is_filtered = ! empty( $status ) && 'trash' !== $status;
		$is_trash    = 'trash' === $status;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		?>
		<div class="wsscd-empty-state">
			<div class="wsscd-empty-state__icon">
				<?php
								if ( $is_search ) {
					WSSCD_Icon_Helper::render( 'search', array( 'size' => 48 ) );
				} elseif ( $is_trash ) {
					WSSCD_Icon_Helper::render( 'trash', array( 'size' => 48 ) );
				} else {
					WSSCD_Icon_Helper::render( 'megaphone', array( 'size' => 48 ) );
				}
				
				?>
			</div>
			<h3 class="wsscd-empty-state__title">
				<?php
				if ( $is_search ) {
					esc_html_e( 'No campaigns found', 'smart-cycle-discounts' );
				} elseif ( $is_trash ) {
					esc_html_e( 'Trash is empty', 'smart-cycle-discounts' );
				} elseif ( $is_filtered ) {
					esc_html_e( 'No campaigns match this filter', 'smart-cycle-discounts' );
				} else {
					esc_html_e( 'No campaigns yet', 'smart-cycle-discounts' );
				}
				?>
			</h3>
			<p class="wsscd-empty-state__description">
				<?php
				if ( $is_search ) {
					esc_html_e( 'Try adjusting your search terms or clearing the search to see all campaigns.', 'smart-cycle-discounts' );
				} elseif ( $is_trash ) {
					esc_html_e( 'Items you delete will appear here for recovery.', 'smart-cycle-discounts' );
				} elseif ( $is_filtered ) {
					esc_html_e( 'Try selecting a different status filter or view all campaigns.', 'smart-cycle-discounts' );
				} else {
					esc_html_e( 'Create your first discount campaign to start boosting sales with automatic promotions.', 'smart-cycle-discounts' );
				}
				?>
			</p>
			<div class="wsscd-empty-state__actions">
				<?php if ( $is_search ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsscd-campaigns' ) ); ?>" class="wsscd-button wsscd-button--secondary">
						<?php WSSCD_Icon_Helper::render( 'dismiss', array( 'size' => 16 ) ); ?>
						<?php esc_html_e( 'Clear Search', 'smart-cycle-discounts' ); ?>
					</a>
				<?php elseif ( $is_filtered ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsscd-campaigns' ) ); ?>" class="wsscd-button wsscd-button--secondary">
						<?php esc_html_e( 'View All Campaigns', 'smart-cycle-discounts' ); ?>
					</a>
				<?php elseif ( ! $is_trash && $this->capability_manager->current_user_can( 'wsscd_create_campaigns' ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsscd-campaigns&action=wizard&intent=new' ) ); ?>" class="wsscd-button wsscd-button--primary wsscd-button--large">
						<?php WSSCD_Icon_Helper::render( 'plus-alt', array( 'size' => 20 ) ); ?>
						<?php esc_html_e( 'Create Your First Campaign', 'smart-cycle-discounts' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get views for the list table.
	 *
	 * @since    1.0.0
	 * @return   array    Views array.
	 */
	protected function get_views() {
		$views = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL param for view highlighting.
		$current_status = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';

		$status_counts = $this->campaign_manager->get_status_counts();

		$repository  = $this->campaign_manager->get_repository();
		$trash_count = $repository->count_trashed();

		// All campaigns (excluding trash)
		$class        = ( empty( $current_status ) || 'trash' !== $current_status ) ? 'current' : '';
		$views['all'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url( admin_url( 'admin.php?page=wsscd-campaigns' ) ),
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
					esc_url( admin_url( 'admin.php?page=wsscd-campaigns&status=' . $status ) ),
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
				esc_url( admin_url( 'admin.php?page=wsscd-campaigns&status=trash' ) ),
				$class,
				__( 'Trash', 'smart-cycle-discounts' ),
				$trash_count
			);
		}

		return $views;
	}

}

