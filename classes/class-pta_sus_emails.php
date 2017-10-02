<?php
/**
* Email Functions
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Emails {

	public $email_options;
	public $main_options;
	public $data;
    public $last_reminder;

	public function __construct() {
		$this->email_options = get_option( 'pta_volunteer_sus_email_options' );
		$this->main_options = get_option( 'pta_volunteer_sus_main_options' );
		$this->data = new PTA_SUS_Data();

	} // Construct

    public function convert_to_plain_text($html) {
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
        $text = strip_tags($text);
        return $text;

    }

	/**
    * Send signs up & reminder emails
    * 
    * @param    int  the signup id
    *           bool signup or reminder email
    * @return   bool
    */
    public function send_mail($signup_id, $reminder=false, $clear=false) {
    	// are emails disabled? Don't send any emails if disabled
	    if(isset($this->email_options['disable_emails']) && true == $this->email_options['disable_emails']) {
	    	return true;
	    }
	    
        $signup = $this->data->get_signup($signup_id);
	    if(!$signup) return false;
        $task = $this->data->get_task($signup->task_id);
        $sheet = $this->data->get_sheet($task->sheet_id);

        do_action( 'pta_sus_before_create_email', $signup, $task, $sheet, $reminder, $clear );
        
        $from = apply_filters('pta_sus_from_email', $this->email_options['from_email'], $signup, $task, $sheet, $reminder, $clear);
        if (empty($from)) $from = get_bloginfo('admin_email');

        $to = $signup->firstname . ' ' . $signup->lastname . ' <'. $signup->email . '>';
    
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
        } else {
            $subject = $this->email_options['confirmation_email_subject'];
            $message = $this->email_options['confirmation_email_template'];
        }
        
        // Allow extensions to modify subject and template
	    $subject = stripslashes(apply_filters('pta_sus_email_subject', $subject, $signup, $reminder, $clear));
	    $message = stripslashes(apply_filters('pta_sus_email_template', $message, $signup, $reminder, $clear));

        // Get Chair emails
	    if (isset($sheet->position) && '' != $sheet->position) {
		    $chair_emails = $this->get_member_directory_emails($sheet->position);
	    } else {
		    if('' == $sheet->chair_email) {
			    $chair_emails = false;
		    } else {
			    $chair_emails = explode(',', $sheet->chair_email);
		    }
	    }
	    $cc_emails = array();
	    if(!isset($this->email_options['no_chair_emails']) || false == $this->email_options['no_chair_emails']) {
	    	$cc_emails = $chair_emails;
	    }
	    
	    // If global CC is set, and it's a valid email, add to cc_emails
	    if( isset($this->email_options['cc_email']) && is_email($this->email_options['cc_email'] ) ) {
	    	// other plugins can modify CC address, or set it blank to disable
	    	$cc = apply_filters('pta_sus_email_ccmail', $this->email_options['cc_email'], $signup, $task, $sheet, $reminder, $clear);
	    	if(!empty($cc) && is_email($cc)) {
			    if(empty($cc_emails)) {
				    $cc_emails = array($cc);
			    } else {
				    $cc_emails[] = $cc;
			    }
		    }
	    }
	    
	    // Chair names
	    $names = false;
	    if (isset($sheet->position) && '' != $sheet->position) {
		    $names = $this->get_member_directory_names($sheet->position);
		    if($names) {
		    	$chair_names = implode(', ', $names);
		    }
	    }
	    
	    if(!$names) {
		    $chair_names = $this->data->get_chair_names_html($sheet->chair_name);
	    }
	
	    if(isset($this->email_options['replyto_chairs']) && true == $this->email_options['replyto_chairs'] && !empty($chair_emails)) {
	        $replyto = apply_filters('pta_sus_replyto_chair_emails', $chair_emails, $signup, $task, $sheet, $reminder, $clear);
	    } else {
		    $replyto = apply_filters('pta_sus_replyto_email', $this->email_options['replyto_email'], $signup, $task, $sheet, $reminder, $clear);
	    }
	    
	    if (empty($replyto)) $replyto = get_bloginfo('admin_email');
	    
        $headers = array();
        $headers[]  = "From: " . get_bloginfo('name') . " <" . $from . ">";
        if(is_array($replyto)) {
        	foreach ($replyto as $reply) {
        		$headers[] = "Reply-To: " . $reply;
	        }
        } else {
	        $headers[]  = "Reply-To: " . $replyto;
        }
        $headers[]  = "Content-Type: text/plain; charset=utf-8";
        $headers[]  = "Content-Transfer-Encoding: 8bit";
        if ( !$reminder && !$this->email_options['individual_emails'] ) {
            if (!empty($cc_emails)) {
                // CC to all chairs for signups/clears, but not reminders
                foreach ($cc_emails as $cc) {
                    $headers[] = 'Bcc: ' . $cc;
                }
            }
        }

        // Calculate some Variables for display
        $date = ($signup->date == '0000-00-00') ? __('N/A', 'pta_volunteer_sus') : mysql2date( get_option('date_format'), $signup->date, $translate = true );
        $start_time = ($task->time_start == "") ? __('N/A', 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($task->time_start));
        $end_time = ($task->time_end == "") ? __('N/A', 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($task->time_end));
        if (isset($signup->item) && $signup->item != " ") {
        	$item = $signup->item;
        } else {
        	$item = __('N/A', 'pta_volunteer_sus');
        }
        if (!empty($chair_emails)) {
        	$contact_emails = implode("\r\n", $chair_emails);
        } else {
        	$contact_emails = __('N/A', 'pta_volunteer_sus');
        }
        $sheet_details = $this->convert_to_plain_text($sheet->details);
        
        // Replace any template tags with the appropriate variables
	    $search = array('{sheet_title}','{sheet_details}','{task_title}','{date}','{start_time}',
		    '{end_time}','{item_details}','{item_qty}','{details_text}','{firstname}','{lastname}',
		    '{contact_emails}','{contact_names}','{site_name}','{site_url}','{phone}');

	    $replace = array($sheet->title, $sheet_details, $task->title, $date, $start_time, $end_time, $item, $signup->item_qty,
		    $task->details_text, $signup->firstname, $signup->lastname, $contact_emails, $chair_names, get_bloginfo('name'), get_bloginfo('url'), $signup->phone );
	    
	    // Allow extension to modify/add to search and replace arrays
	    $search = apply_filters('pta_sus_email_search', $search, $signup, $reminder, $clear);
	    $replace = apply_filters('pta_sus_email_replace', $replace, $signup, $reminder, $clear);

	    $message = str_replace($search, $replace, $message);
	    $subject = str_replace($search, $replace, $subject);

        if( $reminder && $this->main_options['detailed_reminder_admin_emails'] ) {
            $this->last_reminder = "To: " . $to . "\r\n\r\n" . $message . "\r\n\r\n\r\n";
        }

        do_action( 'pta_sus_before_send_email', $to, $subject, $message, $headers );

        // Allow other plugins to determine if we should send this email -- return false to not send
        $send_email = apply_filters( 'pta_sus_send_email_check', true, $signup, $task, $sheet, $reminder, $clear );

        if($send_email && !empty($subject) && !empty($message)) {
        	if($this->email_options['individual_emails'] && !empty($cc_emails)) {
        		// Send out first email to the original TO address, set errors to result (bool)
				$sent = wp_mail($to, $subject, $message, $headers);
		        // loop through all chair_emails and send individually
		        if(!empty($cc_emails)) {
			        foreach ($cc_emails as $to) {
			        	if(is_email($to)) {
					        $result = wp_mail($to, $subject, $message, $headers);
					        if(false === $result) {
						        $sent = false;
					        }
				        }
			        }
		        }
		        return $sent;
	        } else {
	        	// sending with CC/BCC in headers
		        return wp_mail($to, $subject, $message, $headers);
	        }

        } else {
            return true;
        }
    }

    public function get_member_directory_emails($group='') {
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
	
	public function get_member_directory_names($group='') {
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

    public function send_reminders() {
    	$limit = false;
    	$now = current_time( 'timestamp' );
    	// This function is used to check if we need to send out reminder emails or not
    	if(isset($this->email_options['reminder_email_limit']) && '' != $this->email_options['reminder_email_limit'] && 0 < $this->email_options['reminder_email_limit']) {
    		$limit = (int)$this->email_options['reminder_email_limit'];
    		if ( $last_batch = get_option( 'pta_sus_reminders_last_batch' ) ) {
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
        
        // Go through all sheets and check the dates, if they need reminders,
        // if we are within the reminder date and the reminder hasn't been sent, and then build an array of 
        // objects for which we need to send reminders -- Use our modified Get All function from DLS
        $events = $this->data->get_all_data();       
        $reminder_events = array();
        foreach ($events as $event) {
            if ($event->sheet_trash) continue; // skip trashed events
            if (empty($event->email)) continue; // skip if nobody signed up
            $event_time = strtotime($event->signup_date);
            if($event_time < $now) continue; // skip old events that haven't been deleted
            if ( $event->reminder1_days > 0 && !$event->reminder1_sent ) {
                $reminder1_time = $event->reminder1_days * 24 * 60 * 60;
                if (($event_time - $reminder1_time ) < $now) {
                    $event->reminder_num = 1;
                    $reminder_events[] = $event;
                }
            } elseif ( $event->reminder2_days > 0 && !$event->reminder2_sent ) {
                $reminder2_time = $event->reminder2_days * 24 * 60 * 60;
                if (($event_time - $reminder2_time ) < $now) {
                    $event->reminder_num = 2;
                    $reminder_events[] = $event;
                }
            } 
        }

        $reminder_count = 0;

        if (!empty($reminder_events)) {
            // Next, go through the each reminder event and prepare/send an email
            // Each event object returned by get_all_data is actually an individual signup,
            // so each one can be used to create a personalized email.  However, if there are
            // no signups for a given task on a sheet, an event object for that task will still
            // be created, so need to see if there is a valid email first before sending.
            $reminders_log = '';
            foreach ($reminder_events as $event) {
                if(!is_email( $event->email)) continue; // skip any invalid emails

                // Check if we have reached our hourly limit or not
                if ($limit) {
                	if ( $limit <= ($last_batch['num'] + $reminder_count) ) {
                		// limit reached, so break out of foreach loop
                		break;
                	}
                }
                
                $reminder = $event->reminder_num;

                if ($this->send_mail($event->signup_id, $reminder ) == TRUE) {
                    // Keep track of # of reminders sent
                    $reminder_count++; 
                    // Add reminder message to reminders_log
                    $reminders_log .= $this->last_reminder;
                    // Here we need to set the reminder_sent to true
                    $update = array();
                    if ( 1 === $event->reminder_num ) {
                        $update['signup_reminder1_sent'] = TRUE;
                    }
                    if ( 2 === $event->reminder_num ) {
                        $update['signup_reminder2_sent'] = TRUE;
                    }
                    $updated = $this->data->update_signup($update, $event->signup_id);

                }
            }

            if($limit) {
            	// increment our last batch num by number of reminders sent
            	$last_batch['num'] += $reminder_count;
            	update_option( 'pta_sus_reminders_last_batch', $last_batch );
            }
            
            if ( 0 < $reminder_count && $this->main_options['enable_cron_notifications'] ) {
                // Send site admin an email with number of reminders sent
                $to = get_bloginfo( 'admin_email' );
                $subject = __("Volunteer Signup Reminders sent", 'pta_volunteer_sus');
                $message = __("Volunteer signup sheet CRON job has been completed.", 'pta_volunteer_sus')."\r\n\r\n";
                $message .= sprintf( __("%d reminder emails were sent.", 'pta_volunteer_sus'), $reminder_count ) ."\r\n\r\n"; 
                // If enabled, add details of all reminders sent to the admin notification email
                if ($this->main_options['detailed_reminder_admin_emails']) {
                    $message .= "Messages Sent:\r\n\r\n";
                    $message .= $reminders_log;
                }               
                wp_mail($to, $subject, $message);
            }
        }

        // Set another option to save the last time any reminders were sent
        if (!$sent = get_option('pta_sus_last_reminders')) {
            $sent = array('time' => 0, 'num' => 0, 'last' => 0);
        }
        $sent['last'] = $now;
        if ( 0 < $reminder_count ) {
            $sent['time'] = $now;
            $sent['num'] = $reminder_count;          
        }  
        update_option( 'pta_sus_last_reminders', $sent );   
        return $reminder_count;
    } // Send Reminders

} // End of class
/* EOF */
