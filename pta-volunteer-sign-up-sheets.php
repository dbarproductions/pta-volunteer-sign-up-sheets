<?php
/*
Plugin Name: Volunteer Sign Up Sheets
Plugin URI: http://wordpress.org/plugins/pta-volunteer-sign-up-sheets
Description: Volunteer Sign Up Sheets and Management from Stephen Sherrard Plugins
Version: 5.1.0
Author: Stephen Sherrard
Author URI: https://stephensherrardplugins.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: pta-volunteer-sign-up-sheets
Domain Path: /languages
Requires PHP: 7.4
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Save version # in database for future upgrades
if (!defined('PTA_VOLUNTEER_SUS_VERSION_KEY'))
    define('PTA_VOLUNTEER_SUS_VERSION_KEY', 'pta_volunteer_sus_version');

if (!defined('PTA_VOLUNTEER_SUS_VERSION_NUM'))
    define('PTA_VOLUNTEER_SUS_VERSION_NUM', '5.1.0');

if (!defined('PTA_VOLUNTEER_SUS_DIR'))
	define('PTA_VOLUNTEER_SUS_DIR', plugin_dir_path( __FILE__ ) );

if (!defined('PTA_VOLUNTEER_SUS_URL'))
	define('PTA_VOLUNTEER_SUS_URL', plugin_dir_url( __FILE__ ) );

add_option(PTA_VOLUNTEER_SUS_VERSION_KEY, PTA_VOLUNTEER_SUS_VERSION_NUM);

if (!class_exists('PTA_SUS_Data')) require_once 'classes/data.php';
if (!class_exists('PTA_SUS_List_Table')) require_once 'classes/list-table.php';
if (!class_exists('PTA_SUS_Widget')) require_once 'classes/widget.php';
if (!class_exists('PTA_SUS_Emails')) require_once 'classes/class-pta_sus_emails.php';

if(!class_exists('PTA_Sign_Up_Sheet')):

class PTA_Sign_Up_Sheet {
	
    public $data;
    public $public = null;
    public $emails;
    public $db_version = '5.1.0';
    public $main_options;
	public $validation_options;
    public $admin = null;
    
    public function __construct() {
	    $this->data = new PTA_SUS_Data();
        $this->emails = new PTA_SUS_Emails();

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook( __FILE__, array($this, 'deactivate'));

        $this->main_options = get_option( 'pta_volunteer_sus_main_options' );
	    $this->validation_options = get_option( 'pta_volunteer_sus_validation_options' );
    }

    public function init_hooks() {
	    add_action('pta_sus_cron_job', array($this, 'cron_functions'));

	    add_action('plugins_loaded', array($this, 'init'));
	    add_action('plugins_loaded', array($this, 'public_init' ));

	    add_action( 'init', array($this, 'block_assets' ));

	    add_action( 'widgets_init', array($this, 'register_sus_widget') );

	    add_action( 'wpmu_new_blog', array($this, 'new_blog'), 10, 6);

	    add_action('wp_enqueue_scripts', array($this,'register_scripts'), 1);
	    add_action('admin_enqueue_scripts', array($this, 'register_scripts'), 1);

	    if(is_admin()) {
		    if (!class_exists('PTA_SUS_Admin')) {
			    include_once(dirname(__FILE__).'/classes/class-pta_sus_admin.php');
			    $this->admin = new PTA_SUS_Admin();
				add_action('init', array($this->admin, 'init_admin_hooks'));
		    }
	    }
	    if (!class_exists('PTA_SUS_Public')) {
		    include_once(dirname(__FILE__).'/classes/class-pta_sus_public.php');
	    }

    }

    public function register_scripts() {
	    // register some scripts, so they can be used elsewhere
	    wp_register_style( 'pta-datatables-style', plugins_url( 'datatables/datatables.min.css', __FILE__ ) );
	    wp_register_script('pta-datatables', plugins_url( 'datatables/datatables.min.js' , __FILE__ ), array( 'jquery' ),'1.10.23',true);
	    wp_register_script( 'jquery-plugin', plugins_url( 'assets/js/jquery.plugin.min.js' , __FILE__ ), array( 'jquery' ) );
	    wp_register_script( 'pta-jquery-datepick', plugins_url( 'assets/js/jquery.datepick.min.js' , __FILE__ ), array( 'jquery','jquery-plugin' ), '5.1.0' );
	    wp_register_style( 'pta-jquery-datepick', plugins_url( 'assets/css/jquery.datepick.css', __FILE__ ) );
		// Select 2 - Used by several extensions
	    wp_register_style('pta-select2', 'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css', array(), '4.0.13');
	    wp_register_script('pta-select2', 'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js', array('jquery'), '4.0.13');
    }

	/**
	 * Get a sheet by id
	 *
	 * @param     int     sheet_id to retrieve
	 * @return    object    the sheet
	 */
	public function get_sheet($id = false) {
		return $this->data->get_sheet($id);
	}

	/**
	 * Get all Sheets
	 *
	 * @param     bool     get just trash
	 * @param     bool     get only active sheets or those without a set date
	 * @param     bool     get hidden sheets or not
	 * @return    mixed    array of sheets
	 */
	public function get_sheets($trash=false, $active_only=false, $show_hidden=false,$order_by='first_date', $order = 'ASC') {
		return $this->data->get_sheets($trash, $active_only, $show_hidden,$order_by,$order);
	}

	/**
	 * Get tasks by sheet
	 *
	 * @param     int        id of sheet
	 * @return    mixed    array of tasks
	 */
	public function get_tasks($sheet_id, $date = '') {
		return $this->data->get_tasks($sheet_id, $date);
	}
	
	/**
	 * Get task by id
	 *
	 * @param     int        id of task
	 * @return    object    task
	 */
	public function get_task($task_id) {
		return $this->data->get_task($task_id);
	}
	
	/**
	 * Get signup by id
	 *
	 * @param     int        id of signup
	 * @return    object    signup
	 */
	public function get_signup($signup_id) {
		return $this->data->get_signup($signup_id);
	}
	
	/**
	 * Get signup by id
	 *
	 * @param     int        id of signup
	 * @return    object    signup with more details from joins
	 */
	public function get_detailed_signup($signup_id) {
		return $this->data->get_detailed_signup($signup_id);
	}

	/**
	 * Get signups by task & date
	 *
	 * @param    int        id of task
	 * @return    mixed    array of siginups
	 */
	public function get_signups($task_id, $date='')	{
		return $this->data->get_signups($task_id, $date);
	}
	
	/**
	 * Get html output of sheets
	 *
	 * @param $sheets array sheet objects
	 *
	 * @return string html output of sheets
	 */
	public function get_sheets_list($sheets) {
		if(!is_object($this->public)) return '';
		return $this->public->get_sheets_list($sheets);
	}
	
	/**
	 * Get html output of a single sheet
	 *
	 * @param int $id the ID of sheet to display
	 *
	 * @return string html output of sheet and all tasks
	 */
	public function get_single_sheet($id) {
		if(!is_object($this->public)) return '';
		return $this->public->get_single_sheet($id);
	}
	
	/**
	 * Get html table output of all tasks/items user has signed up for
	 *
	 * @return string table list of user signups
	 */
	public function get_user_signups_list() {
		if(!is_object($this->public)) return '';
		return $this->public->get_user_signups_list();
	}
	
	/**
	 * Get html signup form for a specific task and date
	 *
	 * @return string html signup form
	 */
	public function get_signup_form($task_id, $date, $skip_filled_check = false) {
		if(!is_object($this->public)) {
			return '';
		}
		return $this->public->display_signup_form($task_id, $date, $skip_filled_check);
	}

    public function register_sus_widget() {
        register_widget( 'PTA_SUS_Widget' );
    }


    public function cron_functions() {
		pta_logToFile(__('Beginning hourly CRON job', 'pta-volunteer-sign-up-sheets'));
        // Let other plugins hook into our hourly cron job
        do_action( 'pta_sus_hourly_cron' );

        // Run our reminders email check
        $this->emails->send_reminders();
        $this->emails->send_reschedule_emails();

	    $to = get_bloginfo( 'admin_email' );
	    $subject = __("Volunteer Signup Housekeeping Completed!", 'pta-volunteer-sign-up-sheets');
		$message = '';
		$send_mail = $this->main_options['enable_cron_notifications'];

	    // If automatic clearing of expired signups is enabled, run the check
	    if($this->main_options['clear_expired_sheets']) {
		    $results = $this->data->delete_expired_sheets();
		    if($results) {
			    $message .= __("Volunteer signup sheet CRON job has been completed.", 'pta-volunteer-sign-up-sheets')."\n\n" .
			               sprintf(__("%d expired sheets were deleted.", 'pta-volunteer-sign-up-sheets'), (int)$results) . "\n\n";
		    }
	    }

		// If automatic clearing of expired signups is enabled, run the check
        if($this->main_options['clear_expired_signups']) {
            $results = $this->data->delete_expired_signups();
            if($results) {
                $message .= __("Volunteer signup sheet CRON job has been completed.", 'pta-volunteer-sign-up-sheets')."\n\n" .
                            sprintf(__("%d expired signups were deleted.", 'pta-volunteer-sign-up-sheets'), (int)$results) . "\n\n";
            }
        }

		if(isset($this->validation_options['enable_validation']) && $this->validation_options['enable_validation']) {
			// purge expired validation codes and unvalidated signups
			$results = pta_delete_expired_validation_codes();
			if($results) {
				$message .= $results."\n\n";
			}
			// purge unvalidated signups
			$results = pta_delete_unvalidated_signups();
			if($results) {
				$message .= $results."\n\n";
			}
		}

		$message .= apply_filters('pta_sus_cron_message', '');

		if(!empty($message)) {
			pta_logToFile($message);
			if($send_mail) {
				wp_mail($to, $subject, $message);
			}
		}
	    pta_logToFile(__('Finished hourly CRON job', 'pta-volunteer-sign-up-sheets'));

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

    public function public_init() {
	    if (strpos($_SERVER['REQUEST_URI'], 'favicon.ico') !== false) {
		    return;
	    }
    	if(!is_admin() || wp_doing_ajax()) {
		    if($this->public === null) {
			    $this->public = new PTA_SUS_Public();
		    }
	    }
    }

    public function init() {
        load_plugin_textdomain( 'pta-volunteer-sign-up-sheets', false, dirname(plugin_basename( __FILE__ )) . '/languages/' );
        // Check our database version and run the activate function if needed
        $current = get_option( "pta_sus_db_version" );
        if ($current < $this->db_version) {
            $this->pta_sus_activate();
        }

        // If options haven't previously been setup, create the default options
        // MAIN OPTIONS
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
        $options = get_option( 'pta_volunteer_sus_main_options', $defaults );
        // Make sure each option is set -- this helps if new options have been added during plugin upgrades
        foreach ($defaults as $key => $value) {
            if(!isset($options[$key])) {
                $options[$key] = $value;
            }
        }
        update_option( 'pta_volunteer_sus_main_options', $options );

        // EMAIL OPTIONS
$confirm_template = 
"Dear {firstname} {lastname},

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
$remind_template = 
"Dear {firstname} {lastname},

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
$reschedule_template =
"Dear {firstname} {lastname},

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
$clear_template = 
"Dear {firstname} {lastname},

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
	    $defaults = array(
		    'cc_email'                    => '',
		    'from_email'                  => get_bloginfo( $show = 'admin_email' ),
		    'replyto_email'               => get_bloginfo( $show = 'admin_email' ),
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
        $options = get_option( 'pta_volunteer_sus_email_options', $defaults );
        // Make sure each option is set -- this helps if new options have been added during plugin upgrades
        foreach ($defaults as $key => $value) {
            if(!isset($options[$key])) {
                $options[$key] = $value;
            }
        }
        update_option( 'pta_volunteer_sus_email_options', $options );
        
        // INTEGRATION OPTIONS
        $defaults = array(
                    'enable_member_directory' => false,
                    'directory_page_id' =>0,
                    'contact_page_id' => 0,
                    );
        $options = get_option( 'pta_volunteer_sus_integration_options', $defaults );
        // Make sure each option is set -- this helps if new options have been added during plugin upgrades
        foreach ($defaults as $key => $value) {
            if(!isset($options[$key])) {
                $options[$key] = $value;
            }
        }
        update_option( 'pta_volunteer_sus_integration_options', $options );

		// VALIDATION OPTIONS
	    $signup_validation_template = "
Please click on, or copy and paste, the link below to validate your signup:
{validation_link}
	    ";
	    $user_validation_template = "
Please click on, or copy and paste, the link below to validate yourself:
{validation_link}
	    ";
	    $defaults = array(
		    'enable_validation' => false,
			'require_validation_to_view' => false,
		    'require_validation_to_signup' => false,
		    'enable_signup_validation' => true,
		    'signup_expiration_hours' => 1,
		    'signup_validation_email_subject' =>'Your Sign Up Validation Link',
		    'signup_validation_email_template' => $signup_validation_template,
		    'validation_code_expiration_hours' => 48,
		    'user_validation_email_subject' =>'Your Validation Link',
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
	    $options = get_option( 'pta_volunteer_sus_validation_options', $defaults );
	    // Make sure each option is set -- this helps if new options have been added during plugin upgrades
	    foreach ($defaults as $key => $value) {
		    if(!isset($options[$key])) {
			    $options[$key] = $value;
		    }
	    }
	    update_option( 'pta_volunteer_sus_validation_options', $options );
    }

      
 
    /*
    *   Run activation procedure to set up tables and options when a new blog is added
     */
    public function new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta ) {
        global $wpdb;
     
        if (is_plugin_active_for_network('pta-volunteer-sign-up-sheets/pta-volunteer-sign-up-sheets.php')) {
            switch_to_blog($blog_id);
            $this->pta_sus_activate();
            restore_current_blog();
        }
    }
    
    /**
    * Activate the plugin
    */
    public function activate($networkwide) {
        global $wpdb;
                     
        if (function_exists('is_multisite') && is_multisite()) {
            // check if it is a network activation - if so, run the activation function for each blog id
            if ($networkwide) {
                // Get all blog ids
                $blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
                foreach ($blogids as $blog_id) {
                    switch_to_blog($blog_id);
                    $this->pta_sus_activate();
					restore_current_blog();
                }
                return;
            }  
        }
        $this->pta_sus_activate();     
    }

    public function pta_sus_activate() {
        if ( ! current_user_can( 'activate_plugins' ) )
            return;
       
        // Create new data object here so it works for multi-site activation
        $this->data = new PTA_SUS_Data();

        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Database Tables
        // **********************************************************
        $sql = "CREATE TABLE {$this->data->tables['sheet']['name']} (
            id INT NOT NULL AUTO_INCREMENT,
            title VARCHAR(200) NOT NULL,
            first_date DATE,
            last_date DATE,
            details LONGTEXT,
            type VARCHAR(200) NOT NULL,
            position VARCHAR(200),
            chair_name VARCHAR(100),
            chair_email VARCHAR(100),
            sus_group VARCHAR(500) DEFAULT 'none',
            reminder1_days INT,
            reminder2_days INT,
            clear BOOL NOT NULL DEFAULT TRUE,
            clear_days INT DEFAULT 0,
            no_signups BOOL NOT NULL DEFAULT FALSE,
            duplicate_times BOOL NOT NULL DEFAULT FALSE,
            visible BOOL NOT NULL DEFAULT TRUE,
            trash BOOL NOT NULL DEFAULT FALSE,
            clear_emails VARCHAR(50) NOT NULL DEFAULT 'default',
            signup_emails VARCHAR(50) NOT NULL DEFAULT 'default',
            PRIMARY KEY id (id),
            KEY `first_date` (`first_date`),
            KEY `last_date` (`last_date`)
        ) $charset_collate;";
        $sql .= "CREATE TABLE {$this->data->tables['task']['name']} (
            id INT NOT NULL AUTO_INCREMENT,
            sheet_id INT NOT NULL,
            dates VARCHAR(8000) NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            time_start VARCHAR(50),
            time_end VARCHAR(50),
            qty INT NOT NULL DEFAULT 1,
            need_details VARCHAR(3) NOT NULL DEFAULT 'NO',
            details_text VARCHAR(200) NOT NULL DEFAULT 'Item you are bringing',
            details_required VARCHAR(3) NOT NULL DEFAULT 'YES',
            allow_duplicates VARCHAR(3) NOT NULL DEFAULT 'NO',
            enable_quantities VARCHAR(3) NOT NULL DEFAULT 'NO',
            position INT NOT NULL,
            PRIMARY KEY id (id),
            KEY `sheet_id` (`sheet_id`)
        ) $charset_collate;";
        $sql .= "CREATE TABLE {$this->data->tables['signup']['name']} (
            id INT NOT NULL AUTO_INCREMENT,
            task_id INT NOT NULL,
            date DATE NOT NULL,
            item VARCHAR(500) NOT NULL,
            user_id INT NOT NULL,
            firstname VARCHAR(100) NOT NULL,
            lastname VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            reminder1_sent BOOL NOT NULL DEFAULT FALSE,
            reminder2_sent BOOL NOT NULL DEFAULT FALSE,
            item_qty INT NOT NULL DEFAULT 1,
            ts INT NULL DEFAULT NULL,
            validated BOOLEAN NOT NULL DEFAULT TRUE,
            PRIMARY KEY id (id),
            KEY `task_id` (`task_id`),
            KEY `date` (`date`),
            KEY `user_id` (`user_id`)
        ) $charset_collate;";
		$validation_codes = $wpdb->prefix.'pta_sus_validation_codes';
	    $sql .= "CREATE TABLE {$validation_codes} (
            id INT NOT NULL AUTO_INCREMENT,
            firstname VARCHAR(100),
            lastname VARCHAR(100),
            email VARCHAR(100),
            code VARCHAR(200),
        	ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY id (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        update_option("pta_sus_db_version", $this->db_version);
        
        // Add custom role and capability
        $role = get_role( 'author' );
        add_role('signup_sheet_manager', 'Sign-up Sheet Manager', $role->capabilities);
        $role = get_role('signup_sheet_manager');
        if (is_object($role)) {
            $role->add_cap('manage_signup_sheets');
        }

        $role = get_role('administrator');
        if (is_object($role)) {
            $role->add_cap('manage_signup_sheets');
        }

	    // add capability to all super admins
	    $supers = get_super_admins();
	    foreach($supers as $admin) {
		    $user = new WP_User( 0, $admin );
		    $user->add_cap( 'manage_signup_sheets' );
	    }


        // Schedule our Cron job for sending out email reminders
        // Wordpress only checks when someone visits the site, so
        // we'll keep this at hourly so that it hopefully runs at 
        // least once a day
	    if ( ! wp_next_scheduled( 'pta_sus_cron_job' ) ) {
	    	wp_schedule_event( time(), 'hourly', 'pta_sus_cron_job');
	    }
    }
    
    /**
    * Deactivate the plugin
    */
    public function deactivate() {
        // Check permissions and referer
        if ( ! current_user_can( 'activate_plugins' ) )
            return;
        $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
        //check_admin_referer( "deactivate-plugin_{$plugin}" );

        // Remove custom role and capability
        $role = get_role('signup_sheet_manager');
        if (is_object($role)) {
            $role->remove_cap('manage_signup_sheets');
            $role->remove_cap('read');
            remove_role('signup_sheet_manager');
        }
        $role = get_role('administrator');
        if (is_object($role)) {
            $role->remove_cap('manage_signup_sheets');
        }

        wp_clear_scheduled_hook('pta_sus_cron_job');
    }

	/**
	 * Enqueue Gutenberg block assets for both frontend + backend.
	 *
	 */
	public function block_assets() {
		register_block_type( __DIR__ . '/blocks/signup-sheet/block.json', array(
			'render_callback' => array( $this, 'render_volunteer_signup_block' )
		) );

		register_block_type( __DIR__ . '/blocks/user-signups/block.json', array(
			'render_callback' => array( $this, 'render_user_signups_block' )
		) );

		register_block_type( __DIR__ . '/blocks/upcoming-events/block.json', array(
			'render_callback' => array( $this, 'render_upcoming_events_block' )
		) );

		register_block_type( __DIR__ . '/blocks/validation-form/block.json', array(
			'render_callback' => array( $this, 'render_validation_form_block' )
		) );

	}

	public function render_volunteer_signup_block( $attributes ) {
		$shortcode_atts = array(
			'id' => $attributes['id'] ?? '',
			'date' => $attributes['date'] ?? '',
			'group' => $attributes['group'] ?? '',
			'list_title' => $attributes['list_title'] ?? '',
			'show_headers' => $attributes['show_headers'] ?? 'yes',
			'show_time' => $attributes['show_time'] ?? 'yes',
			'show_phone' => $attributes['show_phone'] ?? 'no',
			'show_email' => $attributes['show_email'] ?? 'no',
			'order_by' => $attributes['order_by'] ?? 'first_date',
			'order' => $attributes['order'] ?? 'ASC'
		);

		$shortcode = '[pta_sign_up_sheet';
		foreach ($shortcode_atts as $key => $value) {
			if (!empty($value)) {
				$shortcode .= ' ' . $key . '="' . esc_attr($value) . '"';
			}
		}
		$shortcode .= ']';

		return do_shortcode($shortcode);
	}

	public function render_user_signups_block($attributes) {
		$shortcode_atts = array(
			'show_time' => $attributes['show_time'] ?? 'yes'
		);

		$shortcode = '[pta_user_signups';
		foreach ($shortcode_atts as $key => $value) {
			if (!empty($value)) {
				$shortcode .= ' ' . $key . '="' . esc_attr($value) . '"';
			}
		}
		$shortcode .= ']';

		return do_shortcode($shortcode);
	}

	public function render_upcoming_events_block($attributes) {
		$widget = new PTA_SUS_Widget();

		$widget_args = array(
			'before_widget' => '',
			'after_widget' => '',
			'before_title' => '<h2>',
			'after_title' => '</h2>'
		);

		$instance = array(
			'title' => $attributes['title'] ?? 'Current Volunteer Opportunities',
			'num_items' => $attributes['num_items'] ?? 10,
			'show_what' => $attributes['show_what'] ?? 'both',
			'sort_by' => $attributes['sort_by'] ?? 'first_date',
			'order' => $attributes['order'] ?? 'ASC',
			'list_class' => $attributes['list_class'] ?? ''
		);

		ob_start();
		$widget->widget($widget_args, $instance);
		return ob_get_clean();
	}

	public function render_validation_form_block($attributes) {
		// For block editor preview and REST requests
		if (defined('REST_REQUEST') && REST_REQUEST || is_admin()) {
			return pta_get_validation_form();
		}

		// For frontend rendering
		if (!isset($this->public)) {
			if (!class_exists('PTA_SUS_Public')) {
				include_once(dirname(__FILE__).'/classes/class-pta_sus_public.php');
			}
			$this->public = new PTA_SUS_Public();
		}
		return $this->public->process_validation_form_shortcode($attributes);
	}


}

