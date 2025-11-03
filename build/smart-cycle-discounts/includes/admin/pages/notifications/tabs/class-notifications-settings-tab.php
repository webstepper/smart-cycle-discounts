<?php
/**
 * Notifications Settings Tab Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/notifications/tabs/class-notifications-settings-tab.php
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
 * Notifications Settings Tab Class
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/pages/notifications/tabs
 */
class SCD_Notifications_Settings_Tab extends SCD_Notifications_Tab_Base {

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

		// Email Provider Section
		$this->add_section(
			'scd_notifications_provider',
			'<span class="dashicons dashicons-email"></span> ' . __( 'Email Provider', 'smart-cycle-discounts' ),
			'render_provider_section'
		);

		$this->add_field(
			'email_provider',
			__( 'Provider', 'smart-cycle-discounts' ),
			'render_provider_field',
			'scd_notifications_provider',
			array(
				'tooltip' => __( 'Choose your email service provider. WordPress Mail works out of the box. For better deliverability, use SendGrid or Amazon SES.', 'smart-cycle-discounts' ),
				'options' => array(
					'wpmail'    => __( 'WordPress Mail (Default)', 'smart-cycle-discounts' ),
					'sendgrid'  => __( 'SendGrid', 'smart-cycle-discounts' ),
					'amazonses' => __( 'Amazon SES', 'smart-cycle-discounts' ),
				),
			)
		);

		// SendGrid Settings
		$this->add_field(
			'sendgrid_api_key',
			__( 'SendGrid API Key', 'smart-cycle-discounts' ),
			'render_sendgrid_api_key_field',
			'scd_notifications_provider',
			array(
				'tooltip' => __( 'Get your API key from SendGrid dashboard. Free tier: 100 emails/day.', 'smart-cycle-discounts' ),
				'class'   => 'regular-text scd-provider-setting scd-provider-sendgrid',
			)
		);

		// Amazon SES Settings
		$this->add_field(
			'amazonses_access_key',
			__( 'AWS Access Key ID', 'smart-cycle-discounts' ),
			'render_amazonses_access_key_field',
			'scd_notifications_provider',
			array(
				'tooltip' => __( 'Your AWS Access Key ID with SES permissions.', 'smart-cycle-discounts' ),
				'class'   => 'regular-text scd-provider-setting scd-provider-amazonses',
			)
		);

		$this->add_field(
			'amazonses_secret_key',
			__( 'AWS Secret Access Key', 'smart-cycle-discounts' ),
			'render_amazonses_secret_key_field',
			'scd_notifications_provider',
			array(
				'tooltip' => __( 'Your AWS Secret Access Key.', 'smart-cycle-discounts' ),
				'class'   => 'regular-text scd-provider-setting scd-provider-amazonses',
			)
		);

		$this->add_field(
			'amazonses_region',
			__( 'AWS Region', 'smart-cycle-discounts' ),
			'render_amazonses_region_field',
			'scd_notifications_provider',
			array(
				'tooltip' => __( 'AWS region where SES is configured.', 'smart-cycle-discounts' ),
				'options' => array(
					'us-east-1'      => __( 'US East (N. Virginia)', 'smart-cycle-discounts' ),
					'us-west-2'      => __( 'US West (Oregon)', 'smart-cycle-discounts' ),
					'eu-west-1'      => __( 'EU (Ireland)', 'smart-cycle-discounts' ),
					'eu-central-1'   => __( 'EU (Frankfurt)', 'smart-cycle-discounts' ),
					'ap-southeast-1' => __( 'Asia Pacific (Singapore)', 'smart-cycle-discounts' ),
				),
				'class'   => 'scd-provider-setting scd-provider-amazonses',
			)
		);

		// Email Configuration Section
		$this->add_section(
			'scd_notifications_config',
			'<span class="dashicons dashicons-admin-settings"></span> ' . __( 'Email Configuration', 'smart-cycle-discounts' ),
			'render_config_section'
		);

