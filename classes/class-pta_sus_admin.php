<?php
/**
 * Admin Pages Class
 * 
 * Handles all admin-facing functionality for the Volunteer Sign-Up Sheets plugin.
 * This class manages admin pages, form processing, list tables, and provides hooks
 * for extensions to customize admin behavior.
 * 
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (!class_exists('PTA_SUS_Options')) include_once(dirname(__FILE__).'/class-pta_sus_options.php');

class PTA_SUS_Admin {

	/**
	 * Admin settings page slug
	 * 
	 * @var string
	 */
	private $admin_settings_slug = 'pta-sus-settings';
	
	/**
	 * Options page object
	 * 
	 * @var PTA_SUS_Options
	 */
	public $options_page;
	
	/**
	 * Whether PTA Member Directory plugin is active
	 * 
	 * @var bool
	 */
	private $member_directory_active;
	
	/**
	 * Main plugin options array
	 * 
	 * @var array
	 */
	public $main_options;
	
	/**
	 * Email options array
	 * 
	 * @var array
	 */
	public $email_options;
	
	/**
	 * List table object for sheets display
	 * 
	 * @var PTA_SUS_List_Table
	 */
	public $table;
	
	/**
	 * Whether to show settings menu items
	 * 
	 * @var bool
	 */
	private $show_settings;

	/**
	 * Whether last action was successful
	 * 
	 * @var bool
	 */
	private $success;
	
	/**
	 * Current action being processed
	 * 
	 * @var string
	 */
	private $action;

	/**
	 * Constructor
	 * 
	 * Initializes the admin class, loads options, and sets up data access.
	 * 
	 * @since 1.0.0
	 */
	public function __construct() {
		global $pta_sus_sheet_page_suffix, $pta_sus;

		$this->options_page = new PTA_SUS_Options();

		$this->main_options = get_option( 'pta_volunteer_sus_main_options' );
		$this->email_options = get_option( 'pta_volunteer_sus_email_options' );

	}

	/**
	 * Initialize admin hooks
	 * 
	 * Registers all WordPress admin hooks for menu pages, scripts, AJAX handlers,
	 * and screen options.
	 * 
	 * @since 1.0.0
	 */
	public function init_admin_hooks() {
		add_action('admin_menu', array($this, 'admin_menu'));
		add_action('admin_enqueue_scripts', array($this, 'add_sheet_admin_scripts') );
		add_action( 'wp_ajax_pta_sus_get_user_data', array($this, 'get_user_data' ) );
		add_action( 'wp_ajax_pta_sus_user_search', array($this, 'user_search' ) );
		add_filter( 'set-screen-option', array( $this, 'set_screen' ), 10, 3 );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	/**
	 * Set screen option value
	 * 
	 * WordPress filter callback for screen options. Returns the value to save.
	 * 
	 * @since 1.0.0
	 * @param mixed $status Current status (ignored)
	 * @param string $option Option name
	 * @param mixed $value Value to save
	 * @return mixed The value to save
	 */
	public function set_screen($status, $option, $value) {
		return $value;
	}

	/**
	 * Get user data via AJAX
	 * 
	 * AJAX handler that returns user data (name, email, phone) for a given user ID.
	 * Used by admin signup forms for autocomplete functionality.
	 * 
	 * @since 1.0.0
	 * @return void Sends JSON response and exits
	 * @hook pta_sus_admin_ajax_get_user_data Filter to modify user data response
	 */
	public function get_user_data() {
		check_ajax_referer( 'ajax-pta-nonce', 'security' );
		$response = array();
		if(isset($_POST['user_id']) && absint($_POST['user_id']) > 0) {
			$user = get_user_by('id', absint( $_POST['user_id']));
			if($user) {
				$response = array(
					'firstname' => esc_html($user->first_name),
					'lastname' => esc_html($user->last_name),
					'email' => esc_html($user->user_email),
					'phone' => esc_html(get_user_meta($user->ID, 'billing_phone', true))
				);
				$response = apply_filters('pta_sus_admin_ajax_get_user_data', $response, $user);
			}
		}
		wp_send_json( $response);
	}

	/**
	 * Search users via AJAX
	 * 
	 * AJAX handler that searches WordPress users by first/last name and returns
	 * matching user data. Used by admin signup forms for autocomplete functionality.
	 * 
	 * @since 1.0.0
	 * @return void Sends JSON response and exits
	 * @hook pta_sus_admin_ajax_user_search_data Filter to modify user search results
	 */
	public function user_search() {
		check_ajax_referer( 'ajax-pta-nonce', 'security' );
		$response = array();
		if(isset($_POST['keyword'])) {
			$args = array (
			    'search' => '*'.esc_attr( $_POST['keyword'] ).'*',
			    'meta_query' => array(
			        'relation' => 'OR',
			        array(
			            'key'     => 'first_name',
			            'value'   => $_POST['keyword'],
			            'compare' => 'LIKE'
			        ),
			        array(
			            'key'     => 'last_name',
			            'value'   => $_POST['keyword'],
			            'compare' => 'LIKE'
			        )
			    ),
				'fields' => 'ID'
			);
			$users = get_users($args);
			if($users) {
				$return = array();
				foreach($users as $user_id) {
					$user = get_user_by( 'ID', $user_id);
					if($user) {
						$user_data = array(
							'user_id' => absint($user_id),
							'firstname' => esc_html($user->first_name),
							'lastname' => esc_html($user->last_name),
							'email' => esc_html($user->user_email),
							'phone' => esc_html(get_user_meta($user_id, 'billing_phone', true)),
							'label' => esc_html($user->first_name) . ' ' . esc_html($user->last_name),
							'value' => esc_html($user->first_name)
						);
						$return[] = apply_filters('pta_sus_admin_ajax_user_search_data', $user_data, $user);
					}
				}
				$response = $return;
			}
		}
		wp_send_json( $response);
	}

	/**
	 * Admin initialization
	 * 
	 * WordPress admin_init hook callback. Processes list table actions.
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_init() {
		$this->maybe_process_list_table_actions();
	}

	/**
	 * Register admin menu pages
	 * 
	 * Creates the main admin menu and all submenu pages. Checks for member directory
	 * plugin and adjusts menu items based on user capabilities and settings.
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_menu() {
		if (is_plugin_active( 'pta-member-directory/pta-member-directory.php' )) {
			$this->member_directory_active = true;
		} else {
			$this->member_directory_active = false;
		}
		$this->show_settings = (current_user_can('manage_options') || !isset($this->main_options['admin_only_settings']) || false == $this->main_options['admin_only_settings']);
		if($this->show_settings) {
			add_filter( 'option_page_capability_pta_volunteer_sus_main_options', array($this,'pta_settings_permissions'), 10, 1 );
		}
		if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_signup_sheets' ) ) {
			add_menu_page(__('Sign-up Sheets', 'pta-volunteer-sign-up-sheets'), __('Sign-up Sheets', 'pta-volunteer-sign-up-sheets'), 'manage_signup_sheets', $this->admin_settings_slug.'_sheets', array($this, 'admin_sheet_page'), null, 93);
			$all_sheets = add_submenu_page($this->admin_settings_slug.'_sheets', __('Sign-up Sheets', 'pta-volunteer-sign-up-sheets'), __('All Sheets', 'pta-volunteer-sign-up-sheets'), 'manage_signup_sheets', $this->admin_settings_slug.'_sheets', array($this, 'admin_sheet_page'));
			add_submenu_page($this->admin_settings_slug.'_sheets', __('Add New Sheet', 'pta-volunteer-sign-up-sheets'), __('Add New', 'pta-volunteer-sign-up-sheets'), 'manage_signup_sheets', $this->admin_settings_slug.'_modify_sheet', array($this, 'admin_modify_sheet_page'));
			add_submenu_page($this->admin_settings_slug.'_sheets', __('Email Volunteers', 'pta-volunteer-sign-up-sheets'), __('Email Volunteers', 'pta-volunteer-sign-up-sheets'), 'manage_signup_sheets', $this->admin_settings_slug.'_email', array($this, 'email_volunteers_page'));
			if($this->show_settings) {
				add_submenu_page($this->admin_settings_slug.'_sheets', __('Settings', 'pta-volunteer-sign-up-sheets'), __('Settings', 'pta-volunteer-sign-up-sheets'), 'manage_signup_sheets', $this->admin_settings_slug.'_settings', array($this->options_page, 'admin_options'));
				add_submenu_page($this->admin_settings_slug.'_sheets', __('CRON Functions', 'pta-volunteer-sign-up-sheets'), __('CRON Functions', 'pta-volunteer-sign-up-sheets'), 'manage_signup_sheets', $this->admin_settings_slug.'_cron', array($this, 'admin_reminders_page'));
				add_submenu_page($this->admin_settings_slug.'_sheets', __('Add Ons', 'pta-volunteer-sign-up-sheets'), __('Add Ons', 'pta-volunteer-sign-up-sheets'), 'manage_signup_sheets', $this->admin_settings_slug.'_addons', array($this, 'admin_addons_page'));
			}
			add_action( "load-$all_sheets", array( $this, 'screen_options' ) );
		}
	}

	/**
	 * Set up screen options
	 * 
	 * Configures screen options for the sheets list table, including per-page
	 * pagination. Only adds options on the main list page, not on view/edit pages.
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function screen_options() {
		// Only add on the main all sheets pages (or after copy/trash actions) - not on any view/edit pages
		if(isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('edit_sheet', 'view_signup', 'edit_tasks'))) {
			return;
		}

		// List table needs to be setup before screen options added, so column check boxes will show
		$this->table = new PTA_SUS_List_Table();
		$option = 'per_page';
		$args   = array(
			'label'   => __('Sheets', 'pta-volunteer-sign-up-sheets'),
			'default' => 20,
			'option'  => 'sheets_per_page'
		);
		add_screen_option( $option, $args );

	}

	/**
	 * Filter settings page capability
	 * 
	 * WordPress filter callback to change the required capability for settings pages
	 * from 'manage_options' to 'manage_signup_sheets'.
	 * 
	 * @since 1.0.0
	 * @param string $capability Current capability requirement
	 * @return string Modified capability ('manage_signup_sheets')
	 */
	public function pta_settings_permissions( $capability ) {
		return 'manage_signup_sheets';
	}

	/**
	 * Enqueue admin scripts and styles
	 * 
	 * Registers and enqueues all CSS and JavaScript files needed for admin pages,
	 * including DataTables, date/time pickers, autocomplete, and localization data.
	 * 
	 * @since 1.0.0
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public function add_sheet_admin_scripts($hook) {
		// only add scripts on our settings pages
		if (strpos($hook, 'pta-sus-settings') !== false) {
			wp_enqueue_style( 'pta-admin-style', plugins_url( '../assets/css/pta-admin-style.min.css', __FILE__ ) );
			wp_enqueue_style( 'pta-datatables-style' );
			wp_enqueue_script( 'jquery-plugin' );
			wp_enqueue_script( 'pta-jquery-datepick' );
			wp_enqueue_script( 'pta-jquery-ui-timepicker', plugins_url( '../assets/js/jquery.ui.timepicker.js' , __FILE__ ), array( 'jquery', 'jquery-ui-core', 'jquery-ui-position' ) );
			wp_enqueue_script('pta-datatables');
			wp_enqueue_script('jquery-ui-sortable');
			wp_enqueue_script( 'jquery-ui-autocomplete');
			wp_enqueue_script( 'pta-sus-backend', plugins_url( '../assets/js/backend.min.js' , __FILE__ ), array( 'jquery','pta-jquery-datepick','pta-jquery-ui-timepicker', 'pta-datatables','jquery-ui-sortable','jquery-ui-autocomplete'), PTA_VOLUNTEER_SUS_VERSION_NUM, true );
			wp_enqueue_style( 'pta-jquery-datepick');
			wp_enqueue_style( 'pta-jquery.ui.timepicker', plugins_url( '../assets/css/jquery.ui.timepicker.css', __FILE__ ) );
			wp_enqueue_style( 'pta-jquery-ui-1.10.0.custom', plugins_url( '../assets/css/jquery-ui-1.10.0.custom.min.css', __FILE__ ) );
            // Enqueue live search script for admin signup forms
            if (isset($this->main_options['enable_signup_search']) && $this->main_options['enable_signup_search']) {
                wp_enqueue_style('pta-sus-autocomplete');
                wp_enqueue_script('pta-sus-autocomplete');
                wp_localize_script('pta-sus-autocomplete', 'ptaSUS', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'ptaNonce' => wp_create_nonce('ajax-pta-nonce')
                ));
            }
			$translation_array = array(
                'ajaxurl' => admin_url('admin-ajax.php'),
				'default_text' => __('Item you are bringing', 'pta-volunteer-sign-up-sheets'),
				'ptaNonce' => wp_create_nonce( 'ajax-pta-nonce' ),
				'excelExport' => __('Export to Excel', 'pta-volunteer-sign-up-sheets'),
				'csvExport' => __('Export to CSV', 'pta-volunteer-sign-up-sheets'),
				'pdfSave' => __('Save as PDF', 'pta-volunteer-sign-up-sheets'),
				'toPrint' => __('Print', 'pta-volunteer-sign-up-sheets'),
				'hideRemaining' => __('Hide Remaining', 'pta-volunteer-sign-up-sheets'),
				'disableGrouping' => __('Disable Grouping', 'pta-volunteer-sign-up-sheets'),
				'colvisText' => __('Column Visibility', 'pta-volunteer-sign-up-sheets'),
				'showAll' => __('Show All', 'pta-volunteer-sign-up-sheets'),
				'disableAdminGrouping' => $this->main_options['disable_grouping'] ?? false,
			);
			wp_localize_script('pta-sus-backend', 'PTASUS', $translation_array);
		}
	}


	/**
	 * Admin page: CRON Functions
	 * 
	 * Displays the CRON Functions page (formerly "Reminders" page) which allows
	 * manual triggering of reminder emails, reschedule emails, expired signup/sheet
	 * clearing, and debug log management.
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_reminders_page() {
		$messages = '';
        $rescheduled_messages = '';
		$cleared_message = $cleared_sheets_message = '';
		if ( $last = get_option( 'pta_sus_last_reminders' ) ) {
			$messages .= '<hr/>';
			$messages .= '<h4>' . __('Last reminders sent:', 'pta-volunteer-sign-up-sheets'). '</h4>';
			$messages .= '<p>' . sprintf(__('Date: %s', 'pta-volunteer-sign-up-sheets'), pta_datetime(get_option('date_format'), $last['time'])) . '<br/>';
			$messages .= sprintf(__('Time: %s', 'pta-volunteer-sign-up-sheets'), pta_datetime(get_option("time_format"), $last['time'])) . '<br/>';
			$messages .= sprintf( _n( '1 reminder sent', '%d reminders sent', $last['num'], 'pta-volunteer-sign-up-sheets'), $last['num'] ) . '</p>';
			$messages .= '<h4>' . __('Last reminder check:', 'pta-volunteer-sign-up-sheets'). '</h4>';
			$messages .= '<p>' . sprintf(__('Date: %s', 'pta-volunteer-sign-up-sheets'), pta_datetime(get_option('date_format'), $last['last'])) . '<br/>';
			$messages .= sprintf(__('Time: %s', 'pta-volunteer-sign-up-sheets'), pta_datetime(get_option("time_format"), $last['last'])) . '<br/>';
			$messages .= '<hr/>';

		}
        if ( $last = get_option( 'pta_sus_last_reschedule_emails' ) ) {
            $rescheduled_messages .= '<hr/>';
            $rescheduled_messages .= '<h4>' . __('Last Rescheduled Emails sent:', 'pta-volunteer-sign-up-sheets'). '</h4>';
            $rescheduled_messages .= '<p>' . sprintf(__('Date: %s', 'pta-volunteer-sign-up-sheets'), pta_datetime(get_option('date_format'), $last['time'])) . '<br/>';
            $rescheduled_messages .= sprintf(__('Time: %s', 'pta-volunteer-sign-up-sheets'), pta_datetime(get_option("time_format"), $last['time'])) . '<br/>';
            $rescheduled_messages .= sprintf( _n( '1 email sent', '%d emails sent', $last['num'], 'pta-volunteer-sign-up-sheets'), $last['num'] ) . '</p>';
            $rescheduled_messages .= '<h4>' . __('Last Rescheduled Emails check:', 'pta-volunteer-sign-up-sheets'). '</h4>';
            $rescheduled_messages .= '<p>' . sprintf(__('Date: %s', 'pta-volunteer-sign-up-sheets'), pta_datetime(get_option('date_format'), $last['last'])) . '<br/>';
            $rescheduled_messages .= sprintf(__('Time: %s', 'pta-volunteer-sign-up-sheets'), pta_datetime(get_option("time_format"), $last['last'])) . '<br/>';
            $rescheduled_messages .= '<hr/>';

        }
		if (isset($_GET['action']) && 'reminders' == $_GET['action']) {
			check_admin_referer( 'pta-sus-reminders', '_sus_nonce');
			$num = PTA_SUS_Email_Functions::send_reminders();
			$results = sprintf( _n( '1 reminder sent', '%d reminders sent', $num, 'pta-volunteer-sign-up-sheets'), $num );
			$messages .= '<div class="updated">'.$results.'</div>';
		}
        if (isset($_GET['action']) && 'reschedule' == $_GET['action']) {
            check_admin_referer( 'pta-sus-reschedule', '_sus_nonce');
            $num = PTA_SUS_Email_Functions::send_reschedule_emails();
            $results = sprintf( _n( '1 email sent', '%d emails sent', $num, 'pta-volunteer-sign-up-sheets'), $num );
            $rescheduled_messages .= '<div class="updated">'.$results.'</div>';
        }
		if (isset($_GET['action']) && 'clear_signups' === $_GET['action'] ) {
			check_admin_referer( 'pta-sus-clear-signups', '_sus_nonce');
			$num = PTA_SUS_Signup_Functions::delete_expired_signups();
			$results = sprintf( _n( '1 signup cleared', '%d signups cleared', $num, 'pta-volunteer-sign-up-sheets'), $num );
			$cleared_message = '<div class="updated">'.$results.'</div>';
		}
		if (isset($_GET['action']) && 'clear_sheets' === $_GET['action'] ) {
			check_admin_referer( 'pta-sus-clear-sheets', '_sus_nonce');
			$num = PTA_SUS_Sheet_Functions::delete_expired_sheets();
			$results = sprintf( _n( '1 sheet cleared', '%d sheets cleared', $num, 'pta-volunteer-sign-up-sheets'), $num );
			$cleared_sheets_message = '<div class="updated">'.$results.'</div>';
		}
		// Handle clear log action
		if (isset($_GET['action']) && 'clear_debug_log' === $_GET['action']) {
			check_admin_referer('pta-sus-clear-debug-log', '_sus_nonce');
			if(pta_clear_log_file()) {
				echo '<div class="updated"><p>' . __('Debug log cleared successfully.', 'pta-volunteer-sign-up-sheets') . '</p></div>';
			}
		}
		$reminders_link = add_query_arg(array('action' => 'reminders'));
		$nonced_reminders_link = wp_nonce_url( $reminders_link, 'pta-sus-reminders', '_sus_nonce');
        $reschedule_link = add_query_arg(array('action' => 'reschedule'));
        $nonced_reschedule_link = wp_nonce_url( $reschedule_link, 'pta-sus-reschedule', '_sus_nonce');
		$clear_signups_link = add_query_arg(array('action' => 'clear_signups'));
		$nonced_clear_signups_link = wp_nonce_url( $clear_signups_link, 'pta-sus-clear-signups', '_sus_nonce');
		$clear_sheets_link = add_query_arg(array('action' => 'clear_sheets'));
		$nonced_clear_sheets_link = wp_nonce_url( $clear_sheets_link, 'pta-sus-clear-sheets', '_sus_nonce');
		// Create clear log button with nonce
		$clear_log_url = add_query_arg(array('action' => 'clear_debug_log'));
		$nonced_clear_log_url = wp_nonce_url($clear_log_url, 'pta-sus-clear-debug-log', '_sus_nonce');
		echo '<div class="wrap pta_sus">';
		echo '<h2>'.__('CRON Functions', 'pta-volunteer-sign-up-sheets').'</h2>';
		echo '<h3>'.__('Volunteer Reminders', 'pta-volunteer-sign-up-sheets').'</h3>';
		echo '<p>'.__("The system automatically checks if it needs to send reminders hourly via a CRON function. If you are testing, or don't want to wait for the next CRON job to be triggered, you can trigger the reminders function with the button below.", "pta_volunteer_sus") . '</p>';
		echo $messages;
		echo '<p><a href="'.esc_url($nonced_reminders_link).'" class="button-primary">'.__('Send Reminders', 'pta-volunteer-sign-up-sheets').'</a></p>';
		echo '<hr/>';
        echo '<h3>'.__('Rescheduled Event Emails', 'pta-volunteer-sign-up-sheets').'</h3>';
        echo '<p>'.__("The system automatically checks if it needs to send rescheduled event emails hourly via a CRON function. If you are testing, or don't want to wait for the next CRON job to be triggered, you can trigger the rescheduled event emails function with the button below.", "pta_volunteer_sus") . '</p>';
        echo $rescheduled_messages;
        echo '<p><a href="'.esc_url($nonced_reschedule_link).'" class="button-primary">'.__('Send Rescheduled Event Emails', 'pta-volunteer-sign-up-sheets').'</a></p>';
        echo '<hr/>';
		echo '<h3>'.__('Clear Expired Signups', 'pta-volunteer-sign-up-sheets').'</h3>';
		echo '<p>'.__("If you have disabled the automatic clearing of expired signups, you can use this to clear ALL expired signups from ALL sheets. NOTE: THIS ACTION CAN NOT BE UNDONE!", "pta_volunteer_sus") . '</p>';
		echo '<p><a href="'.esc_url($nonced_clear_signups_link).'" class="button-secondary">'.__('Clear Expired Signups', 'pta-volunteer-sign-up-sheets').'</a></p>';
		echo $cleared_message;
		echo '<hr/>';
		echo '<h3>'.__('Clear Expired Sheets', 'pta-volunteer-sign-up-sheets').'</h3>';
		echo '<p>'.__("If you have disabled the automatic clearing of expired sheets, you can use this to clear ALL expired sheets from database, which will also clear all associated tasks and signups. NOTE: THIS ACTION CAN NOT BE UNDONE!", "pta_volunteer_sus") . '</p>';
		echo '<p><a href="'.esc_url($nonced_clear_sheets_link).'" class="button-secondary">'.__('Clear Expired Sheets', 'pta-volunteer-sign-up-sheets').'</a></p>';
		echo $cleared_sheets_message;
		echo '<hr/>';
		echo '<h3>' . __('Debug Log', 'pta-volunteer-sign-up-sheets') . '</h3>';
		echo '<p>'.__("This log file will show results of any actions taken during CRON functions, and can be helpful for debugging issues. Use the button below the textarea to clear/reset the log.", "pta_volunteer_sus") . '</p>';
		echo '</div>';
		// Display log contents
		$log_file = WP_CONTENT_DIR . '/uploads/pta-logs/pta_debug.log';
		$log_contents = '';
		if(file_exists($log_file)) {
			$log_contents = file_get_contents($log_file);
		}
		echo '<textarea readonly style="width: 100%; height: 300px; font-family: monospace;">' . esc_textarea($log_contents) . '</textarea>';
		echo '<p><a href="' . esc_url($nonced_clear_log_url) . '" class="button-secondary">' . __('Clear Debug Log', 'pta-volunteer-sign-up-sheets') . '</a></p>';
	}
	
	/**
	 * Output signup column data for admin list tables
	 * 
	 * Generates HTML output for a specific column in the admin signups list table.
	 * Handles various column types (slot, sheet, task, date, name, email, phone, etc.)
	 * and provides filter hooks for extensions to customize output.
	 * 
	 * @since 1.0.0
	 * @param string $slug Column identifier (slot, sheet, task, date, name, email, phone, details, qty, validated, ts, actions)
	 * @param int $i Row number/index
	 * @param PTA_SUS_Sheet|object $sheet Sheet object
	 * @param PTA_SUS_Task|object $task Task object
	 * @param PTA_SUS_Signup|object|false $signup Signup object or false for empty slot
	 * @param string $task_date Task date (YYYY-MM-DD format or '0000-00-00' for ongoing)
	 * @return void Outputs HTML directly
	 * @hook pta_sus_admin_signup_display_sheet_title Filter for sheet title display
	 * @hook pta_sus_admin_signup_display_task_title Filter for task title display
	 * @hook pta_sus_admin_signup_display_task_date Filter for task date display
	 * @hook pta_sus_admin_signup_display_start Filter for start time display
	 * @hook pta_sus_admin_signup_display_end Filter for end time display
	 * @hook pta_sus_admin_signup_display_name Filter for volunteer name display
	 * @hook pta_sus_admin_signup_display_email Filter for email display
	 * @hook pta_sus_admin_signup_display_phone Filter for phone display
	 * @hook pta_sus_admin_signup_display_details Filter for item details display
	 * @hook pta_sus_admin_signup_display_task_description Filter for task description display
	 * @hook pta_sus_admin_signup_display_quantity Filter for quantity display
	 * @hook pta_sus_admin_signup_display_validated Filter for validation status display
	 * @hook pta_sus_admin_signup_display_signup_time Filter for signup timestamp display
	 * @hook pta_sus_admin_signup_display_actions Filter to add custom action links
	 * @hook pta_sus_admin_signup_column_data Action for custom column types
	 */
	public function output_signup_column_data($slug, $i, $sheet, $task, $signup, $task_date) {
		if(!is_object($signup) && in_array($slug, array('name','email','phone','details','qty','actions'))) {
			return;
		}
		if ("0000-00-00" === $task_date) {
			$show_date = '';
		} else {
			$show_date = mysql2date( get_option('date_format'), $task_date, $translate = true );
		}
		switch ($slug) {
			case 'slot':
				echo '#'.$i;
				break;
			case 'sheet':
				$title = apply_filters('pta_sus_admin_signup_display_sheet_title', esc_html($sheet->title), $sheet);
				echo '<strong>'.wp_kses_post($title).'</strong>';
				break;
			case 'task':
				$title = apply_filters('pta_sus_admin_signup_display_task_title', esc_html($task->title), $task);
				echo '<strong>'.wp_kses_post($title).'</strong>';
				break;
			case 'date':
				$date = apply_filters('pta_sus_admin_signup_display_task_date', esc_html($show_date), $task);
				echo '<span class="pta-sortdate">'.strtotime($task_date).'|</span><strong>'.esc_html($date).'</strong>';
				break;
			case 'start':
				$start = apply_filters( 'pta_sus_admin_signup_display_start', ("" == $task->time_start) ? '' : pta_datetime(get_option("time_format"), strtotime($task->time_start)), $task );
				echo wp_kses_post($start);
				break;
			case 'end':
				$end = apply_filters( 'pta_sus_admin_signup_display_end', ("" == $task->time_end) ? '' : pta_datetime(get_option("time_format"), strtotime($task->time_end)), $task );
				echo wp_kses_post($end);
				break;
			case 'name':
				$name = '<em>'.esc_html($signup->firstname).' '.esc_html($signup->lastname).'</em>';
				$name = apply_filters('pta_sus_admin_signup_display_name', $name, $sheet, $signup );
				echo wp_kses_post($name);
				break;
			case 'email':
				$email = apply_filters('pta_sus_admin_signup_display_email', $signup->email, $sheet, $signup);
				echo '<a href="mailto:'.esc_attr($email).'">'.esc_html($email).'</a>';
				break;
			case 'phone':
				$phone = apply_filters('pta_sus_admin_signup_display_phone', $signup->phone, $sheet, $signup);
				echo '<a href="tel:'.esc_attr($phone).'">'.esc_html($phone).'</a>';
				break;
			case 'details':
				$details = apply_filters('pta_sus_admin_signup_display_details', $signup->item, $sheet, $signup);
				echo wp_kses_post($details);
				break;
			case 'description':
				$description = apply_filters('pta_sus_admin_signup_display_task_description', $task->description, $sheet, $signup);
				echo wp_kses_post($description);
				break;
			case 'qty':
				$qty = apply_filters('pta_sus_admin_signup_display_quantity', $signup->item_qty, $sheet, $signup);
				echo wp_kses_post($qty);
				break;
			case 'validated':
				if(!$signup) break;
				$validated = apply_filters('pta_sus_admin_signup_display_validated', $signup->validated, $sheet, $signup);
				if($validated) {
					_e('Yes', 'pta-volunteer-sign-up-sheets');
				} elseif (!empty($signup->email)) {
					// only show "No" if it's not an empty slot
					_e( 'No', 'pta-volunteer-sign-up-sheets' );
				}
				break;
			case 'ts':
				$datetime = '';
				if(!empty($signup->ts)) {
					$ts = apply_filters('pta_sus_admin_signup_display_signup_time', $signup->ts, $sheet, $signup);
					if(null != $ts) {
						$format = get_option('date_format') . ' ' . get_option('time_format');
						$datetime = pta_datetime($format, $ts);
					}
				}
				echo $datetime;
				break;
			case 'actions':
				$clear_url = '?page='.$this->admin_settings_slug.'_sheets&amp;sheet_id='.$sheet->id.'&amp;signup_id='.$signup->id.'&amp;action=clear';
				$nonced_clear_url = wp_nonce_url( $clear_url, 'clear', '_sus_nonce' );
				$edit_url = '?page='.$this->admin_settings_slug.'_sheets&amp;sheet_id='.$sheet->id.'&amp;signup_id='.$signup->id.'&amp;action=edit_signup';
				$nonced_edit_url = wp_nonce_url( $edit_url, 'edit_signup', '_sus_nonce' );
				$move_url = '?page='.$this->admin_settings_slug.'_sheets&amp;sheet_id='.$sheet->id.'&amp;signup_id='.$signup->id.'&amp;action=move_signup';
				$nonced_move_url = wp_nonce_url( $move_url, 'move_signup', '_sus_nonce' );
				$actions = '<a href="'. esc_url($nonced_clear_url) . '" title="'.esc_attr(__('Clear Spot','pta-volunteer-sign-up-sheets')).'"><span class="dashicons dashicons-trash">&nbsp;</span></a>';
				$actions .= '<a href="'. esc_url($nonced_edit_url) . '" title="'.esc_attr(__('Edit Signup','pta-volunteer-sign-up-sheets')).'"><span class="dashicons dashicons-edit">&nbsp;</span></a>';
				$actions .= '<a href="'. esc_url($nonced_move_url) . '" title="'.esc_attr(__('Move Signup','pta-volunteer-sign-up-sheets')).'"><span class="dashicons dashicons-schedule">&nbsp;</span></a>';
				$actions .= apply_filters('pta_sus_admin_signup_display_actions', '', $signup); // allow other extensions to add additional actions
				echo $actions;
				break;
			default:
				do_action('pta_sus_admin_signup_column_data', $slug, $i, $sheet, $task, $signup);
				break;
		}
	}

	/**
	 * Get required signup fields for a task
	 * 
	 * @deprecated 6.0.0 Use PTA_SUS_Validation::get_required_signup_fields() instead
	 * @param int $task_id Task ID
	 * @return array Array of required field names
	 */
	private function get_required_signup_fields($task_id) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Validation::get_required_signup_fields() ' . sprintf('Called from %s line %s', $file, $line)
		);
		return PTA_SUS_Validation::get_required_signup_fields($task_id, $this->main_options);
	}

	/**
	 * Process admin signup form submission
	 * 
	 * Handles add/edit signup form submissions from the admin interface. Validates
	 * form data, saves signup to database, and optionally sends confirmation email.
	 * Supports both adding new signups and editing existing ones.
	 * 
	 * @since 1.0.0
	 * @return bool True on success, false on failure
	 * @hook pta_sus_admin_signup_posted_values Filter to modify posted form values
	 * @hook pta_sus_admin_saved_signup Action fired after signup is saved (before email)
	 * @see PTA_SUS_Validation::validate_signup_fields()
	 */
	private function process_signup_form() {
		if(!wp_verify_nonce( $_POST['pta_sus_admin_signup_nonce'], 'pta_sus_admin_signup')) {
			PTA_SUS_Messages::add_error(__('Invalid Referrer', 'pta-volunteer-sign-up-sheets'));
			PTA_SUS_Messages::show_messages(true, 'admin');
			return false;
		}
		$fields = array(
			'user_id',
			'task_id',
			'date',
			'firstname',
			'lastname',
			'email',
			'phone',
			'item',
			'item_qty'
		);
		$signup_id = isset($_POST['signup_id']) ? absint($_POST['signup_id']) : 0;
		$edit = ($signup_id > 0);
		$task_id = isset($_POST['task_id']) ? absint($_POST['task_id']) : 0;
		$date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
		
		// Get task and sheet objects for validation
		$task = pta_sus_get_task($task_id);
		if (!$task) {
			PTA_SUS_Messages::add_error(__('Invalid Task ID', 'pta-volunteer-sign-up-sheets'));
			PTA_SUS_Messages::show_messages(true, 'admin');
			return false;
		}
		$sheet = pta_sus_get_sheet($task->sheet_id);
		if (!$sheet) {
			PTA_SUS_Messages::add_error(__('Invalid Sheet ID', 'pta-volunteer-sign-up-sheets'));
			PTA_SUS_Messages::show_messages(true, 'admin');
			return false;
		}
		
		$posted = array();
		// let extensions modify the posted values
		$form_data = apply_filters('pta_sus_admin_signup_posted_values', $_POST);
		// Make sure required fields are filled out
		$send_mail = isset($form_data['send_email']) && 'yes' === $form_data['send_email'];
		
		// Build posted array with signup_ prefix for validation
		foreach ($fields as $key) {
			if('item_qty' === $key) {
				$qty = isset($form_data['item_qty']) && absint($form_data['item_qty']) > 0 ? absint( $form_data['item_qty']) : 1;
				$posted['signup_item_qty'] = $qty;
			} elseif('user_id' === $key) {
				// user_id is admin-specific, handle separately
				if(isset($form_data[$key])) {
					$posted['signup_'.$key] = absint($form_data[$key]);
				}
			} else {
				// the existing data functions need "signup_" at the front of each key - even though it will get "cleaned"
				if(isset($form_data[$key])) {
					$posted['signup_'.$key] = stripslashes( wp_kses_post( $form_data[$key]));
				}
			}
		}
		
		// Use validation helper class for consistent validation
		// Note: Admin form allows user_id which public form doesn't, but validation will handle other fields
		$error_count = PTA_SUS_Validation::validate_signup_fields($posted, $task, $sheet, $this->main_options);
		if($error_count > 0) {
			PTA_SUS_Messages::show_messages(true, 'admin');
			return false;
		}
		
		if($edit) {
			$result = pta_sus_update_signup( $posted, $signup_id);
		} else {
			$result = pta_sus_add_signup($posted, $task_id);
		}
		if(false === $result) {
			PTA_SUS_Messages::add_error(__('There was an error saving the signup.', 'pta-volunteer-sign-up-sheets'));
			PTA_SUS_Messages::show_messages(true, 'admin');
			return false;
		}
		if(!$edit) {
			$signup_id = $result; // returns insert ID
		}
		// this hook needs to fire before sending emails so extensions have time to process items that may affect email find/replace template tags
		do_action('pta_sus_admin_saved_signup', $signup_id, $task_id, $date);
		if($send_mail) {
			PTA_SUS_Email_Functions::send_mail($signup_id, false, false);
		}
		PTA_SUS_Messages::add_message(__('Signup Saved', 'pta-volunteer-sign-up-sheets'));
		return true;
	}

	/**
	 * Process reschedule/copy form submission
	 * 
	 * Handles the reschedule/copy sheet form submission. Supports three methods:
	 * - 'reschedule': Updates existing sheet with new dates/times
	 * - 'copy': Creates a single copy of the sheet with new dates/times
	 * - 'multi-copy': Creates multiple copies with date intervals
	 * 
	 * @since 1.0.0
	 * @return bool True on success, false on failure
	 * @see PTA_SUS_Sheet_Functions::reschedule_sheet()
	 * @see PTA_SUS_Sheet_Functions::copy_sheet_to_new_dates()
	 * @see PTA_SUS_Sheet_Functions::multi_copy_sheet()
	 */
    private function process_reschedule_form() {
        if(!wp_verify_nonce( $_POST['pta_sus_admin_reschedule_nonce'], 'pta_sus_admin_reschedule')) {
            PTA_SUS_Messages::add_error(__('Invalid Referrer', 'pta-volunteer-sign-up-sheets'));
            PTA_SUS_Messages::show_messages(true, 'admin');
            return false;
        }

        $sheet_id = isset($_POST['sheet_id']) ? absint($_POST['sheet_id']) : 0;
        if($sheet_id < 1) {
            PTA_SUS_Messages::add_error(__('Invalid Sheet ID', 'pta-volunteer-sign-up-sheets'));
            PTA_SUS_Messages::show_messages(true, 'admin');
            return false;
        }
        $sheet = pta_sus_get_sheet($sheet_id);
        if(!$sheet) {
            PTA_SUS_Messages::add_error(__('Invalid Sheet ID', 'pta-volunteer-sign-up-sheets'));
            PTA_SUS_Messages::show_messages(true, 'admin');
            return false;
        }
        $tasks = PTA_SUS_Task_Functions::get_tasks($sheet_id);
        if(empty($tasks)) {
            PTA_SUS_Messages::add_error(__('No Tasks found for that Sheet ID', 'pta-volunteer-sign-up-sheets'));
            PTA_SUS_Messages::show_messages(true, 'admin');
            return false;
        }
        $new_date = '';
        $interval = $copies = 0;
        $new_dates = $new_start_times = $new_end_times = array();
        $method = isset($_POST['method']) && in_array($_POST['method'], array('copy', 'multi-copy','reschedule')) ? $_POST['method'] : false;
        if(!$method) {
            PTA_SUS_Messages::add_error(__('Please select a method', 'pta-volunteer-sign-up-sheets'));
            PTA_SUS_Messages::show_messages(true, 'admin');
            return false;
        }
        if('Single' === $sheet->type && 'multi-copy' !== $method) {
            if(empty($_POST['new_date'])) {
                PTA_SUS_Messages::add_error(__('You Must Select a Date', 'pta-volunteer-sign-up-sheets'));
                PTA_SUS_Messages::show_messages(true, 'admin');
                return false;
            }
            $new_date = pta_datetime('Y-m-d', strtotime(sanitize_text_field($_POST['new_date'])));
        } elseif ('Multi-Day' === $sheet->type && 'multi-copy' !== $method) {
            foreach($tasks as $task) {
                $id = absint($task->id);
                if(empty($_POST['new_task_date'][$id])) {
                    PTA_SUS_Messages::add_error(__('Task Dates are Required!', 'pta-volunteer-sign-up-sheets'));
                    PTA_SUS_Messages::show_messages(true, 'admin');
                    return false;
                }
                $new_dates[$id] = pta_datetime('Y-m-d', strtotime(sanitize_text_field($_POST['new_task_date'][$id])));
            }
        }
        if('multi-copy' === $method) {
            if(empty($_POST['interval']) || empty($_POST['copies'])) {
                PTA_SUS_Messages::add_error(__('Offset Interval and Number of Copies are required', 'pta-volunteer-sign-up-sheets'));
                PTA_SUS_Messages::show_messages(true, 'admin');
                return false;
            }
            if(intval($_POST['interval']) < 1) {
                PTA_SUS_Messages::add_error(__('Offset Interval must be greater than 0', 'pta-volunteer-sign-up-sheets'));
                PTA_SUS_Messages::show_messages(true, 'admin');
                return false;
            }
            if(intval($_POST['copies']) < 1) {
                PTA_SUS_Messages::add_error(__('Number of Copies must be greater than 0', 'pta-volunteer-sign-up-sheets'));
                PTA_SUS_Messages::show_messages(true, 'admin');
                return false;
            }
            $interval = absint($_POST['interval']);
            $copies = absint($_POST['copies']);
        }
        // times & single date
        foreach($tasks as $task) {
            $id = absint($task->id);
            $new_start_times[$id] = !empty($_POST['new_task_start_time'][$id]) ? pta_datetime('h:i A', strtotime($_POST['new_task_start_time'][$id])) : $task->time_start;
            $new_end_times[$id] = !empty($_POST['new_task_end_time'][$id]) ? pta_datetime('h:i A', strtotime($_POST['new_task_end_time'][$id])) : $task->time_end;
            if('Single' === $sheet->type) {
                $new_dates[$id] = $new_date; // add single date to each task for Single sheets
            }
        }

        $clear_signups = isset($_POST['clear_signups']) && 'yes' === $_POST['clear_signups'];
        $send_emails = isset($_POST['send_emails']) && 'yes' === $_POST['send_emails'];
        $copy_signups = !$clear_signups;

        if('reschedule' === $method) {
            // Use helper method to reschedule the sheet
            $result = PTA_SUS_Sheet_Functions::reschedule_sheet($sheet_id, $new_dates, $new_start_times, $new_end_times, $new_date, $clear_signups);
            if ($result) {
                /**
                 * Send emails here before signups are cleared!
                 */
                if($send_emails) {
                    PTA_SUS_Email_Functions::queue_reschedule_emails($tasks);
                }
            }
        } elseif('copy' === $method) {
            $new_sheet_id = PTA_SUS_Sheet_Functions::copy_sheet_to_new_dates($sheet_id, $new_dates, $new_start_times, $new_end_times, $copy_signups);
            /**
             * Maybe Send emails for new sheet
             */
                if(false !== $new_sheet_id && $send_emails && $copy_signups) {
                    $new_tasks = PTA_SUS_Task_Functions::get_tasks($new_sheet_id);
                    if(!empty($new_tasks)) {
                        PTA_SUS_Email_Functions::queue_reschedule_emails($new_tasks);
                    }
                }
        } elseif('multi-copy' === $method) {
            // Use helper method to create multiple copies
            $new_sheet_ids = PTA_SUS_Sheet_Functions::multi_copy_sheet($sheet_id, $tasks, $interval, $copies, $new_start_times, $new_end_times, $copy_signups);
            /**
             * Maybe Send emails for new sheets
             */
                if($send_emails && $copy_signups && !empty($new_sheet_ids)) {
                    foreach($new_sheet_ids as $new_sheet_id) {
                        $new_tasks = PTA_SUS_Task_Functions::get_tasks($new_sheet_id);
                        if(!empty($new_tasks)) {
                            PTA_SUS_Email_Functions::queue_reschedule_emails($new_tasks);
                        }
                    }
                }
        }

        return true;
    }

	/**
	 * Process move signup form submission
	 * 
	 * Handles moving a signup from one task/date to another task/date. Validates
	 * that the new task has available spots before moving.
	 * 
	 * @since 1.0.0
	 * @return int|bool 1 on success, false on failure
	 */
	private function process_move_signup_form() {
        if(!wp_verify_nonce( $_POST['pta_sus_admin_move_nonce'], 'pta_sus_admin_move')) {
            PTA_SUS_Messages::add_error(__('Invalid Referrer', 'pta-volunteer-sign-up-sheets'));
            PTA_SUS_Messages::show_messages(true, 'admin');
            return false;
        }

        $old_signup_id = isset($_POST['old_signup_id']) ? absint($_POST['old_signup_id']) : 0;
        if($old_signup_id < 1) {
            PTA_SUS_Messages::add_error(__('Invalid Signup ID', 'pta-volunteer-sign-up-sheets'));
            PTA_SUS_Messages::show_messages(true, 'admin');
            return false;
        }
		$old_task_id = isset($_POST['old_task_id']) ? absint($_POST['old_task_id']) : 0;
        if($old_task_id < 1) {
            PTA_SUS_Messages::add_error(__('Invalid Task ID', 'pta-volunteer-sign-up-sheets'));
            PTA_SUS_Messages::show_messages(true, 'admin');
            return false;
        }
		$new_task_and_date = isset($_POST['pta_task_id']) ? sanitize_text_field($_POST['pta_task_id']) : false;
		if($new_task_and_date) {
			$values = explode('|', $new_task_and_date);
			$new_task_id = isset($values[0]) ? absint($values[0]) : 0;
			$new_date = isset($values[1]) ? sanitize_text_field($values[1]) : false;
		} else {
			$new_task_id = $new_date = false;
		}
		if(!$new_task_id || !$new_date) {
			PTA_SUS_Messages::add_error(__('Invalid New Task ID or Date', 'pta-volunteer-sign-up-sheets'));
            PTA_SUS_Messages::show_messages(true, 'admin');
            return false;
		}
		// Verify available qty
		$new_task = pta_sus_get_task($new_task_id);
		if(!$new_task) {
			PTA_SUS_Messages::add_error(__('Invalid Task', 'pta-volunteer-sign-up-sheets'));
            PTA_SUS_Messages::show_messages(true, 'admin');
            return false;
		}
        $available = $new_task->get_available_spots($new_date);
		$qty = isset($_POST['signup_qty']) ? absint($_POST['signup_qty']) : 1;
		if(!$available || $qty > $available) {
			PTA_SUS_Messages::add_error(__('Not Enough Open Slots', 'pta-volunteer-sign-up-sheets'));
            PTA_SUS_Messages::show_messages(true, 'admin');
            return false;
		}

		// All good, just update signup with new task ID and Date
		$signup = pta_sus_get_signup($old_signup_id);
		if ($signup) {
			$signup->task_id = $new_task_id;
			$signup->date = $new_date;
			$result = $signup->save();
			return ($result !== false) ? 1 : false;
		}
		return false;
    }

	/**
	 * Redirect after sheet page action
	 * 
	 * Stores messages in cookies and redirects to the sheets list page after
	 * processing an action (trash, delete, copy, etc.). This allows messages to
	 * persist across the redirect.
	 * 
	 * @since 1.0.0
	 * @return void Exits after redirect
	 */
	private function admin_sheet_page_redirect() {
		// Store current messages in cookies
		setcookie(
			'pta_sus_messages',
			json_encode(PTA_SUS_Messages::get_messages()),
			time() + 300,
			COOKIEPATH,
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);

		setcookie(
			'pta_sus_errors',
			json_encode(PTA_SUS_Messages::get_errors()),
			time() + 300,
			COOKIEPATH,
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);
		$redirect_url = remove_query_arg( ['action', 'sheet_id','_sus_nonce'] );
		wp_redirect( esc_url_raw( $redirect_url ) );
		exit; // Always exit after a redirect.
	}

	/**
	 * Process list table actions
	 * 
	 * Handles all actions from the sheets list table including: clear signup,
	 * trash/untrash sheet, delete sheet, copy sheet, toggle visibility, and form
	 * submissions (signup, reschedule, move). Validates nonces and processes
	 * each action appropriately.
	 * 
	 * @since 1.0.0
	 * @return void
	 * @hook pta_sus_admin_clear_signup Action fired when signup is cleared
	 * @hook pta_sus_sheet_before_deleted Action fired before sheet is deleted
	 * @hook pta_sus_sheet_deleted Action fired after sheet is deleted
	 */
	private function maybe_process_list_table_actions() {
		$page = $_REQUEST['page'] ?? '';
			if ( ! ( 'pta-sus-settings_sheets' === $page ) ) {
			return; // Exit if not on the correct page.
		}
		// SECURITY CHECK
		// Checks nonces for ALL actions
		if (isset($_GET['action'])) {
			check_admin_referer( $_GET['action'], '_sus_nonce');
		} else {
			return; // no action
		}
		// Get any messages saved in cookies if there was a redirect
		pta_get_messages_from_cookie();
		$this->action = sanitize_text_field($_GET['action']);

		// Clear signup
		if ('clear' === $this->action ) {
			if($this->email_options['admin_clear_emails']) {
				PTA_SUS_Email_Functions::send_mail($_GET['signup_id'], false, true);
			}
			// make sure there is a signup record first, and get the data before deleting in case extensions need it
			$signup = pta_sus_get_signup($_GET['signup_id']);
			if(!empty($signup)) {
				// Store signup data for action hook (before deletion)
				$signup_data = $signup->to_array();
				if (($result = pta_sus_delete_signup($_GET['signup_id'])) === false) {
					PTA_SUS_Messages::add_error(sprintf( __('Error clearing spot (ID # %s)', 'pta-volunteer-sign-up-sheets'), esc_attr($_GET['signup_id']) ));
				} else {
					if ($result > 0) PTA_SUS_Messages::add_message(__('Spot has been cleared.', 'pta-volunteer-sign-up-sheets'));
					// Convert array back to object for backward compatibility with action hook
					$signup_obj = (object) $signup_data;
					do_action('pta_sus_admin_clear_signup', $signup_obj);
				}
			}
		}

		// Add/Edit Signup form submitted
		$this->success = false;
		if(isset($_POST['pta_admin_signup_form_mode']) && 'submitted' === $_POST['pta_admin_signup_form_mode']) {
			$this->success = $this->process_signup_form();
		}

		if(isset($_POST['pta_admin_reschedule_form_mode']) && 'submitted' === $_POST['pta_admin_reschedule_form_mode']) {
			$this->success = $this->process_reschedule_form();
		}

		if(isset($_POST['pta_admin_move_form_mode']) && 'submitted' === $_POST['pta_admin_move_form_mode']) {
			$this->success = $this->process_move_signup_form();
		}

		$sheet_id = isset($_GET['sheet_id']) ? (int)$_GET['sheet_id'] : 0;

		if ('untrash' === $this->action && $sheet_id > 0) {
			$sheet = pta_sus_get_sheet($sheet_id);
			if ($sheet) {
				$sheet->trash = false;
				$result = $sheet->save();
				if ($result === false) {
					PTA_SUS_Messages::add_error(__('Error restoring sheet.', 'pta-volunteer-sign-up-sheets'));
				} else {
					PTA_SUS_Messages::add_message(__('Sheet has been restored.', 'pta-volunteer-sign-up-sheets'));
				}
			} else {
				PTA_SUS_Messages::add_error(__('Error restoring sheet.', 'pta-volunteer-sign-up-sheets'));
			}
			$this->admin_sheet_page_redirect();
		}
		if ('trash' === $this->action && $sheet_id > 0) {
			$sheet = pta_sus_get_sheet($sheet_id);
			if ($sheet) {
				$sheet->trash = true;
				$result = $sheet->save();
				if ($result === false) {
					PTA_SUS_Messages::add_error(__('Error moving sheet to trash.', 'pta-volunteer-sign-up-sheets'));
				} else {
					PTA_SUS_Messages::add_message(__('Sheet has been moved to trash.', 'pta-volunteer-sign-up-sheets'));
				}
			} else {
				PTA_SUS_Messages::add_error(__('Error moving sheet to trash.', 'pta-volunteer-sign-up-sheets'));
			}
			$this->admin_sheet_page_redirect();
		}
		if ('delete' === $this->action && $sheet_id > 0) {
			do_action('pta_sus_sheet_before_deleted', $sheet_id);
			if (($result = PTA_SUS_Sheet_Functions::delete_sheet($sheet_id)) === false) {
				PTA_SUS_Messages::add_error(__('Error permanently deleting sheet.', 'pta-volunteer-sign-up-sheets'));
			} else {
				PTA_SUS_Messages::add_message(__('Sheet has been permanently deleted.', 'pta-volunteer-sign-up-sheets'));
				do_action('pta_sus_sheet_deleted', $sheet_id);
			}
			$this->admin_sheet_page_redirect();
		}
		if ('copy' === $this->action && $sheet_id > 0) {
			if (($new_id = PTA_SUS_Sheet_Functions::copy_sheet($sheet_id)) === false) {
				PTA_SUS_Messages::add_error(__('Error copying sheet.', 'pta-volunteer-sign-up-sheets'));
			} else {
				PTA_SUS_Messages::add_message(__('Sheet has been copied to new sheet ID #', 'pta-volunteer-sign-up-sheets').$new_id.' (<a href="?page='.$this->admin_settings_slug.'_modify_sheet&amp;action=edit_sheet&amp;sheet_id='.$new_id.'">'.__('Edit', 'pta-volunteer-sign-up-sheets').'</a>).');
			}
			$this->admin_sheet_page_redirect();
		}
		if ('toggle_visibility' === $this->action && $sheet_id > 0) {

            $sheet = pta_sus_get_sheet($sheet_id);
            if ($sheet && false === $sheet->toggle_visibility()) {
                PTA_SUS_Messages::add_error(__('Error toggling sheet visibility.', 'pta-volunteer-sign-up-sheets'));
            }

			$this->admin_sheet_page_redirect();
		}


	}

	/**
	 * Admin page: Sheets list and details
	 * 
	 * Main admin page for managing sheets. Displays either:
	 * - List of all sheets (with search, filter, pagination)
	 * - Single sheet details with signups
	 * - Edit signup form
	 * - Reschedule/copy sheet form
	 * - Move signup form
	 * - View all signups (export view)
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_sheet_page() {
		if (!current_user_can('manage_options') && !current_user_can('manage_signup_sheets'))  {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'pta-volunteer-sign-up-sheets' ) );
		}

		echo '<div class="wrap pta_sus">';

		// Edit Signup
		if ('edit_signup' === $this->action && !$this->success) {
			include(PTA_VOLUNTEER_SUS_DIR.'views/admin-add-edit-signup-form.php');
			echo '</div>';
			return;
		}

		// Set Actions - but not if search or filter submitted (so don't toggle again, for example)
		$filter = isset($_REQUEST['pta-filter-submit']) && (isset($_REQUEST['pta-visible-filter']) || isset($_REQUEST['pta-type-filter']));
		$search = isset($_REQUEST['s']) && '' !== $_REQUEST['s'];
		if($filter || $search) {
			$view_signups = $edit = false;
		} else {
			$edit = (!empty($_GET['sheet_id']) && !in_array($this->action, array('trash', 'untrash', 'delete', 'copy', 'toggle_visibility', 'view_all')));
			$view_signups = !empty($_GET['sheet_id']) && $this->action === 'view_signup';
		}

		$view_all_url = add_query_arg(array('page' => 'pta-sus-settings_sheets','action' => 'view_all', 'sheet_id' => false));
		$nonced_view_all_url = wp_nonce_url($view_all_url, 'view_all', '_sus_nonce');

		if(!in_array($this->action, array('view_all','reschedule','move'))) {
			echo ($edit || $view_signups) ? '<h2>'.__('Sheet Details', 'pta-volunteer-sign-up-sheets').'</h2>' : '<h2>'.__('Sign-up Sheets ', 'pta-volunteer-sign-up-sheets').'
			<a href="?page='.$this->admin_settings_slug.'_modify_sheet" class="add-new-h2">'.__('Add New', 'pta-volunteer-sign-up-sheets').'</a>
			<a href="'.esc_url($nonced_view_all_url).'" class="button-primary">'.__('View/Export ALL Data', 'pta-volunteer-sign-up-sheets').'</a>
			</h2>
			';
		}

		$sheet_id = isset($_GET['sheet_id']) ? intval($_GET['sheet_id']) : 0;

		if ('view_all' === $this->action) {
            echo '<h2><span id="sheet_title">'.__('All Signup Data', 'pta-volunteer-sign-up-sheets').'</span></h2>';
			PTA_SUS_Messages::show_messages(true, 'admin');
            include('admin-view-all-signups-html.php');
			echo '</div>';
            return;
        } elseif ('reschedule' === $this->action) {
            if (!($sheet = pta_sus_get_sheet($sheet_id))) {
                PTA_SUS_Messages::add_error(__('No sign-up sheet found.', 'pta-volunteer-sign-up-sheets'));
                PTA_SUS_Messages::show_messages(true, 'admin');
                echo '</div>';
                return;
            }
            $tasks = PTA_SUS_Task_Functions::get_tasks($sheet_id);
            if (empty($tasks)) {
                PTA_SUS_Messages::add_error(__('No tasks were found.', 'pta-volunteer-sign-up-sheets'));
                PTA_SUS_Messages::show_messages(true, 'admin');
                echo '</div>';
                return;
            }

            echo '<h2><span id="sheet_title">'.sprintf(__('Reschedule/Copy Sheet: %s', 'pta-volunteer-sign-up-sheets'), esc_html($sheet->title)).'</span></h2>';
            include('admin-reschedule-html.php');

            echo '</div>';
			return;
		} elseif ('move_signup' === $this->action) {
            if (!($sheet = pta_sus_get_sheet($sheet_id))) {
                PTA_SUS_Messages::add_error(__('No sign-up sheet found.', 'pta-volunteer-sign-up-sheets'));
                PTA_SUS_Messages::show_messages(true, 'admin');
                echo '</div>';
                return;
            }
			$signup_id = isset($_REQUEST['signup_id']) ? absint($_REQUEST['signup_id']) : 0;
			if (!($signup = pta_sus_get_signup($signup_id))) {
                PTA_SUS_Messages::add_error(__('No sign-up found.', 'pta-volunteer-sign-up-sheets'));
                PTA_SUS_Messages::show_messages(true, 'admin');
                echo '</div>';
                return;
            }
            $task = pta_sus_get_task($signup->task_id);
            if (empty($task)) {
                PTA_SUS_Messages::add_error(__('No task found.', 'pta-volunteer-sign-up-sheets'));
                PTA_SUS_Messages::show_messages(true, 'admin');
                echo '</div>';
                return;
            }

            echo '<h2><span id="sheet_title">'.sprintf(__('Move Signup for: %s', 'pta-volunteer-sign-up-sheets'), esc_html($signup->firstname . ' ' . $signup->lastname . ' - ' . $task->title)).'</span></h2>';
            include('admin-move-html.php');
            echo '</div>';
			return;
		} elseif ($edit || $view_signups) {
			// View Single Sheet
			if (!($sheet = pta_sus_get_sheet($sheet_id))) {
				PTA_SUS_Messages::add_error(__('No sign-up sheet found.', 'pta-volunteer-sign-up-sheets'));
				PTA_SUS_Messages::show_messages(true, 'admin');
			} else {
				echo '
					<h2><span id="sheet_title">'.esc_html($sheet->title).'</span></h2>
					<h4>'.__('Event Type: ', 'pta-volunteer-sign-up-sheets').esc_html($sheet->type).'</h4>                
					<h3>'.__('Signups', 'pta-volunteer-sign-up-sheets').'</h3>
					';

				// Tasks
				$tasks = PTA_SUS_Task_Functions::get_tasks($sheet_id);
				if (empty($tasks)) {
					PTA_SUS_Messages::add_error(__('No tasks were found.', 'pta-volunteer-sign-up-sheets'));
					PTA_SUS_Messages::show_messages(true, 'admin');
				} else {
					PTA_SUS_Messages::show_messages(true, 'admin');
					include('admin-view-signups-html.php');
				}

			}
			echo '</div>';
			return;
		}

		// Show any action messages
		PTA_SUS_Messages::show_messages(true, 'admin');
		//View All
		$show_trash = isset($_REQUEST['sheet_status']) && $_REQUEST['sheet_status'] == 'trash';
		$show_all = !$show_trash;

		// List Table functions need to be inside of form
		echo'<form id="pta-sus-list-table-form" method="post">';
		// Make sure we have a list table - should have been set already
		if(!is_object($this->table)) {
			$this->table = new PTA_SUS_List_Table();
		}

		// Get and prepare data
		$this->table->set_show_trash($show_trash);
		$this->table->prepare_items();

		// Moved this below above 2 lines so counts update properly when doing bulk actions (bulk actions called inside of prepare_items function)
		echo '
			<ul class="subsubsub">
			<li class="all"><a href="admin.php?page='.$this->admin_settings_slug.'_sheets"'.(($show_all) ? ' class="current"' : '').'>'.__('All ', 'pta-volunteer-sign-up-sheets').'<span class="count">('.PTA_SUS_Sheet_Functions::get_sheet_count().')</span></a> |</li>
			<li class="trash"><a href="admin.php?page='.$this->admin_settings_slug.'_sheets&amp;sheet_status=trash"'.(($show_trash) ? ' class="current"' : '').'>'.__('Trash ', 'pta-volunteer-sign-up-sheets').'<span class="count">('.PTA_SUS_Sheet_Functions::get_sheet_count(true).')</span></a></li>
			</ul>
			';

		// Display List Table
		$this->table->search_box( 'Search', 'sheet_search');
		$this->table->display();


		echo '</form><!-- #sheet-filter -->';
		echo '</div><!-- .wrap -->';

	}

	/**
	 * Admin page: Add/Edit Sheet and Tasks
	 * 
	 * Handles the add/edit sheet page which includes:
	 * - Sheet form (title, type, settings, contact info, details)
	 * - Tasks form (multiple tasks with dates, times, quantities)
	 * - Task moving between sheets
	 * 
	 * Processes form submissions, validates data, saves sheets and tasks,
	 * and manages the multi-step workflow (sheet first, then tasks).
	 * 
	 * @since 1.0.0
	 * @return void
	 * @hook pta_sus_admin_process_tasks_start Action fired before processing tasks
	 * @hook pta_sus_posted_task_values Filter to modify posted task values
	 * @hook pta_sus_add_task Action fired when task is added
	 * @hook pta_sus_update_task Action fired when task is updated
	 * @hook pta_sus_delete_task Action fired when task is deleted
	 * @hook pta_sus_admin_process_tasks_end Action fired after processing tasks
	 * @hook pta_sus_admin_process_sheet_start Action fired before processing sheet
	 * @hook pta_sus_validate_sheet_post Filter to validate sheet data
	 * @hook pta_sus_check_duplicate_sheets Filter to allow/deny duplicate sheets
	 * @hook pta_sus_admin_process_sheet_end Action fired after processing sheet
	 * @hook pta_sus_sheet_form_sheet_types Filter to modify available sheet types
	 * @hook pta_sus_sheet_form_after_title Action to add fields after title
	 * @hook pta_sus_sheet_form_after_event_type Action to add fields after event type
	 * @hook pta_sus_sheet_form_after_visible Action to add fields after visible checkbox
	 * @hook pta_sus_sheet_form_before_contact_info Action to add fields before contact info
	 * @hook pta_sus_sheet_form_after_contact_info Action to add fields after contact info
	 * @hook pta_sus_sheet_form_after_sheet_details Action to add fields after sheet details
	 * @hook pta_sus_admin_get_fields Filter to modify fields retrieved from database
	 */
	public function admin_modify_sheet_page() {
		if (!current_user_can('manage_options') && !current_user_can('manage_signup_sheets'))  {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'pta-volunteer-sign-up-sheets' ) );
		}

		// Set mode vars
		$edit = ! empty( $_GET['sheet_id'] );
		$add = ! $edit;
		$sheet_submitted = (isset($_POST['sheet_mode']) && $_POST['sheet_mode'] === 'submitted');
		$tasks_submitted = (isset($_POST['tasks_mode']) && $_POST['tasks_mode'] === 'submitted');
		$tasks_move = (isset($_POST['tasks_mode']) && $_POST['tasks_mode'] === 'move_tasks');
		$edit_tasks = (isset($_GET['action']) && 'edit_tasks' === $_GET['action']);
		$edit_sheet = (isset($_GET['action']) && 'edit_sheet' === $_GET['action']);
		$sheet_success = false;
		$tasks_success = false;
		$add_tasks = false;
		$moved = false;
		$sheet_fields = array();
		$new_sheet_id = 0;

        if ($tasks_move) {
            // Nonce check
            check_admin_referer('pta_sus_move_tasks', 'pta_sus_move_tasks_nonce');

            $sheet_id = (int)$_POST['sheet_id'];
            $new_sheet_id = (int)$_POST['new_sheet_id'];

            if ($new_sheet_id < 1) {
                PTA_SUS_Messages::add_error(__('You must select a sheet to move the tasks to!', 'pta-volunteer-sign-up-sheets'));
            } else {
                $move_results = PTA_SUS_Task_Functions::move_tasks($sheet_id, $new_sheet_id);

                if ($move_results > 0) {
                    PTA_SUS_Messages::add_message(sprintf(_n('%d task successfully moved!', '%d tasks successfully moved!', $move_results, 'pta-volunteer-sign-up-sheets'), $move_results));
                    PTA_SUS_Messages::add_error(__('For changes to show, and for new task dates to be updated, please adjust tasks as needed and hit save.', 'pta-volunteer-sign-up-sheets'));
                    $moved = true;
                }
                // Errors are already added by move_tasks() if it fails
            }
        } elseif ($tasks_submitted) {
			// Tasks
			// Nonce check
			check_admin_referer( 'pta_sus_add_tasks', 'pta_sus_add_tasks_nonce' );

			$sheet_success = true;
			$tasks_success = false;
			$sheet_id = (int)$_POST['sheet_id'];
			$no_signups = absint($_POST['sheet_no_signups']);
			$tasks = PTA_SUS_Task_Functions::get_tasks($sheet_id);
			$tasks_to_delete = array();
			$tasks_to_update = array();
			$task_err = 0;
			$keys_to_process = array();
			$count = 0;
			$dates = array();
			$old_dates = PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet($sheet_id);

			do_action( 'pta_sus_admin_process_tasks_start', $sheet_id, $tasks, $old_dates );

			// Get keys for any task line items on screen when posted (even if empty)
			foreach ($_POST['task_title'] AS $key=>$value) {
				$keys_to_process[] = $key;
				$count++;
			}

			// Check if dates were entered for Single or Recurring Events
			if( "Single" === $_POST['sheet_type'] ) {
				if(empty($_POST['single_date'])) {
					$task_err++;
					PTA_SUS_Messages::add_error(__('You must enter a date!', 'pta-volunteer-sign-up-sheets'));
				} elseif (false === pta_sus_check_date($_POST['single_date'])) {
					$task_err++;
					PTA_SUS_Messages::add_error(__('Invalid date!', 'pta-volunteer-sign-up-sheets'));
				} else {
					$dates[] = $_POST['single_date'];
				}
			} elseif ( "Recurring" === $_POST['sheet_type'] ) {
				if(empty($_POST['recurring_dates'])) {
					$task_err++;
					PTA_SUS_Messages::add_error(__('You must enter at least two dates for a Recurring event!', 'pta-volunteer-sign-up-sheets'));
				} else {
					$dates = pta_sus_sanitize_dates($_POST['recurring_dates']);
					if (count($dates) < 2) {
						$task_err++;
						PTA_SUS_Messages::add_error(__('Invalid dates!  Enter at least 2 valid dates.', 'pta-volunteer-sign-up-sheets'));
					}
				}
			} elseif ( "Ongoing" === $_POST['sheet_type'] ) {
				$dates[] = "0000-00-00";
			}


			// created a posted_tasks array of fields we want to validate
			$posted_tasks = array();
			foreach ($keys_to_process as $index => $key) {
				$posted_tasks[] = apply_filters( 'pta_sus_posted_task_values', array(
					'task_sheet_id'          => $_POST['sheet_id'],
					'task_title'             => $_POST['task_title'][ $key ],
					'task_description'       => $_POST['task_description'][ $key ],
					'task_dates'             => ( isset( $_POST['task_dates'][ $key ] ) ) ? $_POST['task_dates'][ $key ] : '',
					'task_time_start'        => $_POST['task_time_start'][ $key ],
					'task_time_end'          => $_POST['task_time_end'][ $key ],
					'task_qty'               => isset( $_POST['task_qty'][ $key ] ) ? $_POST['task_qty'][ $key ] : 1,
					'task_need_details'      => isset( $_POST['task_need_details'][ $key ] ) ? "YES" : "NO",
					'task_details_required'  => isset( $_POST['task_details_required'][ $key ] ) ? "YES" : "NO",
					'task_allow_duplicates'  => isset( $_POST['task_allow_duplicates'][ $key ] ) ? "YES" : "NO",
					'task_enable_quantities' => isset( $_POST['task_enable_quantities'][ $key ] ) ? "YES" : "NO",
					'task_details_text'      => isset( $_POST['task_details_text'][ $key ] ) ? $_POST['task_details_text'][ $key ] : '',
					'task_id'                => ( isset( $_POST['task_id'][ $key ] ) && 0 != $_POST['task_id'][ $key ] ) ? (int) $_POST['task_id'][ $key ] : - 1,
				), $key );
			}

			foreach ($posted_tasks as $task) {
				// Clean and validate each posted task
				$clean_task_fields = pta_sus_clean_prefixed_array($task, 'task_');
				$results = PTA_SUS_Task_Functions::validate_task_fields($clean_task_fields);
				if(!empty($results['errors'])) {
					$task_err++;
					// Messages are already added by validation method, but check for any from filter hook
					if (!empty($results['message'])) {
						PTA_SUS_Messages::add_error($results['message']);
					}
				} elseif ("Multi-Day" === $_POST['sheet_type'] && -1 != $task['task_id']) {
					// Make sure a date was entered
					if(empty($task['task_dates'])) {
						$task_err++;
						PTA_SUS_Messages::add_error(__('Task date is a required field', 'pta-volunteer-sign-up-sheets'));
					}
					// If the date changed, check for signups on the old date
					$old_task = pta_sus_get_task($task['task_id']);
					if ($old_task && $old_task->dates !== $task['task_dates']) {
						// Date has changed - check if there were signups
						$signups = PTA_SUS_Signup_Functions::get_signups_for_task($old_task->id, $old_task->dates);
						$signup_count = count($signups);
						if ($signup_count > 0) {
							$task_err++;
							$people = _n('person', 'people', $signup_count, 'pta-volunteer-sign-up-sheets');
							PTA_SUS_Messages::add_error(sprintf(__('The task "%1$s" cannot be changed to a new date because it has %2$d %3$s signed up.  Please clear all spots first before changing this task date.', 'pta-volunteer-sign-up-sheets'), esc_html($old_task->title), (int)$signup_count, $people) );
						} else {
							$dates[] = $task['task_dates']; // build our array of valid dates
						}
					}
				}
			}


			if (0 === $task_err && !empty($dates) && !empty($old_dates) && "Multi-Day" != $_POST['sheet_type']) {
				// This works for Single & Recurring Event Types, but can be fooled by certain edits on Multi-Day Events
				// Compare the posted $dates with the $old_dates and figure out which task dates to add or remove
				// Skip multi-day events, since we took care of them above

				sort($dates);
				sort($old_dates);
				// sort them and then see if they are different
				if ($dates !== $old_dates) {
					// Adding new dates is fine, we just need to get an array of removed dates that we can use
					// to see if anybody signed up for those dates.  If so, we'll just create an error here that
					// will prevent continuing
					$signups = false;
					$removed_dates = array_diff($old_dates, $dates);
					// Since this only happens if we edit existing tasks/dates, and not for brand new sheet/tasks
					// we can just use the existing tasks from the database that we already put in $tasks
					if(!empty($removed_dates)) {
						foreach ($removed_dates as $removed_date) {
							foreach ($tasks as $task) {
								if(count(PTA_SUS_Signup_Functions::get_signups_for_task($task->id, $removed_date)) > 0) {
									$signups = true;
									break 2; // break out of both foreach loops
								}
							}
						}
						if($signups) {
							$task_err++;
							PTA_SUS_Messages::add_error(__('You are trying to remove '._n('a date', 'dates', count($removed_dates), 'pta-volunteer-sign-up-sheets').' that people have already signed up for!<br/>
									Please clear those signups first if you wish to remove '._n('that date', 'those dates', count($removed_dates), 'pta-volunteer-sign-up-sheets'), 'pta-volunteer-sign-up-sheets').'<br/>'.
								__('Please check '._n('this date', 'these dates', count($removed_dates), 'pta-volunteer-sign-up-sheets' ).' for existing signups:', 'pta-volunteer-sign-up-sheets').'<br/>'.esc_html(implode(', ', $removed_dates)));
						}
					}
				}
			}
			
			if( 0 === $task_err ) {
				$skip_signups_check = isset($this->main_options['skip_signups_check']) && true == $this->main_options['skip_signups_check'];

				// Queue for removal: tasks where the fields were emptied out
				for ($i = 0; $i < $count; $i++) {
					if (empty($_POST['task_title'][$i])) {
						if (!empty($_POST['task_id'][$i])) {
							$tasks_to_delete[] = $_POST['task_id'][$i];
						}
					} else {
						$tasks_to_update[] = (int)$_POST['task_id'][$i];

						if(!$skip_signups_check) {
							if("Single" === $_POST['sheet_type'] || "Recurring" === $_POST['sheet_type'] || "Ongoing" === $_POST['sheet_type']) {
								$check_dates = $dates;
							} else {
								$check_dates = pta_sus_sanitize_dates($_POST['task_dates'][$i]);
							}
							foreach ($check_dates as $key => $cdate) {
								$signup_count = count(PTA_SUS_Signup_Functions::get_signups_for_task((int)$_POST['task_id'][$i], $cdate));
								if ($signup_count > 0 && isset($_POST['task_qty']) && $signup_count > $_POST['task_qty'][$i]) {
									$task_err++;
									$people = _n('person', 'people', $signup_count, 'pta-volunteer-sign-up-sheets');
									PTA_SUS_Messages::add_error(sprintf(__('The number of spots for task "%1$s" cannot be set below %2$d because it currently has %2$d %3$s signed up.  Please clear some spots first before updating this task.', 'pta-volunteer-sign-up-sheets'), esc_attr($_POST['task_title'][$i]), (int)$signup_count, $people));
								}
							}
						}
					}
				}

				if( 0 === count($tasks_to_update) ) {
					$task_err++;
					PTA_SUS_Messages::add_error(__('You must enter at least one task!', 'pta-volunteer-sign-up-sheets'));
				}
				// Queue for removal: tasks that are no longer in the list
				foreach ($tasks AS $task) {
					if (!in_array($task->id, $_POST['task_id'])) {
						$tasks_to_delete[] = $task->id;
					}
				}

				if(!$skip_signups_check) {
					foreach ($tasks_to_delete as $task_id) {
						$signup_count = count(PTA_SUS_Signup_Functions::get_signups_for_task($task_id));
						if ($signup_count > 0) {
							$task_err++;
							$task = pta_sus_get_task($task_id);
							$people = _n('person', 'people', $signup_count, 'pta-volunteer-sign-up-sheets');
							PTA_SUS_Messages::add_error(sprintf(__('The task "%1$s" cannot be removed because it has %2$d %3$s signed up.  Please clear all spots first before removing this task.', 'pta-volunteer-sign-up-sheets'), esc_html($task->title), (int)$signup_count, $people));
						}
					}
				}

				if (empty($task_err)) {
					$i = 0;
					foreach ($keys_to_process AS $key) {                        
						if (!empty($_POST['task_title'][$key])) {
							// Use new Task class to get properties (includes extension-added fields via filters)
							$task = new PTA_SUS_Task();
							$task_properties = $task->get_properties();
							foreach ($task_properties AS $field=>$nothing) {
								if ( 'need_details' === $field && !isset($_POST['task_'.$field][$key]) ) {
									$task_data['task_'.$field] = 'NO';
								}
								if ( 'details_required' === $field && !isset($_POST['task_'.$field][$key]) ) {
									$task_data['task_'.$field] = 'NO';
								}
								if ( 'allow_duplicates' === $field && !isset($_POST['task_'.$field][$key]) ) {
									$task_data['task_'.$field] = 'NO';
								}
								if ( 'enable_quantities' === $field && !isset($_POST['task_'.$field][$key]) ) {
									$task_data['task_'.$field] = 'NO';
								}
								if (isset($_POST['task_'.$field][$key])) {
									$task_data['task_'.$field] = $_POST['task_'.$field][$key];
								}
							}
							$task_data['task_position'] = $i;
							if ( "Single" === $_POST['sheet_type'] || "Ongoing" === $_POST['sheet_type'] ) {
								$task_data['task_dates'] = $dates[0];
							} elseif ( "Recurring" === $_POST['sheet_type'] ) {
								$task_data['task_dates'] = implode(",", $dates);
							} elseif ( "Multi-Day" === $_POST['sheet_type'] ) {
								$dates[] = $_POST['task_dates'][$key];
							}
							$task_data['task_sheet_id'] = $sheet_id;
							if (empty($_POST['task_id'][$key])) {
								$task_id = pta_sus_add_task($task_data, $sheet_id, $no_signups);
								if ($task_id === false) {
									$task_err++;
								} else {
									do_action('pta_sus_add_task', $task_data, $sheet_id, $task_id, $key);
								}
							} else {
								if (($result = pta_sus_update_task($task_data, $_POST['task_id'][$key], $no_signups)) === false) {
									$task_err++;
								} else {
									do_action('pta_sus_update_task', $task_data, $sheet_id, $_POST['task_id'][$key], $key);
								}
							}
						}
						$i++;
					}

					if (!empty($task_err)) {
						PTA_SUS_Messages::add_error(sprintf(__('Error saving %d '. _n('task.', 'tasks.', $task_err), 'pta-volunteer-sign-up-sheets'), (int)$task_err));
					} else {
						// Tasks updated successfully

						// Update sheet with first and last dates
						// Update sheet with first and last dates if needed
						$sheet = pta_sus_get_sheet((int)$_POST['sheet_id']);
						if ($sheet) {
							$needs_update = false;
							sort($dates);
							$min_date = min($dates);
							$max_date = max($dates);
							
							if ($sheet->first_date != $min_date) {
								$sheet->first_date = $min_date;
								$needs_update = true;
							}
							if ($sheet->last_date != $max_date) {
								$sheet->last_date = $max_date;
								$needs_update = true;
							}
							if ($needs_update) {
								$result = $sheet->save();
								if(false === $result) {
									$task_err++;
									PTA_SUS_Messages::add_error(__('Error updating sheet.', 'pta-volunteer-sign-up-sheets'));
								}
							}
						}
						if(empty($task_err)) {
							$tasks_success = true;
							$sheet_fields['sheet_id'] = $_POST['sheet_id'];
						}
					}
					
					// Delete unused tasks
					foreach ($tasks_to_delete AS $task_id) {
						$task = pta_sus_get_task($task_id);
						if ($task) {
							$result = $task->delete();
							if ($result === false) {
								PTA_SUS_Messages::add_error(__('Error removing a task.', 'pta-volunteer-sign-up-sheets'));
							} else {
								// Action hook is automatically fired by the class delete() method
								do_action('pta_sus_delete_task', $task_id);
							}
						} else {
							PTA_SUS_Messages::add_error(__('Error removing a task.', 'pta-volunteer-sign-up-sheets'));
						}
					}
				}
			}

			do_action( 'pta_sus_admin_process_tasks_end', $sheet_id );    

		} elseif($sheet_submitted) {
			// Nonce check
			check_admin_referer( 'pta_sus_add_sheet', 'pta_sus_add_sheet_nonce' );

			do_action( 'pta_sus_admin_process_sheet_start' );
			$sheet_err = 0;

			// Validate the posted fields
			if ((isset($_POST['sheet_position']) && '' != $_POST['sheet_position'] ) && !empty($_POST['sheet_chair_name'])) {
				$sheet_err++;
				PTA_SUS_Messages::add_error(__('Please select a Position OR manually enter Chair contact info. NOT Both!', 'pta-volunteer-sign-up-sheets'));
			} elseif ( (!empty($_POST['sheet_chair_name']) && empty($_POST['sheet_chair_email'])) || (empty($_POST['sheet_chair_name']) && !empty($_POST['sheet_chair_email']))) {
				$sheet_err++;
				PTA_SUS_Messages::add_error(__('Please enter Chair Name(s) AND Email(s)!', 'pta-volunteer-sign-up-sheets'));
			} elseif ((isset($_POST['sheet_position']) && '' == $_POST['sheet_position']) && (empty($_POST['sheet_chair_name']) || empty($_POST['sheet_chair_email']))) {
				$sheet_err++;
				PTA_SUS_Messages::add_error(__('Please either select a position or type in the chair contact info!', 'pta-volunteer-sign-up-sheets'));
			}
			// Clean and validate sheet fields
			$clean_sheet_fields = pta_sus_clean_prefixed_array($_POST, 'sheet_');
			$results = PTA_SUS_Sheet_Functions::validate_sheet_fields($clean_sheet_fields);
			// Give extensions a chance to validate any custom fields
			$results = apply_filters( 'pta_sus_validate_sheet_post', $results );
			if(!empty($results['errors'])) {
				// Messages are already added by validation method, but check for any from filter hook
				if (!empty($results['message'])) {
					PTA_SUS_Messages::add_error($results['message']);
				}
			} elseif (!$sheet_err) {
				// Passed Validation
				$sheet_fields = $_POST;
				$duplicates = PTA_SUS_Sheet_Functions::check_duplicate_sheet( $sheet_fields['sheet_title'] );
				// Some extensions may want to allow duplicates
				$duplicates = apply_filters( 'pta_sus_check_duplicate_sheets', $duplicates, $sheet_fields );
				// Make sure our sheet_visible gets set correctly
				if (isset($sheet_fields['sheet_visible']) && '1' == $sheet_fields['sheet_visible']) {
					$sheet_fields['sheet_visible'] = true;
				} else {
					$sheet_fields['sheet_visible'] = false;
				}
				// Make sure our sheet_clear gets set correctly
				if (isset($sheet_fields['sheet_clear']) && '1' == $sheet_fields['sheet_clear']) {
					$sheet_fields['sheet_clear'] = true;
				} else {
					$sheet_fields['sheet_clear'] = false;
				}
				// check new sheet_clear_type gets set correctly
				if (isset($sheet_fields['sheet_clear_type']) && in_array($sheet_fields['sheet_clear_type'], array('days','hours'))) {
					$sheet_fields['sheet_clear_type'] = sanitize_text_field($sheet_fields['sheet_clear_type']);
				} else {
					$sheet_fields['sheet_clear_type'] = 'days';
				}
				// Make sure our no_signups gets set correctly
				if (isset($sheet_fields['sheet_no_signups']) && '1' == $sheet_fields['sheet_no_signups']) {
					$sheet_fields['sheet_no_signups'] = true;
				} else {
					$sheet_fields['sheet_no_signups'] = false;
				}
				// Make sure our duplicate_times gets set correctly
				if (isset($sheet_fields['sheet_duplicate_times']) && '1' == $sheet_fields['sheet_duplicate_times']) {
					$sheet_fields['sheet_duplicate_times'] = true;
				} else {
					$sheet_fields['sheet_duplicate_times'] = false;
				}
				$email_options = array('default','chair','user','both','none');
				if(empty($sheet_fields['sheet_clear_emails']) || !in_array($sheet_fields['sheet_clear_emails'], $email_options)) {
					$sheet_fields['sheet_clear_emails'] = 'default';
				}
				if(empty($sheet_fields['sheet_signup_emails']) || !in_array($sheet_fields['sheet_signup_emails'], $email_options)) {
					$sheet_fields['sheet_signup_emails'] = 'default';
				}
				if ($duplicates && $add) {
					PTA_SUS_Messages::add_error(__('A Sheet with the same name already exists!', 'pta-volunteer-sign-up-sheets'));
					PTA_SUS_Messages::show_messages(true,'admin');
					return;
				}
				// Add/Update Sheet
				if ($add) {
					$sheet_id = pta_sus_add_sheet($sheet_fields);
					if(!$sheet_id) {
						$sheet_err++;
						PTA_SUS_Messages::add_error(__('Error adding sheet.', 'pta-volunteer-sign-up-sheets'));
						$sheet_fields['sheet_id'] = 0;
					} else {
						$sheet_fields['sheet_id'] = $sheet_id;
					}
				} else {
					$updated = pta_sus_update_sheet($sheet_fields, (int)$_GET['sheet_id']);
					$sheet_fields['sheet_id'] = (int)$_GET['sheet_id'];
					if(false === $updated) {
						$sheet_err++;
						PTA_SUS_Messages::add_error(__('Error updating sheet.', 'pta-volunteer-sign-up-sheets'));
					}
				}

				do_action( 'pta_sus_admin_process_sheet_end', $add, $sheet_err, $sheet_fields['sheet_id'] );

				if (!$sheet_err) {
					// Sheet saved successfully, set flags to show tasks form
					$sheet_success = true;
					if($add) $add_tasks = true;                   
				}
			}
		}
		// display and reset any messages up to this point
		PTA_SUS_Messages::show_messages(true, 'admin');
		PTA_SUS_Messages::clear_messages();

		// Set field values for form
		$fields = array();
		// Check possible conditions
		// 
		// If a form was submitted, but no success yet, get fields from POST data
		if(($sheet_submitted && !$sheet_success) || ($tasks_submitted && !$tasks_success)) {
			$fields = $_POST;
		} elseif($edit_sheet || $edit_tasks || $add_tasks || $tasks_success || $moved) {
			// Clicked on an edit action link, but nothing posted yet - Get fields from DB instead
			// Or, Tasks successfully posted, in which case we want to show task form again (Heather)
			// So, grab the fields from the database also
			// Get the right sheet id
			if($sheet_success || $tasks_success) {
				$sheet_id = (int)$sheet_fields['sheet_id'];
			} elseif ($moved) {
				$sheet_id = $new_sheet_id;
			} else {
				$sheet_id = (int)$_GET['sheet_id'];
			}
			$fields = $this->get_fields((int)$sheet_id);
		} 

		// Figure out which form to display
		if (!$tasks_success && ($edit_tasks || $tasks_submitted || $add_tasks || $moved)) {
			echo '<div class="wrap pta_sus"><h2>'.( $edit_tasks || $moved ? __('Edit', 'pta-volunteer-sign-up-sheets') : __('ADD', 'pta-volunteer-sign-up-sheets')) . ' '.__('Tasks', 'pta-volunteer-sign-up-sheets').'</h2>';
			$this->display_tasks_form($fields);
			echo '</div>';
		} elseif (!$sheet_success && ($add || $edit_sheet)) {
			echo '<div class="wrap pta_sus"><h2>'.(($add) ? __('ADD', 'pta-volunteer-sign-up-sheets') :  __('Edit', 'pta-volunteer-sign-up-sheets')).' '.__('Sign-up Sheet', 'pta-volunteer-sign-up-sheets').'</h2>';
			$this->display_sheet_form($fields, $edit_sheet);
			if($edit_sheet) {
				$edit_tasks_url = add_query_arg(array("action"=>"edit_tasks", "sheet_id"=>$_GET['sheet_id']));
				echo '<a href="'.esc_url($edit_tasks_url).'" class="button-secondary">'.__('Edit Tasks', 'pta-volunteer-sign-up-sheets').'</a>';
			} else {
				echo'<p><strong>'.__('Dates and Tasks are added on the next page', 'pta-volunteer-sign-up-sheets').'</strong></p>';
			}
			echo '</div>';
		} elseif ($tasks_success) {
			echo '<div class="wrap pta_sus"><h2>'.($edit_tasks ? __('Edit', 'pta-volunteer-sign-up-sheets') : __('ADD', 'pta-volunteer-sign-up-sheets')) . ' '.__('Tasks', 'pta-volunteer-sign-up-sheets').'</h2>';
			PTA_SUS_Messages::add_message(__('Tasks Successfully Updated!', 'pta-volunteer-sign-up-sheets'));
			PTA_SUS_Messages::show_messages(true,'admin');
			$this->display_tasks_form($fields);
			echo '</div>';
		} elseif ($sheet_success && $edit_sheet) {
			echo '<div class="wrap pta_sus"><h2>'.__('Edit Sheet', 'pta-volunteer-sign-up-sheets').'</h2>';
			PTA_SUS_Messages::add_message(__('Sheet Updated!', 'pta-volunteer-sign-up-sheets'));
			PTA_SUS_Messages::show_messages(true,'admin');
			$edit_tasks_url = add_query_arg(array("action"=>"edit_tasks", "sheet_id"=>$_GET['sheet_id']));
			echo '<a href="'.esc_url($edit_tasks_url).'" class="button-secondary">'.__('Edit Tasks', 'pta-volunteer-sign-up-sheets').'</a>
				</div>';
		}
	}

	/**
	 * Get sheet and task fields for form display
	 * 
	 * Retrieves all sheet and task data from the database and formats it for
	 * use in the admin forms. Handles different sheet types (Single, Recurring, etc.)
	 * and formats dates appropriately.
	 * 
	 * @since 1.0.0
	 * @param int|string $id Sheet ID (empty string returns false)
	 * @return array|false Array of form fields with 'sheet_' and 'task_' prefixes, or false if invalid ID
	 * @hook pta_sus_admin_get_fields Filter to modify retrieved fields
	 */
	private function get_fields($id='') {
		if('' === $id) return false;
		$sheet_fields = array();
		if ($sheet = pta_sus_get_sheet($id)) {
            $sheet = $sheet->to_array();
			foreach($sheet AS $k=>$v) {
                $sheet_fields['sheet_' . $k] = $v;
            }
		}
		$task_fields = array();
		$dates = PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet($id);
		if ($tasks = PTA_SUS_Task_Functions::get_tasks($id)) {
			foreach ($tasks AS $task) {
				$task_fields['task_id'][] = $task->id;
				$task_fields['task_title'][] = $task->title;
				$task_fields['task_description'][] = $task->description;
				$task_fields['task_dates'][] = $task->dates;
				$task_fields['task_qty'][] = $task->qty;
				$task_fields['task_time_start'][] = $task->time_start;
				$task_fields['task_time_end'][] = $task->time_end;
				$task_fields['task_need_details'][] = $task->need_details;
				$task_fields['task_details_required'][] = $task->details_required;
				$task_fields['task_details_text'][] = $task->details_text;
				$task_fields['task_allow_duplicates'][] = $task->allow_duplicates;                
				$task_fields['task_enable_quantities'][] = $task->enable_quantities;
			}
		}

		$fields = array_merge((array)$sheet_fields, (array)$task_fields);
		if ( 'Single' === $sheet_fields['sheet_type'] ) {
			$fields['single_date'] = (empty($dates) || !isset($dates[0])) ? '' : $dates[0];
		} elseif ( 'Recurring' === $sheet_fields['sheet_type'] ) {
			$fields['recurring_dates'] = (empty($dates)) ? '' : implode(",", $dates);
		}
		return apply_filters( 'pta_sus_admin_get_fields', $fields, $id );
	}

	/**
	 * Display sheet form
	 * 
	 * Outputs the HTML form for adding or editing a sheet. Includes all sheet
	 * fields, email options, contact info, and details editor. Provides multiple
	 * action hooks for extensions to add custom fields.
	 * 
	 * @since 1.0.0
	 * @param array $f Form field values (with 'sheet_' prefix)
	 * @param bool $edit Whether this is edit mode (true) or add mode (false)
	 * @return void Outputs HTML directly
	 * @hook pta_sus_sheet_form_sheet_types Filter to modify available sheet types
	 * @hook pta_sus_sheet_form_after_title Action to add fields after title
	 * @hook pta_sus_sheet_form_after_event_type Action to add fields after event type
	 * @hook pta_sus_sheet_form_after_visible Action to add fields after visible checkbox
	 * @hook pta_sus_sheet_form_before_contact_info Action to add fields before contact info
	 * @hook pta_sus_sheet_form_after_contact_info Action to add fields after contact info
	 * @hook pta_sus_sheet_form_after_sheet_details Action to add fields after sheet details
	 */
	private function display_sheet_form($f=array(), $edit=false) {
		// Allow other plugins to add/modify other sheet types
		$sheet_types = apply_filters( 'pta_sus_sheet_form_sheet_types', 
				array(
					'Single' => __('Single', 'pta-volunteer-sign-up-sheets'),
					'Recurring' => __('Recurring', 'pta-volunteer-sign-up-sheets'),
					'Multi-Day' => __('Multi-Day', 'pta-volunteer-sign-up-sheets'),
					'Ongoing' => __('Ongoing', 'pta-volunteer-sign-up-sheets')
				     ));
		// default for visible will be checked
		if ((isset($f['sheet_visible']) && 1 == $f['sheet_visible']) || !isset($f['sheet_visible'])) {
			$visible_checked = 'checked="checked"';
		} else {
			// it's set, but == 0
			$visible_checked = '';
		}
		// default for clear will be checked
		if ((isset($f['sheet_clear']) && 1 == $f['sheet_clear']) || !isset($f['sheet_clear'])) {
			$clear_checked = 'checked="checked"';
		} else {
			// it's set, but == 0
			$clear_checked = '';
		}

		if (isset($f['sheet_no_signups']) && 1 == $f['sheet_no_signups']) {
			$no_signups_checked = 'checked="checked"';
		} else {
			// it's set, but == 0
			$no_signups_checked = '';
		}

		if (isset($f['sheet_duplicate_times']) && 1 == $f['sheet_duplicate_times']) {
			$duplicate_times_checked = 'checked="checked"';
		} else {
			// it's set, but == 0
			$duplicate_times_checked = '';
		}

		$options = array();
		$options[] = "<option value=''>".__('Select Event Type', 'pta-volunteer-sign-up-sheets')."</option>";
		foreach ($sheet_types as $type => $display) {
			$selected = '';
			if ( isset($f['sheet_type']) && $type == $f['sheet_type'] ) {
				$selected = "selected='selected'"; 
			}
			$options[] = "<option value='{$type}' $selected >{$display}</option>";
		}
		$sheet_id = isset($f['sheet_id']) ? absint( $f['sheet_id']) : 0;
		echo '
			<form name="add_sheet" id="pta-sus-modify-sheet" method="post" action="">
			<p>
			<label for="sheet_title"><strong>'.__('Title:', 'pta-volunteer-sign-up-sheets').'</strong></label>
			<input type="text" id="sheet_title" name="sheet_title" value="'.((isset($f['sheet_title']) ? stripslashes(esc_attr($f['sheet_title'])) : '')).'" size="60">
			<em>'.__('Title of event, program or function', 'pta-volunteer-sign-up-sheets').'</em>
			</p>';
		// Allow other plugins to add fields to the form
		do_action( 'pta_sus_sheet_form_after_title', $f, $edit );
		if($edit) {
			echo '<p><strong>'.__('Event Type:', 'pta-volunteer-sign-up-sheets').' </strong>'.$f['sheet_type'].'</p>
				<input type="hidden" name="sheet_type" value="'.$f['sheet_type'].'" />';
		} else {
			echo '<p>
				<label for="sheet_type"><strong>'.__('Event Type:', 'pta-volunteer-sign-up-sheets').'</strong></label>
				<select id="sheet_type" name="sheet_type">
				'.implode("\n", $options).'
				</select>
				</p>';
		}
		// Allow other plugins to add fields to the form
		do_action( 'pta_sus_sheet_form_after_event_type', $f, $edit );

		echo '
			<p>
			<label for="sheet_no_signups">'.__('No Signup Event?', 'pta-volunteer-sign-up-sheets').'&nbsp;</label>
			<input type="checkbox" id="sheet_no_signups" name="sheet_no_signups" value="1" '.$no_signups_checked.'/>
			<em>&nbsp;'.__('Check this for an event where no sign-ups are needed (display only). You can still enter tasks, which could be used to show a schedule, but you can not enter quantities and there will be no signup links.', 'pta-volunteer-sign-up-sheets').'</em>
			</p>';

		echo '  
			<p>    
			<label for="sheet_reminder1_days">'.__('1st Reminder # of days:', 'pta-volunteer-sign-up-sheets').'</label>
			<input type="text" id="sheet_reminder1_days" name="sheet_reminder1_days" value="'.((isset($f['sheet_reminder1_days']) ? esc_attr($f['sheet_reminder1_days']) : '')).'" size="5" >
			<em>'.__('# of days before the event date to send the first reminder. Leave blank (or 0) for no automatic reminders', 'pta-volunteer-sign-up-sheets').'</em>
			</p>
			<p>    
			<label for="sheet_reminder2_days">'.__('2nd Reminder # of days:', 'pta-volunteer-sign-up-sheets').'</label>
			<input type="text" id="sheet_reminder2_days" name="sheet_reminder2_days" value="'.((isset($f['sheet_reminder2_days']) ? esc_attr($f['sheet_reminder2_days']) : '')).'" size="5" >
			<em>'.__('# of days before the event date to send the second reminder. Leave blank (or 0) for no second reminder', 'pta-volunteer-sign-up-sheets').'</em>
			</p>';

		echo '
			<p>
			<label for="sheet_clear">'.__('Show Clear links for signups?', 'pta-volunteer-sign-up-sheets').'&nbsp;</label>
			<input type="checkbox" id="sheet_clear" name="sheet_clear" value="1" '.$clear_checked.'/>
			<em>&nbsp;'.__('<strong>Uncheck</strong> if you want to <strong>HIDE</strong> the clear link in the user\'s signup list. Administrators and Sign-Up Sheet Managers can still clear volunteers from signups in the admin dashboard.', 'pta-volunteer-sign-up-sheets').'</em>
			</p>
			<p>
			<label for="sheet_clear_type">'.__('Clear Time Type:', 'pta-volunteer-sign-up-sheets').'</label>
			<select id="sheet_clear_type" name="sheet_clear_type">
			<option value="days" '.((isset($f['sheet_clear_type']) && 'days' == $f['sheet_clear_type']) ? 'selected="selected"' : '').'>'.__('Days', 'pta-volunteer-sign-up-sheets').'</option>
			<option value="hours" '.((isset($f['sheet_clear_type']) && 'hours' == $f['sheet_clear_type']) ? 'selected="selected"' : '').'>'.__('Hours', 'pta-volunteer-sign-up-sheets').'</option>
			</select>
			</p>
			<p>    
			<label for="sheet_clear_days">'.__('# of days/hours to allow clear:', 'pta-volunteer-sign-up-sheets').'</label>
			<input type="text" id="sheet_clear_days" name="sheet_clear_days" value="'.((isset($f['sheet_clear_days']) ? esc_attr($f['sheet_clear_days']) : '')).'" size="5" >
			<em>'.__('If the Show Clear option is checked, enter the MINIMUM # of days, or hours, before the signup event/item date during which volunteers can clear their signups. Leave blank (or 0) to allow them to clear themselves at any time.', 'pta-volunteer-sign-up-sheets').'</em>
			</p>';

		echo '
			<p>
			<label for="sheet_visible">'.__('Visible to Public?', 'pta-volunteer-sign-up-sheets').'&nbsp;</label>
			<input type="checkbox" id="sheet_visible" name="sheet_visible" value="1" '.$visible_checked.'/>
			<em>&nbsp;'.__('<strong>Uncheck</strong> if you want to <strong>hide</strong> this sheet from the public. Administrators and Sign-Up Sheet Managers can still see hidden sheets.', 'pta-volunteer-sign-up-sheets').'</em>
			</p>';

		// Allow other plugins to add fields to the form
		do_action( 'pta_sus_sheet_form_after_visible', $f, $edit );

		echo '
			<p>
			<label for="sheet_duplicate_times">'.__('Allow Duplicate Signup Times?', 'pta-volunteer-sign-up-sheets').'&nbsp;</label>
			<input type="checkbox" id="sheet_duplicate_times" name="sheet_duplicate_times" value="1" '.$duplicate_times_checked.'/>
			<em>&nbsp;'.__('Check this to allow a volunteer to signup for more than one task (in this sheet) with overlapping time ranges.', 'pta-volunteer-sign-up-sheets').'</em>
			</p>';

		echo '<hr/>';
		echo '<h3>'.__('Sheet Email Options','pta-volunteer-sign-up-sheets').'</h3>';
		echo '<p>'. __(/** @lang Text */'Select where you want signup confirmation and clear emails to go for this sheet. Leave them set to "Default" to use the global settings from the Email settings page.','pta-volunteer-sign-up-sheets').'<br/>';
		echo __('If you select anything other than "Default" for these options, they will override the main email settings. Even if you disable all emails via the emails settings page, emails for signup confirmation and clear emails will be sent to your selections here (unless you select none or default).','pta-volunteer-sign-up-sheets').'</p>';


		$email_options = array(
			'default' => __('Default', 'pta-volunteer-sign-up-sheets'),
			'chair' => __('Chair Only', 'pta-volunteer-sign-up-sheets'),
			'user' => __('User Only', 'pta-volunteer-sign-up-sheets'),
			'both' => __('Both Chair & User', 'pta-volunteer-sign-up-sheets'),
			'none' => __('None (no emails)', 'pta-volunteer-sign-up-sheets'),
		);
		echo '
			<p>
			<label for="sheet_clear_emails">'.__("Send Clear Emails to:",'pta-volunteer-sign-up-sheets').'</label>
			<select name="sheet_clear_emails" id="sheet_clear_emails">';
		$selected = !empty($f['sheet_clear_emails']) && in_array($f['sheet_clear_emails'], array_keys($email_options)) ? $f['sheet_clear_emails'] : 'default';
		foreach ($email_options as $value => $label) {
			echo '<option value="'.$value.'" '.selected($value, $selected).'>'.$label.'</option>';
		}
		echo '</select></p>';
		echo '
			<p>
			<label for="sheet_signup_emails">'.__("Send Signup Confirmation Emails to:",'pta-volunteer-sign-up-sheets').'</label>
			<select name="sheet_signup_emails" id="sheet_signup_emails">';
		$selected = !empty($f['sheet_signup_emails']) && in_array($f['sheet_signup_emails'], array_keys($email_options)) ? $f['sheet_signup_emails'] : 'default';
		foreach ($email_options as $value => $label) {
			echo '<option value="'.$value.'" '.selected($value, $selected).'>'.$label.'</option>';
		}
		echo '</select></p>';

		// Allow other plugins to add fields to the form
		do_action( 'pta_sus_sheet_form_before_contact_info', $f, $edit );

		echo '
			<hr />
			<h3>'.__('Contact Info:', 'pta-volunteer-sign-up-sheets').'</h3>';

		if( $this->member_directory_active ) {
			$taxonomies = array('member_category');
			$positions = get_terms( $taxonomies );
			$options = array();
			$options[] = "<option value=''>".__('Select Position', 'pta-volunteer-sign-up-sheets')."</option>";
			foreach ($positions as $position) {
				$selected = '';
				if ( isset($f['sheet_position']) && $position->slug == $f['sheet_position'] ) {
					$selected = "selected='selected'"; 
				}
				$options[] = "<option value='{$position->slug}' $selected >{$position->name}</option>";
			}
			echo '<p>'.__('Select a program, committee, or position, to use the contact form:', 'pta-volunteer-sign-up-sheets').'</p>
				<label for="sheet_position">'.__('Position:', 'pta-volunteer-sign-up-sheets').'</label>
				<select id="sheet_position" name="sheet_position">
				'.implode("\n", $options).'
				</select>
				<p>'.__('<strong><em>OR</em></strong>, manually enter chair names and contact emails below.', 'pta-volunteer-sign-up-sheets').'</p>';
		}

		echo '
			<p>
			<label for="sheet_chair_name">'.__('Chair Name(s):', 'pta-volunteer-sign-up-sheets').'</label>
	      	<input type="text" id="sheet_chair_name" name="sheet_chair_name" value="'.((isset($f['sheet_chair_name']) ? esc_attr($f['sheet_chair_name']) : '')).'" size="80">
			<em>'.__('Separate multiple names with commas', 'pta-volunteer-sign-up-sheets').'</em>
		  	</p>
	      	<p>
		 	<label for="sheet_chair_email">'.__('Chair Email(s):', 'pta-volunteer-sign-up-sheets').'</label>
		 	<input type="text" id="sheet_chair_email" name="sheet_chair_email" value="'.((isset($f['sheet_chair_email']) ? esc_attr($f['sheet_chair_email']) : '')).'" size="80">
		  	<em>'.__('Separate multiple emails with commas', 'pta-volunteer-sign-up-sheets').'</em>
		    </p>';
		// Allow other plugins to add fields to the form
		do_action( 'pta_sus_sheet_form_after_contact_info', $f, $edit );
		$content = isset($f['sheet_details']) ? wp_kses_post($f['sheet_details']) : '';
		$editor_id = "sheet_details";
		$settings = array( 'wpautop' => false, 'textarea_rows' => 10 );
		echo '
			<hr />
			<p>
			<label for="sheet_details"><h3>'.__('Program/Event Details (optional):', 'pta-volunteer-sign-up-sheets').'</h3></label>
												  </p>';
		wp_editor( $content, $editor_id, $settings );
		// Allow other plugins to add fields to the form
		do_action( 'pta_sus_sheet_form_after_sheet_details', $f, $edit );
		// Security Nonce
		wp_nonce_field('pta_sus_add_sheet','pta_sus_add_sheet_nonce');
		echo '
			<p class="submit">
			<input type="hidden" name="sheet_id" value="'.esc_attr($sheet_id).'" />
			<input type="hidden" name="sheet_mode" value="submitted" />
			<input type="submit" name="Submit" class="button-primary" value="'.__("Save Sheet", "pta_volunteer_sus").'" /><br/><br/>
			</p>
			</form>
			';
	}

	/**
	 * Display tasks form
	 * 
	 * Outputs the HTML form for adding or editing tasks. Includes template file
	 * that handles the complex task entry interface with dates, times, quantities, etc.
	 * 
	 * @since 1.0.0
	 * @param array $f Form field values (with 'task_' prefix arrays)
	 * @return void Outputs HTML directly
	 */
	private function display_tasks_form($f=array()) {
		include(PTA_VOLUNTEER_SUS_DIR.'views/admin-task-form-html.php');
	}

	/**
	 * Admin page: Email Volunteers
	 * 
	 * Displays the email volunteers page and processes form submissions.
	 * Allows admins to send emails to all volunteers for a specific sheet
	 * or all WordPress users.
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function email_volunteers_page() {
		// check if form submitted, and send emails, if needed
		if(isset($_POST['email_volunteers_mode']) && 'submitted' === $_POST['email_volunteers_mode']) {
			check_admin_referer( 'pta_sus_email_volunteers', 'pta_sus_email_volunteers_nonce' );
			$this->send_volunteer_emails();
		}

		include('admin-email-volunteers-html.php');
	}

	/**
	 * Admin page: Add Ons
	 * 
	 * Displays information about available add-on plugins and extensions.
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_addons_page() {
		include('admin-addons-html.php');
	}

	/**
	 * Send emails to volunteers
	 * 
	 * Processes the email volunteers form and sends emails to either:
	 * - All volunteers signed up for a specific sheet
	 * - All WordPress users
	 * 
	 * Supports sending individually or via BCC. Validates all inputs and
	 * provides user feedback on success/failure.
	 * 
	 * @since 1.0.0
	 * @return void
	 * @see PTA_SUS_Signup_Functions::get_volunteer_emails()
	 */
	public function send_volunteer_emails() {
		$errors = 0;
		$sheet_id = 0;
		$from_name = $subject = $message = '';
		// Get all needed info, or set error messages
		if(isset($_POST['sheet_select']) && ( 'users' === $_POST['sheet_select'] || is_numeric($_POST['sheet_select']) ) ) {
			if('users' !== $_POST['sheet_select']) {
				$sheet_id = absint($_POST['sheet_select']);
			}
		} else {
			$errors++;
			PTA_SUS_Messages::add_error(__('Invalid sheet selection', 'pta-volunteer-sign-up-sheets'));
		}
		if(isset($_POST['from_name']) && '' !== $_POST['from_name']) {
			$from_name = sanitize_text_field($_POST['from_name']);
		} else {
			$errors++;
			PTA_SUS_Messages::add_error(__('Please enter a From Name', 'pta-volunteer-sign-up-sheets'));
		}
		if(isset($_POST['reply_to']) && is_email($_POST['reply_to']) && '' !== $_POST['reply_to']) {
			$reply_to = sanitize_text_field($_POST['reply_to']);
		} else {
			$errors++;
			PTA_SUS_Messages::add_error(__('Please enter a valid reply to email, or leave blank for none.', 'pta-volunteer-sign-up-sheets'));
		}
		if(isset($_POST['subject']) && '' !== sanitize_text_field($_POST['subject'])) {
			$subject = stripslashes(sanitize_text_field($_POST['subject']));
		} else {
			$errors++;
			PTA_SUS_Messages::add_error(__('Please enter a subject', 'pta-volunteer-sign-up-sheets'));
		}
		if(isset($_POST['message']) && '' !== wp_kses_post(trim($_POST['message']))) {
			$message = stripslashes(sanitize_textarea_field($_POST['message']));
		} else {
			$errors++;
			PTA_SUS_Messages::add_error(__('Please enter a message', 'pta-volunteer-sign-up-sheets'));
		}
		$individually = (isset($_POST['individually']) && 1 == absint($_POST['individually']));

		if(0 == $errors) {
			// No errors, get emails
			if($sheet_id > 0) {
				$emails = PTA_SUS_Signup_Functions::get_volunteer_emails($sheet_id);
			} else {
				$users = get_users();
				$emails = array();
				foreach($users as $user) {
					/**
					 * @var WP_User $user
					 */
					$emails[] = sanitize_email( $user->user_email);
				}
			}

			if(empty($emails)) {
				PTA_SUS_Messages::add_error(__('No signups found for that sheet', 'pta-volunteer-sign-up-sheets'));
			} else {
				// Send some emails!
				$from_email = isset($_POST['from_email']) ? sanitize_email($_POST['from_email']) : get_option('admin_email');
				$reply_to = !empty($reply_to) && is_email($reply_to) ? $reply_to : $from_email;
				$headers = array();
				$headers[]  = "From: " . $from_name . " <" . $from_email . ">";
				$headers[]  = "Reply-To: " . $reply_to;
				$headers[]  = "Content-Type: text/plain; charset=utf-8";
				$headers[]  = "Content-Transfer-Encoding: 8bit";

				$sent_to = array();
				if($individually) {
					$emails[] = $from_email;
					$sent = true;
					foreach ($emails as $email) {
						// make sure it's a valid email before sending
						if(is_email($email)) {
							$result = wp_mail($email, $subject, $message, $headers);
							if(false === $result) {
								$sent = false;
							} else {
								$sent_to[] = $email;
							}
						}
					}
				} else {
					$sent_to[] = $from_email;
					// put all volunteer emails in BCC fields
					foreach ($emails as $cc) {
						if(is_email($cc)) {
							$headers[] = 'Bcc: ' . $cc;
							$sent_to[] = $cc;
						}
					}
					// send to the sender, with BCC to all volunteers
					$sent = wp_mail($from_email, $subject, $message, $headers);
				}

				if($sent) {
					$count = count($sent_to);
					$emails = implode(', ', $sent_to);
					PTA_SUS_Messages::add_message(sprintf(__('%s Emails Sent!', 'pta-volunteer-sign-up-sheets'), $count));
					PTA_SUS_Messages::add_message(sprintf(__('Emails sent to: %s', 'pta-volunteer-sign-up-sheets'), esc_html($emails)));
				} else {
					PTA_SUS_Messages::add_error(__('The WordPress Mail function reported a problem sending one or more emails.', 'pta-volunteer-sign-up-sheets'));
				}
			}
		}

	}

} // End of Class
/* EOF */
