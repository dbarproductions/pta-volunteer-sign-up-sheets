<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class PTA_SUS_Signup_Functions {

	private static $signup_table;
	private static $task_table;
	private static $sheet_table;
	private static $main_options;

	private static $signup_properties = array(
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
	);

	private static $task_properties = array(
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
	);

	private static $sheet_properties = array(
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
		'signup_emails' => 'text',
	);

	public static function init() {
		global $wpdb;
		self::$signup_table = $wpdb->prefix.'pta_sus_signups';
		self::$task_table = $wpdb->prefix.'pta_sus_tasks';
		self::$sheet_table = $wpdb->prefix.'pta_sus_sheets';
		self::$main_options = get_option('pta_volunteer_sus_main_options', array());
	}

	public static function get_signup($signup_id) {
		global $wpdb;
		$signup_table = self::$signup_table;
		$results = $wpdb->get_row($wpdb->prepare("SELECT * FROM ".$signup_table." WHERE id = %d" , $signup_id));
		if(!empty($results)) {
			$results = stripslashes_deep($results);
		}
		return $results;
	}

	private static function build_where_clauses($where, $show_expired = false) {
		$signup_table = self::$signup_table;
		$task_table = self::$task_table;
		$sheet_table = self::$sheet_table;
		$sql = '';
		foreach ( $where as $key => $value ) {
			$key = pta_create_slug( $key );
			if (array_key_exists($key, self::$signup_properties)) {
				$table_prefix = $signup_table;
				$field_type = self::$signup_properties[$key];
			} elseif (array_key_exists($key, self::$task_properties)) {
				$table_prefix = $task_table;
				$field_type = self::$task_properties[$key];
			} elseif (array_key_exists($key, self::$sheet_properties)) {
				$table_prefix = $sheet_table;
				$field_type = self::$sheet_properties[$key];
			} else {
				continue;
			}
			$sql .= " AND ";
			if ( $field_type === 'int' || $field_type === 'bool' ) {
				$value = absint( $value );
				$sql .= "{$table_prefix}.{$key} = $value";
			} else {
				$value = esc_sql(sanitize_text_field( $value ));
				$sql .= "{$table_prefix}.{$key} = '$value'";
			}
		}
		if(!$show_expired) {
			$current_date = current_time('Y-m-d');
			$sql .= " AND ($signup_table.date >= '$current_date' OR $signup_table.date = '0000-00-00')";
		}
		return $sql;
	}

	public static function get_signups($where=array(), $show_expired = false) {
		global $wpdb;
		$signup_table = self::$signup_table;
		$task_table = self::$task_table;
		$sheet_table = self::$sheet_table;
		$sql = "SELECT $signup_table.* FROM  $signup_table
	        INNER JOIN $task_table ON $signup_table.task_id = $task_table.id
	        INNER JOIN $sheet_table ON $task_table.sheet_id = $sheet_table.id
	        WHERE $sheet_table.trash = 0";

		$sql .= self::build_where_clauses($where, $show_expired);
		$sql .= " ORDER BY date, user_id";

		$results = $wpdb->get_results($sql);

        // Convert to class objects
        $signups = array();
        foreach ($results as $row) {
            $signup = new PTA_SUS_Signup($row); // Pass data to constructor
            $signups[] = $signup;
        }

        return $signups;
	}

	public static function get_detailed_signups($where=array(), $show_expired = false) {
		global $wpdb;
		$signup_table = self::$signup_table;
		$task_table = self::$task_table;
		$sheet_table = self::$sheet_table;
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
	        WHERE $sheet_table.trash = 0";

		$sql .= self::build_where_clauses($where, $show_expired);
		$sql .= " ORDER BY signup_date, time_start";

		$results = $wpdb->get_results($sql);

		return stripslashes_deep($results);
	}

	public static function get_signup_ids($where=array(),$show_expired=false) {
		global $wpdb;
		$signup_table = self::$signup_table;
		$task_table = self::$task_table;
		$sheet_table = self::$sheet_table;
		$sql = "SELECT $signup_table.id FROM $signup_table
	        INNER JOIN $task_table ON $signup_table.task_id = $task_table.id
	        INNER JOIN $sheet_table ON $task_table.sheet_id = $sheet_table.id
	        WHERE $sheet_table.trash = 0";
		$sql .= self::build_where_clauses($where, $show_expired);
		$sql .= " ORDER BY signup_date, time_start";
		return $wpdb->get_col( $sql );
	}

    /**
     * Get signups for a specific task
     * Simple query without joins - faster for single task lookups
     *
     * @param int    $task_id Task ID
     * @param string $date    Optional specific date to filter by
     * @return array Array of PTA_SUS_Signup objects
     */
    public static function get_signups_for_task($task_id, $date = '') {
        global $wpdb;

        $task_id = absint($task_id);
        if (empty($task_id)) {
            return array();
        }

        $sql = "SELECT * FROM " . self::$signup_table . " WHERE task_id = %d";
        $params = array($task_id);

        if ('' !== $date) {
            $sql .= " AND date = %s";
            $params[] = sanitize_text_field($date);
        }

        $sql .= " ORDER BY id";

        // Get results as arrays
        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        // Convert to PTA_SUS_Signup objects
        $signups = array();
        foreach ($results as $row) {
            $signups[] = new PTA_SUS_Signup($row);
        }

        return $signups;
    }

    /**
     * Search signups by firstname or lastname
     * Used for live search functionality in admin and frontend
     *
     * @param string $search Search term (searches both firstname and lastname)
     * @return array Array of PTA_SUS_Signup objects (grouped by name)
     */
    public static function search_signups_by_name($search = '') {
        global $wpdb;

        if (empty($search)) {
            return array();
        }

        $search = sanitize_text_field($search);
        $search_pattern = '%' . $wpdb->esc_like($search) . '%';

        $sql = "SELECT * FROM " . self::$signup_table . " 
            WHERE lastname LIKE %s OR firstname LIKE %s 
            GROUP BY firstname, lastname 
            ORDER BY lastname, firstname";

        $results = $wpdb->get_results($wpdb->prepare($sql, $search_pattern, $search_pattern), ARRAY_A);

        // Convert to PTA_SUS_Signup objects
        $signups = array();
        foreach ($results as $row) {
            $signups[] = new PTA_SUS_Signup($row);
        }

        return $signups;
    }

	public static function validate_signup($signup_id) {
		global $wpdb;
		$data = array('validated' => 1);
		$where = array('id' => $signup_id);
		return $wpdb->update(self::$signup_table,$data,$where,'%d','%d');
	}

    /**
     * Check if a signup already exists for this task, date, and volunteer
     *
     * @param int    $task_id Task ID
     * @param string $signup_date Date
     * @param string $firstname First name
     * @param string $lastname Last name
     * @return int|false Count of duplicates or false if none
     */
    public static function check_duplicate_signup($task_id, $signup_date, $firstname, $lastname) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::$signup_table . " 
        WHERE task_id = %d AND date = %s AND firstname = %s AND lastname = %s",
            $task_id,
            $signup_date,
            sanitize_text_field($firstname),
            sanitize_text_field($lastname)
        ));

        return absint($count) > 0 ? absint($count) : false;
    }

    /**
     * Check if volunteer has overlapping time commitment on same date
     * Prevents double-booking volunteers for tasks with conflicting times
     *
     * @param PTA_SUS_Sheet|object $sheet Sheet object
     * @param PTA_SUS_Task|object $task Task object being signed up for
     * @param string $signup_date Date to check
     * @param string $firstname Volunteer first name
     * @param string $lastname Volunteer last name
     * @param bool $check_all Check across all sheets (default: false)
     * @return bool True if duplicate time found, false if no conflict
     */
    public static function check_duplicate_time_signup($sheet, $task, $signup_date, $firstname, $lastname, $check_all = false) {
        // Don't check if task doesn't have both start and end times
        if (empty($task->time_start) || empty($task->time_end)) {
            return false;
        }

        // Create timestamps for comparison (use arbitrary date, only time matters)
        $task_start = strtotime('2015-01-01 ' . $task->time_start);
        $task_end = strtotime('2015-01-01 ' . $task->time_end);

        // Handle overnight tasks (end time before start time)
        if ($task_end < $task_start) {
            $task_end = strtotime('2015-01-02 ' . $task->time_end);
        }

        // Build where clause for signup query
        $where = array(
            'firstname' => $firstname,
            'lastname' => $lastname,
            'date' => $signup_date,
        );

        // Limit to same sheet unless checking all sheets
        if (!$check_all) {
            $where['sheet_id'] = $sheet->id;
        }

        // Get all signups for this person on this date
        $signups = self::get_detailed_signups($where);

        // Check each signup for time overlap
        foreach ($signups as $signup) {
            // Skip if no times defined
            if (empty($signup->time_start) || empty($signup->time_end)) {
                continue;
            }

            // Skip if it's the same task (allow_duplicates handles this separately)
            if ($signup->task_id === $task->id) {
                continue;
            }

            // Create timestamps for existing signup
            $signup_start = strtotime('2015-01-01 ' . $signup->time_start);
            $signup_end = strtotime('2015-01-01 ' . $signup->time_end);

            // Handle overnight existing signup
            if ($signup_end < $signup_start) {
                $signup_end = strtotime('2015-01-02 ' . $signup->time_end);
            }

            // Check for time range overlap
            // Ranges overlap if: (StartA < EndB) AND (EndA > StartB)
            if (($task_start < $signup_end) && ($task_end > $signup_start)) {
                return true; // Overlap found!
            }
        }

        return false; // No conflicts
    }

}
PTA_SUS_Signup_Functions::init();