<?php
/**
 * Cycle AI Service Class
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services/class-cycle-ai-service.php
 * @author     Webstepper <contact@webstepper.io>
 * @copyright  2026 Webstepper
 * @license    GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://webstepper.io/wordpress-plugins/smart-cycle-discounts
 * @since      1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Cycle AI Service Class
 *
 * Responsible for communicating with the external AI provider (e.g. OpenAI)
 * to generate campaign suggestions based on high-level store context.
 *
 * IMPORTANT:
 * - This service does NOT store any credentials.
 * - API keys are read from configuration (constants / filters) and are never exposed to JavaScript.
 * - Only non-PII store metadata (products, categories, campaigns) should be passed to AI.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/services
 * @author     Webstepper <contact@webstepper.io>
 */
class WSSCD_Cycle_AI_Service {

	/**
	 * Default model used for Cycle AI.
	 *
	 * This is filterable via the 'wsscd_cycle_ai_model' filter so sites can
	 * switch to newer/better models without code changes.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const DEFAULT_MODEL = 'gpt-5-mini';

	/**
	 * Logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WSSCD_Logger $logger Logger instance.
	 */
	private WSSCD_Logger $logger;

	/**
	 * Initialize the Cycle AI service.
	 *
	 * @since 1.0.0
	 *
	 * @param WSSCD_Logger $logger Logger instance.
	 */
	public function __construct( WSSCD_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Generate campaign suggestions using the external AI provider.
	 *
	 * This uses a single chat-completions style request with a JSON-formatted
	 * response describing suggested campaigns. The output is intentionally
	 * simple so it can be mapped into the wizard.
	 *
	 * @since 1.0.0
	 *
	 * @return array|\WP_Error Array of suggestions on success, \WP_Error on failure.
	 */
	public function get_campaign_suggestions() {
		$api_key = $this->get_api_key();

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'wsscd_cycle_ai_not_configured',
				__( 'Cycle AI is not configured. Please add an API key in the plugin settings.', 'smart-cycle-discounts' ),
				array(
					'status' => 500,
				)
			);
		}

		$model = $this->get_model();

		$context = $this->build_store_context();

		$payload = array(
			'model'    => $model,
			'messages' => array(
				array(
					'role'    => 'system',
					'content' => $this->get_system_prompt(),
				),
				array(
					'role'    => 'user',
					'content' => $context,
				),
			),
		);

		$response = $this->call_api( $api_key, $payload );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$suggestions = $this->extract_suggestions_from_response( $response );

		if ( is_wp_error( $suggestions ) ) {
			return $suggestions;
		}

