<?php
if(!function_exists( 'pta_datetime')) {
	function pta_datetime($format, $timestamp) {
		$main_options = get_option( 'pta_volunteer_sus_main_options' );
		if( isset($main_options['disable_datei18n']) && $main_options['disable_datei18n'] ) {
			$datetime = date($format, $timestamp);
		} else {
			$datetime = date_i18n( $format, $timestamp);
		}
		return apply_filters('pta_sus_datetime', $datetime, $format, $timestamp);
	}
}

function pta_sanitize_value($value, $type) {
	$sanitized_value = $value;
	switch ($type) {
		case 'text':
			$sanitized_value = sanitize_text_field(stripslashes($value));
			break;
		case 'textarea':
			$sanitized_value = wp_kses_post(stripslashes($value));
			break;
		case 'html':
			$sanitized_value = wpautop(wp_kses_post(stripslashes($value)));
			break;
		case 'email':
			if('{chair_email}' === $value) {
				$sanitized_value = '{chair_email}';
			} else {
				$sanitized_value = sanitize_email($value);
			}
			break;
		case 'date':
			// Sanitize one date
			// SQL Format for date should be yyyy-mm-dd
			// First, get rid of any spaces
			$date = str_replace(' ', '', $value);
			// Convert the remaining string to an SQL format date
			if(!empty($date)) {
				$sanitized_value = date('Y-m-d', strtotime($date));
			} else {
				$sanitized_value = null;
			}
			break;
		case 'time':
			// Sanitize one time and convert to SQL format, which should be 24 hour format H:i:s
			// First, do basic sanitizing
			$time = sanitize_text_field($value);
			if(!empty($time)) {
				$sanitized_value = date('H:i:s', strtotime($time));
			} else {
				$sanitized_value = null;
			}
			break;
		case 'int':
			// Make the value into absolute integer
			$sanitized_value = null === $value ? null : absint($value);
			break;
		case 'intval':
			// Make the value into a regular integer - positive or negative
			$sanitized_value = null === $value ? null : intval($value);
			break;
		case 'float':
			$sanitized_value = null === $value ? null : floatval($value);
			break;
		case 'bool':
			$sanitized_value = true == $value;
			break;
		case 'array':
			$array = maybe_unserialize($value);
			if(is_array($array)) {
				$array = stripslashes_deep($array);
				$sanitized_value = pta_sanitize_array($array);
			}
			break;
		case 'yesno':
			if ($value === 'yes') {
				$sanitized_value = 'yes';
			} else {
				$sanitized_value = 'no';
			}
			break;
		default:
			$sanitized_value = apply_filters('pta_sanitize_value', wp_kses_post(stripslashes($value)), $type);
			break;
	}
	return $sanitized_value;
}

function pta_create_slug($name) {
	$name = trim($name);
	$name = str_replace(' ','_', $name);
	return sanitize_key($name);
}

function pta_sanitize_array( &$array ) {
	foreach ( $array as &$value ) {
		if ( ! is_array( $value ) ) {
			// sanitize if value is not an array
			$value = sanitize_text_field( $value );
		} else {
			// go inside this function again
			pta_sanitize_array( $value );
		}
	}
	return $array;
}

function pta_get_validation_required_message() {
	$validation_options = get_option( 'pta_volunteer_sus_validation_options' );
	$message = '<p class="pta-sus error">' . esc_html($validation_options['validation_required_message']) . '</p>';
	if(isset($validation_options['validation_page_id']) && $validation_options['validation_page_id'] > 0) {
		$link_text = $validation_options['validation_page_link_text'] ?? 'Go to the validation form';
		$message .= '<p><a class="pta-sus-link validate" href="'.  get_permalink($validation_options['validation_page_id'])  .'" title="Validation Form">'.apply_filters( 'pta_sus_public_output', $link_text, 'validation_page_link_text').'</a></p>';
	}
	return $message;
}

function pta_validation_enabled() {
	$validation_options = get_option( 'pta_volunteer_sus_validation_options' );
	return isset($validation_options['enable_validation']) && $validation_options['enable_validation'];
}

