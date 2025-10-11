<?php
class PTA_SUS_Template_Tags {
	private static $tags = array();

	private static $extra_tags = array();
	private static $registered_tag_callbacks = array();
	private static $current_signup_id = null;

	public static function register_tag_provider($callback) {
		self::$registered_tag_callbacks[] = $callback;
	}

	public static function convert_to_plain_text($html) {
		// convert common formatting tags
		$text = str_replace('<p>', '', $html);
		$text = str_replace('<h1>', '', $text);
		$text = str_replace('<h2>', '', $text);
		$text = str_replace('<h3>', '', $text);
		$text = str_replace('<h4>', '', $text);
		$text = str_replace('<h5>', '', $text);
		$text = str_replace('<h6>', '', $text);
		$text = str_replace('<div>', '', $text);
		$text = str_replace('</p>', "\r\n", $text);
		$text = str_replace('</h1>', "\r\n", $text);
		$text = str_replace('</h2>', "\r\n", $text);
		$text = str_replace('</h3>', "\r\n", $text);
		$text = str_replace('</h4>', "\r\n", $text);
		$text = str_replace('</h5>', "\r\n", $text);
		$text = str_replace('</h6>', "\r\n", $text);
		$text = str_replace('</div>', "\r\n", $text);
		$text = str_replace('<br/>', "\r\n", $text);
		$text = str_replace('<br />', "\r\n", $text);
		// Strip any other tags
		return strip_tags($text);

	}

	public static function get_member_directory_names($group='') {
		$args = array( 'post_type' => 'member', 'member_category' => $group );
		$members = get_posts( $args );
		if(!$members) return false;
		$names = array();
		foreach ($members as $member) {
			$names[] = sanitize_text_field($member->post_title);
		}
		if(0 == count($names)) return false;
		return $names;
	}

	public static function get_member_directory_emails($group='') {
		$args = array( 'post_type' => 'member', 'member_category' => $group );
		$members = get_posts( $args );
		if(!$members) return false;
		$emails = array();
		foreach ($members as $member) {
			if (is_email( esc_html( $email = get_post_meta( $member->ID, '_pta_member_directory_email', true ) ) )) {
				$emails[] = $email;
			}
		}
		if(0 == count($emails)) return false;
		return $emails;
	}

	public static function get_signup_tags($signup) {
		if(empty($signup)) {
			return array();
		}

		$date = ($signup->date === '0000-00-00') ? __('N/A', 'pta-volunteer-sign-up-sheets') : pta_datetime(get_option('date_format'), strtotime($signup->date));
		$item = (isset($signup->item) && $signup->item !== " ") ? $signup->item : __('N/A', 'pta-volunteer-sign-up-sheets');
		$firstname = sanitize_text_field($signup->firstname);
		$lastname = sanitize_text_field($signup->lastname);
		$email = sanitize_email($signup->email);
		$datetime_format = get_option('date_format') . ' ' . get_option('time_format');
		$signup_time = pta_datetime($datetime_format, $signup->ts);

		return array(
			'{firstname}' => $firstname,
			'{signup_firstname}' => $firstname,
			'{lastname}' => $lastname,
			'{signup_lastname}' => $lastname,
			'{email}' => $email,
			'{signup_email}' => $email,
			'{phone}' => sanitize_text_field($signup->phone),
			'{date}' => $date,
			'{signup_date}' => $date,
			'{item_details}' => sanitize_text_field($item),
			'{item_qty}' => (int)$signup->item_qty,
			'{signup_time}' => $signup_time,
		);
	}

	public static function get_task_tags($task, $date='') {
		if(empty($task)) {
			return array();
		}

		$start_time = ($task->time_start === "") ? __('N/A', 'pta-volunteer-sign-up-sheets') : pta_datetime(get_option("time_format"), strtotime($task->time_start));
		$end_time = ($task->time_end === "") ? __('N/A', 'pta-volunteer-sign-up-sheets') : pta_datetime(get_option("time_format"), strtotime($task->time_end));

		global $pta_sus;
		$task_open_spots = $pta_sus->data->get_available_qty($task->id, '', $task->qty);
		$task_filled_spots = $task->qty - $task_open_spots;

		// try to get the task date if not passed in
		if(empty($date)) {
			$dates = explode(',', $task->dates);
			foreach($dates as $task_date) {
				if(!empty($date)) {
					$date .= ', ';
				}
				$date .= pta_datetime(get_option('date_format'), strtotime($task_date));
			}
		}

		return array(
			'{task_title}' => $task->title,
			'{task_description}' => $task->description,
			'{task_filled_spots}' => $task_filled_spots,
			'{task_open_spots}' => $task_open_spots,
			'{start_time}' => $start_time,
			'{task_start_time}' => $start_time,
			'{end_time}' => $end_time,
			'{task_end_time}' => $end_time,
			'{details_text}' => $task->details_text,
			'{task_date}' => $date,
		);
	}

