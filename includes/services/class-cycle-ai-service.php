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
	 * Build rich context for full-campaign AI suggestion (store, time, events, categories, products, performance, stock).
	 *
	 * Includes current date/time, weekday, upcoming events, categories with counts, sample products,
	 * best sellers, slow movers, new arrivals, and stock summary so the AI can suggest campaigns
	 * that are relevant to the current moment and store data.
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

		// Current moment and upcoming events (so AI can suggest time-relevant campaigns).
		$smart = $this->build_smart_context();
		if ( '' !== $smart ) {
			$lines[] = $smart;
			$lines[] = '';
		}

		$categories = $this->get_categories_with_ids_and_counts();
		if ( ! empty( $categories ) ) {
			$lines[] = 'Product categories (use these exact names in category_names):';
			foreach ( $categories as $cat ) {
				$count = isset( $cat['count'] ) ? ' (' . (int) $cat['count'] . ' products)' : '';
				$lines[] = '- ID ' . $cat['id'] . ': ' . $cat['name'] . $count;
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
			'user_brief'        => '',
			'extra'             => '',
		);
		$overrides = array_merge( $base, $context_overrides );
		$filtered  = apply_filters( 'wsscd_cycle_ai_full_campaign_context', $overrides );

		if ( ! empty( $filtered['existing_campaigns'] ) ) {
			$lines[] = 'Existing campaigns (suggest something different or complementary):';
			$lines[] = $filtered['existing_campaigns'];
			$lines[] = '';
		}

		if ( ! empty( $filtered['user_brief'] ) ) {
			$lines[] = 'Campaign type requested: ' . $filtered['user_brief'];
			$lines[] = 'Use the requested type as the main theme for the campaign name (e.g. "Flash Sale", "Clearance", "New Arrivals"). Only add seasonal or event wording (e.g. Valentine\'s, Black Friday) when it clearly fits this type.';
			$lines[] = '';
		}

		if ( ! empty( $filtered['extra'] ) ) {
			$lines[] = $filtered['extra'];
			$lines[] = '';
		}

		$lines[] = 'Generate exactly ONE full campaign as JSON (see schema below). Use only category names from the list above. Use the current date, weekday, upcoming events, and store data (best sellers, slow movers, new arrivals, stock) to suggest a campaign that is relevant RIGHT NOW.';

		return implode( "\n", $lines );
	}

	/**
	 * Build time- and data-aware context (date, weekday, events, best sellers, slow movers, new arrivals, stock).
	 *
	 * Cached briefly to avoid heavy queries on every AI call. Filter 'wsscd_cycle_ai_smart_context' can add more.
	 *
	 * @since 1.0.0
	 * @return string Context block for the prompt.
	 */
	private function build_smart_context(): string {
		$cache_key = 'wsscd_cycle_ai_smart_context';
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$parts = array();

		// Current date, time, weekday (store timezone). Use 2-arg wp_date() to avoid PHP 9+ DateTime::setTimezone(string) fatal.
		$now   = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i', time() ) : gmdate( 'Y-m-d H:i' );
		$day   = function_exists( 'wp_date' ) ? wp_date( 'l', time() ) : gmdate( 'l' );
		$parts[] = 'Current moment (store timezone): ' . $now . ', ' . $day . '.';

		// Upcoming events (holidays / promotions) – filter so merchant or code can inject.
		$events = $this->get_upcoming_events();
		if ( ! empty( $events ) ) {
			$parts[] = 'Upcoming events or holidays (suggest campaigns that align): ' . implode( '; ', $events );
		}

		// Best sellers (top products by recent sales).
		$bestsellers = $this->get_bestsellers_summary();
		if ( '' !== $bestsellers ) {
			$parts[] = $bestsellers;
		}

		// Slow movers (products with few or no recent sales).
		$slow = $this->get_slow_movers_summary();
		if ( '' !== $slow ) {
			$parts[] = $slow;
		}

		// New arrivals (recently published products).
		$new_arrivals = $this->get_new_arrivals_summary();
		if ( '' !== $new_arrivals ) {
			$parts[] = $new_arrivals;
		}

		// Stock summary (low stock, out of stock – opportunity for clearance).
		$stock = $this->get_stock_summary();
		if ( '' !== $stock ) {
			$parts[] = $stock;
		}

		$raw = implode( "\n", $parts );
		$out = apply_filters( 'wsscd_cycle_ai_smart_context', $raw );
		if ( ! is_string( $out ) ) {
			$out = $raw;
		}

		set_transient( $cache_key, $out, 20 * MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * Get upcoming events / holidays for the next 30 days (for time-relevant suggestions).
	 *
	 * @since 1.0.0
	 * @return array List of event strings.
	 */
	private function get_upcoming_events(): array {
		$events = array();
		$today  = wp_date( 'Y-m-d' );
		$year   = (int) wp_date( 'Y' );

		// Next 30 days: add well-known dates if they fall in range.
		$known = array(
			'Valentine\'s Day'       => $year . '-02-14',
			'St. Patrick\'s Day'     => $year . '-03-17',
			'Easter Sunday'          => $this->get_easter_date( $year ),
			'Mother\'s Day'          => $this->get_mothers_day( $year ),
			'Father\'s Day'          => $this->get_fathers_day( $year ),
			'Independence Day (US)'  => $year . '-07-04',
			'Halloween'              => $year . '-10-31',
			'Black Friday'           => $this->get_black_friday( $year ),
			'Cyber Monday'           => $this->get_cyber_monday( $year ),
			'Christmas'              => $year . '-12-25',
			'New Year\'s Eve'         => $year . '-12-31',
		);

		$cutoff = strtotime( $today . ' +30 days' );
		foreach ( $known as $label => $date ) {
			if ( '' === $date ) {
				continue;
			}
			$ts = strtotime( $date );
			if ( $ts >= strtotime( $today ) && $ts <= $cutoff ) {
				$events[] = $label . ' (' . $date . ')';
			}
		}

		$events = apply_filters( 'wsscd_cycle_ai_upcoming_events', $events );
		return is_array( $events ) ? $events : array();
	}

	/**
	 * Easter Sunday (approximate for Western Christianity).
	 *
	 * @param int $year Year.
	 * @return string Y-m-d or empty.
	 */
	private function get_easter_date( int $year ): string {
		$a = $year % 19;
		$b = (int) floor( $year / 100 );
		$c = $year % 100;
		$d = (int) floor( $b / 4 );
		$e = $b % 4;
		$f = (int) floor( ( $b + 8 ) / 25 );
		$g = (int) floor( ( $b - $f + 1 ) / 3 );
		$h = ( 19 * $a + $b - $d - $g + 15 ) % 30;
		$i = (int) floor( $c / 4 );
		$k = $c % 4;
		$l = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
		$m = (int) floor( ( $a + 11 * $h + 22 * $l ) / 451 );
		$month = (int) floor( ( $h + $l - 7 * $m + 114 ) / 31 );
		$day   = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;
		if ( $month < 1 || $month > 12 || $day < 1 || $day > 31 ) {
			return '';
		}
		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}

	private function get_mothers_day( int $year ): string {
		$second_sunday = strtotime( "second sunday of May $year" );
		return $second_sunday ? wp_date( 'Y-m-d', $second_sunday ) : '';
	}

	private function get_fathers_day( int $year ): string {
		$third_sunday = strtotime( "third sunday of June $year" );
		return $third_sunday ? wp_date( 'Y-m-d', $third_sunday ) : '';
	}

	private function get_black_friday( int $year ): string {
		$thanksgiving = strtotime( "fourth thursday of November $year" );
		if ( ! $thanksgiving ) {
			return '';
		}
		return wp_date( 'Y-m-d', strtotime( '+1 day', $thanksgiving ) );
	}

	private function get_cyber_monday( int $year ): string {
		$thanksgiving = strtotime( "fourth thursday of November $year" );
		if ( ! $thanksgiving ) {
			return '';
		}
		return wp_date( 'Y-m-d', strtotime( '+4 days', $thanksgiving ) );
	}

	/**
	 * Best sellers summary (top products by quantity sold in last 30 days). Cached.
	 *
	 * @since 1.0.0
	 * @return string Summary line(s) or empty.
	 */
	private function get_bestsellers_summary(): string {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return '';
		}

		$date_from = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		$orders = wc_get_orders(
			array(
				'status'       => array( 'wc-completed', 'wc-processing' ),
				'limit'        => 200,
				'date_created' => '>' . $date_from,
				'return'       => 'ids',
			)
		);

		if ( empty( $orders ) || ! is_array( $orders ) ) {
			return '';
		}

		$qty_by_id = array();
		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();
				if ( $product_id ) {
					$qty_by_id[ $product_id ] = ( isset( $qty_by_id[ $product_id ] ) ? $qty_by_id[ $product_id ] : 0 ) + $item->get_quantity();
				}
			}
		}

		if ( empty( $qty_by_id ) ) {
			return '';
		}

		arsort( $qty_by_id, SORT_NUMERIC );
		$top = array_slice( array_keys( $qty_by_id ), 0, 10 );
		$names = array();
		foreach ( $top as $pid ) {
			$product = wc_get_product( $pid );
			if ( $product && is_a( $product, 'WC_Product' ) ) {
				$name = $product->get_name();
				if ( $name ) {
					$names[] = $name;
				}
			}
		}

		if ( empty( $names ) ) {
			return '';
		}

		return 'Best sellers (last 30 days): ' . implode( ', ', array_slice( $names, 0, 8 ) ) . '.';
	}

	/**
	 * Slow movers: products with no or very low sales in last 30 days (candidates for clearance).
	 *
	 * @since 1.0.0
	 * @return string Summary or empty.
	 */
	private function get_slow_movers_summary(): string {
		if ( ! function_exists( 'wc_get_orders' ) || ! function_exists( 'wc_get_products' ) ) {
			return '';
		}

		$date_from = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		$orders = wc_get_orders(
			array(
				'status'       => array( 'wc-completed', 'wc-processing' ),
				'limit'        => 300,
				'date_created' => '>' . $date_from,
				'return'       => 'ids',
			)
		);

		$sold_ids = array();
		if ( ! empty( $orders ) && is_array( $orders ) ) {
			foreach ( $orders as $order_id ) {
				$order = wc_get_order( $order_id );
				if ( ! $order ) {
					continue;
				}
				foreach ( $order->get_items() as $item ) {
					$pid = $item->get_product_id();
					if ( $pid ) {
						$sold_ids[ $pid ] = true;
					}
				}
			}
		}

		// Products published but not in sold list (simplified "slow movers").
		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 100,
				'orderby' => 'date',
				'order'   => 'ASC',
				'return'  => 'ids',
			)
		);

		if ( empty( $products ) || ! is_array( $products ) ) {
			return '';
		}

		$slow = array();
		foreach ( array_slice( $products, 0, 50 ) as $pid ) {
			if ( isset( $sold_ids[ $pid ] ) ) {
				continue;
			}
			$product = wc_get_product( $pid );
			if ( $product && is_a( $product, 'WC_Product' ) ) {
				$name = $product->get_name();
				if ( $name ) {
					$slow[] = $name;
				}
			}
			if ( count( $slow ) >= 5 ) {
				break;
			}
		}

		if ( empty( $slow ) ) {
			return '';
		}

		return 'Slow movers (little or no recent sales; good for clearance): ' . implode( ', ', $slow ) . '.';
	}

	/**
	 * New arrivals: recently published products (last 14 days).
	 *
	 * @since 1.0.0
	 * @return string Summary or empty.
	 */
	private function get_new_arrivals_summary(): string {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return '';
		}

		$after = wp_date( 'Y-m-d', strtotime( '-14 days' ) );
		$today = wp_date( 'Y-m-d' );
		$products = wc_get_products(
			array(
				'status'       => 'publish',
				'limit'        => 20,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'date_created' => $after . '...' . $today,
				'return'       => 'objects',
			)
		);

		if ( empty( $products ) ) {
			return '';
		}

		$count = is_array( $products ) ? count( $products ) : 0;
		$names = array();
		foreach ( array_slice( $products, 0, 5 ) as $product ) {
			if ( is_a( $product, 'WC_Product' ) ) {
				$n = $product->get_name();
				if ( $n ) {
					$names[] = $n;
				}
			}
		}

		$out = 'New arrivals (last 14 days): ' . $count . ' product(s).';
		if ( ! empty( $names ) ) {
			$out .= ' Examples: ' . implode( ', ', $names ) . '.';
		}
		return $out;
	}

	/**
	 * Stock summary: low stock and out-of-stock counts (opportunity for clearance or restock promos).
	 *
	 * @since 1.0.0
	 * @return string Summary or empty.
	 */
	private function get_stock_summary(): string {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return '';
		}

		$low = 0;
		$out = 0;
		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 500,
				'return' => 'ids',
			)
		);

		if ( empty( $products ) || ! is_array( $products ) ) {
			return '';
		}

		foreach ( $products as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				continue;
			}
			if ( $product->managing_stock() ) {
				$stock = $product->get_stock_quantity();
				if ( $stock !== null && $stock <= $product->get_low_stock_amount() ) {
					++$low;
				}
				if ( $stock !== null && $stock <= 0 ) {
					++$out;
				}
			} else {
				$status = $product->get_stock_status();
				if ( 'outofstock' === $status ) {
					++$out;
				}
			}
		}

		if ( $low === 0 && $out === 0 ) {
			return '';
		}

		$parts = array();
		if ( $low > 0 ) {
			$parts[] = $low . ' product(s) low on stock';
		}
		if ( $out > 0 ) {
			$parts[] = $out . ' out of stock';
		}
		return 'Stock: ' . implode( ', ', $parts ) . '. Consider clearance or restock campaigns.';
	}

	/**
	 * Get categories with IDs and product counts (for smarter targeting).
	 *
	 * @since 1.0.0
	 * @return array List of arrays with id, name, count.
	 */
	private function get_categories_with_ids_and_counts(): array {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return $this->get_categories_with_ids();
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
			return $this->get_categories_with_ids();
		}

		$out = array();
		foreach ( $terms as $term ) {
			if ( isset( $term->term_id, $term->name ) && '' !== $term->name ) {
				$out[] = array(
					'id'    => (int) $term->term_id,
					'name'  => $term->name,
					'count' => isset( $term->count ) ? (int) $term->count : 0,
				);
			}
		}

		return $out;
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
		$p .= 'You will receive the current date and time, weekday, upcoming events or holidays, store categories (with product counts), best sellers, slow movers, new arrivals, and stock summary. ';
		$p .= 'Use this data to suggest a campaign that is relevant RIGHT NOW (e.g. holiday or event promos, clear slow movers, highlight new arrivals, flash sale on overstock). ';
		$p .= 'Generate exactly ONE complete campaign that the merchant can review and save. ';
		$p .= 'Use only the store information provided. ';
		$p .= 'Plugin supports: product_selection_type (all_products, random_products, specific_products, smart_selection), ';
		$p .= 'discount_type (percentage, fixed, bogo, tiered), category filter, and schedule (immediate or scheduled). ';
		$p .= 'Product selection rules: Prefer "all_products" when targeting one or more categories (category_names). ';
		$p .= 'Use "random_products" only when the campaign idea is explicitly about a random subset (e.g. "Weekly random picks", "Flash sale – 5 random items"). ';
		$p .= 'Vary your suggestions: do not always use random_products; often use all_products with category_names so the whole category is on sale. ';
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
		$p .= 'When using random_products set random_count (1-100); when using all_products random_count is ignored. ';
		$p .= 'Campaign name and description must be human-readable only: use real words, event names, or category names from the list. ';
		$p .= 'When a merchant request or focus is provided (e.g. "Flash Sale", "Clearance", "New Arrivals"), use that as the primary theme for the campaign name—e.g. "Flash Sale", "Overstock Clearance", "New Arrivals Promo". ';
		$p .= 'Only add a seasonal or event prefix (e.g. Valentine\'s, Black Friday) when it clearly fits the requested type or when no specific type was given. Do not prefix every campaign with the same seasonal theme. ';
		$p .= 'Never put numbers, IDs, or digit sequences (like 101010) in name or description.';
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
	 * Sanitize AI-generated name or description: remove numeric placeholders (e.g. 1010101010).
	 *
	 * Some models output digit sequences as placeholders. We strip those and collapse spaces.
	 * If nothing meaningful remains, use the category name or a fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text                 Name or description from AI.
	 * @param string $first_category_name  First valid category name for fallback, or empty.
	 * @return string Sanitized text.
	 */
	private function sanitize_ai_name_description( string $text, string $first_category_name ): string {
		if ( '' === $text ) {
			return $text;
		}
		$fallback = '' !== $first_category_name ? $first_category_name : __( 'AI-suggested campaign', 'smart-cycle-discounts' );
		// Remove any token that is purely digits (e.g. 1010101010, 123) and collapse spaces.
		$sanitized = preg_replace( '/\s*\b\d+\b\s*/', ' ', $text );
		$sanitized = is_string( $sanitized ) ? preg_replace( '/\s{2,}/', ' ', trim( $sanitized ) ) : '';
		if ( '' === $sanitized ) {
			return $fallback;
		}
		return $sanitized;
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
		// Drop AI placeholder "categories" that are purely digits (e.g. 1010101010, 11111).
		$category_names = array_values( array_filter( $category_names, function ( $n ) {
			$n = is_string( $n ) ? trim( $n ) : '';
			return '' !== $n && ! preg_match( '/^\d+$/', $n );
		} ) );
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

