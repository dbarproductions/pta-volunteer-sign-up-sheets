<?php
/**
* Email Functions
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Emails {

	public $email_options;
	public $main_options;
	public $validation_options;
	public $data;
    public $last_reminder;

	public function __construct() {
		$this->email_options = get_option( 'pta_volunteer_sus_email_options' );
		$this->main_options = get_option( 'pta_volunteer_sus_main_options' );
		$this->validation_options = get_option( 'pta_volunteer_sus_validation_options' );
		$this->data =new PTA_SUS_Data();

	} // Construct

	private function get_email_headers($from, $replyto, $use_html = false) {
		$headers = array();
		if($use_html) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}
		$headers[] = "From: " . get_bloginfo('name') . " <" . $from . ">";
		if(is_array($replyto)) {
			foreach ($replyto as $reply) {
				$headers[] = "Reply-To: <" . $reply . ">";
			}
		} else {
			$headers[] = "Reply-To: <" . $replyto . ">";
		}
		return $headers;
	}

	/**
    * Send signs up & reminder emails
    * 
    * @param    int  $signup_id the signup id
    * @param    bool $reminder is this a reminder email?
	 * @param    bool $clear is this a clear email?
	 * @param    bool $reschedule is this a reschedule email?
	 * @param    string $action the action being performed, if not one of the boolean values
    * @return   bool true if success or email does not need to be sent. False on sending failure
    */
    public function send_mail($signup_id, $reminder=false, $clear=false, $reschedule=false, $action='') {
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
			if('default' === $sheet->clear_emails && isset($this->email_options['disable_emails']) && $this->email_options['disable_emails']) {
				return true;
			}
			if('none' === $sheet->clear_emails) {
				return true;
			}
		}
		// maybe don't send confirmation emails
		if( $confirmation ) {
			if('default' === $sheet->signup_emails) {
				if(isset($this->email_options['disable_emails']) && $this->email_options['disable_emails']) {
					return true;
				}
				if(isset($this->email_options['no_confirmation_emails']) && $this->email_options['no_confirmation_emails']) {
					return true;
				}
			}
			if('none' === $sheet->signup_emails) {
				return true;
			}
		}

		// Maybe don't send reminder emails
		if( isset($this->email_options['no_reminder_emails']) && $this->email_options['no_reminder_emails'] && $reminder) {
	    	return true;
	    }

		$use_html = isset($this->email_options['use_html']) && $this->email_options['use_html'];

        do_action( 'pta_sus_before_create_email', $signup, $task, $sheet, $reminder, $clear, $reschedule );
        
        $from = apply_filters('pta_sus_from_email', $this->email_options['from_email'], $signup, $task, $sheet, $reminder, $clear, $reschedule);
        if (empty($from)) $from = get_bloginfo('admin_email');

		$subject = $message = $validation_link = '';
		$signup_validation = false;
        if($reminder) {
        	if( 2 == $reminder && isset($this->email_options['reminder2_email_subject']) && '' !== $this->email_options['reminder2_email_subject']) {
		        $subject = $this->email_options['reminder2_email_subject'];
	        } else {
		        $subject = $this->email_options['reminder_email_subject'];
	        }
	        if( 2 == $reminder && isset($this->email_options['reminder2_email_template']) && '' !== $this->email_options['reminder2_email_template']) {
		        $message = $this->email_options['reminder2_email_template'];
	        } else {
		        $message = $this->email_options['reminder_email_template'];
	        }
            
        } elseif ($clear) {
            $subject = $this->email_options['clear_email_subject'];
            $message = $this->email_options['clear_email_template'];
        } elseif ($reschedule) {
            $subject = $this->email_options['reschedule_email_subject'];
            $message = $this->email_options['reschedule_email_template'];
        } elseif($confirmation) {
            $subject = $this->email_options['confirmation_email_subject'];
            $message = $this->email_options['confirmation_email_template'];
        } elseif('validate_signup' === $action) {
            $subject = $this->validation_options['signup_validation_email_subject'];
            $message = $this->validation_options['signup_validation_email_template'];
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
					if( !isset($this->email_options['no_chair_emails']) || ! $this->email_options['no_chair_emails'] ) {
						$cc_emails = $chair_emails;
					}
				} else {
					$cc_emails = $chair_emails;
				}
			} elseif($confirmation && in_array($sheet->signup_emails, array('default','chair','both'))) {
				if('default' == $sheet->signup_emails) {
					if( !isset($this->email_options['no_chair_emails']) || ! $this->email_options['no_chair_emails'] ) {
						$cc_emails = $chair_emails;
					}
				} else {
					$cc_emails = $chair_emails;
				}
			}
		}
	    
	    // If global CC is set, and it's a valid email, add to cc_emails
	    $use_global_cc = true;
	    if($signup_validation && (!isset($this->validation_options['disable_cc_validation_signup_emails']) || $this->validation_options['disable_cc_validation_signup_emails']) ) {
		    $use_global_cc = false;
	    }
	    $global_cc = $use_global_cc && isset($this->email_options['cc_email']) && is_email($this->email_options['cc_email']) ? $this->email_options['cc_email'] : '';
	    
	    // other plugins can modify CC address, or set it blank to disable
	    $cc = apply_filters('pta_sus_email_ccmail', $global_cc, $signup, $task, $sheet, $reminder, $clear, $reschedule);
	    	
        if(!empty($cc) && is_email($cc)) {
		    if(empty($cc_emails)) {
			    $cc_emails = array($cc);
		    } else {
			    $cc_emails[] = $cc;
		    }
	    }
	
	    if( isset($this->email_options['replyto_chairs']) && $this->email_options['replyto_chairs'] && !empty($chair_emails)) {
	        $replyto = apply_filters('pta_sus_replyto_chair_emails', $chair_emails, $signup, $task, $sheet, $reminder, $clear, $reschedule);
	    } else {
		    $replyto = apply_filters('pta_sus_replyto_email', $this->email_options['replyto_email'], $signup, $task, $sheet, $reminder, $clear, $reschedule );
	    }
	    
	    if (empty($replyto)) $replyto = get_bloginfo('admin_email');

		$headers = $this->get_email_headers($from,$replyto,$use_html);

        if ( !$reminder && !$this->email_options['individual_emails'] ) {
            if (!empty($cc_emails)) {
                // CC to all chairs for signups/clears, but not reminders
                foreach ($cc_emails as $cc) {
                    $headers[] = 'Bcc: ' . $cc;
                }
            }
        }

	    $message = PTA_SUS_Template_Tags::process_text($message, $signup, $reminder, $clear, $reschedule);
	    $subject = PTA_SUS_Template_Tags::process_text($subject, $signup, $reminder, $clear, $reschedule);

        if( $reminder && $this->main_options['detailed_reminder_admin_emails'] ) {
            $this->last_reminder = "To: " . $to . "\r\n\r\n" . $message . "\r\n\r\n\r\n";
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
        	if($this->email_options['individual_emails'] && !empty($cc_emails) && !$reminder) {
        		// Send out first email to the original TO address, set errors to result (bool)
				$sent = wp_mail($to, $subject, $message, $headers, $attachments);
		        // loop through all chair_emails and send individually
		        foreach ($cc_emails as $to) {
		            if(is_email($to)) {
				        $result = wp_mail($to, $subject, $message, $headers, $attachments);
				        if(false === $result) {
					        $sent = false;
				        }
			        }
		        }
		        return $sent;
	        } else {
	        	// sending with CC/BCC in headers
		        return wp_mail($to, $subject, $message, $headers, $attachments);
	        }

        } else {
            return true;
        }
    }

	public function send_user_validation_email($firstname, $lastname, $email) {
		$firstname  = sanitize_text_field($firstname);
		$lastname = sanitize_text_field($lastname);
		$email = sanitize_email($email);
		if(empty($firstname) || empty($lastname) || empty($email)) return false;
		$user_validation_template = "
Please click on, or copy and paste, the link below to validate yourself:
{validation_link}
	    ";
		$subject = $this->validation_options['user_validation_email_subject'] ?? __('Your Validation Link', 'pta-volunteer-sign-up-sheets');
		$message = $this->validation_options['user_validation_email_template'] ?? $user_validation_template;
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
			($this->validation_options['validation_code_expiration_hours'] ?? 48),
		);

		// Allow extension to modify/add to search and replace arrays
		$search = apply_filters('pta_sus_validation_email_search', $search, $firstname, $lastname, $email);
		$replace = apply_filters('pta_sus_validation_email_replace', $replace, $firstname, $lastname, $email);

		$message = str_replace($search, $replace, $message);
		$subject = str_replace($search, $replace, $subject);
		$to = sanitize_text_field($firstname) . ' ' . sanitize_text_field($lastname) . ' <'. sanitize_email($email) . '>';
		$to = str_replace( ',', '', $to);
		$use_html = isset($this->email_options['use_html']) && $this->email_options['use_html'];
		$domain = parse_url(get_site_url(), PHP_URL_HOST);
		$from = 'no-reply@' . $domain;
		$headers = $this->get_email_headers($from,$from,$use_html);
		// Allow other plugins to determine if we should send this email -- return false to not send
		$send_email = apply_filters( 'pta_sus_send_validation_email_check', true, $firstname, $lastname, $email );
		if($send_email) {
			return wp_mail($to, $subject, $message, $headers);
		} else {
			return true;
		}
	}

    public function send_reminders() {
        $limit = false;
        $now = current_time('timestamp');

        // Check reminder email limit
        if(isset($this->email_options['reminder_email_limit']) && '' != $this->email_options['reminder_email_limit'] && 0 < $this->email_options['reminder_email_limit']) {
            $limit = (int)$this->email_options['reminder_email_limit'];
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

        if (!empty($reminder_events)) {
            $reminders_log = '';
            foreach ($reminder_events as $event) {
                if(!is_email($event->email)) continue; // Final email validation

                // Check if we have reached our hourly limit
                if ($limit && !empty($last_batch)) {
                    if ( $limit <= ($last_batch['num'] + $reminder_count) ) {
                        break;
                    }
                }

                $reminder = $event->reminder_num;

                if ( $this->send_mail( $event->signup_id, $reminder ) ) {
                    $reminder_count++;
                    $reminders_log .= $this->last_reminder;

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

                    do_action('pta_sus_reminder_sent', $event, $this );
                }
            }

            if($limit && !empty($last_batch)) {
                $last_batch['num'] += $reminder_count;
                update_option( 'pta_sus_reminders_last_batch', $last_batch );
            }

            if ( 0 < $reminder_count && $this->main_options['enable_cron_notifications'] ) {
                $to = get_bloginfo( 'admin_email' );
                $subject = __("Volunteer Signup Reminders sent", 'pta-volunteer-sign-up-sheets');
                $message = __("Volunteer signup sheet CRON job has been completed.", 'pta-volunteer-sign-up-sheets')."\r\n\r\n";
                $message .= sprintf( __("%d reminder emails were sent.", 'pta-volunteer-sign-up-sheets'), $reminder_count ) ."\r\n\r\n";
                if ($this->main_options['detailed_reminder_admin_emails']) {
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

    public function send_reschedule_emails() {
        $limit = false;
        $now = current_time( 'timestamp' );
        $reschedule_queue = get_option('pta_sus_rescheduled_signup_ids', array());
        if(empty($reschedule_queue)) {
			return false;
        };
        // This function is used to check if we need to send out reminder emails or not
        if(isset($this->email_options['reminder_email_limit']) && '' != $this->email_options['reminder_email_limit'] && 0 < $this->email_options['reminder_email_limit']) {
            $limit = (int)$this->email_options['reminder_email_limit'];
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


        // Next, go through each reschedule event and prepare/send an email
        foreach ($reschedule_queue as $index => $signup_data) {

            // Check if we have reached our hourly limit or not
            if ($limit && !empty($last_batch)) {
                if ( $limit <= ($last_batch['num'] + $reschedule_count) ) {
                    // limit reached, so break out of foreach loop
                    break;
                }
            }

            // $signup_data can be either an ID (legacy) or an array (new format with signup data)
            // Pass it directly to send_mail which now handles both formats
            if ( $this->send_mail( $signup_data, false, false, true ) ) {
                // Keep track of # of emails sent
                $reschedule_count++;
                unset($reschedule_queue[$index]); // remove it from queue
            }
        }

        if($limit && !empty($last_batch)) {
            // increment our last batch num by number of reminders sent
            $last_batch['num'] += $reschedule_count;
            update_option( 'pta_sus_reschedule_emails_last_batch', $last_batch );
        }

        // update queue
        update_option('pta_sus_rescheduled_signup_ids', $reschedule_queue);

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
    } // Send Reschedule Emails

} // End of class
/* EOF */
