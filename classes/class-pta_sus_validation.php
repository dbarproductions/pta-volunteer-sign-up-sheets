<?php
/**
 * Validation Helper Class
 * 
 * Centralized validation methods for sheets, tasks, and signups.
 * Provides both generic field validation and object-specific validation logic.
 * 
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Validation {

	/**
	 * Validate a single field based on its type
	 * Used by validate_object_fields() and can be called directly for individual field validation
	 *
	 * @param mixed $value Field value to validate
	 * @param string $type Field type (text, email, date, etc.)
	 * @param string $field_name Field name (for error messages)
	 * @return array Array with 'error' (bool) and 'message' (string)
	 */
	public static function validate_field_by_type($value, $type, $field_name = '') {
		$result = array(
			'error' => false,
			'message' => '',
		);

		switch ($type) {
			case 'text':
			case 'names':
				if (!pta_sus_check_allowed_text($value)) {
					$result['error'] = true;
					$result['message'] = sprintf(__('Invalid characters in %s field.', 'pta-volunteer-sign-up-sheets'), $field_name);
				}
				break;

			case 'textarea':
				// For now, we allow everything in text area, but it is escaped before display on admin side,
				// using wp_kses_post on public side to sanitize
				// need to sanitize before saving to database
				break;

			case 'email':
				// Single email validation
				if (!is_email($value)) {
					$result['error'] = true;
					$result['message'] = __('Invalid email.', 'pta-volunteer-sign-up-sheets');
				}
				break;

			case 'emails':
				// Validate one or more emails that will be separated by commas
				// First, get rid of any spaces
				$emails_field = str_replace(' ', '', $value);
				// Then, separate out the emails into a simple data array, using comma as separator
				$emails = explode(',', $emails_field);

				foreach ($emails as $email) {
					if (!is_email($email)) {
						$result['error'] = true;
						$result['message'] = __('Invalid email.', 'pta-volunteer-sign-up-sheets');
						break; // Stop on first invalid email
					}
				}
				break;

			case 'date':
				if (!pta_sus_check_date($value)) {
					$result['error'] = true;
					$result['message'] = __('Invalid date.', 'pta-volunteer-sign-up-sheets');
				}
				break;

			case 'dates':
				// Validate one or more dates that will be separated by commas
				// Format for each date should be yyyy-mm-dd
				// First, get rid of any spaces
				$dates_field = str_replace(' ', '', $value);
				// Then, separate out the dates into a simple data array, using comma as separator
				$dates = explode(',', $dates_field);
				foreach ($dates as $date) {
					if (!pta_sus_check_date($date)) {
						$result['error'] = true;
						$result['message'] = __('Invalid date.', 'pta-volunteer-sign-up-sheets');
						break; // Stop on first invalid date
					}
				}
				break;

			case 'int':
				// Validate input is only numbers
				if (!pta_sus_check_numbers($value)) {
					$result['error'] = true;
					$result['message'] = sprintf(__('Numbers only for %s please!', 'pta-volunteer-sign-up-sheets'), $field_name);
				}
				break;

			case 'yesno':
				if ("YES" != $value && "NO" != $value) {
					$result['error'] = true;
					$result['message'] = sprintf(__('YES or NO only for %s please!', 'pta-volunteer-sign-up-sheets'), $field_name);
				}
				break;

			case 'bool':
				if ("1" != $value && "0" != $value) {
					$result['error'] = true;
					$result['message'] = sprintf(__('Invalid Value for %s', 'pta-volunteer-sign-up-sheets'), $field_name);
				}
				break;

			case 'time':
				$pattern = '/^(?:0[1-9]|1[0-2]):[0-5][0-9] (am|pm|AM|PM)$/';
				if (!preg_match($pattern, $value)) {
					$result['error'] = true;
					$result['message'] = sprintf(__('Invalid time format for %s', 'pta-volunteer-sign-up-sheets'), $field_name);
				}
				break;

			default:
				// Allow extensions to validate custom field types
				$filtered = apply_filters('pta_sus_validate_custom_field_type', $result, $value, $type, $field_name);
				if (isset($filtered['error']) && $filtered['error']) {
					$result = $filtered;
				}
				break;
		}

		return $result;
	}

	/**
	 * Validate object fields (generic method for sheets, tasks, etc.)
	 * Validates required fields and field types based on property definitions
	 * Adds error messages directly to PTA_SUS_Messages class
	 *
	 * @param array $clean_fields Array of cleaned fields (after prefix removal)
	 * @param string $object_type Object type: 'sheet', 'task', or class name
	 * @return array Array with 'errors' (count) and 'message' (empty string - messages added directly to PTA_SUS_Messages)
	 */
	public static function validate_object_fields($clean_fields, $object_type) {
		$results = array(
			'errors' => 0,
			'message' => '', // Empty - messages are added directly to PTA_SUS_Messages
		);

		// Determine class name based on object type
		$class_name = '';
		if ($object_type === 'sheet') {
			$class_name = 'PTA_SUS_Sheet';
		} elseif ($object_type === 'task') {
			$class_name = 'PTA_SUS_Task';
		} elseif (class_exists($object_type)) {
			$class_name = $object_type;
		} else {
			$results['errors']++;
			PTA_SUS_Messages::add_error(__('Invalid object type for validation.', 'pta-volunteer-sign-up-sheets'));
			return $results;
		}

		// Create a temporary instance to get property definitions and required fields
		$object = new $class_name();
		
		// Get required fields using reflection to access protected method
		$reflection = new ReflectionClass($object);
		$get_required_method = $reflection->getMethod('get_required_fields');
		// setAccessible() is only needed for PHP < 8.0 (deprecated in PHP 8.5)
		if (version_compare(PHP_VERSION, '8.0', '<')) {
			$get_required_method->setAccessible(true);
		}
		$required_fields = $get_required_method->invoke($object);
		
		// Get property definitions
		$get_properties_method = $reflection->getMethod('get_property_definitions');
		// setAccessible() is only needed for PHP < 8.0 (deprecated in PHP 8.5)
		if (version_compare(PHP_VERSION, '8.0', '<')) {
			$get_properties_method->setAccessible(true);
		}
		$property_definitions = $get_properties_method->invoke($object);

		// Check Required Fields first
		foreach ($required_fields as $required_field => $label) {
			if (empty($clean_fields[$required_field])) {
				$results['errors']++;
				$error_message = sprintf(__('%s is a required field.', 'pta-volunteer-sign-up-sheets'), $label);
				PTA_SUS_Messages::add_error($error_message);
			}
		}

		// Validate field types
		foreach ($property_definitions as $field => $type) {
			if (!empty($clean_fields[$field])) {
				$validation_result = self::validate_field_by_type($clean_fields[$field], $type, $field);
				if ($validation_result['error']) {
					$results['errors']++;
					PTA_SUS_Messages::add_error($validation_result['message']);
				}
			}
		}

		/**
		 * Filter validation results to allow extensions to add custom validation
		 * 
		 * Note: Extensions should use PTA_SUS_Messages::add_error() directly in this filter
		 * for best results. Modifying $results['message'] is still supported for backward
		 * compatibility, but messages should be added via PTA_SUS_Messages to avoid duplicates.
		 *
		 * @param array $results Validation results array
		 * @param array $clean_fields Cleaned fields being validated
		 * @param string $object_type Object type being validated
		 */
		$filter_name = "pta_sus_validate_{$object_type}_fields";
		return apply_filters($filter_name, $results, $clean_fields);
	}

	/**
	 * Validate signup form fields
	 * Validates signup-specific fields including availability, duplicates, and business rules
	 * Returns error count (does not modify external state)
	 *
	 * @param array $posted Posted form data (with 'signup_' prefix)
	 * @param PTA_SUS_Task|object $task Task object
	 * @param PTA_SUS_Sheet|object $sheet Sheet object
	 * @param array $options Main options array (for phone_required, no_phone, no_global_overlap)
	 *                       Optional 'editing_signup_id' key: When editing an existing signup, pass the signup ID
	 *                       to skip duplicate checks and account for existing spots in availability calculation
	 * @return int Number of validation errors found
	 */
	public static function validate_signup_fields($posted, $task, $sheet, $options = array()) {
		$error_count = 0;

		// Load options if not provided
		if (empty($options)) {
			$options = get_option('pta_volunteer_sus_main_options',array());
		}

		$phone_required = $options['phone_required'] ?? true;
		$no_phone = isset($options['no_phone']) && $options['no_phone'];
		$no_global_overlap = isset($options['no_global_overlap']) && $options['no_global_overlap'];

		$details_required = isset($task->details_required) && "YES" === $task->details_required;

		// Validate date format FIRST before using it in database queries
		// This prevents database errors when invalid dates are passed
		if (!empty($posted['signup_date']) && !pta_sus_check_date($posted['signup_date'])) {
			$error_count++;
			PTA_SUS_Messages::add_error(apply_filters('pta_sus_public_output', __('Hidden signup date field is invalid!  Please try again.', 'pta-volunteer-sign-up-sheets'), 'signup_date_error_message'));
		}

		// Check if task start time has already passed
		if ($error_count === 0 && !empty($posted['signup_date']) && !pta_sus_allow_signup($task, $posted['signup_date'])) {
			$error_count++;
			PTA_SUS_Messages::add_error(apply_filters('pta_sus_public_output', __('Sign-up for this task is no longer available because the task start time has already passed.', 'pta-volunteer-sign-up-sheets'), 'signup_past_start_time_message'));
		}

		// Check availability (only if date is valid)
		$available = 0;
		$editing_signup_id = isset($options['editing_signup_id']) ? absint($options['editing_signup_id']) : 0;
		if ($error_count === 0 && !empty($posted['signup_date'])) {
			$available = $task->get_available_spots($posted['signup_date']);
			// Allow extensions to modify available slots
			$available = apply_filters('pta_sus_process_signup_available_slots', $available, $posted, $sheet, $task);

			// If editing an existing signup, add back the existing signup's quantity to available count
			// This allows users to edit their signup even when all spots are filled (they already have spots)
			if ($editing_signup_id > 0) {
				$existing_signup = PTA_SUS_Signup_Functions::get_signup($editing_signup_id);
				if ($existing_signup) {
					$available += (int) ($existing_signup->item_qty ?? 1);
				}
			}

			if ($available < 1) {
				$error_count++;
				PTA_SUS_Messages::add_error(apply_filters('pta_sus_public_output', __('All spots have already been filled.', 'pta-volunteer-sign-up-sheets'), 'no_spots_available_signup_error_message'));
			}
		}

		// Check for invalid characters first (before checking if empty, since sanitize_text_field might strip invalid chars)
		// This ensures we get specific error messages for invalid characters rather than generic "required fields" error
		if (!empty($posted['signup_firstname']) && !pta_sus_check_allowed_text(stripslashes($posted['signup_firstname']))) {
			$error_count++;
			PTA_SUS_Messages::add_error(apply_filters('pta_sus_public_output', __('Invalid Characters in First Name!  Please try again.', 'pta-volunteer-sign-up-sheets'), 'firstname_error_message'));
		} elseif (!empty($posted['signup_lastname']) && !pta_sus_check_allowed_text(stripslashes($posted['signup_lastname']))) {
			$error_count++;
			PTA_SUS_Messages::add_error(apply_filters('pta_sus_public_output', __('Invalid Characters in Last Name!  Please try again.', 'pta-volunteer-sign-up-sheets'), 'lastname_error_message'));
		} elseif (!empty($posted['signup_email']) && !is_email(trim($posted['signup_email']))) {
			$error_count++;
			PTA_SUS_Messages::add_error(apply_filters('pta_sus_public_output', __('Invalid Email!  Please try again.', 'pta-volunteer-sign-up-sheets'), 'email_error_message'));
		} elseif (!empty($posted['signup_email']) && !empty($posted['signup_validate_email']) && trim($posted['signup_email']) != trim($posted['signup_validate_email'])) {
			$error_count++;
			PTA_SUS_Messages::add_error(apply_filters('pta_sus_public_output', __('Email and Confirmation Email do not match!  Please try again.', 'pta-volunteer-sign-up-sheets'), 'confirmation_email_error_message'));
		} elseif (!$no_phone && !empty($posted['signup_phone']) && preg_match("/[^0-9\-\.\(\)\ \+]/", $posted['signup_phone'])) {
			$error_count++;
			PTA_SUS_Messages::add_error(apply_filters('pta_sus_public_output', __('Invalid Characters in Phone Number!  Please try again.', 'pta-volunteer-sign-up-sheets'), 'phone_error_message'));
		} elseif ("YES" === $task->need_details && !empty($posted['signup_item']) && !pta_sus_check_allowed_text(stripslashes($posted['signup_item']))) {
			$error_count++;
			PTA_SUS_Messages::add_error(apply_filters('pta_sus_public_output', __('Invalid Characters in Signup Item!  Please try again.', 'pta-volunteer-sign-up-sheets'), 'item_details_error_message'));
		} elseif ("YES" === $task->enable_quantities && isset($posted['signup_item_qty']) && (!pta_sus_check_numbers($posted['signup_item_qty']) || (int)$posted['signup_item_qty'] < 1 || ($available > 0 && (int)$posted['signup_item_qty'] > $available))) {
			$error_count++;
			// Only check against available if we have a valid date and availability was checked
			if ($available > 0) {
				PTA_SUS_Messages::add_error(apply_filters('pta_sus_public_output', sprintf(__('Please enter a number between 1 and %d for Item QTY!', 'pta-volunteer-sign-up-sheets'), (int)$available), 'item_quantity_error_message', $available));
			} else {
				PTA_SUS_Messages::add_error(apply_filters('pta_sus_public_output', __('Please enter a valid quantity for Item QTY!', 'pta-volunteer-sign-up-sheets'), 'item_quantity_error_message'));
			}
		}

		// Check required fields (only if no format errors found)
		if ($error_count === 0) {
			if (
				empty(isset($posted['signup_firstname']) ? sanitize_text_field($posted['signup_firstname']) : '')
				|| empty(isset($posted['signup_lastname']) ? sanitize_text_field($posted['signup_lastname']) : '')
				|| empty(isset($posted['signup_email']) ? $posted['signup_email'] : '')
				|| empty(isset($posted['signup_validate_email']) ? $posted['signup_validate_email'] : '')
				|| (!$no_phone && empty(isset($posted['signup_phone']) ? $posted['signup_phone'] : '') && $phone_required)
				|| ("YES" === $task->need_details && $details_required && '' === (isset($posted['signup_item']) ? sanitize_text_field($posted['signup_item']) : ''))
				|| ("YES" === $task->enable_quantities && !isset($posted['signup_item_qty']))
			) {
				$error_count++;
				PTA_SUS_Messages::add_error(apply_filters('pta_sus_public_output', __('Please complete all required fields.', 'pta-volunteer-sign-up-sheets'), 'required_fields_error_message'));
			}
		}

		// Check duplicates (only if no errors so far and not editing an existing signup)
		// When editing, duplicate checks would incorrectly flag the user's own existing signup
		$perform_duplicate_checks = apply_filters('pta_sus_perform_duplicate_checks', true, $task, $sheet);
		if ($perform_duplicate_checks && $error_count === 0 && $editing_signup_id === 0) {
			$error_count += self::check_signup_duplicates($posted, $task, $sheet, $no_global_overlap);
		}

		return $error_count;
	}

	/**
	 * Check for duplicate signups
	 * Consolidates all duplicate checking logic into one method
	 *
	 * @param array $posted Posted form data
	 * @param PTA_SUS_Task|object $task Task object
	 * @param PTA_SUS_Sheet|object $sheet Sheet object
	 * @param bool $no_global_overlap Whether to check global overlap
	 * @return int Number of duplicate errors found
	 */
	public static function check_signup_duplicates($posted, $task, $sheet, $no_global_overlap = false) {
		$error_count = 0;

		// Check for duplicate signups if not allowed
		if ('NO' === $task->allow_duplicates) {
			if (PTA_SUS_Signup_Functions::check_duplicate_signup($posted['signup_task_id'], $posted['signup_date'], $posted['signup_firstname'], $posted['signup_lastname'])) {
				$error_count++;
				PTA_SUS_Messages::add_error(apply_filters('pta_sus_public_output', __('You are already signed up for this task!', 'pta-volunteer-sign-up-sheets'), 'signup_duplicate_error_message'));
			}
		}

		// Check for duplicate time signups (sheet-level)
		if (!$sheet->duplicate_times) {
			if (PTA_SUS_Signup_Functions::check_duplicate_time_signup($sheet, $task, $posted['signup_date'], $posted['signup_firstname'], $posted['signup_lastname'])) {
				$error_count++;
				PTA_SUS_Messages::add_error(apply_filters('pta_sus_public_output', __('You are already signed up for another task in this time frame!', 'pta-volunteer-sign-up-sheets'), 'signup_duplicate_time_error_message'));
			}
		}

		// Check for global overlap if enabled
		if ($no_global_overlap) {
			if (PTA_SUS_Signup_Functions::check_duplicate_time_signup($sheet, $task, $posted['signup_date'], $posted['signup_firstname'], $posted['signup_lastname'], $check_all = true)) {
				$error_count++;
				PTA_SUS_Messages::add_error(apply_filters('pta_sus_public_output', __('You are already signed up for another task in this time frame!', 'pta-volunteer-sign-up-sheets'), 'signup_duplicate_time_error_message'));
			}
		}

		return $error_count;
	}

	/**
	 * Get list of required signup fields for a given task
	 * Used for form display to mark fields as required
	 *
	 * @param int $task_id Task ID
	 * @param array $main_options Main plugin options (optional, will be fetched if not provided)
	 * @return array Array of required field names (without 'signup_' prefix)
	 */
	public static function get_required_signup_fields($task_id, $main_options = null) {
		// bare minimum required fields
		$required = array('firstname','lastname','email');
		
		// Get options if not provided
		if (null === $main_options) {
			$main_options = get_option('pta_volunteer_sus_main_options');
		}
		
		// check if phone is required
		if( isset($main_options['phone_required']) && $main_options['phone_required'] && true !== $main_options['no_phone']) {
			$required[] = 'phone';
		}
		
		// get task so can check if details are required
		$task = pta_sus_get_task( $task_id );
		if($task && 'YES' === $task->details_required && 'YES' === $task->need_details) {
			$required[] = 'item';
		}
		
		return apply_filters('pta_sus_admin_signup_required_fields', $required, $task_id);
	}

}

