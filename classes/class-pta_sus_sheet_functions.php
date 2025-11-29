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
    private static $task_table;
    private static $signup_table;

    /**
     * Initialize static properties
     */
    public static function init() {
        global $wpdb;
        self::$sheet_table = $wpdb->prefix . 'pta_sus_sheets';
        self::$task_table = $wpdb->prefix . 'pta_sus_tasks';
        self::$signup_table = $wpdb->prefix . 'pta_sus_signups';
    }

    /**
     * Get all sheets with optional filtering
     * 
     * Backward compatible wrapper that converts parameters to args array
     * and calls get_sheets_by_args()
     *
     * @param bool   $trash       Get trashed sheets (default: false)
     * @param bool   $active_only Get only non-expired sheets (default: false)
     * @param bool   $show_hidden Include hidden sheets (default: false)
     * @param string $order_by    Column to order by (default: 'first_date')
     * @param string $order       Sort order ASC or DESC (default: 'ASC')
     * @return array Array of PTA_SUS_Sheet objects
     */
    public static function get_sheets( $trash = false, $active_only = false, $show_hidden = false, $order_by = 'first_date', $order = 'ASC' ) {
        // Convert parameters to args array for get_sheets_by_args()
        $args = array(
            'trash' => $trash,
            'active_only' => $active_only,
            'show_hidden' => $show_hidden,
            'order_by' => $order_by,
            'order' => $order,
        );
        
        return self::get_sheets_by_args( $args );
    }

    /**
     * Get sheets by flexible arguments array
     * 
     * Supports filtering by author, sus_group, and all standard filters.
     * This is the main method for querying sheets with advanced filtering.
     *
     * @param array $args {
     *     Optional. Array of arguments for filtering sheets.
     *
     *     @type bool        $trash         Get trashed sheets (default: false)
     *     @type bool        $active_only   Get only non-expired sheets (default: false)
     *     @type bool        $show_hidden   Include hidden sheets (default: false)
     *     @type string      $order_by      Column to order by (default: 'first_date')
     *     @type string      $order         Sort order ASC or DESC (default: 'ASC')
     *     @type int|null    $author_id     Filter by author user ID. null = no filter, 0 = no author, >0 = specific author (default: null)
     *     @type string      $author_email  Filter by author email (default: '')
     *     @type string|array $sus_group    Filter by sus_group slug(s) (default: '')
     * }
     * @return array Array of PTA_SUS_Sheet objects
     * @since 6.1.0
     */
    public static function get_sheets_by_args( $args = array() ) {
        global $wpdb;

        // Parse args with defaults
        $defaults = array(
            'trash' => false,
            'active_only' => false,
            'show_hidden' => false,
            'order_by' => 'first_date',
            'order' => 'ASC',
            'author_id' => null,      // null = no filter, 0 = no author, >0 = specific author
            'author_email' => '',     // empty = no filter
            'sus_group' => '',        // empty = no filter, string or array for filtering
        );
        
        $args = wp_parse_args( $args, $defaults );

        // Sanitize and validate order_by
        $order_by = sanitize_key( $args['order_by'] );
        $allowed_order_by = array( 'first_date', 'last_date', 'title', 'id' );
        if ( ! in_array( $order_by, $allowed_order_by ) ) {
            $order_by = 'first_date';
        }

        // Sanitize and validate order
        $order = strtoupper( sanitize_text_field( $args['order'] ) );
        if ( ! in_array( $order, array( 'ASC', 'DESC' ) ) ) {
            $order = 'ASC';
        }

        // Build query
        $sql = "SELECT * FROM " . self::$sheet_table . " WHERE trash = %d";
        $params = array( $args['trash'] ? 1 : 0 );

        // Add active_only filter
        if ( $args['active_only'] ) {
            $sql .= " AND (DATE_ADD(last_date, INTERVAL 1 DAY) >= %s OR last_date = '0000-00-00')";
            $params[] = current_time( 'mysql' );
        }

        // Add visibility filter
        if ( ! $args['show_hidden'] ) {
            $sql .= " AND visible = 1";
        }

        // Add author_id filter
        if ( null !== $args['author_id'] ) {
            $author_id = absint( $args['author_id'] );
            $sql .= " AND author_id = %d";
            $params[] = $author_id;
        }

        // Add author_email filter
        if ( ! empty( $args['author_email'] ) ) {
            $author_email = sanitize_email( $args['author_email'] );
            if ( ! empty( $author_email ) ) {
                $sql .= " AND author_email = %s";
                $params[] = $author_email;
            }
        }

        // Add sus_group filter (if provided)
        // Note: sus_group is stored as serialized array in VARCHAR field
        // For now, we'll do a simple LIKE search - can be enhanced later for better performance
        if ( ! empty( $args['sus_group'] ) ) {
            $sus_groups = is_array( $args['sus_group'] ) ? $args['sus_group'] : array( $args['sus_group'] );
            if ( ! empty( $sus_groups ) ) {
                $group_conditions = array();
                foreach ( $sus_groups as $group ) {
                    $group = sanitize_text_field( $group );
                    if ( ! empty( $group ) ) {
                        // Escape for LIKE query
                        $group_escaped = '%' . $wpdb->esc_like( $group ) . '%';
                        $group_conditions[] = "sus_group LIKE %s";
                        $params[] = $group_escaped;
                    }
                }
                if ( ! empty( $group_conditions ) ) {
                    $sql .= " AND (" . implode( " OR ", $group_conditions ) . ")";
                }
            }
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
     * @param bool|array $trash_or_args If boolean, count trashed or non-trashed sheets.
     *                                   If array, use as args for filtering (supports author filtering).
     * @return int Number of sheets
     */
    public static function get_sheet_count( $trash_or_args = false ) {
        // If $trash_or_args is an array, use it as args for filtering
        if ( is_array( $trash_or_args ) ) {
            $args = $trash_or_args;
            $sheets = self::get_sheets_by_args( $args );
            return count( $sheets );
        }
        
        // Otherwise, use the old boolean behavior for backward compatibility
        $trash = (bool) $trash_or_args;
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

        // Create new sheet from array data
        $sheet_data = $original_sheet->to_array();
        $sheet_data['id'] = 0; // Explicitly set to 0 instead of unsetting
        $sheet_data['title'] = $original_sheet->title . ' Copy';
        $sheet_data['visible'] = false;
        
        // Set author to current user when copying
        $current_user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        $sheet_data['author_id'] = $current_user_id;
        $sheet_data['author_email'] = $current_user->user_email;

        $new_sheet = new PTA_SUS_Sheet($sheet_data);
        $new_sheet_id = $new_sheet->save();
        if (!$new_sheet_id) {
            return false;
        }

        // Copy all tasks
        $tasks = PTA_SUS_Task_Functions::get_tasks($sheet_id);
        $new_tasks = array();

        foreach ($tasks as $original_task) {
            // Create new task from array data
            $task_data = $original_task->to_array();
            $task_data['id'] = 0; // Explicitly set to 0
            $task_data['sheet_id'] = $new_sheet_id;

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

    /**
     * Delete a sheet and all associated tasks and signups
     * Cascading delete - removes signups, then tasks, then the sheet
     *
     * @param int $sheet_id Sheet ID to delete
     * @return bool True on success, false on failure
     */
    public static function delete_sheet($sheet_id) {
        global $wpdb;

        $sheet_id = absint($sheet_id);
        if (empty($sheet_id)) {
            return false;
        }

        // Load sheet to verify it exists and fire action hook
        $sheet = pta_sus_get_sheet($sheet_id);
        if (!$sheet) {
            return false;
        }

        // Fire action hook before deletion
        do_action('pta_sus_before_delete_sheet', $sheet_id, $sheet);

        // Get all tasks for this sheet
        $tasks = PTA_SUS_Task_Functions::get_tasks($sheet_id);

        // Delete all signups for each task
        foreach ($tasks as $task) {
            // Get all signups for this task
            $signups = PTA_SUS_Signup_Functions::get_signups_for_task($task->id);
            
            foreach ($signups as $signup) {
                // Use object's delete method to fire hooks
                if (!$signup->delete()) {
                    return false;
                }
            }
        }

        // Delete all tasks
        foreach ($tasks as $task) {
            // Use object's delete method to fire hooks
            if (!$task->delete()) {
                return false;
            }
        }

        // Finally, delete the sheet itself
        $result = $sheet->delete();

        // Fire action hook after deletion
        if ($result) {
            do_action('pta_sus_deleted_sheet', $sheet_id);
        }

        return (bool) $result;
    }

    /**
     * Get all unique dates for tasks for a given sheet ID
     * Optimized SQL query - only fetches dates field, not full task objects
     *
     * @param int $sheet_id Sheet ID
     * @return array Array of unique, validated, sorted dates (or empty array if no tasks/dates)
     */
    public static function get_all_task_dates_for_sheet($sheet_id) {
        global $wpdb;

        $sheet_id = absint($sheet_id);
        if (empty($sheet_id)) {
            return array();
        }

        // Get all dates fields from tasks for this sheet
        $sql = "SELECT dates FROM " . self::$task_table . " WHERE sheet_id = %d";
        $results = $wpdb->get_col($wpdb->prepare($sql, $sheet_id));

        $dates = array();

        if (empty($results)) {
            return $dates;
        }

        // Process each task's dates (may be comma-separated)
        foreach ($results as $result) {
            if (empty($result)) {
                continue;
            }

            // Use the sanitize function to validate dates (same as old method)
            $task_dates = pta_sus_sanitize_dates($result);

            foreach ($task_dates as $date) {
                // Add to array if not already present
                if (!in_array($date, $dates)) {
                    $dates[] = $date;
                }
            }
        }

        // Sort dates (same as old method)
        sort($dates);

        return $dates;
    }

    /**
     * Checks to see if the sheet title already exists in the database
     * @param $title string
     * @return false|int
     */
    public static function check_duplicate_sheet($title) {
        global $wpdb;
        $title = sanitize_text_field($title);
        if (empty($title)) {
            return false;
        }

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::$sheet_table . " WHERE title = %s AND trash = 0",
            $title
        ));

        return !empty($count) ? absint($count) : false;
    }

    /**
     * @param int $sheet_id
     * @param string $date
     * @return int
     */
    public static function get_sheet_signup_count($sheet_id, $date = '') {
        global $wpdb;
        $sheet_id = absint($sheet_id);
        if (empty($sheet_id)) {
            return 0;
        }

        $signup_table = self::$signup_table;
        $task_table = self::$task_table;
        $now = current_time('mysql');

        $sql = "
        SELECT 
            {$signup_table}.item_qty AS item_qty,
            {$task_table}.enable_quantities AS enable_quantities 
        FROM {$task_table} 
        RIGHT OUTER JOIN {$signup_table} ON {$task_table}.id = {$signup_table}.task_id 
        WHERE {$task_table}.sheet_id = %d 
        AND (%s <= ADDDATE({$signup_table}.date, 1) OR {$signup_table}.date = '0000-00-00') 
    ";

        $prepare_args = array($sheet_id, $now);
        if (!empty($date)) {
            $sql .= " AND {$signup_table}.date = %s";
            $prepare_args[] = $date;
        }

        $results = $wpdb->get_results($wpdb->prepare($sql, ...$prepare_args));

        $count = 0;
        foreach ($results as $result) {
            if ('YES' === $result->enable_quantities) {
                $count += absint($result->item_qty);
            } else {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param int $sheet_id
     * @return array
     */
    public static function get_all_signup_ids_for_sheet($sheet_id) {
        global $wpdb;
        $sheet_id = absint($sheet_id);
        if (empty($sheet_id)) {
            return array();
        }
        $signup_table = self::$signup_table;
        $task_table = self::$task_table;

        $sql = "
        SELECT {$signup_table}.id AS signup_id 
        FROM {$signup_table}
        INNER JOIN {$task_table} ON {$task_table}.id = {$signup_table}.task_id 
        WHERE {$task_table}.sheet_id = %d 
    ";

        $results = $wpdb->get_results($wpdb->prepare($sql, $sheet_id));

        // Return array of IDs
        $ids = array();
        foreach ($results as $result) {
            $ids[] = absint($result->signup_id);
        }

        return $ids;
    }

    /**
     * @param int $sheet_id
     * @param string $date
     * @return float|int
     */
    public static function get_sheet_total_spots($sheet_id, $date = '') {
        $sheet_id = absint($sheet_id);
        if (empty($sheet_id)) {
            return 0;
        }

        $tasks = PTA_SUS_Task_Functions::get_tasks($sheet_id, $date);
        $total_spots = 0;
        $time = current_time('timestamp');

        foreach ($tasks as $task) {
            $task_dates = pta_sus_sanitize_dates($task->dates);
            $good_dates = 0;

            foreach ($task_dates as $tdate) {
                if (!empty($date) && $tdate !== $date) {
                    continue;
                }
                if ('0000-00-00' === $tdate || (strtotime($tdate) >= ($time - (24*60*60)))) {
                    ++$good_dates;
                }
            }
            $total_spots += $good_dates * absint($task->qty);
        }

        return $total_spots;
    }

    /**
     * Copy a sheet and all tasks to a new sheet with new dates, optionally copy signups
     * 
     * This method creates a new sheet based on an existing one, but with new dates/times
     * for the tasks. It's used for rescheduling or creating multiple copies of an event.
     * 
     * @param int $id Original sheet ID to copy
     * @param array $task_dates Array where key is task ID, value is new date (SQL format: Y-m-d)
     * @param array $start_times Array where key is task ID, value is new start time
     * @param array $end_times Array where key is task ID, value is new end time
     * @param bool $copy_signups Whether to copy signups to the new sheet (default: false)
     * @return int|false New sheet ID on success, false on failure
     */
    public static function copy_sheet_to_new_dates($id, $task_dates, $start_times, $end_times, $copy_signups = false) {
        // Validate input
        $id = absint($id);
        if (empty($id) || empty($task_dates)) {
            return false;
        }

        // Calculate new sheet dates from task dates
        $first_date = min($task_dates);
        $last_date = max($task_dates);

        // Load original sheet
        $original_sheet = pta_sus_get_sheet($id);
        if (!$original_sheet) {
            return false;
        }

        // Create new sheet from original sheet data
        $sheet_data = $original_sheet->to_array();
        $sheet_data['id'] = 0; // New sheet
        $sheet_data['first_date'] = $first_date;
        $sheet_data['last_date'] = $last_date;
        $sheet_data['visible'] = false; // Make copied sheets hidden until admin can edit them
        
        // Set author to current user when copying
        $current_user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        $sheet_data['author_id'] = $current_user_id;
        $sheet_data['author_email'] = $current_user->user_email;

        $new_sheet = new PTA_SUS_Sheet($sheet_data);
        $new_sheet_id = $new_sheet->save();
        if (!$new_sheet_id) {
            return false;
        }

        // Get original tasks
        $tasks = PTA_SUS_Task_Functions::get_tasks($id);
        if (empty($tasks)) {
            return false;
        }

        $new_tasks = array();

        // Copy each task with new dates/times
        foreach ($tasks as $original_task) {
            $task_id = absint($original_task->id);
            
            // Skip if no date provided for this task
            if (!isset($task_dates[$task_id])) {
                continue;
            }

            // Create new task from original task data
            $task_data = $original_task->to_array();
            $task_data['id'] = 0; // New task
            $task_data['sheet_id'] = $new_sheet_id;
            $task_data['dates'] = $task_dates[$task_id];
            
            // Update times if provided
            if (isset($start_times[$task_id])) {
                $task_data['time_start'] = $start_times[$task_id];
            }
            if (isset($end_times[$task_id])) {
                $task_data['time_end'] = $end_times[$task_id];
            }

            $new_task = new PTA_SUS_Task($task_data);
            $new_task_id = $new_task->save();
            
            if (!$new_task_id) {
                return false;
            }

            $new_tasks[$task_id] = $new_task_id;
            do_action('pta_sus_task_copied', $task_id, $new_task_id);

            // Optionally copy signups
            if ($copy_signups) {
                $signups = PTA_SUS_Signup_Functions::get_signups_for_task($task_id);
                if (!empty($signups)) {
                    $date = $task_dates[$task_id];
                    foreach ($signups as $signup) {
                        // Create prefixed fields array for pta_sus_add_signup()
                        $signup_data = $signup->to_array();
                        $prefixed_fields = array();
                        
                        // Map signup properties to prefixed fields
                        foreach ($signup_data as $field => $value) {
                            if ('date' === $field) {
                                $prefixed_fields['signup_date'] = $date;
                            } elseif ('task_id' === $field) {
                                // Skip - passed separately to pta_sus_add_signup()
                                continue;
                            } elseif ('item_qty' === $field) {
                                // Ensure qty is at least 1
                                $qty = !empty($value) && absint($value) > 0 ? absint($value) : 1;
                                $prefixed_fields['signup_item_qty'] = $qty;
                            } elseif ('reminder1_sent' === $field || 'reminder2_sent' === $field) {
                                // Reset reminders for copied signups
                                $prefixed_fields['signup_' . $field] = false;
                            } else {
                                $prefixed_fields['signup_' . $field] = $value;
                            }
                        }

                        $new_signup_id = pta_sus_add_signup($prefixed_fields, $new_task_id);
                        if (false === $new_signup_id) {
                            return false;
                        }
                        do_action('pta_sus_signup_copied', $signup->id, $new_signup_id);
                    }
                }
            }
        }

        // Store task mapping for extensions
        if (!empty($new_tasks)) {
            $data = array('sheet_id' => $id, 'tasks' => $new_tasks);
            update_option('pta_sus_copied_tasks', $data);
        }

        do_action('pta_sus_sheet_copied', $id, $new_sheet_id);
        return $new_sheet_id;
    }

    /**
     * Delete expired sheets from the database
     * 
     * Deletes sheets that are older than the configured number of days based on their last_date.
     * Only runs if the admin has enabled automatic clearing in settings.
     * Uses the delete_sheet() method which handles cascading deletes of tasks and signups.
     * 
     * @param array $exclude Array of sheet IDs to NOT delete (allows extensions to protect certain sheets)
     * @return int Number of sheets deleted
     */
    public static function delete_expired_sheets($exclude = array()) {
        global $wpdb;
        
        // Allow extensions to modify the exclusion list
        $exclude = apply_filters('pta_sus_delete_expired_sheets_exclusions', $exclude);
        
        // Get main options (not stored in this class, so get it directly)
        $main_options = get_option('pta_volunteer_sus_main_options', array());
        
        // Get number of days from options (default to 1 if not set)
        $num_days = !empty($main_options['num_days_expired']) ? absint($main_options['num_days_expired']) : 1;
        if ($num_days < 1) {
            $num_days = 1;
        }
        
        $sheet_table = self::$sheet_table;
        $now = current_time('mysql');
        
        // Build the SQL query to get expired sheet IDs
        // Note: Using ADDDATE to calculate expiration date based on last_date
        $sql = "SELECT id FROM {$sheet_table} WHERE %s > ADDDATE(last_date, %d)";
        
        // Add exclusions if provided
        if (!empty($exclude)) {
            // Sanitize all IDs to integers (safe for IN clause)
            $clean_ids = array_map('absint', $exclude);
            $clean_ids = array_filter($clean_ids); // Remove any zeros
            if (!empty($clean_ids)) {
                $exclusions = implode(',', $clean_ids);
                $sql .= " AND id NOT IN ($exclusions)";
            }
        }
        
        // Prepare the query (date and num_days are the placeholders)
        $safe_sql = $wpdb->prepare($sql, $now, $num_days);
        
        // Get the sheet IDs that need to be deleted
        $ids = $wpdb->get_col($safe_sql);
        
        $deleted = 0;
        if (!empty($ids)) {
            // Delete each sheet (this will cascade delete tasks and signups)
            foreach ($ids as $id) {
                if (self::delete_sheet($id)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }

    /**
     * Validate sheet fields from form submission
     * Validates required fields and field types based on property definitions
     * Adds error messages directly to PTA_SUS_Messages class
     *
     * @param array $clean_fields Array of cleaned fields (after prefix removal)
     * @return array Array with 'errors' (count) and 'message' (empty string - messages added directly to PTA_SUS_Messages)
     */
    public static function validate_sheet_fields($clean_fields) {
        return PTA_SUS_Validation::validate_object_fields($clean_fields, 'sheet');
    }

    /**
     * Reschedule a sheet to new dates/times
     * Updates tasks, sheet dates, and optionally updates signup dates or clears signups
     *
     * @param int $sheet_id Sheet ID to reschedule
     * @param array $new_dates Array of task_id => new_date mappings
     * @param array $new_start_times Array of task_id => new_start_time mappings
     * @param array $new_end_times Array of task_id => new_end_time mappings
     * @param string $new_date Single date for Single sheet type
     * @param bool $clear_signups Whether to clear all signups (default: false)
     * @return bool True on success, false on failure
     */
    public static function reschedule_sheet($sheet_id, $new_dates, $new_start_times, $new_end_times, $new_date = '', $clear_signups = false) {
        $sheet_id = absint($sheet_id);
        if ($sheet_id < 1) {
            return false;
        }

        $sheet = pta_sus_get_sheet($sheet_id);
        if (!$sheet) {
            return false;
        }

        $tasks = PTA_SUS_Task_Functions::get_tasks($sheet_id);
        if (empty($tasks)) {
            return false;
        }

        // Update tasks
        foreach($tasks as $task_obj) {
            $id = absint($task_obj->id);
            $task = pta_sus_get_task($id);
            if ($task) {
                if (isset($new_dates[$id])) {
                    $task->dates = $new_dates[$id];
                }
                if (isset($new_start_times[$id])) {
                    $task->time_start = $new_start_times[$id];
                }
                if (isset($new_end_times[$id])) {
                    $task->time_end = $new_end_times[$id];
                }
                $task->save();
            }
        }

        // Update sheet dates
        $sheet = pta_sus_get_sheet($sheet_id);
        if ($sheet) {
            if('Single' === $sheet->type && !empty($new_date)) {
                $sheet->first_date = $new_date;
                $sheet->last_date = $new_date;
            } elseif (!empty($new_dates)) {
                $sheet->first_date = min($new_dates);
                $sheet->last_date = max($new_dates);
            }
            $sheet->save();
        }

        // Update signup dates if not clearing
        if(!$clear_signups) {
            // update dates for signups - reset reminder flags
            foreach ($tasks AS $task_obj) {
                $id = absint($task_obj->id);
                if('Single' === $sheet->type && !empty($new_date)) {
                    $date = $new_date;
                } elseif (isset($new_dates[$id])) {
                    $date = $new_dates[$id];
                } else {
                    continue; // Skip if no date for this task
                }
                $signups = PTA_SUS_Signup_Functions::get_signups_for_task($id);
                if(empty($signups)) continue;
                foreach($signups as $signup_obj) {
                    $signup = pta_sus_get_signup($signup_obj->id);
                    if ($signup) {
                        $signup->date = $date;
                        $signup->reminder1_sent = false;
                        $signup->reminder2_sent = false;
                        $signup->save();
                    }
                }
            }
        }

        // Maybe clear Signups
        if($clear_signups) {
            // allow extensions to clear data first before signups are deleted
            do_action('pta_sus_clear_all_signups_for_sheet', $sheet_id);
            foreach ($tasks AS $task) {
                $id = absint($task->id);
                PTA_SUS_Signup_Functions::clear_all_for_task($id);
            }
        }

        do_action( 'pta_sus_sheet_rescheduled', $sheet_id);
        return true;
    }

    /**
     * Create multiple copies of a sheet with date offsets
     * Creates multiple copies of a sheet, each offset by a specified number of days
     *
     * @param int $sheet_id Original sheet ID
     * @param array $tasks Array of PTA_SUS_Task objects from the original sheet
     * @param int $interval Number of days between copies
     * @param int $copies Number of copies to create
     * @param array $new_start_times Array of task_id => new_start_time mappings
     * @param array $new_end_times Array of task_id => new_end_time mappings
     * @param bool $copy_signups Whether to copy signups to new sheets (default: true)
     * @return array Array of new sheet IDs created
     */
    public static function multi_copy_sheet($sheet_id, $tasks, $interval, $copies, $new_start_times, $new_end_times, $copy_signups = true) {
        $sheet_id = absint($sheet_id);
        $interval = absint($interval);
        $copies = absint($copies);
        
        if ($sheet_id < 1 || $interval < 1 || $copies < 1) {
            return array();
        }

        $new_sheet_ids = array();
        $new_dates = array();
        $offset = $interval * 86400; // timestamp value of interval in days

        for($i = 1; $i <= $copies; $i++) {
            // loop through tasks and set new dates
            foreach($tasks as $task) {
                $id = absint($task->id);
                if(1 == $i) {
                    $task_date = strtotime($task->dates);
                    $new_dates[$id] = date('Y-m-d', $task_date + $offset);
                } else {
                    $new_date = date('Y-m-d', strtotime($new_dates[$id]) + $offset);
                    $new_dates[$id] = $new_date;
                }
            }
            $new_sheet_id = self::copy_sheet_to_new_dates($sheet_id, $new_dates, $new_start_times, $new_end_times, $copy_signups);
            if ($new_sheet_id) {
                $new_sheet_ids[] = $new_sheet_id;
            }
        }

        return $new_sheet_ids;
    }
}

// Initialize the class
PTA_SUS_Sheet_Functions::init();