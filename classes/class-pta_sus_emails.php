<?php
/**
* Email Functions
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Emails {

	public $email_options;
	public $main_options;
	public $validation_options;
    public $last_reminder;

	public function __construct() {
		$this->email_options = get_option( 'pta_volunteer_sus_email_options' );
		$this->main_options = get_option( 'pta_volunteer_sus_main_options' );
		$this->validation_options = get_option( 'pta_volunteer_sus_validation_options' );

	} // Construct

	/**
    * Send signs up & reminder emails
    * 
    * @deprecated 6.0.0 Use PTA_SUS_Email_Functions::send_mail() instead
    * @param    int  $signup_id the signup id
    * @param    bool $reminder is this a reminder email?
	 * @param    bool $clear is this a clear email?
	 * @param    bool $reschedule is this a reschedule email?
	 * @param    string $action the action being performed, if not one of the boolean values
    * @return   bool|string true if success or email does not need to be sent. False on sending failure. String if detailed reminder info needed.
    */
    public function send_mail($signup_id, $reminder=false, $clear=false, $reschedule=false, $action='') {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Email_Functions::send_mail() ' . sprintf('Called from %s line %s', $file, $line)
		);
		
		// Call static helper method
		$result = PTA_SUS_Email_Functions::send_mail($signup_id, $reminder, $clear, $reschedule, $action);
		
		// Store last_reminder if result is a string (detailed reminder info)
		if (is_string($result)) {
			$this->last_reminder = $result;
			return true; // Return true for backward compatibility
		}
		
		return $result;
    }

	/**
	 * Send user validation email
	 * 
	 * @deprecated 6.0.0 Use PTA_SUS_Email_Functions::send_user_validation_email() instead
	 * @param string $firstname First name
	 * @param string $lastname Last name
	 * @param string $email Email address
	 * @return bool True on success, false on failure
	 */
	public function send_user_validation_email($firstname, $lastname, $email) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Email_Functions::send_user_validation_email() ' . sprintf('Called from %s line %s', $file, $line)
		);
		
		return PTA_SUS_Email_Functions::send_user_validation_email($firstname, $lastname, $email);
	}

    /**
     * Send reminder emails for signups
     * 
     * @deprecated 6.0.0 Use PTA_SUS_Email_Functions::send_reminders() instead
     * @return int Number of reminder emails sent
     */
    public function send_reminders() {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Email_Functions::send_reminders() ' . sprintf('Called from %s line %s', $file, $line)
		);
		
		return PTA_SUS_Email_Functions::send_reminders();
    }

    /**
     * Send reschedule emails from queue
     * 
     * @deprecated 6.0.0 Use PTA_SUS_Email_Functions::send_reschedule_emails() instead
     * @return int|false Number of emails sent, or false if queue is empty
     */
    public function send_reschedule_emails() {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Email_Functions::send_reschedule_emails() ' . sprintf('Called from %s line %s', $file, $line)
		);
		
		return PTA_SUS_Email_Functions::send_reschedule_emails();
    } // Send Reschedule Emails

    /**
     * Queue signups for reschedule emails
     * Stores signup data in the reschedule queue so emails can be sent later
     * 
     * @deprecated 6.0.0 Use PTA_SUS_Email_Functions::queue_reschedule_emails() instead
     * @param array $tasks Array of PTA_SUS_Task objects to queue signups for
     * @return void
     */
    public static function queue_reschedule_emails($tasks) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Email_Functions::queue_reschedule_emails() ' . sprintf('Called from %s line %s', $file, $line)
		);
		
		PTA_SUS_Email_Functions::queue_reschedule_emails($tasks);
    }

} // End of class
/* EOF */