		$this->add_field(
			'from_email',
			__( 'From Email Address', 'smart-cycle-discounts' ),
			'render_from_email_field',
			'scd_notifications_config',
			array(
				'tooltip' => __( 'Email address that notifications will be sent from.', 'smart-cycle-discounts' ),
			)
		);

		$this->add_field(
			'from_name',
			__( 'From Name', 'smart-cycle-discounts' ),
			'render_from_name_field',
			'scd_notifications_config',
			array(
				'tooltip' => __( 'Sender name shown in email client.', 'smart-cycle-discounts' ),
			)
		);

		$this->add_field(
			'additional_recipients',
			__( 'Additional Recipients', 'smart-cycle-discounts' ),
			'render_additional_recipients_field',
			'scd_notifications_config',
			array(
				'tooltip'     => __( 'Add extra email addresses to receive notifications (comma-separated). Admin email is always included.', 'smart-cycle-discounts' ),
				'placeholder' => 'manager@example.com, team@example.com',
			)
		);

		$this->add_field(
			'test_email',
			__( 'Test Email', 'smart-cycle-discounts' ),
			'render_test_email_field',
			'scd_notifications_config',
			array(
				'tooltip' => __( 'Send a test email to verify your configuration.', 'smart-cycle-discounts' ),
			)
		);

		// FREE Notifications Section
		$this->add_section(
			'scd_notifications_free',
			'<span class="dashicons dashicons-bell"></span> ' . __( 'FREE Notifications', 'smart-cycle-discounts' ),
			'render_free_section'
		);

		$this->add_field(
			'notify_campaign_started',
			__( 'Campaign Started', 'smart-cycle-discounts' ),
			'render_notify_campaign_started_field',
			'scd_notifications_free',
			array(
				'tooltip' => __( 'Receive notification when a campaign starts.', 'smart-cycle-discounts' ),
			)
		);

		$this->add_field(
			'notify_campaign_ended',
			__( 'Campaign Ended', 'smart-cycle-discounts' ),
			'render_notify_campaign_ended_field',
			'scd_notifications_free',
			array(
				'tooltip' => __( 'Receive notification with performance summary when campaign ends.', 'smart-cycle-discounts' ),
			)
		);

		$this->add_field(
			'notify_errors',
			__( 'Error Notifications', 'smart-cycle-discounts' ),
			'render_notify_errors_field',
			'scd_notifications_free',
			array(
				'tooltip' => __( 'Receive notifications when email delivery fails or other errors occur.', 'smart-cycle-discounts' ),
			)
		);

		// PRO Notifications Section
		$this->add_section(
			'scd_notifications_pro',
			'<span class="dashicons dashicons-star-filled"></span> ' . __( 'PRO Notifications', 'smart-cycle-discounts' ),
			'render_pro_section'
		);

		$this->add_field(
			'notify_campaign_ending',
			__( 'Campaign Ending Soon', 'smart-cycle-discounts' ),
			'render_notify_campaign_ending_field',
			'scd_notifications_pro',
			array(
				'tooltip' => __( 'Receive notification 24 hours before a campaign ends so you can take action.', 'smart-cycle-discounts' ),
			)
		);

		$this->add_field(
			'notify_daily_report',
			__( 'Daily Performance Report', 'smart-cycle-discounts' ),
			'render_notify_daily_report_field',
			'scd_notifications_pro',
			array(
				'tooltip' => __( 'Receive daily summary of all active campaigns with actionable insights.', 'smart-cycle-discounts' ),
			)
		);

		$this->add_field(
			'notify_weekly_report',
			__( 'Weekly Performance Report', 'smart-cycle-discounts' ),
			'render_notify_weekly_report_field',
			'scd_notifications_pro',
			array(
				'tooltip' => __( 'Receive weekly summary with trends and strategic insights.', 'smart-cycle-discounts' ),
			)
		);

		$this->add_field(
			'notify_performance_alert',
			__( 'Performance Alerts', 'smart-cycle-discounts' ),
			'render_notify_performance_alert_field',
			'scd_notifications_pro',
			array(
				'tooltip' => __( 'Get real-time alerts about campaign performance issues or opportunities.', 'smart-cycle-discounts' ),
			)
		);

