<?php
/**
 * Campaigns REST API controller
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/api/controllers
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Campaigns API Controller
 *
 * Handles all REST API operations for campaigns.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/api/controllers
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Campaigns_Controller {

    /**
     * API namespace.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $namespace    API namespace.
     */
    private string $namespace;

    /**
     * Campaign manager instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Campaign_Manager    $campaign_manager    Campaign manager.
     */
    private SCD_Campaign_Manager $campaign_manager;

    /**
     * Permissions manager instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_API_Permissions    $permissions_manager    Permissions manager.
     */
    private SCD_API_Permissions $permissions_manager;

    /**
     * Logger instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Logger    $logger    Logger instance.
     */
    private SCD_Logger $logger;

    /**
     * Campaign serializer instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      SCD_Campaign_Serializer    $serializer    Campaign serializer.
     */
    private SCD_Campaign_Serializer $serializer;

    /**
     * Initialize the campaigns endpoint.
     *
     * @since    1.0.0
     * @param    string                     $namespace             API namespace.
     * @param    SCD_Campaign_Manager       $campaign_manager      Campaign manager.
     * @param    SCD_API_Permissions        $permissions_manager   Permissions manager.
     * @param    SCD_Campaign_Serializer    $serializer            Campaign serializer.
     * @param    SCD_Logger                 $logger                Logger instance.
     */
    public function __construct(
        string $namespace,
        SCD_Campaign_Manager $campaign_manager,
        SCD_API_Permissions $permissions_manager,
        SCD_Campaign_Serializer $serializer,
        SCD_Logger $logger
    ) {
        $this->namespace = $namespace;
        $this->campaign_manager = $campaign_manager;
        $this->permissions_manager = $permissions_manager;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * Register API routes.
     *
     * @since    1.0.0
     * @return   void
     */
    public function register_routes(): void {
        // Collection endpoint
        register_rest_route($this->namespace, '/campaigns', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_campaigns'),
                'permission_callback' => array($this->permissions_manager, 'check_campaigns_permissions'),
                'args' => $this->get_collection_params()
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_campaign'),
                'permission_callback' => array($this->permissions_manager, 'check_campaigns_permissions'),
                'args' => $this->get_campaign_schema()
            )
        ));

        // Single campaign endpoint
        register_rest_route($this->namespace, '/campaigns/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_campaign'),
                'permission_callback' => array($this->permissions_manager, 'check_campaigns_permissions'),
                'args' => array(
                    'id' => array(
                        'description' => __('Campaign ID.', 'smart-cycle-discounts'),
                        'type' => 'integer',
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0;
                        }
                    )
                )
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_campaign'),
                'permission_callback' => array($this->permissions_manager, 'check_campaigns_permissions'),
                'args' => $this->get_campaign_schema()
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_campaign'),
                'permission_callback' => array($this->permissions_manager, 'check_campaigns_permissions'),
                'args' => array(
                    'force' => array(
                        'description' => __('Whether to permanently delete the campaign.', 'smart-cycle-discounts'),
                        'type' => 'boolean',
                        'default' => false
                    )
                )
            )
        ));

        // Campaign actions
        register_rest_route($this->namespace, '/campaigns/(?P<id>\d+)/(?P<action>activate|deactivate|duplicate)', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'campaign_action'),
            'permission_callback' => array($this->permissions_manager, 'check_campaigns_permissions'),
            'args' => array(
                'id' => array(
                    'description' => __('Campaign ID.', 'smart-cycle-discounts'),
                    'type' => 'integer',
                    'required' => true
                ),
                'action' => array(
                    'description' => __('Action to perform.', 'smart-cycle-discounts'),
                    'type' => 'string',
                    'enum' => array('activate', 'deactivate', 'duplicate'),
                    'required' => true
                )
            )
        ));

        // Bulk operations
        register_rest_route($this->namespace, '/campaigns/batch', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'batch_campaigns'),
            'permission_callback' => array($this->permissions_manager, 'check_campaigns_permissions'),
            'args' => array(
                'action' => array(
                    'description' => __('Bulk action to perform.', 'smart-cycle-discounts'),
                    'type' => 'string',
                    'enum' => array('activate', 'deactivate', 'delete'),
                    'required' => true
                ),
                'ids' => array(
                    'description' => __('Campaign IDs to process.', 'smart-cycle-discounts'),
                    'type' => 'array',
                    'items' => array('type' => 'integer'),
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_array($param) && !empty($param);
                    }
                )
            )
        ));

        $this->logger->debug('Campaigns API routes registered');
    }

    /**
     * Get campaigns collection.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    Request object.
     * @return   WP_REST_Response               Response object.
     */
    public function get_campaigns(WP_REST_Request $request): WP_REST_Response {
        try {
            $params = $this->prepare_collection_params($request);
            $campaigns = $this->campaign_manager->get_campaigns($params);
            $total = $this->campaign_manager->count_campaigns($params);

            $data = array();
            foreach ($campaigns as $campaign) {
                if (!$campaign) {
                    continue;
                }
                $data[] = $this->prepare_campaign_for_response($campaign, $request);
            }

            $response = new WP_REST_Response($data, 200);
            
            // Add pagination headers
            $response->header('X-WP-Total', (string) $total);
            $response->header('X-WP-TotalPages', (string) ceil($total / $params['per_page']));

            $this->logger->debug('Campaigns retrieved via API', array(
                'count' => count($data),
                'total' => $total,
                'params' => $params
            ));

            return $response;

        } catch (Exception $e) {
            $this->logger->error('Failed to get campaigns via API', array(
                'error' => $e->getMessage(),
                'params' => $request->get_params()
            ));

            return new WP_REST_Response(array(
                'code' => 'campaigns_fetch_error',
                'message' => __('Failed to retrieve campaigns.', 'smart-cycle-discounts'),
                'data' => array('status' => 500)
            ), 500);
        }
    }

    /**
     * Get single campaign.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    Request object.
     * @return   WP_REST_Response               Response object.
     */
    public function get_campaign(WP_REST_Request $request): WP_REST_Response {
        $campaign_id = (int) $request['id'];

        try {
            $campaign = $this->campaign_manager->find($campaign_id);
            
            if (!$campaign) {
                return new WP_REST_Response(array(
                    'code' => 'campaign_not_found',
                    'message' => __('Campaign not found.', 'smart-cycle-discounts'),
                    'data' => array('status' => 404)
                ), 404);
            }

            $data = $this->prepare_campaign_for_response($campaign, $request);

            $this->logger->debug('Campaign retrieved via API', array('campaign_id' => $campaign_id));

            return new WP_REST_Response($data, 200);

        } catch (Exception $e) {
            $this->logger->error('Failed to get campaign via API', array(
                'campaign_id' => $campaign_id,
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'code' => 'campaign_fetch_error',
                'message' => __('Failed to retrieve campaign.', 'smart-cycle-discounts'),
                'data' => array('status' => 500)
            ), 500);
        }
    }

    /**
     * Create new campaign.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    Request object.
     * @return   WP_REST_Response               Response object.
     */
    public function create_campaign(WP_REST_Request $request): WP_REST_Response {
        try {
            $campaign_data = $this->prepare_campaign_for_database($request);
            $result = $this->campaign_manager->create_campaign($campaign_data);

            if (!$result->is_success()) {
                return new WP_REST_Response(array(
                    'code' => 'campaign_creation_failed',
                    'message' => $result->get_error_message(),
                    'data' => array(
                        'status' => 400,
                        'errors' => $result->get_errors()
                    )
                ), 400);
            }

            $campaign = $result->get_campaign();
            $data = $this->prepare_campaign_for_response($campaign, $request);

            $this->logger->info('Campaign created via API', array(
                'campaign_id' => $campaign->get_id(),
                'name' => $campaign->get_name()
            ));

            return new WP_REST_Response($data, 201);

        } catch (Exception $e) {
            $this->logger->error('Failed to create campaign via API', array(
                'error' => $e->getMessage(),
                'data' => $request->get_params()
            ));

            return new WP_REST_Response(array(
                'code' => 'campaign_creation_error',
                'message' => __('Failed to create campaign.', 'smart-cycle-discounts'),
                'data' => array('status' => 500)
            ), 500);
        }
    }

    /**
     * Update existing campaign.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    Request object.
     * @return   WP_REST_Response               Response object.
     */
    public function update_campaign(WP_REST_Request $request): WP_REST_Response {
        $campaign_id = (int) $request['id'];

        try {
            $campaign = $this->campaign_manager->find($campaign_id);
            
            if (!$campaign) {
                return new WP_REST_Response(array(
                    'code' => 'campaign_not_found',
                    'message' => __('Campaign not found.', 'smart-cycle-discounts'),
                    'data' => array('status' => 404)
                ), 404);
            }

            $campaign_data = $this->prepare_campaign_for_database($request);
            $result = $this->campaign_manager->update_campaign($campaign_id, $campaign_data);

            if (!$result->is_success()) {
                return new WP_REST_Response(array(
                    'code' => 'campaign_update_failed',
                    'message' => $result->get_error_message(),
                    'data' => array(
                        'status' => 400,
                        'errors' => $result->get_errors()
                    )
                ), 400);
            }

            $updated_campaign = $result->get_campaign();
            $data = $this->prepare_campaign_for_response($updated_campaign, $request);

            $this->logger->info('Campaign updated via API', array(
                'campaign_id' => $campaign_id,
                'name' => $updated_campaign->get_name()
            ));

            return new WP_REST_Response($data, 200);

        } catch (Exception $e) {
            $this->logger->error('Failed to update campaign via API', array(
                'campaign_id' => $campaign_id,
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'code' => 'campaign_update_error',
                'message' => __('Failed to update campaign.', 'smart-cycle-discounts'),
                'data' => array('status' => 500)
            ), 500);
        }
    }

    /**
     * Delete campaign.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    Request object.
     * @return   WP_REST_Response               Response object.
     */
    public function delete_campaign(WP_REST_Request $request): WP_REST_Response {
        $campaign_id = (int) $request['id'];
        $force = (bool) $request->get_param('force');

        try {
            $campaign = $this->campaign_manager->find($campaign_id);
            
            if (!$campaign) {
                return new WP_REST_Response(array(
                    'code' => 'campaign_not_found',
                    'message' => __('Campaign not found.', 'smart-cycle-discounts'),
                    'data' => array('status' => 404)
                ), 404);
            }

            $result = $this->campaign_manager->delete_campaign($campaign_id, $force);

            if (!$result) {
                return new WP_REST_Response(array(
                    'code' => 'campaign_delete_failed',
                    'message' => __('Failed to delete campaign.', 'smart-cycle-discounts'),
                    'data' => array('status' => 500)
                ), 500);
            }

            $this->logger->info('Campaign deleted via API', array(
                'campaign_id' => $campaign_id,
                'force' => $force
            ));

            return new WP_REST_Response(array(
                'deleted' => true,
                'previous' => $this->prepare_campaign_for_response($campaign, $request)
            ), 200);

        } catch (Exception $e) {
            $this->logger->error('Failed to delete campaign via API', array(
                'campaign_id' => $campaign_id,
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'code' => 'campaign_delete_error',
                'message' => __('Failed to delete campaign.', 'smart-cycle-discounts'),
                'data' => array('status' => 500)
            ), 500);
        }
    }

    /**
     * Perform campaign action.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    Request object.
     * @return   WP_REST_Response               Response object.
     */
    public function campaign_action(WP_REST_Request $request): WP_REST_Response {
        $campaign_id = (int) $request['id'];
        $action = $request['action'];

        try {
            $campaign = $this->campaign_manager->find($campaign_id);
            
            if (!$campaign) {
                return new WP_REST_Response(array(
                    'code' => 'campaign_not_found',
                    'message' => __('Campaign not found.', 'smart-cycle-discounts'),
                    'data' => array('status' => 404)
                ), 404);
            }

            $result = match ($action) {
                'activate' => $this->campaign_manager->activate_campaign($campaign_id),
                'deactivate' => $this->campaign_manager->deactivate_campaign($campaign_id),
                'duplicate' => $this->campaign_manager->duplicate_campaign($campaign_id),
                default => false
            };

            if (!$result || (is_object($result) && !$result->is_success())) {
                $error_message = is_object($result) ? $result->get_error_message() : 
                    sprintf(__('Failed to %s campaign.', 'smart-cycle-discounts'), $action);

                return new WP_REST_Response(array(
                    'code' => 'campaign_action_failed',
                    'message' => $error_message,
                    'data' => array('status' => 400)
                ), 400);
            }

            // Get updated campaign or new campaign for duplicate
            $updated_campaign = $action === 'duplicate' ? 
                $result->get_campaign() : 
                $this->campaign_manager->find($campaign_id);

            $data = $this->prepare_campaign_for_response($updated_campaign, $request);

            $this->logger->info('Campaign action performed via API', array(
                'campaign_id' => $campaign_id,
                'action' => $action
            ));

            return new WP_REST_Response($data, 200);

        } catch (Exception $e) {
            $this->logger->error('Failed to perform campaign action via API', array(
                'campaign_id' => $campaign_id,
                'action' => $action,
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'code' => 'campaign_action_error',
                'message' => __('Failed to perform campaign action.', 'smart-cycle-discounts'),
                'data' => array('status' => 500)
            ), 500);
        }
    }

    /**
     * Perform bulk operations on campaigns.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    Request object.
     * @return   WP_REST_Response               Response object.
     */
    public function batch_campaigns(WP_REST_Request $request): WP_REST_Response {
        $action = $request['action'];
        $campaign_ids = array_map('intval', $request['ids']);

        try {
            $results = array();
            $errors = array();

            foreach ($campaign_ids as $campaign_id) {
                try {
                    $result = match ($action) {
                        'activate' => $this->campaign_manager->activate_campaign($campaign_id),
                        'deactivate' => $this->campaign_manager->deactivate_campaign($campaign_id),
                        'delete' => $this->campaign_manager->delete_campaign($campaign_id),
                        default => false
                    };

                    if ($result) {
                        $results[] = $campaign_id;
                    } else {
                        $errors[] = array(
                            'id' => $campaign_id,
                            'message' => sprintf(__('Failed to %s campaign %d.', 'smart-cycle-discounts'), $action, $campaign_id)
                        );
                    }

                } catch (Exception $e) {
                    $errors[] = array(
                        'id' => $campaign_id,
                        'message' => $e->getMessage()
                    );
                }
            }

            $this->logger->info('Bulk campaign action performed via API', array(
                'action' => $action,
                'processed' => count($results),
                'errors' => count($errors)
            ));

            return new WP_REST_Response(array(
                'action' => $action,
                'processed' => $results,
                'errors' => $errors,
                'total' => count($campaign_ids),
                'success_count' => count($results),
                'error_count' => count($errors)
            ), 200);

        } catch (Exception $e) {
            $this->logger->error('Failed to perform bulk campaign action via API', array(
                'action' => $action,
                'ids' => $campaign_ids,
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'code' => 'bulk_action_error',
                'message' => __('Failed to perform bulk action.', 'smart-cycle-discounts'),
                'data' => array('status' => 500)
            ), 500);
        }
    }

    /**
     * Prepare campaign for API response.
     *
     * @since    1.0.0
     * @access   private
     * @param    SCD_Campaign       $campaign    Campaign object.
     * @param    WP_REST_Request    $request     Request object.
     * @return   array                           Prepared campaign data.
     */
    private function prepare_campaign_for_response(SCD_Campaign $campaign, WP_REST_Request $request): array {
        $fields = $request->get_param('_fields');
        $embed = $request->get_param('_embed');

        $data = array(
            'id' => $campaign->get_id(),
            'uuid' => $campaign->get_uuid(),
            'name' => $campaign->get_name(),
            'description' => $campaign->get_description(),
            'status' => $campaign->get_status(),
            'type' => $campaign->get_type(),
            'discount_type' => $campaign->get_discount_type(),
            'discount_value' => $campaign->get_discount_value(),
            'start_date' => $campaign->get_starts_at() ? $campaign->get_starts_at()->format('Y-m-d H:i:s') : null,
            'end_date' => $campaign->get_ends_at() ? $campaign->get_ends_at()->format('Y-m-d H:i:s') : null,
            'settings' => $campaign->get_settings(),
            'created_at' => $campaign->get_created_at()->format('Y-m-d H:i:s'),
            'updated_at' => $campaign->get_updated_at()->format('Y-m-d H:i:s'),
            'created_by' => $campaign->get_created_by()
        );

        // Add computed fields
        $data['is_active'] = $campaign->is_active();
        $data['is_scheduled'] = $campaign->is_scheduled();
        $data['is_expired'] = $campaign->is_expired();
        $data['days_remaining'] = $campaign->get_days_remaining();

        // Add performance metrics if embedded
        if ($embed && strpos($embed, 'metrics') !== false) {
            $data['metrics'] = array(
                'views' => 0, // Would be calculated from analytics
                'clicks' => 0,
                'conversions' => 0,
                'revenue' => 0.0
            );
        }

        // Filter fields if specified
        if ($fields) {
            $field_list = explode(',', $fields);
            $data = array_intersect_key($data, array_flip($field_list));
        }

        return $data;
    }

    /**
     * Prepare campaign data for database.
     *
     * @since    1.0.0
     * @access   private
     * @param    WP_REST_Request    $request    Request object.
     * @return   array                          Prepared campaign data.
     */
    private function prepare_campaign_for_database(WP_REST_Request $request): array {
        $data = array();

        $fields = array(
            'name', 'description', 'status', 'type',
            'discount_type', 'discount_value',
            'start_date', 'end_date', 'settings'
        );

        foreach ($fields as $field) {
            if ($request->has_param($field)) {
                $data[$field] = $request->get_param($field);
            }
        }

        return $data;
    }

    /**
     * Prepare collection parameters.
     *
     * @since    1.0.0
     * @access   private
     * @param    WP_REST_Request    $request    Request object.
     * @return   array                          Prepared parameters.
     */
    private function prepare_collection_params(WP_REST_Request $request): array {
        $params = array();

        // Pagination
        $params['page'] = max(1, (int) $request->get_param('page'));
        $params['per_page'] = min(100, max(1, (int) $request->get_param('per_page')));
        $params['offset'] = ($params['page'] - 1) * $params['per_page'];

        // Filtering
        if ($request->has_param('status')) {
            $params['status'] = $request->get_param('status');
        }

        if ($request->has_param('type')) {
            $params['type'] = $request->get_param('type');
        }

        if ($request->has_param('search')) {
            $params['search'] = sanitize_text_field($request->get_param('search'));
        }

        // Sorting
        $params['orderby'] = $request->get_param('orderby') ?: 'created_at';
        $params['order'] = $request->get_param('order') ?: 'DESC';

        return $params;
    }

    /**
     * Get collection parameters schema.
     *
     * @since    1.0.0
     * @access   private
     * @return   array    Collection parameters.
     */
    private function get_collection_params(): array {
        return array(
            'page' => array(
                'description' => __('Current page of the collection.', 'smart-cycle-discounts'),
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1
            ),
            'per_page' => array(
                'description' => __('Maximum number of items to return.', 'smart-cycle-discounts'),
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100
            ),
            'search' => array(
                'description' => __('Search term to filter campaigns.', 'smart-cycle-discounts'),
                'type' => 'string'
            ),
            'status' => array(
                'description' => __('Filter by campaign status.', 'smart-cycle-discounts'),
                'type' => 'string',
                'enum' => array('draft', 'active', 'paused', 'completed', 'expired')
            ),
            'type' => array(
                'description' => __('Filter by campaign type.', 'smart-cycle-discounts'),
                'type' => 'string',
                'enum' => array('flash_sale', 'seasonal', 'clearance', 'new_product')
            ),
            'orderby' => array(
                'description' => __('Sort collection by field.', 'smart-cycle-discounts'),
                'type' => 'string',
                'enum' => array('id', 'name', 'status', 'created_at', 'start_date'),
                'default' => 'created_at'
            ),
            'order' => array(
                'description' => __('Sort order.', 'smart-cycle-discounts'),
                'type' => 'string',
                'enum' => array('ASC', 'DESC'),
                'default' => 'DESC'
            ),
            '_fields' => array(
                'description' => __('Limit response to specific fields.', 'smart-cycle-discounts'),
                'type' => 'string'
            ),
            '_embed' => array(
                'description' => __('Embed additional data.', 'smart-cycle-discounts'),
                'type' => 'string'
            )
        );
    }

    /**
     * Get campaign schema.
     *
     * @since    1.0.0
     * @access   private
     * @return   array    Campaign schema.
     */
    private function get_campaign_schema(): array {
        return array(
            'name' => array(
                'description' => __('Campaign name.', 'smart-cycle-discounts'),
                'type' => 'string',
                'required' => true,
                'validate_callback' => function($param) {
                    return !empty(trim($param)) && strlen($param) <= 255;
                }
            ),
            'description' => array(
                'description' => __('Campaign description.', 'smart-cycle-discounts'),
                'type' => 'string'
            ),
            'status' => array(
                'description' => __('Campaign status.', 'smart-cycle-discounts'),
                'type' => 'string',
                'enum' => array('draft', 'active', 'paused', 'completed', 'expired'),
                'default' => 'draft'
            ),
            'type' => array(
                'description' => __('Campaign type.', 'smart-cycle-discounts'),
                'type' => 'string',
                'enum' => array('flash_sale', 'seasonal', 'clearance', 'new_product'),
                'required' => true
            ),
            'discount_type' => array(
                'description' => __('Discount type.', 'smart-cycle-discounts'),
                'type' => 'string',
                'enum' => array('percentage', 'fixed'),
                'required' => true
            ),
            'discount_value' => array(
                'description' => __('Discount value.', 'smart-cycle-discounts'),
                'type' => 'number',
                'required' => true,
                'minimum' => 0
            ),
            'start_date' => array(
                'description' => __('Campaign start date.', 'smart-cycle-discounts'),
                'type' => 'string',
                'format' => 'date-time'
            ),
            'end_date' => array(
                'description' => __('Campaign end date.', 'smart-cycle-discounts'),
                'type' => 'string',
                'format' => 'date-time'
            ),
            'settings' => array(
                'description' => __('Campaign settings.', 'smart-cycle-discounts'),
                'type' => 'object'
            )
        );
    }

    /**
     * Get endpoint information.
     *
     * @since    1.0.0
     * @return   array    Endpoint information.
     */
    public function get_endpoint_info(): array {
        return array(
            'name' => 'Campaigns',
            'description' => __('Manage discount campaigns', 'smart-cycle-discounts'),
            'routes' => array(
                'GET /campaigns' => __('List campaigns', 'smart-cycle-discounts'),
                'POST /campaigns' => __('Create campaign', 'smart-cycle-discounts'),
                'GET /campaigns/{id}' => __('Get campaign', 'smart-cycle-discounts'),
                'PUT /campaigns/{id}' => __('Update campaign', 'smart-cycle-discounts'),
                'DELETE /campaigns/{id}' => __('Delete campaign', 'smart-cycle-discounts'),
                'POST /campaigns/{id}/activate' => __('Activate campaign', 'smart-cycle-discounts'),
                'POST /campaigns/{id}/deactivate' => __('Deactivate campaign', 'smart-cycle-discounts'),
                'POST /campaigns/{id}/duplicate' => __('Duplicate campaign', 'smart-cycle-discounts'),
                'POST /campaigns/batch' => __('Bulk operations', 'smart-cycle-discounts')
            ),
            'capabilities' => array(
                'view_campaigns',
                'create_campaigns',
                'edit_campaigns',
                'delete_campaigns'
            )
        );
    }
}
