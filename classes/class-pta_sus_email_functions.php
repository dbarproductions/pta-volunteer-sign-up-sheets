<?php
/**
 * Email Functions Helper Class
 * Static helper methods for sending emails
 *
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PTA_SUS_Email_Functions {

	/**
	 * Get email options
	 * Cached to avoid multiple get_option calls
	 *
	 * @return array Email options
	 */
	private static function get_email_options() {
		static $email_options = null;
		if (null === $email_options) {
			$email_options = get_option('pta_volunteer_sus_email_options');
		}
		return $email_options;
	}

	/**
	 * Get main options
	 * Cached to avoid multiple get_option calls
	 *
	 * @return array Main options
	 */
	private static function get_main_options() {
		static $main_options = null;
		if (null === $main_options) {
			$main_options = get_option('pta_volunteer_sus_main_options');
		}
		return $main_options;
	}

	/**
	 * Get validation options
	 * Cached to avoid multiple get_option calls
	 *
	 * @return array Validation options
	 */
	private static function get_validation_options() {
		static $validation_options = null;
		if (null === $validation_options) {
			$validation_options = get_option('pta_volunteer_sus_validation_options');
		}
		return $validation_options;
	}

	/**
	 * Get email headers
	 *
	 * @param string|array $from From email address
	 * @param string|array $replyto Reply-to email address(es)
	 * @param bool $use_html Whether to use HTML content type
	 * @return array Array of email headers
	 */
	private static function get_email_headers($from, $replyto, $use_html = false) {
		$headers = array();
		if($use_html) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}
		// Encode site name if it contains special characters to prevent header issues
		$site_name = get_bloginfo('name');
		// Use sanitize_email to ensure from address is valid, but keep original for display
		$from_email = is_email($from) ? $from : get_bloginfo('admin_email');
		// Format From header with proper encoding for display name
		$headers[] = "From: " . $site_name . " <" . $from_email . ">";
		if(is_array($replyto)) {
			foreach ($replyto as $reply) {
				// Validate each reply-to email before adding
				if (is_email($reply)) {
					$headers[] = "Reply-To: <" . $reply . ">";
				}
			}
		} else {
			// Validate reply-to email before adding
			if (is_email($replyto)) {
				$headers[] = "Reply-To: <" . $replyto . ">";
			}
		}
		return $headers;
	}

	/**
	 * Send signup & reminder emails
	 * 
	 * @param int|array $signup_id The signup ID or signup data array
	 * @param bool $reminder Is this a reminder email?
	 * @param bool $clear Is this a clear email?
	 * @param bool $reschedule Is this a reschedule email?
	 * @param string $action The action being performed, if not one of the boolean values
	 * @return bool|string True if success or email does not need to be sent. False on sending failure. String if detailed reminder info needed.
	 */
	public static function send_mail($signup_id, $reminder = false, $clear = false, $reschedule = false, $action = '') {
		$email_options = self::get_email_options();
		$main_options = self::get_main_options();
		$validation_options = self::get_validation_options();

		// Accept either signup ID (int) or signup data (array) for reschedule emails after signups are deleted
		if (is_array($signup_id)) {
			// Convert array to stdClass object for compatibility
			$signup = (object) $signup_id;
		} else {
			$signup = pta_sus_get_signup($signup_id);
		}
		if(!$signup) return false;
		
		// If signup was passed as array, task and sheet might not exist anymore, so try to load them
		// If they don't exist, we can't send the email properly
		$task = pta_sus_get_task($signup->task_id);
		if (!$task) return false;
		$sheet = pta_sus_get_sheet($task->sheet_id);
		if (!$sheet) return false;

		$confirmation = !($reminder || $clear || $reschedule) && empty($action);

		// maybe don't send clear emails
		if($clear) {
			if('default' === $sheet->clear_emails && isset($email_options['disable_emails']) && $email_options['disable_emails']) {
				return true;
			}
			if('none' === $sheet->clear_emails) {
				return true;
			}
		}
		// maybe don't send confirmation emails
		if( $confirmation ) {
			if('default' === $sheet->signup_emails) {
				if(isset($email_options['disable_emails']) && $email_options['disable_emails']) {
					return true;
				}
				if(isset($email_options['no_confirmation_emails']) && $email_options['no_confirmation_emails']) {
					return true;
				}
			}
			if('none' === $sheet->signup_emails) {
				return true;
			}
		}

		// Maybe don't send reminder emails
		if( isset($email_options['no_reminder_emails']) && $email_options['no_reminder_emails'] && $reminder) {
			return true;
		}

		$use_html = isset($email_options['use_html']) && $email_options['use_html'];

		do_action( 'pta_sus_before_create_email', $signup, $task, $sheet, $reminder, $clear, $reschedule );
		
		$from = apply_filters('pta_sus_from_email', $email_options['from_email'], $signup, $task, $sheet, $reminder, $clear, $reschedule);
		if (empty($from)) $from = get_bloginfo('admin_email');

		$subject = $message = $validation_link = '';
		$signup_validation = false;
		if($reminder) {
			if( 2 == $reminder && isset($email_options['reminder2_email_subject']) && '' !== $email_options['reminder2_email_subject']) {
				$subject = $email_options['reminder2_email_subject'];
			} else {
				$subject = $email_options['reminder_email_subject'];
			}
			if( 2 == $reminder && isset($email_options['reminder2_email_template']) && '' !== $email_options['reminder2_email_template']) {
				$message = $email_options['reminder2_email_template'];
			} else {
				$message = $email_options['reminder_email_template'];
			}
			
		} elseif ($clear) {
			$subject = $email_options['clear_email_subject'];
			$message = $email_options['clear_email_template'];
		} elseif ($reschedule) {
			$subject = $email_options['reschedule_email_subject'];
			$message = $email_options['reschedule_email_template'];
		} elseif($confirmation) {
			$subject = $email_options['confirmation_email_subject'];
			$message = $email_options['confirmation_email_template'];
		} elseif('validate_signup' === $action) {
			$subject = $validation_options['signup_validation_email_subject'];
			$message = $validation_options['signup_validation_email_template'];
			$validation_link = pta_create_validation_link($signup->firstname,$signup->lastname,$signup->email,$signup_id,'validate_signup');
			$signup_validation = true;
		}
		PTA_SUS_Template_Tags::add_tag('{validation_link}', $validation_link);
		
		// Allow extensions to modify subject and template
		$subject = stripslashes(apply_filters('pta_sus_email_subject', $subject, $signup, $reminder, $clear, $reschedule, $action));
		$message = stripslashes(apply_filters('pta_sus_email_template', $message, $signup, $reminder, $clear, $reschedule, $action));

		if(empty($subject) || empty($message)) {
			return false;
		}

		// convert old line breaks to html when using html
		if($use_html) {
			$message = wpautop($message, false);
		}

		// Get Chair emails
		if (isset($sheet->position) && '' != $sheet->position) {
			$chair_emails = PTA_SUS_Template_Tags::get_member_directory_emails($sheet->position);
		} else {
			if('' == $sheet->chair_email) {
				$chair_emails = false;
			} else {
				$chair_emails = explode(',', $sheet->chair_email);
			}
		}

		$to_chair = false;
		$to = $signup->firstname . ' ' . $signup->lastname . ' <'. $signup->email . '>';
		$to = str_replace( ',', '', $to);
		// $to should always go to signup user unless it's clear/confirmation and only chair is specified in the new sheet settings
		if ( ($clear && 'chair' === $sheet->clear_emails) || ($confirmation && 'chair' === $sheet->signup_emails) ) {
			$to_chair = true;
			if(empty($chair_emails)) {
				// return since nobody to send to
				return true;
			}
			$to = $chair_emails;
		}

		// other extensions can modify $to address
		$to = apply_filters('pta_sus_email_recipient', $to, $signup, $task, $sheet, $reminder, $clear, $reschedule);

		$cc_emails = array();

		if(!$to_chair) {
			// check if we should CC chairs
			if($clear && in_array($sheet->clear_emails, array('default','chair','both'))) {
				if('default' == $sheet->clear_emails) {
					if( !isset($email_options['no_chair_emails']) || ! $email_options['no_chair_emails'] ) {
						$cc_emails = $chair_emails;
					}
				} else {
					$cc_emails = $chair_emails;
				}
			} elseif($confirmation && in_array($sheet->signup_emails, array('default','chair','both'))) {
				if('default' == $sheet->signup_emails) {
					if( !isset($email_options['no_chair_emails']) || ! $email_options['no_chair_emails'] ) {
						$cc_emails = $chair_emails;
					}
				} else {
					$cc_emails = $chair_emails;
				}
			}
		}
		
		// Normalize $cc_emails to a flat array of valid email addresses
		// $chair_emails can be false, an array, or a comma-separated string
		$valid_cc_emails = array();
		if (!empty($cc_emails)) {
			if (is_array($cc_emails)) {
				// Flatten array and validate each email
				foreach ($cc_emails as $email) {
					// Handle case where $email might be an array (shouldn't happen, but defensive)
					if (is_array($email)) {
						foreach ($email as $sub_email) {
							if (is_email($sub_email)) {
								$valid_cc_emails[] = sanitize_email($sub_email);
							}
						}
					} else {
						// Trim whitespace and validate
						$email = trim($email);
						if (is_email($email)) {
							$valid_cc_emails[] = sanitize_email($email);
						}
					}
				}
			} else {
				// Handle string (comma-separated emails)
				$emails = explode(',', $cc_emails);
				foreach ($emails as $email) {
					$email = trim($email);
					if (is_email($email)) {
						$valid_cc_emails[] = sanitize_email($email);
					}
				}
			}
		}
		
		// If global CC is set, and it's a valid email, add to cc_emails
		$use_global_cc = true;
		if($signup_validation && (!isset($validation_options['disable_cc_validation_signup_emails']) || $validation_options['disable_cc_validation_signup_emails']) ) {
			$use_global_cc = false;
		}
		$global_cc = $use_global_cc && isset($email_options['cc_email']) && is_email($email_options['cc_email']) ? $email_options['cc_email'] : '';
		
		// other plugins can modify CC address, or set it blank to disable
		$cc = apply_filters('pta_sus_email_ccmail', $global_cc, $signup, $task, $sheet, $reminder, $clear, $reschedule);
			
		if(!empty($cc) && is_email($cc)) {
			$valid_cc_emails[] = sanitize_email($cc);
		}
		
		// Remove duplicates and reassign
		$cc_emails = array_unique($valid_cc_emails);

		if( isset($email_options['replyto_chairs']) && $email_options['replyto_chairs'] && !empty($chair_emails)) {
			$replyto = apply_filters('pta_sus_replyto_chair_emails', $chair_emails, $signup, $task, $sheet, $reminder, $clear, $reschedule);
		} else {
			$replyto = apply_filters('pta_sus_replyto_email', $email_options['replyto_email'], $signup, $task, $sheet, $reminder, $clear, $reschedule );
		}
		
		if (empty($replyto)) $replyto = get_bloginfo('admin_email');

		$headers = self::get_email_headers($from,$replyto,$use_html);

		if ( !$reminder && !$email_options['individual_emails'] ) {
			if (!empty($cc_emails)) {
				// Add BCC headers for chairs/CC recipients
				// Validate each email before adding to prevent SMTP issues
				// Using multiple BCC headers (one per recipient) is valid per RFC 5322
				// Some SMTP services prefer this over comma-separated addresses
				foreach ($cc_emails as $cc_email) {
					// Double-check validation (should already be validated, but defensive)
					if (is_email($cc_email)) {
						$headers[] = 'Bcc: ' . sanitize_email($cc_email);
					}
				}
			}
		}

		$message = PTA_SUS_Template_Tags::process_text($message, $signup, $reminder, $clear, $reschedule);
		$subject = PTA_SUS_Template_Tags::process_text($subject, $signup, $reminder, $clear, $reschedule);

		$last_reminder = '';
		if( $reminder && $main_options['detailed_reminder_admin_emails'] ) {
			$last_reminder = "To: " . $to . "\r\n\r\n" . $message . "\r\n\r\n\r\n";
		}

		// allow other plugins to add attachments to emails where it makes sense
		$attachments = array();
		if($confirmation) {
			$attachments = apply_filters('pta_sus_confirmation_email_attachments', array(), $signup, $task, $sheet);
		}
		if($reminder) {
			$attachments = apply_filters('pta_sus_reminder_email_attachments', array(), $signup, $task, $sheet);
		}
		if($reschedule) {
			$attachments = apply_filters('pta_sus_reschedule_email_attachments', array(), $signup, $task, $sheet);
		}

		do_action( 'pta_sus_before_send_email', $to, $subject, $message, $headers );

		// Allow other plugins to determine if we should send this email -- return false to not send
		$send_email = apply_filters( 'pta_sus_send_email_check', true, $signup, $task, $sheet, $reminder, $clear, $reschedule );

		if($send_email && !empty($subject) && !empty($message)) {
			if($email_options['individual_emails'] && !empty($cc_emails) && !$reminder) {
				// Send out first email to the original TO address, set errors to result (bool)
				$sent = wp_mail($to, $subject, $message, $headers, $attachments);
				// loop through all CC/BCC emails and send individually (avoids CC/BCC header issues with some SMTP services)
				// Use $cc_recipient instead of $to to preserve original recipient
				foreach ($cc_emails as $cc_recipient) {
					// Validate email before sending (should already be validated, but defensive)
					if(is_email($cc_recipient)) {
						$result = wp_mail($cc_recipient, $subject, $message, $headers, $attachments);
						if(false === $result) {
							$sent = false;
						}
					}
				}
				// Return last_reminder if needed, otherwise return sent status
				return $last_reminder ? $last_reminder : $sent;
			} else {
				// sending with CC/BCC in headers
				$sent = wp_mail($to, $subject, $message, $headers, $attachments);
				// Return last_reminder if needed, otherwise return sent status
				return $last_reminder ? $last_reminder : $sent;
			}

		} else {
			return true;
		}
	}

	/**
	 * Send user validation email
	 *
	 * @param string $firstname First name
	 * @param string $lastname Last name
	 * @param string $email Email address
	 * @return bool True on success, false on failure
	 */
	public static function send_user_validation_email($firstname, $lastname, $email) {
		$email_options = self::get_email_options();
		$validation_options = self::get_validation_options();

		$firstname  = sanitize_text_field($firstname);
		$lastname = sanitize_text_field($lastname);
		$email = sanitize_email($email);
		// Validate that email is actually valid before proceeding
		if(empty($firstname) || empty($lastname) || empty($email) || !is_email($email)) return false;
		$user_validation_template = "
Please click on, or copy and paste, the link below to validate yourself:
{validation_link}
		";
		$subject = $validation_options['user_validation_email_subject'] ?? __('Your Validation Link', 'pta-volunteer-sign-up-sheets');
		$message = $validation_options['user_validation_email_template'] ?? $user_validation_template;
		// Allow extensions to modify subject and template
		$subject = stripslashes(apply_filters('pta_sus_validation_email_subject', $subject, $firstname, $lastname, $email));
		$message = stripslashes(apply_filters('pta_sus_validation_email_template', $message, $firstname, $lastname, $email));

		$validation_link = pta_create_validation_link($firstname,$lastname,$email );
		// Replace any template tags with the appropriate variables
		$search = array(
			'{firstname}',
			'{lastname}',
			'{email}',
			'{site_name}',
			'{site_url}',
			'{validation_link}',
			'{validation_code_expiration_hours}'
		);

		$replace = array(
			$firstname,
			$lastname,
			$email,
			get_bloginfo( 'name' ),
			get_bloginfo( 'url' ),
			$validation_link,
			($validation_options['validation_code_expiration_hours'] ?? 48),
		);

		// Allow extension to modify/add to search and replace arrays
		$search = apply_filters('pta_sus_validation_email_search', $search, $firstname, $lastname, $email);
		$replace = apply_filters('pta_sus_validation_email_replace', $replace, $firstname, $lastname, $email);

		$message = str_replace($search, $replace, $message);
		$subject = str_replace($search, $replace, $subject);
		$to = sanitize_text_field($firstname) . ' ' . sanitize_text_field($lastname) . ' <'. sanitize_email($email) . '>';
		$to = str_replace( ',', '', $to);
		$use_html = isset($email_options['use_html']) && $email_options['use_html'];
		// Get from email - use configured from_email if valid, otherwise use admin email
		// Don't use no-reply@domain as it may not be a valid/verified email address
		$from = apply_filters('pta_sus_from_email', $email_options['from_email'], null, null, null, false, false, false);
		if (empty($from) || !is_email($from)) {
			$from = get_bloginfo('admin_email');
		}
		$headers = self::get_email_headers($from, $from, $use_html);
		// Allow other plugins to determine if we should send this email -- return false to not send
		$send_email = apply_filters( 'pta_sus_send_validation_email_check', true, $firstname, $lastname, $email );
		if($send_email) {
			return wp_mail($to, $subject, $message, $headers);
		} else {
			return true;
		}
	}

	/**
	 * Send reminder emails for signups
	 *
	 * @return int Number of reminder emails sent
	 */
	public static function send_reminders() {
		$email_options = self::get_email_options();
		$main_options = self::get_main_options();

		$limit = false;
		$now = current_time('timestamp');

		// Check reminder email limit
		if(isset($email_options['reminder_email_limit']) && '' != $email_options['reminder_email_limit'] && 0 < $email_options['reminder_email_limit']) {
			$limit = (int)$email_options['reminder_email_limit'];
			if ( $last_batch = get_option( 'pta_sus_reminders_last_batch' ) ) {
				if( ( $now - $last_batch['time'] < 60 * 60 ) && ( $limit <= $last_batch['num'] ) ) {
					return false;
				} elseif ( $now - $last_batch['time'] >= 60 * 60 ) {
					$last_batch['num'] = 0;
					$last_batch['time'] = $now;
				}
			} else {
				$last_batch = array('num' => 0, 'time' => $now);
			}
		}

		// Get only signups that need reminders (optimized query)
		$reminder_events = PTA_SUS_Signup_Functions::get_signups_needing_reminders($now);

		$reminder_count = 0;
		$reminders_log = '';

		if (!empty($reminder_events)) {
			foreach ($reminder_events as $event) {
				// Validate event data before processing
				if(empty($event->signup_id) || !is_email($event->email)) continue; // Final email validation

				// Check if we have reached our hourly limit
				if ($limit && !empty($last_batch)) {
					if ( $limit <= ($last_batch['num'] + $reminder_count) ) {
						break;
					}
				}

				$reminder = $event->reminder_num;

				$result = self::send_mail( $event->signup_id, $reminder );
				if ($result !== false) {
					$reminder_count++;
					// If detailed reminders are enabled, result will be the reminder text
					if ($main_options['detailed_reminder_admin_emails'] && is_string($result)) {
						$reminders_log .= $result;
					}

					// Update reminder sent flag
					$update = array();
					if ( 1 === $event->reminder_num ) {
						$update['reminder1_sent'] = 1;
					}
					if ( 2 === $event->reminder_num ) {
						$update['reminder2_sent'] = 1;
					}

					// Use new signup object to update
					$signup = pta_sus_get_signup($event->signup_id);
					if ($signup) {
						foreach ($update as $key => $value) {
							$signup->$key = $value;
						}
						$signup->save();
					}

					do_action('pta_sus_reminder_sent', $event, null );
				}
			}

			if($limit && !empty($last_batch)) {
				$last_batch['num'] += $reminder_count;
				update_option( 'pta_sus_reminders_last_batch', $last_batch );
			}

			if ( 0 < $reminder_count && $main_options['enable_cron_notifications'] ) {
				$to = get_bloginfo( 'admin_email' );
				$subject = __("Volunteer Signup Reminders sent", 'pta-volunteer-sign-up-sheets');
				$message = __("Volunteer signup sheet CRON job has been completed.", 'pta-volunteer-sign-up-sheets')."\r\n\r\n";
				$message .= sprintf( __("%d reminder emails were sent.", 'pta-volunteer-sign-up-sheets'), $reminder_count ) ."\r\n\r\n";
				if ($main_options['detailed_reminder_admin_emails']) {
					$message .= "Messages Sent:\r\n\r\n";
					$message .= $reminders_log;
				}
				wp_mail($to, $subject, $message);
			}
		}

		// Set another option to save the last time any reminders were sent
		if (!$sent = get_option('pta_sus_last_reminders')) {
			$sent = array('time' => 0, 'num' => 0);
		}
		$sent['last'] = $now;
		if ( 0 < $reminder_count ) {
			$sent['time'] = $now;
			$sent['num'] = $reminder_count;
		}
		update_option( 'pta_sus_last_reminders', $sent );
		return $reminder_count;
	}

	/**
	 * Send reschedule emails from queue
	 *
	 * @return int|false Number of emails sent, or false if queue is empty
	 */
	public static function send_reschedule_emails() {
		$email_options = self::get_email_options();

		$limit = false;
		$now = current_time( 'timestamp' );
		$reschedule_queue = get_option('pta_sus_rescheduled_signup_ids', array());
		if(empty($reschedule_queue)) {
			return false;
		};
		// This function is used to check if we need to send out reminder emails or not
		if(isset($email_options['reminder_email_limit']) && '' != $email_options['reminder_email_limit'] && 0 < $email_options['reminder_email_limit']) {
			$limit = (int)$email_options['reminder_email_limit'];
			if ( $last_batch = get_option( 'pta_sus_reschedule_emails_last_batch' ) ) {
				if( ( $now - $last_batch['time'] < 60 * 60 ) && ( $limit <= $last_batch['num'] ) ) {
					// past our limit and less than an hour, so return
					return false;
				} elseif ( $now - $last_batch['time'] >= 60 * 60 ) {
					// more than an hour has passed, reset last batch
					$last_batch['num'] = 0;
					$last_batch['time'] = $now;
				}
			} else {
				// Option doesn't exist yet, set default
				$last_batch = array();
				$last_batch['num'] = 0;
				$last_batch['time'] = $now;
			}
		}

		$reschedule_count = 0;
		$remaining_queue = array(); // Track items that weren't sent yet

		// Next, go through each reschedule event and prepare/send an email
		foreach ($reschedule_queue as $index => $signup_data) {

			// Check if we have reached our hourly limit or not
			if ($limit && !empty($last_batch)) {
				if ( $limit <= ($last_batch['num'] + $reschedule_count) ) {
					// limit reached, so add remaining items to queue and break
					$remaining_queue = array_merge($remaining_queue, array_slice($reschedule_queue, $index));
					break;
				}
			}

			// $signup_data can be either an ID (legacy) or an array (new format with signup data)
			// Pass it directly to send_mail which now handles both formats
			if ( self::send_mail( $signup_data, false, false, true ) ) {
				// Keep track of # of emails sent
				$reschedule_count++;
				// Don't add to remaining_queue - successfully sent, so remove from queue
			} else {
				// Failed to send - keep in queue for retry
				$remaining_queue[] = $signup_data;
			}
		}

		if($limit && !empty($last_batch)) {
			// increment our last batch num by number of reminders sent
			$last_batch['num'] += $reschedule_count;
			update_option( 'pta_sus_reschedule_emails_last_batch', $last_batch );
		}

		// update queue with remaining items (unsent or failed)
		update_option('pta_sus_rescheduled_signup_ids', $remaining_queue);

		// Set another option to save the last time any reminders were sent
		if (!$sent = get_option('pta_sus_last_reschedule_emails')) {
			$sent = array('time' => 0, 'num' => 0);
		}
		$sent['last'] = $now;
		if ( 0 < $reschedule_count ) {
			$sent['time'] = $now;
			$sent['num'] = $reschedule_count;
		}
		update_option( 'pta_sus_last_reschedule_emails', $sent );
		return $reschedule_count;
	}

	/**
	 * Queue signups for reschedule emails
	 * Stores signup data in the reschedule queue so emails can be sent later
	 * 
	 * @param array $tasks Array of PTA_SUS_Task objects to queue signups for
	 * @return void
	 */
	public static function queue_reschedule_emails($tasks) {
		if(empty($tasks)) {
			return;
		}
		// Store signup data arrays instead of just IDs, so emails can be sent even after signups are deleted
		$reschedule_queue = get_option('pta_sus_rescheduled_signup_ids', array());
		foreach ($tasks AS $task) {
			$id = absint($task->id);
			$signups = PTA_SUS_Signup_Functions::get_signups_for_task($id);
			if(empty($signups)) continue;
			foreach($signups as $signup) {
				// Store signup data as array so we can send emails even if signup is deleted
				$signup_data = $signup->to_array();
				$reschedule_queue[] = $signup_data;
			}
		}
		update_option('pta_sus_rescheduled_signup_ids', $reschedule_queue);
	}
}