function pta_get_validated_user_info($validation_code='') {
	$user_info = false;
	$user = wp_get_current_user();
	if($user->ID > 0) {
		$user_info = new stdClass();
		$user_info->user_id = $user->ID;
		$user_info->firstname = sanitize_text_field($user->first_name);
		$user_info->lastname = sanitize_text_field($user->last_name);
		$user_info->email = sanitize_email($user->user_email);
		return $user_info;
	}
	if (isset($_COOKIE['pta_sus_validated_user'])) {
		$cookie_data = json_decode(base64_decode($_COOKIE['pta_sus_validated_user']), true);
		if ( $cookie_data && isset($cookie_data['firstname']) && isset($cookie_data['lastname']) && isset($cookie_data['email']) ) {
			$user_info = new stdClass();
			$user_info->user_id = 0;
			$user_info->firstname = sanitize_text_field($cookie_data['firstname']);
			$user_info->lastname = sanitize_text_field($cookie_data['lastname']);
			$user_info->email = sanitize_email($cookie_data['email']);
		}
		return $user_info;
	}
	if(empty($validation_code)) {
		$validation_code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
	}
	if(!empty($validation_code)) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'pta_sus_validation_codes';
		$query      = $wpdb->prepare( "SELECT * FROM $table_name WHERE code = %s", $validation_code );
		$row        = $wpdb->get_row( $query );
		if ( $row ) {
			$user_info = new stdClass();
			$user_info->user_id = 0;
			$user_info->firstname = sanitize_text_field( $row->firstname );
			$user_info->lastname  = sanitize_text_field( $row->lastname );
			$user_info->email     = sanitize_email( $row->email );
		}
	}
	return $user_info;
}

function pta_create_validation_link($firstname, $lastname, $email,$signup_id=0,$action='validate_user') {
	$validation_options = get_option( 'pta_volunteer_sus_validation_options' );
	$main_options   = get_option( 'pta_volunteer_sus_main_options' );
	$link_expiration = isset($validation_options['link_code_expiration_hours']) && (absint($validation_options['link_code_expiration_hours']) > 0) ? absint($validation_options['link_code_expiration_hours']) : 24;
	$code = pta_get_validation_code($firstname, $lastname, $email, $link_expiration);
	if(empty($code)) {
		$code = pta_create_or_update_code($firstname, $lastname, $email);
	}
	$args = array(
		'pta-sus-action' => $action,
		'validate_signup_id' => ( $signup_id > 0 ) ? $signup_id : false,
		'code' => $code,
	);
	$page_id = isset($validation_options['validation_page_id']) ? absint($validation_options['validation_page_id']) : 0;
	if($page_id > 0) {
		$link = get_permalink($page_id);
	} elseif(isset($main_options['volunteer_page_id']) && $main_options['volunteer_page_id'] > 0) {
		$link = get_permalink($main_options['volunteer_page_id']);
	} else {
		$link = home_url();
	}
	return add_query_arg($args, $link);
}

/**
 * Sets a cookie to track validated user information
 *
 * @param string $firstname User's first name
 * @param string $lastname User's last name
 * @param string $email User's email address
 * @return void
 */
function pta_set_validated_user_cookie($firstname, $lastname, $email) {
	$options = get_option( 'pta_volunteer_sus_main_options' );
	$expiration_hours = isset($options['validation_code_expiration_hours']) ? absint($options['validation_code_expiration_hours']) : 24;
	$cookie_data = base64_encode(json_encode([
		'firstname' => $firstname,
		'lastname' => $lastname,
		'email' => $email
	]));

	setcookie(
		'pta_sus_validated_user',
		$cookie_data,
		time() + ($expiration_hours * 3600),
		COOKIEPATH,
		COOKIE_DOMAIN,
		is_ssl(),
		true
	);
}

function pta_clear_validated_user_cookie() {
	setcookie(
		'pta_sus_validated_user',
		'',
		time() - 3600,
		COOKIEPATH,
		COOKIE_DOMAIN,
		is_ssl(),
		true
	);
}

