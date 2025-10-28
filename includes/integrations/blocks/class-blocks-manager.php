<?php
/**
 * Gutenberg Blocks Manager
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/blocks
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Gutenberg Blocks Manager
 *
 * Handles registration and management of Gutenberg blocks for the plugin.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/integrations/blocks
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Blocks_Manager {

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Logger    $logger    Logger instance.
	 */
	private SCD_Logger $logger;

	/**
	 * Asset manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Admin_Asset_Manager    $asset_manager    Asset manager.
	 */
	private SCD_Admin_Asset_Manager $asset_manager;

	/**
	 * Campaign manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      SCD_Campaign_Manager    $campaign_manager    Campaign manager.
	 */
	private SCD_Campaign_Manager $campaign_manager;

	/**
	 * Registered blocks.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $blocks    Registered blocks.
	 */
	private array $blocks = array();

	/**
	 * Block categories.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $categories    Block categories.
	 */
	private array $categories = array(
		'smart-cycle-discounts' => array(
			'slug'  => 'smart-cycle-discounts',
			'title' => 'Smart Cycle Discounts',
			'icon'  => 'tag',
		),
	);

	/**
	 * Initialize the blocks manager.
	 *
	 * @since    1.0.0
	 * @param    SCD_Logger              $logger            Logger instance.
	 * @param    SCD_Admin_Asset_Manager $asset_manager     Asset manager.
	 * @param    SCD_Campaign_Manager    $campaign_manager  Campaign manager.
	 */
	public function __construct(
		SCD_Logger $logger,
		SCD_Admin_Asset_Manager $asset_manager,
		SCD_Campaign_Manager $campaign_manager
	) {
		$this->logger           = $logger;
		$this->asset_manager    = $asset_manager;
		$this->campaign_manager = $campaign_manager;
	}

	/**
	 * Initialize blocks.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function init(): void {
		// Check if Gutenberg is available
		if ( ! function_exists( 'register_block_type' ) ) {
			$this->logger->warning( 'Gutenberg blocks not available - WordPress version too old' );
			return;
		}

		$this->add_hooks();
		$this->register_block_categories();
		$this->register_blocks();

		$this->logger->debug( 'Blocks manager initialized' );
	}

	/**
	 * Add WordPress hooks.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function add_hooks(): void {
		add_action( 'init', array( $this, 'register_block_types' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_assets' ) );
		add_filter( 'block_categories_all', array( $this, 'add_block_categories' ), 10, 2 );
	}

	/**
	 * Register block categories.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function register_block_categories(): void {
		// Categories are registered via the block_categories_all filter
		$this->logger->debug( 'Block categories prepared for registration' );
	}

	/**
	 * Register all blocks.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function register_blocks(): void {
		// Define available blocks
		$this->blocks = array(
			'discount-showcase' => array(
				'name'            => 'scd/discount-showcase',
				'title'           => __( 'Discount Showcase', 'smart-cycle-discounts' ),
				'description'     => __( 'Display active discounts and campaigns', 'smart-cycle-discounts' ),
				'category'        => 'smart-cycle-discounts',
				'icon'            => 'tag',
				'keywords'        => array( 'discount', 'sale', 'campaign', 'smart-cycle' ),
				'supports'        => array(
					'html'    => false,
					'align'   => array( 'wide', 'full' ),
					'color'   => array(
						'background' => true,
						'text'       => true,
						'gradients'  => true,
					),
					'spacing' => array(
						'margin'  => true,
						'padding' => true,
					),
				),
				'attributes'      => array(
					'campaign_id'  => array(
						'type'    => 'number',
						'default' => 0,
					),
					'display_type' => array(
						'type'    => 'string',
						'default' => 'grid',
					),
					'show_timer'   => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'max_products' => array(
						'type'    => 'number',
						'default' => 6,
					),
					'columns'      => array(
						'type'    => 'number',
						'default' => 3,
					),
				),
				'render_callback' => array( $this, 'render_discount_showcase_block' ),
			),

			'campaign-timer'    => array(
				'name'            => 'scd/campaign-timer',
				'title'           => __( 'Campaign Timer', 'smart-cycle-discounts' ),
				'description'     => __( 'Display countdown timer for active campaigns', 'smart-cycle-discounts' ),
				'category'        => 'smart-cycle-discounts',
				'icon'            => 'clock',
				'keywords'        => array( 'timer', 'countdown', 'campaign', 'deadline' ),
				'supports'        => array(
					'html'       => false,
					'align'      => array( 'left', 'center', 'right' ),
					'color'      => array(
						'background' => true,
						'text'       => true,
					),
					'typography' => array(
						'fontSize'   => true,
						'lineHeight' => true,
					),
				),
				'attributes'      => array(
					'campaign_id'       => array(
						'type'    => 'number',
						'default' => 0,
					),
					'timer_style'       => array(
						'type'    => 'string',
						'default' => 'modern',
					),
					'show_labels'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'hide_when_expired' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
				'render_callback' => array( $this, 'render_campaign_timer_block' ),
			),

			'product-discounts' => array(
				'name'            => 'scd/product-discounts',
				'title'           => __( 'Product Discounts', 'smart-cycle-discounts' ),
				'description'     => __( 'Display products with active discounts', 'smart-cycle-discounts' ),
				'category'        => 'smart-cycle-discounts',
				'icon'            => 'products',
				'keywords'        => array( 'products', 'discounts', 'sale', 'woocommerce' ),
				'supports'        => array(
					'html'  => false,
					'align' => array( 'wide', 'full' ),
					'color' => array(
						'background' => true,
						'text'       => true,
					),
				),
				'attributes'      => array(
					'product_ids'  => array(
						'type'    => 'array',
						'default' => array(),
					),
					'category_ids' => array(
						'type'    => 'array',
						'default' => array(),
					),
					'order_by'     => array(
						'type'    => 'string',
						'default' => 'discount_amount',
					),
					'order'        => array(
						'type'    => 'string',
						'default' => 'desc',
					),
					'limit'        => array(
						'type'    => 'number',
						'default' => 8,
					),
				),
				'render_callback' => array( $this, 'render_product_discounts_block' ),
			),
		);

		$this->logger->debug( 'Blocks registered', array( 'count' => count( $this->blocks ) ) );
	}

	/**
	 * Register block types.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function register_block_types(): void {
		foreach ( $this->blocks as $block_slug => $block_config ) {
			$block_path = SCD_INCLUDES_DIR . "integrations/blocks/{$block_slug}/";

			// Check if block.json exists
			if ( file_exists( $block_path . 'block.json' ) ) {
				register_block_type( $block_path );
			} else {
				// Register programmatically
				register_block_type(
					$block_config['name'],
					array(
						'attributes'      => $block_config['attributes'],
						'render_callback' => $block_config['render_callback'],
						'editor_script'   => 'scd-blocks-editor',
						'editor_style'    => 'scd-blocks-editor',
						'style'           => 'scd-blocks',
					)
				);
			}
		}

		$this->logger->debug( 'Block types registered' );
	}

	/**
	 * Enqueue block editor assets.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function enqueue_block_editor_assets(): void {
		$asset_file = SCD_PLUGIN_DIR . 'assets/dist/js/blocks-editor.asset.php';
		$asset_data = file_exists( $asset_file ) ? include $asset_file : array(
			'dependencies' => array(),
			'version'      => '1.0.0',
		);

		// Check if block assets exist before enqueueing
		$block_js  = SCD_PLUGIN_DIR . 'assets/dist/js/blocks-editor.js';
		$block_css = SCD_PLUGIN_DIR . 'assets/dist/css/blocks-editor.css';

		if ( file_exists( $block_js ) ) {
			// Enqueue editor script
			wp_enqueue_script(
				'scd-blocks-editor',
				SCD_PLUGIN_URL . 'assets/dist/js/blocks-editor.js',
				array_merge( $asset_data['dependencies'], array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components' ) ),
				$asset_data['version'],
				true
			);
		}

		if ( file_exists( $block_css ) ) {
			// Enqueue editor styles
			wp_enqueue_style(
				'scd-blocks-editor',
				SCD_PLUGIN_URL . 'assets/dist/css/blocks-editor.css',
				array( 'wp-edit-blocks' ),
				$asset_data['version']
			);
		}

		// Localize script with data
		wp_localize_script(
			'scd-blocks-editor',
			'scdBlocks',
			array(
				'api_url'    => rest_url( 'scd/v1/' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'campaigns'  => $this->get_campaigns_for_editor(),
				'categories' => $this->get_product_categories(),
				'strings'    => array(
					'select_campaign'     => __( 'Select Campaign', 'smart-cycle-discounts' ),
					'select_products'     => __( 'Select Products', 'smart-cycle-discounts' ),
					'select_categories'   => __( 'Select Categories', 'smart-cycle-discounts' ),
					'no_active_campaigns' => __( 'No active campaigns found', 'smart-cycle-discounts' ),
					'loading'             => __( 'Loading...', 'smart-cycle-discounts' ),
				),
			)
		);

		$this->logger->debug( 'Block editor assets enqueued' );
	}

	/**
	 * Enqueue block assets.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function enqueue_block_assets(): void {
		// Check if block assets exist before enqueueing
		$block_css = SCD_PLUGIN_DIR . 'assets/dist/css/blocks.css';
		$block_js  = SCD_PLUGIN_DIR . 'assets/dist/js/blocks.js';

		if ( file_exists( $block_css ) ) {
			// Enqueue frontend styles
			wp_enqueue_style(
				'scd-blocks',
				SCD_PLUGIN_URL . 'assets/dist/css/blocks.css',
				array(),
				'1.0.0'
			);
		}

		if ( file_exists( $block_js ) ) {
			// Enqueue frontend script
			wp_enqueue_script(
				'scd-blocks',
				SCD_PLUGIN_URL . 'assets/dist/js/blocks.js',
				array( 'jquery' ),
				'1.0.0',
				true
			);
		}

		$this->logger->debug( 'Block assets enqueued' );
	}

	/**
	 * Add block categories.
	 *
	 * @since    1.0.0
	 * @param    array  $categories    Existing categories.
	 * @param    object $post          Post object.
	 * @return   array                   Modified categories.
	 */
	public function add_block_categories( array $categories, $post ): array {
		foreach ( $this->categories as $category ) {
			$categories[] = array(
				'slug'  => $category['slug'],
				'title' => $category['title'],
				'icon'  => $category['icon'],
			);
		}

		return $categories;
	}

	/**
	 * Render discount showcase block.
	 *
	 * @since    1.0.0
	 * @param    array $attributes    Block attributes.
	 * @return   string                  Block HTML.
	 */
	public function render_discount_showcase_block( array $attributes ): string {
		$defaults = array(
			'campaign_id'  => 0,
			'display_type' => 'grid',
			'show_timer'   => true,
			'max_products' => 6,
			'columns'      => 3,
		);

		$attributes = wp_parse_args( $attributes, $defaults );

		// Get campaign data
		$campaign = null;
		if ( $attributes['campaign_id'] > 0 ) {
			$campaign = $this->campaign_manager->find( $attributes['campaign_id'] );
		}

		if ( ! $campaign ) {
			return '<div class="scd-block-error">' .
					esc_html__( 'No campaign selected or campaign not found.', 'smart-cycle-discounts' ) .
					'</div>';
		}

		// Get discounted products
		$products = $this->get_campaign_products( $campaign, $attributes['max_products'] );

		if ( empty( $products ) ) {
			return '<div class="scd-block-empty">' .
					esc_html__( 'No products found for this campaign.', 'smart-cycle-discounts' ) .
					'</div>';
		}

		ob_start();
		?>
		<div class="scd-discount-showcase scd-display-<?php echo esc_attr( $attributes['display_type'] ); ?> scd-columns-<?php echo esc_attr( $attributes['columns'] ); ?>">
			<?php if ( $attributes['show_timer'] && $campaign->has_end_date() ) : ?>
				<div class="scd-campaign-timer">
					<?php echo $this->render_campaign_timer( $campaign ); ?>
				</div>
			<?php endif; ?>
			
			<div class="scd-products-grid">
				<?php foreach ( $products as $product ) : ?>
					<div class="scd-product-item">
						<?php echo $this->render_product_item( $product, $campaign ); ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render campaign timer block.
	 *
	 * @since    1.0.0
	 * @param    array $attributes    Block attributes.
	 * @return   string                  Block HTML.
	 */
	public function render_campaign_timer_block( array $attributes ): string {
		$defaults = array(
			'campaign_id'       => 0,
			'timer_style'       => 'modern',
			'show_labels'       => true,
			'hide_when_expired' => true,
		);

		$attributes = wp_parse_args( $attributes, $defaults );

		// Get campaign data
		$campaign = null;
		if ( $attributes['campaign_id'] > 0 ) {
			$campaign = $this->campaign_manager->find( $attributes['campaign_id'] );
		}

		if ( ! $campaign || ! $campaign->has_end_date() ) {
			if ( $attributes['hide_when_expired'] ) {
				return '';
			}
			return '<div class="scd-block-error">' .
					esc_html__( 'No campaign selected or campaign has no end date.', 'smart-cycle-discounts' ) .
					'</div>';
		}

		return $this->render_campaign_timer( $campaign, $attributes );
	}

	/**
	 * Render product discounts block.
	 *
	 * @since    1.0.0
	 * @param    array $attributes    Block attributes.
	 * @return   string                  Block HTML.
	 */
	public function render_product_discounts_block( array $attributes ): string {
		$defaults = array(
			'product_ids'  => array(),
			'category_ids' => array(),
			'order_by'     => 'discount_amount',
			'order'        => 'desc',
			'limit'        => 8,
		);

		$attributes = wp_parse_args( $attributes, $defaults );

		// Get products with discounts
		$products = $this->get_discounted_products( $attributes );

		if ( empty( $products ) ) {
			return '<div class="scd-block-empty">' .
					esc_html__( 'No products with active discounts found.', 'smart-cycle-discounts' ) .
					'</div>';
		}

		ob_start();
		?>
		<div class="scd-product-discounts">
			<div class="scd-products-grid">
				<?php foreach ( $products as $product ) : ?>
					<div class="scd-product-item">
						<?php echo $this->render_product_item( $product ); ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get campaigns for editor.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Campaigns data.
	 */
	private function get_campaigns_for_editor(): array {
		try {
			$campaigns        = $this->campaign_manager->get_active_campaigns();
			$campaign_options = array();

			foreach ( $campaigns as $campaign ) {
				$campaign_options[] = array(
					'value' => $campaign->get_id(),
					'label' => $campaign->get_name(),
				);
			}

			return $campaign_options;
		} catch ( Exception $e ) {
			$this->logger->error( 'Failed to get campaigns for editor', array( 'error' => $e->getMessage() ) );
			return array();
		}
	}

	/**
	 * Get product categories.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Product categories.
	 */
	private function get_product_categories(): array {
		if ( ! function_exists( 'get_terms' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$categories = array();
		foreach ( $terms as $term ) {
			$categories[] = array(
				'value' => $term->term_id,
				'label' => $term->name,
			);
		}

		return $categories;
	}

	/**
	 * Get campaign products.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    object $campaign      Campaign object.
	 * @param    int    $max_products  Maximum products to return.
	 * @return   array                    Products array.
	 */
	private function get_campaign_products( $campaign, int $max_products ): array {
		// This would integrate with the campaign manager to get actual products
		// For now, return empty array
		return array();
	}

	/**
	 * Get discounted products.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $attributes    Block attributes.
	 * @return   array                   Products array.
	 */
	private function get_discounted_products( array $attributes ): array {
		// This would integrate with the discount engine to get products with active discounts
		// For now, return empty array
		return array();
	}

	/**
	 * Render campaign timer.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    object $campaign     Campaign object.
	 * @param    array  $attributes   Timer attributes.
	 * @return   string                  Timer HTML.
	 */
	private function render_campaign_timer( $campaign, array $attributes = array() ): string {
		$defaults = array(
			'timer_style' => 'modern',
			'show_labels' => true,
		);

		$attributes = wp_parse_args( $attributes, $defaults );

		ob_start();
		?>
		<div class="scd-countdown-timer scd-style-<?php echo esc_attr( $attributes['timer_style'] ); ?>" 
			data-end-time="<?php echo esc_attr( $campaign->get_ends_at() ? $campaign->get_ends_at()->format( 'Y-m-d H:i:s' ) : '' ); ?>">
			<div class="scd-timer-units">
				<div class="scd-timer-unit">
					<span class="scd-timer-value" data-unit="days">0</span>
					<?php if ( $attributes['show_labels'] ) : ?>
						<span class="scd-timer-label"><?php esc_html_e( 'Days', 'smart-cycle-discounts' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="scd-timer-unit">
					<span class="scd-timer-value" data-unit="hours">0</span>
					<?php if ( $attributes['show_labels'] ) : ?>
						<span class="scd-timer-label"><?php esc_html_e( 'Hours', 'smart-cycle-discounts' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="scd-timer-unit">
					<span class="scd-timer-value" data-unit="minutes">0</span>
					<?php if ( $attributes['show_labels'] ) : ?>
						<span class="scd-timer-label"><?php esc_html_e( 'Minutes', 'smart-cycle-discounts' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="scd-timer-unit">
					<span class="scd-timer-value" data-unit="seconds">0</span>
					<?php if ( $attributes['show_labels'] ) : ?>
						<span class="scd-timer-label"><?php esc_html_e( 'Seconds', 'smart-cycle-discounts' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render product item.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    object $product     Product object.
	 * @param    object $campaign    Campaign object (optional).
	 * @return   string                 Product HTML.
	 */
	private function render_product_item( $product, $campaign = null ): string {
		// This would render a product item with discount information
		// For now, return placeholder
		return '<div class="scd-product-placeholder">Product Item</div>';
	}

	/**
	 * Get registered blocks.
	 *
	 * @since    1.0.0
	 * @return   array    Registered blocks.
	 */
	public function get_blocks(): array {
		return $this->blocks;
	}

	/**
	 * Check if blocks are supported.
	 *
	 * @since    1.0.0
	 * @return   bool    True if blocks are supported.
	 */
	public function is_blocks_supported(): bool {
		return function_exists( 'register_block_type' );
	}

	/**
	 * Get block categories.
	 *
	 * @since    1.0.0
	 * @return   array    Block categories.
	 */
	public function get_categories(): array {
		return $this->categories;
	}
}

