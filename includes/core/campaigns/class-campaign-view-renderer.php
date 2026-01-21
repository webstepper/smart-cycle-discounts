<?php
/**
 * Campaign View Renderer Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/core/campaigns/class-campaign-view-renderer.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2025 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Campaign View Renderer Class
 *
 * @since      1.0.0
 */
class WSSCD_Campaign_View_Renderer {

	/**
	 * Template loader instance.
	 *
	 * @since    1.0.0
	 * @var      WSSCD_Template_Loader
	 */
	private WSSCD_Template_Loader $template_loader;

	/**
	 * Initialize the renderer.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Template_Loader $template_loader    Template loader.
	 */
	public function __construct( WSSCD_Template_Loader $template_loader ) {
		$this->template_loader = $template_loader;
	}

	/**
	 * Render edit form.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign|null $campaign     Campaign object or null for new.
	 * @param    array             $form_data    Form data to populate.
	 * @return   void
	 */
	public function render_edit_form( ?WSSCD_Campaign $campaign, array $form_data = array() ): void {
		$is_new      = ! $campaign;
		$campaign_id = $campaign ? $campaign->get_id() : 0;

		?>
		<div class="wrap">
			<h1>
				<?php
				echo $is_new
					? esc_html__( 'Add New Campaign', 'smart-cycle-discounts' )
					: esc_html__( 'Edit Campaign', 'smart-cycle-discounts' );
				?>
			</h1>
			
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wsscd-campaign-form">
				<?php wp_nonce_field( 'wsscd_save_campaign', 'wsscd_campaign_nonce' ); ?>
				<input type="hidden" name="action" value="wsscd_save_campaign">
				<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $campaign_id ); ?>">
				
				<div id="poststuff">
					<div id="post-body" class="metabox-holder columns-2">
						<div id="post-body-content">
							<?php $this->render_main_fields( $campaign, $form_data ); ?>
						</div>
						
