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
     * @param     bool     get just trash
     * @param     bool     get only active sheets or those without a set date
     * @return    mixed    array of sheets
     */
    public function get_sheets($trash=false, $active_only=false, $show_hidden=false, $order_by='first_date', $order = 'ASC') {
    	$order_by = sanitize_key($order_by);
    	if(!in_array($order_by, array('first_date', 'last_date', 'title', 'id'))) {
		    $order_by='first_date';
	    }
	    $order = sanitize_text_field(strtoupper($order));
    	if(!in_array($order, array('ASC', 'DESC'))) {
    		$order = 'ASC';
	    }
        $SQL = "
            SELECT * 
            FROM ".$this->tables['sheet']['name']." 
            WHERE trash = %d
            ";
        if ( $active_only ) {
            $SQL .= " AND (ADDDATE(last_date,1) >= %s OR last_date = 0000-00-00)";
        }
        if ( !$show_hidden ) {
            $SQL .= " AND visible = 1";
        }
        $SQL .= "
            ORDER BY $order_by $order, id DESC
        ";
        if($active_only) {
	        $results = $this->wpdb->get_results($this->wpdb->prepare($SQL, $trash, $this->now));
        } else {
	        $results = $this->wpdb->get_results($this->wpdb->prepare($SQL, $trash));
        }
        
        $results = stripslashes_deep($results);
        // Hide incomplete sheets (no tasks) from public

        if (!is_admin()) {
            foreach($results as $key => $result) {
                $tasks = $this->get_tasks($result->id);
                if(empty($tasks)) {
                    unset($results[$key]);
                }
            }
        }

        return $results;
    }

    public function get_all_sheet_ids_and_titles($trash = false, $active_only = false, $show_hidden = false) {
	    // return an array with sheet ids as array key, and sheet titles as value
	    $return_array = array();
	    $sheets = $this->get_sheets($trash, $active_only, $show_hidden);
	    foreach($sheets as $sheet) {
		    $return_array[$sheet->id] = $sheet->title;
	    }
	    return $return_array;
    }

	/**
	 * Get single sheet
	 *
	 * @param $id INT
	 *
	 * @return    mixed
	 */
    public function get_sheet($id) {
	    if(is_object($id)) {
		    if(empty($id->id)) {
			    return false;
		    }
		    $id = $id->id;
	    }
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM ".$this->tables['sheet']['name']." WHERE id = %d" , $id));
	    if(!empty($row) ) {
		    return stripslashes_deep($row);
	    } else {
		    return false;
	    }
    }

	/**
	 * Get number of sheets
	 *
	 * @param bool $trash
	 *
	 * @return false
	 */
    public function get_sheet_count($trash=false)
    { 
        $count = $this->wpdb->get_var($this->wpdb->prepare("
            SELECT COUNT(*) FROM ".$this->tables['sheet']['name']." WHERE trash = %d", $trash));
	    if(!empty($count) ) {
		    return $count;
	    } else {
		    return false;
	    }
    }
    
    /**
     * Return # of entries that have matching title and date
     * @param  string $title sheet title
     * @return mixed        # of matching sheets, or false if none
     */
    public function check_duplicate_sheet($title) {
        $count = $this->wpdb->get_var($this->wpdb->prepare("
            SELECT COUNT(*) FROM ".$this->tables['sheet']['name']." WHERE title = %s AND trash = 0", $title));
	    if(!empty($count)) {
		    return $count;
	    } else {
		    return false;
	    }
    }

	/**
	 * Return # of signups that have matching task_id, and signup names
	 *
	 * @param $task_id INT
	 * @param $signup_date string
	 * @param $firstname string
	 * @param $lastname string
	 *
	 * @return mixed
	 */
    public function check_duplicate_signup($task_id, $signup_date, $firstname, $lastname) {
        $count = $this->wpdb->get_var($this->wpdb->prepare("
            SELECT COUNT(*) FROM ".$this->tables['signup']['name']." 
            WHERE task_id = %d AND date = %s AND firstname = %s AND lastname = %s
        ", $task_id, $signup_date, $firstname, $lastname));
	    if(!empty($count)) {
		    return $count;
	    } else {
		    return false;
	    }
    }

	/**
	 * Check if there is a signup with overlapping time for same volunteer info
	 *
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

	    if( '' === $task->time_start || '' === $task->time_end ) {
		    // don't check if the task doesn't have both start and end time
		    return false;
	    }
	    // Only the time matters, so use any date to create timestamp to compare times
	    $task_start = strtotime('01-01-2015 '. $task->time_start);
	    $task_end = strtotime('01-01-2015 '. $task->time_end);
        if($task_end < $task_start) {
	        $task_end = strtotime('01-02-2015 '. $task->time_end);
        }

	    if($check_all) {
        	// Gets all user signups for all sheets
		    $signups = $this->get_all_signups_by_user_name($firstname, $lastname, $signup_date);
	    } else {
		    // Gets all signup data by user name for sheet and signup date
		    $signups = $this->get_sheet_signups_by_user_name($firstname, $lastname, $sheet->id, $signup_date);
	    }
     
	    $duplicate = false;

	    foreach($signups as $signup) {
		    if( '' === $signup->time_start || '' === $signup->time_end ) {
			    // don't check if the signup doesn't have both start and end time
			    continue;
		    }
		    if($signup->task_id == $task->id) {
			    // don't check if it's the same task - we already have another allow duplicates for that
			    continue;
		    }
		    $signup_start = strtotime('01-01-2015 '. $signup->time_start);
		    $signup_end = strtotime('01-01-2015 '. $signup->time_end);
		    if($signup_end < $signup_start) {
			    $signup_end = strtotime('01-02-2015 '. $signup->time_end);
		    }
		    // check if time range overlaps
		    if( ($task_start < $signup_end) && ($task_end > $signup_start) ) {
			    // Overlap
			    $duplicate = true;
			    break;
		    }
	    }

	    return $duplicate;
    }

    public function toggle_visibility($id) {
        $SQL = "UPDATE ".$this->tables['sheet']['name']." 
                SET visible = IF(visible, 0, 1) 
                WHERE id = %d";
        return $this->wpdb->query($this->wpdb->prepare($SQL, $id));
    }

	/**
	 * Get tasks by sheet
	 *
	 * @param int        id of sheet
	 * @param string $date
	 *
	 * @return    mixed    array of tasks
	 */
    public function get_tasks($sheet_id, $date = '') {
        $SQL = "SELECT * FROM ".$this->tables['task']['name']." WHERE sheet_id = %d ";
        if ('' != $date ) {
            $SQL .= "AND INSTR(`dates`, %s) > 0 ";
        }
        $SQL .= "ORDER BY position, id";
	    if ('' != $date ) {
		    $results = $this->wpdb->get_results($this->wpdb->prepare($SQL, $sheet_id, $date));
	    } else {
		    $results = $this->wpdb->get_results($this->wpdb->prepare($SQL, $sheet_id));
	    }

	    return stripslashes_deep($results);
    }

	/**
	 * @param int $sheet_id
	 * @param string $date
	 *
	 * @return array an array of task IDs
	 */
    public function get_task_ids($sheet_id, $date = '') {
		$SQL = "SELECT id FROM ".$this->tables['task']['name']." WHERE sheet_id = %d ";
		if ('' != $date ) {
			$SQL .= "AND INSTR(`dates`, %s) > 0 ";
		}
		$SQL .= "ORDER BY position, id";
		if ('' != $date ) {
			$results = $this->wpdb->get_col($this->wpdb->prepare($SQL, $sheet_id, $date));
		} else {
			$results = $this->wpdb->get_col($this->wpdb->prepare($SQL, $sheet_id));
		}

		return $results;
	}

    /**
     * Get single task
     * 
     * @param     int      task id
     * @return    mixed    single task object
     */
    public function get_task($id) {
		if(is_object($id)) {
			if(empty($id->id)) {
				return false;
			}
			$id = $id->id;
		}
        $task = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM ".$this->tables['task']['name']." WHERE id = %d" , $id));
	    if(!empty($task)) {
		    return stripslashes_deep($task);
	    } else {
		    return false;
	    }
    }

	public function get_all_task_dates_for_sheet($sheet_id) {
		$SQL = "SELECT DISTINCT dates FROM ".$this->tables['task']['name']." WHERE sheet_id = %d";
		$results = $this->wpdb->get_col($this->wpdb->prepare($SQL, $sheet_id));
		$dates = array();
		if(empty($results)) {
			return $dates;
		}
		foreach($results as $result) {
			// split out individual dates
			$task_dates = explode(',', $result);
			foreach($task_dates as $task_date) {
				$task_date = trim($task_date);
				if(!empty($task_date) && !in_array($task_date, $dates)) {
					$dates[] = $task_date;
				}
			}
			$dates[] = $result;
		}
		return $dates;
	}

	/**
	 * Move tasks
	 *
	 * @param int      sheet id
	 * @param int      new sheet id
	 *
	 * @return bool|int
	 */
    public function move_tasks($sheet_id,$new_sheet_id)
    {
        $SQL = "UPDATE ".$this->tables['task']['name']." SET sheet_id = %d WHERE sheet_id = %d";
        return $this->wpdb->query($this->wpdb->prepare($SQL, $new_sheet_id, $sheet_id));
    }
    
    /**
     * Get signups by task & date
     * 
     * @param    int        id of task
     * @return    mixed    array of signups
     */
    public function get_signups($task_id, $date='') {
	    if(is_object($task_id)) {
		    if(empty($task_id->id)) {
			    return false;
		    }
		    $task_id = $task_id->id;
	    }
        $SQL = "SELECT * FROM ".$this->tables['signup']['name']." WHERE task_id = %d ";
        if ('' != $date) {
            $SQL .= "AND date = %s";
        }
        $SQL .= " ORDER by id";
	    if ('' != $date) {
		    $results = $this->wpdb->get_results($this->wpdb->prepare($SQL , $task_id, $date));
	    } else {
		    $results = $this->wpdb->get_results($this->wpdb->prepare($SQL , $task_id));
	    }

	    return stripslashes_deep($results);
    }

    public function get_signups2($search='')
    {
        $SQL = "SELECT * FROM ".$this->tables['signup']['name']." WHERE lastname like '%s' OR firstname like '%s' GROUP BY firstname, lastname";
        $results = $this->wpdb->get_results($this->wpdb->prepare($SQL,'%'.$search.'%','%'.$search.'%'));

	    return stripslashes_deep($results);
    }

    public function get_users($search='') {
	    $meta_query = array(
		    'relation' => 'OR',
		    array(
		    'key'     => 'first_name',
		    'value'   => $search,
		    'compare' => 'LIKE'
	        ),
		    array(
			    'key'     => 'last_name',
			    'value'   => $search,
			    'compare' => 'LIKE'
		    ),
		    array(
			    'key'     => 'user_email',
			    'value'   => $search,
			    'compare' => 'LIKE'
		    )
	    );
	    $args = array(
		    'meta_query'   =>$meta_query,
		    'orderby'      => 'ID',
		    'order'        => 'ASC',
		    'count_total'  => false,
		    'fields'       => array('ID', 'user_email'),
	    );
	    return get_users($args);
    }

    public function get_volunteer_emails($sheet_id = 0) {
	    $SQL = "SELECT DISTINCT email FROM ".$this->tables['signup']['name']." ";
	    if ($sheet_id > 0) {
			$TASKSQL = "SELECT id FROM ".$this->tables['task']['name']." WHERE sheet_id = %d";
		    // get the array of matching task ids
		    $task_ids = $this->wpdb->get_col($this->wpdb->prepare($TASKSQL , $sheet_id));
		    $safe_ids = array_map('intval', $task_ids);
		    if(empty($safe_ids)) {
		    	// No valid tasks for the given sheet id, return empty array
		    	return array();
		    }
		    $SQL .= "WHERE task_id IN(".implode(',',$safe_ids).")";
	    }
	    $results = $this->wpdb->get_col($SQL);

	    return stripslashes_deep($results);
    }
    
    public function get_signup($id)
    {
        $results = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM ".$this->tables['signup']['name']." WHERE id = %d" , $id));
        if(!empty($results)) {
        	$results = stripslashes_deep($results);
        }
        return $results;
    }
	
	/**
	 * Get detailed signup info for a specific signup ID
	 * @param  int $signup_id
	 * @return Mixed Object/false    Returns an object with the detailed signup info
	 */
	public function get_detailed_signup($signup_id) {
		$signup_table = $this->tables['signup']['name'];
		$task_table = $this->tables['task']['name'];
		$sheet_table = $this->tables['sheet']['name'];
		$safe_sql = $this->wpdb->prepare("SELECT
        $signup_table.id AS id,
        $signup_table.task_id AS task_id,
        $signup_table.user_id AS user_id,
        $signup_table.date AS signup_date,
        $signup_table.item AS item,
        $signup_table.item_qty AS item_qty,
        $task_table.title AS task_title,
        $task_table.time_start AS time_start,
        $task_table.time_end AS time_end,
        $task_table.qty AS task_qty,
        $sheet_table.title AS title,
        $sheet_table.id AS sheet_id,
        $sheet_table.details AS sheet_details,
        $sheet_table.chair_name AS chair_name,
        $sheet_table.chair_email AS chair_email,
        $sheet_table.clear AS clear,
        $sheet_table.clear_days AS clear_days,
        $task_table.dates AS task_dates
        FROM  $signup_table
        INNER JOIN $task_table ON $signup_table.task_id = $task_table.id
        INNER JOIN $sheet_table ON $task_table.sheet_id = $sheet_table.id
        WHERE $signup_table.id = %d AND $sheet_table.trash = 0
        ORDER BY signup_date, time_start
        ", $signup_id);
		$results = $this->wpdb->get_results($safe_sql);
		if(!empty($results) && isset($results[0])) {
			return stripslashes_deep($results[0]);
		} else {
			return false;
		}
	}
    
    /**
     * Get all data -- Right now this is only used for CRON remider emails, so can probably get rid of a lot of the select fields
     * 
     * @return    mixed    array of siginups
     */
    public function get_all_data()
    {
        $results = $this->wpdb->get_results("
            SELECT
                sheet.id AS sheet_id
                , sheet.title AS sheet_title
                , sheet.type AS sheet_type
                , sheet.details AS sheet_details
                , sheet.chair_name AS sheet_chair_name
                , sheet.chair_email AS sheet_chair_email
                , sheet.trash AS sheet_trash
                , sheet.reminder1_days AS reminder1_days
                , signup.reminder1_sent AS reminder1_sent
                , sheet.reminder2_days AS reminder2_days
                , signup.reminder2_sent AS reminder2_sent
                , task.id AS task_id
                , task.title AS task_title
                , task.dates AS task_dates
                , task.time_start AS task_time_start
                , task.time_end AS task_time_end
                , task.qty AS task_qty
                , task.need_details AS need_details
                , task.details_required AS details_required
                , task.details_text AS details_text
                , task.enable_quantities AS enable_quantities
                , task.position AS task_position
                , signup.id AS signup_id
                , signup.date AS signup_date
                , signup.item_qty AS item_qty
                , signup.user_id AS signup_user_id
                , signup.validated AS signup_validated
                , signup.ts AS signup_ts
                , item
                , firstname
                , lastname
                , email
                , phone
            FROM  ".$this->tables['sheet']['name']." sheet
            INNER JOIN ".$this->tables['task']['name']." task ON sheet.id = task.sheet_id
            INNER JOIN ".$this->tables['signup']['name']." signup ON task.id = signup.task_id
        ");

	    return stripslashes_deep($results);
    }
    
    /**
     * Get all unique dates for tasks for the given sheet id
     * @param  integer $id Sheet ID
     * @return mixed   array of all unique dates for a sheet
     */
    public function get_all_task_dates($id) {
        if ($tasks = $this->get_tasks($id)) {
            $dates = array();
            foreach ($tasks AS $task) {
                // Build an array of all unique dates from all tasks for this sheet
                $task_dates = $this->get_sanitized_dates($task->dates);
                foreach ($task_dates as $date) {
                    if(!in_array($date, $dates)) {
                        $dates[] = $date;
                    }
                }
            }
            sort($dates);
            return $dates;
        } else {
            return false;
        }
    }

    public function get_available_qty($task_id, $date, $task_qty) {
        $signups = $this->get_signups($task_id, $date);
        $count = 0;
        foreach ($signups as $signup) {
            $count += (int)$signup->item_qty;
        }
        $available = $task_qty - $count;
        if ($available > 0) {
            return $available;
        } else {
            return false;
        }
    }


	/**
	 * Get number of signups on a specific sheet
	 * Optionally for a specific date
	 * Don't count any signups for past dates
	 * UPDATED in version 1.6 to take into account signup quanitites
	 *
	 * @param int    sheet id
	 *
	 * @return int
	 */
    public function get_sheet_signup_count($id, $date='') {
        $signup_table = $this->tables['signup']['name'];
        $task_table = $this->tables['task']['name'];
        $SQL = "
            SELECT 
            $signup_table.item_qty AS item_qty
            , $task_table.enable_quantities AS enable_quantities 
            FROM $task_table 
            RIGHT OUTER JOIN $signup_table ON $task_table.id = $signup_table.task_id 
            WHERE $task_table.sheet_id = %d 
            AND (%s <= ADDDATE($signup_table.date, 1) OR $signup_table.date = 0000-00-00) 
        ";
        if( '' != $date ) {
            $SQL .= " AND $signup_table.date = %s ";
        }
	    if( '' != $date ) {
		    $results = $this->wpdb->get_results($this->wpdb->prepare($SQL, $id, $this->now, $date));
	    } else {
		    $results = $this->wpdb->get_results($this->wpdb->prepare($SQL, $id, $this->now));
	    }
        
        $count = 0;
        foreach ($results as $result) {
            if ( 'YES' === $result->enable_quantities ) {
                $count += $result->item_qty;
            } else {
                $count++;
            }
        }
        return $count;
    }

    public function get_all_signup_ids_for_sheet($id) {
	    $signup_table = $this->tables['signup']['name'];
	    $task_table = $this->tables['task']['name'];
	    $SQL = "
            SELECT $signup_table.id AS signup_id FROM $signup_table
            INNER JOIN $task_table ON $task_table.id = $signup_table.task_id 
            WHERE $task_table.sheet_id = %d 
        ";
	    return $this->wpdb->get_results($this->wpdb->prepare($SQL, $id));
    }
    
    /**
    * Get number of total spots on a specific sheet
    * And optionally for a specific date
    * @param    int    sheet id
    *           string date
    */
    public function get_sheet_total_spots($id, $date='') {
        $total_spots = 0;
        $tasks = $this->get_tasks($id, $date);
        
        foreach ($tasks as $task) {
            $task_dates = explode(',', $task->dates);
            $good_dates = 0;
            foreach ($task_dates as $tdate) {
                if('' != $date) {
                    if ($tdate != $date) {
                        continue;
                    }
                }
                if( (strtotime($tdate) >= ($this->time - (24*60*60))) || "0000-00-00" == $tdate ) {
                    ++$good_dates;
                }
            }
            $total_spots += $good_dates * $task->qty;
        }
        return $total_spots;
    }

    public function get_gdpr_user_export_items($email) {
    	$export_items = array();
	    $user = get_user_by( 'email', $email );
	    $user_id = false;
	    if ( $user && $user->ID ) {
	    	$user_id = $user->ID;
	    }

	    // Core group IDs include 'comments', 'posts', etc.
	    // But you can add your own group IDs as needed
	    $group_id = 'user-volunteer-signups';
	    // Optional group label. Core provides these for core groups.
	    // If you define your own group, the first exporter to
	    // include a label will be used as the group label in the
	    // final exported report
	    $group_label = __( 'User Volunteer Signup Data', 'pta-volunteer-sign-up-sheets' );

	    $signup_table = $this->tables['signup']['name'];
	    $task_table = $this->tables['task']['name'];
	    $sheet_table = $this->tables['sheet']['name'];
	    $sql = "SELECT
            $signup_table.id AS id,
            $signup_table.date AS signup_date,
            $signup_table.firstname AS firstname,
            $signup_table.lastname AS lastname,
            $signup_table.email AS email,
            $signup_table.phone AS phone,
            $signup_table.item AS item,
            $signup_table.item_qty AS item_qty,
            $task_table.title AS task_title,
            $task_table.time_start AS time_start,
            $task_table.time_end AS time_end,
            $sheet_table.title AS title 
            FROM  $signup_table
            INNER JOIN $task_table ON $signup_table.task_id = $task_table.id
            INNER JOIN $sheet_table ON $task_table.sheet_id = $sheet_table.id";
	    if($user_id > 0) {
	    	$sql .= " WHERE ($signup_table.email = %s OR $signup_table.user_id = %d) AND $sheet_table.trash = 0";
	    } else {
		    $sql .= " WHERE $signup_table.email = %s AND $sheet_table.trash = 0";
	    }

	    $sql .= " ORDER BY signup_date, time_start";
	    if($user_id > 0) {
		    $safe_sql = $this->wpdb->prepare($sql, $email, $user_id);
	    } else {
		    $safe_sql = $this->wpdb->prepare($sql, $email);
	    }

	    $results = $this->wpdb->get_results($safe_sql);
	    if(!empty($results)) {
		    $results = stripslashes_deep($results);
		    foreach ($results as $signup) {
			    // Most item IDs should look like postType-postID
			    // If you don't have a post, comment or other ID to work with,
			    // use a unique value to avoid having this item's export
			    // combined in the final report with other items of the same id
			    $item_id = "pta-volunteer-signup-sheets-{$signup->id}";
			    $data = array();
			    $value = esc_html($signup->title. ' - ' . $signup->task_title);
			    if('0000-00-00' != $signup->signup_date) {
			    	$value .= ' - '. pta_datetime(get_option("date_format"), strtotime($signup->signup_date));
			    }
			    if(!empty($signup->item)) {
			    	$value .= ' - ' . esc_html($signup->item);
			    }
			    $data[] = array(
			    	'name' => __('Signup Item', 'pta-volunteer-sign-up-sheets' ),
				    'value' => $value
			    );
			    $data[] = array(
				    'name' => __('Signup Name', 'pta-volunteer-sign-up-sheets' ),
				    'value' => esc_html($signup->firstname . ' ' .$signup->lastname)
			    );
			    $data[] = array(
				    'name' => __('Signup Email', 'pta-volunteer-sign-up-sheets' ),
				    'value' => esc_html($signup->email)
			    );
			    $data[] = array(
				    'name' => __('Signup Phone', 'pta-volunteer-sign-up-sheets' ),
				    'value' => esc_html($signup->phone)
			    );
			    // Add this group of items to the exporters data array.
			    $export_items[] = array(
				    'group_id'    => $group_id,
				    'group_label' => $group_label,
				    'item_id'     => $item_id,
				    'data'        => $data,
			    );
		    }
	    }

	    return $export_items;
    }

    public function gdpr_delete_user_data($email) {
    	$user_id = false;
	    $user = get_user_by( 'email', $email );
	    if($user && $user->ID) {
	    	$user_id = $user->ID;
	    }
	    $signup_table = $this->tables['signup']['name'];
	    $where = array();
	    $where_format = array();
	    $where['email'] = $email;
	    $where_format[] = '%s';
	    if($user_id > 0) {
	    	$where['user_id'] = $user_id;
	    	$where_format[] = '%d';
	    }
    	return $this->wpdb->delete($signup_table, $where, $where_format);
    }

	public function get_all_signups_for_sheet($sheet_id, $date='') {
		$signup_table = $this->tables['signup']['name'];
		$task_table = $this->tables['task']['name'];
		if(is_object($sheet_id)) {
			$sheet_id = absint($sheet_id->id);
		}
		$sql = "SELECT $signup_table.* FROM $signup_table INNER JOIN $task_table ON $signup_table.task_id = $task_table.id WHERE $task_table.sheet_id = %d";

		if (!empty($date)) {
			$sql .= " AND $signup_table.date = %s";
			$sql = $this->wpdb->prepare($sql, $sheet_id, $date);
		} else {
			$sql = $this->wpdb->prepare($sql, $sheet_id);
		}

		return $this->wpdb->get_results($sql);
	}

    /**
     * Get all the signups for a given user id
     * Return info on what they signed up for
     * @param  int $user_id WordPress uer id
     * @return Array    Returns an array of objects with the user's signup info
     */
    public function get_user_signups($user_id, $show_expired = false) {
		if($user_id < 1) {
			return array();
		}
        $signup_table = $this->tables['signup']['name'];
        $task_table = $this->tables['task']['name'];
        $sheet_table = $this->tables['sheet']['name'];
        $sql = "SELECT
            $signup_table.id AS id,
            $signup_table.task_id AS task_id,
            $signup_table.user_id AS user_id,
            $signup_table.date AS signup_date,
            $signup_table.item AS item,
            $signup_table.item_qty AS item_qty,
            $task_table.title AS task_title,
            $task_table.time_start AS time_start,
            $task_table.time_end AS time_end,
            $sheet_table.title AS title,
            $sheet_table.id AS sheet_id,
            $sheet_table.clear AS clear,
            $sheet_table.clear_days AS clear_days,
            $task_table.dates AS task_dates
            FROM  $signup_table
            INNER JOIN $task_table ON $signup_table.task_id = $task_table.id
            INNER JOIN $sheet_table ON $task_table.sheet_id = $sheet_table.id
            WHERE $signup_table.user_id = %d AND $sheet_table.trash = 0";
        if(!$show_expired) {
        	$sql .= " AND (ADDDATE($signup_table.date, 1) >= %s OR $signup_table.date = '0000-00-00')";
        }
        $sql .= " ORDER BY signup_date, time_start";
	    if(!$show_expired) {
		    $safe_sql = $this->wpdb->prepare($sql, $user_id, $this->now);
	    } else {
		    $safe_sql = $this->wpdb->prepare($sql, $user_id);
	    }
        $results = $this->wpdb->get_results($safe_sql);

	    return stripslashes_deep($results);
    }

	public function get_sheet_signups_by_user_name($firstname, $lastname, $sheet_id, $date = false ) {
		$signup_table = $this->tables['signup']['name'];
		$task_table = $this->tables['task']['name'];
		$sheet_table = $this->tables['sheet']['name'];
		$sql = "SELECT
			$signup_table.id AS id,
            $signup_table.task_id AS task_id,
            $signup_table.user_id AS user_id,
            $signup_table.date AS signup_date,
            $signup_table.item AS item,
            $signup_table.item_qty AS item_qty,
            $task_table.title AS task_title,
            $task_table.time_start AS time_start,
            $task_table.time_end AS time_end,
            $sheet_table.title AS title,
            $sheet_table.id AS sheet_id,
            $sheet_table.clear AS clear,
            $sheet_table.clear_days AS clear_days,
            $task_table.dates AS task_dates
            FROM  $signup_table
            INNER JOIN $task_table ON $signup_table.task_id = $task_table.id
            INNER JOIN $sheet_table ON $task_table.sheet_id = $sheet_table.id
            WHERE $signup_table.firstname = %s AND $signup_table.lastname = %s
            AND $sheet_table.trash = 0 AND $sheet_table.id = %d";
		if($date) {
			$sql .= "  AND $signup_table.date = %s";
		}
		$sql .= " ORDER BY signup_date, time_start";
		if($date) {
			$safe_sql = $this->wpdb->prepare($sql, $firstname, $lastname, $sheet_id, $date);
		} else {
			$safe_sql = $this->wpdb->prepare($sql, $firstname, $lastname, $sheet_id);
		}
		$results = $this->wpdb->get_results($safe_sql);

		return stripslashes_deep($results);
	}
	
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
	 * @param Bool true to show expired signups
	 * @return Object Array    Returns an array of objects with signup info
	 */
	public function get_all_signups($show_expired = false) {
		_deprecated_function( __FUNCTION__, '4.7.0', 'PTA_SUS_Signup_Functions::get_detailed_signups()' );
		$where = array();
		return PTA_SUS_Signup_Functions::get_detailed_signups($where,$show_expired);
	}

    public function get_chair_names_html($names_csv) {
        $html_names = '';
        $i = 1;
        $names = explode( ',',sanitize_text_field($names_csv));
        $count = count($names);
        foreach ($names as $name) {
            if ($i > 1) {
                if ($i < $count) {
                    $html_names .= _x(', ', 'contact name separating character', 'pta-volunteer-sign-up-sheets' );
                } else {
                    $html_names .= _x(' and ', 'separator before last contact name', 'pta-volunteer-sign-up-sheets' );
                }                
            }
            $html_names .= $name;
            $i++;
        }
        return $html_names;
    }

    
    /**
     * Add a new sheet
     * 
     * @param    array    array of fields and values to insert
     * @return    mixed    false if insert fails
     */
    public function add_sheet($fields) {
        $clean_fields = $this->clean_array($fields, 'sheet_');
        $clean_fields = array_intersect_key($clean_fields, $this->tables['sheet']['allowed_fields']);
        // if (isset($clean_fields['date']) && $clean_fields['date'] != '0000-00-00') $clean_fields['date'] = date('Y-m-d', strtotime($clean_fields['date']));
        // Data should be sanitized and prepared before inserting into database
        $sanitized_fields = $this->sanitize_sheet_fields($clean_fields);
        // wpdb->insert does all necessary SQL sanitation before inserting into database
        return $this->wpdb->insert($this->tables['sheet']['name'], $sanitized_fields);
        
    }
    
    /**
     * Add a new task
     * 
     * @param    array    array of fields and values to insert
     * @param   int     sheet id
     * @param   bool    no signups  whether or not to allow task with 0 qty
     * @return    mixed    false if insert fails
     */
    public function add_task($fields, $sheet_id, $no_signups = false) {
        $clean_fields = $this->clean_array($fields, 'task_');
        $clean_fields = array_intersect_key($clean_fields, $this->tables['task']['allowed_fields']);
        $clean_fields['sheet_id'] = $sheet_id;
        if ($clean_fields['qty'] < 2 && !$no_signups) $clean_fields['qty'] = 1;
        // wpdb->insert does all necessary sanitation before inserting into database
        return $this->wpdb->insert($this->tables['task']['name'], $clean_fields);       
    }
    
    /**
     * Add a new signup to a task
     * 
     * @param   array   array of fields and values to insert
     * @param   int     task id
     * @return  mixed   false if insert fails, the signup id if it succeeds
     */
    public function add_signup($fields, $task_id) {
        $clean_fields = $this->clean_array($fields, 'signup_');
        $clean_fields = array_intersect_key($clean_fields, $this->tables['signup']['allowed_fields']);
        $clean_fields['task_id'] = $task_id;
        // Set user from email if they weren't logged in and if there is an account with that email
        // if they were logged in and not manager, take the current wp id as value
        if (is_user_logged_in() && !current_user_can('manage_signup_sheets')) {
            $clean_fields['user_id'] = get_current_user_id();
        }
        if (!isset($clean_fields['user_id']) || empty($clean_fields['user_id'])) {
            if (is_user_logged_in()) {
                 $clean_fields['user_id'] = get_current_user_id();
            } elseif ($user = get_user_by( 'email', $clean_fields['email'] )) {
                $clean_fields['user_id'] = $user->ID;
            }           
        }
        // If we have a user_id, check to see if their meta fields are empty and update them so they can be pre-filled for future signups
        if (isset($clean_fields['user_id']) && !empty($clean_fields['user_id'])) {
            if (!isset($user)) {
                $user = get_user_by( 'id', $clean_fields['user_id'] );
            }
            if($user) {
            	if ( !isset($user->first_name) || empty($user->first_name) ) {
                    update_user_meta( $user->ID, 'first_name', $clean_fields['firstname'] );
	            }
	            if ( !isset($user->last_name) || empty($user->last_name) ) {
	                update_user_meta( $user->ID, 'last_name', $clean_fields['lastname'] );
	            }
	            $phone = get_user_meta( $user->ID, 'billing_phone', true );
	            if (empty($phone) && isset($clean_fields['phone']) ) {
	                update_user_meta( $user->ID, 'billing_phone', $clean_fields['phone'] );
	            }
            }
        }
        
        // Check if signup spots are filled
        $task = $this->get_task($task_id);
        $signups = $this->get_signups($task_id, $clean_fields['date']);
        if ($task->enable_quantities == 'YES') {
            // Take item quantities into account when calculating # of items
            $count = 0;
            foreach ($signups as $signup) {
                $count += (int)$signup->item_qty;
            }
            $count += $clean_fields['item_qty'];
        } else {
            $count = count($signups) + 1;
        }
        if ($count > $task->qty) {
            return false;
        }
		// set ts to current timestamp
	    $clean_fields['ts'] = $this->time;

        // wpdb->insert does all necessary sanitation before inserting into database
        // $fields were also validated before this function was called
        $result=$this->wpdb->insert($this->tables['signup']['name'], $clean_fields);
		if ($result !== false) {
			return $this->wpdb->insert_id;
		}
		return false;
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
        $tasks = $this->get_tasks($id);
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
    * @param    int     signup id
     *
     * @return mixed
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
    * @param    int  $id   sheet id
     * @return mixed
    */
    public function copy_sheet($id) {
        $new_fields = array();
        
        $sheet = $this->get_sheet($id);
        $sheet = (array)$sheet;
        foreach ($this->tables['sheet']['allowed_fields'] AS $field=>$nothing) {
            if ('title' == $field) {
                $new_fields['sheet_title'] = $sheet['title'] . ' Copy';
            } else {
                $new_fields['sheet_'.$field] = $sheet[$field];
            }
            
        }
        if ( false === $this->add_sheet($new_fields) ) {
            return false;
        }
        
        $new_sheet_id = $this->wpdb->insert_id;
        
        $new_tasks = array();
        
        $tasks = $this->get_tasks($id);
        foreach ($tasks AS $task) {
            $new_fields = array();
            $task = (array)$task;
            foreach ($this->tables['task']['allowed_fields'] AS $field=>$nothing) {
                $new_fields['task_'.$field] = $task[$field];
            }
            if (false === $this->add_task($new_fields, $new_sheet_id) ) {
                return false;
            }
            $new_task_id = $this->wpdb->insert_id;
            $old_task_id = $task['id'];
            do_action('pta_sus_task_copied', $old_task_id, $new_task_id);
            $new_tasks[$old_task_id] = $new_task_id;
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
        $sheet = $this->get_sheet($id);
        if(!$sheet) return false;
        $sheet = (array)$sheet;
        foreach ($this->tables['sheet']['allowed_fields'] AS $field=>$nothing) {
            if('first_date' == $field) {
                $new_fields['sheet_first_date'] = $first_date;
            } elseif('last_date' == $field) {
                $new_fields['sheet_last_date'] = $last_date;
            } else {
                $new_fields['sheet_'.$field] = $sheet[$field];
            }
        }
        if ( false === $this->add_sheet($new_fields) ) {
            return false;
        }

        $new_sheet_id = $this->wpdb->insert_id;

        $new_tasks = array();

        $tasks = $this->get_tasks($id);
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
                $signups = $this->get_signups($old_task_id);
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
                        if(false === $this->add_signup($new_fields, $new_task_id)) {
                            return false;
                        }
                        $new_signup_id = $this->wpdb->insert_id;
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
        $prefix = ( 'sheet' == $post_type ) ? 'sheet_' : 'task_';
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
                        if (!$this->check_allowed_text($clean_fields[$field])) {
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
                        if (!$this->check_date( $clean_fields[$field] )) {
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
                            if (!$this->check_date( $date )) {
                                $results['errors']++;
                                $results['message'] .= __('Invalid date.', 'pta-volunteer-sign-up-sheets') .'<br/>';
                            }
                        }
                        break;

                    case 'int':
                        // Validate input is only numbers
                        if (!$this->check_numbers($clean_fields[$field])) {
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

    public function check_allowed_text($text) {
        // For titles and names, allow letters, numbers, and common punctuation
        // Returns true if good or false if bad
        // return !preg_match( "/[^A-Za-z0-9\p{L}\p{Z}\p{N}\-\.\,\!\&\(\)\'\/\?\ ]+$/", stripslashes($text) );
        
        // New method to allow all good text... check against wordpress santized version
	    if(!empty($text)) {
		    $text = preg_replace('/\s+/', ' ', trim($text)); // strip out extra spaces before compare
		    $sanitized = sanitize_text_field( $text );
		    if ( $text === $sanitized ) {
			    return true;
		    } else {
			    return false;
		    }
	    }
        return true; // empty allowed
    }

    public function check_date($date) {
        // Our dates should be in yyyy-mm-dd format.  Convert/Reject if not
        // Checks to see if it's a valid date
        // Returns true if good, false if bad
        if ($date == "0000-00-00") return true;
        $date = str_replace(' ', '-', $date);
        $date = str_replace('/', '-', $date);
        $date = str_replace('--', '-', $date);
        if ( empty($date) ) return false;
        preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $xadBits);
        if (count($xadBits) < 3) return false;
        return checkdate($xadBits[2], $xadBits[3], $xadBits[1]);
    }

    public function check_numbers($string) {
        // Returns true if string contains only numbers
        return !preg_match("/[^0-9]/", stripslashes($string));
    }

    public function get_sanitized_dates($dates) {
        // Sanitize one or more dates that will be separated by commas
        // Format for each date should be yyyy-mm-dd
        // First, get rid of any spaces
        $dates = str_replace(' ', '', $dates);
        // Then, separate out the dates into a simple data array, using comma as separator
        $dates = explode(',', $dates);
        $valid_dates = array();
        foreach ($dates as $date) {
            if ($this->check_date( $date )) {
                $valid_dates[] = $date;
            }
        }
        return $valid_dates;
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
