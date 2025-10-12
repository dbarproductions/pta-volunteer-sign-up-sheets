<?php
/**
 * Sheet Functions Helper Class
 * Static helper methods for working with multiple sheets
 *
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTA_SUS_Sheet_Functions {

    private static $sheet_table;

    /**
     * Initialize static properties
     */
    public static function init() {
        global $wpdb;
        self::$sheet_table = $wpdb->prefix . 'pta_sus_sheets';
    }

    /**
     * Get all sheets with optional filtering
     *
     * @param bool   $trash       Get trashed sheets (default: false)
     * @param bool   $active_only Get only non-expired sheets (default: false)
     * @param bool   $show_hidden Include hidden sheets (default: false)
     * @param string $order_by    Column to order by (default: 'first_date')
     * @param string $order       Sort order ASC or DESC (default: 'ASC')
     * @return array Array of PTA_SUS_Sheet objects
     */
    public static function get_sheets( $trash = false, $active_only = false, $show_hidden = false, $order_by = 'first_date', $order = 'ASC' ) {
        global $wpdb;

        // Sanitize and validate order_by
        $order_by = sanitize_key( $order_by );
        $allowed_order_by = array( 'first_date', 'last_date', 'title', 'id' );
        if ( ! in_array( $order_by, $allowed_order_by ) ) {
            $order_by = 'first_date';
        }

        // Sanitize and validate order
        $order = strtoupper( sanitize_text_field( $order ) );
        if ( ! in_array( $order, array( 'ASC', 'DESC' ) ) ) {
            $order = 'ASC';
        }

        // Build query
        $sql = "SELECT * FROM " . self::$sheet_table . " WHERE trash = %d";
        $params = array( $trash ? 1 : 0 );

        // Add active_only filter
        if ( $active_only ) {
            $sql .= " AND (DATE_ADD(last_date, INTERVAL 1 DAY) >= %s OR last_date = '0000-00-00')";
            $params[] = current_time( 'mysql' );
        }

        // Add visibility filter
        if ( ! $show_hidden ) {
            $sql .= " AND visible = 1";
        }

        // Add ordering - safe to use variables since we validated them
        $sql .= " ORDER BY {$order_by} {$order}, id DESC";

        // Execute query
        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        // Convert to PTA_SUS_Sheet objects
        $sheets = array();
        foreach ( $results as $row ) {
            $sheet = new PTA_SUS_Sheet( $row );

            // On public side, filter out sheets with no tasks
            if ( ! is_admin() ) {
                $tasks = $sheet->get_tasks();
                if ( empty( $tasks ) ) {
                    continue; // Skip this sheet
                }
            }

            $sheets[] = $sheet;
        }

        return $sheets;
    }

    /**
     * Get all sheet IDs and titles
     * Useful for dropdown menus and selects
     *
     * @param bool $trash       Include trashed sheets
     * @param bool $active_only Only active sheets
     * @param bool $show_hidden Include hidden sheets
     * @return array Associative array of id => title
     */
    public static function get_sheet_ids_and_titles( $trash = false, $active_only = false, $show_hidden = false ) {
        $sheets = self::get_sheets( $trash, $active_only, $show_hidden );
        $return_array = array();

        foreach ( $sheets as $sheet ) {
            $return_array[ $sheet->id ] = $sheet->title;
        }

        return $return_array;
    }

    /**
     * Get sheet count
     *
     * @param bool $trash Count trashed or non-trashed sheets
     * @return int Number of sheets
     */
    public static function get_sheet_count( $trash = false ) {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . self::$sheet_table . " WHERE trash = %d",
                $trash ? 1 : 0
            )
        );

        return absint( $count );
    }

    /**
     * Copy a sheet and all its tasks to a new sheet
     *
     * @param int $sheet_id Sheet ID to copy
     * @return int|false New sheet ID on success, false on failure
     */
    public static function copy_sheet($sheet_id) {
        // Load original sheet
        $original_sheet = pta_sus_get_sheet($sheet_id);
        if (!$original_sheet) {
            return false;
        }

        // Get sheet data and modify for copy
        $sheet_data = $original_sheet->to_array();
        unset($sheet_data['id']); // Remove ID so new one is created
        $sheet_data['title'] .= ' Copy';
        $sheet_data['visible'] = false;

        // Create and save new sheet
        $new_sheet = new PTA_SUS_Sheet($sheet_data);
        $new_sheet_id = $new_sheet->save();
        if (!$new_sheet_id) {
            return false;
        }

        // Copy all tasks
        $tasks = PTA_SUS_Task_Functions::get_tasks($sheet_id);
        $new_tasks = array();

        foreach ($tasks as $original_task) {
            // Get task data and modify for copy
            $task_data = $original_task->to_array();
            unset($task_data['id']); // Remove ID
            $task_data['sheet_id'] = $new_sheet_id; // Link to new sheet

            // Create and save new task
            $new_task = new PTA_SUS_Task($task_data);
            $new_task_id = $new_task->save();

            if ($new_task_id) {
                $new_tasks[$original_task->id] = $new_task_id;
                do_action('pta_sus_task_copied', $original_task->id, $new_task_id);
            }
        }

        // Store task mapping
        if (!empty($new_tasks)) {
            update_option('pta_sus_copied_tasks', array(
                'sheet_id' => $sheet_id,
                'tasks' => $new_tasks
            ));
        }

        do_action('pta_sus_sheet_copied', $sheet_id, $new_sheet_id);

        return $new_sheet_id;
    }
}

// Initialize the class
PTA_SUS_Sheet_Functions::init();