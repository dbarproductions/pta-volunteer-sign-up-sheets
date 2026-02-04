<?php
/**
 * Public Display Functions Helper Class
 * 
 * Static helper methods for generating display data for sheets, tasks, and signups.
 * These methods can be used by extensions without requiring access to the public class instance.
 * 
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Public_Display_Functions {

	/**
	 * Cached main options array
	 * Loaded once and reused across all methods
	 * 
	 * @var array|null
	 */
	private static $main_options = null;

	/**
	 * Cached validation options array
	 * 
	 * @var array|null
	 */
	private static $validation_options = null;

	/**
	 * Cached integration options array
	 * 
	 * @var array|null
	 */
	private static $integration_options = null;

	/**
	 * Display options (headers, visibility settings, etc.)
	 * 
	 * @var array|null
	 */
	private static $display_options = null;

	/**
	 * Current date filter for sheet/task lists
	 * 
	 * @var string|null
	 */
	private static $current_date_filter = null;

	/**
	 * Volunteer object
	 * 
	 * @var PTA_SUS_Volunteer|null
	 */
	private static $volunteer = null;

	/**
	 * Track if signup form has been displayed on current page
	 * Used to prevent multiple forms when suppress_duplicates is enabled
	 * 
	 * @var bool
	 */
	private static $signup_displayed = false;

	/**
	 * Form processing state - whether form was submitted
	 * 
	 * @var bool
	 */
	private static $submitted = false;

	/**
	 * Form processing state - error count
	 * 
	 * @var int
	 */
	private static $err = 0;

	/**
	 * Form processing state - whether submission was successful
	 * 
	 * @var bool
	 */
	private static $success = false;

	/**
	 * Form processing state - whether signup was cleared
	 * 
	 * @var bool
	 */
	private static $cleared = false;

	/**
	 * Track if messages have been displayed on current page
	 * Used to prevent duplicate messages when multiple shortcodes on one page
	 * 
	 * @var bool
	 */
	private static $messages_displayed = false;

	/**
	 * Whether validation email was sent
	 * 
	 * @var bool
	 */
	private static $validation_sent = false;

	/**
	 * Whether validation is enabled
	 * 
	 * @var bool
	 */
	private static $validation_enabled = false;

	/**
	 * Whether to show hidden sheets
	 * 
	 * @var bool
	 */
	private static $show_hidden = false;

	/**
	 * Whether the helper class has been initialized
	 * 
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Initialize display options
	 * Called by public class to set all display options at once
	 * Extensions can also call this to customize display behavior
	 * 
	 * IMPORTANT: For best performance, call this method early (e.g., in your
	 * extension's AJAX handler before calling display methods). The Public class
	 * calls this automatically on public-facing pages via plugins_loaded hook.
	 * If not called, individual getter methods will load options lazily as needed,
	 * but calling this first loads all options at once for better performance.
	 * 
	 * @param array $options Optional array of options to override defaults
	 * @param PTA_SUS_Volunteer|null $volunteer Optional volunteer object
	 */
	public static function initialize($options = array(), $volunteer = null) {
		// Merge with defaults
		self::$display_options = array_merge(
			self::get_default_display_options(),
			$options
		);

		// Set volunteer if provided
		if ($volunteer !== null) {
			self::$volunteer = $volunteer;
		}

		// Load options arrays
		self::$main_options = get_option('pta_volunteer_sus_main_options');
		self::$validation_options = get_option('pta_volunteer_sus_validation_options');
		self::$integration_options = get_option('pta_volunteer_sus_integration_options');

		// Set validation enabled flag
		self::$validation_enabled = isset(self::$validation_options['enable_validation']) && self::$validation_options['enable_validation'];

		// Set show_hidden based on user capabilities
		if (current_user_can('manage_options') || current_user_can('manage_signup_sheets')) {
			self::$show_hidden = true;
		}

		self::$initialized = true;
	}

	/**
	 * Update form processing state
	 * Called by public class to update form state after processing
	 * 
	 * @param bool $submitted Whether form was submitted
	 * @param int $err Error count
	 * @param bool $success Whether submission was successful
	 * @param bool $cleared Whether signup was cleared
	 */
	public static function update_form_state($submitted = false, $err = 0, $success = false, $cleared = false) {
		self::$submitted = $submitted;
		self::$err = $err;
		self::$success = $success;
		self::$cleared = $cleared;
	}

	/**
	 * Update messages displayed flag
	 * 
	 * @param bool $displayed Whether messages have been displayed
	 */
	public static function set_messages_displayed($displayed = true) {
		self::$messages_displayed = $displayed;
	}

	/**
	 * Update validation sent flag
	 * 
	 * @param bool $sent Whether validation email was sent
	 */
	public static function set_validation_sent($sent = true) {
		self::$validation_sent = $sent;
	}

	/**
	 * Get form state - submitted
	 * 
	 * @return bool
	 */
	public static function get_submitted() {
		return self::$submitted;
	}

	/**
	 * Get form state - error count
	 * 
	 * @return int
	 */
	public static function get_err() {
		return self::$err;
	}

	/**
	 * Get form state - success
	 * 
	 * @return bool
	 */
	public static function get_success() {
		return self::$success;
	}

	/**
	 * Get form state - cleared
	 * 
	 * @return bool
	 */
	public static function get_cleared() {
		return self::$cleared;
	}

	/**
	 * Get messages displayed flag
	 * 
	 * @return bool
	 */
	public static function get_messages_displayed() {
		return self::$messages_displayed;
	}

	/**
	 * Get validation enabled flag
	 * 
	 * @return bool
	 */
	public static function get_validation_enabled() {
		return self::$validation_enabled;
	}

	/**
	 * Get show hidden flag
	 * 
	 * @return bool
	 */
	public static function get_show_hidden() {
		return self::$show_hidden;
	}

	/**
	 * Get default display options
	 * Returns all default values for display options
	 * 
	 * @return array Default display options
	 */
	private static function get_default_display_options() {
		$main_options = self::get_main_options();
		
		return array(
			'phone_required' => $main_options['phone_required'] ?? true,
			'suppress_duplicates' => $main_options['suppress_duplicates'] ?? true,
			'use_divs' => isset($main_options['use_divs']) && $main_options['use_divs'],
			'show_time' => true,
			'show_date_start' => true,
			'show_date_end' => true,
			'show_headers' => true,
			'show_phone' => false,
			'show_email' => false,
			'shortcode_id' => false,
			'all_sheets_uri' => add_query_arg(array('sheet_id' => false, 'date' => false, 'signup_id' => false, 'task_id' => false)),
			'title_header' => apply_filters('pta_sus_public_output', __('Title', 'pta-volunteer-sign-up-sheets'), 'title_header'),
			'start_date_header' => apply_filters('pta_sus_public_output', __('Start Date', 'pta-volunteer-sign-up-sheets'), 'start_date_header'),
			'end_date_header' => apply_filters('pta_sus_public_output', __('End Date', 'pta-volunteer-sign-up-sheets'), 'end_date_header'),
			'open_spots_header' => apply_filters('pta_sus_public_output', __('Open Spots', 'pta-volunteer-sign-up-sheets'), 'open_spots_header'),
			'start_time_header' => apply_filters('pta_sus_public_output', __('Start Time', 'pta-volunteer-sign-up-sheets'), 'start_time_header'),
			'end_time_header' => apply_filters('pta_sus_public_output', __('End Time', 'pta-volunteer-sign-up-sheets'), 'end_time_header'),
			'item_details_header' => apply_filters('pta_sus_public_output', __('Item Details', 'pta-volunteer-sign-up-sheets'), 'item_details_header'),
			'item_qty_header' => apply_filters('pta_sus_public_output', __('Item Qty', 'pta-volunteer-sign-up-sheets'), 'item_qty_header'),
			'contact_label' => apply_filters('pta_sus_public_output', __('Contact:', 'pta-volunteer-sign-up-sheets'), 'contact_label'),
			'no_contact_message' => apply_filters('pta_sus_public_output', __('No Event Chair contact info provided', 'pta-volunteer-sign-up-sheets'), 'no_contact_message'),
			'hidden' => self::get_hidden_string(),
			'date' => null, // Current date filter, set per request
		);
	}

	/**
	 * Get hidden string for hidden sheets
	 * 
	 * @return string Hidden indicator string
	 */
	private static function get_hidden_string() {
		$hidden = '';
		// Allow admin or volunteer managers to view hidden sign up sheets
		if (current_user_can('manage_options') || current_user_can('manage_signup_sheets')) {
			$hidden = '<br/><span class="pta-sus-hidden">' . apply_filters('pta_sus_public_output', '(--' . __('Hidden!', 'pta-volunteer-sign-up-sheets') . '--)', 'hidden_notice') . '</span>';
		}
		return $hidden;
	}

	/**
	 * Get main options
	 * Returns cached options array, loading from database if needed
	 * 
	 * @return array Main options array
	 */
	private static function get_main_options() {
		if (self::$main_options === null) {
			self::$main_options = get_option('pta_volunteer_sus_main_options');
		}
		return self::$main_options;
	}

	/**
	 * Get display option
	 * Returns a display option value, initializing with defaults if needed
	 * 
	 * @param string $key Option key
	 * @param mixed $default Default value if option not set
	 * @return mixed Option value
	 */
	private static function get_display_option($key, $default = null) {
		if (!self::$initialized) {
			self::initialize(); // Auto-initialize with defaults
		}
		return isset(self::$display_options[$key]) ? self::$display_options[$key] : $default;
	}

	/**
	 * Get volunteer object
	 * Returns volunteer object, creating one if needed
	 * 
	 * Made public in 6.0.0 to allow extensions to access the volunteer object directly
	 * 
	 * @return PTA_SUS_Volunteer Volunteer object
	 */
	public static function get_volunteer() {
		if (self::$volunteer === null) {
			self::$volunteer = new PTA_SUS_Volunteer(get_current_user_id());
		}
		return self::$volunteer;
	}

	/**
	 * Get validation enabled status
	 * 
	 * @return bool Whether validation is enabled
	 */
	/**
	 * Check if validation is enabled
	 * 
	 * Note: Options should already be loaded by initialize(). The null check
	 * here is a fallback safety net in case this method is called before
	 * initialize() has been called. For best performance, ensure initialize()
	 * is called first.
	 * 
	 * @return bool True if validation is enabled
	 */
	private static function is_validation_enabled() {
		if (self::$validation_options === null) {
			self::$validation_options = get_option('pta_volunteer_sus_validation_options');
		}
		return isset(self::$validation_options['enable_validation']) && self::$validation_options['enable_validation'];
	}

	/**
	 * Get validation options
	 * 
	 * Note: Options should already be loaded by initialize(). The null check
	 * here is a fallback safety net in case this method is called before
	 * initialize() has been called. For best performance, ensure initialize()
	 * is called first.
	 * 
	 * @return array Validation options array
	 */
	private static function get_validation_options() {
		if (self::$validation_options === null) {
			self::$validation_options = get_option('pta_volunteer_sus_validation_options');
		}
		return self::$validation_options;
	}

	/**
	 * Get integration options
	 * 
	 * Note: Options should already be loaded by initialize(). The null check
	 * here is a fallback safety net in case this method is called before
	 * initialize() has been called. For best performance, ensure initialize()
	 * is called first.
	 * 
	 * @return array Integration options array
	 */
	private static function get_integration_options() {
		if (self::$integration_options === null) {
			self::$integration_options = get_option('pta_volunteer_sus_integration_options');
		}
		return self::$integration_options;
	}

	/**
	 * Set date filter
	 * Updates the date filter for display methods
	 * 
	 * @param string|null $date Date string or null to clear
	 */
	public static function set_date($date = null) {
		if (!self::$initialized) {
			self::initialize();
		}
		self::$display_options['date'] = $date;
	}

	/**
	 * Get default task column values
	 * Generates formatted display data for task columns (date, time, description, title)
	 * 
	 * @param PTA_SUS_Task|object $task Task object
	 * @param string $date Date string (yyyy-mm-dd format)
	 * @return array Associative array with keys: column-description, column-date, column-start-time, column-end-time, column-task
	 */
	public static function get_default_task_column_values($task, $date) {
		$display_date = $date != "0000-00-00" ? mysql2date( get_option('date_format'), $date, $translate = true ) : '';
		$start_time = !empty($task->time_start) ? pta_datetime(get_option("time_format"), strtotime($task->time_start)) : '';
		$end_time = !empty($task->time_end) ? pta_datetime(get_option("time_format"), strtotime($task->time_end)) : '';
		$description = wp_kses_post($task->description);
		$task_title = sanitize_text_field($task->title);
		$row_data = array();
		$row_data['column-description'] = $description;
		$row_data['column-date'] = $display_date;
		$row_data['column-start-time'] = $start_time;
		$row_data['column-end-time'] = $end_time;
		$row_data['column-task'] = $task_title;
		return $row_data;
	}

	/**
	 * Generate signup row data
	 * Creates formatted display data for a single signup row
	 * 
	 * @param PTA_SUS_Signup|object $signup Signup object
	 * @param PTA_SUS_Task|object $task Task object
	 * @param int $i Row number/index
	 * @param PTA_SUS_Volunteer|object $volunteer Volunteer object (for validation/permission checks)
	 * @param bool $show_names Whether to show volunteer names (default: true)
	 * @param bool $show_clear Whether to show clear link if volunteer can modify (default: false)
	 * @return array Associative array with row data keys: column-available-spots, column-phone, column-email, column-details, column-quantity, column-clear, extra-class
	 */
	public static function generate_signup_row_data($signup, $task, $i, $volunteer, $show_names = true, $show_clear = false) {
		$main_options = self::get_main_options();
		$show_full_name = isset($main_options['show_full_name']) && $main_options['show_full_name'];
		
		$row_data = array();

		if($show_names) {
			if($show_full_name) {
				$display_signup = wp_kses_post($signup->firstname.' '.$signup->lastname);
			} else {
				$display_signup = wp_kses_post($signup->firstname.' '.pta_sus_get_name_initials($signup->lastname));
			}
			$row_data['extra-class'] = 'signup';
		} else {
			$display_signup = apply_filters('pta_sus_public_output', __('Filled', 'pta-volunteer-sign-up-sheets'), 'task_spot_filled_message');
			$row_data['extra-class'] = 'filled';
		}

		$display_signup = apply_filters('pta_sus_display_signup_name', $display_signup, $signup);

		$clear_url = '';
		$clear_text = '';
		if ($volunteer && $volunteer->can_modify_signup($signup)) {
			$clear_args = array('sheet_id' => false, 'task_id' => false, 'signup_id' => (int)$signup->id);
			$raw_clear_url = add_query_arg($clear_args);
			$clear_url = wp_nonce_url($raw_clear_url, 'pta_sus_clear_signup');
			$clear_text = apply_filters('pta_sus_public_output', __('Clear', 'pta-volunteer-sign-up-sheets'), 'clear_signup_link_text');
		}

		$row_data['column-available-spots'] = '#'.$i.': '.$display_signup;
		$row_data['column-phone'] = $signup->phone;
		$row_data['column-email'] = $signup->email;
		$row_data['column-details'] = $signup->item;
		$row_data['column-quantity'] = (int)($signup->item_qty);

		if($show_clear && $volunteer && $volunteer->is_validated()) {
			$row_data['column-clear'] = '<a class="pta-sus-link clear-signup" href="'.esc_url($clear_url).'">'.esc_html($clear_text).'</a>';
		} else {
			$row_data['column-clear'] = '';
		}

		return apply_filters('pta_sus_generate_signup_row_data', $row_data, $signup, $task, $i);
	}

	/**
	 * Generate consolidated signup row data
	 * Creates a single consolidated row showing filled/remaining spots for a task
	 * 
	 * @param array $signups Array of signup objects
	 * @param int $task_qty Total quantity for the task
	 * @param string $task_url URL for signing up to the task
	 * @param bool $allow_signups Whether signups are allowed for this task
	 * @param PTA_SUS_Volunteer|object $volunteer Volunteer object (for validation/permission checks)
	 * @return array Associative array with keys: column-available-spots, extra-class
	 */
	public static function generated_consolidated_signup_row_data($signups, $task_qty, $task_url, $allow_signups, $volunteer) {
		$main_options = self::get_main_options();
		$row_data = array();
		$filled = 0;
		foreach($signups as $signup) {
			$filled += $signup->item_qty;
		}
		$remaining = $task_qty - $filled;

		if($remaining > 0) {
			$filled_text = apply_filters( 'pta_sus_public_output', sprintf(__('%d Filled', 'pta-volunteer-sign-up-sheets'),(int)$filled ), 'task_number_spots_filled_message', (int)$filled );
		} else {
			$filled_text = apply_filters('pta_sus_public_output', __('Filled', 'pta-volunteer-sign-up-sheets'), 'task_spots_full_message');
		}
		$remaining_text = apply_filters( 'pta_sus_public_output', sprintf(__('%d remaining: &nbsp;', 'pta-volunteer-sign-up-sheets'), (int)$remaining), 'task_number_remaining', (int)$remaining );
		$separator = apply_filters('pta_sus_public_output', ', ', 'task_spots_filled_remaining_separator');
		$display_consolidated = $filled_text;
		if($remaining > 0 && $allow_signups) {
			if( ! $main_options['login_required_signup'] || ($volunteer && $volunteer->is_validated()) ) {
				$display_consolidated .= esc_html($separator . $remaining_text).'<a class="pta-sus-link signup" href="'.esc_url($task_url).'">'.apply_filters( 'pta_sus_public_output', __('Sign up &raquo;', 'pta-volunteer-sign-up-sheets'), 'task_sign_up_link_text' ) . '</a>';
			} else {
				if ( isset( $main_options['show_login_link'] ) && true === $main_options['show_login_link'] ) {
					$signup_message = '<a class="pta-sus-link login" href="' . wp_login_url( get_permalink() ) . '" title="Login">' . esc_html( $main_options['login_signup_message'] ) . '</a>';
				} else {
					$signup_message = esc_html( $main_options['login_signup_message'] );
				}
				$display_consolidated .= ' - ' . $signup_message;
			}
		}
		$row_data['column-available-spots'] = $display_consolidated;
		$row_data['extra-class'] = 'consolidated';
		return $row_data;
	}

	/**
	 * Get task row data
	 * Generates all row data for displaying a task (signups, remaining spots, etc.)
	 * 
	 * @param PTA_SUS_Task|object $task Task object
	 * @param string $date Date string (yyyy-mm-dd format)
	 * @param int $sheet_id Sheet ID
	 * @param PTA_SUS_Volunteer|object|null $volunteer Optional volunteer object (for validation/permission checks). If null, uses the initialized volunteer from the static class.
	 * @param bool $show_clear Whether to show clear link if volunteer can modify (default: false)
	 * @param bool $no_signups Whether this is a no-signups task (default: false)
	 * @param array $signups Optional pre-loaded signups array (default: empty, will load if needed)
	 * @return array Array of row data arrays, each with column keys
	 */
	public static function get_task_row_data($task, $date, $sheet_id, $volunteer = null, $show_clear = false, $no_signups = false, $signups = array()) {
		// If no volunteer provided, use the initialized one from the static class
		if ($volunteer === null) {
			$volunteer = self::get_volunteer();
		}
		$main_options = self::get_main_options();
		$column_data = array();
		$show_all_slots = true;
		if(isset($main_options['show_remaining']) && $main_options['show_remaining']) {
			$show_all_slots = false;
		}
		$show_names = true;
		if($main_options['hide_volunteer_names']) {
			$show_names = false;
		}

		$show_details = false;

		if ( isset( $main_options['hide_details_qty'] ) && ! $main_options['hide_details_qty'] ) {
			if ( 'YES' === $task->need_details ) {
				$show_details = true;
			}
		}

		$default_data = self::get_default_task_column_values($task, $date);
		$display_date = $default_data['column-date'];
		$start_time = $default_data['column-start-time'];
		$end_time = $default_data['column-end-time'];
		$description = $default_data['column-description'];
		$task_title = $default_data['column-task'];

		$allow_signups = apply_filters( 'pta_sus_allow_signups', true, $task, $sheet_id, $date );
		$task_qty      = $no_signups ? 1 : absint( $task->qty );
		if(empty($signups)) {
			$signups = apply_filters( 'pta_sus_task_get_signups', PTA_SUS_Signup_Functions::get_signups_for_task( $task->id, $date ), $task->id, $date );
		}

		$one_row = false;
		if ( ! $show_all_slots && ! $show_details && ! $show_names && ! $no_signups ) {
			$one_row = true;
		}

		$task_args = array( 'sheet_id'  => $sheet_id, 'task_id'   => $task->id, 'date'      => $date, 'signup_id' => false );
		if ( is_page( $main_options['volunteer_page_id'] ) || ! $main_options['signup_redirect'] ) {
			$task_url = add_query_arg( $task_args );
		} else {
			$main_page_url = get_permalink( $main_options['volunteer_page_id'] );
			$task_url      = add_query_arg( $task_args, $main_page_url );
		}
		$task_url = apply_filters( 'pta_sus_task_signup_url', $task_url, $task, $sheet_id, $date );

		if ( $one_row ) {
			// Consolidated single row view
			$row_data = self::generated_consolidated_signup_row_data($signups, $task_qty, $task_url, $allow_signups, $volunteer);
			$default_data = self::get_default_task_column_values($task, $date);
			$row_data = array_merge($row_data, $default_data);
			$row_data['column-num'] = '';
			$column_data[] = apply_filters( 'pta_sus_task_consolidated_row_data', $row_data, $task, $date );
		} else {
			// Individual rows for each signup
			$i = 1;
			foreach ( $signups as $signup ) {
				$row_data = self::generate_signup_row_data( $signup, $task, $i, $volunteer, $show_names, $show_clear );
				$default_data = self::get_default_task_column_values($task, $date);
				$row_data = array_merge($row_data, $default_data);
				$row_data['column-num'] = $i;
				if ( 'YES' === $task->enable_quantities ) {
					$i += $signup->item_qty;
				} else {
					$i ++;
				}
				$column_data[] = apply_filters( 'pta_sus_task_signup_display_row_data', $row_data, $task, $signup, $date );
			}

			// Add remaining spots rows
			$remaining = $task_qty - ( $i - 1 );
			$start     = $i;

			if ( ! $show_all_slots ) {
				$start    = $remaining;
				$task_qty = $remaining;
			}
			if ( $remaining > 0 ) {
				// set up all the common data first to speed things up
				$row_data       = array();
				$signup_message = '';
				if ( ! $no_signups ) {
					if ( $allow_signups ) {
						if ( ! $main_options['login_required_signup'] || ($volunteer && $volunteer->is_validated()) ) {
							$signup_message = '<a class="pta-sus-link signup" href="' . esc_url( $task_url ) . '">' . apply_filters( 'pta_sus_public_output', __( 'Sign up &raquo;', 'pta-volunteer-sign-up-sheets' ), 'task_sign_up_link_text' ) . '</a>';
						} else {
							if ( isset( $main_options['show_login_link'] ) && true === $main_options['show_login_link'] ) {
								$signup_message = '<a class="pta-sus-link login" href="' . wp_login_url( get_permalink() ) . '" title="Login">' . esc_html( $main_options['login_signup_message'] ) . '</a>';
							} else {
								$signup_message = esc_html( $main_options['login_signup_message'] );
							}
						}
					}

					$row_data['column-phone'] = '';
					$row_data['column-email'] = '';

				}
				$row_data['column-clear']    = '';
				$row_data['column-details']  = '';
				$row_data['column-quantity'] = '';
				$row_data['column-task'] = $task_title;
				$row_data['extra-class'] = 'remaining';
				$row_data['column-description'] = $description;
				$row_data['column-date'] = $display_date;
				$row_data['column-start-time'] = $start_time;
				$row_data['column-end-time'] = $end_time;
				$row_data['column-num'] = $i;
				$row_data                = apply_filters( 'pta_sus_task_remaining_display_row_data', $row_data, $task, $date );
				for ( $i = $start; $i <= $task_qty; $i ++ ) {
					if ( ! $no_signups ) {
						if ( $show_all_slots ) {
							$row_data['column-available-spots'] = '#' . $i . ': ';
						} else {
							$row_data['column-available-spots'] = apply_filters( 'pta_sus_public_output', sprintf( __( '%d remaining: &nbsp;', 'pta-volunteer-sign-up-sheets' ), (int) $remaining ), 'task_number_remaining', (int) $remaining );
						}
						$row_data['column-available-spots'] .= $signup_message;
					}
					$column_data[] = $row_data;
				}
			}
		}

		return apply_filters('pta_sus_task_row_data', $column_data, $task, $date);
	}

	/**
	 * Display signup form for a task and date
	 * 
	 * @param int $task_id Task ID
	 * @param string $date Date (YYYY-MM-DD format)
	 * @param bool $skip_filled_check Whether to skip checking if spots are filled
	 * @param bool|null $filled Optional reference to filled flag (will be set if spots are filled)
	 * @return string HTML signup form
	 */
	public static function display_signup_form($task_id, $date, $skip_filled_check = false, &$filled = null) {
		// Auto-initialize if not already done
		if (!self::$initialized) {
			self::initialize();
		}

		$main_options = self::get_main_options();
		$validation_options = self::get_validation_options();
		$validation_enabled = self::is_validation_enabled();
		$volunteer = self::get_volunteer();
		$phone_required = self::get_display_option('phone_required', true);
		$suppress_duplicates = self::get_display_option('suppress_duplicates', true);
		$show_time = self::get_display_option('show_time', true);
		$start_time_header = self::get_display_option('start_time_header');
		$end_time_header = self::get_display_option('end_time_header');

		// Check login requirement
		if ($main_options['login_required_signup'] && !is_user_logged_in()) {
			$message = '<p class="pta-sus error">' . esc_html($main_options['login_signup_message']) . '</p>';
			if (isset($main_options['show_login_link']) && true === $main_options['show_login_link']) {
				$message .= '<p><a class="pta-sus-link login" href="' . wp_login_url(get_permalink()) . '" title="Login">' . esc_html($main_options['login_signup_message']) . '</a></p>';
			}
			return $message;
		}

		// Check validation requirement
		if ($validation_enabled && isset($validation_options['require_validation_to_signup']) && $validation_options['require_validation_to_signup']) {
			if (!$volunteer->is_validated()) {
				return pta_get_validation_required_message();
			}
		}

		// Check if form already displayed (suppress duplicates)
		if ($suppress_duplicates && apply_filters('pta_sus_signup_form_already_displayed', self::$signup_displayed, $task_id, $date)) {
			// don't show more than one signup form on a page,
			// if admin put multiple shortcodes on a page and didn't set to redirect to main shortcode page
			return '';
		}

		$task = apply_filters('pta_sus_public_signup_get_task', pta_sus_get_task($task_id), $task_id);
		if (!$task) {
			return '<p class="pta-sus error">' . apply_filters('pta_sus_public_output', __('Task not found.', 'pta-volunteer-sign-up-sheets'), 'task_not_found_error') . '</p>';
		}

		do_action('pta_sus_before_signup_form', $task, $date);

		$go_back_args = array('task_id' => false, 'date' => false, 'sheet_id' => $task->sheet_id);
		$go_back_url = apply_filters('pta_sus_signup_goback_url', add_query_arg($go_back_args));

		$available = $task->get_available_spots($date);
		if (!$skip_filled_check) {
			// Check if nothing available before showing the sign-up form, or if it was filled before they submitted the form
			if ($available < 1) {
				// Set filled flag if passed by reference
				if (null !== $filled) {
					$filled = true;
				}
				$message = '<p class="pta-sus error">' . apply_filters('pta_sus_public_output', __('All spots have already been filled.', 'pta-volunteer-sign-up-sheets'), 'no_spots_available_signup_error_message') . '</p>';
				$message .= '<p><a class="pta-sus-link go-back" href="' . esc_url($go_back_url) . '">' . esc_html(apply_filters('pta_sus_public_output', __('&laquo; go back to the Sign-Up Sheet', 'pta-volunteer-sign-up-sheets'), 'go_back_to_signup_sheet_text')) . '</a></p>';
				return $message;
			} elseif (null !== $filled && $filled) {
				return '<p><a class="pta-sus-link go-back" href="' . esc_url($go_back_url) . '">' . esc_html(apply_filters('pta_sus_public_output', __('&laquo; go back to the Sign-Up Sheet', 'pta-volunteer-sign-up-sheets'), 'go_back_to_signup_sheet_text')) . '</a></p>';
			}
		}

		// Give other plugins a chance to restrict signup access
		if (!apply_filters('pta_sus_can_signup', true, $task, $date)) {
			return '<p class="pta-sus error">' . apply_filters('pta_sus_public_output', __("You don't have permission to view this page.", 'pta-volunteer-sign-up-sheets'), 'no_permission_to_view_error_message') . '</p>';
		}

		if ("0000-00-00" === $date) {
			$show_date = false;
		} else {
			$show_date = pta_datetime(get_option('date_format'), strtotime($date));
		}
		$phone_required_attr = $phone_required ? 'required' : '';
		$details_required = isset($task->details_required) && "YES" === $task->details_required ? 'required' : '';

		$form = '<div class="pta-sus-sheets signup-form">';
		$form .= apply_filters('pta_sus_signup_page_before_form_title', '', $task, $date);
		$form .= '<h3 class="pta-sus sign-up-header">' . apply_filters('pta_sus_public_output', __('Sign Up', 'pta-volunteer-sign-up-sheets'), 'sign_up_form_heading') . '</h3>';
		$form .= '<h4 class="pta-sus sign-up-header">' . apply_filters('pta_sus_public_output', __('You are signing up for... ', 'pta-volunteer-sign-up-sheets'), 'you_are_signing_up_for') . '<br/><strong>' . esc_html($task->title) . '</strong> ';
		if ($show_date) {
			$form .= apply_filters('pta_sus_public_output', sprintf(__('on %s', 'pta-volunteer-sign-up-sheets'), $show_date), 'sign_up_for_date', $show_date);
		}
		$form .= '</h4>';
		if ($show_time && !empty($task->time_start)) {
			$form .= '<span class="time_start">' . esc_html($start_time_header) . ': ' . pta_datetime(get_option("time_format"), strtotime($task->time_start)) . '</span><br/>';
		}
		if ($show_time && !empty($task->time_end)) {
			$form .= '<span class="time_end">' . esc_html($end_time_header) . ': ' . pta_datetime(get_option("time_format"), strtotime($task->time_end)) . '</span><br/>';
		}
		if ($main_options['show_task_description_on_signup_form']) {
			if (!empty($task->description)) {
				$form .= '<div class="pta-sus task-description">' . wp_kses_post($task->description) . '</div>';
			}
		}
		$firstname_label = apply_filters('pta_sus_public_output', __('First Name', 'pta-volunteer-sign-up-sheets'), 'firstname_label');
		$lastname_label = apply_filters('pta_sus_public_output', __('Last Name', 'pta-volunteer-sign-up-sheets'), 'lastname_label');
		$email_label = apply_filters('pta_sus_public_output', __('E-mail', 'pta-volunteer-sign-up-sheets'), 'email_label');
		$validate_email_label = apply_filters('pta_sus_public_output', __('Confirm E-mail', 'pta-volunteer-sign-up-sheets'), 'confirm_email_label');
		$phone_label = apply_filters('pta_sus_public_output', __('Phone', 'pta-volunteer-sign-up-sheets'), 'phone_label');

		$form .= apply_filters('pta_sus_signup_form_before_form_fields', '<br/>', $task, $date);
		// Give other plugins a chance to modify signup data
		$posted = apply_filters('pta_sus_signup_posted_values', $_POST);

		// Initialize values array with posted data if it exists
		$values = array(
			'signup_user_id' => isset($posted['signup_user_id']) ? absint($posted['signup_user_id']) : '',
			'signup_firstname' => isset($posted['signup_firstname']) ? sanitize_text_field($posted['signup_firstname']) : '',
			'signup_lastname' => isset($posted['signup_lastname']) ? sanitize_text_field($posted['signup_lastname']) : '',
			'signup_email' => isset($posted['signup_email']) ? sanitize_email($posted['signup_email']) : '',
			'signup_validate_email' => isset($posted['signup_validate_email']) ? sanitize_email($posted['signup_validate_email']) : '',
			'signup_phone' => isset($posted['signup_phone']) ? sanitize_text_field($posted['signup_phone']) : ''
		);

		$readonly_first = "";
		$readonly_last = "";
		$readonly_email = "";

		// Only pre-fill if no form submission error and user is validated
		if (!isset($_POST['pta_sus_form_mode']) && $volunteer->is_validated()) {

			// Set readonly attributes if required
			if (isset($main_options['readonly_signup']) &&
				true === $main_options['readonly_signup'] &&
				!current_user_can('manage_signup_sheets')) {

				if (!empty($volunteer->get_firstname())) {
					$readonly_first = "readonly='readonly'";
				}
				if (!empty($volunteer->get_lastname())) {
					$readonly_last = "readonly='readonly'";
				}
				$readonly_email = "readonly='readonly'";
			}

			// Pre-populate form for regular users, or admins when live search is disabled
			if (!current_user_can('manage_signup_sheets') ||
				!$main_options['enable_signup_search']) {

				// Set values from volunteer object
				$values['signup_user_id'] = $volunteer->get_user_id();
				$values['signup_firstname'] = $volunteer->get_firstname();
				$values['signup_lastname'] = $volunteer->get_lastname();
				$values['signup_email'] = $volunteer->get_email();
				$values['signup_validate_email'] = $volunteer->get_email();

				// Handle phone separately since it's not in volunteer object
				if (!$main_options['no_phone'] && $volunteer->get_user_id() > 0) {
					$phone = apply_filters('pta_sus_user_phone',
						get_user_meta($volunteer->get_user_id(), 'billing_phone', true),
						get_user_by('id', $volunteer->get_user_id())
					);
					$values['signup_phone'] = $phone;
				}

				$values = apply_filters('pta_sus_prefilled_user_signup_values', $values);
			}
		}

		// Default User Fields
		if (!is_user_logged_in() && !$main_options['disable_signup_login_notice']) {
			$form .= '<p>' . apply_filters('pta_sus_public_output', __('If you have an account, it is strongly recommended that you <strong>login before you sign up</strong> so that you can view and edit all your signups.', 'pta-volunteer-sign-up-sheets'), 'signup_login_notice') . '</p>';
		}
		$form .= '
		<form name="pta_sus_signup_form" method="post" action="">
			<input type="hidden" name="signup_user_id" value="' . $values['signup_user_id'] . '" />
			<p>
				<label class="required" for="signup_firstname">' . $firstname_label . '</label>
				<input type="text" class="required" id="signup_firstname" name="signup_firstname" value="' . ((isset($values['signup_firstname'])) ? stripslashes(esc_attr($values['signup_firstname'])) : '') . '" ' . $readonly_first . ' required />
			</p>
			<p>
				<label class="required" for="signup_lastname">' . $lastname_label . '</label>
				<input type="text" class="required" id="signup_lastname" name="signup_lastname" value="' . ((isset($values['signup_lastname'])) ? stripslashes(esc_attr($values['signup_lastname'])) : '') . '" ' . $readonly_last . ' required />
			</p>
			<p>
				<label class="required" for="signup_email">' . $email_label . '</label>
				<input type="email" class="required email" id="signup_email" name="signup_email" value="' . ((isset($values['signup_email'])) ? esc_attr($values['signup_email']) : '') . '" ' . $readonly_email . ' required />
			</p>
			<p>
				<label class="required" for="signup_validate_email">' . $validate_email_label . '</label>
				<input type="email" class="required email" id="signup_validate_email" name="signup_validate_email" value="' . ((isset($values['signup_validate_email'])) ? esc_attr($values['signup_validate_email']) : '') . '" ' . $readonly_email . ' required />
			</p>';
		if (!$main_options['no_phone']) {
			$form .= '
            <p>
                <label class="' . esc_attr($phone_required_attr) . '" for="signup_phone">' . $phone_label . '</label>
                <input type="tel" class="phone ' . $phone_required_attr . '" id="signup_phone" name="signup_phone" value="' . ((isset($values['signup_phone'])) ? esc_attr($values['signup_phone']) : '') . '" ' . esc_attr($phone_required_attr) . ' />
            </p>';
		}

		$form .= apply_filters('pta_sus_signup_form_before_details_field', '', $task, $date);

		// Get the remaining fields, whether or not they are signed in

		// If details are needed for the task, show the field to fill in details.
		// Otherwise don't show the field, but fill it with a blank space
		if ($task->need_details == "YES") {
			$form .= '
            <p>
			    <label class="' . esc_attr($details_required) . '" for="signup_item">' . esc_html($task->details_text) . '</label>
			    <input type="text" id="signup_item" name="signup_item" value="' . ((isset($posted['signup_item'])) ? stripslashes(esc_attr($posted['signup_item'])) : '') . '" ' . esc_attr($details_required) . ' />
		    </p>';
		}
		if ($task->enable_quantities == "YES") {
			$form .= '<p>';
			$available = $task->get_available_spots($date);
			$available = apply_filters('pta_sus_signup_form_available_qty', $available, $task, $date);
			if ($available > 1) {
				$form .= '<label class="required" for="signup_item_qty">' . esc_html(apply_filters('pta_sus_public_output', sprintf(__('Item QTY (1 - %d): ', 'pta-volunteer-sign-up-sheets'), (int)$available), 'item_quantity_input_label', (int)$available)) . '</label>
                <input type="number" id="signup_item_qty" name="signup_item_qty" value="' . ((isset($posted['signup_item_qty'])) ? (int)($posted['signup_item_qty']) : '') . '" min="1" max="' . esc_attr($available) . '" />';
			} elseif (1 == $available) {
				$form .= '<strong>' . apply_filters('pta_sus_public_output', __('Only 1 remaining! Your quantity will be set to 1.', 'pta-volunteer-sign-up-sheets'), 'only_1_remaining') . '</strong>';
				$form .= '<input type="hidden" name="signup_item_qty" value="1" />';
			}
			$form .= '</p>';
		} else {
			$form .= '<input type="hidden" name="signup_item_qty" value="1" />';
		}

		$form .= apply_filters('pta_sus_signup_form_after_details_field', '', $task, $date);

		// Spam check and form submission
		$form .= '
			<div style="visibility:hidden"> 
	            <input name="website" type="text" size="20" />
	        </div>
	        <p class="submit">
	            <input type="hidden" name="signup_date" value="' . esc_attr($date) . '" />
                <input type="hidden" name="allow_duplicates" value="' . $task->allow_duplicates . '" />
	            <input type="hidden" name="signup_task_id" value="' . esc_attr($task_id) . '" />
	        	<input type="hidden" name="pta_sus_form_mode" value="submitted" />
	        	<input type="submit" name="Submit" class="button-primary" value="' . esc_attr(apply_filters('pta_sus_public_output', __('Sign me up!', 'pta-volunteer-sign-up-sheets'), 'signup_button_text')) . '" />
	            <a class="pta-sus-link go-back" href="' . esc_url($go_back_url) . '">' . esc_html(apply_filters('pta_sus_public_output', __('&laquo; go back to the Sign-Up Sheet', 'pta-volunteer-sign-up-sheets'), 'go_back_to_signup_sheet_text')) . '</a>
	        </p>
            ' . wp_nonce_field('pta_sus_signup', 'pta_sus_signup_nonce', true, false) . '
		</form>
		';
		$form .= '</div>';
		self::$signup_displayed = true; // prevent multiple forms on same page
		return $form;
	}

	/**
	 * Get sheets list HTML
	 * 
	 * @param array $sheets Array of sheet objects
	 * @param array $atts Optional attributes array
	 * @param string|null $date Optional date filter (overrides default)
	 * @return string HTML output of sheets list
	 */
	public static function get_sheets_list($sheets, $atts = array(), $date = null) {
		// Auto-initialize if not already done
		if (!self::$initialized) {
			self::initialize();
		}

		// Use provided date or get from display options
		if ($date === null) {
			$date = self::get_display_option('date');
		}

		// Get display options from static properties
		$title_header = self::get_display_option('title_header');
		$show_date_start = self::get_display_option('show_date_start', true);
		$start_date_header = self::get_display_option('start_date_header');
		$show_date_end = self::get_display_option('show_date_end', true);
		$end_date_header = self::get_display_option('end_date_header');
		$open_spots_header = self::get_display_option('open_spots_header');
		$use_divs = self::get_display_option('use_divs', false);
		$hidden = self::get_display_option('hidden', '');
		
		$return = apply_filters('pta_sus_before_sheet_list_table', '');
		$return .= '<div class="pta-sus-sheets main">';
		$columns = array();
		$columns['column-title'] = $title_header;
		if ($show_date_start) {
			$columns['column-date_start'] = $start_date_header;
		}
		if ($show_date_end) {
			$columns['column-date_end'] = $end_date_header;
		}
		$columns['column-open_spots'] = $open_spots_header;
		$columns['column-view_link'] = '';
		$columns = apply_filters('pta_sus_sheet_column_headers', $columns, $sheets, $atts);

		if ($use_divs) {
			$return .= '<div class="pta-sus-sheets-table pta-sus">';
			ob_start();
			include(PTA_VOLUNTEER_SUS_DIR . 'views/sheets-view-divs-header-row-html.php');
			$return .= ob_get_clean();
		} else {
			$return .= '<table class="pta-sus pta-sus-sheets"><thead class="pta-sus-table-head">';
			ob_start();
			include(PTA_VOLUNTEER_SUS_DIR . 'views/sheets-view-table-header-row-html.php');
			$return .= ob_get_clean();
			$return .= '</thead><tbody>';
		}

		foreach ($sheets AS $sheet) {
			if ('Single' == $sheet->type) {
				// if a date was passed in, skip any sheets not on that date
				if ($date && $date != $sheet->first_date) continue;
			} else {
				// Recurring or Multi-day sheets
				$dates = PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet($sheet->id);
				if ($date && !in_array($date, $dates)) continue;
			}
			if ($sheet->visible) {
				$is_hidden = '';
			} else {
				$is_hidden = $hidden;
			}
			$open_spots = (PTA_SUS_Sheet_Functions::get_sheet_total_spots($sheet->id) - PTA_SUS_Sheet_Functions::get_sheet_signup_count($sheet->id));
			$number_spots = $sheet->no_signups ? '' : absint($open_spots);
			$open_spots_display = apply_filters('pta_sus_public_output', $number_spots, 'sheet_number_open_spots', absint($open_spots));
			$sheet_args = array('sheet_id' => $sheet->id, 'date' => false, 'signup_id' => false, 'task_id' => false);
			$sheet_url = apply_filters('pta_sus_view_sheet_url', add_query_arg($sheet_args), $sheet);
			$ongoing_label = apply_filters('pta_sus_public_output', __('Ongoing', 'pta-volunteer-sign-up-sheets'), 'ongoing_event_type_start_end_label');
			$view_signup_text = apply_filters('pta_sus_public_output', __('View &amp; sign-up &raquo;', 'pta-volunteer-sign-up-sheets'), 'view_and_signup_link_text');
			$sheet_filled_text = apply_filters('pta_sus_public_output', __('Filled', 'pta-volunteer-sign-up-sheets'), 'sheet_filled_text');

			if ($sheet->no_signups) {
				$open_spots = 1;
				$view_signup_text = apply_filters('pta_sus_public_output', __('View Event &raquo;', 'pta-volunteer-sign-up-sheets'), 'view_event_link_text');
			}

			$view_signup_text = apply_filters('pta_sus_view_signup_text_for_sheet', $view_signup_text, $sheet);

			$title = '<a class="pta-sus-link view" href="' . esc_url($sheet_url) . '">' . esc_html($sheet->title) . '</a>' . $is_hidden;
			$start_date = (empty($sheet->first_date) || $sheet->first_date == '0000-00-00') ? $ongoing_label : pta_datetime(get_option('date_format'), strtotime($sheet->first_date));
			$end_date = (empty($sheet->last_date) || $sheet->last_date == '0000-00-00') ? $ongoing_label : pta_datetime(get_option('date_format'), strtotime($sheet->last_date));
			$view_link = ($open_spots > 0) ? '<a class="pta-sus-link view" href="' . esc_url($sheet_url) . '">' . esc_html($view_signup_text) . '</a>' : '&#10004; ' . esc_html($sheet_filled_text);
			// allow extensions to modify the view link
			$view_link = apply_filters('pta_sus_view_link_for_sheet', $view_link, $sheet, $open_spots);

			$row_data = array();
			$row_data['column-title'] = $title;
			if ($show_date_start) {
				$row_data['column-date_start'] = $start_date;
			}
			if ($show_date_end) {
				$row_data['column-date_end'] = $end_date;
			}
			$row_data['column-open_spots'] = $open_spots_display;
			$row_data['column-view_link'] = $view_link;
			$row_data = apply_filters('pta_sus_sheet_display_row_data', $row_data, $sheet, $date);

			if ($use_divs) {
				ob_start();
				include(PTA_VOLUNTEER_SUS_DIR . 'views/sheets-view-divs-row-html.php');
				$return .= ob_get_clean();
			} else {
				ob_start();
				include(PTA_VOLUNTEER_SUS_DIR . 'views/sheets-view-table-row-html.php');
				$return .= ob_get_clean();
			}
		}

		if ($use_divs) {
			$return .= '
                    </div>
                    </div>
                ';
		} else {
			$return .= '
                        </tbody>
                    </table>
                    </div>
                ';
		}

		$return .= apply_filters('pta_sus_after_sheet_list_table', '');
		return $return;
	}

	/**
	 * Display task list for a specific sheet and date
	 * Generates HTML output for displaying tasks with signups
	 * 
	 * @param int $sheet_id Sheet ID
	 * @param string $date Date string (yyyy-mm-dd format)
	 * @param bool $no_signups Whether this is a no-signups sheet (default: false)
	 * @param PTA_SUS_Sheet|object|false $sheet Optional pre-loaded sheet object (for optimization)
	 * @return string HTML output for task list
	 */
	public static function display_task_list($sheet_id, $date, $no_signups = false, $sheet = false) {
		$main_options = self::get_main_options();
		$volunteer = self::get_volunteer();
		$use_divs = self::get_display_option('use_divs', false);
		$show_phone = self::get_display_option('show_phone', false);
		$show_email = self::get_display_option('show_email', false);
		$item_details_header = self::get_display_option('item_details_header', '');
		$item_qty_header = self::get_display_option('item_qty_header', '');

		// Tasks
		$tasks = apply_filters('pta_sus_public_sheet_get_tasks', PTA_SUS_Task_Functions::get_tasks($sheet_id, $date), $sheet_id, $date);

		if (!$tasks) {
			return '<p>' . apply_filters('pta_sus_public_output', __('No tasks were found for ', 'pta-volunteer-sign-up-sheets'), 'no_tasks_found_for_date') . mysql2date(get_option('date_format'), $date, $translate = true) . '</p>';
		}
		$return = apply_filters('pta_sus_before_task_list', '', $tasks);

		$show_all_slots = true;
		if (isset($main_options['show_remaining']) && $main_options['show_remaining']) {
			$show_all_slots = false;
		}
		$show_names = true;
		if ($main_options['hide_volunteer_names']) {
			$show_names = false;
		}

		// Use provided sheet object if available, otherwise load it (optimization to avoid redundant loads)
		if (!$sheet || !is_object($sheet) || empty($sheet->id) || $sheet->id != $sheet_id) {
			$sheet = pta_sus_get_sheet($sheet_id);
		}
		$show_date = true;
		// Standardize type comparison (database stores as 'Single', 'Recurring', 'Multi-day', 'Ongoing')
		if ('Single' === $sheet->type && isset($main_options['hide_single_date_header']) && $main_options['hide_single_date_header']) {
			$show_date = false;
		}

		$return .= '<div class="pta-sus-sheets tasks">';

		foreach ($tasks as $task) {
			// Use the task's get_dates_array() method instead of manual parsing
			$task_dates = $task->get_dates_array();
			// Don't show tasks that don't include our date, if one was passed in
			if ($date && !in_array($date, $task_dates)) continue;

			$columns = array();

			$show_clear = pta_sus_show_clear($sheet, $date, $task->time_start);
			$show_details = false;
			$show_qty = false;
			if (isset($main_options['hide_details_qty']) && !$main_options['hide_details_qty']) {
				if ('YES' === $task->need_details) {
					$show_details = true;
				}
				if ('YES' === $task->enable_quantities && ($show_names || $show_all_slots || $show_details)) {
					$show_qty = true;
				}
			}

			$one_row = false;
			if (!$show_all_slots && !$show_details && !$show_names && !$no_signups) {
				$one_row = true;
			}

			if (!$no_signups) {
				$columns['column-available-spots'] = apply_filters('pta_sus_public_output', __('Available Spots', 'pta-volunteer-sign-up-sheets'), 'task_available_spots_header');

				if ($show_phone && !$one_row) {
					$columns['column-phone'] = apply_filters('pta_sus_public_output', __('Phone', 'pta-volunteer-sign-up-sheets'), 'task_phone_header');
				}

				if ($show_email && !$one_row) {
					$columns['column-email'] = apply_filters('pta_sus_public_output', __('Email', 'pta-volunteer-sign-up-sheets'), 'task_email_header');
				}
			}
			if ($show_details && !$one_row) {
				$columns['column-details'] = $item_details_header;
			}
			if ($show_qty && !$one_row) {
				$columns['column-quantity'] = $item_qty_header;
			}

			if ($volunteer->is_validated() && !$one_row && $show_clear) {
				$columns['column-clear'] = '';
			}

			$i = 1;
			$signups = apply_filters('pta_sus_task_get_signups', PTA_SUS_Signup_Functions::get_signups_for_task($task->id, $date), $task->id, $date);

			// Set qty to one for no_signups sheets
			$task_qty = $no_signups ? 1 : absint($task->qty);

			// Allow extensions to add/modify column headers
			$columns = apply_filters('pta_sus_task_column_headers', $columns, $task, $date, $one_row);

			// Prepare variables for template - extract to ensure they're in scope
			$template_vars = array(
				'task' => $task,
				'date' => $date,
				'show_date' => $show_date,
				'show_time' => self::get_display_option('show_time', true),
				'start_time_header' => self::get_display_option('start_time_header', ''),
				'end_time_header' => self::get_display_option('end_time_header', ''),
			);
			extract($template_vars);

			ob_start();
			include(PTA_VOLUNTEER_SUS_DIR . 'views/task-view-header-html.php');
			$return .= ob_get_clean();

			if ($use_divs) {
				$additional_div_class = apply_filters('pta_sus_additional_task_table_class_divs', '');
				$return .= '<div class="pta-sus-tasks-table ' . esc_attr($additional_div_class) . '">';
				ob_start();
				include(PTA_VOLUNTEER_SUS_DIR . 'views/task-view-divs-header-row-html.php');
				$return .= ob_get_clean();
			} else {
				$additional_table_class = apply_filters('pta_sus_additional_task_table_class', '');
				$return .= '<table class="pta-sus pta-sus-tasks ' . esc_attr($additional_table_class) . '"><thead class="pta-sus-table-head">';
				ob_start();
				include(PTA_VOLUNTEER_SUS_DIR . 'views/task-view-table-header-row-html.php');
				$return .= ob_get_clean();
				$return .= '</thead><tbody>';
			}

			$column_data = self::get_task_row_data($task, $date, $sheet_id, $volunteer, $show_clear, $no_signups);

			$column_data = apply_filters('pta_sus_task_display_rows', $column_data, $task, $date, $one_row);

			if ($use_divs) {
				ob_start();
				include(PTA_VOLUNTEER_SUS_DIR . 'views/task-view-divs-rows-html.php');
				$return .= ob_get_clean();
				$return .= '</div>'; // close "table" divs
			} else {
				ob_start();
				include(PTA_VOLUNTEER_SUS_DIR . 'views/task-view-table-rows-html.php');
				$return .= ob_get_clean();
				$return .= '</tbody></table>'; // close body and table
			}

			$return .= apply_filters('pta_sus_after_single_task_list', '', $task, $date);
		}

		$return .= apply_filters('pta_sus_after_all_tasks_list', '', $tasks);

		$return .= '</div>'; // close wrapper for both table and divs layouts

		return $return;
	}

	/**
	 * Get single sheet display
	 * Generates HTML output for displaying a single sheet with all its tasks
	 * 
	 * @param int $id Sheet ID
	 * @return string HTML output for single sheet
	 */
	public static function get_single_sheet($id) {
		$main_options = self::get_main_options();
		$integration_options = self::get_integration_options();
		$volunteer = self::get_volunteer();
		$date = self::get_display_option('date', null);
		$show_headers = self::get_display_option('show_headers', true);
		$shortcode_id = self::get_display_option('shortcode_id', false);
		$all_sheets_uri = self::get_display_option('all_sheets_uri', '');
		$contact_label = self::get_display_option('contact_label', '');
		$no_contact_message = self::get_display_option('no_contact_message', '');
		$submitted = self::get_display_option('submitted', false);
		$err = self::get_display_option('err', 0);
		$success = self::get_display_option('success', false);

		$return = '';
		// Display Individual Sheet
		$sheet = apply_filters('pta_sus_display_individual_sheet', pta_sus_get_sheet($id), $id);
		if ($sheet === false) {
			$return .= '<p class="pta-sus error">' . apply_filters('pta_sus_public_output', __("Sign-up sheet not found.", 'pta-volunteer-sign-up-sheets'), 'sheet_not_found_error_message') . '</p>';
			return $return;
		} else {
			// Check if the sheet is visible and don't show unless it's an admin user
			if ($sheet->trash || (!apply_filters('pta_sus_public_sheet_visible', $sheet->visible, $sheet) && !current_user_can('manage_signup_sheets'))) {
				$return .= '<p class="pta-sus error">' . apply_filters('pta_sus_public_output', __("You don't have permission to view this page.", 'pta-volunteer-sign-up-sheets'), 'no_permission_to_view_error_message') . '</p>';
				return $return;
			}

			// check if there are any future dates for the sheet
			$future_dates = false;
			if ($date && $date >= current_time('Y-m-d')) {
				$task_dates = array($date);
				$future_dates = true;
			} else {
				$task_dates = PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet($sheet->id);
				foreach ($task_dates as $tdate) {
					if ($tdate >= current_time('Y-m-d') || "0000-00-00" === $tdate) {
						$future_dates = true;
						break;
					}
				}
			}

			// Allow extensions to choose if they want to show header info if not on the main volunteer page
			$show_headers = apply_filters('pta_sus_show_sheet_headers', $show_headers, $sheet);
			$return .= apply_filters('pta_sus_before_display_single_sheet', '', $sheet);

			// Show the view all link only if no sheet ID is specified within the shortcode
			if ($shortcode_id !== $id) {
				$view_all_text = apply_filters('pta_sus_public_output', __('&laquo; View all Sign-up Sheets', 'pta-volunteer-sign-up-sheets'), 'view_all_sheets_link_text');
				$view_all_url = apply_filters('pta_sus_view_all_sheets_url', $all_sheets_uri, $sheet);
				$return .= '<p><a class="pta-sus-link view-all" href="' . esc_url($view_all_url) . '">' . esc_html($view_all_text) . '</a></p>';
			}

			// *****************************************************************************
			// Show headers only if show_headers is true
			if ($show_headers) {
				// AFTER MAKING PTA MEMBER DIRECTORY A CLASS, WE CAN ALSO CHECK IF IT EXISTS
				if (isset($integration_options['enable_member_directory']) && true === $integration_options['enable_member_directory'] && function_exists('pta_member_directory_init') && '' != $sheet->position) {
					// Create Contact Form link
					if ($position = get_term_by('slug', $sheet->position, 'member_category')) {
						if (isset($integration_options['contact_page_id']) && 0 < $integration_options['contact_page_id']) {
							$contact_url = get_permalink($integration_options['contact_page_id']) . '?id=' . esc_html($sheet->position);
							$display_chair = esc_html($contact_label) . ' <a class="pta-sus-link contact" href="' . esc_url($contact_url) . '">' . esc_html($position->name) . '</a>';
						} elseif (isset($integration_options['directory_page_id']) && 0 < $integration_options['directory_page_id']) {
							$contact_url = get_permalink($integration_options['directory_page_id']) . '?id=' . $sheet->position;
							$display_chair = esc_html($contact_label) . ' <a class="pta-sus-link contact" href="' . esc_url($contact_url) . '">' . esc_html($position->name) . '</a>';
						} else {
							$display_chair = esc_html($no_contact_message);
						}
					} else {
						$display_chair = esc_html($no_contact_message);
					}
				} else {
					$chair_names = pta_sus_get_chair_names_html($sheet->chair_name);
					// Check if there is more than one chair name to display either Chair or Chairs
					$names = explode(',', sanitize_text_field($sheet->chair_name));
					$count = count($names);
					if ($count > 1) {
						$display_chair = apply_filters('pta_sus_public_output', __('Event Chairs:', 'pta-volunteer-sign-up-sheets'), 'event_chairs_label_plural') . ' <a class="pta-sus-link contact" href="mailto:' . esc_attr($sheet->chair_email) . '">' . esc_html($chair_names) . '</a>';
					} elseif (1 == $count && '' != $sheet->chair_name && '' != $sheet->chair_email) {
						$display_chair = apply_filters('pta_sus_public_output', __('Event Chair:', 'pta-volunteer-sign-up-sheets'), 'event_chair_label_singular') . ' <a class="pta-sus-link contact" href="mailto:' . esc_attr($sheet->chair_email) . '">' . esc_html($chair_names) . '</a>';
					} else {
						$display_chair = esc_html($no_contact_message);
					}
				}

				$display_chair = apply_filters('pta_sus_display_chair_contact', $display_chair, $sheet);

				$return .= '
                        <div class="pta-sus-sheet">
                            <h2>' . esc_html($sheet->title) . '</h2>
                    ';
				if (!$main_options['hide_contact_info']) {
					$return .= '<h2>' . $display_chair . '</h2>';
				}
			} else {
				$return .= '<div class="pta-sus-sheet">';
			}
			if (!$sheet->visible && current_user_can('manage_signup_sheets')) {
				$return .= '<p class="pta-sus-hidden">' . apply_filters('pta_sus_public_output', __('This sheet is currently hidden from the public.', 'pta-volunteer-sign-up-sheets'), 'sheet_hidden_message') . '</p>';
			}

			// Display Sign-up Form
			if (!$submitted || $err) {
				if (isset($_GET['task_id']) && isset($_GET['date'])) {
					do_action('pta_sus_before_display_signup_form', $_GET['task_id'], $_GET['date']);
					return self::display_signup_form($_GET['task_id'], $_GET['date']);
				}
			}

			$return .= apply_filters('pta_sus_single_sheet_display_before_details', '', $sheet);

			// Sheet Details
			if (!$submitted || $success || $err) {

				// Make sure there are some future dates before showing anything
				if ($future_dates) {
					// Only show details if there is something to show, and show headers is true
					if ('' != $sheet->details && $show_headers) {
						$return .= '<h3 class="pta-sus details-header">' . apply_filters('pta_sus_public_output', __('DETAILS:', 'pta-volunteer-sign-up-sheets'), 'sheet_details_heading') . '</h3>';
						$return .= wp_kses_post($sheet->details);
					}
					$open_spots = (PTA_SUS_Sheet_Functions::get_sheet_total_spots($sheet->id) - PTA_SUS_Sheet_Functions::get_sheet_signup_count($sheet->id));
					if ($open_spots > 0 && !$sheet->no_signups) {
						$return .= '<h3 class="pta-sus sign-up-header">' . apply_filters('pta_sus_public_output', __('Sign up below...', 'pta-volunteer-sign-up-sheets'), 'sign_up_below') . '</h3>';
					} elseif (!$sheet->no_signups) {
						$return .= '<h3 class="pta-sus filled-header">' . apply_filters('pta_sus_public_output', __('All spots have been filled.', 'pta-volunteer-sign-up-sheets'), 'sheet_all_spots_filled') . '</h3>';
					}

					$task_dates = apply_filters('pta_sus_sheet_task_dates', $task_dates, $sheet->id);
					$alt_view = apply_filters('pta_sus_display_alt_task_list', '', $sheet, $task_dates);
					if ('' === $alt_view) {
						foreach ($task_dates as $tdate) {
							if ("0000-00-00" != $tdate && $tdate < current_time('Y-m-d')) continue; // Skip dates that have passed already
							// Pass the already-loaded sheet object to avoid redundant load
							$return .= self::display_task_list($sheet->id, $tdate, $sheet->no_signups, $sheet);
						}
					} else {
						$return .= $alt_view;
					}
				}

				$return .= '</div>';
			}
		}
		return $return;
	}

	/**
	 * Get user signups list
	 * Displays a list of signups for the current user with clear links
	 * 
	 * @param array $atts Shortcode attributes
	 * @return string HTML output for user signups list
	 */
	public static function get_user_signups_list($atts = array()) {
		$main_options = self::get_main_options();
		$validation_options = self::get_validation_options();
		$volunteer = self::get_volunteer();
		$use_divs = self::get_display_option('use_divs', false);
		$title_header = self::get_display_option('title_header', '');
		$date_header = self::get_display_option('date_header', '');
		$task_item_header = self::get_display_option('task_item_header', '');
		$start_time_header = self::get_display_option('start_time_header', '');
		$end_time_header = self::get_display_option('end_time_header', '');
		$item_details_header = self::get_display_option('item_details_header', '');
		$item_qty_header = self::get_display_option('item_qty_header', '');
		$na_text = apply_filters('pta_sus_public_output', __('N/A', 'pta-volunteer-sign-up-sheets'), 'not_applicable_text');

		$return = '';
		if (!$volunteer->is_validated()) {
			if (self::$validation_enabled && isset($validation_options['enable_user_validation_form']) && $validation_options['enable_user_validation_form']) {
				if ((isset($_COOKIE['pta_sus_validation_form_submitted']) && empty($_COOKIE['pta_sus_validation_cleared'])) || self::$validation_sent) {
					$minutes = $validation_options['validation_form_resubmission_minutes'] ?? 1;
					$return .= '<p>' . apply_filters('pta_sus_public_output', sprintf(__('User Validation email has been sent. Please check your email. If you did not receive the email, you can return and submit the form again after %d minutes.', 'pta-volunteer-sign-up-sheets'), $minutes), 'validation_form_already_submitted_message', absint($minutes)) . '</p>';
					return $return;
				}
				return pta_get_validation_form();
			}
		}
		$signups = $volunteer->get_detailed_signups();
		/**
		 * @var string $show_time
		 */
		extract(shortcode_atts(array(
			'show_time' => 'yes'
		), $atts, 'pta_user_signups'));
		$times = 'no' !== $show_time;
		$details = !$main_options['hide_signups_details_qty'];

		if (!empty($signups)) {
			$return .= apply_filters('pta_sus_before_user_signups_list_headers', '');
			$return .= '<h3 class="pta-sus user-heading">' . apply_filters('pta_sus_public_output', __('You have signed up for the following', 'pta-volunteer-sign-up-sheets'), 'user_signups_list_headers_h3') . '</h3>';
			$return .= '<h4 class="pta-sus user-heading">' . apply_filters('pta_sus_public_output', __('Click on Clear to remove yourself from a signup.', 'pta-volunteer-sign-up-sheets'), 'user_signups_list_headers_h4') . '</h4>';
			$return .= apply_filters('pta_sus_before_user_signups_list_table', '');
			$return .= '<div class="pta-sus-sheets user">';

			if ($use_divs) {
				$return .= '<div class="pta-sus-sheets-table">
                            <div class="pta-sus-sheets-row pta-sus-header-row">
                                <div class="column-title head">' . esc_html($title_header) . '</div>
                                <div class="column-date head">' . esc_html($date_header) . '</div>
                                <div class="column-task head">' . esc_html($task_item_header) . '</div>';
			} else {
				$return .= '
                        <table class="pta-sus pta-sus-sheets">
                        <thead class="pta-sus-table-head">
                            <tr class="pta-sus pta-sus-header-row">
                                <th class="column-title">' . esc_html($title_header) . '</th>
                                <th class="column-date">' . esc_html($date_header) . '</th>
                                <th class="column-task">' . esc_html($task_item_header) . '</th>';
			}

			$return .= apply_filters('pta_sus_user_signups_list_headers_after_task', '', $use_divs);

			if ($times) {
				if ($use_divs) {
					$return .= '
                                <div class="column-start-time head" >' . esc_html($start_time_header) . '</div>
                                <div class="column-end-time head" >' . esc_html($end_time_header) . '</div>';
				} else {
					$return .= '
                                <th class="column-time start" >' . esc_html($start_time_header) . '</th>
                                <th class="column-time end" >' . esc_html($end_time_header) . '</th>';
				}
			}

			if ($details) {
				if ($use_divs) {
					$return .= '
	                                <div class="column-details head" >' . esc_html($item_details_header) . '</div>
	                                <div class="column-qty head" >' . esc_html($item_qty_header) . '</div>';
				} else {
					$return .= '
	                                <th class="column-details" >' . esc_html($item_details_header) . '</th>
	                                <th class="column-qty" >' . esc_html($item_qty_header) . '</th>';
				}
			}

			if ($use_divs) {
				$return .= '<div class="column-clear_link head">&nbsp;</div>';
			} else {
				$return .= '<th class="column-clear_link">&nbsp;</th>';
			}

			$return .= apply_filters('pta_sus_user_signups_list_headers_after_clear', '', $use_divs);

			if ($use_divs) {
				$return .= '</div>';
			} else {
				$return .= '</tr>';
			}

			if (!$use_divs) {
				$return .= '</thead><tbody>';
			}

			foreach ($signups as $signup) {
				$sheet = false;
				$url = false;
				if (isset($main_options['volunteer_page_id']) && absint($main_options['volunteer_page_id']) > 0) {
					$url = get_permalink(absint($main_options['volunteer_page_id']));
				}
				if ($url && $signup->sheet_id) {
					// pta_sus_get_sheet() uses class-level caching, so repeated calls for same ID return cached object
					$sheet = pta_sus_get_sheet($signup->sheet_id);
					if ($sheet) {
						$sheet_args = array('sheet_id' => $signup->sheet_id, 'date' => false, 'signup_id' => false, 'task_id' => false);
						$url = apply_filters('pta_sus_view_sheet_url', add_query_arg($sheet_args, $url), $sheet);
					}
				}

				if ($sheet && pta_sus_show_clear($sheet, $signup->signup_date, $signup->time_start)) {
					$clear_args = array('sheet_id' => false, 'task_id' => false, 'signup_id' => (int)$signup->id);
					$raw_clear_url = add_query_arg($clear_args);
					$clear_url = wp_nonce_url($raw_clear_url, 'pta_sus_clear_signup');
					$clear_text = apply_filters('pta_sus_public_output', __('Clear', 'pta-volunteer-sign-up-sheets'), 'clear_signup_link_text');
				} else {
					$clear_url = '';
					$clear_text = '';
				}

				if ($url) {
					$title = '<a href="' . $url . '" title="' . esc_attr($signup->title) . '">' . esc_html($signup->title) . '</a>';
				} else {
					$title = esc_html($signup->title);
				}

				if ($use_divs) {
					$return .= '<div class="pta-sus-sheets-row">
                            <div class="column-title">' . $title . '</div>
                            <div class="column-date">' . (($signup->signup_date == "0000-00-00") ? esc_html($na_text) : pta_datetime(get_option("date_format"), strtotime($signup->signup_date))) . '</div>
                            <div class="column-task" >' . esc_html($signup->task_title) . '</div>';
				} else {
					$return .= '<tr class="pta-sus signup">
                            <td class="pta-sus title-td" data-label="' . esc_attr($title_header) . '">' . $title . '</td>
                            <td class="pta-sus date-td" data-label="' . esc_attr($date_header) . '">' . (($signup->signup_date == "0000-00-00") ? esc_html($na_text) : pta_datetime(get_option("date_format"), strtotime($signup->signup_date))) . '</td>
                            <td class="pta-sus task-td" data-label="' . esc_attr($task_item_header) . '">' . esc_html($signup->task_title) . '</td>';
				}

				$return .= apply_filters('pta_sus_user_signups_list_content_after_task', '', $signup, $use_divs);

				if ($times) {
					if ($use_divs) {
						$return .= '
                            <div class="column-start-time" >' . (empty($signup->time_start) ? esc_html($na_text) : pta_datetime(get_option("time_format"), strtotime($signup->time_start))) . '</div>
                            <div class="column-end-time" >' . (empty($signup->time_end) ? esc_html($na_text) : pta_datetime(get_option("time_format"), strtotime($signup->time_end))) . '</div>';
					} else {
						$return .= '
                            <td class="pta-sus start-time-td" data-label="' . esc_attr($start_time_header) . '" >' . (empty($signup->time_start) ? esc_html($na_text) : pta_datetime(get_option("time_format"), strtotime($signup->time_start))) . '</td>
                            <td class="pta-sus end-time-td" data-label="' . esc_attr($end_time_header) . '" >' . (empty($signup->time_end) ? esc_html($na_text) : pta_datetime(get_option("time_format"), strtotime($signup->time_end))) . '</td>';
					}
				}
				if ($details) {
					if ($use_divs) {
						$return .= '
	                            <div class="column-item" >' . (("" !== $signup->item) ? esc_html($signup->item) : esc_html($na_text)) . '</div>
	                            <div class="column-qty" >' . (("" !== $signup->item_qty) ? (int)$signup->item_qty : esc_html($na_text)) . '</div>';
					} else {
						$return .= '
	                            <td class="pta-sus item-td" data-label="' . esc_attr($item_details_header) . '" >' . (("" !== $signup->item) ? esc_html($signup->item) : esc_html($na_text)) . '</td>
	                            <td class="pta-sus qty-td" data-label="' . esc_attr($item_qty_header) . '" >' . (("" !== $signup->item_qty) ? (int)$signup->item_qty : esc_html($na_text)) . '</td>';
					}
				}
				if ($use_divs) {
					$return .= '<div class="column-clear" ><a class="pta-sus-link clear-signup clear-signup-link" href="' . esc_url($clear_url) . '">' . esc_html($clear_text) . '</a></div>';
				} else {
					$return .= '<td class="pta-sus clear-td" data-label="" ><a class="pta-sus-link clear-signup clear-signup-link" href="' . esc_url($clear_url) . '">' . esc_html($clear_text) . '</a></td>';
				}

				$return .= apply_filters('pta_sus_user_signups_list_content_after_clear', '', $signup, $use_divs);

				if ($use_divs) {
					$return .= '</div>';
				} else {
					$return .= '</tr>';
				}
			}

			if ($use_divs) {
				$return .= '</div></div>';
			} else {
				$return .= '</tbody></table></div>';
			}
		}
		$return .= apply_filters('pta_sus_after_user_signups_list_table', '');
		return $return;
	}

	/**
	 * Display sheet (main shortcode/block handler)
	 * Processes shortcode attributes and URL arguments to display either all sheets or a single sheet
	 * 
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public static function display_sheet($atts = array()) {
		static $instance_count = 0;
		$instance_count++;
		$container_id = 'pta-sus-container-' . $instance_count;

		// Store attributes for AJAX use
		if (!defined('DOING_AJAX')) {
			wp_add_inline_script('pta-sus-ajax', "if(typeof pta_sus_instances === 'undefined') { var pta_sus_instances = {}; } pta_sus_instances['{$container_id}'] = " . json_encode($atts) . ";", 'before');
		}
		$main_options = self::get_main_options();
		$validation_options = self::get_validation_options();
		$volunteer = self::get_volunteer();
		$all_sheets_uri = self::get_display_option('all_sheets_uri', '');

		do_action('pta_sus_before_process_shortcode', $atts);
		$return = '';
		if (isset($main_options['enable_test_mode']) && true === $main_options['enable_test_mode']) {
			if (current_user_can('manage_options') || current_user_can('manage_signup_sheets')) {
				$return .= '<p class="pta-sus error">' . apply_filters('pta_sus_public_output', __('Volunteer Sign-Up Sheets are in TEST MODE', 'pta-volunteer-sign-up-sheets'), 'admin_test_mode_message') . '</p>';
			} elseif (is_page($main_options['volunteer_page_id'])) {
				return esc_html($main_options['test_mode_message']);
			} else {
				return '';
			}
		}
		if (isset($main_options['login_required']) && true === $main_options['login_required']) {
			if (!is_user_logged_in()) {
				$message = '<p class="pta-sus error">' . esc_html($main_options['login_required_message']) . '</p>';
				if (isset($main_options['show_login_link']) && true === $main_options['show_login_link']) {
					$message .= '<p><a class="pta-sus-link login" href="' . wp_login_url(get_permalink()) . '" title="Login">' . apply_filters('pta_sus_public_output', __("Login", "pta_volunteer_sus"), 'login_link_text') . '</a></p>';
				}
				return $message;
			}
		}
		if (self::$validation_enabled && isset($validation_options['require_validation_to_view']) && true === $validation_options['require_validation_to_view']) {
			if (!$volunteer->is_validated()) {
				return pta_get_validation_required_message();
			}
		}
		extract(shortcode_atts(array(
			'id' => '',
			'date' => '',
			'show_time' => 'yes',
			'show_phone' => 'no',
			'show_email' => 'no',
			'show_headers' => 'yes',
			'show_date_start' => 'yes',
			'show_date_end' => 'yes',
			'order_by' => 'first_date',
			'order' => 'ASC',
			'list_title' => __('Current Volunteer Sign-up Sheets', 'pta-volunteer-sign-up-sheets'),
			'author_id' => '',
			'author_email' => '',
		), $atts, 'pta_sign_up_sheet'));
		/**
		 * Variables extracted from shortcode, with above default values
		 * @var mixed $id
		 * @var mixed $date
		 * @var string $show_time
		 * @var string $show_phone
		 * @var string $show_email
		 * @var string $show_headers
		 * @var string $order_by
		 * @var string $order
		 * @var string $list_title
		 * @var string $show_date_start
		 * @var string $show_date_end
		 * @var string $author_id
		 * @var string $author_email
		 */
		// Allow plugins or themes to modify shortcode parameters
		$id = apply_filters('pta_sus_shortcode_id', $id);
		if ('' == $id) {
			$id = false;
		}
		$shortcode_id = $id;
		$date_filter = apply_filters('pta_sus_shortcode_date', $date);
		if ('' == $date_filter) {
			$date_filter = false;
		}
		if ('' == $list_title) {
			$list_title = __('Current Volunteer Sign-up Sheets', 'pta-volunteer-sign-up-sheets');
		}
		$list_title = apply_filters('pta_sus_shortcode_list_title', $list_title);
		$order_by = apply_filters('pta_sus_shortcode_order_by', $order_by);
		$order = apply_filters('pta_sus_shortcode_order', $order);
		$show_time_bool = $show_time === 'no' ? false : true;
		$show_phone_bool = $show_phone === 'yes' ? true : false;
		$show_email_bool = $show_email === 'yes' ? true : false;
		$show_headers_bool = $show_headers === 'no' ? false : true;
		$show_date_start_bool = $show_date_start === 'no' ? false : true;
		$show_date_end_bool = $show_date_end === 'no' ? false : true;

		// Update the display helper class with current shortcode settings
		self::initialize(
			array(
				'show_time' => $show_time_bool,
				'show_phone' => $show_phone_bool,
				'show_email' => $show_email_bool,
				'show_headers' => $show_headers_bool,
				'show_date_start' => $show_date_start_bool,
				'show_date_end' => $show_date_end_bool,
				'shortcode_id' => $shortcode_id,
				'date' => $date_filter,
			),
			$volunteer
		);

		if ($id === false && !empty($_GET['sheet_id']) && !self::$success && !self::$cleared) {
			$id = (int)$_GET['sheet_id'];
		}

		if ($date_filter === false && !empty($_GET['date']) && !self::$success && !self::$cleared) {
			// Make sure it's a valid date in our format first - Security check
			if (pta_sus_check_date($_GET['date'])) {
				$date_filter = $_GET['date'];
				// Update date in helper class
				self::set_date($date_filter);
			}
		}

		// Give other plugins a chance to create their own output and not go any further
		$alt_display = apply_filters('pta_sus_main_shortcode_alt_display', '', $atts);
		if (!empty($alt_display)) {
			return $alt_display;
		}

		// Give other plugins a chance to restrict access to the sheets list
		if (!apply_filters('pta_sus_can_view_sheets', true, $atts)) {
			PTA_SUS_Messages::add_error(apply_filters('pta_sus_public_output', __("You don't have permission to view this page.", 'pta-volunteer-sign-up-sheets'), 'no_permission_to_view_error_message'));
			return PTA_SUS_Messages::show_messages();
		}

		$return = apply_filters('pta_sus_before_display_sheets', $return, $id, $date_filter);
		do_action('pta_sus_begin_display_sheets', $id, $date_filter);

		if (!self::$messages_displayed || !self::get_display_option('suppress_duplicates', true)) {
			// only show messages above first list if multiple shortcodes on one page
			$return .= PTA_SUS_Messages::show_messages();
			PTA_SUS_Messages::clear_messages();
			self::$messages_displayed = true;
		}

		if ($id === false) {
			// Display all active
			// allow modification of list title header
			$title_header = '<h2 class="pta-sus-list-title">' . apply_filters('pta_sus_public_output', esc_html($list_title), 'sheet_list_title') . '</h2>';
			$title_header = apply_filters('pta_sus_sheet_list_title_header_html', $title_header, $list_title);
			$return .= $title_header;
			
			// Build args for get_sheets_by_args() to support author filtering
			$args = array(
				'trash' => false,
				'active_only' => true,
				'show_hidden' => self::$show_hidden,
				'order_by' => $order_by,
				'order' => $order,
			);
			
			// Add author filtering if provided in shortcode attributes
			if ( ! empty( $author_id ) ) {
				$args['author_id'] = absint( $author_id );
			}
			if ( ! empty( $author_email ) ) {
				$args['author_email'] = sanitize_email( $author_email );
			}
			
			$sheets = PTA_SUS_Sheet_Functions::get_sheets_by_args( $args );

			// Move ongoing sheets to bottom of list if that setting is checked
			if ($main_options['show_ongoing_last']) {
				// Move ongoing events to end of our sheets array
				foreach ($sheets as $key => $sheet) {
					if ('Ongoing' === $sheet->type) {
						$move_me = $sheet;
						unset($sheets[$key]);
						$sheets[] = $move_me;
					}
				}
			}

			// Allow plugins or themes to modify retrieved sheets
			$sheets = apply_filters('pta_sus_display_active_sheets', $sheets, $atts);

			if (empty($sheets)) {
				$return .= '<p>' . apply_filters('pta_sus_public_output', __('No sheets currently available at this time.', 'pta-volunteer-sign-up-sheets'), 'no_sheets_message') . '</p>';
			} else {
				$sheets_table = self::get_sheets_list($sheets, $atts);
				$return .= apply_filters('pta_sus_display_sheets_table', $sheets_table, $sheets);
			}

			// If current user has signed up for anything, list their signups and allow them to edit/clear them
			// If they aren't logged in, prompt them to login to see their signup info
			if (!isset($main_options['disable_user_signups']) || !$main_options['disable_user_signups']) {
				if (!$volunteer->is_validated()) {
					if (!$main_options['disable_signup_login_notice']) {
						$return .= '<p>' . apply_filters('pta_sus_public_output', __('Please login to view and edit your volunteer sign ups.', 'pta-volunteer-sign-up-sheets'), 'user_not_loggedin_signups_list_message') . '</p>';
					}
					if (self::$validation_enabled && isset($validation_options['enable_user_validation_form']) && $validation_options['enable_user_validation_form']) {
						if (((isset($_COOKIE['pta_sus_validation_form_submitted']) && empty($_COOKIE['pta_sus_validation_cleared'])) || self::$validation_sent)) {
							$minutes = $validation_options['validation_form_resubmission_minutes'] ?? 1;
							$return .= '<p>' . apply_filters('pta_sus_public_output', sprintf(__('User Validation email has been sent. Please check your email. If you did not receive the email, you can return and submit the form again after %d minutes.', 'pta-volunteer-sign-up-sheets'), $minutes), 'validation_form_already_submitted_message', absint($minutes)) . '</p>';
						} else {
							$return .= pta_get_validation_form();
						}
					}
				} else {
					$user_signups_list = self::get_user_signups_list($atts);
					$return .= apply_filters('pta_sus_display_user_signups_table', $user_signups_list);
					// Get clear validation message and link if needed
					$return .= self::get_clear_validation_message();
				}
			}
		} else {
			$return .= self::get_single_sheet($id);
		}
		$return .= apply_filters('pta_sus_after_display_sheets', '', $id, $date_filter);
		
		// Wrap in AJAX container
		$return = '<div id="' . esc_attr($container_id) . '" class="pta-sus-ajax-container" data-pta-sus-instance="' . esc_attr($container_id) . '">' . $return . '</div>';
		
		return $return;
	}

	/**
	 * Process validation form shortcode
	 * 
	 * Displays the user validation form or appropriate messages based on
	 * validation status. Handles form submission state and cookie-based
	 * resubmission limits.
	 * 
	 * @since 6.0.0
	 * @param array $atts Shortcode attributes
	 *   - hide_when_validated (string) 'yes' to hide form when user is validated
	 * @return string HTML output for validation form or messages
	 * @hook pta_sus_public_output Filter for various validation messages
	 */
	public static function process_validation_form_shortcode($atts = array()) {
		$validation_options = self::get_validation_options();
		$validation_enabled = self::is_validation_enabled();
		
		// Don't show anything if the system is not enabled
		if (!$validation_enabled || !(isset($validation_options['enable_user_validation_form']) && $validation_options['enable_user_validation_form'])) {
			return '<p>' . apply_filters('pta_sus_public_output', __('User Validation is currently disabled.', 'pta-volunteer-sign-up-sheets'), 'user_validation_disabled_message') . '</p>';
		}
		
		$atts = shortcode_atts(array(
			'hide_when_validated' => 'no'
		), $atts, 'pta_validation_form');
		
		$return = PTA_SUS_Messages::show_messages();
		self::set_messages_displayed(true);
		PTA_SUS_Messages::clear_messages();
		
		$volunteer = self::get_volunteer();
		
		// Return empty if user is validated and hide_when_validated is enabled
		if ($volunteer->is_validated() && 'yes' === $atts['hide_when_validated']) {
			return '';
		}
		
		// Return empty if signup form is being displayed, in case they have the validation form displayed on the same page
		if (isset($_GET['task_id'])) {
			return '';
		}
		
		if (!$volunteer->is_validated()) {
			if (self::$validation_sent) {
				return $return;
			}
			if (isset($_COOKIE['pta_sus_validation_form_submitted']) && empty($_COOKIE['pta_sus_validation_cleared'])) {
				$minutes = $validation_options['validation_form_resubmission_minutes'] ?? 1;
				$return .= '<p>' . apply_filters('pta_sus_public_output', sprintf(__('User Validation email has been sent. Please check your email. If you did not receive the email, you can return and submit the form again after %d minutes.', 'pta-volunteer-sign-up-sheets'), $minutes), 'validation_form_already_submitted_message', absint($minutes)) . '</p>';
				return $return;
			}
			$return .= pta_get_validation_form();
		} elseif (!isset($_GET['pta-sus-action']) || ($_GET['pta-sus-action'] != 'validate_signup' && $_GET['pta-sus-action'] != 'validate_user')) {
			$return .= '<p>' . apply_filters('pta_sus_public_output', __('You are already validated.', 'pta-volunteer-sign-up-sheets'), 'already_validated_message') . '</p>';
		}
		
		$return .= self::get_clear_validation_message();
		return $return;
	}

	/**
	 * Get clear validation message and link
	 * 
	 * Returns HTML for the clear validation message and link if the user
	 * is validated, not logged in, and clear validation is enabled.
	 * 
	 * @since 6.0.0
	 * @return string HTML output for clear validation message, empty string if not applicable
	 */
	private static function get_clear_validation_message() {
		$return = '';
		$volunteer = self::get_volunteer();
		$validation_options = self::get_validation_options();
		$validation_enabled = self::is_validation_enabled();
		
		if ($volunteer->is_validated() && !is_user_logged_in() && $validation_enabled && isset($validation_options['enable_clear_validation']) && $validation_options['enable_clear_validation']) {
			$message = $validation_options['clear_validation_message'] ?? '';
			if ($message) {
				$return .= '<div class="pta-sus clear-validation-message">' . wpautop($message) . '</div>';
			}
			$args = array('pta-sus-action' => 'clear_validation', 'validate_signup_id' => false, 'code' => false);
			$raw_url = add_query_arg($args);
			$url = wp_nonce_url($raw_url, 'pta-sus-clear-validation', 'pta-sus-clear-validation-nonce');
			$link_text = $validation_options['clear_validation_link_text'] ?? 'Clear Validation';
			$return .= '<p><a href="' . esc_url($url) . '">' . esc_html($link_text) . '</a></p>';
		}
		return $return;
	}

}

