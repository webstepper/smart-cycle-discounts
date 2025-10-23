<?php
/**
 * Task manager
 *
 * @link       https://smartcyclediscounts.com
 * @since      1.0.0
 *
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/scheduled-tasks
 */

declare(strict_types=1);


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Task Manager
 *
 * Handles scheduled task management.
 *
 * @since      1.0.0
 * @package    SmartCycleDiscounts
 * @subpackage SmartCycleDiscounts/includes/scheduled-tasks
 * @author     Smart Cycle Discounts <support@smartcyclediscounts.com>
 */
class SCD_Task_Manager {

    /**
     * Container instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      object    $container    Container instance.
     */
    private object $container;

    /**
     * Registered tasks.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $tasks    Registered tasks.
     */
    private array $tasks = array();

    /**
     * Initialize the task manager.
     *
     * @since    1.0.0
     * @param    object    $container    Container instance.
     */
    public function __construct(object $container) {
        $this->container = $container;
    }

    /**
     * Initialize task manager.
     *
     * @since    1.0.0
     * @return   void
     */
    public function init(): void {
        $this->register_tasks();
        $this->schedule_tasks();
    }

    /**
     * Register available tasks.
     *
     * @since    1.0.0
     * @access   private
     * @return   void
     */
    private function register_tasks(): void {
        $this->tasks = array(
            // No scheduled tasks currently registered
        );
    }

    /**
     * Schedule all tasks.
     *
     * @since    1.0.0
     * @access   private
     * @return   void
     */
    private function schedule_tasks(): void {
        foreach ($this->tasks as $key => $task) {
            $this->schedule_task($key, $task);
        }
    }

    /**
     * Schedule a specific task.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $key     Task key.
     * @param    array     $task    Task config.
     * @return   void
     */
    private function schedule_task(string $key, array $task): void {
        // Load task class
        $file_path = SCD_INCLUDES_DIR . 'scheduled-tasks/' . $task['file'];
        if (file_exists($file_path)) {
            require_once $file_path;
        }

        // Schedule with WordPress cron
        if (!wp_next_scheduled($task['hook'])) {
            wp_schedule_event(time(), $task['schedule'], $task['hook']);
        }

        // Add action hook
        add_action($task['hook'], array($this, 'execute_task_' . $key));
    }

    /**
     * Unschedule all tasks.
     *
     * @since    1.0.0
     * @return   void
     */
    public function unschedule_all_tasks(): void {
        foreach ($this->tasks as $task) {
            $timestamp = wp_next_scheduled($task['hook']);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $task['hook']);
            }
        }
    }

    /**
     * Get task status.
     *
     * @since    1.0.0
     * @param    string    $key    Task key.
     * @return   array             Task status.
     */
    public function get_task_status(string $key): array {
        if (!isset($this->tasks[$key])) {
            return array('status' => 'not_found');
        }

        $task = $this->tasks[$key];
        $next_run = wp_next_scheduled($task['hook']);

        return array(
            'status' => $next_run ? 'scheduled' : 'not_scheduled',
            'next_run' => $next_run ? date('Y-m-d H:i:s', $next_run) : null,
            'schedule' => $task['schedule']
        );
    }
}
