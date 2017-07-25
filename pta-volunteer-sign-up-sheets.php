<?php
/*
Plugin Name: PTA Volunteer Sign Up Sheets
Plugin URI: http://wordpress.org/plugins/pta-volunteer-sign-up-sheets
Description: Volunteer sign-up sheet manager
Version: 2.2.0
Author: Stephen Sherrard
Author URI: https://stephensherrardplugins.com
License: GPL2
Text Domain: pta_volunteer_sus
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Save version # in database for future upgrades
if (!defined('PTA_VOLUNTEER_SUS_VERSION_KEY'))
    define('PTA_VOLUNTEER_SUS_VERSION_KEY', 'pta_volunteer_sus_version');

if (!defined('PTA_VOLUNTEER_SUS_VERSION_NUM'))
    define('PTA_VOLUNTEER_SUS_VERSION_NUM', '2.2.0');

add_option(PTA_VOLUNTEER_SUS_VERSION_KEY, PTA_VOLUNTEER_SUS_VERSION_NUM);

if (!class_exists('PTA_SUS_Data')) require_once 'classes/data.php';
if (!class_exists('PTA_SUS_List_Table')) require_once 'classes/list-table.php';
if (!class_exists('PTA_SUS_Widget')) require_once 'classes/widget.php';
if (!class_exists('PTA_SUS_CSV_EXPORTER')) require_once 'classes/class-pta_csv_exporter.php';
if (!class_exists('PTA_SUS_Emails')) require_once 'classes/class-pta_sus_emails.php';

// To resolve fatal errors with PHP versions < 5.3 that don't have str_getcsv function
if(!function_exists('str_getcsv')) {
    function str_getcsv($input, $delimiter = ',', $enclosure = '"') {

        if( ! preg_match("/[$enclosure]/", $input) ) {
          return (array)preg_replace(array("/^\\s*/", "/\\s*$/"), '', explode($delimiter, $input));
        }

        $token = "##"; $token2 = "::";
        //alternate tokens "\034\034", "\035\035", "%%";
        $t1 = preg_replace(array("/\\\[$enclosure]/", "/$enclosure{2}/",
             "/[$enclosure]\\s*[$delimiter]\\s*[$enclosure]\\s*/", "/\\s*[$enclosure]\\s*/"),
             array($token2, $token2, $token, $token), trim(trim(trim($input), $enclosure)));

        $a = explode($token, $t1);
        foreach($a as $k=>$v) {
            if ( preg_match("/^{$delimiter}/", $v) || preg_match("/{$delimiter}$/", $v) ) {
                $a[$k] = trim($v, $delimiter); $a[$k] = preg_replace("/$delimiter/", "$token", $a[$k]); }
        }
        $a = explode($token, implode($token, $a));
        return (array)preg_replace(array("/^\\s/", "/\\s$/", "/$token2/"), array('', '', $enclosure), $a);

    }
}

if(!class_exists('PTA_Sign_Up_Sheet')):

class PTA_Sign_Up_Sheet {
	
    public $data;
    public $public = false;
    private $emails;
    public $db_version = '2.0.1';
    public $main_options;
    
