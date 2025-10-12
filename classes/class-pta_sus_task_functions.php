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


}

// Initialize the class
PTA_SUS_Task_Functions::init();