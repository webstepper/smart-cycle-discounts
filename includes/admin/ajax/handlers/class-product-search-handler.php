<?php
/**
 * Product Search Handler
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Product Search Handler Class
 *
 * Orchestrates product search requests by delegating to the Product Service.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/admin/ajax/handlers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Product_Search_Handler extends SCD_Abstract_Ajax_Handler {

    /**
     * Product service instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Product_Service|null    $product_service    Product service.
     */
    private $product_service = null;

    /**
     * Initialize the handler.
     *
     * @since    1.0.0
     * @param    SCD_Product_Service    $product_service    Product service (optional).
     * @param    SCD_Logger             $logger             Logger instance (optional).
     */
    public function __construct( $product_service = null, $logger = null ) {
        parent::__construct( $logger );
        $this->product_service = $product_service;
    }

    /**
     * Get AJAX action name.
     *
     * @since    1.0.0
     * @return   string    Action name.
     */
    protected function get_action_name() {
        return 'scd_product_search';
    }

    /**
     * Handle the request.
     *
     * @since    1.0.0
     * @param    array    $request    Request data.
     * @return   array                Response data.
     * @throws   Exception            If request fails.
     */
    protected function handle( $request ) {
        // NOTE: Product browsing/searching is FREE - allows users to explore what's available
        // License protection happens at campaign SAVE level (in save-step-handler)

        try {
            // Determine which action to perform with whitelist validation
            $action = isset( $request['wizard_action'] ) ? sanitize_text_field( $request['wizard_action'] ) : '';

            // Security: Whitelist of allowed actions
            $allowed_actions = array(
                'get_products_by_ids',
                'get_product_categories',
                'get_product_tags',
                '' // Empty string for default search action
            );

            if ( ! in_array( $action, $allowed_actions, true ) ) {
                throw new Exception(
                    'Invalid action requested',
                    400
                );
            }

            if ( 'get_products_by_ids' === $action ) {
                return $this->handle_get_by_ids( $request );
            }

            if ( 'get_product_categories' === $action ) {
                return $this->handle_get_categories( $request );
            }

            if ( 'get_product_tags' === $action ) {
                return $this->handle_get_tags( $request );
            }

            return $this->handle_search( $request );
        } catch ( Exception $e ) {
            // Log the error for debugging
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[SCD Product Search Handler] Error: ' . $e->getMessage() );
                error_log( '[SCD Product Search Handler] Stack trace: ' . $e->getTraceAsString() );
            }
            
            // Return error response
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'product_search_error'
            );
        }
    }

    /**
     * Handle product search.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $request    Request data.
     * @return   array               Response data.
     */
    private function handle_search( $request ) {
        // Extract and sanitize parameters
        $search = sanitize_text_field( isset( $request['search'] ) ? $request['search'] : '' );
        $page = max( 1, intval( isset( $request['page'] ) ? $request['page'] : 1 ) );
        $per_page = max( SCD_Validation_Rules::SEARCH_PER_PAGE_MIN, min( SCD_Validation_Rules::SEARCH_PER_PAGE_MAX, intval( isset( $request['per_page'] ) ? $request['per_page'] : SCD_Validation_Rules::SEARCH_PER_PAGE_DEFAULT ) ) );
        
        // Process categories
        $categories = $this->process_categories( isset( $request['categories'] ) ? $request['categories'] : array() );
        
        // Get selected products
        $selected = array_map( 'intval', (array) ( isset( $request['selected'] ) ? $request['selected'] : array() ) );

        // Delegate to service
        try {
            $service = $this->get_product_service();
            
            // Add null check for service
            if ( ! $service ) {
                throw new Exception( 'Product Service not available' );
            }
            
            $results = $service->search_products( $search, $categories, $page, $per_page, $selected );
        } catch ( Exception $e ) {
            throw $e;
        }
        
        // Format response with standardized structure
        return array(
            'products' => $results['products'],
            'pagination' => array(
                'page' => $results['current_page'],
                'total_pages' => $results['pages'],
                'total_items' => $results['total'],
                'per_page' => $results['per_page']
            ),
            'debug' => defined('WP_DEBUG') && WP_DEBUG ? array(
                'search_term' => $search,
                'categories' => $categories
            ) : null
        );
    }

    /**
     * Handle get products by IDs.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $request    Request data.
     * @return   array               Response data.
     * @throws   Exception           If too many products requested.
     */
    private function handle_get_by_ids( $request ) {
        // Get and validate product IDs
        $product_ids = isset( $request['product_ids'] ) ? $request['product_ids'] : array();
        if ( ! is_array( $product_ids ) ) {
            $product_ids = array( $product_ids );
        }

        // Limit number of products for security
        if ( count( $product_ids ) > 100 ) {
            throw new Exception(
                'Too many products requested. Maximum 100 allowed',
                400
            );
        }

        // Sanitize product IDs with proper validation
        $product_ids = array_map( 'intval', $product_ids );
        $product_ids = array_filter( $product_ids, array( $this, 'filter_valid_product_ids' ) );

        if ( empty( $product_ids ) ) {
            return array(
                'products' => array()
            );
        }

        // Delegate to service
        $service = $this->get_product_service();
        $products = $service->get_products_by_ids( $product_ids );

        return array(
            'products' => $products,
            'requested' => count( $product_ids ),
            'found' => count( $products )
        );
    }

    /**
     * Filter valid product IDs.
     *
     * @since    1.0.0
     * @access   private
     * @param    int    $id    Product ID to validate.
     * @return   bool          True if valid product ID.
     */
    private function filter_valid_product_ids( $id ) {
        return $id > 0 && $id <= PHP_INT_MAX;
    }

    /**
     * Process categories parameter.
     *
     * @since    1.0.0
     * @access   private
     * @param    mixed    $categories    Raw categories.
     * @return   array                   Processed categories.
     */
    private function process_categories( $categories ) {
        if ( ! is_array( $categories ) ) {
            $categories = array( $categories );
        }

        // Limit number of categories for performance
        if ( count( $categories ) > SCD_Validation_Rules::SEARCH_CATEGORIES_MAX ) {
            $categories = array_slice( $categories, 0, SCD_Validation_Rules::SEARCH_CATEGORIES_MAX );
        }

        // Handle 'all' category selection
        if ( in_array( 'all', $categories, true ) ) {
            return array();
        }

        // Sanitize and filter valid category IDs
        $categories = array_map( 'intval', $categories );
        $filtered = array_filter( $categories, array( $this, 'filter_valid_category_ids' ) );

        return $filtered;
    }

    /**
     * Filter valid category IDs.
     *
     * @since    1.0.0
     * @access   private
     * @param    int    $id    Category ID to validate.
     * @return   bool          True if valid category ID.
     */
    private function filter_valid_category_ids( $id ) {
        return $id > 0 && $id <= PHP_INT_MAX;
    }

    /**
     * Get product service instance.
     *
     * @since    1.0.0
     * @access   private
     * @return   SCD_Product_Service    Product service.
     */
    private function get_product_service() {
        if ( null === $this->product_service ) {
            // Use dirname to go up to the includes directory
            $includes_dir = dirname( dirname( dirname( dirname( __FILE__ ) ) ) );
            $service_file = $includes_dir . '/core/products/class-product-service.php';

            if ( ! file_exists( $service_file ) ) {
                throw new Exception( 'Product Service file not found: ' . $service_file );
            }
            
            require_once $service_file;
            
            if ( ! class_exists( 'SCD_Product_Service' ) ) {
                throw new Exception( 'Product Service class not found after include' );
            }
            
            try {
                $this->product_service = new SCD_Product_Service();
            } catch ( Exception $e ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( '[SCD Product Search Handler] Failed to instantiate Product Service: ' . $e->getMessage() );
                }
                throw new Exception( 'Failed to create Product Service instance: ' . $e->getMessage() );
            }
        }
        
        return $this->product_service;
    }

    /**
     * Handle get product categories.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $request    Request data.
     * @return   array               Response data.
     */
    private function handle_get_categories( $request ) {
        // Extract and sanitize parameters
        $search = sanitize_text_field( isset( $request['search'] ) ? $request['search'] : '' );
        $parent = max( 0, intval( isset( $request['parent'] ) ? $request['parent'] : 0 ) );
        $ids = isset( $request['ids'] ) && is_array( $request['ids'] ) ? array_map( 'sanitize_text_field', $request['ids'] ) : array();

        // Build query args for product categories
        $args = array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'search' => $search,
            'number' => 100, // Limit results
            'orderby' => 'menu_order',
            'order' => 'ASC'
        );

        // If specific IDs requested, only fetch those
        if ( ! empty( $ids ) ) {
            // Filter out 'all' from IDs as it's not a real category
            $numeric_ids = array_filter( $ids, array( $this, 'filter_numeric_category_ids' ) );

            if ( ! empty( $numeric_ids ) ) {
                $args['include'] = array_map( 'intval', $numeric_ids );
            }
        }

        // If parent is specified, filter by parent
        if ( $parent > 0 ) {
            $args['parent'] = $parent;
        }

        $terms = get_terms( $args );
        $categories = array();

        if ( ! is_wp_error( $terms ) ) {
            // Add "All Categories" option if no parent and no search, OR if 'all' is specifically requested in IDs
            if ( ( 0 === $parent && empty( $search ) ) || in_array( 'all', $ids, true ) ) {
                $categories[] = array(
                    'value' => 'all',
                    'text' => 'All Categories',
                    'count' => 0,
                    'level' => 0
                );
            }

            // If no parent specified, build hierarchical structure
            if ( 0 === $parent && empty( $search ) ) {
                $categories = array_merge( $categories, $this->build_category_hierarchy( $terms ) );
            } else {
                // Otherwise, just return the terms as-is
                foreach ( $terms as $term ) {
                    $categories[] = array(
                        'value' => $term->term_id,
                        'text' => $term->name,
                        'count' => $term->count,
                        'parent' => $term->parent,
                        'slug' => $term->slug,
                        'level' => 0 // Default level for non-hierarchical display
                    );
                }
            }
        }

        return array(
            'categories' => $categories,
            'total' => count( $categories ),
            'search' => $search,
            'parent' => $parent
        );
    }

    /**
     * Filter numeric category IDs (excludes 'all').
     *
     * @since    1.0.0
     * @access   private
     * @param    mixed    $id    Category ID to validate.
     * @return   bool            True if numeric and not 'all'.
     */
    private function filter_numeric_category_ids( $id ) {
        return 'all' !== $id && is_numeric( $id );
    }

    /**
     * Build hierarchical category structure.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $categories    Flat array of categories.
     * @param    int      $parent_id     Parent ID to start from.
     * @param    int      $level         Current nesting level.
     * @return   array                   Hierarchical array of categories.
     */
    private function build_category_hierarchy( $categories, $parent_id = 0, $level = 0 ) {
        $hierarchy = array();

        foreach ( $categories as $category ) {
            if ( $parent_id == $category->parent ) {
                // Check if this category has children
                $has_children = false;
                foreach ( $categories as $check_cat ) {
                    if ( $category->term_id == $check_cat->parent ) {
                        $has_children = true;
                        break;
                    }
                }

                $hierarchy[] = array(
                    'value' => $category->term_id,
                    'text' => $category->name,
                    'count' => $category->count,
                    'parent' => $category->parent,
                    'slug' => $category->slug,
                    'level' => $level,
                    'has_children' => $has_children
                );

                // Recursively get children
                $children = $this->build_category_hierarchy( $categories, $category->term_id, $level + 1 );
                $hierarchy = array_merge( $hierarchy, $children );
            }
        }

        return $hierarchy;
    }

    /**
     * Handle get product tags.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $request    Request data.
     * @return   array               Response data.
     */
    private function handle_get_tags( $request ) {
        // Extract and sanitize parameters
        $search = sanitize_text_field( isset( $request['search'] ) ? $request['search'] : '' );

        // Build query args for product tags
        $args = array(
            'taxonomy' => 'product_tag',
            'hide_empty' => false,
            'search' => $search,
            'number' => 100, // Limit results
            'orderby' => 'name',
            'order' => 'ASC'
        );

        $terms = get_terms( $args );
        $tags = array();

        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $tags[] = array(
                    'value' => $term->term_id,
                    'text' => $term->name,
                    'count' => $term->count,
                    'slug' => $term->slug
                );
            }
        }
        
        return array(
            'tags' => $tags,
            'total' => count( $tags ),
            'search' => $search
        );
    }
}