		return $suggestions;
	}

	/**
	 * Get API key from configuration.
	 *
	 * Priority:
	 * 1. Constant WSSCD_CYCLE_AI_API_KEY
	 * 2. Filter 'wsscd_cycle_ai_api_key'
	 *
	 * @since 1.0.0
	 *
	 * @return string API key or empty string if not configured.
	 */
	private function get_api_key(): string {
		$api_key = '';

		if ( defined( 'WSSCD_CYCLE_AI_API_KEY' ) && WSSCD_CYCLE_AI_API_KEY ) {
			$api_key = WSSCD_CYCLE_AI_API_KEY;
		}

		/**
		 * Filter the Cycle AI API key.
		 *
		 * Allows advanced setups to provide the key from environment variables
		 * or external configuration without hard-coding it into the plugin.
		 *
		 * @since 1.0.0
		 *
		 * @param string $api_key Current API key value.
		 */
		$api_key = apply_filters( 'wsscd_cycle_ai_api_key', $api_key );

		// Never log or expose the API key.
		return is_string( $api_key ) ? trim( $api_key ) : '';
	}

	/**
	 * Get model name for Cycle AI.
	 *
	 * Uses DEFAULT_MODEL by default and allows overrides via filter.
	 *
	 * @since 1.0.0
	 *
	 * @return string Model identifier.
	 */
	private function get_model(): string {
		$model = self::DEFAULT_MODEL;

		/**
		 * Filter the Cycle AI model name.
		 *
		 * @since 1.0.0
		 *
		 * @param string $model Default model identifier.
		 */
		$model = apply_filters( 'wsscd_cycle_ai_model', $model );

		return is_string( $model ) && '' !== trim( $model ) ? trim( $model ) : self::DEFAULT_MODEL;
	}

	/**
	 * Build concise store context for AI.
	 *
	 * Only includes non-PII metadata:
	 * - Store name.
	 * - Main product categories.
	 * - A few example product names.
	 *
	 * @since 1.0.0
	 *
	 * @return string Context string.
	 */
	private function build_store_context(): string {
		$lines       = array();
		$store_name  = get_bloginfo( 'name', 'display' );
		$store_url   = get_home_url();
		$lines[]     = 'Store name: ' . ( $store_name ? $store_name : 'Unknown Store' );
		$lines[]     = 'Store URL: ' . $store_url;
		$lines[]     = '';
		$lines[]     = 'You are helping create WooCommerce discount campaigns.';
		$lines[]     = '';
		$categories  = $this->get_product_categories_summary();
		$products    = $this->get_sample_products_summary();
		$has_context = false;

		if ( ! empty( $categories ) ) {
			$lines[]     = 'Main product categories:';
			foreach ( $categories as $category_name ) {
				$lines[] = '- ' . $category_name;
			}
			$has_context = true;
		}

		if ( ! empty( $products ) ) {
			$lines[] = '';
			$lines[] = 'Example products (names only):';
			foreach ( $products as $product_name ) {
				$lines[] = '- ' . $product_name;
			}
			$has_context = true;
		}

		if ( ! $has_context ) {
			$lines[] = 'No detailed product catalog is available. Suggest generic WooCommerce discount campaigns for a typical store.';
		}

		$lines[] = '';
		$lines[] = 'Please suggest 3-5 campaign ideas as JSON ONLY, no explanation text.';

		return implode( "\n", $lines );
	}

	/**
	 * Get a short list of product category names.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of category names.
	 */
	private function get_product_categories_summary(): array {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'number'     => 8,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$names = array();

		foreach ( $terms as $term ) {
			if ( isset( $term->name ) && '' !== $term->name ) {
				$names[] = $term->name;
			}
		}

		return $names;
	}

	/**
	 * Get a few example product names from the catalog.
	 *
	 * Uses WooCommerce helpers when available; otherwise returns an empty array.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of product names.
	 */
	private function get_sample_products_summary(): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_wc_get_products -- Small, bounded query for AI context.
		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 10,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'objects',
			)
		);

		if ( empty( $products ) ) {
			return array();
		}

		$names = array();

		foreach ( $products as $product ) {
			if ( is_a( $product, 'WC_Product' ) ) {
				$name = $product->get_name();
				if ( $name ) {
					$names[] = $name;
				}
			}
		}

		return $names;
	}

	/**
	 * Get system prompt for Cycle AI.
	 *
	 * Keeps this logic centralized so it can be refined over time without
	 * changing call sites.
	 *
	 * @since 1.0.0
	 *
	 * @return string System prompt content.
	 */
	private function get_system_prompt(): string {
		$prompt = 'You are Cycle AI, an assistant for the Smart Cycle Discounts WooCommerce plugin. ';
		$prompt .= 'Your job is to suggest practical, safe discount campaigns for a WooCommerce store. ';
		$prompt .= 'Use only information given in the prompt about the store. ';
		$prompt .= 'Do not invent store-specific details that are not provided. ';
		$prompt .= 'Respect these rules:' . "\n";
		$prompt .= '- Use only these discount types: percentage, fixed, bogo, tiered.' . "\n";
		$prompt .= '- Reasonable discounts: 10-40% for most products; up to 60% only for clearance or slow movers.' . "\n";
		$prompt .= '- Campaign duration: typically 3-21 days.' . "\n";
		$prompt .= '- Prefer targeting specific categories or product groups over the whole store.' . "\n\n";

		$prompt .= 'Return your answer as pure JSON, with this structure:' . "\n";
		$prompt .= '[{' . "\n";
		$prompt .= '  "name": "Short campaign name",' . "\n";
		$prompt .= '  "description": "1-2 sentence human-friendly description",' . "\n";
		$prompt .= '  "discount_type": "percentage | fixed | bogo | tiered",' . "\n";
		$prompt .= '  "discount_value": 20,' . "\n";
		$prompt .= '  "target": "category: <exact category name from the list>",' . "\n";
		$prompt .= '  "duration_days": 7,' . "\n";
		$prompt .= '  "notes": "Optional short reasoning for the merchant"' . "\n";
		$prompt .= '}...]' . "\n\n";
		$prompt .= 'For "target": use the exact category name from the store\'s Main product categories list (e.g. "category: Hoodies"). Do not use category IDs or numbers.' . "\n\n";

		$prompt .= 'Return only valid JSON (no comments, no markdown, no extra text).';

		return $prompt;
	}

	/**
	 * Call the external AI API.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key API key.
	 * @param array  $payload Request payload.
	 * @return array|\WP_Error Decoded JSON response or WP_Error.
	 */
	private function call_api( string $api_key, array $payload ) {
		$url = 'https://api.openai.com/v1/chat/completions';

		$args = array(
			'timeout'  => 45,
			'sslverify' => true,
			'headers'  => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'     => wp_json_encode( $payload ),
		);

		$args = apply_filters( 'wsscd_cycle_ai_request_args', $args, $payload );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.http_request_wp_remote_post -- Using WordPress HTTP API is required here.
		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->logger->error(
				'Cycle AI HTTP error',
				array(
					'code'    => $response->get_error_code(),
					'message' => $response->get_error_message(),
				)
			);

			$error_data = array( 'status' => 500 );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$error_data['reason'] = $response->get_error_message();
			}

			return new WP_Error(
				'wsscd_cycle_ai_http_error',
				__( 'Cycle AI request failed. Please try again later.', 'smart-cycle-discounts' ),
				$error_data
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$this->logger->error(
				'Cycle AI API error',
				array(
					'status' => $status_code,
				)
			);

			return new WP_Error(
				'wsscd_cycle_ai_api_error',
				__( 'Cycle AI returned an error response.', 'smart-cycle-discounts' ),
				array(
					'status' => $status_code,
				)
			);
		}

		$decoded = json_decode( $body, true );

		if ( null === $decoded || ! is_array( $decoded ) ) {
			$this->logger->error(
				'Cycle AI invalid JSON',
				array(
					'status' => $status_code,
				)
			);

			return new WP_Error(
				'wsscd_cycle_ai_invalid_response',
				__( 'Cycle AI returned an invalid response.', 'smart-cycle-discounts' ),
				array(
					'status' => 500,
				)
			);
		}

		return $decoded;
	}

	/**
	 * Extract suggestions array from chat-completions response.
	 *
	 * @since 1.0.0
	 *
	 * @param array $response Decoded API response.
	 * @return array|\WP_Error Suggestions array or WP_Error.
	 */
	private function extract_suggestions_from_response( array $response ) {
		if ( empty( $response['choices'] ) || ! is_array( $response['choices'] ) ) {
			return new WP_Error(
				'wsscd_cycle_ai_no_choices',
				__( 'Cycle AI did not return any suggestions.', 'smart-cycle-discounts' ),
				array(
					'status' => 500,
				)
			);
		}

		$first_choice = $response['choices'][0];

		if ( ! isset( $first_choice['message']['content'] ) || '' === $first_choice['message']['content'] ) {
			return new WP_Error(
				'wsscd_cycle_ai_empty_content',
				__( 'Cycle AI returned an empty suggestion.', 'smart-cycle-discounts' ),
				array(
					'status' => 500,
				)
			);
		}

		$content = $first_choice['message']['content'];

		// Content should be pure JSON according to system prompt.
		$suggestions = json_decode( $content, true );

		if ( null === $suggestions || ! is_array( $suggestions ) ) {
			$this->logger->warning(
				'Cycle AI returned non-JSON content',
				array(
					'raw_preview' => mb_substr( $content, 0, 200 ),
				)
			);

			return new WP_Error(
				'wsscd_cycle_ai_invalid_json',
				__( 'Cycle AI returned suggestions in an unexpected format.', 'smart-cycle-discounts' ),
				array(
					'status' => 500,
				)
			);
		}

		// Normalize to a list of associative arrays.
		if ( isset( $suggestions['name'] ) ) {
			$suggestions = array( $suggestions );
		}

		if ( empty( $suggestions ) ) {
			return new WP_Error(
				'wsscd_cycle_ai_no_suggestions',
				__( 'Cycle AI did not return any campaign suggestions.', 'smart-cycle-discounts' ),
				array(
					'status' => 500,
				)
			);
		}

		return $suggestions;
	}

	/**
	 * Get product categories with IDs for mapping AI category names to IDs.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of arrays with 'id' and 'name'.
	 */
	public function get_categories_with_ids(): array {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'number'     => 50,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$out = array();
		foreach ( $terms as $term ) {
			if ( isset( $term->term_id, $term->name ) && '' !== $term->name ) {
				$out[] = array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
				);
			}
		}

		return $out;
	}

	/**
	 * Build rich context for full-campaign AI suggestion (store, categories, products, optional existing campaigns).
	 *
	 * @since 1.0.0
	 *
	 * @param array $context_overrides Optional overrides (e.g. 'existing_campaigns' => '...').
	 * @return string Context string for the AI prompt.
	 */
	public function build_full_campaign_context( array $context_overrides = array() ): string {
		$store_name = get_bloginfo( 'name', 'display' );
		$store_url  = get_home_url();
		$currency   = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';

		$lines   = array();
		$lines[] = 'Store name: ' . ( $store_name ? $store_name : 'Unknown Store' );
		$lines[] = 'Store URL: ' . $store_url;
		$lines[] = 'Currency: ' . $currency;
		$lines[] = '';

		$categories = $this->get_categories_with_ids();
		if ( ! empty( $categories ) ) {
			$lines[] = 'Product categories (use these exact names in category_names):';
			foreach ( $categories as $cat ) {
				$lines[] = '- ID ' . $cat['id'] . ': ' . $cat['name'];
			}
			$lines[] = '';
		}

		$products = $this->get_sample_products_summary();
		if ( ! empty( $products ) ) {
			$lines[] = 'Sample product names:';
			foreach ( array_slice( $products, 0, 15 ) as $name ) {
				$lines[] = '- ' . $name;
			}
			$lines[] = '';
		}

		$base = array(
			'existing_campaigns' => '',
			'extra'              => '',
		);
		$overrides = array_merge( $base, $context_overrides );
		$filtered  = apply_filters( 'wsscd_cycle_ai_full_campaign_context', $overrides );

		if ( ! empty( $filtered['existing_campaigns'] ) ) {
			$lines[] = 'Existing campaigns (suggest something different or complementary):';
			$lines[] = $filtered['existing_campaigns'];
			$lines[] = '';
		}

		if ( ! empty( $filtered['extra'] ) ) {
			$lines[] = $filtered['extra'];
			$lines[] = '';
		}

		$lines[] = 'Generate exactly ONE full campaign as JSON (see schema below). Use only category names from the list above.';

		return implode( "\n", $lines );
	}

	/**
	 * Get system prompt for generating one full campaign (all wizard steps).
	 *
	 * @since 1.0.0
	 *
	 * @return string System prompt.
	 */
	private function get_system_prompt_for_full_campaign(): string {
		$p  = 'You are Cycle AI for the Smart Cycle Discounts WooCommerce plugin. ';
		$p .= 'Generate exactly ONE complete campaign that the merchant can review and save. ';
		$p .= 'Use only the store information provided. ';
		$p .= 'Plugin supports: product_selection_type (all_products, random_products, specific_products, smart_selection), ';
		$p .= 'discount_type (percentage, fixed, bogo, tiered), category filter, and schedule (immediate or scheduled). ';
		$p .= 'Return ONLY valid JSON with this exact structure (no markdown, no comments):' . "\n";
		$p .= '{' . "\n";
		$p .= '  "name": "Short campaign name",' . "\n";
		$p .= '  "description": "1-2 sentence description",' . "\n";
		$p .= '  "priority": 3,' . "\n";
		$p .= '  "product_selection_type": "all_products" or "random_products",' . "\n";
		$p .= '  "category_names": ["Exact category name from list"],' . "\n";
		$p .= '  "random_count": 10,' . "\n";
		$p .= '  "discount_type": "percentage" or "fixed" or "bogo" or "tiered",' . "\n";
		$p .= '  "discount_value": 20,' . "\n";
		$p .= '  "duration_days": 7,' . "\n";
		$p .= '  "start_type": "immediate" or "scheduled",' . "\n";
		$p .= '  "start_date": "Y-m-d",' . "\n";
		$p .= '  "end_date": "Y-m-d"' . "\n";
		$p .= '}' . "\n";
		$p .= 'Rules: category_names must be exact names from the provided list. duration_days 3-21. discount_value 5-60 for percentage. start_date/end_date only when start_type is scheduled. ';
		$p .= 'In name and description use only real words and category names from the provided list; never use numbers, category IDs, or placeholders (e.g. 101010).';
		return $p;
	}

	/**
	 * Map category names to term IDs.
	 *
	 * @since 1.0.0
	 *
	 * @param array $names Category names.
	 * @return array Term IDs.
	 */
	private function map_category_names_to_ids( array $names ): array {
		if ( empty( $names ) ) {
			return array();
		}

		$categories = $this->get_categories_with_ids();
		$name_to_id = array();
		foreach ( $categories as $cat ) {
			$name_to_id[ $cat['name'] ] = $cat['id'];
		}

		$ids = array();
		foreach ( $names as $name ) {
			$name = is_string( $name ) ? trim( $name ) : '';
			if ( '' !== $name && isset( $name_to_id[ $name ] ) ) {
				$ids[] = $name_to_id[ $name ];
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Sanitize AI-generated name or description: replace numeric placeholders (e.g. 101010) with category name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text                 Name or description from AI.
	 * @param string $first_category_name  First valid category name to substitute, or empty.
	 * @return string Sanitized text.
	 */
	private function sanitize_ai_name_description( string $text, string $first_category_name ): string {
		if ( '' === $text ) {
			return $text;
		}
		$replacement = '' !== $first_category_name ? $first_category_name : __( 'Selected products', 'smart-cycle-discounts' );
		// Replace one or more digits (e.g. 101010, 123) with the category/replacement.
		$sanitized = preg_replace( '/\b\d+\b/', $replacement, $text );
		return is_string( $sanitized ) ? $sanitized : $text;
	}

	/**
	 * Generate one full campaign suggestion and return wizard step data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $context_overrides Optional context (e.g. 'existing_campaigns' => '...').
	 * @return array|\WP_Error Wizard steps array (basic, products, discounts, schedule) or WP_Error.
	 */
	public function get_full_campaign_suggestion( array $context_overrides = array() ) {
		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return new WP_Error(
				'wsscd_cycle_ai_not_configured',
				__( 'Cycle AI is not configured. Please add an API key in the plugin settings.', 'smart-cycle-discounts' ),
				array( 'status' => 500 )
			);
		}

		$context = $this->build_full_campaign_context( $context_overrides );
		$prompt  = $this->get_system_prompt_for_full_campaign();

		$payload = array(
			'model'    => $this->get_model(),
			'messages' => array(
				array( 'role' => 'system', 'content' => $prompt ),
				array( 'role' => 'user', 'content' => $context ),
			),
		);

		$response = $this->call_api( $api_key, $payload );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$raw = $this->extract_single_full_campaign_from_response( $response );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		return $this->map_full_campaign_to_wizard_steps( $raw );
	}

	/**
	 * Extract single campaign object from chat-completions response.
	 *
	 * @since 1.0.0
	 *
	 * @param array $response Decoded API response.
	 * @return array|\WP_Error Campaign array or WP_Error.
	 */
	private function extract_single_full_campaign_from_response( array $response ) {
		if ( empty( $response['choices'] ) || ! is_array( $response['choices'] ) ) {
			return new WP_Error( 'wsscd_cycle_ai_no_choices', __( 'Cycle AI did not return a campaign.', 'smart-cycle-discounts' ), array( 'status' => 500 ) );
		}

		$content = isset( $response['choices'][0]['message']['content'] ) ? $response['choices'][0]['message']['content'] : '';
		if ( '' === $content ) {
			return new WP_Error( 'wsscd_cycle_ai_empty_content', __( 'Cycle AI returned an empty response.', 'smart-cycle-discounts' ), array( 'status' => 500 ) );
		}

		$decoded = json_decode( $content, true );
		if ( null === $decoded || ! is_array( $decoded ) ) {
			return new WP_Error( 'wsscd_cycle_ai_invalid_json', __( 'Cycle AI returned invalid JSON.', 'smart-cycle-discounts' ), array( 'status' => 500 ) );
		}

		return $decoded;
	}

	/**
	 * Map AI full-campaign array to wizard step data structure.
	 *
	 * @since 1.0.0
	 *
	 * @param array $raw Decoded AI campaign.
	 * @return array Steps keyed by step name (basic, products, discounts, schedule).
	 */
	private function map_full_campaign_to_wizard_steps( array $raw ): array {
		$category_names = isset( $raw['category_names'] ) && is_array( $raw['category_names'] ) ? $raw['category_names'] : array();
		$category_ids   = $this->map_category_names_to_ids( $category_names );
		$first_category_name = ! empty( $category_names ) && is_string( $category_names[0] ) ? trim( $category_names[0] ) : '';

		$name        = isset( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : __( 'AI-suggested campaign', 'smart-cycle-discounts' );
		$description = isset( $raw['description'] ) ? sanitize_textarea_field( $raw['description'] ) : '';
		$name        = $this->sanitize_ai_name_description( $name, $first_category_name );
		$description = $this->sanitize_ai_name_description( $description, $first_category_name );

		$priority = isset( $raw['priority'] ) ? max( 1, min( 5, (int) $raw['priority'] ) ) : 3;

		$product_selection_type = isset( $raw['product_selection_type'] ) ? sanitize_key( $raw['product_selection_type'] ) : 'all_products';
		if ( ! in_array( $product_selection_type, array( 'all_products', 'random_products', 'specific_products', 'smart_selection' ), true ) ) {
			$product_selection_type = 'all_products';
		}
		$random_count   = isset( $raw['random_count'] ) ? max( 1, min( 100, (int) $raw['random_count'] ) ) : 10;

		$discount_type = isset( $raw['discount_type'] ) ? sanitize_key( $raw['discount_type'] ) : 'percentage';
		if ( ! in_array( $discount_type, array( 'percentage', 'fixed', 'bogo', 'tiered' ), true ) ) {
			$discount_type = 'percentage';
		}
		$discount_value = isset( $raw['discount_value'] ) ? floatval( $raw['discount_value'] ) : 15.0;
		$duration_days  = isset( $raw['duration_days'] ) ? max( 1, min( 365, (int) $raw['duration_days'] ) ) : 7;
		$start_type     = isset( $raw['start_type'] ) && 'scheduled' === $raw['start_type'] ? 'scheduled' : 'immediate';
		$start_date     = isset( $raw['start_date'] ) ? sanitize_text_field( $raw['start_date'] ) : '';
		$end_date       = isset( $raw['end_date'] ) ? sanitize_text_field( $raw['end_date'] ) : '';

		if ( 'scheduled' !== $start_type || '' === $start_date || '' === $end_date ) {
			$start_type = 'immediate';
			$start_date = wp_date( 'Y-m-d' );
			$end_date   = wp_date( 'Y-m-d', strtotime( '+' . $duration_days . ' days' ) );
		}

		$basic = array(
			'name'        => $name,
			'description' => $description,
			'priority'    => $priority,
		);

		$products = array(
			'product_selection_type' => $product_selection_type,
			'category_ids'           => $category_ids,
			'product_ids'            => array(),
			'selected_product_ids'   => array(),
			'random_count'           => $random_count,
		);

		$discounts = array(
			'discount_type'             => $discount_type,
			'discount_value_percentage' => 'percentage' === $discount_type ? $discount_value : 10,
			'discount_value_fixed'      => 'fixed' === $discount_type ? $discount_value : 0,
			'stack_with_others'         => false,
			'allow_coupons'             => false,
			'apply_to_sale_items'       => false,
			'badge_enabled'             => true,
			'badge_text'                => 'auto',
		);

		$schedule = array(
			'start_type'        => $start_type,
			'start_date'        => $start_date,
			'end_date'          => $end_date,
			'start_time'        => '00:00',
			'end_time'           => '23:59',
			'duration_seconds'  => 86400 * $duration_days,
			'timezone'          => wp_timezone_string(),
			'rotation_enabled'  => false,
			'enable_recurring'  => false,
		);

		return array(
			'basic'     => $basic,
			'products'  => $products,
			'discounts' => $discounts,
			'schedule'  => $schedule,
		);
	}
}