    public function __construct() {
        
        $this->emails = new PTA_SUS_Emails();
	    $this->data = new PTA_SUS_Data();

        add_shortcode('pta_sign_up_sheet', array($this, 'display_sheet'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook( __FILE__, array($this, 'deactivate'));

        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('pta_sus_cron_job', array($this, 'cron_functions'));

        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'public_init' ));

        add_action( 'widgets_init', array($this, 'register_sus_widget') );

        add_action( 'wpmu_new_blog', array($this, 'new_blog'), 10, 6); 

        $this->main_options = get_option( 'pta_volunteer_sus_main_options' );
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
	public function get_sheets($trash=false, $active_only=false, $show_hidden=false) {
		return $this->data->get_sheets($trash, $active_only, $show_hidden);
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
	 * @param $id ID of sheet to display
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

    public function register_sus_widget() {
        register_widget( 'PTA_SUS_Widget' );
    }
        
    /**
    * Admin Menu
    */
    public function admin_menu() {
        if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_signup_sheets' ) ) {
            if (!class_exists('PTA_SUS_Admin')) {
                include_once(dirname(__FILE__).'/classes/class-pta_sus_admin.php');
                $pta_sus_admin = new PTA_SUS_Admin();
            }
        }
    }


    public function cron_functions() {
        // Let other plugins hook into our hourly cron job
        do_action( 'pta_sus_hourly_cron' );

        // Run our reminders email check
        $this->emails->send_reminders();

        // If automatic clearing of expired signups is enabled, run the check
        if($this->main_options['clear_expired_signups']) {
            $this->data = new PTA_SUS_Data();
            $results = $this->data->delete_expired_signups();
            if($results && $this->main_options['enable_cron_notifications']) {
                $to = get_bloginfo( 'admin_email' );
                $subject = __("Volunteer Signup Housekeeping Completed!", 'pta_volunteer_sus');
                $message = __("Volunteer signup sheet CRON job has been completed.", 'pta_volunteer_sus')."\n\n" . 
                sprintf(__("%d expired signups were deleted.", 'pta_volunteer_sus'), (int)$results) . "\n\n";
                wp_mail($to, $subject, $message);            
            }
        }
    }

    public function public_init() {
        if(!is_admin()) {
            if (!class_exists('PTA_SUS_Public')) {
                include_once(dirname(__FILE__).'/classes/class-pta_sus_public.php');
            }
            $this->public = new PTA_SUS_Public();
        }
    }

    public function init() {
        load_plugin_textdomain( 'pta_volunteer_sus', false, dirname(plugin_basename( __FILE__ )) . '/languages/' );
        // Check our database version and run the activate function if needed
        $current = get_option( "pta_sus_db_version" );
        if ($current < $this->db_version) {
            $this->pta_sus_activate();
        }

        // If options haven't previously been setup, create the default options
        // MAIN OPTIONS
        $defaults = array(
                    'enable_test_mode' => false,
                    'test_mode_message' => 'The Volunteer Sign-Up System is currently undergoing maintenance. Please check back later.',
                    'volunteer_page_id' => 0,
                    'hide_volunteer_names' => false,
                    'show_remaining' => false,
                    'show_ongoing_in_widget' => true,
                    'show_ongoing_last' => true,
                    'no_phone' => false,
                    'hide_contact_info' => false,
                    'login_required' => false,
                    'login_required_signup' => false,
                    'login_required_message' => 'You must be logged in to a valid account to view and sign up for volunteer opportunities.',
                    'login_signup_message' => 'Login to Signup',
                    'readonly_signup' => false,
                    'show_login_link' => false,
                    'disable_signup_login_notice' => false,
                    'enable_cron_notifications' => true,
                    'detailed_reminder_admin_emails' => true,
                    'show_expired_tasks' => false,
                    'clear_expired_signups' => true,
                    'hide_donation_button' => false,
                    'reset_options' => false,
                    'enable_signup_search' => false,
                    'signup_search_tables' => 'signups',
	                'signup_redirect' => true,
	                'phone_required' => true,
	                'use_divs' => false,
	                'disable_css' => false,
	                'show_full_name' => false,
	                'suppress_duplicates' => true,
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
                    'cc_email' => '',
                    'from_email' => get_bloginfo( $show='admin_email' ),
                    'replyto_email' => get_bloginfo( $show='admin_email' ),
                    'confirmation_email_subject' => 'Thank you for volunteering!',
                    'confirmation_email_template' => $confirm_template,
                    'clear_email_subject' => 'Volunteer spot cleared!',
                    'clear_email_template' => $clear_template,
                    'reminder_email_subject' => 'Volunteer Reminder',
                    'reminder_email_template' => $remind_template,
                    'reminder2_email_subject' => '',
                    'reminder2_email_template' => '',
                    'reminder_email_limit' => "",
	                'individual_emails' => false,
                    'admin_clear_emails' => false,
                    'no_chair_emails' => false,
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
    }

      
 
    /*
    *   Run activation procedure to set up tables and options when a new blog is added
     */
    public function new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta ) {
        global $wpdb;
     
        if (is_plugin_active_for_network('pta-volunteer-sign-up-sheets/pta-volunteer-sign-up-sheets.php')) {
            $old_blog = $wpdb->blogid;
            switch_to_blog($blog_id);
            $this->pta_sus_activate();
            switch_to_blog($old_blog);
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
                $old_blog = $wpdb->blogid;
                // Get all blog ids
                $blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
                foreach ($blogids as $blog_id) {
                    switch_to_blog($blog_id);
                    $this->pta_sus_activate();
                }
                switch_to_blog($old_blog);
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
            UNIQUE KEY id (id)
        ) $charset_collate;";
        $sql .= "CREATE TABLE {$this->data->tables['task']['name']} (
            id INT NOT NULL AUTO_INCREMENT,
            sheet_id INT NOT NULL,
            dates VARCHAR(8000) NOT NULL,
            title VARCHAR(200) NOT NULL,
            time_start VARCHAR(50),
            time_end VARCHAR(50),
            qty INT NOT NULL DEFAULT 1,
            need_details VARCHAR(3) NOT NULL DEFAULT 'NO',
            details_text VARCHAR(200) NOT NULL DEFAULT 'Item you are bringing',
            details_required VARCHAR(3) NOT NULL DEFAULT 'YES',
            allow_duplicates VARCHAR(3) NOT NULL DEFAULT 'NO',
            enable_quantities VARCHAR(3) NOT NULL DEFAULT 'NO',
            position INT NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";
        $sql .= "CREATE TABLE {$this->data->tables['signup']['name']} (
            id INT NOT NULL AUTO_INCREMENT,
            task_id INT NOT NULL,
            date DATE NOT NULL,
            item VARCHAR(300) NOT NULL,
            user_id INT NOT NULL,
            firstname VARCHAR(100) NOT NULL,
            lastname VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            reminder1_sent BOOL NOT NULL DEFAULT FALSE,
            reminder2_sent BOOL NOT NULL DEFAULT FALSE,
            item_qty INT NOT NULL DEFAULT 1,
            UNIQUE KEY id (id)
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
        wp_schedule_event( time(), 'hourly', 'pta_sus_cron_job');

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
	
}
	
global $pta_sus;
$pta_sus = new PTA_Sign_Up_Sheet();

endif; // class exists

$pta_vol_sus_plugin_file = 'pta-volunteer-sign-up-sheets/pta-volunteer-sign-up-sheets.php';
add_filter( "plugin_action_links_{$pta_vol_sus_plugin_file}", 'pta_vol_sus_plugin_action_links', 10, 2 );
function pta_vol_sus_plugin_action_links( $links, $file ) {
    $extensions_link = '<a href="https://stephensherrardplugins.com">' . __( 'Extensions', 'pta_volunteer_sus' ) . '</a>';
    array_unshift( $links, $extensions_link );
    $docs_link = '<a href="https://stephensherrardplugins.com/docs/pta-volunteer-sign-up-sheets-documentation/">' . __( 'Docs', 'pta_volunteer_sus' ) . '</a>';
    array_unshift( $links, $docs_link );
    $settings_link = '<a href="' . admin_url( 'admin.php?page=pta-sus-settings_settings' ) . '">' . __( 'Settings', 'pta_volunteer_sus' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

/* EOF */