/**
 * Checks if a user is validated through login, cookie, or validation code
 *
 * @param string $firstname User's first name
 * @param string $lastname User's last name
 * @param string $email User's email address
 *
 * @return boolean True if validated, false otherwise
 */
function pta_is_user_validated( $firstname, $lastname, $email) {
	if(is_user_logged_in()) {
		return true;
	}
	$messages = '';
	if (isset($_COOKIE['pta_sus_validated_user'])) {
		$cookie_data = json_decode(base64_decode($_COOKIE['pta_sus_validated_user']), true);
		if ($cookie_data && isset($cookie_data['firstname']) && isset($cookie_data['lastname']) && isset($cookie_data['email'])
		    && $cookie_data['firstname'] === $firstname && $cookie_data['lastname'] === $lastname && $cookie_data['email'] === $email) {
			return true;
		} else {
			return false;
		}
	} elseif (isset($_GET['code'])) {
		$options = get_option( 'pta_volunteer_sus_validation_options' );
		$code = sanitize_text_field($_GET['code']);
		$link_expiration = isset($options['validation_code_expiration_hours']) && (absint($options['validation_code_expiration_hours']) > 0) ? absint($options['validation_code_expiration_hours']) : 24;
		$code_entry = pta_validate_code($code,$link_expiration);
		if(!$code_entry) {
			return false;
		}
		pta_set_validated_user_cookie($firstname, $lastname, $email);
		return true;
	}
	return false;
}

function pta_generate_unique_code() {
	try {
		$code = bin2hex(random_bytes(32));
	} catch (Exception $e) {
		$code = md5(uniqid(rand(), true));
	}
	return $code;
}