	public static function get_sheet_tags($sheet) {
		if(empty($sheet)) {
			return array();
		}

		global $pta_sus;
		$main_options = get_option('pta_volunteer_sus_main_options', array());
		$sheet_first_date = ($sheet->first_date === '0000-00-00') ? __('N/A', 'pta-volunteer-sign-up-sheets') : mysql2date(get_option('date_format'), $sheet->first_date, $translate = true);
		$sheet_last_date = ($sheet->last_date === '0000-00-00') ? __('N/A', 'pta-volunteer-sign-up-sheets') : mysql2date(get_option('date_format'), $sheet->last_date, $translate = true);

		$sheet_total_spots = $pta_sus->data->get_sheet_total_spots($sheet->id);
		$sheet_filled_spots = $pta_sus->data->get_sheet_signup_count($sheet->id);
		$sheet_open_spots = $sheet_total_spots - $sheet_filled_spots;


		if (isset($sheet->position) && '' !== $sheet->position) {
			$chair_emails = self::get_member_directory_emails($sheet->position);
		} else {
			$chair_emails = !empty($sheet->chair_email) ? explode(',', $sheet->chair_email) : array();
		}
		$contact_emails = !empty($chair_emails) ? implode("\r\n", $chair_emails) : __('N/A', 'pta-volunteer-sign-up-sheets');

		$chair_names = '';
		if (isset($sheet->position) && '' !== $sheet->position) {
			$names = self::get_member_directory_names($sheet->position);
			if($names) {
				$chair_names = implode(', ', $names);
			}
		}
		if(!$chair_names) {
			$chair_names = $pta_sus->data->get_chair_names_html($sheet->chair_name);
		}

		$volunteer_page_id = isset($main_options['volunteer_page_id']) ? absint( $main_options['volunteer_page_id']) : 0;
		$volunteer_url = get_permalink($volunteer_page_id);
		if($volunteer_page_id > 0) {
			$sheet_args = array('sheet_id' => $sheet->id, 'date' => false, 'signup_id' => false, 'task_id' => false);
			$sheet_url = add_query_arg( $sheet_args, $volunteer_url);
		} else {
			$sheet_url = '';
		}

		return array(
			'{sheet_title}' => $sheet->title,
			'{sheet_details}' => $sheet->details,
			'{sheet_first_date}' => $sheet_first_date,
			'{sheet_last_date}' => $sheet_last_date,
			'{sheet_filled_spots}' => $sheet_filled_spots,
			'{sheet_open_spots}' => $sheet_open_spots,
			'{sheet_url}' => $sheet_url,
			'{contact_emails}' => $contact_emails,
			'{contact_names}' => $chair_names
		);
	}


	public static function register_default_tags($signup) {

		// Initialize empty tags array
		self::$tags = array();

		// Get signup and related objects
		if(is_numeric($signup)) {
			$signup = pta_sus_get_signup($signup);
		}

		// Get signup tags if valid signup
		if(!empty($signup)) {
			self::$tags = self::get_signup_tags($signup);

			// Get associated task and its tags
			$task = pta_sus_get_task($signup->task_id);
			if(!empty($task)) {
				self::$tags = array_merge(self::$tags, self::get_task_tags($task,$signup->date));

				// Get associated sheet and its tags
				$sheet = pta_sus_get_sheet($task->sheet_id);
				if(!empty($sheet)) {
					self::$tags = array_merge(self::$tags, self::get_sheet_tags($sheet));
				}
			}
		}

		// Add validation options tags
		$validation_options = get_option('pta_volunteer_sus_validation_options');
		self::$tags['{signup_expiration_hours}'] = ($validation_options['signup_expiration_hours'] ?? 1);
		self::$tags['{validation_code_expiration_hours}'] = ($validation_options['validation_code_expiration_hours'] ?? 48);

		// Add site info tags
		self::$tags['{site_name}'] = get_bloginfo('name');
		self::$tags['{site_url}'] = get_bloginfo('url');

		// Merge in any registered extra tags
		foreach(self::$extra_tags as $tag => $value) {
			if(!isset(self::$tags[$tag])) {
				self::$tags[$tag] = $value;
			}
		}

		// Process registered callbacks only once per signup
		foreach (self::$registered_tag_callbacks as $callback) {
			$additional_tags = call_user_func($callback, $signup);
			if (is_array($additional_tags)) {
				self::$tags = array_merge(self::$tags, $additional_tags);
			}
		}

		return self::$tags;
	}


	public static function add_tag($tag, $value) {
		self::$extra_tags[$tag] = $value;
	}

	public static function process_text($text, $signup, $reminder=false, $clear=false, $reschedule=false) {
		if(is_numeric($signup)) {
			$signup = pta_sus_get_signup($signup);
		}
		if(empty($signup)) {
			return $text;
		}
		// Only register tags if this is a new signup or tags are empty
		if(self::$current_signup_id !== $signup->id || empty(self::$tags)) {
			self::$current_signup_id = $signup->id;
			self::register_default_tags($signup);
		}

		// For backwards compatibility
		$search = apply_filters('pta_sus_email_search', array_keys(self::$tags), $signup, $reminder, $clear, $reschedule);
		$replace = apply_filters('pta_sus_email_replace', array_values(self::$tags), $signup, $reminder, $clear, $reschedule);

		return str_replace($search, $replace, $text);
	}
}
