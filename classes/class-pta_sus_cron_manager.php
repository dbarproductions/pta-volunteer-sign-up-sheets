<?php
/**
 * CRON Manager Class
 * 
 * Handles all CRON job execution and housekeeping tasks for the Volunteer
 * Sign-Up Sheets plugin. This includes sending reminder emails, cleaning up
 * expired data, and managing log files.
 * 
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Cron_Manager {

	/**
	 * Run hourly CRON job
	 * 
	 * Executes all scheduled housekeeping tasks including:
	 * - Sending reminder emails
	 * - Sending reschedule emails
	 * - Cleaning up expired sheets
	 * - Cleaning up expired signups
	 * - Purging expired validation codes
	 * - Purging unvalidated signups
	 * - Clearing old log files
	 * 
	 * Fires the 'pta_sus_hourly_cron' action hook to allow extensions
	 * to add their own CRON tasks.
	 * 
	 * @since 6.0.0
	 * @return void
	 * @hook pta_sus_hourly_cron Action hook fired at start of CRON job
	 * @hook pta_sus_cron_message Filter to add custom messages to CRON notification
	 */
	public static function run_hourly_cron() {
		pta_logToFile(__('Beginning hourly CRON job', 'pta-volunteer-sign-up-sheets'));
		
		// Let other plugins hook into our hourly cron job
		do_action('pta_sus_hourly_cron');
		
		// Send reminder and reschedule emails
		PTA_SUS_Email_Functions::send_reminders();
		PTA_SUS_Email_Functions::send_reschedule_emails();
		
		// Get options
		$main_options = get_option('pta_volunteer_sus_main_options', array());
		$validation_options = get_option('pta_volunteer_sus_validation_options', array());
		
		$message = '';
		$send_mail = isset($main_options['enable_cron_notifications']) ? $main_options['enable_cron_notifications'] : true;
		
		// Cleanup expired sheets if enabled
		if (!empty($main_options['clear_expired_sheets'])) {
			$results = PTA_SUS_Sheet_Functions::delete_expired_sheets();
			if ($results) {
				$message .= __("Volunteer signup sheet CRON job has been completed.", 'pta-volunteer-sign-up-sheets')."\n\n" .
				           sprintf(__("%d expired sheets were deleted.", 'pta-volunteer-sign-up-sheets'), (int)$results) . "\n\n";
			}
		}
		
		// Cleanup expired signups if enabled
		if (!empty($main_options['clear_expired_signups'])) {
			$results = PTA_SUS_Signup_Functions::delete_expired_signups();
			if ($results) {
				$message .= __("Volunteer signup sheet CRON job has been completed.", 'pta-volunteer-sign-up-sheets')."\n\n" .
				           sprintf(__("%d expired signups were deleted.", 'pta-volunteer-sign-up-sheets'), (int)$results) . "\n\n";
			}
		}
		
		// Cleanup validation codes and unvalidated signups if validation is enabled
		if (isset($validation_options['enable_validation']) && $validation_options['enable_validation']) {
			// Purge expired validation codes
			$results = pta_delete_expired_validation_codes();
			if ($results) {
				$message .= $results."\n\n";
			}
			// Purge unvalidated signups
			$results = pta_delete_unvalidated_signups();
			if ($results) {
				$message .= $results."\n\n";
			}
		}
		
		// Allow extensions to add custom messages
		$message .= apply_filters('pta_sus_cron_message', '');
		
		// Log and send notification if there's a message
		if (!empty($message)) {
			pta_logToFile($message);
			if ($send_mail) {
				self::send_cron_notification($message);
			}
		}
		
		pta_logToFile(__('Finished hourly CRON job', 'pta-volunteer-sign-up-sheets'));
		
		// Clear old log files (runs every 30 days)
		self::clear_old_log_files();
	}
	
	/**
	 * Send CRON notification email to admin
	 * 
	 * Sends an email notification to the site administrator with the
	 * results of the CRON job execution.
	 * 
	 * @since 6.0.0
	 * @param string $message Message body to send
	 * @return void
	 */
	private static function send_cron_notification($message) {
		$to = get_bloginfo('admin_email');
		$subject = __("Volunteer Signup Housekeeping Completed!", 'pta-volunteer-sign-up-sheets');
		wp_mail($to, $subject, $message);
	}
	
	/**
	 * Clear old log files
	 * 
	 * Removes log files older than 30 days. This runs as part of the
	 * hourly CRON job but only executes if 30 days have passed since
	 * the last cleanup.
	 * 
	 * @since 6.0.0
	 * @return void
	 */
	private static function clear_old_log_files() {
		$last_log_clear = get_option('pta_sus_last_log_clear', 0);
		$clear_interval = 30 * DAY_IN_SECONDS; // 30 days
		
		if (time() - $last_log_clear >= $clear_interval) {
			$upload_dir = wp_upload_dir();
			$log_dir = $upload_dir['basedir'] . '/pta-logs';
			
			if (is_dir($log_dir)) {
				$log_files = glob($log_dir . '/*.log');
				foreach ($log_files as $log_file) {
					$filename = basename($log_file);
					pta_clear_log_file($filename);
				}
			}
			
			update_option('pta_sus_last_log_clear', time());
		}
	}
}