		$this->add_field(
			'notify_low_stock_alert',
			__( 'Low Stock Alerts', 'smart-cycle-discounts' ),
			'render_notify_low_stock_alert_field',
			'scd_notifications_pro',
			array(
				'tooltip' => __( 'Receive proactive alerts when products in active campaigns are running low on stock.', 'smart-cycle-discounts' ),
			)
		);

		$this->add_field(
			'notify_milestone_alert',
			__( 'Milestone Alerts', 'smart-cycle-discounts' ),
			'render_notify_milestone_alert_field',
			'scd_notifications_pro',
			array(
				'tooltip' => __( 'Celebrate when your campaigns reach important revenue or order milestones.', 'smart-cycle-discounts' ),
			)
		);
	}

	/**
	 * Render tab content.
	 *
	 * @since    1.0.0
	 * @param    string $current_tab    Current active tab.
	 * @return   void
	 */
	public function render_tab_content( string $current_tab ): void {
		if ( $current_tab !== $this->tab_slug ) {
			return;
		}

		// Render all settings sections and fields
		// Note: settings_fields() is already called in the parent page (class-notifications-page.php:299)
		// Only call do_settings_sections() here to avoid duplicate nonce fields
		do_settings_sections( 'scd_notifications_' . $this->tab_slug );
	}

	/**
	 * Render provider section.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_provider_section(): void {
		echo '<p class="scd-section-description">';
		echo esc_html__( 'Select your email delivery service. WordPress Mail works with any SMTP plugin. For professional emails with analytics, use SendGrid or Amazon SES.', 'smart-cycle-discounts' );
		echo '</p>';
	}

	/**
	 * Render provider field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_provider_field( array $args ): void {
		$this->render_select_field( $args );
	}

	/**
	 * Render SendGrid API key field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_sendgrid_api_key_field( array $args ): void {
		$this->render_text_field( $args );
		?>
		<button type="button" class="button button-secondary scd-test-connection-btn" data-provider="sendgrid" style="margin-left: 10px;">
			<span class="dashicons dashicons-yes-alt"></span>
			<?php esc_html_e( 'Test Connection', 'smart-cycle-discounts' ); ?>
		</button>
		<p class="description">
			<?php
			printf(
				/* translators: %s: link to SendGrid dashboard */
				esc_html__( 'Get your API key from %s', 'smart-cycle-discounts' ),
				'<a href="https://app.sendgrid.com/settings/api_keys" target="_blank">' . esc_html__( 'SendGrid Dashboard', 'smart-cycle-discounts' ) . '</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render Amazon SES access key field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_amazonses_access_key_field( array $args ): void {
		$this->render_text_field( $args );
	}

	/**
	 * Render Amazon SES secret key field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_amazonses_secret_key_field( array $args ): void {
		$field_id = $args['field_id'];
		$value    = $this->get_value( $field_id, '' );
		$name     = $this->get_field_name( $field_id );

		printf(
			'<input type="password" id="%s" name="%s" value="%s" class="%s">',
			esc_attr( $field_id ),
			esc_attr( $name ),
			esc_attr( $value ),
			esc_attr( isset( $args['class'] ) ? $args['class'] : 'regular-text' )
		);
	}

	/**
	 * Render Amazon SES region field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_amazonses_region_field( array $args ): void {
		$this->render_select_field( $args );
		?>
		<button type="button" class="button button-secondary scd-test-connection-btn" data-provider="amazonses" style="margin-left: 10px;">
			<span class="dashicons dashicons-yes-alt"></span>
			<?php esc_html_e( 'Test Connection', 'smart-cycle-discounts' ); ?>
		</button>
		<p class="description">
			<?php esc_html_e( 'Make sure your AWS account has SES enabled in this region and your sending limits are configured.', 'smart-cycle-discounts' ); ?>
		</p>
		<?php
	}

	/**
	 * Render config section.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_config_section(): void {
		echo '<p class="scd-section-description">';
		echo esc_html__( 'Configure sender information for notification emails.', 'smart-cycle-discounts' );
		echo '</p>';
	}

	/**
	 * Render from email field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_from_email_field( array $args ): void {
		$this->render_text_field( $args );
	}

	/**
	 * Render from name field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_from_name_field( array $args ): void {
		$this->render_text_field( $args );
	}

	/**
	 * Render additional recipients field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_additional_recipients_field( array $args ): void {
		$this->render_text_field( $args );
		echo '<p class="description">';
		echo esc_html__( 'Admin email:', 'smart-cycle-discounts' );
		echo ' <code>' . esc_html( get_option( 'admin_email' ) ) . '</code> ';
		echo esc_html__( '(always included)', 'smart-cycle-discounts' );
		echo '</p>';
	}

	/**
	 * Render test email field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_test_email_field( array $args ): void {
		// Render tooltip using centralized system
		if ( isset( $args['tooltip'] ) && ! empty( $args['tooltip'] ) ) {
			echo ' ';
			$this->render_tooltip( $args['tooltip'] );
		}

		?>
		<button type="button" id="scd-test-email" class="button button-secondary">
			<span class="dashicons dashicons-email-alt"></span>
			<?php esc_html_e( 'Send Test Email', 'smart-cycle-discounts' ); ?>
		</button>
		<p class="description">
			<?php
			printf(
				/* translators: %s: admin email address */
				esc_html__( 'Test email will be sent to: %s', 'smart-cycle-discounts' ),
				'<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render FREE notifications section.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_free_section(): void {
		echo '<p class="scd-section-description">';
		echo esc_html__( 'These notifications are available to all users. Get notified when important events happen.', 'smart-cycle-discounts' );
		echo '</p>';
	}

	/**
	 * Render PRO notifications section.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function render_pro_section(): void {
		$feature_gate = $this->page->get_feature_gate();
		$has_pro      = $feature_gate->is_premium();

		echo '<p class="scd-section-description">';
		if ( $has_pro ) {
			echo esc_html__( 'PRO notifications provide proactive insights to help you optimize campaigns before issues occur.', 'smart-cycle-discounts' );
		} else {
			echo esc_html__( 'PRO notifications provide proactive insights and alerts. Upgrade to unlock these powerful features.', 'smart-cycle-discounts' );
		}
		echo '</p>';

		// Show upgrade banner once for free users
		if ( ! $has_pro ) {
			?>
			<div class="scd-upgrade-prompt">
				<p class="scd-upgrade-message">
					<span class="dashicons dashicons-lock" style="color: #f0ad4e;"></span>
					<strong><?php esc_html_e( 'Unlock Proactive Notifications', 'smart-cycle-discounts' ); ?></strong><br>
					<span style="color: #666;">
						<?php esc_html_e( 'Upgrade to PRO to get notified before things happen so you can take action and optimize your campaigns.', 'smart-cycle-discounts' ); ?>
					</span>
				</p>
				<p>
					<a href="<?php echo esc_url( $feature_gate->get_upgrade_url() ); ?>" class="button button-primary">
						<?php esc_html_e( 'Upgrade to PRO', 'smart-cycle-discounts' ); ?>
					</a>
					<a href="<?php echo esc_url( $feature_gate->get_trial_url() ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Start 14-Day Trial', 'smart-cycle-discounts' ); ?>
					</a>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Render campaign started notification field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_notify_campaign_started_field( array $args ): void {
		$args['notification_type'] = 'campaign_started';
		$this->render_toggle_field( $args );
	}

	/**
	 * Render campaign ending notification field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_notify_campaign_ending_field( array $args ): void {
		$args['notification_type'] = 'campaign_ending';
		$this->render_toggle_field( $args );
	}

	/**
	 * Render campaign ended notification field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_notify_campaign_ended_field( array $args ): void {
		$args['notification_type'] = 'campaign_ended';
		$this->render_toggle_field( $args );
	}

	/**
	 * Render daily report notification field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_notify_daily_report_field( array $args ): void {
		$args['notification_type'] = 'daily_report';
		$this->render_toggle_field( $args );
	}

	/**
	 * Render weekly report notification field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_notify_weekly_report_field( array $args ): void {
		$args['notification_type'] = 'weekly_report';
		$this->render_toggle_field( $args );
	}

	/**
	 * Render performance alert notification field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_notify_performance_alert_field( array $args ): void {
		$args['notification_type'] = 'performance_alert';
		$this->render_toggle_field( $args );
	}

	/**
	 * Render low stock alert notification field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_notify_low_stock_alert_field( array $args ): void {
		$args['notification_type'] = 'low_stock_alert';
		$this->render_toggle_field( $args );
	}

	/**
	 * Render milestone alert notification field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_notify_milestone_alert_field( array $args ): void {
		$args['notification_type'] = 'milestone_alert';
		$this->render_toggle_field( $args );
	}

	/**
	 * Render error notification field.
	 *
	 * @since    1.0.0
	 * @param    array $args    Field arguments.
	 * @return   void
	 */
	public function render_notify_errors_field( array $args ): void {
		$args['notification_type'] = 'errors';
		$this->render_toggle_field( $args );
	}

	/**
	 * Sanitize notifications settings.
	 *
	 * @since    1.0.0
	 * @param    array $input    Raw input data.
	 * @return   array              Sanitized data.
	 */
	protected function sanitize_settings( array $input ): array {
		$sanitized = array();

		// Email provider
		$valid_providers             = array( 'wpmail', 'sendgrid', 'amazonses' );
		$sanitized['email_provider'] = in_array( $input['email_provider'] ?? '', $valid_providers, true )
			? $input['email_provider']
			: 'wpmail';

		// SendGrid settings
		$sanitized['sendgrid_api_key'] = isset( $input['sendgrid_api_key'] )
			? sanitize_text_field( $input['sendgrid_api_key'] )
			: '';

		// Amazon SES settings
		$sanitized['amazonses_access_key'] = isset( $input['amazonses_access_key'] )
			? sanitize_text_field( $input['amazonses_access_key'] )
			: '';

		$sanitized['amazonses_secret_key'] = isset( $input['amazonses_secret_key'] )
			? sanitize_text_field( $input['amazonses_secret_key'] )
			: '';

		$valid_regions                 = array( 'us-east-1', 'us-west-2', 'eu-west-1', 'eu-central-1', 'ap-southeast-1' );
		$sanitized['amazonses_region'] = in_array( $input['amazonses_region'] ?? '', $valid_regions, true )
			? $input['amazonses_region']
			: 'us-east-1';

		// From email and name
		$sanitized['from_email'] = isset( $input['from_email'] ) && is_email( $input['from_email'] )
			? sanitize_email( $input['from_email'] )
			: get_option( 'admin_email' );

		$sanitized['from_name'] = isset( $input['from_name'] )
			? sanitize_text_field( $input['from_name'] )
			: get_bloginfo( 'name' );

		// Additional recipients
		$recipients_input = isset( $input['additional_recipients'] ) ? $input['additional_recipients'] : '';
		$recipients_array = array_map( 'trim', explode( ',', $recipients_input ) );
		$valid_recipients = array();

		foreach ( $recipients_array as $email ) {
			if ( is_email( $email ) ) {
				$valid_recipients[] = sanitize_email( $email );
			}
		}

		$sanitized['additional_recipients'] = implode( ', ', $valid_recipients );

		// Notification types
		$sanitized['notify_campaign_started'] = isset( $input['notify_campaign_started'] ) && '1' === $input['notify_campaign_started'];
		$sanitized['notify_campaign_ending']  = isset( $input['notify_campaign_ending'] ) && '1' === $input['notify_campaign_ending'];
		$sanitized['notify_campaign_ended']   = isset( $input['notify_campaign_ended'] ) && '1' === $input['notify_campaign_ended'];
		$sanitized['notify_daily_report']     = isset( $input['notify_daily_report'] ) && '1' === $input['notify_daily_report'];
		$sanitized['notify_weekly_report']    = isset( $input['notify_weekly_report'] ) && '1' === $input['notify_weekly_report'];
		$sanitized['notify_errors']           = isset( $input['notify_errors'] ) && '1' === $input['notify_errors'];

		return $sanitized;
	}
}
