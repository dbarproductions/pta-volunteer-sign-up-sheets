<?php
/**
 * Options Manager Class
 * 
 * Handles initialization and management of all plugin options including
 * main options, email options, integration options, and validation options.
 * Ensures all options are properly initialized with defaults and updated
 * when new options are added during plugin upgrades.
 * 
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Options_Manager {

	/**
	 * Options version
	 * 
	 * This version should be incremented whenever new options are added
	 * to ensure existing installations get the new defaults during upgrades.
	 * 
	 * @var string
	 */
	private static $options_version = '6.0.0';

	/**
	 * Get options version
	 * 
	 * @since 6.0.0
	 * @return string Options version
	 */
	public static function get_options_version() {
		return self::$options_version;
	}

	/**
	 * Check if options need upgrading
	 * 
	 * Compares the stored options version with the current options version.
	 * Returns true if options need to be upgraded (new options added).
	 * 
	 * @since 6.0.0
	 * @return bool True if upgrade needed, false otherwise
	 */
	public static function needs_options_upgrade() {
		$current = get_option( 'pta_sus_options_version', '0.0.0' );
		return version_compare( $current, self::$options_version, '<' );
	}

	/**
	 * Initialize all plugin options
	 * 
	 * Ensures all option groups are properly initialized with defaults.
	 * This method should be called during plugin activation or when options
	 * need to be upgraded (new options added during plugin upgrades).
	 * 
	 * @since 6.0.0
	 * @param bool $save_version Whether to save the options version after initialization
	 * @return void
	 * @hook pta_sus_options_init_all Action fired after all options are initialized
	 */
	public static function init_all_options( $save_version = true ) {
		self::init_main_options();
		self::init_email_options();
		self::init_integration_options();
		self::init_validation_options();
		
		if ( $save_version ) {
			update_option( 'pta_sus_options_version', self::$options_version );
		}
		
		do_action( 'pta_sus_options_init_all' );
	}

	/**
	 * Initialize main plugin options
	 * 
	 * Sets up default values for main plugin options and ensures all
	 * options exist in the database, adding any missing options from defaults.
	 * 
	 * @since 6.0.0
	 * @return void
	 * @hook pta_sus_main_options_defaults Filter to modify main options defaults
	 */
	public static function init_main_options() {
		$defaults = self::get_main_options_defaults();
		self::ensure_options_exist( 'pta_volunteer_sus_main_options', $defaults );
	}

	/**
	 * Get default values for main options
	 * 
	 * Returns an array of default values for all main plugin options.
	 * These defaults are used when initializing options for the first time
	 * or when new options are added during plugin upgrades.
	 * 
	 * @since 6.0.0
	 * @return array Array of default option values
	 * @hook pta_sus_main_options_defaults Filter to modify main options defaults
	 */
	public static function get_main_options_defaults() {
		$defaults = array(
			'enable_test_mode'                     => false,
			'test_mode_message'                    => 'The Volunteer Sign-Up System is currently undergoing maintenance. Please check back later.',
			'volunteer_page_id'                    => 0,
			'hide_volunteer_names'                 => false,
			'show_remaining'                       => false,
			'hide_details_qty'                     => false,
			'hide_signups_details_qty'             => false,
			'show_ongoing_in_widget'               => true,
			'show_ongoing_last'                    => true,
			'no_phone'                             => false,
			'hide_contact_info'                    => false,
			'login_required'                       => false,
			'login_required_signup'                => false,
			'login_required_message'               => 'You must be logged in to a valid account to view and sign up for volunteer opportunities.',
			'login_signup_message'                 => 'Login to Signup',
			'readonly_signup'                      => false,
			'show_login_link'                      => false,
			'disable_signup_login_notice'          => false,
			'enable_cron_notifications'            => true,
			'detailed_reminder_admin_emails'       => true,
			'show_expired_tasks'                   => false,
			'clear_expired_signups'                => false,
			'clear_expired_sheets'                 => false,
			'num_days_expired'                     => 1,
			'hide_donation_button'                 => false,
			'reset_options'                        => false,
			'enable_signup_search'                 => false,
			'signup_search_tables'                 => 'signups',
			'signup_redirect'                      => true,
			'phone_required'                       => true,
			'use_divs'                             => false,
			'disable_css'                          => false,
			'enable_mobile_css'                    => false,
			'show_full_name'                       => false,
			'suppress_duplicates'                  => true,
			'no_global_overlap'                    => false,
			'admin_only_settings'                  => false,
			'disable_datei18n'                     => false,
			'disable_grouping'                     => false,
			'show_all_slots_for_all_data'          => false,
			'skip_signups_check'                   => false,
			'show_task_description_on_signup_form' => false,
			'hide_single_date_header'              => false,
		);
		
		return apply_filters( 'pta_sus_main_options_defaults', $defaults );
	}

	/**
	 * Initialize email options
	 * 
	 * Sets up default values for email options including email templates
	 * and ensures all options exist in the database.
	 * 
	 * @since 6.0.0
	 * @return void
	 * @hook pta_sus_email_options_defaults Filter to modify email options defaults
	 */
	public static function init_email_options() {
		$defaults = self::get_email_options_defaults();
		self::ensure_options_exist( 'pta_volunteer_sus_email_options', $defaults );
	}

	/**
	 * Get default values for email options
	 * 
	 * Returns an array of default values for all email options including
	 * email templates for confirmation, reminder, reschedule, and clear emails.
	 * 
	 * @since 6.0.0
	 * @return array Array of default email option values
	 * @hook pta_sus_email_options_defaults Filter to modify email options defaults
	 */
	public static function get_email_options_defaults() {
		$confirm_template = self::get_confirmation_email_template();
		$remind_template = self::get_reminder_email_template();
		$reschedule_template = self::get_reschedule_email_template();
		$clear_template = self::get_clear_email_template();
		
		$defaults = array(
			'cc_email'                    => '',
			'from_email'                  => get_bloginfo( 'admin_email' ),
			'replyto_email'               => get_bloginfo( 'admin_email' ),
			'confirmation_email_subject'  => 'Thank you for volunteering!',
			'confirmation_email_template' => $confirm_template,
			'clear_email_subject'         => 'Volunteer spot cleared!',
			'clear_email_template'        => $clear_template,
			'reminder_email_subject'      => 'Volunteer Reminder',
			'reminder_email_template'     => $remind_template,
			'reminder2_email_subject'     => '',
			'reminder2_email_template'    => '',
			'reminder_email_limit'        => "",
			'reschedule_email_subject'    => 'Event Rescheduled',
			'reschedule_email_template'   => $reschedule_template,
			'individual_emails'           => false,
			'admin_clear_emails'          => false,
			'no_chair_emails'             => false,
			'no_confirmation_emails'      => false,
			'no_reminder_emails'          => false,
			'disable_emails'              => false,
			'replyto_chairs'              => false,
			'use_html'                    => false
		);
		
		return apply_filters( 'pta_sus_email_options_defaults', $defaults );
	}

	/**
	 * Get confirmation email template
	 * 
	 * Returns the default template for signup confirmation emails.
	 * 
	 * @since 6.0.0
	 * @return string Email template with placeholders
	 */
	private static function get_confirmation_email_template() {
		return "Dear {firstname} {lastname},

This is to confirm that you volunteered for the following:

Event: {sheet_title} 
Task/Item: {task_title}
Date: {date}
Start Time: {start_time}
End Time: {end_time}
{details_text}: {item_details}
Item Quantity: {item_qty}

If you have any questions, please contact:
{contact_emails}

Thank You!
{site_name}
{site_url}
";
	}

	/**
	 * Get reminder email template
	 * 
	 * Returns the default template for reminder emails.
	 * 
	 * @since 6.0.0
	 * @return string Email template with placeholders
	 */
	private static function get_reminder_email_template() {
		return "Dear {firstname} {lastname},

This is to remind you that you volunteered for the following:

Event: {sheet_title} 
Task/Item: {task_title}
Date: {date}
Start Time: {start_time}
End Time: {end_time}
{details_text}: {item_details}
Item Quantity: {item_qty}

If you have any questions, please contact:
{contact_emails}

Thank You!
{site_name}
{site_url}
";
	}

	/**
	 * Get reschedule email template
	 * 
	 * Returns the default template for reschedule notification emails.
	 * 
	 * @since 6.0.0
	 * @return string Email template with placeholders
	 */
	private static function get_reschedule_email_template() {
		return "Dear {firstname} {lastname},

An event you volunteered for has been rescheduled. New details are as follow:

Event: {sheet_title} 
Task/Item: {task_title}
Date: {date}
Start Time: {start_time}
End Time: {end_time}
{details_text}: {item_details}
Item Quantity: {item_qty}

If you have any questions, please contact:
{contact_emails}

Thank You!
{site_name}
{site_url}
";
	}

	/**
	 * Get clear email template
	 * 
	 * Returns the default template for signup clear confirmation emails.
	 * 
	 * @since 6.0.0
	 * @return string Email template with placeholders
	 */
	private static function get_clear_email_template() {
		return "Dear {firstname} {lastname},

This is to confirm that you have cleared yourself from the following volunteer signup:

Event: {sheet_title} 
Task/Item: {task_title}
Date: {date}
Start Time: {start_time}
End Time: {end_time}
{details_text}: {item_details}
Item Quantity: {item_qty}

If this was a mistake, please visit the site and sign up again.

If you have any questions, please contact:
{contact_emails}

Thank You!
{site_name}
{site_url}
";
	}

	/**
	 * Initialize integration options
	 * 
	 * Sets up default values for integration options (e.g., member directory)
	 * and ensures all options exist in the database.
	 * 
	 * @since 6.0.0
	 * @return void
	 * @hook pta_sus_integration_options_defaults Filter to modify integration options defaults
	 */
	public static function init_integration_options() {
		$defaults = self::get_integration_options_defaults();
		self::ensure_options_exist( 'pta_volunteer_sus_integration_options', $defaults );
	}

	/**
	 * Get default values for integration options
	 * 
	 * Returns an array of default values for integration-related options.
	 * 
	 * @since 6.0.0
	 * @return array Array of default integration option values
	 * @hook pta_sus_integration_options_defaults Filter to modify integration options defaults
	 */
	public static function get_integration_options_defaults() {
		$defaults = array(
			'enable_member_directory' => false,
			'directory_page_id' => 0,
			'contact_page_id' => 0,
		);
		
		return apply_filters( 'pta_sus_integration_options_defaults', $defaults );
	}

	/**
	 * Initialize validation options
	 * 
	 * Sets up default values for validation options including validation
	 * email templates and ensures all options exist in the database.
	 * 
	 * @since 6.0.0
	 * @return void
	 * @hook pta_sus_validation_options_defaults Filter to modify validation options defaults
	 */
	public static function init_validation_options() {
		$defaults = self::get_validation_options_defaults();
		self::ensure_options_exist( 'pta_volunteer_sus_validation_options', $defaults );
	}

	/**
	 * Get default values for validation options
	 * 
	 * Returns an array of default values for validation-related options
	 * including email templates for signup and user validation.
	 * 
	 * @since 6.0.0
	 * @return array Array of default validation option values
	 * @hook pta_sus_validation_options_defaults Filter to modify validation options defaults
	 */
	public static function get_validation_options_defaults() {
		$signup_validation_template = self::get_signup_validation_email_template();
		$user_validation_template = self::get_user_validation_email_template();
		
		$defaults = array(
			'enable_validation' => false,
			'require_validation_to_view' => false,
			'require_validation_to_signup' => false,
			'enable_signup_validation' => true,
			'signup_expiration_hours' => 1,
			'signup_validation_email_subject' => 'Your Sign Up Validation Link',
			'signup_validation_email_template' => $signup_validation_template,
			'validation_code_expiration_hours' => 48,
			'user_validation_email_subject' => 'Your Validation Link',
			'user_validation_email_template' => $user_validation_template,
			'validation_form_header' => 'To view and manage your signups you must either login or fill out the form below to receive a validation link via email.',
			'enable_user_validation_form' => true,
			'validation_form_resubmission_minutes' => 1,
			'validation_required_message' => 'You must be validated to view this page.',
			'validation_page_link_text' => 'Go to the validation form',
			'validation_page_id' => 0,
			'enable_clear_validation' => true,
			'clear_validation_message' => 'Use the link below to clear the validation info from your browser. You should do this on public computers, or if you need to validate again as a spouse or family member using a different name or email.',
			'clear_validation_link_text' => 'Clear Validation',
			'disable_cc_validation_signup_emails' => true,
		);
		
		return apply_filters( 'pta_sus_validation_options_defaults', $defaults );
	}

	/**
	 * Get signup validation email template
	 * 
	 * Returns the default template for signup validation emails.
	 * 
	 * @since 6.0.0
	 * @return string Email template with placeholders
	 */
	private static function get_signup_validation_email_template() {
		return "
Please click on, or copy and paste, the link below to validate your signup:
{validation_link}
		";
	}

	/**
	 * Get user validation email template
	 * 
	 * Returns the default template for user validation emails.
	 * 
	 * @since 6.0.0
	 * @return string Email template with placeholders
	 */
	private static function get_user_validation_email_template() {
		return "
Please click on, or copy and paste, the link below to validate yourself:
{validation_link}
		";
	}

	/**
	 * Ensure options exist with defaults
	 * 
	 * Checks if an option exists in the database and ensures all default
	 * values are present. This is useful when new options are added during
	 * plugin upgrades - existing options are preserved, but new options
	 * are added with their default values.
	 * 
	 * @since 6.0.0
	 * @param string $option_name WordPress option name
	 * @param array $defaults Array of default option values
	 * @return void
	 */
	public static function ensure_options_exist( $option_name, $defaults ) {
		$options = get_option( $option_name, $defaults );
		
		// Make sure each option is set -- this helps if new options have been added during plugin upgrades
		$needs_update = false;
		foreach ( $defaults as $key => $value ) {
			if ( ! isset( $options[ $key ] ) ) {
				$options[ $key ] = $value;
				$needs_update = true;
			}
		}
		
		if ( $needs_update ) {
			update_option( $option_name, $options );
		}
	}

} // End of Class
/* EOF */

