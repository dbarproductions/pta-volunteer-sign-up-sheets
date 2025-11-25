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

        // Create new sheet from array data
        $sheet_data = $original_sheet->to_array();
        $sheet_data['id'] = 0; // Explicitly set to 0 instead of unsetting
        $sheet_data['title'] = $original_sheet->title . ' Copy';
        $sheet_data['visible'] = false;

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
        $results = array(
            'errors' => 0,
            'message' => '', // Empty - messages are added directly to PTA_SUS_Messages
        );

        // Create a temporary sheet instance to get property definitions and required fields
        $sheet = new PTA_SUS_Sheet();
        
        // Get required fields using reflection to access protected method
        $reflection = new ReflectionClass($sheet);
        $get_required_method = $reflection->getMethod('get_required_fields');
        // setAccessible() is only needed for PHP < 8.0 (deprecated in PHP 8.5)
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $get_required_method->setAccessible(true);
        }
        $required_fields = $get_required_method->invoke($sheet);
        
        // Get property definitions
        $get_properties_method = $reflection->getMethod('get_property_definitions');
        // setAccessible() is only needed for PHP < 8.0 (deprecated in PHP 8.5)
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $get_properties_method->setAccessible(true);
        }
        $property_definitions = $get_properties_method->invoke($sheet);

        // Check Required Fields first
        foreach ($required_fields as $required_field => $label) {
            if (empty($clean_fields[$required_field])) {
                $results['errors']++;
                $error_message = sprintf(__('%s is a required field.', 'pta-volunteer-sign-up-sheets'), $label);
                PTA_SUS_Messages::add_error($error_message);
            }
        }

        // Validate field types
        foreach ($property_definitions as $field => $type) {
            if (!empty($clean_fields[$field])) {
                $validation_result = self::validate_field_by_type($clean_fields[$field], $type, $field);
                if ($validation_result['error']) {
                    $results['errors']++;
                    PTA_SUS_Messages::add_error($validation_result['message']);
                }
            }
        }

        /**
         * Filter validation results to allow extensions to add custom validation
         * 
         * Note: Extensions should use PTA_SUS_Messages::add_error() directly in this filter
         * for best results. Modifying $results['message'] is still supported for backward
         * compatibility, but messages should be added via PTA_SUS_Messages to avoid duplicates.
         *
         * @param array $results Validation results array
         * @param array $clean_fields Cleaned fields being validated
         */
        return apply_filters('pta_sus_validate_sheet_fields', $results, $clean_fields);
    }

    /**
     * Validate a single field based on its type
     * Helper method used by validate_sheet_fields and validate_task_fields
     *
     * @param mixed $value Field value to validate
     * @param string $type Field type (text, email, date, etc.)
     * @param string $field_name Field name (for error messages)
     * @return array Array with 'error' (bool) and 'message' (string)
     */
    public static function validate_field_by_type($value, $type, $field_name) {
        $result = array(
            'error' => false,
            'message' => '',
        );

        switch ($type) {
            case 'text':
            case 'names':
                if (!pta_sus_check_allowed_text($value)) {
                    $result['error'] = true;
                    $result['message'] = sprintf(__('Invalid characters in %s field.', 'pta-volunteer-sign-up-sheets'), $field_name);
                }
                break;

            case 'textarea':
                // For now, we allow everything in text area, but it is escaped before display on admin side,
                // using wp_kses_post on public side to sanitize
                // need to sanitize before saving to database
                break;

            case 'emails':
                // Validate one or more emails that will be separated by commas
                // First, get rid of any spaces
                $emails_field = str_replace(' ', '', $value);
                // Then, separate out the emails into a simple data array, using comma as separator
                $emails = explode(',', $emails_field);

                foreach ($emails as $email) {
                    if (!is_email($email)) {
                        $result['error'] = true;
                        $result['message'] = __('Invalid email.', 'pta-volunteer-sign-up-sheets');
                        break; // Stop on first invalid email
                    }
                }
                break;

            case 'date':
                if (!pta_sus_check_date($value)) {
                    $result['error'] = true;
                    $result['message'] = __('Invalid date.', 'pta-volunteer-sign-up-sheets');
                }
                break;

            case 'dates':
                // Validate one or more dates that will be separated by commas
                // Format for each date should be yyyy-mm-dd
                // First, get rid of any spaces
                $dates_field = str_replace(' ', '', $value);
                // Then, separate out the dates into a simple data array, using comma as separator
                $dates = explode(',', $dates_field);
                foreach ($dates as $date) {
                    if (!pta_sus_check_date($date)) {
                        $result['error'] = true;
                        $result['message'] = __('Invalid date.', 'pta-volunteer-sign-up-sheets');
                        break; // Stop on first invalid date
                    }
                }
                break;

            case 'int':
                // Validate input is only numbers
                if (!pta_sus_check_numbers($value)) {
                    $result['error'] = true;
                    $result['message'] = sprintf(__('Numbers only for %s please!', 'pta-volunteer-sign-up-sheets'), $field_name);
                }
                break;

            case 'yesno':
                if ("YES" != $value && "NO" != $value) {
                    $result['error'] = true;
                    $result['message'] = sprintf(__('YES or NO only for %s please!', 'pta-volunteer-sign-up-sheets'), $field_name);
                }
                break;

            case 'bool':
                if ("1" != $value && "0" != $value) {
                    $result['error'] = true;
                    $result['message'] = sprintf(__('Invalid Value for %s', 'pta-volunteer-sign-up-sheets'), $field_name);
                }
                break;

            case 'time':
                $pattern = '/^(?:0[1-9]|1[0-2]):[0-5][0-9] (am|pm|AM|PM)$/';
                if (!preg_match($pattern, $value)) {
                    $result['error'] = true;
                    $result['message'] = sprintf(__('Invalid time format for %s', 'pta-volunteer-sign-up-sheets'), $field_name);
                }
                break;

            default:
                // Allow extensions to validate custom field types
                $filtered = apply_filters('pta_sus_validate_custom_field_type', $result, $value, $type, $field_name);
                if (isset($filtered['error']) && $filtered['error']) {
                    $result = $filtered;
                }
                break;
        }

        return $result;
    }
}

// Initialize the class
PTA_SUS_Sheet_Functions::init();