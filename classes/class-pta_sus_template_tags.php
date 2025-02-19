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

	public static function register_default_tags($signup) {
		global $pta_sus;
		if(is_numeric($signup)) {
			$signup = $pta_sus->get_signup($signup);
		}
		if(empty($signup)) {
			return self::$tags;
		}
		$task = $pta_sus->get_task($signup->task_id);
		if(empty($task)) {
			return self::$tags;
		}
		$sheet = $pta_sus->get_sheet($task->sheet_id);
		if(empty($sheet)) {
			return self::$tags;
		}

		// Calculate some Variables for display
		$email_options = get_option( 'pta_volunteer_sus_email_options' );
		$validation_options = get_option( 'pta_volunteer_sus_validation_options' );
		$use_html = isset($email_options['use_html']) && $email_options['use_html'];
		$date = ($signup->date == '0000-00-00') ? __('N/A', 'pta-volunteer-sign-up-sheets') : mysql2date( get_option('date_format'), $signup->date, $translate = true );
		$start_time = ($task->time_start == "") ? __('N/A', 'pta-volunteer-sign-up-sheets') : pta_datetime(get_option("time_format"), strtotime($task->time_start));
		$end_time = ($task->time_end == "") ? __('N/A', 'pta-volunteer-sign-up-sheets') : pta_datetime(get_option("time_format"), strtotime($task->time_end));
		$chair_emails = !empty($sheet->chair_email) ? explode(',', $sheet->chair_email) : array();
		if (isset($signup->item) && $signup->item != " ") {
			$item = $signup->item;
		} else {
			$item = __('N/A', 'pta-volunteer-sign-up-sheets');
		}
		if (!empty($chair_emails)) {
			$contact_emails = implode("\r\n", $chair_emails);
		} else {
			$contact_emails = __('N/A', 'pta-volunteer-sign-up-sheets');
		}
		$sheet_details =  $sheet->details;
		if(!$use_html) {
			$sheet_details = self::convert_to_plain_text($sheet->details);
		}
		// Chair names
		$names = false;
		$chair_names = '';
		if (isset($sheet->position) && '' != $sheet->position) {
			$names = self::get_member_directory_names($sheet->position);
			if($names) {
				$chair_names = implode(', ', $names);
			}
		}
		if(!$names) {
			$chair_names = $pta_sus->data->get_chair_names_html($sheet->chair_name);
		}

		self::$tags = array(
			'{sheet_title}' => $sheet->title,
			'{sheet_details}' => $sheet_details,
			'{task_title}' => $task->title,
			'{task_description}' => $task->description,
			'{date}' => $date,
			'{start_time}' => $start_time,
			'{end_time}' => $end_time,
			'{item_details}' => $item,
			'{item_qty}' => $signup->item_qty,
			'{details_text}' => $task->details_text,
			'{firstname}' => $signup->firstname,
			'{lastname}' => $signup->lastname,
			'{contact_emails}' => $contact_emails,
			'{contact_names}' => $chair_names,
			'{site_name}' => get_bloginfo('name'),
			'{site_url}' => get_bloginfo('url'),
			'{phone}' => $signup->phone,
			'{email}' => $signup->email,
			'{signup_expiration_hours}' => ($validation_options['signup_expiration_hours'] ?? 1),
			'{validation_code_expiration_hours}' => ($validation_options['validation_code_expiration_hours'] ?? 48)
		);
		// add any extra tags here, as above resets the tags array
		foreach(self::$extra_tags as $tag => $value) {
			if(!isset(self::$tags[$tag])) {
				self::$tags[ $tag ] = $value;
			}
		}
		return self::$tags;
	}

	public static function add_tag($tag, $value) {
		self::$extra_tags[$tag] = $value;
	}

	public static function process_text($text, $signup, $reminder=false, $clear=false, $reschedule=false) {
		global $pta_sus;
		if(is_numeric($signup)) {
			$signup = $pta_sus->get_signup($signup);
		}
		if(empty($signup)) {
			return $text;
		}
		// Only register tags if this is a new signup or tags are empty
		if(self::$current_signup_id !== $signup->id || empty(self::$tags)) {
			self::$current_signup_id = $signup->id;
			self::register_default_tags($signup);

			// Process registered callbacks only once per signup
			foreach (self::$registered_tag_callbacks as $callback) {
				$additional_tags = call_user_func($callback, $signup);
				if (is_array($additional_tags)) {
					self::$tags = array_merge(self::$tags, $additional_tags);
				}
			}
		}

		// For backwards compatibility
		$search = apply_filters('pta_sus_email_search', array_keys(self::$tags), $signup, $reminder, $clear, $reschedule);
		$replace = apply_filters('pta_sus_email_replace', array_values(self::$tags), $signup, $reminder, $clear, $reschedule);

		return str_replace($search, $replace, $text);
	}
}
