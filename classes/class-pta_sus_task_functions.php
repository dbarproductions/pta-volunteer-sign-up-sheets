<?php
/**
 * Task Functions Helper Class
 * Static helper methods for working with multiple tasks
 *
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTA_SUS_Task_Functions {

    private static $task_table;

    /**
     * Initialize static properties
     */
    public static function init() {
        global $wpdb;
        self::$task_table = $wpdb->prefix . 'pta_sus_tasks';
    }

    /**
     * Get tasks by sheet ID and optionally a date
     *
     * @param int $sheet_id       id of sheet
     * @param string $date  optional date
     *
     * @return    array    array of tasks
     */
    public static function get_tasks($sheet_id, $date='') {
        global $wpdb;
        $SQL = "SELECT * FROM ".self::$task_table." WHERE sheet_id = %d ";
        if ('' !== $date ) {
            $SQL .= "AND INSTR(`dates`, %s) > 0 ";
        }
        $SQL .= "ORDER BY position, id";
        if ('' !== $date ) {
            $results = $wpdb->get_results($wpdb->prepare($SQL, $sheet_id, $date));
        } else {
            $results = $wpdb->get_results($wpdb->prepare($SQL, $sheet_id));
        }
        $tasks = array();
        foreach ( $results as $row ) {
            $task = new PTA_SUS_Task($row);
            $tasks[] = $task;
        }
        return $tasks;
    }

    /**
     * Get an array of task IDs for a sheet ID and optionally a date
     *
     * @param int $sheet_id
     * @param string $date
     *
     * @return array an array of task IDs
     */
    public static function get_task_ids($sheet_id, $date='') {
        global $wpdb;
        $SQL = "SELECT id FROM ".self::$task_table." WHERE sheet_id = %d ";
        if ('' !== $date ) {
            $SQL .= "AND INSTR(`dates`, %s) > 0 ";
        }
        $SQL .= "ORDER BY position, id";
        if ('' !== $date ) {
            $results = $wpdb->get_col($wpdb->prepare($SQL, $sheet_id, $date));
        } else {
            $results = $wpdb->get_col($wpdb->prepare($SQL, $sheet_id));
        }

        return $results;
    }

    /**
     * Move all tasks from one sheet to another
     * WARNING: Does not check for existing signups or sheet type compatibility
     *
     * @param int $old_sheet_id Source sheet ID
     * @param int $new_sheet_id Destination sheet ID
     * @return int|false Number of tasks moved, or false on failure
     */
    public static function move_tasks($old_sheet_id, $new_sheet_id) {
        global $wpdb;

        $old_sheet_id = absint($old_sheet_id);
        $new_sheet_id = absint($new_sheet_id);

        if (empty($old_sheet_id) || empty($new_sheet_id) || $old_sheet_id === $new_sheet_id) {
            PTA_SUS_Messages::add_error(__('Invalid sheet IDs provided.', 'pta-volunteer-sign-up-sheets'));
            return false;
        }

        // Get tasks to be moved
        $tasks = self::get_tasks($old_sheet_id);
        if (empty($tasks)) {
            PTA_SUS_Messages::add_error(__('No tasks found to move.', 'pta-volunteer-sign-up-sheets'));
            return false;
        }

        // Check for existing signups
        $has_signups = false;
        foreach ($tasks as $task) {
            $signups = PTA_SUS_Signup_Functions::get_signups_for_task($task->id);
            if (!empty($signups)) {
                $has_signups = true;
                break;
            }
        }

        // Get sheet info for type checking
        $old_sheet = pta_sus_get_sheet($old_sheet_id);
        $new_sheet = pta_sus_get_sheet($new_sheet_id);

        if (!$old_sheet || !$new_sheet) {
            PTA_SUS_Messages::add_error(__('One or both sheets not found.', 'pta-volunteer-sign-up-sheets'));
            return false;
        }

        // Add warnings if needed
        if ($has_signups) {
            PTA_SUS_Messages::add_error(__('WARNING: Tasks being moved have existing signups. Signups will remain associated with moved tasks.', 'pta-volunteer-sign-up-sheets'));
        }
        if ($old_sheet->type !== $new_sheet->type) {
            PTA_SUS_Messages::add_error(sprintf(__('WARNING: Sheet types differ (%s → %s). This may cause issues with date handling.', 'pta-volunteer-sign-up-sheets'), $old_sheet->type, $new_sheet->type));
        }

        // Perform the move
        $sql = "UPDATE " . self::$task_table . " SET sheet_id = %d WHERE sheet_id = %d";
        $result = $wpdb->query($wpdb->prepare($sql, $new_sheet_id, $old_sheet_id));

        if ($result === false) {
            PTA_SUS_Messages::add_error(__('Failed to move tasks.', 'pta-volunteer-sign-up-sheets'));
            return false;
        }

        if ($result > 0) {
            /**
             * Action after tasks are moved
             *
             * @param int $old_sheet_id Original sheet ID
             * @param int $new_sheet_id New sheet ID
             * @param int $moved_count Number of tasks moved
             */
            do_action('pta_sus_tasks_moved', $old_sheet_id, $new_sheet_id, $result);
        }

        return $result;
    }

    /**
     * Validate task fields from form submission
     * Validates required fields and field types based on property definitions
     * Adds error messages directly to PTA_SUS_Messages class
     *
     * @param array $clean_fields Array of cleaned fields (after prefix removal)
     * @return array Array with 'errors' (count) and 'message' (empty string - messages added directly to PTA_SUS_Messages)
     */
    public static function validate_task_fields($clean_fields) {
        return PTA_SUS_Validation::validate_object_fields($clean_fields, 'task');
    }

}

// Initialize the class
PTA_SUS_Task_Functions::init();