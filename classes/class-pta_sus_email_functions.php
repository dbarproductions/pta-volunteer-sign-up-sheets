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
	 * Track whether we've already disabled old Customizer email filters for this request.
	 *
	 * @var bool
	 */
	private static $customizer_filters_removed = false;

	/**
	 * Disable old Customizer email subject/body filters (for legacy versions)
	 *
	 * This ensures the main plugin's template system controls email content even
	 * when an old Customizer version is still active. We only remove callbacks
	 * from PTA_SUS_Customizer_Public and leave other extensions intact.
	 *
	 * @since 6.2.0
	 * @return void
	 */
	private static function maybe_disable_customizer_email_filters() {
		if ( self::$customizer_filters_removed ) {
			return;
		}

		// Only needed when an older Customizer version is active
		if ( ! defined( 'PTA_VOL_SUS_CUSTOMIZER_VERSION' ) ) {
			return;
		}

		// In future Customizer versions the filters will be removed there; this is for legacy.
		if ( version_compare( PTA_VOL_SUS_CUSTOMIZER_VERSION, '4.1.0', '>=' ) ) {
			return;
		}

		global $wp_filter;

		foreach ( array( 'pta_sus_email_subject', 'pta_sus_email_template' ) as $tag ) {
			if ( empty( $wp_filter[ $tag ] ) || ! isset( $wp_filter[ $tag ]->callbacks ) ) {
				continue;
			}

			foreach ( $wp_filter[ $tag ]->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback_id => $callback ) {
					$fn = $callback['function'];
					// Look for methods on PTA_SUS_Customizer_Public instances
					if ( is_array( $fn ) && is_object( $fn[0] ) && 'PTA_SUS_Customizer_Public' === get_class( $fn[0] ) ) {
						remove_filter( $tag, $fn, $priority );
					}
				}
			}
		}

		self::$customizer_filters_removed = true;
	}

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
	 * @param string $from_name Display name for From header (falls back to site name if empty)
	 * @return array Array of email headers
	 */
	private static function get_email_headers($from, $replyto, $use_html = false, $from_name = '') {
		$headers = array();
		if($use_html) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}
		// Use template from_name if provided, otherwise fall back to site name
		$display_name = !empty($from_name) ? $from_name : get_bloginfo('name');
		// Use sanitize_email to ensure from address is valid, but keep original for display
		$from_email = is_email($from) ? $from : get_bloginfo('admin_email');
		// Format From header with proper encoding for display name
		$headers[] = "From: " . $display_name . " <" . $from_email . ">";
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
		
		$subject = $message = $validation_link = '';
		$signup_validation = false;
		$email_type = '';
		$from = '';
		$from_name = '';

		// Determine email type
		if($reminder) {
			$email_type = ( 2 == $reminder ) ? 'reminder2' : 'reminder1';
		} elseif ($clear) {
			$email_type = 'clear';
		} elseif ($reschedule) {
			$email_type = 'reschedule';
		} elseif($confirmation) {
			$email_type = 'confirmation';
		} elseif('validate_signup' === $action) {
			$email_type = 'signup_validation';
			$signup_validation = true;
		}

		// Look up email template using the proper cascade:
		// Task template -> Sheet template -> System default
		$template = null;
		if ( ! empty( $email_type ) ) {
			if ( 'signup_validation' === $email_type ) {
				// Signup validation is system-wide only, not sheet/task level
				$template_id = isset( $validation_options['signup_validation_email_template_id'] ) ? absint( $validation_options['signup_validation_email_template_id'] ) : 0;
				$template = self::get_email_template( $template_id, 'signup_validation' );
			} else {
				$template = self::get_active_email_template( $email_type, $sheet->id, $task->id );
			}
		}

		// Use template for subject, body, from name, and from email
		// Fall back to legacy $email_options only if no template is found
		if ( $template ) {
			$subject = $template->subject;
			$message = $template->body;
			$from = $template->get_from_email( $email_options['from_email'] ?? '' );
			$from_name = $template->get_from_name();
		} else {
			// Legacy fallback to email options
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
				$subject = $validation_options['signup_validation_email_subject'] ?? '';
				$message = $validation_options['signup_validation_email_template'] ?? '';
			}
		}

		// Handle validation link for signup validation emails
		if ( $signup_validation ) {
			$validation_link = pta_create_validation_link($signup->firstname,$signup->lastname,$signup->email,$signup_id,'validate_signup');
			if ($use_html && !empty($validation_link)) {
				$validation_link = '<a href="' . esc_url($validation_link) . '">' . esc_html($validation_link) . '</a>';
			}
		}

		// Resolve from address with fallbacks
		if ( empty($from) ) {
			$from = $email_options['from_email'] ?? '';
		}
		$from = apply_filters('pta_sus_from_email', $from, $signup, $task, $sheet, $reminder, $clear, $reschedule);
		if (empty($from)) $from = get_bloginfo('admin_email');

		PTA_SUS_Template_Tags::add_tag('{validation_link}', $validation_link);

		// Disable old Customizer email filters (subject/body) so the new template system is authoritative
		self::maybe_disable_customizer_email_filters();

		// Allow other extensions to modify subject and template
		$subject = stripslashes(apply_filters('pta_sus_email_subject', $subject, $signup, $reminder, $clear, $reschedule, $action));
		$message = stripslashes(apply_filters('pta_sus_email_template', $message, $signup, $reminder, $clear, $reschedule, $action));

		if(empty($subject) || empty($message)) {
			return false;
		}

		// Format message based on content type
		if ($use_html) {
			// When sending HTML, convert plain-text line breaks into paragraphs/BRs.
			// Normalize line endings first so wpautop sees consistent "\n" breaks.
			$message = preg_replace('/\r\n?|\r/', "\n", $message);
			// Let wpautop add <p> and <br> for both double and single line breaks.
			$message = wpautop($message, true);
		} else {
			// When sending plain text, strip HTML but preserve basic structure.
			// Convert common block/line break tags to newlines before stripping tags.
			$search = array(
				"/<br\\s*\\/?>/i",
				"/<p[^>]*>/i",
				"/<\\/p>/i",
				"/<div[^>]*>/i",
				"/<\\/div>/i",
				"/<li[^>]*>/i",
				"/<\\/li>/i",
				"/<h[1-6][^>]*>/i",
				"/<\\/h[1-6]>/i",
			);
			$replace = array(
				"\n",
				"",
				"\n\n",
				"",
				"\n\n",
				"* ",
				"\n",
				"",
				"\n\n",
			);
			$message = preg_replace($search, $replace, $message);
			// Strip any remaining tags
			$message = wp_strip_all_tags($message);
			// Decode entities and normalize newlines
			$message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');
			$message = preg_replace("/\r\n|\r/", "\n", $message);
			// Collapse excessive blank lines
			$message = preg_replace("/\n{3,}/", "\n\n", $message);
			// Wrap to a reasonable width for plain text emails
			$message = wordwrap(trim($message), 70);
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

		$headers = self::get_email_headers($from, $replyto, $use_html, $from_name);

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
		
		$use_html = isset($email_options['use_html']) && $email_options['use_html'];
		
		// Get template ID from validation options (0 = use system default)
		$template_id = isset( $validation_options['user_validation_email_template_id'] ) ? absint( $validation_options['user_validation_email_template_id'] ) : 0;
		$template = self::get_email_template( $template_id, 'user_validation' );
		
		if ( $template ) {
			$subject = $template->subject;
			$message = $template->body;
		} else {
			// Fallback to old options or default template
			$user_validation_template = "
Please click on, or copy and paste, the link below to validate yourself:
{validation_link}
			";
			$subject = $validation_options['user_validation_email_subject'] ?? __('Your Validation Link', 'pta-volunteer-sign-up-sheets');
			$message = $validation_options['user_validation_email_template'] ?? $user_validation_template;
		}
		// Allow extensions to modify subject and template
		$subject = stripslashes(apply_filters('pta_sus_validation_email_subject', $subject, $firstname, $lastname, $email));
		$message = stripslashes(apply_filters('pta_sus_validation_email_template', $message, $firstname, $lastname, $email));

		$validation_link = pta_create_validation_link($firstname,$lastname,$email );
		// Format validation link for HTML emails if enabled
		if ($use_html && !empty($validation_link)) {
			$validation_link = '<a href="' . esc_url($validation_link) . '">' . esc_html($validation_link) . '</a>';
		}
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
		// Get from name/email from template if available, fall back to email options
		$from_name = '';
		if ( $template ) {
			$from = $template->get_from_email( $email_options['from_email'] ?? '' );
			$from_name = $template->get_from_name();
		} else {
			$from = $email_options['from_email'] ?? '';
		}
		$from = apply_filters('pta_sus_from_email', $from, null, null, null, false, false, false);
		if (empty($from) || !is_email($from)) {
			$from = get_bloginfo('admin_email');
		}
		$headers = self::get_email_headers($from, $from, $use_html, $from_name);
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
				
				$use_html = isset( $email_options['use_html'] ) && $email_options['use_html'];
				
				if ( $use_html ) {
					// HTML email format
					$message = '<p>' . __("Volunteer signup sheet CRON job has been completed.", 'pta-volunteer-sign-up-sheets') . '</p>';
					$message .= '<p>' . sprintf( __("%d reminder emails were sent.", 'pta-volunteer-sign-up-sheets'), $reminder_count ) . '</p>';
					if ($main_options['detailed_reminder_admin_emails']) {
						$message .= '<h3>' . __("Messages Sent:", 'pta-volunteer-sign-up-sheets') . '</h3>';
						// Convert plain text reminders log to HTML
						$reminders_log_html = preg_replace('/\r\n?|\r/', "\n", $reminders_log);
						$reminders_log_html = wpautop($reminders_log_html, true);
						$message .= $reminders_log_html;
					}
					$headers = array('Content-Type: text/html; charset=UTF-8');
				} else {
					// Plain text email format
					$message = __("Volunteer signup sheet CRON job has been completed.", 'pta-volunteer-sign-up-sheets')."\r\n\r\n";
					$message .= sprintf( __("%d reminder emails were sent.", 'pta-volunteer-sign-up-sheets'), $reminder_count ) ."\r\n\r\n";
					if ($main_options['detailed_reminder_admin_emails']) {
						$message .= __("Messages Sent:", 'pta-volunteer-sign-up-sheets') . "\r\n\r\n";
						$message .= $reminders_log;
					}
					$headers = array();
				}
				
				wp_mail($to, $subject, $message, $headers);
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

	/**
	 * Get system default template ID for an email type
	 * Stored in options: pta_volunteer_sus_email_template_defaults
	 * 
	 * @param string $email_type Email type (confirmation, reminder1, reminder2, clear, reschedule, signup_validation)
	 * @return int Template ID, or 0 if not set
	 */
	public static function get_system_default_template_id($email_type) {
		$defaults = get_option('pta_volunteer_sus_email_template_defaults', array());
		if (isset($defaults[$email_type]) && $defaults[$email_type] > 0) {
			return absint($defaults[$email_type]);
		}
		return 0;
	}

	/**
	 * Get system default template object for an email type
	 * Handles fallback: if reminder2 not set, use reminder1
	 * 
	 * @param string $email_type Email type
	 * @return PTA_SUS_Email_Template|false Template object or false if not found
	 */
	public static function get_system_default_template($email_type) {
		$template_id = self::get_system_default_template_id($email_type);
		
		// Handle reminder2 fallback to reminder1
		if ('reminder2' === $email_type && $template_id === 0) {
			$template_id = self::get_system_default_template_id('reminder1');
		}
		
		if ($template_id > 0) {
			$template = new PTA_SUS_Email_Template($template_id);
			if ($template->id > 0) {
				return $template;
			}
		}
		
		return false;
	}

	/**
	 * Get active email template for a specific email type
	 * Checks: Task template -> Sheet template -> System default
	 * Special handling for reminder2: falls back to reminder1 (task/sheet or system default) if no reminder2 template
	 * 
	 * @param string $email_type Email type (confirmation, reminder1, reminder2, clear, reschedule, signup_validation)
	 * @param int $sheet_id Sheet ID
	 * @param int $task_id Task ID (optional, for task-specific templates)
	 * @return PTA_SUS_Email_Template|false Template object or false if not found
	 */
	public static function get_active_email_template($email_type, $sheet_id, $task_id = 0) {
		$sheet = pta_sus_get_sheet($sheet_id);
		if (!$sheet) {
			return false;
		}
		
		// Map email type to property name (same for both task and sheet)
		// Note: signup_validation and user_validation are system-wide only, not sheet/task level
		$property_map = array(
			'confirmation' => 'confirmation_email_template_id',
			'reminder1' => 'reminder1_email_template_id',
			'reminder2' => 'reminder2_email_template_id',
			'clear' => 'clear_email_template_id',
			'reschedule' => 'reschedule_email_template_id',
		);
		
		if (!isset($property_map[$email_type])) {
			return false;
		}
		
		$property_name = $property_map[$email_type];
		
		// First, check task template (if task_id provided)
		if ($task_id > 0) {
			$task = pta_sus_get_task($task_id);
			if ($task) {
				$task_template_id = isset($task->$property_name) ? absint($task->$property_name) : 0;
				// If task has a template assigned (not 0), use it
				if ($task_template_id > 0) {
					$template = new PTA_SUS_Email_Template($task_template_id);
					if ($template->id > 0) {
						return $template;
					}
				}
			}
		}
		
		// Second, check sheet template
		$sheet_template_id = isset($sheet->$property_name) ? absint($sheet->$property_name) : 0;
		if ($sheet_template_id > 0) {
			$template = new PTA_SUS_Email_Template($sheet_template_id);
			if ($template->id > 0) {
				return $template;
			}
		}
		
		// Special handling for reminder2: if no task/sheet template and no system default for reminder2,
		// fall back to reminder1 template (task/sheet reminder1 template if set, otherwise reminder1 system default)
		if ('reminder2' === $email_type) {
			// Check if there's a system default for reminder2
			$reminder2_system_default_id = self::get_system_default_template_id('reminder2');
			
			// If no system default for reminder2, use reminder1 instead
			if ($reminder2_system_default_id === 0) {
				// Check task first (if task_id provided)
				if ($task_id > 0) {
					$task = pta_sus_get_task($task_id);
					if ($task) {
						$reminder1_template_id = isset($task->reminder1_email_template_id) ? absint($task->reminder1_email_template_id) : 0;
						if ($reminder1_template_id > 0) {
							$template = new PTA_SUS_Email_Template($reminder1_template_id);
							if ($template->id > 0) {
								return $template;
							}
						}
					}
				}
				// Check sheet's reminder1 template
				$reminder1_template_id = isset($sheet->reminder1_email_template_id) ? absint($sheet->reminder1_email_template_id) : 0;
				if ($reminder1_template_id > 0) {
					$template = new PTA_SUS_Email_Template($reminder1_template_id);
					if ($template->id > 0) {
						return $template;
					}
				}
				// Fall back to reminder1 system default
				return self::get_system_default_template('reminder1');
			}
		}
		
		// Finally, fall back to system default
		return self::get_system_default_template($email_type);
	}

	/**
	 * Get templates available to current user
	 * Authors see their own + templates with author_id = 0 (available to all)
	 * Admins/Managers see all templates
	 * 
	 * @param bool $include_system_defaults Whether to include system defaults
	 * @return array Array of PTA_SUS_Email_Template objects
	 */
	public static function get_available_templates($include_system_defaults = true) {
		global $wpdb;
		$table = $wpdb->prefix . 'pta_sus_email_templates';
		
		$can_manage_others = current_user_can('manage_others_signup_sheets');
		$current_user_id = get_current_user_id();
		
		// Build WHERE clause based on permissions
		if ($can_manage_others) {
			// Admins/Managers see all templates
			$where = '';
			$params = array();
		} else {
			// Authors see their own + templates with author_id = 0
			$where = ' WHERE (author_id = %d OR author_id = 0)';
			$params = array($current_user_id);
		}
		
		$sql = "SELECT id FROM {$table}" . $where . " ORDER BY title ASC";
		
		if (!empty($params)) {
			$sql = $wpdb->prepare($sql, $params);
		}
		
		$template_ids = $wpdb->get_col($sql);
		$templates = array();
		$defaults = get_option('pta_volunteer_sus_email_template_defaults', array());
		
		foreach ($template_ids as $template_id) {
			$template = new PTA_SUS_Email_Template($template_id);
			if ($template->id > 0) {
				// Optionally exclude system defaults (check option instead of database field)
				if (!$include_system_defaults && in_array($template_id, $defaults, true)) {
					continue;
				}
				$templates[] = $template;
			}
		}
		
		return $templates;
	}

	/**
	 * Get email template by ID, with fallback to system default
	 * Used for validation emails where template_id comes from options
	 * 
	 * @param int $template_id Template ID (0 = use system default)
	 * @param string $email_type Email type for system default fallback
	 * @return PTA_SUS_Email_Template|false Template object or false if not found
	 */
	public static function get_email_template($template_id, $email_type) {
		$template_id = absint($template_id);
		
		// If template_id is 0, use system default
		if ($template_id === 0) {
			return self::get_system_default_template($email_type);
		}
		
		// Try to load the specified template
		$template = new PTA_SUS_Email_Template($template_id);
		if ($template->id > 0) {
			return $template;
		}
		
		// If template not found, fall back to system default
		return self::get_system_default_template($email_type);
	}

	/**
	 * Set system default template for an email type
	 * Updates options: pta_volunteer_sus_email_template_defaults
	 * 
	 * @param string $email_type Email type
	 * @param int $template_id Template ID (0 to unset)
	 * @return bool Success
	 */
	public static function set_system_default_template($email_type, $template_id) {
		$defaults = get_option('pta_volunteer_sus_email_template_defaults', array());
		$template_id = absint($template_id);
		
		// Validate template exists if ID > 0
		if ($template_id > 0) {
			$template = new PTA_SUS_Email_Template($template_id);
			if ($template->id === 0) {
				return false; // Template doesn't exist
			}
		}
		
		$defaults[$email_type] = $template_id;
		return update_option('pta_volunteer_sus_email_template_defaults', $defaults);
	}

	/**
	 * Check if a template is a system default
	 * Checks the pta_volunteer_sus_email_template_defaults option to see if
	 * the template ID is used as a system default for any email type
	 * 
	 * @param int $template_id Template ID to check
	 * @return bool True if template is a system default, false otherwise
	 */
	public static function is_system_default_template($template_id) {
		$template_id = absint($template_id);
		if ($template_id <= 0) {
			return false;
		}
		
		$defaults = get_option('pta_volunteer_sus_email_template_defaults', array());
		// Check if this template ID is used as a default for any email type
		return in_array($template_id, $defaults, true);
	}

	/**
	 * Get list of email types
	 * Returns an array of email types with their labels
	 * Can be filtered by extensions to add custom email types
	 * 
	 * @return array Associative array of email_type => label
	 */
	public static function get_email_types() {
		$email_types = array(
			'confirmation' => __('Confirmation Email', 'pta-volunteer-sign-up-sheets'),
			'reminder1' => __('Reminder 1 Email', 'pta-volunteer-sign-up-sheets'),
			'reminder2' => __('Reminder 2 Email', 'pta-volunteer-sign-up-sheets'),
			'clear' => __('Clear Email', 'pta-volunteer-sign-up-sheets'),
			'reschedule' => __('Reschedule Email', 'pta-volunteer-sign-up-sheets'),
			'signup_validation' => __('Signup Validation Email', 'pta-volunteer-sign-up-sheets'),
			'user_validation' => __('User Validation Email', 'pta-volunteer-sign-up-sheets'),
		);
		
		/**
		 * Filter email types
		 * Allows extensions to add custom email types
		 * 
		 * @param array $email_types Associative array of email_type => label
		 * @return array Filtered email types array
		 */
		return apply_filters('pta_sus_email_types', $email_types);
	}
}