function pta_create_or_update_code($firstname,$lastname,$email) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'pta_sus_validation_codes';
	// Check for existing code
	$existing = $wpdb->get_row( $wpdb->prepare(
		"SELECT code FROM $table_name WHERE firstname = %s AND lastname = %s AND email = %s",
		$firstname, $lastname, $email
	) );

	if ( $existing ) {
		// Update timestamp for existing code
		$wpdb->update(
			$table_name,
			array( 'ts' => current_time( 'mysql' ) ),
			array(
				'firstname' => $firstname,
				'lastname'  => $lastname,
				'email'     => $email
			),
			array( '%s' ),
			array( '%s', '%s', '%s' )
		);

		return $existing->code;
	} else {
		// Insert new code
		$code = pta_generate_unique_code();
		$wpdb->insert(
			$table_name,
			array(
				'firstname' => $firstname,
				'lastname'  => $lastname,
				'email'     => $email,
				'code'      => $code,
				'ts'        => current_time( 'mysql' )
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return $code;
	}
}

function pta_validate_code($code, $expiration=24) {
	global $wpdb;
	$table  = $wpdb->prefix . 'pta_sus_validation_codes';
	$sql    = "SELECT * FROM $table WHERE code = %s AND ts > (NOW() - INTERVAL %d HOUR)";
	$sql    = $wpdb->prepare( $sql, $code, $expiration );
	$result = $wpdb->get_row( $sql );
	if ( empty( $result ) ) {
		return false;
	}
	return $result;
}

function pta_get_validation_code($firstname, $lastname, $email, $hours='') {
	if(empty($hours)) {
		$options = get_option( 'pta_volunteer_sus_validation_options' );
		$hours = isset($options['validation_code_expiration_hours']) ? absint($options['validation_code_expiration_hours']) : 24;
	}
	global $wpdb;
	$table  = $wpdb->prefix . 'pta_sus_validation_codes';
	$sql    = "SELECT code FROM $table WHERE firstname = %s AND lastname = %s AND email = %s AND ts > (NOW() - INTERVAL %d HOUR)";
	$sql    = $wpdb->prepare( $sql, $firstname, $lastname, $email, $hours );
	return $wpdb->get_var( $sql );
}

function pta_delete_expired_validation_codes() {
	$options = get_option( 'pta_volunteer_sus_validation_options' );
	$hours = isset($options['validation_code_expiration_hours']) ? absint($options['validation_code_expiration_hours']) : 24;
	global $wpdb;
	$table  = $wpdb->prefix . 'pta_sus_validation_codes';
	$deleted = $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE ts < DATE_SUB(NOW(), INTERVAL %d HOUR)", $hours	));
	$deleted > 0 ? $message = sprintf('%d expired validation codes deleted from %s', $deleted, $table) : $message = '';
	return $message;
}

function pta_delete_unvalidated_signups() {
	$options = get_option( 'pta_volunteer_sus_validation_options' );
	$hours = isset($options['signup_expiration_hours']) ? absint($options['signup_expiration_hours']) : 1;
	global $wpdb;
	$table  = $wpdb->prefix . 'pta_sus_signups';
	$deleted = $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE validated = 0 AND ts < DATE_SUB(NOW(), INTERVAL %d HOUR)", $hours	));
	$deleted > 0 ? $message = sprintf('%d unvalidated signups deleted from %s', $deleted, $table) : $message = '';
	return $message;
}

function pta_get_validation_form($echo=false) {
	ob_start();
	include(PTA_VOLUNTEER_SUS_DIR.'views/public-validation-form.php');
	$form = ob_get_clean();
	if($echo) {
		echo $form;;
	} else {
		return $form;
	}
}

function pta_get_main_options() {
	return get_option('pta_volunteer_sus_main_options', array());
}

function pta_get_validation_options() {
	return get_option('pta_volunteer_sus_validation_options', array());
}

function pta_get_email_options() {
	return get_option('pta_volunteer_sus_email_options', array());
}

function pta_clean_redirect() {
	// Store current messages in cookies
	setcookie(
		'pta_sus_messages',
		json_encode(PTA_SUS_Messages::get_messages()),
		time() + 300,
		COOKIEPATH,
		COOKIE_DOMAIN,
		is_ssl(),
		true
	);

	setcookie(
		'pta_sus_errors',
		json_encode(PTA_SUS_Messages::get_errors()),
		time() + 300,
		COOKIEPATH,
		COOKIE_DOMAIN,
		is_ssl(),
		true
	);

	// Keep only sheet_id parameter, if set
	if(isset($_GET['sheet_id'])) {
		$clean_url = add_query_arg(
			['sheet_id' => $_GET['sheet_id']],
			remove_query_arg(array_keys($_GET))
		);
	} else {
		$clean_url = remove_query_arg(array_keys($_GET));
	}

	// Redirect to individual sheet page
	wp_redirect(esc_url($clean_url));
	exit;
}


function pta_sus_show_clear($sheet, $date, $time='') {
	if ( current_user_can('manage_signup_sheets')) {
		// admin/manager can always clear
		return true;
	}
	if ($sheet->clear && (
			0 == $sheet->clear_days ||
			$date == "0000-00-00" ||
			(strtotime($date . (!empty($time) ? ' ' . $time : '')) - current_time('timestamp') > (
					(int)$sheet->clear_days * 60 * 60 * ($sheet->clear_type === 'hours' ? 1 : 24)
				))
		)) {
		return true;
	}
	return false;
}

function pta_logToFile($msg, $filename='')	{
	if(empty($filename)) {
		$filename = 'pta_debug.log';
	}
	$upload_dir = wp_upload_dir();
	$log_dir = $upload_dir['basedir'] . '/pta-logs';

	// Create directory if it doesn't exist
	wp_mkdir_p($log_dir);
	$log_file = $log_dir . '/'. $filename;
	if(!file_exists($log_file)) {
		touch( $log_file );
	}
	// Write to file
	file_put_contents($log_file, date('Y-m-d H:i:s') . ": " . $msg . "\n", FILE_APPEND);
}

function pta_clear_log_file($filename='') {
	if(empty($filename)) {
		$filename = 'pta_debug.log';
	}
	$upload_dir = wp_upload_dir();
	$log_dir = $upload_dir['basedir'] . '/pta-logs';
	$log_file = $log_dir . '/'. $filename;
	if(file_exists($log_file)) {
		file_put_contents($log_file, '');
		return true;
	}
	return false;
}