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
			if ( in_array( $key, array_keys(self::$signup_properties) ) ) {
				$table_prefix = $signup_table;
				$field_type = self::$signup_properties[$key];
			} elseif ( in_array( $key, array_keys(self::$task_properties) ) ) {
				$table_prefix = $task_table;
				$field_type = self::$task_properties[$key];
			} elseif ( in_array( $key, array_keys(self::$sheet_properties) ) ) {
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

		return stripslashes_deep($results);
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
	public static function validate_signup($signup_id) {
		global $wpdb;
		$data = array('validated' => 1);
		$where = array('id' => $signup_id);
		return $wpdb->update(self::$signup_table,$data,$where,'%d','%d');
	}

}
PTA_SUS_Signup_Functions::init();