require_once(dirname(__FILE__).'/pta-sus-global-functions.php');
require_once(dirname(__FILE__).'/classes/class-pta_sus_messages.php');
require_once(dirname(__FILE__).'/classes/class-pta_sus_volunteer.php');
require_once(dirname(__FILE__).'/classes/class-pta_sus_signup_functions.php');
global $pta_sus;
$pta_sus = new PTA_Sign_Up_Sheet();
$pta_sus->init_hooks();
require_once(dirname(__FILE__).'/classes/class-pta-sus-ajax.php');

endif; // class exists

$pta_vol_sus_plugin_file = 'pta-volunteer-sign-up-sheets/pta-volunteer-sign-up-sheets.php';
add_filter( "plugin_action_links_{$pta_vol_sus_plugin_file}", 'pta_vol_sus_plugin_action_links', 10, 2 );
function pta_vol_sus_plugin_action_links( $links, $file ) {
    $extensions_link = '<a href="https://stephensherrardplugins.com">' . __( 'Extensions', 'pta-volunteer-sign-up-sheets' ) . '</a>';
    array_unshift( $links, $extensions_link );
    $docs_link = '<a href="https://stephensherrardplugins.com/docs/pta-volunteer-sign-up-sheets-documentation/">' . __( 'Docs', 'pta-volunteer-sign-up-sheets' ) . '</a>';
    array_unshift( $links, $docs_link );
    $settings_link = '<a href="' . admin_url( 'admin.php?page=pta-sus-settings_settings' ) . '">' . __( 'Settings', 'pta-volunteer-sign-up-sheets' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

/**
 * Register exporter for Plugin user data.
 *
 * @see https://github.com/allendav/wp-privacy-requests/blob/master/EXPORT.md
 *
 * @param $exporters
 *
 * @return array
 */
function pta_sus_register_exporters( $exporters ) {
	$exporters[] = array(
		'exporter_friendly_name' => __( 'Volunteer Sign Up Data', 'pta-volunteer-sign-up-sheets' ),
		'callback'               => 'pta_sus_user_data_exporter',
	);
	return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'pta_sus_register_exporters');

/**
 * Exporter for Plugin user data.
 *
 * @see https://github.com/allendav/wp-privacy-requests/blob/master/EXPORT.md
 *
 * @param     $email_address
 * @param int $page
 *
 * @return array
 */
function pta_sus_user_data_exporter($email_address, $page = 1) {
	global $pta_sus;
	$export_items = $pta_sus->data->get_gdpr_user_export_items($email_address);

	// Returns an array of exported items for this pass, but also a boolean whether this exporter is finished.
	//If not it will be called again with $page increased by 1.
	return array(
		'data' => $export_items,
		'done' => true,
	);
}

/**
 * Register eraser for Plugin user data.
 *
 * @param array $erasers
 *
 * @return array
 */
function pta_sus_plugin_register_erasers( $erasers = array() ) {
	$erasers[] = array(
		'eraser_friendly_name' => __( 'Volunteer Sign Up Data', 'pta-volunteer-sign-up-sheets' ),
		'callback'               => 'pta_sus_user_data_eraser',
	);
	return $erasers;
}
add_filter( 'wp_privacy_personal_data_erasers', 'pta_sus_plugin_register_erasers' );

/**
 * Eraser for Plugin user data.
 *
 * @param     $email_address
 * @param int $page
 *
 * @return array
 */
function pta_sus_user_data_eraser( $email_address, $page = 1 ) {
	if ( empty( $email_address ) ) {
		return array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
	$messages = array();
	$items_removed  = false;
	$items_retained = false;
	global $pta_sus;
	$results = $pta_sus->data->gdpr_delete_user_data($email_address);
	if ( false === $results ) {
		$messages[] = __( 'Your Volunteer Sign Up Info was unable to be removed at this time.');
		$items_retained = true;
	} else {
		$items_removed = true;
	}
	// Returns an array of exported items for this pass, but also a boolean whether this exporter is finished.
	//If not it will be called again with $page increased by 1.
	return array(
		'items_removed'  => $items_removed,
		'items_retained' => $items_retained,
		'messages'       => $messages,
		'done'           => true,
	);
}
/* EOF */