						<div id="postbox-container-1" class="postbox-container">
							<?php $this->render_sidebar( $campaign, $form_data ); ?>
						</div>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render campaign stats view.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $campaign    Campaign object.
	 * @param    array        $stats       Campaign statistics.
	 * @return   void
	 */
	public function render_stats_view( WSSCD_Campaign $campaign, array $stats ): void {
		?>
		<div class="wrap">
			<h1>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: campaign name */
						__( 'Campaign Statistics: %s', 'smart-cycle-discounts' ),
						$campaign->get_name()
					)
				);
				?>
			</h1>
			
			<div class="wsscd-stats-grid">
				<?php foreach ( $stats as $key => $value ) : ?>
					<div class="wsscd-stat-box">
						<h3><?php echo esc_html( $this->get_stat_label( $key ) ); ?></h3>
						<p class="wsscd-stat-value"><?php echo esc_html( $this->format_stat_value( $key, $value ) ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render main form fields.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign|null $campaign     Campaign object.
	 * @param    array             $form_data    Form data.
	 * @return   void
	 */
	private function render_main_fields( ?WSSCD_Campaign $campaign, array $form_data ): void {
		$name        = $form_data['name'] ?? ( $campaign ? $campaign->get_name() : '' );
		$description = $form_data['description'] ?? ( $campaign ? $campaign->get_description() : '' );

		?>
		<div class="wsscd-form-section">
			<h2><?php esc_html_e( 'Campaign Details', 'smart-cycle-discounts' ); ?></h2>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="campaign_name"><?php esc_html_e( 'Campaign Name', 'smart-cycle-discounts' ); ?></label>
					</th>
					<td>
						<input type="text" 
								id="campaign_name" 
								name="campaign_name" 
								value="<?php echo esc_attr( $name ); ?>" 
								class="regular-text" 
								required>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="campaign_description"><?php esc_html_e( 'Description', 'smart-cycle-discounts' ); ?></label>
					</th>
					<td>
						<textarea id="campaign_description" 
									name="description" 
									rows="5" 
									cols="50"><?php echo esc_textarea( $description ); ?></textarea>
					</td>
				</tr>
			</table>
		</div>
		
		<?php
		$this->template_loader->get_template(
			'admin/campaign-edit-products.php',
			array(
				'campaign'  => $campaign,
				'form_data' => $form_data,
			)
		);

		$this->template_loader->get_template(
			'admin/campaign-edit-discounts.php',
			array(
				'campaign'  => $campaign,
				'form_data' => $form_data,
			)
		);

		$this->template_loader->get_template(
			'admin/campaign-edit-schedule.php',
			array(
				'campaign'  => $campaign,
				'form_data' => $form_data,
			)
		);
	}

	/**
	 * Render sidebar.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign|null $campaign     Campaign object.
	 * @param    array             $form_data    Form data.
	 * @return   void
	 */
	private function render_sidebar( ?WSSCD_Campaign $campaign, array $form_data ): void {
		?>
		<div class="postbox">
			<h2 class="hndle"><?php esc_html_e( 'Publish', 'smart-cycle-discounts' ); ?></h2>
			<div class="inside">
				<div class="submitbox">
					<?php $this->render_status_field( $campaign, $form_data ); ?>
					
					<div id="major-publishing-actions">
						<?php if ( $campaign && $campaign->get_id() ) : ?>
							<div id="delete-action">
								<a class="submitdelete deletion"
									href="
									<?php
									echo esc_url(
										wp_nonce_url(
											admin_url( 'admin.php?page=wsscd-campaigns&action=delete&id=' . $campaign->get_id() ),
											'wsscd_delete_campaign_' . $campaign->get_id()
										)
									);
									?>
											"
									onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this campaign?', 'smart-cycle-discounts' ); ?>');">
									<?php esc_html_e( 'Delete', 'smart-cycle-discounts' ); ?>
								</a>
							</div>
						<?php endif; ?>
						
						<div id="publishing-action">
							<input type="submit" 
									class="button button-primary button-large" 
									value="<?php echo $campaign ? esc_attr__( 'Update Campaign', 'smart-cycle-discounts' ) : esc_attr__( 'Create Campaign', 'smart-cycle-discounts' ); ?>">
						</div>
						
						<div class="clear"></div>
					</div>
				</div>
			</div>
		</div>
		
		<?php
		// Show recurring info if campaign exists
		if ( $campaign && $campaign->get_id() ) {
			$this->render_recurring_info( $campaign );
		}
		?>
		<?php
	}

	/**
	 * Render status field.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign|null $campaign     Campaign object.
	 * @param    array             $form_data    Form data.
	 * @return   void
	 */
	private function render_status_field( ?WSSCD_Campaign $campaign, array $form_data ): void {
		$current_status = $form_data['status'] ?? ( $campaign ? $campaign->get_status() : 'draft' );
		$statuses       = array(
			'draft'     => __( 'Draft', 'smart-cycle-discounts' ),
			'active'    => __( 'Active', 'smart-cycle-discounts' ),
			'scheduled' => __( 'Scheduled', 'smart-cycle-discounts' ),
			'paused'    => __( 'Paused', 'smart-cycle-discounts' ),
			'expired'   => __( 'Expired', 'smart-cycle-discounts' ),
		);

		?>
		<div class="misc-pub-section">
			<label for="status"><?php esc_html_e( 'Status:', 'smart-cycle-discounts' ); ?></label>
			<select id="status" name="status">
				<?php foreach ( $statuses as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_status, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	/**
	 * Get stat label.
	 *
	 * @since    1.0.0
	 * @param    string $key    Stat key.
	 * @return   string            Formatted label.
	 */
	private function get_stat_label( string $key ): string {
		$labels = array(
			'total_revenue'   => __( 'Total Revenue', 'smart-cycle-discounts' ),
			'total_orders'    => __( 'Total Orders', 'smart-cycle-discounts' ),
			'conversion_rate' => __( 'Conversion Rate', 'smart-cycle-discounts' ),
			'avg_order_value' => __( 'Average Order Value', 'smart-cycle-discounts' ),
		);

		return $labels[ $key ] ?? ucwords( str_replace( '_', ' ', $key ) );
	}

	/**
	 * Format stat value.
	 *
	 * @since    1.0.0
	 * @param    string $key      Stat key.
	 * @param    mixed  $value    Stat value.
	 * @return   string              Formatted value.
	 */
	private function format_stat_value( string $key, $value ): string {
		switch ( $key ) {
			case 'total_revenue':
			case 'avg_order_value':
				return wc_price( $value );
			case 'conversion_rate':
				return sprintf( '%.2f%%', $value );
			default:
				return (string) $value;
		}
	}

	/**
	 * Render recurring information.
	 *
	 * @since    1.0.0
	 * @param    WSSCD_Campaign $campaign    Campaign object.
	 * @return   void
	 */
	private function render_recurring_info( WSSCD_Campaign $campaign ): void {
		$container = null;
		if ( class_exists( 'SmartCycleDiscounts' ) ) {
			$plugin    = SmartCycleDiscounts::get_instance();
			$container = $plugin->get_container();
		}

		if ( ! $container || ! $container->has( 'recurring_handler' ) ) {
			return;
		}

		$recurring_handler  = $container->get( 'recurring_handler' );
		$recurring_settings = $recurring_handler->get_recurring_settings( $campaign->get_id() );

		if ( ! $recurring_settings ) {
			return;
		}

		?>
		<div class="postbox">
			<h2 class="hndle"><?php esc_html_e( 'Recurring Information', 'smart-cycle-discounts' ); ?></h2>
			<div class="inside">
				<?php if ( empty( $recurring_settings['parent_campaign_id'] ) ) : ?>
					<!-- Parent campaign with recurring -->
					<p>
						<strong><?php esc_html_e( 'Status:', 'smart-cycle-discounts' ); ?></strong>
						<?php if ( ! empty( $recurring_settings['is_active'] ) ) : ?>
							<span class="wsscd-text-success"><?php esc_html_e( 'Active', 'smart-cycle-discounts' ); ?></span>
						<?php else : ?>
							<span class="wsscd-text-error"><?php esc_html_e( 'Stopped', 'smart-cycle-discounts' ); ?></span>
						<?php endif; ?>
					</p>
					
					<p>
						<strong><?php esc_html_e( 'Pattern:', 'smart-cycle-discounts' ); ?></strong>
						<?php echo esc_html( ucfirst( $recurring_settings['recurrence_pattern'] ) ); ?>
						<?php
						if ( $recurring_settings['recurrence_interval'] > 1 ) {
							echo esc_html( sprintf( ' (every %d)', $recurring_settings['recurrence_interval'] ) );
						}
						?>
					</p>
					
					<?php if ( 'weekly' === $recurring_settings['recurrence_pattern'] && ! empty( $recurring_settings['recurrence_days'] ) ) : ?>
						<p>
							<strong><?php esc_html_e( 'Days:', 'smart-cycle-discounts' ); ?></strong>
							<?php
							$days          = is_array( $recurring_settings['recurrence_days'] )
								? $recurring_settings['recurrence_days']
								: json_decode( $recurring_settings['recurrence_days'], true );
							$day_names     = array(
								'mon' => __( 'Monday', 'smart-cycle-discounts' ),
								'tue' => __( 'Tuesday', 'smart-cycle-discounts' ),
								'wed' => __( 'Wednesday', 'smart-cycle-discounts' ),
								'thu' => __( 'Thursday', 'smart-cycle-discounts' ),
								'fri' => __( 'Friday', 'smart-cycle-discounts' ),
								'sat' => __( 'Saturday', 'smart-cycle-discounts' ),
								'sun' => __( 'Sunday', 'smart-cycle-discounts' ),
							);
							$selected_days = array();
							foreach ( $days as $day ) {
								if ( isset( $day_names[ $day ] ) ) {
									$selected_days[] = $day_names[ $day ];
								}
							}
							echo esc_html( implode( ', ', $selected_days ) );
							?>
						</p>
					<?php endif; ?>
					
					<p>
						<strong><?php esc_html_e( 'Occurrences:', 'smart-cycle-discounts' ); ?></strong>
						<?php echo esc_html( $recurring_settings['occurrence_number'] ); ?>
					</p>
					
					<?php if ( ! empty( $recurring_settings['next_occurrence_date'] ) ) : ?>
						<p>
							<strong><?php esc_html_e( 'Next occurrence:', 'smart-cycle-discounts' ); ?></strong>
							<?php
							$next_date = new DateTime( $recurring_settings['next_occurrence_date'] );
							echo esc_html( $next_date->format( 'M j, Y g:i A' ) );
							?>
						</p>
					<?php endif; ?>
					
					<?php if ( 'after' === $recurring_settings['recurrence_end_type'] ) : ?>
						<p>
							<strong><?php esc_html_e( 'Ends after:', 'smart-cycle-discounts' ); ?></strong>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of recurring occurrences */
									__( '%d occurrences', 'smart-cycle-discounts' ),
									$recurring_settings['recurrence_count']
								)
							);
							?>
						</p>
					<?php elseif ( 'on' === $recurring_settings['recurrence_end_type'] && ! empty( $recurring_settings['recurrence_end_date'] ) ) : ?>
						<p>
							<strong><?php esc_html_e( 'Ends on:', 'smart-cycle-discounts' ); ?></strong>
							<?php
							$end_date = new DateTime( $recurring_settings['recurrence_end_date'] );
							echo esc_html( $end_date->format( 'M j, Y' ) );
							?>
						</p>
					<?php endif; ?>
					
					<?php if ( ! empty( $recurring_settings['is_active'] ) ) : ?>
						<p>
							<a href="
							<?php
							echo esc_url(
								wp_nonce_url(
									admin_url( 'admin.php?page=wsscd-campaigns&action=stop_recurring&id=' . $campaign->get_id() ),
									'wsscd_stop_recurring'
								)
							);
							?>
										" 
								class="button button-secondary"
								onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to stop this campaign from recurring?', 'smart-cycle-discounts' ); ?>');">
								<?php esc_html_e( 'Stop Recurring', 'smart-cycle-discounts' ); ?>
							</a>
						</p>
					<?php endif; ?>
					
				<?php else : ?>
					<!-- Child campaign created by recurring -->
					<p>
						<strong><?php esc_html_e( 'Type:', 'smart-cycle-discounts' ); ?></strong>
						<?php esc_html_e( 'Recurring Occurrence', 'smart-cycle-discounts' ); ?>
					</p>
					
					<p>
						<strong><?php esc_html_e( 'Parent Campaign:', 'smart-cycle-discounts' ); ?></strong>
						<?php
						$parent_id = $recurring_settings['parent_campaign_id'];
						printf(
							'<a href="%s">%s</a>',
							esc_url( admin_url( 'admin.php?page=wsscd-campaigns&action=edit&id=' . $parent_id ) ),
							/* translators: %d: campaign ID number */
							esc_html( sprintf( __( 'Campaign #%d', 'smart-cycle-discounts' ), $parent_id ) )
						);
						?>
					</p>
					
					<p>
						<strong><?php esc_html_e( 'Occurrence:', 'smart-cycle-discounts' ); ?></strong>
						<?php echo esc_html( '#' . $recurring_settings['occurrence_number'] ); ?>
					</p>
					
					<p class="description">
						<?php esc_html_e( 'This campaign was automatically created by a recurring campaign.', 'smart-cycle-discounts' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}