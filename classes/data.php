<?php
/**
* Database queries and actions
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Data
{
    
    public $wpdb;
    public $tables = array();
    public $now;
    public $time;
	public $main_options;
    
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->now = current_time( 'mysql' );
        $this->time = current_time( 'timestamp' );

		$this->main_options =get_option( 'pta_volunteer_sus_main_options', array() );
        
        // Set table names
        $this->tables = array(
            'sheet' => apply_filters( 'pta_sus_sheet_fields', array(
                'name' => $this->wpdb->prefix.'pta_sus_sheets',
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
                'name' => $this->wpdb->prefix.'pta_sus_tasks',
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
                'name' => $this->wpdb->prefix.'pta_sus_signups',
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
     * @return array Array of dates
     */
    public function get_all_task_dates_for_sheet($sheet_id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(__FUNCTION__, '6.0.0', 'PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet() ' . sprintf('Called from %s line %s', $file, $line));

        return PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet($sheet_id);
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
     * @param    int        sheet id
     * @param    array     array of fields and values to update
     * @return    mixed    number of rows update or false if fails
     */
    public function update_sheet($fields, $id) {
        $clean_fields = $this->clean_array($fields, 'sheet_');
        $clean_fields = array_intersect_key($clean_fields, $this->tables['sheet']['allowed_fields']);
        if (isset($clean_fields['date']) && $clean_fields['date'] != '0000-00-00') $clean_fields['date'] = date('Y-m-d', strtotime($clean_fields['date']));
        $sanitized_fields = $this->sanitize_sheet_fields($clean_fields);
        // wpdb->update does all necessary sanitation before updating the database
        return $this->wpdb->update($this->tables['sheet']['name'], $sanitized_fields, array('id' => $id), null, array('%d'));
    }
    
    /**
     * Update a task
     * 
     * @param    int    $id    task id
     * @param    array  $fields   array of fields and values to update
     * @param   bool $no_signups
     * @return    mixed    number of rows update or false if fails
     */
    public function update_task($fields, $id, $no_signups = false) {
        $clean_fields = $this->clean_array($fields, 'task_');
        $clean_fields = array_intersect_key($clean_fields, $this->tables['task']['allowed_fields']);
        if (!$no_signups && isset($clean_fields['qty']) && $clean_fields['qty'] < 2 ) {
            $clean_fields['qty'] = 1;
        }
        // wpdb->update does all necessary sanitation before updating the database
        return $this->wpdb->update($this->tables['task']['name'], $clean_fields, array('id' => $id), null, array('%d'));
    }

    /**
     * Update a signup
     * 
     * @param    int   $id     signup id
     * @param    array  $fields   array of fields and values to update
     * @return    mixed    number of rows update or false if fails
     */
    public function update_signup( $fields, $id ) {
        $clean_fields = $this->clean_array($fields, 'signup_');
        $clean_fields = array_intersect_key($clean_fields, $this->tables['signup']['allowed_fields']);
        // wpdb->update does all necessary sanitation before updating the database
        return $this->wpdb->update($this->tables['signup']['name'], $clean_fields, array('id' => $id), null, array('%d'));
    }

	/**
	 * Delete a sheet and all associated tasks and signups
	 *
	 * @param int     sheet id
	 *
	 * @return bool
	 */
    public function delete_sheet($id) {
        $tasks = PTA_SUS_Task_Functions::get_tasks($id);
        $where_format = array('%d');
        foreach ($tasks AS $task) {
            // Delete Signups
	        $where = array('task_id' => $task->id);
	        if(false === $this->wpdb->delete($this->tables['signup']['name'], $where, $where_format)) {
	        	return false;
	        }
        }
        // Delete Tasks
	    $where = array('sheet_id' => $id);
        if(false === $this->wpdb->delete($this->tables['task']['name'], $where, $where_format)) {
            return false;
        }
        // Delete Sheet
	    $where = array('id' => $id);
        if(false === $this->wpdb->delete($this->tables['sheet']['name'], $where, $where_format)) {
            return false;
        }
        return true;
    }

    public function clear_all_signups_for_task($task_id) {
        $where = array('task_id' => $task_id);
        $where_format = array('%d');
        return $this->wpdb->delete($this->tables['signup']['name'], $where, $where_format);
    }

	/**
	 * Delete a task
	 *
	 * @param int     task id
	 *
	 * @return bool|int
	 */
    public function delete_task($id) {
    	$where = array('id' => $id);
    	$where_format = array('%d');
        return $this->wpdb->delete($this->tables['task']['name'],$where,$where_format);
    }
    
    /**
    * Delete a signup
    * 
    * @param    int     $id  Signup ID
     *
     * @return mixed int/false
    */
    public function delete_signup($id) {
    	$where = array('id' => $id);
    	$where_format = array('%d');
        return $this->wpdb->delete($this->tables['signup']['name'],$where,$where_format);
    }
	
	/**
	 * @param array $exclude array of ids to NOT delete
	 *
	 * @return false|int
	 */
    public function delete_expired_signups($exclude = array()) {
    	$exclude = apply_filters('pta_sus_delete_expired_signups_exclusions', $exclude);
	    $num_days = !empty($this->main_options['num_days_expired']) ? absint($this->main_options['num_days_expired']) : 1;
	    if($num_days < 1) {
		    $num_days = 1;
	    }
	    $sql = "DELETE FROM ".$this->tables['signup']['name']." WHERE %s > ADDDATE(date, %d)";
    	if(!empty($exclude)) {
    		$clean_ids = array_map('absint', $exclude);
    		$exclusions = implode(',', $clean_ids);
		    $sql .= " AND id NOT IN ($exclusions)";
	    }
	    $safe_sql = $this->wpdb->prepare($sql, $this->now, $num_days);
        return $this->wpdb->query($safe_sql);
    }

	public function delete_expired_sheets($exclude = array()) {
		$exclude = apply_filters('pta_sus_delete_expired_sheets_exclusions', $exclude);
		$num_days = !empty($this->main_options['num_days_expired']) ? absint($this->main_options['num_days_expired']) : 1;
		if($num_days < 1) {
			$num_days = 1;
		}
		// get sheet IDs for expired sheets
		$sql = "SELECT id FROM ".$this->tables['sheet']['name']." WHERE %s > ADDDATE(last_date, %d)";
		if(!empty($exclude)) {
			$clean_ids = array_map('absint', $exclude);
			$exclusions = implode(',', $clean_ids);
			$sql .= " AND id NOT IN ($exclusions)";
		}
		$safe_sql = $this->wpdb->prepare($sql, $this->now, $num_days);
		$ids = $this->wpdb->get_col($safe_sql);
		$deleted = 0;
		if(!empty($ids)) {
			foreach ($ids AS $id) {
				if( $this->delete_sheet($id) ) {
					$deleted++;
				}
			}
		}
		return $deleted;
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
     * @param    int  $id   sheet id
     * @param   array $task_dates key must be task ID, value is SQL format date
     * @param   array $start_times key must be task ID, value is start time
     * @param   array $end_times key must be task ID, value is end time
     *
     * @return mixed
     */
    public function copy_sheet_to_new_dates($id, $task_dates, $start_times, $end_times, $copy_signups = false) {
        $new_fields = array();

        $first_date = min($task_dates);
        $last_date = max($task_dates);
        $sheet = pta_sus_get_sheet($id);
        if(!$sheet) return false;
        $sheet = (array)$sheet;
        foreach ($this->tables['sheet']['allowed_fields'] AS $field=>$nothing) {
            if('first_date' === $field) {
                $new_fields['sheet_first_date'] = $first_date;
            } elseif('last_date' === $field) {
                $new_fields['sheet_last_date'] = $last_date;
            } elseif('visible' === $field) {
	            // make copied sheets hidden until admin can edit them
	            $new_fields['sheet_visible'] = false;
            } else {
                $new_fields['sheet_'.$field] = $sheet[$field];
            }
        }
        if ( false === $this->add_sheet($new_fields) ) {
            return false;
        }

        $new_sheet_id = $this->wpdb->insert_id;

        $new_tasks = array();

        $tasks = PTA_SUS_Task_Functions::get_tasks($id);
        if(empty($tasks)) return false;
        foreach ($tasks AS $task) {
            $new_fields = array();
            $task = (array)$task;
            $task_id = absint($task['id']);
            foreach ($this->tables['task']['allowed_fields'] AS $field=>$nothing) {
                if('dates' === $field) {
                    $new_fields['task_dates'] = $task_dates[$task_id];
                } elseif('time_start' === $field) {
                    $new_fields['task_time_start'] = $start_times[$task_id];
                } elseif('time_end' === $field) {
                    $new_fields['task_time_end'] = $end_times[$task_id];
                } elseif('sheet_id' === $field) {
                    continue; // passed in separately to add_task method
                } else {
                    $new_fields['task_'.$field] = $task[$field];
                }
            }
            if (false === $this->add_task($new_fields, $new_sheet_id) ) {
                return false;
            }
            $new_task_id = $this->wpdb->insert_id;
            $old_task_id = $task['id'];
            $new_tasks[$old_task_id] = $new_task_id;

            do_action('pta_sus_task_copied', $old_task_id, $new_task_id);

            // Maybe copy signups
            if($copy_signups) {
                $signups = PTA_SUS_Signup_Functions::get_signups_for_task($old_task_id);
                if(!empty($signups)) {
                    $date = $task_dates[$old_task_id];
                    foreach($signups as $signup) {
                        $signup = (array)$signup;
                        $new_fields = array();
                        foreach ($this->tables['signup']['allowed_fields'] AS $field=>$nothing) {
                            if('date' === $field) {
                                $new_fields['signup_date'] = $date;
                            } elseif('task_id' === $field) {
                                continue; // this is passed in separate parameter to add signup function
                            } elseif('item_qty' === $field) {
                                $qty = isset($signup['item_qty']) && absint($signup['item_qty']) > 0 ? absint( $signup['item_qty']) : 1;
                                $new_fields['signup_item_qty'] = $qty;
                            } elseif('reminder1_sent' === $field || 'reminder2_sent' === $field) {
                                $new_fields['signup_'.$field] = false; // reset reminders for copied sheet
                            } else {
                                $new_fields['signup_'.$field] = $signup[$field];
                            }
                        }
                        $new_signup_id = pta_sus_add_signup($new_fields, $new_task_id);
                        if (false === $new_signup_id) {
                            return false;
                        }
                        do_action('pta_sus_signup_copied', $signup['id'], $new_signup_id);
                    }
                }
            }
        }

        if(!empty($new_tasks)) {
            $data = array('sheet_id' => $id, 'tasks' => $new_tasks);
            // store array of copied tasks to temporary option for possible use in other extensions
            update_option('pta_sus_copied_tasks', $data);
        }
        do_action('pta_sus_sheet_copied', $id, $new_sheet_id);
        return $new_sheet_id;
    }


	/**
	 * Remove prefix from keys of an array and return records that were cleaned
	 *
	 * @param array   input array
	 * @param mixed $prefix
	 *
	 * @return   mixed   records that were cleaned
	 */
    public function clean_array($input=array(), $prefix=false) {
        if (!is_array($input)) return false;
        $clean_fields = array();
        foreach ($input AS $k=>$v) {
            if ($prefix === false || (substr($k, 0, strlen($prefix)) == $prefix)) {
                $clean_fields[str_replace($prefix, '', $k)] = $v;
            }
        }
        return $clean_fields;
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

    public function validate_post($fields, $post_type="sheet") {
        // Create a results array that we will return
        $results = array(
            'errors' => 0,
            'message' => '',
            );
        $prefix = ( 'sheet' === $post_type ) ? 'sheet_' : 'task_';
        $clean_fields = $this->clean_array($fields, $prefix);
        // Check Required Fields first
        foreach ( $this->tables[$post_type]['required_fields'] as $required_field => $label ) {
            if( empty($clean_fields[$required_field]) ) {
                $results['errors']++;
                $results['message'] .= sprintf( __('%s is a required field.', 'pta-volunteer-sign-up-sheets'), $label ) . '<br/>';
            }
        }

        foreach ( $this->tables[$post_type]['allowed_fields'] as $field => $type ) {
            if ( !empty( $clean_fields[$field] ) ) {
                switch ($type) {
                    case 'text':
                    case 'names':
                        if (!pta_sus_check_allowed_text($clean_fields[$field])) {
                            $results['errors']++;
                            $results['message'] .= sprintf( __('Invalid characters in %s field.', 'pta-volunteer-sign-up-sheets'), $field ) .'<br/>';
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
                        $emails_field = str_replace(' ', '', $clean_fields[$field]);
                        // Then, separate out the emails into a simple data array, using comma as separator
                        $emails = explode( ',',$emails_field);

                        foreach ($emails as $email) {
                            if (!is_email( $email )) {
                                $results['errors']++;
                                $results['message'] .= __('Invalid email.', 'pta-volunteer-sign-up-sheets') . '<br/>';
                            }
                        }
                        break;

                    case 'date':
                        if (!pta_sus_check_date( $clean_fields[$field] )) {
                            $results['errors']++;
                            $results['message'] .= __('Invalid date.', 'pta-volunteer-sign-up-sheets') .'<br/>';
                        }
                        break;

                    case 'dates':
                        // Validate one or more dates that will be separated by commas
                        // Format for each date should be yyyy-mm-dd
                        // First, get rid of any spaces
                        $dates_field = str_replace(' ', '', $clean_fields[$field]);
                        // Then, separate out the dates into a simple data array, using comma as separator
                        $dates = explode(',', $dates_field);
                        foreach ($dates as $date) {
                            if (!pta_sus_check_date( $date )) {
                                $results['errors']++;
                                $results['message'] .= __('Invalid date.', 'pta-volunteer-sign-up-sheets') .'<br/>';
                            }
                        }
                        break;

                    case 'int':
                        // Validate input is only numbers
                        if (!pta_sus_check_numbers($clean_fields[$field])) {
                            $results['errors']++;
                            $results['message'] .= sprintf(__('Numbers only for %s please!', 'pta-volunteer-sign-up-sheets'), $field ) . '<br/>';
                        }
                        break;

                    case 'yesno':
                        if ("YES" != $clean_fields[$field] && "NO" != $clean_fields[$field]) {
                            $results['errors']++;
                            $results['message'] .= sprintf( __('YES or NO only for %s please!', 'pta-volunteer-sign-up-sheets'), $field ) . '<br/>';
                        }
                        break;

                    case 'bool':
                        if ("1" != $clean_fields[$field] && "0" != $clean_fields[$field]) {
                            $results['errors']++;
                            $results['message'] .= sprintf( __('Invalid Value for %s', 'pta-volunteer-sign-up-sheets'), $field ) .'<br/>';
                        }
                        break;

                    case 'time':
                        $pattern = '/^(?:0[1-9]|1[0-2]):[0-5][0-9] (am|pm|AM|PM)$/';
                        if(!preg_match($pattern, $clean_fields[$field])){
                            $results['errors']++;
                            $results['message'] .= sprintf( __('Invalid time format for %s', 'pta-volunteer-sign-up-sheets'), $field) .'<br/>';
                        }
                        break;
                        
                    default:
                        $results = apply_filters( 'pta_sus_validate_custom_fields', $results, $field, $type );
                        break;
                }
            }
        }
        return $results;
    }

    public function sanitize_sheet_fields($clean_fields) {
        $sanitized_fields = array();
        foreach ( $this->tables['sheet']['allowed_fields'] as $field => $type ) {
            if ( isset( $clean_fields[$field] ) ) {
                switch ($type) {
                    case 'text':
                    case 'type':
                    case 'position':
                        $sanitized_fields[$field] = sanitize_text_field( $clean_fields[$field] );
                        break;
                    case 'names':
                        $valid_names = '';
	                    // Sanitize and format one or more names that will be separated by commas
						if(!empty($clean_fields[$field])) {
							$names = explode( ',',sanitize_text_field( $clean_fields[$field] ));
							$count = 1;
							foreach ($names as $name) {
								$name = !empty($name) ? trim($name) : '';
								if ('' != $name) {
									if ($count >1) {
										$valid_names .= ',';
									}
									$valid_names .= $name;
								}
								$count++;
							}
						}
                        $sanitized_fields[$field] = $valid_names;
                        break;
                    case 'textarea':
                        $sanitized_fields[$field] = wp_kses_post( $clean_fields[$field] );
                        break;

                    case 'emails':
	                    // create an empty string to store our valid emails
	                    $valid_emails = '';
                        if(!empty($clean_fields[$field])) {
	                        // Sanitize one or more emails that will be separated by commas
	                        // First, get rid of any spaces
	                        $emails_field = preg_replace('/\s+/', '', $clean_fields[$field]);
	                        // Then, separate out the emails into a simple data array, using comma as separator
	                        $emails = explode( ',',$emails_field);
	                        $count = 1;
	                        foreach ($emails as $email) {
		                        // Only add the email if it's a valid email
		                        if (is_email( $email )) {
			                        if ($count > 1) {
				                        // separate multiple emails by comma
				                        $valid_emails .= ',';
			                        }
			                        $valid_emails .= $email;
		                        }
		                        $count++;
	                        }
                        }

                        $sanitized_fields[$field] = $valid_emails;
                        break;

                    case 'dates':
                        // Sanitize one date
                        // Format for date should be yyyy-mm-dd
                        // First, get rid of any spaces
                        $date = str_replace(' ', '', $clean_fields[$field]);
                        // Convert the remaining string to a date
                        $sanitized_fields[$field] = date('Y-m-d', strtotime($date));
                        break;

                    case 'int':
                        // Make the value into absolute integer
                        $sanitized_fields[$field] = absint( $clean_fields[$field] );
                        break;

                    case 'bool':
                        if ( $clean_fields[ $field ] ) {
                            $sanitized_fields[$field] = 1;
                        } else {
                            $sanitized_fields[$field] = 0;
                        }
                        break;
	                case 'array':
	                	// make sure it is really an array
						$array = (array)maybe_unserialize($clean_fields[$field]);
	                	$sanitized_fields[$field] = array();
						foreach ($array as $k => $v) {
							$sanitized_fields[$field][$k] = sanitize_text_field($v);
						}
		                $sanitized_fields[$field] = maybe_serialize($sanitized_fields[$field]);
		                break;

                    default:
                        // return any other fields unaltered for now
                        $sanitized_fields[$field] = apply_filters('pta_sus_sanitize_sheet_fields', $clean_fields[$field], $type);
                        break;
                }
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
    
    private function initials_arr($nwords) {
        $new_name="";
        foreach($nwords as $nword){
        	if(!empty($nword[0])) {
        		$new_name .= $nword[0].'.';
	        }
        }
        return $new_name;
    }
    
    public function initials($name) {
        $nwords = explode(" ",$name);
        return $this->initials_arr($nwords);
    }

    public function initials_firstname_complete($name) {
        $nwords = explode(" ",$name);
        $firstname=array_shift($nwords);
        return $firstname." ".$this->initials_arr($nwords);
    }
}
/* EOF */
