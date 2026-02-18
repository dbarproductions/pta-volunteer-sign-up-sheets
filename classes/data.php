<?php
/**
* Database queries and actions
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Data
{
    /**
     * Table definitions and field configurations
     * 
     * This property is kept for backward compatibility with extensions
     * that access table names and field definitions via $pta_sus->data->tables
     * 
     * @var array
     */
    public $tables = array();

    /**
     * Static cache of table structures for use without global $pta_sus.
     * Populated when constructor runs.
     *
     * @var array|null
     */
    private static $tables_cache = null;

    /**
     * Get table structure for an object type (sheet, task, signup).
     * Used by PTA_SUS_Base_Object for backward compatibility with old filter format.
     *
     * @since 6.2.0
     * @param string $object_type One of 'sheet', 'task', 'signup'.
     * @return array Table structure with 'allowed_fields' etc., or empty array.
     */
    public static function get_table_structure( $object_type ) {
        if ( null === self::$tables_cache ) {
            return array();
        }
        return isset( self::$tables_cache[ $object_type ] ) ? self::$tables_cache[ $object_type ] : array();
    }

    /**
     * Constructor
     * 
     * Initializes the tables array with table names and field definitions.
     * This class is kept for backward compatibility - all methods are deprecated.
     * 
     * @since 1.0.0
     */
    public function __construct()
    {
        global $wpdb;
        
        // Set table names
        // Note: Only the tables array is kept for backward compatibility with extensions
        $this->tables = array(
            'sheet' => apply_filters( 'pta_sus_sheet_fields', array(
                'name' => $wpdb->prefix.'pta_sus_sheets',
                'allowed_fields' => array(
                    'title' => 'text',
                    'details' => 'textarea',
                    'first_date' => 'date',
                    'last_date' => 'date',
                    'type' => 'text',
                    'position' => 'text',
                    'chair_name' => 'names',
                    'chair_email' => 'emails',
                    'sus_group' => 'array',
                    'reminder1_days' => 'int',
                    'reminder2_days' => 'int',
                    'clear' => 'bool',
					'clear_type' => 'text',
                    'clear_days' => 'int',
                    'no_signups' => 'bool',
	                'duplicate_times' => 'bool',
                    'visible' => 'bool',
                    'trash' => 'bool',
					'clear_emails' => 'text',
	                'signup_emails' => 'text'
                ),
                'required_fields' => array(
                    'title' => 'Title',
                    'type' => 'Event Type'
                    ),
            )),
            'task' => apply_filters( 'pta_sus_task_fields', array(
                'name' => $wpdb->prefix.'pta_sus_tasks',
                'allowed_fields' => array(
                    'sheet_id' => 'int',
                    'title' => 'text',
                    'description' => 'textarea',
                    'dates' => 'dates',
                    'time_start' => 'time',
                    'time_end' => 'time',
                    'qty' => 'int',
                    'need_details' => 'yesno',
                    'details_required' => 'yesno',
                    'details_text' => 'text',
                    'allow_duplicates' => 'yesno',
                    'enable_quantities' => 'yesno',
                    'position' => 'int',
                ),
                'required_fields' => array(),
            )),
            'signup' => apply_filters( 'pta_sus_signup_fields', array(
                'name' => $wpdb->prefix.'pta_sus_signups',
                'allowed_fields' => array(
                    'task_id' => 'int',
                    'date'  => 'date',
                    'user_id' => 'int',
                    'item' => 'text',
                    'firstname' => 'text',
                    'lastname' => 'text',
                    'email' => 'email',
                    'phone' => 'phone',
                    'reminder1_sent' => 'bool',
                    'reminder2_sent' => 'bool',
                    'item_qty' => 'int',
	                'ts' => 'int',
	                'validated' => 'bool'
                ),
            )),
        );

        self::$tables_cache = $this->tables;
    }

    /**
     * Get all Sheets
     *
     * @deprecated 6.0.0 Use PTA_SUS_Sheet_Functions::get_sheets() instead
     * @param bool $trash Get just trash
     * @param bool $active_only Get only active sheets
     * @return mixed Array of sheets
     */
    public function get_sheets($trash=false, $active_only=false, $show_hidden=false, $order_by='first_date', $order = 'ASC') {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Sheet_Functions::get_sheets() ' . sprintf('Called from %s line %s', $file, $line) );

        return PTA_SUS_Sheet_Functions::get_sheets($trash, $active_only, $show_hidden, $order_by, $order);
    }

    /**
     * Get all sheet IDs and titles
     *
     * @deprecated 6.0.0 Use PTA_SUS_Sheet_Functions::get_sheet_ids_and_titles() instead
     * @param bool $trash Get just trash
     * @param bool $active_only Get only active sheets
     * @param bool $show_hidden Show hidden sheets
     * @return array Array of sheet IDs and titles
     */
    public function get_all_sheet_ids_and_titles($trash = false, $active_only = false, $show_hidden = false) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Sheet_Functions::get_sheet_ids_and_titles() ' . sprintf('Called from %s line %s', $file, $line) );
        return PTA_SUS_Sheet_Functions::get_sheet_ids_and_titles($trash, $active_only, $show_hidden);
    }

	/**
	 * Get single sheet
     * @deprecated 6.0.0 use pta_sus_get_sheet() instead
	 *
	 * @param $id INT
	 *
	 * @return    mixed
	 */
    public function get_sheet($id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'pta_sus_get_sheet() '.sprintf('Called from %s line %s', $file, $line) );
        return pta_sus_get_sheet($id);
    }

	/**
	 * Get number of sheets
	 *
	 * @deprecated 6.0.0 Use PTA_SUS_Sheet_Functions::get_sheet_count() instead
	 * @param bool $trash
	 *
	 * @return int|false
	 */
    public function get_sheet_count($trash=false) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Sheet_Functions::get_sheet_count() ' . sprintf('Called from %s line %s', $file, $line) );
        return PTA_SUS_Sheet_Functions::get_sheet_count($trash);
    }
    
    /**
     * Return # of entries that have matching title and date
     * @deprecated 6.0.0 use PTA_SUS_Sheet_Functions::check_duplicate_sheet() instead
     * @param  string $title sheet title
     * @return false|int      # of matching sheets, or false if none
     */
    public function check_duplicate_sheet($title) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(__FUNCTION__, '6.0.0', 'PTA_SUS_Sheet_Functions::check_duplicate_sheet() ' . sprintf('Called from %s line %s', $file, $line));
        return PTA_SUS_Sheet_Functions::check_duplicate_sheet($title);
    }

	/**
	 * Return # of signups that have matching task_id, and signup names
	 * @deprecated 6.0.0 PTA_SUS_Signup_Functions::check_duplicate_signup()
	 * @param $task_id INT
	 * @param $signup_date string
	 * @param $firstname string
	 * @param $lastname string
	 *
	 * @return mixed
	 */
    public function check_duplicate_signup($task_id, $signup_date, $firstname, $lastname) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Signup_Functions::check_duplicate_signup() '.sprintf('Called from %s line %s', $file, $line) );
        return PTA_SUS_Signup_Functions::check_duplicate_signup($task_id, $signup_date, $firstname, $lastname);
    }

	/**
	 * Check if there is a signup with overlapping time for same volunteer info
	 * @deprecated 6.0.0 PTA_SUS_Signup_Functions::check_duplicate_time_signup()
	 * @param $sheet object
	 * @param $task object
	 * @param $signup_date string
	 * @param $firstname string
	 * @param $lastname string
	 * @param bool $check_all
	 *
	 * @return bool true if duplicate time
	 */
    public function check_duplicate_time_signup($sheet, $task, $signup_date, $firstname, $lastname, $check_all = false) {

        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Signup_Functions::check_duplicate_time_signup() '.sprintf('Called from %s line %s', $file, $line) );
        return PTA_SUS_Signup_Functions::check_duplicate_time_signup($sheet, $task, $signup_date, $firstname, $lastname, $check_all = false);
    }

    /**
     * Toggle sheet visibility
     *
     * @deprecated 6.0.0 Use PTA_SUS_Sheet::toggle_visibility() method instead
     * @param int $id Sheet ID
     * @return mixed
     */
    public function toggle_visibility($id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(__FUNCTION__, '6.0.0', 'PTA_SUS_Sheet::toggle_visibility() method ' . sprintf('Called from %s line %s', $file, $line));

        $sheet = pta_sus_get_sheet($id);
        if ($sheet) {
            return $sheet->toggle_visibility();
        }
        return false;
    }

	/**
	 * Get tasks by sheet
     * @deprecated 6.0.0 use PTA_SUS_Task_Functions::get_tasks() instead
	 *
	 * @param int        id of sheet
	 * @param string $date
	 *
	 * @return    mixed    array of tasks
	 */
    public function get_tasks($sheet_id, $date = '') {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Task_Functions::get_tasks() or $sheet->get_tasks() '.sprintf('Called from %s line %s', $file, $line) );

	    return PTA_SUS_Task_Functions::get_tasks($sheet_id, $date);
    }

	/**
     * Get task IDs by sheet ID and optionally a date
     * @deprecated 6.0.0 use PTA_SUS_Task_Functions::get_task_ids() instead
	 * @param int $sheet_id
	 * @param string $date
	 *
	 * @return array an array of task IDs
	 */
    public function get_task_ids($sheet_id, $date = '') {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Task_Functions::get_task_ids() '.sprintf('Called from %s line %s', $file, $line) );

        return PTA_SUS_Task_Functions::get_task_ids($sheet_id, $date);
	}

    /**
     * Get single task
     * @deprecated 6.0.0 use pta_sus_get_task instead
     * @param     int      task id
     * @return    mixed    single task object
     */
    public function get_task($id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'pta_sus_get_task() '.sprintf('Called from %s line %s', $file, $line) );
        return pta_sus_get_task($id);
    }

    /**
     * Get all task dates for a sheet
     *
     * @deprecated 6.0.0 Use PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet() instead
     * @param int $sheet_id Sheet ID
     * @return array|false Array of dates, or false if no dates found (for backward compatibility)
     */
    public function get_all_task_dates_for_sheet($sheet_id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(__FUNCTION__, '6.0.0', 'PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet() ' . sprintf('Called from %s line %s', $file, $line));

        $result = PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet($sheet_id);
        
        // Maintain backward compatibility - return false if empty (old behavior)
        return !empty($result) ? $result : false;
    }

    /**
     * Move tasks between sheets
     *
     * @deprecated 6.0.0 Use PTA_SUS_Task_Functions::move_tasks() instead
     * @param int $sheet_id Old sheet ID
     * @param int $new_sheet_id New sheet ID
     * @return mixed
     */
    public function move_tasks($sheet_id, $new_sheet_id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(__FUNCTION__, '6.0.0', 'PTA_SUS_Task_Functions::move_tasks() ' . sprintf('Called from %s line %s', $file, $line));

        return PTA_SUS_Task_Functions::move_tasks($sheet_id, $new_sheet_id);
    }

    /**
     * Get signups by task & date
     *
     * @deprecated 6.0.0 Use PTA_SUS_Signup_Functions::get_signups_for_task() instead
     * @param int $task_id ID of task
     * @param string $date Optional date filter
     * @return mixed Array of signups
     */
    public function get_signups($task_id, $date = '') {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(__FUNCTION__, '6.0.0', 'PTA_SUS_Signup_Functions::get_signups_for_task() ' . sprintf('Called from %s line %s', $file, $line));

        return PTA_SUS_Signup_Functions::get_signups_for_task($task_id, $date);
    }

    /**
     * Search signups by name
     *
     * @deprecated 6.0.0 Use PTA_SUS_Signup_Functions::search_signups_by_name() instead
     * @param string $search Search term
     * @return mixed
     */
    public function get_signups2($search = '') {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(__FUNCTION__, '6.0.0', 'PTA_SUS_Signup_Functions::search_signups_by_name() ' . sprintf('Called from %s line %s', $file, $line));

        return PTA_SUS_Signup_Functions::search_signups_by_name($search);
    }

    /**
     * Search WordPress users by name or email
     *
     * @deprecated 6.0.0 Use pta_sus_search_users() instead
     * @param string $search Search term
     * @return mixed
     */
    public function get_users($search = '') {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(__FUNCTION__, '6.0.0', 'pta_sus_search_users() ' . sprintf('Called from %s line %s', $file, $line));

        return pta_sus_search_users($search);
    }

    /**
     * Get volunteer emails for a sheet
     *
     * @deprecated 6.0.0 Use PTA_SUS_Signup_Functions::get_volunteer_emails() instead
     * @param int $sheet_id Sheet ID (0 for all sheets)
     * @return array Array of email addresses
     */
    public function get_volunteer_emails($sheet_id = 0) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Signup_Functions::get_volunteer_emails() ' . sprintf('Called from %s line %s', $file, $line) );

        return PTA_SUS_Signup_Functions::get_volunteer_emails($sheet_id);
    }
    
    /**
     * Get single signup
     *
     * @deprecated 6.0.0 Use pta_sus_get_signup() instead
     * @param int $id Signup ID
     * @return PTA_SUS_Signup|false
     */
    public function get_signup($id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'pta_sus_get_signup() '.sprintf('Called from %s line %s', $file, $line) );
        return pta_sus_get_signup($id);
    }

	/**
	 * Get detailed signup info for a specific signup ID
     * @deprecated 6.0.0 Use PTA_SUS_Signup_Functions::get_detailed_signups() instead, but note it returns array instead of single signup
	 * @param  int $signup_id
	 * @return Mixed Object/false    Returns an object with the detailed signup info
	 */
    public function get_detailed_signup($signup_id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Signup_Functions::get_detailed_signups() ' . sprintf('Called from %s line %s', $file, $line) );

        $signup_id = absint($signup_id);
        if (empty($signup_id)) {
            return false;
        }

        $results = PTA_SUS_Signup_Functions::get_detailed_signups(array('id' => $signup_id));

        if (!empty($results) && isset($results[0])) {
            return $results[0];
        }

        return false;
    }

    /**
     * Get all data -- DEPRECATED: Only used for CRON reminder emails
     * Replaced by PTA_SUS_Signup_Functions::get_signups_needing_reminders()
     *
     * @deprecated 6.0.0 Use PTA_SUS_Signup_Functions::get_signups_needing_reminders() instead
     * @return    mixed    array of signups
     */
    public function get_all_data()
    {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Signup_Functions::get_signups_needing_reminders() ' . sprintf('Called from %s line %s', $file, $line) );

        // Return empty array since this is no longer used
        // The send_reminders() method now uses get_signups_needing_reminders() directly
        return array();
    }

    /**
     * Get all unique dates for tasks for the given sheet id
     *
     * @deprecated 6.0.0 Use PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet() instead
     * @param  integer $id Sheet ID
     * @return mixed   array of all unique dates for a sheet
     */
    public function get_all_task_dates($id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet() ' . sprintf('Called from %s line %s', $file, $line) );

        $result = PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet($id);

        // Maintain backward compatibility - return false if empty (old behavior)
        return !empty($result) ? $result : false;
    }

    /**
     * Get available quantity for a task on a specific date
     *
     * @deprecated 6.0.0 Use PTA_SUS_Task::get_available_spots() instead
     * @param int $task_id Task ID
     * @param string $date Date to check
     * @param int $task_qty Task quantity (kept for backward compatibility, but retrieved from task object)
     * @return int|false Available quantity, or false if none available
     */
    public function get_available_qty($task_id, $date, $task_qty) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Task::get_available_spots() ' . sprintf('Called from %s line %s', $file, $line) );

        $task = pta_sus_get_task($task_id);
        if (!$task) {
            return false;
        }

        // Use the new Task class method
        $available = $task->get_available_spots($date);

        // Maintain exact backward compatibility - return false if 0, otherwise return the number
        return $available > 0 ? $available : false;
    }


	/**
	 * Get number of signups on a specific sheet
	 * Optionally for a specific date
	 * Don't count any signups for past dates
	 * UPDATED in version 1.6 to take into account signup quantities
     *
     * @deprecated 6.0.0 use PTA_SUS_Sheet_Functions::get_sheet_signup_count() instead
	 *
	 * @param int    $id
     * @param string $date
	 *
	 * @return int
	 */
    public function get_sheet_signup_count($id, $date='') {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Sheet_Functions::get_sheet_signup_count() ' . sprintf('Called from %s line %s', $file, $line) );

        return PTA_SUS_Sheet_Functions::get_sheet_signup_count($id, $date);
    }

    /**
     * @deprecated 6.0.0 use PTA_SUS_Sheet_Functions::get_all_signup_ids_for_sheet() instead
     * @param int $id
     * @return array
     */
    public function get_all_signup_ids_for_sheet($id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Sheet_Functions::get_all_signup_ids_for_sheet() ' . sprintf('Called from %s line %s', $file, $line) );

        return PTA_SUS_Sheet_Functions::get_all_signup_ids_for_sheet($id);
    }

    /**
     * Get number of total spots on a specific sheet
     * And optionally for a specific date
     * @param int    sheet id
     * @param string date
     * @deprecated 6.0.0 use PTA_SUS_Sheet_Functions::get_sheet_total_spots() instead
     */
    public function get_sheet_total_spots($id, $date = '') {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(__FUNCTION__, '6.0.0', 'PTA_SUS_Sheet_Functions::get_sheet_total_spots() ' . sprintf('Called from %s line %s', $file, $line));

        return PTA_SUS_Sheet_Functions::get_sheet_total_spots($id, $date);
    }

    /**
     * Get GDPR export items for a user by email
     * Formats signup data for WordPress GDPR export
     *
     * @deprecated 6.0.0 Use PTA_SUS_Signup_Functions::get_gdpr_user_export_items() instead
     * @param string $email User email address
     * @return array Array of export items in WordPress GDPR format
     */
    public function get_gdpr_user_export_items($email) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(__FUNCTION__, '6.0.0', 'PTA_SUS_Signup_Functions::get_gdpr_user_export_items() ' . sprintf('Called from %s line %s', $file, $line));
        
        return PTA_SUS_Signup_Functions::get_gdpr_user_export_items($email);
    }

    /**
     * Delete user signup data for GDPR compliance
     * Deletes all signups for a given email address or user ID
     *
     * @deprecated 6.0.0 Use PTA_SUS_Signup_Functions::gdpr_delete_user_data() instead
     * @param string $email User email address
     * @return int|false Number of rows deleted, or false on failure
     */
    public function gdpr_delete_user_data($email) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(__FUNCTION__, '6.0.0', 'PTA_SUS_Signup_Functions::gdpr_delete_user_data() ' . sprintf('Called from %s line %s', $file, $line));
        
        return PTA_SUS_Signup_Functions::gdpr_delete_user_data($email);
    }

	/**
	 * Get all signups for a sheet
	 *
	 * @deprecated 5.7.0 Use PTA_SUS_Signup_Functions::get_signups() instead
	 * @param int|object $sheet_id Sheet ID or sheet object
	 * @param string $date Optional date filter
	 * @return array Array of signup objects
	 */
	public function get_all_signups_for_sheet($sheet_id, $date='') {
		if(is_object($sheet_id)) {
			$sheet_id = $sheet_id->id;
		}
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function( __FUNCTION__, '5.7.0', 'PTA_SUS_Signup_Functions::get_signups() '.sprintf('Called from %s line %s', $file, $line) );
		$where = array('sheet_id' => absint($sheet_id));
		if(!empty($date)) {
			$where['date'] = $date;
		}
		return PTA_SUS_Signup_Functions::get_signups($where);
	}

    /**
     * Get all the signups for a given user id - currently only used by ALOSB extension
     * Return info on what they signed up for
     *
     * @deprecated 5.7.0 Use PTA_SUS_Signup_Functions::get_detailed_signups() instead
     * @param  int $user_id WordPress user id
     * @param bool $show_expired Show expired signups
     * @return array Returns an array of objects with the user's signup info
     */
    public function get_user_signups($user_id, $show_expired = false) {
		if($user_id < 1) {
			return array();
		}
	    $trace = debug_backtrace();
	    $caller = $trace[1] ?? array();
	    $file = $caller['file'] ?? '';
	    $line = $caller['line'] ?? '';
	    _deprecated_function( __FUNCTION__, '5.7.0', 'PTA_SUS_Signup_Functions::get_detailed_signups() '.sprintf('Called from %s line %s', $file, $line) );
	    $where = array('user_id' => absint($user_id));
        return PTA_SUS_Signup_Functions::get_detailed_signups($where);
    }

	/**
	 * Get signups for a sheet by user name
	 *
	 * @deprecated 5.7.0 Use PTA_SUS_Signup_Functions::get_detailed_signups() instead
	 * @param string $firstname First name
	 * @param string $lastname Last name
	 * @param int $sheet_id Sheet ID
	 * @param string|false $date Optional date filter
	 * @return array Array of signup objects
	 */
	public function get_sheet_signups_by_user_name($firstname, $lastname, $sheet_id, $date = false ) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function( __FUNCTION__, '5.7.0', 'PTA_SUS_Signup_Functions::get_detailed_signups() '.sprintf('Called from %s line %s', $file, $line) );
		$where = array('firstname' => sanitize_text_field($firstname), 'lastname' => sanitize_text_field($lastname), 'sheet_id' => absint($sheet_id));
		if($date) {
			$where['date'] = $date;
		}
        return PTA_SUS_Signup_Functions::get_detailed_signups($where);
	}
	
	/**
	 * Get all signups by user name
	 *
	 * @deprecated 4.7.0 Use PTA_SUS_Signup_Functions::get_detailed_signups() instead
	 * @param string $firstname First name
	 * @param string $lastname Last name
	 * @param string|false $date Optional date filter
	 * @return array Array of signup objects
	 */
	public function get_all_signups_by_user_name($firstname, $lastname, $date = false ) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function( __FUNCTION__, '4.7.0', 'PTA_SUS_Signup_Functions::get_detailed_signups() '.sprintf('Called from %s line %s', $file, $line) );
		$where = array(
			'firstname' => $firstname,
			'lastname' => $lastname,
		);
		if($date) {
			$where['date'] = $date;
		}
        return PTA_SUS_Signup_Functions::get_detailed_signups($where);
	}
	
	/**
	 * Get all signups by email
	 *
	 * @deprecated 4.7.0 Use PTA_SUS_Signup_Functions::get_detailed_signups() instead
	 * @param string $email Email address
	 * @param string|false $date Optional date filter
	 * @return array Array of signup objects
	 */
	public function get_all_signups_by_email($email, $date = false ) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function( __FUNCTION__, '4.7.0', 'PTA_SUS_Signup_Functions::get_detailed_signups() '.sprintf('Called from %s line %s', $file, $line) );
		$where = array(
			'email' => $email
		);
		if($date) {
			$where['date'] = $date;
		}
		return PTA_SUS_Signup_Functions::get_detailed_signups($where);
	}
	
	/**
	 * Get all the signups for all users
	 * Return info on what they signed up for
	 *
	 * @deprecated 4.7.0 Use PTA_SUS_Signup_Functions::get_detailed_signups() instead
	 * @param bool $show_expired true to show expired signups
	 * @return array Returns an array of objects with signup info
	 */
	public function get_all_signups($show_expired = false) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function( __FUNCTION__, '4.7.0', 'PTA_SUS_Signup_Functions::get_detailed_signups()'.sprintf('Called from %s line %s', $file, $line) );
		$where = array();
		return PTA_SUS_Signup_Functions::get_detailed_signups($where,$show_expired);
	}

    /**
     * Format chair names for HTML display
     * 
     * @deprecated 6.0.0 Use pta_sus_get_chair_names_html() instead
     * @param string $names_csv Comma-separated list of names
     * @return string Formatted HTML string with names and separators
     */
    public function get_chair_names_html($names_csv) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(
            __FUNCTION__,
            '6.0.0',
            'pta_sus_get_chair_names_html() ' . sprintf('Called from %s line %s', $file, $line)
        );
        return pta_sus_get_chair_names_html($names_csv);
    }

    
    /**
     * Add a new sheet
     * 
     * @deprecated 6.0.0 Use pta_sus_add_sheet() instead
     * @param    array    array of fields and values to insert
     * @return    mixed    false if insert fails, or number of rows inserted (1) on success
     */
    public function add_sheet($fields) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(
            __FUNCTION__,
            '6.0.0',
            'pta_sus_add_sheet() ' . sprintf('Called from %s line %s', $file, $line)
        );
        
        // Use new function - it returns ID, but old method returned wpdb->insert result
        $sheet_id = pta_sus_add_sheet($fields);
        if ($sheet_id) {
            // Return 1 to match old behavior (wpdb->insert returns number of rows)
            return 1;
        }
        return false;
    }
    
    /**
     * Add a new task
     * 
     * @deprecated 6.0.0 Use pta_sus_add_task() instead
     * @param    array    array of fields and values to insert
     * @param   int     sheet id
     * @param   bool    no signups  whether or not to allow task with 0 qty
     * @return    mixed    false if insert fails, or number of rows inserted (1) on success
     */
    public function add_task($fields, $sheet_id, $no_signups = false) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(
            __FUNCTION__,
            '6.0.0',
            'pta_sus_add_task() ' . sprintf('Called from %s line %s', $file, $line)
        );
        
        // Use new function - it returns ID, but old method returned wpdb->insert result
        $task_id = pta_sus_add_task($fields, $sheet_id, $no_signups);
        if ($task_id) {
            // Return 1 to match old behavior (wpdb->insert returns number of rows)
            return 1;
        }
        return false;
    }
    
    /**
     * Add a new signup to a task
     * 
     * @param   array   array of fields and values to insert
     * @param   int     task id
     * @return  mixed   false if insert fails, the signup id if it succeeds
     */
    /**
     * Add a new signup
     * 
     * @deprecated 6.0.0 Use pta_sus_add_signup() instead
     * @param    array    array of fields and values to insert
     * @param   int     task id
     * @return    mixed    signup ID on success, false on failure
     */
    public function add_signup($fields, $task_id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(
            __FUNCTION__,
            '6.0.0',
            'pta_sus_add_signup() ' . sprintf('Called from %s line %s', $file, $line)
        );
        
        // Use new function - it returns ID directly
        return pta_sus_add_signup($fields, $task_id);
    }
    
    /**
     * Update a sheet
     * 
     * @deprecated 6.0.0 Use pta_sus_update_sheet() instead
     * @param    array     $fields array of fields and values to update (with "sheet_" prefix)
     * @param    int       $id     sheet id
     * @return    mixed    number of rows update or false if fails
     */
    public function update_sheet($fields, $id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(
            __FUNCTION__,
            '6.0.0',
            'pta_sus_update_sheet() ' . sprintf('Called from %s line %s', $file, $line)
        );
        
        // Use new function - it handles prefix cleaning and returns compatible value
        return pta_sus_update_sheet($fields, $id);
    }
    
    /**
     * Update a task
     * 
     * @deprecated 6.0.0 Use pta_sus_update_task() instead
     * @param    array  $fields   array of fields and values to update (with "task_" prefix)
     * @param    int    $id       task id
     * @param    bool   $no_signups Whether to allow task with 0 qty (default: false)
     * @return    mixed    number of rows update or false if fails
     */
    public function update_task($fields, $id, $no_signups = false) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(
            __FUNCTION__,
            '6.0.0',
            'pta_sus_update_task() ' . sprintf('Called from %s line %s', $file, $line)
        );
        
        // Use new function - it handles prefix cleaning and returns compatible value
        return pta_sus_update_task($fields, $id, $no_signups);
    }

    /**
     * Update a signup
     * 
     * @deprecated 6.0.0 Use pta_sus_update_signup() instead
     * @param    array  $fields   array of fields and values to update (with "signup_" prefix)
     * @param    int    $id       signup id
     * @return    mixed    number of rows update or false if fails
     */
    public function update_signup( $fields, $id ) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(
            __FUNCTION__,
            '6.0.0',
            'pta_sus_update_signup() ' . sprintf('Called from %s line %s', $file, $line)
        );
        
        // Use new function - it handles prefix cleaning and returns compatible value
        return pta_sus_update_signup($fields, $id);
    }

	/**
	 * Delete a sheet and all associated tasks and signups
	 *
	 * @deprecated 6.0.0 Use PTA_SUS_Sheet_Functions::delete_sheet() instead
	 * @param int $id Sheet ID
	 * @return bool
	 */
    public function delete_sheet($id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(
            __FUNCTION__,
            '6.0.0',
            'PTA_SUS_Sheet_Functions::delete_sheet() ' . sprintf('Called from %s line %s', $file, $line)
        );

        return PTA_SUS_Sheet_Functions::delete_sheet($id);
    }

    /**
     * Clear all signups for a specific task
     *
     * @deprecated 6.0.0 Use PTA_SUS_Signup_Functions::clear_all_for_task() instead
     * @param int $task_id Task ID
     * @return int|false Number of signups deleted, or false on failure
     */
    public function clear_all_signups_for_task($task_id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(
            __FUNCTION__,
            '6.0.0',
            'PTA_SUS_Signup_Functions::clear_all_for_task() ' . sprintf('Called from %s line %s', $file, $line)
        );

        return PTA_SUS_Signup_Functions::clear_all_for_task($task_id);
    }

	/**
	 * Delete a task
	 *
	 * @deprecated 6.0.0 Use $task->delete() or pta_sus_get_task($id)->delete() instead
	 * @param int     task id
	 *
	 * @return bool|int
	 */
    public function delete_task($id) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Task::delete() ' . sprintf('Called from %s line %s', $file, $line) );
		
		$task = pta_sus_get_task($id);
		if (!$task) {
			return false;
		}
		return $task->delete();
    }
    
    /**
    * Delete a signup
    * 
    * @deprecated 6.0.0 Use pta_sus_delete_signup($id) instead
    * @param    int     $id  Signup ID
     *
     * @return mixed int/false
    */
    public function delete_signup($id) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function( __FUNCTION__, '6.0.0', 'pta_sus_delete_signup() ' . sprintf('Called from %s line %s', $file, $line) );
		
		return pta_sus_delete_signup($id);
    }
	
	/**
	 * Delete expired signups from the database
	 *
	 * @deprecated 6.0.0 Use PTA_SUS_Signup_Functions::delete_expired_signups() instead
	 * @param array $exclude array of ids to NOT delete
	 *
	 * @return false|int
	 */
    public function delete_expired_signups($exclude = array()) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Signup_Functions::delete_expired_signups() ' . sprintf('Called from %s line %s', $file, $line) );
		
		return PTA_SUS_Signup_Functions::delete_expired_signups($exclude);
    }

	/**
	 * Delete expired sheets from the database
	 *
	 * @deprecated 6.0.0 Use PTA_SUS_Sheet_Functions::delete_expired_sheets() instead
	 * @param array $exclude array of ids to NOT delete
	 *
	 * @return int Number of sheets deleted
	 */
	public function delete_expired_sheets($exclude = array()) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Sheet_Functions::delete_expired_sheets() ' . sprintf('Called from %s line %s', $file, $line) );
		
		return PTA_SUS_Sheet_Functions::delete_expired_sheets($exclude);
	}
    
    /**
    * Copy a sheet and all tasks to a new sheet for editing
    *
    * @deprecated 6.0.0 Use PTA_SUS_Sheet_Functions::copy_sheet() instead
    * @param    int  $id   sheet id
     * @return int|false New sheet ID on success, false on failure
    */
    public function copy_sheet($id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Sheet_Functions::copy_sheet() '.sprintf('Called from %s line %s', $file, $line) );
        
        return PTA_SUS_Sheet_Functions::copy_sheet($id);
    }

    /**
     * Copy a sheet and all tasks to a new sheet with new dates, optionally copy signups
     *
     * @deprecated 6.0.0 Use PTA_SUS_Sheet_Functions::copy_sheet_to_new_dates() instead
     * @param    int  $id   sheet id
     * @param   array $task_dates key must be task ID, value is SQL format date
     * @param   array $start_times key must be task ID, value is start time
     * @param   array $end_times key must be task ID, value is end time
     * @param   bool  $copy_signups Whether to copy signups (default: false)
     *
     * @return int|false New sheet ID on success, false on failure
     */
    public function copy_sheet_to_new_dates($id, $task_dates, $start_times, $end_times, $copy_signups = false) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Sheet_Functions::copy_sheet_to_new_dates() ' . sprintf('Called from %s line %s', $file, $line) );
		
		return PTA_SUS_Sheet_Functions::copy_sheet_to_new_dates($id, $task_dates, $start_times, $end_times, $copy_signups);
    }


	/**
	 * Remove prefix from keys of an array and return records that were cleaned
	 *
	 * @deprecated 6.0.0 Use pta_sus_clean_prefixed_array() instead
	 * @param array   input array
	 * @param mixed $prefix
	 *
	 * @return   mixed   records that were cleaned
	 */
    public function clean_array($input=array(), $prefix=false) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function( __FUNCTION__, '6.0.0', 'pta_sus_clean_prefixed_array() ' . sprintf('Called from %s line %s', $file, $line) );
		
		return pta_sus_clean_prefixed_array($input, $prefix);
    }
    
    /**
    * Remove slashes from strings, arrays and objects
    * 
    * @param    mixed   input data
    * @return   mixed   cleaned input data
    */
    public function stripslashes_full($input) {
	    $trace = debug_backtrace();
	    $caller = $trace[1] ?? array();
	    $file = $caller['file'] ?? '';
	    $line = $caller['line'] ?? '';
		_deprecated_function( __FUNCTION__, '4.7.0', 'stripslashes_deep() '.sprintf('Called from %s line %s', $file, $line) );
	    return stripslashes_deep($input);
    }

    /**
     * Validate form post data for sheets or tasks
     *
     * @deprecated 6.0.0 Use PTA_SUS_Sheet_Functions::validate_sheet_fields() or PTA_SUS_Task_Functions::validate_task_fields() instead
     * @param array $fields Form fields (with prefixes)
     * @param string $post_type "sheet" or "task"
     * @return array Array with 'errors' (count) and 'message' (error messages)
     */
    public function validate_post($fields, $post_type="sheet") {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function( __FUNCTION__, '6.0.0', ($post_type === 'sheet' ? 'PTA_SUS_Sheet_Functions::validate_sheet_fields()' : 'PTA_SUS_Task_Functions::validate_task_fields()') . ' ' . sprintf('Called from %s line %s', $file, $line) );
		
		$prefix = ( 'sheet' === $post_type ) ? 'sheet_' : 'task_';
		$clean_fields = pta_sus_clean_prefixed_array($fields, $prefix);
		
		if ($post_type === 'sheet') {
			return PTA_SUS_Sheet_Functions::validate_sheet_fields($clean_fields);
		} else {
			return PTA_SUS_Task_Functions::validate_task_fields($clean_fields);
		}
    }

    /**
     * Sanitize sheet fields from form submission
     *
     * @deprecated 6.0.0 Use pta_sanitize_value() directly for each field instead
     * @param array $clean_fields Array of cleaned fields (after prefix removal)
     * @return array Array of sanitized fields
     */
    public function sanitize_sheet_fields($clean_fields) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function( __FUNCTION__, '6.0.0', 'pta_sanitize_value() ' . sprintf('Called from %s line %s', $file, $line) );
		
		$sanitized_fields = array();
		
		// Get allowed fields from tables structure (for backward compatibility)
		$allowed_fields = isset($this->tables['sheet']['allowed_fields']) ? $this->tables['sheet']['allowed_fields'] : array();
		
		foreach ($allowed_fields as $field => $type) {
			if (isset($clean_fields[$field])) {
				// Map 'type' and 'position' to 'text' for pta_sanitize_value
				$sanitize_type = $type;
				if ($type === 'type' || $type === 'position') {
					$sanitize_type = 'text';
				}
				
				// Use the global pta_sanitize_value function
				$sanitized_value = pta_sanitize_value($clean_fields[$field], $sanitize_type);
				
				// Apply filter for backward compatibility (old filter was called per-field in default case)
				// Filter signature: (value, type) - matches old behavior
				$sanitized_fields[$field] = apply_filters('pta_sus_sanitize_sheet_fields', $sanitized_value, $type);
			}
		}
		
		return $sanitized_fields;
    }

    /**
     * Check if text contains only allowed characters
     *
     * @deprecated 6.0.0 Use pta_sus_check_allowed_text() instead
     * @param string $text Text to check
     * @return bool True if text is clean, false if contains invalid characters
     */
    public function check_allowed_text($text) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(__FUNCTION__, '6.0.0', 'pta_sus_check_allowed_text() ' . sprintf('Called from %s line %s', $file, $line));
        return pta_sus_check_allowed_text($text);
    }

    /**
     * Check if a date is valid in yyyy-mm-dd format
     *
     * @deprecated 6.0.0 Use pta_sus_check_date() instead
     * @param string $date Date to check
     * @return bool True if valid, false if not
     */
    public function check_date($date) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(__FUNCTION__, '6.0.0', 'pta_sus_check_date() ' . sprintf('Called from %s line %s', $file, $line));
        return pta_sus_check_date($date);
    }

    /**
     * Check if string contains only numeric digits
     *
     * @deprecated 6.0.0 Use pta_sus_check_numbers() instead
     * @param string $string String to check
     * @return bool True if only digits, false otherwise
     */
    public function check_numbers($string) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(__FUNCTION__, '6.0.0', 'pta_sus_check_numbers() ' . sprintf('Called from %s line %s', $file, $line));
        return pta_sus_check_numbers($string);
    }

    /**
     * Sanitize comma-separated dates
     *
     * @deprecated 6.0.0 Use pta_sus_sanitize_dates() instead
     * @param string $dates Comma-separated dates
     * @return string Comma-separated valid dates
     */
    public function get_sanitized_dates($dates) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(__FUNCTION__, '6.0.0', 'pta_sus_sanitize_dates ' . sprintf('Called from %s line %s', $file, $line));
        return pta_sanitize_value($dates,'dates');
    }
    
    /**
     * Convert a name string to initials
     *
     * @deprecated 6.0.0 Use pta_sus_get_name_initials() instead
     * @param string $name Name string to convert
     * @return string Initials with periods
     */
    public function initials($name) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function( __FUNCTION__, '6.0.0', 'pta_sus_get_name_initials() ' . sprintf('Called from %s line %s', $file, $line) );
		
		return pta_sus_get_name_initials($name);
    }

    /**
     * Convert a full name to first name plus initials for remaining words
     *
     * @deprecated 6.0.0 Use pta_sus_get_name_with_initials() instead
     * @param string $name Full name string to convert
     * @return string First name followed by initials for remaining words
     */
    public function initials_firstname_complete($name) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function( __FUNCTION__, '6.0.0', 'pta_sus_get_name_with_initials() ' . sprintf('Called from %s line %s', $file, $line) );
		
		return pta_sus_get_name_with_initials($name);
    }
}
/* EOF */
