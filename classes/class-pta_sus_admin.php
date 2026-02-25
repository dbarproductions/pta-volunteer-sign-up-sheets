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
	 * Whether to filter sheets by author (for Signup Sheet Authors)
	 * 
	 * @var bool
	 */
	private $filter_by_author;

	/**
	 * Current author ID for filtering (null for admins, user ID for authors)
	 * 
	 * @var int|null
	 */
	private $current_author_id;

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
		// Task management AJAX handlers
		add_action( 'wp_ajax_pta_sus_save_task', array($this, 'ajax_save_task' ) );
		add_action( 'wp_ajax_pta_sus_delete_task', array($this, 'ajax_delete_task' ) );
		add_action( 'wp_ajax_pta_sus_get_task', array($this, 'ajax_get_task' ) );
		add_action( 'wp_ajax_pta_sus_reorder_tasks', array($this, 'ajax_reorder_tasks' ) );
		add_action( 'wp_ajax_pta_sus_save_sheet_dates', array($this, 'ajax_save_sheet_dates' ) );
		add_filter( 'set-screen-option', array( $this, 'set_screen' ), 10, 3 );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		// Server-side DataTables AJAX endpoint (admin-only).
		add_action( 'wp_ajax_PTA_SUS_ADMIN_DT_DATA', array( $this, 'ajax_admin_dt_data' ) );
		// Server-side export endpoint (admin-only, outputs CSV or print HTML).
		add_action( 'wp_ajax_PTA_SUS_ADMIN_EXPORT', array( $this, 'ajax_admin_export' ) );
		// Invalidate server-side DT cache on any CRUD operation.
		$cache_invalidation_hooks = array(
			'pta_sus_created_signup', 'pta_sus_updated_signup', 'pta_sus_deleted_signup',
			'pta_sus_created_task',   'pta_sus_updated_task',   'pta_sus_deleted_task',
			'pta_sus_created_sheet',  'pta_sus_updated_sheet',  'pta_sus_deleted_sheet',
		);
		foreach ( $cache_invalidation_hooks as $hook ) {
			add_action( $hook, array( $this, 'invalidate_admin_dt_cache' ) );
		}
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
	 * WordPress admin_init hook callback. Processes list table actions and email template forms.
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_init() {
		$this->maybe_process_list_table_actions();
		// Process email template forms early (before any output)
		// Check for POST submission (form was submitted) rather than just page check
		if (isset($_POST['pta_email_template_mode']) && 'submitted' === $_POST['pta_email_template_mode']) {
			$this->process_email_template_form();
		}
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
		// Settings pages should only be visible to Admins and Managers (those with manage_others_signup_sheets)
		// Authors (only manage_signup_sheets) should NOT see Settings, CRON, or Add Ons pages
		$this->show_settings = (current_user_can('manage_options') || current_user_can('manage_others_signup_sheets') || (!isset($this->main_options['admin_only_settings']) || false == $this->main_options['admin_only_settings']));
		if($this->show_settings) {
			add_filter( 'option_page_capability_pta_volunteer_sus_main_options', array($this,'pta_settings_permissions'), 10, 1 );
		}
		if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_signup_sheets' ) ) {
			add_menu_page(__('Sign-up Sheets', 'pta-volunteer-sign-up-sheets'), __('Sign-up Sheets', 'pta-volunteer-sign-up-sheets'), 'manage_signup_sheets', $this->admin_settings_slug.'_sheets', array($this, 'admin_sheet_page'), null, 93);
			$all_sheets = add_submenu_page($this->admin_settings_slug.'_sheets', __('Sign-up Sheets', 'pta-volunteer-sign-up-sheets'), __('All Sheets', 'pta-volunteer-sign-up-sheets'), 'manage_signup_sheets', $this->admin_settings_slug.'_sheets', array($this, 'admin_sheet_page'));
			add_submenu_page($this->admin_settings_slug.'_sheets', __('Add New Sheet', 'pta-volunteer-sign-up-sheets'), __('Add New', 'pta-volunteer-sign-up-sheets'), 'manage_signup_sheets', $this->admin_settings_slug.'_modify_sheet', array($this, 'admin_modify_sheet_page'));
			add_submenu_page($this->admin_settings_slug.'_sheets', __('Email Volunteers', 'pta-volunteer-sign-up-sheets'), __('Email Volunteers', 'pta-volunteer-sign-up-sheets'), 'manage_signup_sheets', $this->admin_settings_slug.'_email', array($this, 'email_volunteers_page'));
			add_submenu_page($this->admin_settings_slug.'_sheets', __('Email Templates', 'pta-volunteer-sign-up-sheets'), __('Email Templates', 'pta-volunteer-sign-up-sheets'), 'manage_signup_sheets', $this->admin_settings_slug.'_email_templates', array($this, 'admin_email_templates_page'));
			// Settings, CRON, and Add Ons pages require manage_others_signup_sheets (Admins and Managers only, not Authors)
			if($this->show_settings && (current_user_can('manage_options') || current_user_can('manage_others_signup_sheets'))) {
				add_submenu_page($this->admin_settings_slug.'_sheets', __('Settings', 'pta-volunteer-sign-up-sheets'), __('Settings', 'pta-volunteer-sign-up-sheets'), 'manage_others_signup_sheets', $this->admin_settings_slug.'_settings', array($this->options_page, 'admin_options'));
				add_submenu_page($this->admin_settings_slug.'_sheets', __('Tools', 'pta-volunteer-sign-up-sheets'), __('Tools', 'pta-volunteer-sign-up-sheets'), 'manage_others_signup_sheets', $this->admin_settings_slug.'_cron', array($this, 'admin_reminders_page'));
				add_submenu_page($this->admin_settings_slug.'_sheets', __('Add Ons', 'pta-volunteer-sign-up-sheets'), __('Add Ons', 'pta-volunteer-sign-up-sheets'), 'manage_others_signup_sheets', $this->admin_settings_slug.'_addons', array($this, 'admin_addons_page'));
				add_submenu_page($this->admin_settings_slug.'_sheets', __('Licenses', 'pta-volunteer-sign-up-sheets'), __('Licenses', 'pta-volunteer-sign-up-sheets'), 'manage_others_signup_sheets', $this->admin_settings_slug.'_licenses', array('PTA_SUS_License_Manager', 'render_licenses_page'));
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
	 * from 'manage_options' to 'manage_others_signup_sheets' (so only Admins and Managers can access).
	 * Authors (only manage_signup_sheets) should NOT be able to access settings.
	 * 
	 * @since 1.0.0
	 * @param string $capability Current capability requirement
	 * @return string Modified capability ('manage_others_signup_sheets')
	 */
	public function pta_settings_permissions( $capability ) {
		return 'manage_others_signup_sheets';
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
			wp_enqueue_style( 'pta-datatables2-style' );
			wp_enqueue_script( 'jquery-plugin' );
			wp_enqueue_script( 'pta-jquery-datepick' );
			wp_enqueue_script( 'pta-jquery-ui-timepicker', plugins_url( '../assets/js/jquery.ui.timepicker.js' , __FILE__ ), array( 'jquery', 'jquery-ui-core', 'jquery-ui-position' ) );
			wp_enqueue_script('pta-datatables2');
			wp_enqueue_style('pta-select2');
			wp_enqueue_script('pta-select2');
			wp_enqueue_script('jquery-ui-sortable');
			wp_enqueue_script( 'jquery-ui-autocomplete');
			// Use non-minified version for debugging (switch back to .min.js for production)
			// Ensure pta-sus-autocomplete is a dependency so livesearch.js loads before backend.js
			$backend_deps = array( 'jquery','pta-jquery-datepick','pta-jquery-ui-timepicker', 'pta-datatables2','jquery-ui-sortable','jquery-ui-autocomplete');
			if (isset($this->main_options['enable_signup_search']) && $this->main_options['enable_signup_search']) {
				$backend_deps[] = 'pta-sus-autocomplete'; // Ensure livesearch.js loads before backend.js
			}
			wp_enqueue_script( 'pta-sus-backend', plugins_url( '../assets/js/backend.js' , __FILE__ ), $backend_deps, PTA_VOLUNTEER_SUS_VERSION_NUM . '-' . filemtime(plugin_dir_path(__FILE__) . '../assets/js/backend.js'), true );
			// Task management modal system
			wp_enqueue_script( 'jquery-ui-dialog' );
			// Always enqueue Quill for task description editor (used on public side)
			wp_enqueue_style('pta-quill');
			wp_enqueue_script('pta-quill');
			wp_enqueue_script( 'pta-sus-task-management', plugins_url( '../assets/js/task-management.js' , __FILE__ ), array( 'jquery', 'jquery-ui-dialog', 'jquery-ui-sortable', 'pta-jquery-datepick', 'pta-jquery-ui-timepicker' ), PTA_VOLUNTEER_SUS_VERSION_NUM, true );
			wp_enqueue_style( 'pta-jquery-datepick');
			wp_enqueue_style( 'pta-jquery.ui.timepicker', plugins_url( '../assets/css/jquery.ui.timepicker.css', __FILE__ ) );
			wp_enqueue_style( 'pta-jquery-ui-1.10.0.custom', plugins_url( '../assets/css/jquery-ui-1.10.0.custom.min.css', __FILE__ ) );
			wp_enqueue_style( 'wp-jquery-ui-dialog' );
            // Enqueue live search script for admin signup forms
            // Note: pta-sus-autocomplete (livesearch.js) must load before backend.js so ptaVolunteer is available
            if (isset($this->main_options['enable_signup_search']) && $this->main_options['enable_signup_search']) {
                wp_enqueue_style('pta-sus-autocomplete');
                wp_enqueue_script('pta-sus-autocomplete');
                wp_localize_script('pta-sus-autocomplete', 'ptaSUS', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'ptaNonce' => wp_create_nonce('ajax-pta-nonce')
                ));
                // Ensure backend.js depends on pta-sus-autocomplete so livesearch.js loads first
                // This is important because backend.js uses ptaVolunteer from livesearch.js
            }
			// Determine server-side DataTables mode for this request.
			$dt_mode        = $this->main_options['admin_dt_server_side'] ?? 'off';
			$dt_threshold   = absint( $this->main_options['admin_dt_threshold'] ?? 500 );
			$url_sheet_id   = absint( $_GET['sheet_id'] ?? 0 );
			$url_action     = sanitize_text_field( $_GET['action'] ?? '' );

			if ( 'on' === $dt_mode ) {
				$server_side = true;
			} elseif ( 'auto' === $dt_mode ) {
				// Auto: estimate row count and compare to threshold.
				// Phase 7 will add a proper estimate; for now treat auto as always on.
				$server_side = true;
			} else {
				$server_side = false;
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
				'showRemaining' => __('Show Remaining', 'pta-volunteer-sign-up-sheets'),
				'disableGrouping' => __('Disable Grouping', 'pta-volunteer-sign-up-sheets'),
				'colvisText' => __('Column Visibility', 'pta-volunteer-sign-up-sheets'),
				'showAll' => __('Show All', 'pta-volunteer-sign-up-sheets'),
				'disableAdminGrouping' => $this->main_options['disable_grouping'] ?? false,
				// Server-side DataTables (6.2.0).
				'serverSide'     => $server_side,
				'sheetId'        => $url_sheet_id,
				// Report Builder defaults (passed to JS for filter panel initial state).
				'rfShowExpired'  => ! empty( $this->main_options['show_expired_tasks'] ),
				'rfShowEmpty'    => true,
				'rfAllSheets'    => __( 'All Sheets', 'pta-volunteer-sign-up-sheets' ),
			);
			wp_localize_script('pta-sus-backend', 'PTASUS', $translation_array);

			// If an old version of the Customizer is active, hide its email template selects on the sheet form.
			// The main plugin now owns email templates; we keep the Customizer layout options but hide its email options UI.
			if ( defined( 'PTA_VOL_SUS_CUSTOMIZER_VERSION' )
			     && version_compare( PTA_VOL_SUS_CUSTOMIZER_VERSION, '4.1.0', '<' )
			     && class_exists( 'PTA_SUS_CUSTOMIZER_INTEGRATOR' ) ) {
				$custom_css = '
					/* Hide Customizer Email Options section on sheet form (but keep Layout Options) */
					.pta-sus.customizer h3:nth-of-type(2),
					.pta-sus.customizer h3:nth-of-type(2) ~ p {
						display: none !important;
					}
				';
				wp_add_inline_style( 'pta-admin-style', $custom_css );
			}
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
		// Check permissions - only Admins and Managers (with manage_others_signup_sheets) can access
		if (!current_user_can('manage_options') && !current_user_can('manage_others_signup_sheets'))  {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'pta-volunteer-sign-up-sheets' ) );
		}
		
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
		if (isset($_GET['action']) && 'reminders' === $_GET['action']) {
			check_admin_referer( 'pta-sus-reminders', '_sus_nonce');
			$num = PTA_SUS_Email_Functions::send_reminders();
			$results = sprintf( _n( '1 reminder sent', '%d reminders sent', $num, 'pta-volunteer-sign-up-sheets'), $num );
			$messages .= '<div class="updated">'.$results.'</div>';
		}
        if (isset($_GET['action']) && 'reschedule' === $_GET['action']) {
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
		// Handle migrate customizer templates action
		$migrate_message = '';
		if (isset($_GET['action']) && 'migrate_customizer' === $_GET['action']) {
			check_admin_referer('pta-sus-migrate-customizer', '_sus_nonce');
			$main_options = get_option('pta_volunteer_sus_main_options', array());
			$from_email = $main_options['from_email'] ?? get_option('admin_email');
			PTA_SUS_Activation::migrate_customizer_templates($from_email);
			$migrate_message = '<div class="updated"><p>' . __('Customizer email templates have been migrated. Check the Email Templates page to verify.', 'pta-volunteer-sign-up-sheets') . '</p></div>';
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
		echo '<h2>'.__('Tools', 'pta-volunteer-sign-up-sheets').'</h2>';
		echo '<h2 class="title">'.__('CRON Functions', 'pta-volunteer-sign-up-sheets').'</h2>';
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
		// Display log contents
		$log_file = WP_CONTENT_DIR . '/uploads/pta-logs/pta_debug.log';
		$log_contents = '';
		if(file_exists($log_file)) {
			$log_contents = file_get_contents($log_file);
		}
		echo '<textarea readonly style="width: 100%; height: 300px; font-family: monospace;">' . esc_textarea($log_contents) . '</textarea>';
		echo '<p><a href="' . esc_url($nonced_clear_log_url) . '" class="button-secondary">' . __('Clear Debug Log', 'pta-volunteer-sign-up-sheets') . '</a></p>';

		// Other Tools Section - only show if there are tools to display
		if (class_exists('PTA_SUS_Customizer')) {
			echo '<hr/>';
			echo '<h2 class="title">'.__('Other Tools', 'pta-volunteer-sign-up-sheets').'</h2>';

			// Migrate Customizer Templates button
			echo '<h3>'.__('Migrate Customizer Email Templates', 'pta-volunteer-sign-up-sheets').'</h3>';
			echo '<p>'.__("If you have custom email templates created in the Customizer extension that were not automatically imported during the version 6.0 update, or if you added new templates in the Customizer after upgrading to version 6.0, use this button to migrate those templates to the new Email Templates system.", "pta-volunteer-sign-up-sheets") . '</p>';
			echo '<p><strong>'.__("Note:", "pta-volunteer-sign-up-sheets").'</strong> '.__("This will import templates from the Customizer extension. Existing templates with the same name will not be duplicated.", "pta-volunteer-sign-up-sheets").'</p>';
			echo $migrate_message;
			$migrate_link = add_query_arg(array('action' => 'migrate_customizer'));
			$nonced_migrate_link = wp_nonce_url($migrate_link, 'pta-sus-migrate-customizer', '_sus_nonce');
			echo '<p><a href="'.esc_url($nonced_migrate_link).'" class="button-secondary">'.__('Migrate Customizer Templates', 'pta-volunteer-sign-up-sheets').'</a></p>';
		}

		echo '</div>'; // Close wrap div
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
				echo '<strong>' . esc_html($date) . '</strong>';
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
					if(null !== $ts) {
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
			case 'author':
				if ( empty( $sheet->author_email ) ) {
					echo '&#8212;';
					break;
				}
				$author_display = $sheet->author_email;
				$user = get_user_by( 'email', $sheet->author_email );
				if ( $user ) {
					$first = trim( $user->first_name );
					$last  = trim( $user->last_name );
					if ( ! empty( $first ) || ! empty( $last ) ) {
						$author_display = trim( $first . ' ' . $last );
					} else {
						$author_display = $user->display_name;
					}
				}
				$author_display = apply_filters( 'pta_sus_admin_signup_display_author', $author_display, $sheet );
				echo esc_html( $author_display );
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
					// Handle arrays (e.g., multi-select fields from Custom Fields extension)
					if (is_array($form_data[$key])) {
						$posted['signup_'.$key] = $form_data[$key];
					} else {
						$posted['signup_'.$key] = stripslashes( wp_kses_post( $form_data[$key]));
					}
				}
			}
		}
		
		// Admin form doesn't have validate_email field, but validation expects it
		// Set it to the same value as email since admin doesn't need email confirmation
		if (!isset($posted['signup_validate_email']) && isset($posted['signup_email'])) {
			$posted['signup_validate_email'] = $posted['signup_email'];
		}
		
		// Use validation helper class for consistent validation
		// Note: Admin form allows user_id which public form doesn't, but validation will handle other fields
		// Pass editing_signup_id when editing so validation can account for existing signup's spots
		$validation_options = $this->main_options;
		if ($edit) {
			$validation_options['editing_signup_id'] = $signup_id;
		}
		$error_count = PTA_SUS_Validation::validate_signup_fields($posted, $task, $sheet, $validation_options);
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

		// Check if we need to filter by author (for Signup Sheet Authors)
		$can_manage_others = current_user_can( 'manage_others_signup_sheets' );
		$this->filter_by_author = ! $can_manage_others; // Store for list table to use
		$this->current_author_id = $can_manage_others ? null : get_current_user_id();

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
		// Get filtered counts based on user permissions (Authors only see their own sheets)
		$can_manage_others = current_user_can( 'manage_others_signup_sheets' );
		$author_id = $can_manage_others ? null : get_current_user_id();
		
		$all_count_args = array(
			'trash' => false,
			'active_only' => false,
			'show_hidden' => true,
			'author_id' => $author_id,
		);
		$trash_count_args = array(
			'trash' => true,
			'active_only' => false,
			'show_hidden' => true,
			'author_id' => $author_id,
		);
		
		$all_count = PTA_SUS_Sheet_Functions::get_sheet_count( $all_count_args );
		$trash_count = PTA_SUS_Sheet_Functions::get_sheet_count( $trash_count_args );
		
		echo '
			<ul class="subsubsub">
			<li class="all"><a href="admin.php?page='.$this->admin_settings_slug.'_sheets"'.(($show_all) ? ' class="current"' : '').'>'.__('All ', 'pta-volunteer-sign-up-sheets').'<span class="count">('.$all_count.')</span></a> |</li>
			<li class="trash"><a href="admin.php?page='.$this->admin_settings_slug.'_sheets&amp;sheet_status=trash"'.(($show_trash) ? ' class="current"' : '').'>'.__('Trash ', 'pta-volunteer-sign-up-sheets').'<span class="count">('.$trash_count.')</span></a></li>
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
		
		// Check author permissions for editing
		if ( $edit ) {
			$sheet_id = (int) $_GET['sheet_id'];
			$sheet = pta_sus_get_sheet( $sheet_id );
			
			if ( $sheet ) {
				$current_user_id = get_current_user_id();
				$can_manage_others = current_user_can( 'manage_others_signup_sheets' );
				
				// If user doesn't have manage_others_signup_sheets and is not the author, deny access
				if ( ! $can_manage_others && $sheet->author_id != $current_user_id ) {
					wp_die( __( 'You do not have permission to edit this sheet. You can only edit sheets that you created.', 'pta-volunteer-sign-up-sheets' ) );
				}
			}
		}
		$sheet_submitted = (isset($_POST['sheet_mode']) && $_POST['sheet_mode'] === 'submitted');
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
				// Handle author assignment
				$current_user_id = get_current_user_id();
				$current_user = wp_get_current_user();
				$can_manage_others = current_user_can( 'manage_others_signup_sheets' );
				
				// Add/Update Sheet
				if ($add) {
					// For new sheets, set author to current user
					$sheet_fields['sheet_author_id'] = $current_user_id;
					$sheet_fields['sheet_author_email'] = $current_user->user_email;
					
					$sheet_id = pta_sus_add_sheet($sheet_fields);
					if(!$sheet_id) {
						$sheet_err++;
						PTA_SUS_Messages::add_error(__('Error adding sheet.', 'pta-volunteer-sign-up-sheets'));
						$sheet_fields['sheet_id'] = 0;
					} else {
						$sheet_fields['sheet_id'] = $sheet_id;
					}
				} else {
					// For updates, only allow author changes if user has manage_others_signup_sheets
					if ( $can_manage_others ) {
						// Admin/Manager can change author
						if ( isset( $_POST['sheet_author_id'] ) ) {
							$author_id = absint( $_POST['sheet_author_id'] );
							$sheet_fields['sheet_author_id'] = $author_id;
							
							// If assigning to a WordPress user (author_id > 0), fetch their email
							// If author_id is 0 (no author), use the email from form (for guest authors)
							if ( $author_id > 0 ) {
								$author_user = get_user_by( 'id', $author_id );
								if ( $author_user ) {
									$sheet_fields['sheet_author_email'] = $author_user->user_email;
								} else {
									// User doesn't exist, clear email
									$sheet_fields['sheet_author_email'] = '';
								}
							} else {
								// No author (0) - use email from form if provided (for guest authors)
								if ( isset( $_POST['sheet_author_email'] ) ) {
									$sheet_fields['sheet_author_email'] = sanitize_email( $_POST['sheet_author_email'] );
								} else {
									$sheet_fields['sheet_author_email'] = '';
								}
							}
						} elseif ( isset( $_POST['sheet_author_email'] ) ) {
							// If only email is set (guest author), set author_id to 0
							$sheet_fields['sheet_author_id'] = 0;
							$sheet_fields['sheet_author_email'] = sanitize_email( $_POST['sheet_author_email'] );
						}
					} else {
						// Author cannot change author - verify they are still the author
						$sheet = pta_sus_get_sheet( (int)$_GET['sheet_id'] );
						if ( $sheet && $sheet->author_id != $current_user_id ) {
							$sheet_err++;
							PTA_SUS_Messages::add_error(__('You do not have permission to edit this sheet.', 'pta-volunteer-sign-up-sheets'));
						}
						// Don't include author fields - they should remain unchanged
					}
					
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
		if($sheet_submitted && !$sheet_success) {
			$fields = $_POST;
		} elseif($edit_sheet || $edit_tasks || $add_tasks || $moved) {
			// Clicked on an edit action link, but nothing posted yet - Get fields from DB instead
			// Get the right sheet id
			if($sheet_success) {
				$sheet_id = (int)$sheet_fields['sheet_id'];
			} elseif ($moved) {
				$sheet_id = $new_sheet_id;
			} else {
				$sheet_id = (int)$_GET['sheet_id'];
			}
			$fields = $this->get_fields((int)$sheet_id);
		} 

		// Figure out which form to display
		if ($edit_tasks || $add_tasks || $moved) {
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
				// Email template IDs
				$task_fields['task_confirmation_email_template_id'][] = isset($task->confirmation_email_template_id) ? $task->confirmation_email_template_id : 0;
				$task_fields['task_reminder1_email_template_id'][] = isset($task->reminder1_email_template_id) ? $task->reminder1_email_template_id : 0;
				$task_fields['task_reminder2_email_template_id'][] = isset($task->reminder2_email_template_id) ? $task->reminder2_email_template_id : 0;
				$task_fields['task_clear_email_template_id'][] = isset($task->clear_email_template_id) ? $task->clear_email_template_id : 0;
				$task_fields['task_reschedule_email_template_id'][] = isset($task->reschedule_email_template_id) ? $task->reschedule_email_template_id : 0;
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

		// Sheet-level email templates (optional, overrides system defaults)
		// Only show if we have templates available
		$templates = PTA_SUS_Email_Functions::get_available_templates( true );
		if ( ! empty( $templates ) ) {
			echo '<hr />';
			echo '<h3>' . __( 'Sheet Email Templates', 'pta-volunteer-sign-up-sheets' ) . '</h3>';
			echo '<p>' . __( 'Select specific email templates for this sheet. Leave set to "Use System Default" to use the global default templates.', 'pta-volunteer-sign-up-sheets' ) . '</p>';

			// Build options array once for reuse
			$template_options = array(
				0 => __( 'Use System Default', 'pta-volunteer-sign-up-sheets' ),
			);
			foreach ( $templates as $template ) {
				$label = $template->title;
				if ( $template->is_system_default() ) {
					$label .= ' ' . __( '(System Default)', 'pta-volunteer-sign-up-sheets' );
				}
				$template_options[ $template->id ] = $label;
			}

			// Helper to render a select for a given field
			$render_template_select = function( $field_key, $label_text ) use ( $f, $template_options ) {
				$current = isset( $f[ $field_key ] ) ? absint( $f[ $field_key ] ) : 0;
				echo '<p>';
				echo '<label for="' . esc_attr( $field_key ) . '">'. esc_html( $label_text ) . '</label> ';
				echo '<select id="' . esc_attr( $field_key ) . '" name="' . esc_attr( $field_key ) . '">';
				foreach ( $template_options as $value => $label ) {
					echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
				echo '</p>';
			};

			$render_template_select(
				'sheet_confirmation_email_template_id',
				__( 'Confirmation Email Template:', 'pta-volunteer-sign-up-sheets' )
			);
			$render_template_select(
				'sheet_reminder1_email_template_id',
				__( 'Reminder 1 Email Template:', 'pta-volunteer-sign-up-sheets' )
			);
			$render_template_select(
				'sheet_reminder2_email_template_id',
				__( 'Reminder 2 Email Template:', 'pta-volunteer-sign-up-sheets' )
			);
			$render_template_select(
				'sheet_clear_email_template_id',
				__( 'Clear Email Template:', 'pta-volunteer-sign-up-sheets' )
			);
			$render_template_select(
				'sheet_reschedule_email_template_id',
				__( 'Reschedule Email Template:', 'pta-volunteer-sign-up-sheets' )
			);
		}

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

		// Prefill chair name and email with current user's info if fields are empty
		$current_user = wp_get_current_user();
		$chair_name = isset($f['sheet_chair_name']) ? $f['sheet_chair_name'] : '';
		$chair_email = isset($f['sheet_chair_email']) ? $f['sheet_chair_email'] : '';
		
		// Only prefill if fields are empty (for new sheets or when editing existing sheets with empty values)
		if ( empty( $chair_name ) && ! empty( $current_user->first_name ) ) {
			// Build name from firstname and lastname
			$chair_name = trim( $current_user->first_name . ' ' . $current_user->last_name );
		}
		if ( empty( $chair_email ) && ! empty( $current_user->user_email ) ) {
			$chair_email = $current_user->user_email;
		}
		
		echo '
			<p>
			<label for="sheet_chair_name">'.__('Chair Name(s):', 'pta-volunteer-sign-up-sheets').'</label>
	      	<input type="text" id="sheet_chair_name" name="sheet_chair_name" value="'.esc_attr( $chair_name ).'" size="80">
			<em>'.__('Separate multiple names with commas', 'pta-volunteer-sign-up-sheets').'</em>
		  	</p>
	      	<p>
		 	<label for="sheet_chair_email">'.__('Chair Email(s):', 'pta-volunteer-sign-up-sheets').'</label>
		 	<input type="text" id="sheet_chair_email" name="sheet_chair_email" value="'.esc_attr( $chair_email ).'" size="80">
		  	<em>'.__('Separate multiple emails with commas', 'pta-volunteer-sign-up-sheets').'</em>
		    </p>';
		// Allow other plugins to add fields to the form
		do_action( 'pta_sus_sheet_form_after_contact_info', $f, $edit );
		
		// Author assignment section (only show if editing or if user can manage others)
		$current_user_id = get_current_user_id();
		$current_user = wp_get_current_user();
		$can_manage_others = current_user_can( 'manage_others_signup_sheets' );
		$is_author = false;
		$sheet_author_id = isset( $f['sheet_author_id'] ) ? absint( $f['sheet_author_id'] ) : 0;
		$sheet_author_email = isset( $f['sheet_author_email'] ) ? sanitize_email( $f['sheet_author_email'] ) : '';
		
		if ( $edit && $sheet_author_id == $current_user_id ) {
			$is_author = true;
		}
		
		// Show author section if: editing and (user can manage others OR user is the author)
		if ( $edit && ( $can_manage_others || $is_author ) ) {
			echo '<hr />';
			echo '<h3>'.__('Author Information:', 'pta-volunteer-sign-up-sheets').'</h3>';
			
			if ( $can_manage_others ) {
				// Admin/Manager can edit author - show dropdown and email field
				// Get all users with manage_signup_sheets capability
				$users = get_users( array(
					'capability' => 'manage_signup_sheets',
					'orderby' => 'display_name',
				) );
				
				echo '<p>';
				echo '<label for="sheet_author_id"><strong>'.__('Author:', 'pta-volunteer-sign-up-sheets').'</strong></label><br/>';
				echo '<select id="sheet_author_id" name="sheet_author_id">';
				echo '<option value="0" '.selected( 0, $sheet_author_id, false ).'>'.__('No Author', 'pta-volunteer-sign-up-sheets').'</option>';
				foreach ( $users as $user ) {
					$selected = selected( $user->ID, $sheet_author_id, false );
					echo '<option value="'.esc_attr( $user->ID ).'" '.$selected.'>'.esc_html( $user->display_name ).' ('.esc_html( $user->user_email ).')</option>';
				}
				echo '</select>';
				echo '<em> '.__('Select the author of this sheet. Authors can only edit their own sheets.', 'pta-volunteer-sign-up-sheets').'</em>';
				echo '</p>';
				
				echo '<p>';
				echo '<label for="sheet_author_email"><strong>'.__('Author Email:', 'pta-volunteer-sign-up-sheets').'</strong></label><br/>';
				echo '<input type="email" id="sheet_author_email" name="sheet_author_email" value="'.esc_attr( $sheet_author_email ).'" size="60">';
				echo '<em> '.__('Email address for guest authors (users without WordPress accounts). Leave blank if author has a WordPress account.', 'pta-volunteer-sign-up-sheets').'</em>';
				echo '</p>';
			} else {
				// Author can only view (read-only)
				$author_name = __('No Author', 'pta-volunteer-sign-up-sheets');
				$author_email_display = '';
				
				if ( $sheet_author_id > 0 ) {
					$author_user = get_user_by( 'id', $sheet_author_id );
					if ( $author_user ) {
						$author_name = $author_user->display_name . ' (' . $author_user->user_email . ')';
					}
				} elseif ( ! empty( $sheet_author_email ) ) {
					$author_name = __('Guest Author', 'pta-volunteer-sign-up-sheets');
					$author_email_display = $sheet_author_email;
				}
				
				echo '<p>';
				echo '<strong>'.__('Author:', 'pta-volunteer-sign-up-sheets').'</strong> ';
				echo esc_html( $author_name );
				if ( ! empty( $author_email_display ) ) {
					echo ' - ' . esc_html( $author_email_display );
				}
				echo '<br/><em>'.__('You can only edit sheets that you created.', 'pta-volunteer-sign-up-sheets').'</em>';
				echo '</p>';
			}
		}
		
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
		// Include the modal structure
		include(PTA_VOLUNTEER_SUS_DIR.'views/admin-task-modal-html.php');
		// Include the new task list view
		include(PTA_VOLUNTEER_SUS_DIR.'views/admin-task-list-html.php');
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
		// Check permissions - only Admins and Managers (with manage_others_signup_sheets) can access
		if (!current_user_can('manage_options') && !current_user_can('manage_others_signup_sheets'))  {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'pta-volunteer-sign-up-sheets' ) );
		}
		
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

	/**
	 * Admin page: Email Templates
	 * 
	 * Displays the Email Templates management page with list table and add/edit forms.
	 * Allows users to create, edit, delete, and duplicate email templates.
	 * 
	 * @since 6.2.0
	 * @return void
	 */
	public function admin_email_templates_page() {
		// Check permissions
		if (!current_user_can('manage_signup_sheets')) {
			wp_die(__('You do not have sufficient permissions to access this page.', 'pta-volunteer-sign-up-sheets'));
		}

		// Form submissions are processed in admin_init() to avoid header issues
		// Messages from cookies are already retrieved by PTA_SUS_Public::init() which runs on admin pages too
		
		// Show messages
		PTA_SUS_Messages::show_messages(true, 'admin');

		// Check if we're adding or editing
		$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
		$template_id = isset($_GET['template_id']) ? absint($_GET['template_id']) : 0;

		if (in_array($action, array('add', 'edit')) && ($action !== 'edit' || $template_id > 0)) {
			// Show add/edit form
			$this->display_email_template_form($template_id, $action);
		} else {
			// Show list table
			$this->display_email_templates_list();
		}
	}

	/**
	 * Process email template form submissions
	 * 
	 * Handles add, edit, delete, and duplicate actions for email templates.
	 * 
	 * @since 6.2.0
	 * @return void
	 */
	private function process_email_template_form() {
		if (!isset($_POST['pta_email_template_mode']) || 'submitted' !== $_POST['pta_email_template_mode']) {
			return;
		}

		// Verify nonce
		if (!isset($_POST['pta_email_template_nonce']) || !wp_verify_nonce($_POST['pta_email_template_nonce'], 'pta_email_template_action')) {
			PTA_SUS_Messages::add_error(__('Invalid security token. Please try again.', 'pta-volunteer-sign-up-sheets'));
			return;
		}

		$action = isset($_POST['action']) ? sanitize_key($_POST['action']) : '';

		switch ($action) {
			case 'add':
			case 'edit':
				$this->save_email_template();
				break;
			case 'delete':
				$this->delete_email_template();
				break;
			case 'duplicate':
				$this->duplicate_email_template();
				break;
		}
	}

	/**
	 * Save email template (add or edit)
	 * 
	 * @since 6.2.0
	 * @return void
	 */
	private function save_email_template() {
		$template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
		$template = new PTA_SUS_Email_Template($template_id);

		// Check permissions
		if (!$template->can_edit()) {
			PTA_SUS_Messages::add_error(__('You do not have permission to edit this template.', 'pta-volunteer-sign-up-sheets'));
			return;
		}

		// Validate required fields
		$title = isset($_POST['template_title']) ? sanitize_text_field($_POST['template_title']) : '';
		$subject = isset($_POST['template_subject']) ? sanitize_text_field($_POST['template_subject']) : '';
		$body = isset($_POST['template_body']) ? wp_kses_post($_POST['template_body']) : '';

		if (empty($title) || empty($subject) || empty($body)) {
			PTA_SUS_Messages::add_error(__('Title, Subject, and Body are required fields.', 'pta-volunteer-sign-up-sheets'));
			return;
		}

		// Set template properties
		// Note: content_type is determined by global use_html setting, not stored per-template
		$template->title = $title;
		$template->subject = $subject;
		$template->body = $body;
		$template->from_name = isset($_POST['template_from_name']) ? sanitize_text_field($_POST['template_from_name']) : '';
		// Allow {chair_email} tag through — pta_sanitize_value() handles this on save
		$from_email_raw = isset($_POST['template_from_email']) ? trim( sanitize_text_field($_POST['template_from_email']) ) : '';
		$template->from_email = ( '{chair_email}' === $from_email_raw ) ? '{chair_email}' : sanitize_email( $from_email_raw );
		$reply_to_raw = isset($_POST['template_reply_to']) ? trim( sanitize_text_field($_POST['template_reply_to']) ) : '';
		$template->reply_to = ( '{chair_email}' === $reply_to_raw ) ? '{chair_email}' : sanitize_email( $reply_to_raw );

		// Handle author assignment (only for Admins/Managers)
		if (current_user_can('manage_others_signup_sheets')) {
			$author_id = isset($_POST['template_author_id']) ? absint($_POST['template_author_id']) : 0;
			$template->author_id = $author_id;
		} else {
			// Authors can only set their own author_id on new templates
			if ($template_id === 0) {
				$template->author_id = get_current_user_id();
			}
		}

		// Save template first to get the ID
		$saved_id = $template->save();
		
		// Handle system default assignment (only for Admins/Managers, after template is saved)
		if ($saved_id > 0 && current_user_can('manage_others_signup_sheets')) {
			$system_default_email_type = isset($_POST['template_system_default_email_type']) ? sanitize_text_field($_POST['template_system_default_email_type']) : '';
			
			// Get current system defaults
			$defaults = get_option('pta_volunteer_sus_email_template_defaults', array());
			
			// Remove this template from any email type it's currently set as default for
			// Check both old template_id (for edits) and new saved_id
			foreach ($defaults as $email_type => $default_template_id) {
				if ($default_template_id == $saved_id || ($template_id > 0 && $default_template_id == $template_id)) {
					unset($defaults[$email_type]);
				}
			}
			
			// Set this template as system default for the selected email type (if any)
			// Validate against filterable email types list
			$valid_email_types = array_keys(PTA_SUS_Email_Functions::get_email_types());
			if (!empty($system_default_email_type) && in_array($system_default_email_type, $valid_email_types, true)) {
				$defaults[$system_default_email_type] = $saved_id;
			}
			
			// Update system defaults option
			update_option('pta_volunteer_sus_email_template_defaults', $defaults);
		}
		if ($saved_id > 0) {
			$message = $template_id > 0 ? __('Email template updated successfully.', 'pta-volunteer-sign-up-sheets') : __('Email template created successfully.', 'pta-volunteer-sign-up-sheets');
			PTA_SUS_Messages::add_message($message);
			// Redirect to list after successful save (preserves messages in cookie)
			$redirect_url = admin_url('admin.php?page=' . $this->admin_settings_slug . '_email_templates');
			pta_clean_redirect($redirect_url);
		} else {
			PTA_SUS_Messages::add_error(__('Error saving email template. Please try again.', 'pta-volunteer-sign-up-sheets'));
		}
	}

	/**
	 * Delete email template
	 * 
	 * @since 6.2.0
	 * @return void
	 */
	private function delete_email_template() {
		$template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
		if ($template_id === 0) {
			PTA_SUS_Messages::add_error(__('Invalid template ID.', 'pta-volunteer-sign-up-sheets'));
			return;
		}

		$template = new PTA_SUS_Email_Template($template_id);
		if ($template->id === 0) {
			PTA_SUS_Messages::add_error(__('Template not found.', 'pta-volunteer-sign-up-sheets'));
			return;
		}

		if (!$template->can_delete()) {
			PTA_SUS_Messages::add_error(__('You do not have permission to delete this template.', 'pta-volunteer-sign-up-sheets'));
			return;
		}

		if ($template->delete()) {
			PTA_SUS_Messages::add_message(__('Email template deleted successfully.', 'pta-volunteer-sign-up-sheets'));
		} else {
			PTA_SUS_Messages::add_error(__('Error deleting email template.', 'pta-volunteer-sign-up-sheets'));
		}
	}

	/**
	 * Duplicate email template
	 * 
	 * @since 6.2.0
	 * @return void
	 */
	private function duplicate_email_template() {
		$template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
		if ($template_id === 0) {
			PTA_SUS_Messages::add_error(__('Invalid template ID.', 'pta-volunteer-sign-up-sheets'));
			return;
		}

		$original = new PTA_SUS_Email_Template($template_id);
		if ($original->id === 0) {
			PTA_SUS_Messages::add_error(__('Template not found.', 'pta-volunteer-sign-up-sheets'));
			return;
		}

		// Create new template from original
		$new_template = new PTA_SUS_Email_Template();
		$new_template->title = $original->title . ' ' . __('(Copy)', 'pta-volunteer-sign-up-sheets');
		$new_template->subject = $original->subject;
		$new_template->body = $original->body;
		// Note: content_type is determined by global use_html setting, not stored per-template
		$new_template->from_name = $original->from_name;
		$new_template->from_email = $original->from_email;
		$new_template->author_id = get_current_user_id(); // Duplicate belongs to current user
		// Note: System defaults are determined by the option, not a database field

		$new_id = $new_template->save();
		if ($new_id > 0) {
			PTA_SUS_Messages::add_message(__('Email template duplicated successfully.', 'pta-volunteer-sign-up-sheets'));
			// Store messages in cookies and redirect to edit the new template
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
			wp_safe_redirect(admin_url('admin.php?page=' . $this->admin_settings_slug . '_email_templates&action=edit&template_id=' . $new_id));
			exit;
		}

        PTA_SUS_Messages::add_error(__('Error duplicating email template.', 'pta-volunteer-sign-up-sheets'));
    }

	/**
	 * Display email templates list table
	 * 
	 * @since 6.2.0
	 * @return void
	 */
	private function display_email_templates_list() {
		// Get available templates
		$templates = PTA_SUS_Email_Functions::get_available_templates(true);
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php _e('Email Templates', 'pta-volunteer-sign-up-sheets'); ?></h1>
			<a href="<?php echo admin_url('admin.php?page=' . $this->admin_settings_slug . '_email_templates&action=add'); ?>" class="page-title-action"><?php _e('Add New', 'pta-volunteer-sign-up-sheets'); ?></a>
			<hr class="wp-header-end">

			<?php if (empty($templates)) : ?>
				<p><?php _e('No email templates found.', 'pta-volunteer-sign-up-sheets'); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col"><?php _e('Title', 'pta-volunteer-sign-up-sheets'); ?></th>
							<th scope="col"><?php _e('Subject', 'pta-volunteer-sign-up-sheets'); ?></th>
							<th scope="col"><?php _e('Content Type', 'pta-volunteer-sign-up-sheets'); ?></th>
							<th scope="col"><?php _e('Author', 'pta-volunteer-sign-up-sheets'); ?></th>
							<th scope="col"><?php _e('Type', 'pta-volunteer-sign-up-sheets'); ?></th>
							<th scope="col"><?php _e('Actions', 'pta-volunteer-sign-up-sheets'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($templates as $template) : ?>
							<tr>
								<td><strong><?php echo esc_html($template->title); ?></strong></td>
								<td><?php echo esc_html($template->subject); ?></td>
								<td>
								<?php
								$email_options = get_option('pta_volunteer_sus_email_options', array());
								$use_html = isset($email_options['use_html']) && $email_options['use_html'];
								echo $use_html ? __('HTML', 'pta-volunteer-sign-up-sheets') : __('Plain Text', 'pta-volunteer-sign-up-sheets');
								?>
							</td>
								<td>
									<?php
									if ($template->author_id > 0) {
										$author = get_user_by('id', $template->author_id);
										echo $author ? esc_html($author->display_name) : __('Unknown', 'pta-volunteer-sign-up-sheets');
									} else {
										_e('Available to All', 'pta-volunteer-sign-up-sheets');
									}
									?>
								</td>
								<td>
									<?php
									if ($template->is_system_default()) {
										echo '<span class="dashicons dashicons-admin-settings" title="' . esc_attr__('System Default', 'pta-volunteer-sign-up-sheets') . '"></span> ' . __('System Default', 'pta-volunteer-sign-up-sheets');
									} else {
										_e('Custom', 'pta-volunteer-sign-up-sheets');
									}
									?>
								</td>
								<td>
									<?php if ($template->can_edit()) : ?>
										<a href="<?php echo admin_url('admin.php?page=' . $this->admin_settings_slug . '_email_templates&action=edit&template_id=' . $template->id); ?>"><?php _e('Edit', 'pta-volunteer-sign-up-sheets'); ?></a> |
									<?php endif; ?>
									<?php if ($template->can_delete()) : ?>
										<form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this template?', 'pta-volunteer-sign-up-sheets'); ?>');">
											<?php wp_nonce_field('pta_email_template_action', 'pta_email_template_nonce'); ?>
											<input type="hidden" name="pta_email_template_mode" value="submitted">
											<input type="hidden" name="action" value="delete">
											<input type="hidden" name="template_id" value="<?php echo $template->id; ?>">
											<button type="submit" class="button-link" style="color:#b32d2e;"><?php _e('Delete', 'pta-volunteer-sign-up-sheets'); ?></button>
										</form> |
									<?php endif; ?>
									<form method="post" style="display:inline;">
										<?php wp_nonce_field('pta_email_template_action', 'pta_email_template_nonce'); ?>
										<input type="hidden" name="pta_email_template_mode" value="submitted">
										<input type="hidden" name="action" value="duplicate">
										<input type="hidden" name="template_id" value="<?php echo $template->id; ?>">
										<button type="submit" class="button-link"><?php _e('Duplicate', 'pta-volunteer-sign-up-sheets'); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Display email template add/edit form
	 * 
	 * @since 6.2.0
	 * @param int $template_id Template ID (0 for new template)
	 * @param string $action Action ('add' or 'edit')
	 * @return void
	 */
	private function display_email_template_form($template_id, $action) {
		$template = new PTA_SUS_Email_Template($template_id);
		$is_edit = ($action === 'edit' && $template_id > 0);

		// Check permissions
		if ($is_edit && !$template->can_edit()) {
			wp_die(__('You do not have permission to edit this template.', 'pta-volunteer-sign-up-sheets'));
		}

		$use_html = isset($this->email_options['use_html']) && $this->email_options['use_html'];
		$can_manage_others = current_user_can('manage_others_signup_sheets');
		?>
		<div class="wrap">
			<h1><?php echo $is_edit ? __('Edit Email Template', 'pta-volunteer-sign-up-sheets') : __('Add New Email Template', 'pta-volunteer-sign-up-sheets'); ?></h1>
            <?php PTA_SUS_Template_Tags_Helper::render_helper_panel(); ?>
			<form method="post" action="">
				<?php wp_nonce_field('pta_email_template_action', 'pta_email_template_nonce'); ?>
				<input type="hidden" name="pta_email_template_mode" value="submitted">
				<input type="hidden" name="action" value="<?php echo $is_edit ? 'edit' : 'add'; ?>">
				<?php if ($is_edit) : ?>
					<input type="hidden" name="template_id" value="<?php echo $template->id; ?>">
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="template_title"><?php _e('Title', 'pta-volunteer-sign-up-sheets'); ?> <span class="required">*</span></label></th>
						<td>
							<input type="text" id="template_title" name="template_title" value="<?php echo esc_attr($template->title); ?>" class="regular-text" required />
							<p class="description"><?php _e('A descriptive name for this template (for admin use only).', 'pta-volunteer-sign-up-sheets'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="template_subject"><?php _e('Subject', 'pta-volunteer-sign-up-sheets'); ?> <span class="required">*</span></label></th>
						<td>
							<input type="text" id="template_subject" name="template_subject" value="<?php echo esc_attr($template->subject); ?>" class="regular-text" required />
							<p class="description"><?php _e('Email subject line. You can use template tags like {firstname}, {lastname}, {sheet_title}, etc.', 'pta-volunteer-sign-up-sheets'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="template_body"><?php _e('Body', 'pta-volunteer-sign-up-sheets'); ?> <span class="required">*</span></label></th>
						<td>
							<?php if ($use_html) : ?>
								<?php
								$editor_id = 'template_body';
								$editor_content = wp_kses_post($template->body);
								$editor_settings = array(
									'wpautop' => true,
									'media_buttons' => false,
									'textarea_name' => 'template_body',
									'textarea_rows' => 15,
									'teeny' => false,
									'quicktags' => true,
									'tinymce' => true,
								);
								wp_editor($editor_content, $editor_id, $editor_settings);
								?>
							<?php else : ?>
								<textarea id="template_body" name="template_body" rows="15" class="large-text" required><?php echo esc_textarea($template->body); ?></textarea>
							<?php endif; ?>
							<p class="description"><?php _e('Email body content. You can use template tags like {firstname}, {lastname}, {sheet_title}, etc.', 'pta-volunteer-sign-up-sheets'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="template_from_name"><?php _e('From Name', 'pta-volunteer-sign-up-sheets'); ?></label></th>
						<td>
							<input type="text" id="template_from_name" name="template_from_name" value="<?php echo esc_attr($template->from_name); ?>" class="regular-text" />
							<p class="description"><?php printf( __('Optional custom "From" name. Leave blank to use default. Use %s to dynamically use the sheet chair\'s name.', 'pta-volunteer-sign-up-sheets'), '<code>{chair_name}</code>' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="template_from_email"><?php _e('From Email', 'pta-volunteer-sign-up-sheets'); ?></label></th>
						<td>
							<input type="text" id="template_from_email" name="template_from_email" value="<?php echo esc_attr($template->from_email); ?>" class="regular-text" />
							<p class="description"><?php printf( __('Optional custom "From" email address. Leave blank to use default. Use %s to dynamically use the sheet chair\'s email.', 'pta-volunteer-sign-up-sheets'), '<code>{chair_email}</code>' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="template_reply_to"><?php _e('Reply-To Email', 'pta-volunteer-sign-up-sheets'); ?></label></th>
						<td>
							<input type="text" id="template_reply_to" name="template_reply_to" value="<?php echo esc_attr($template->reply_to); ?>" class="regular-text" />
							<p class="description"><?php printf( __('Optional Reply-To email address. Leave blank to use the global Reply-To settings. Use %s to dynamically use the sheet chair\'s email.', 'pta-volunteer-sign-up-sheets'), '<code>{chair_email}</code>' ); ?></p>
						</td>
					</tr>
					<?php if ($can_manage_others) : ?>
						<tr>
							<th scope="row"><label for="template_system_default_email_type"><?php _e('System Default', 'pta-volunteer-sign-up-sheets'); ?></label></th>
							<td>
								<?php
								// Get current system defaults
								$defaults = get_option('pta_volunteer_sus_email_template_defaults', array());
								$current_default_for = '';
								foreach ($defaults as $email_type => $default_template_id) {
									if ($default_template_id == $template->id) {
										$current_default_for = $email_type;
										break;
									}
								}
								
								// Get email types (filterable by extensions)
								$email_types = PTA_SUS_Email_Functions::get_email_types();
								$email_type_labels = array('' => __('None (Not a System Default)', 'pta-volunteer-sign-up-sheets'));
								$email_type_labels = array_merge($email_type_labels, $email_types);
								?>
								<select id="template_system_default_email_type" name="template_system_default_email_type">
									<?php foreach ($email_type_labels as $email_type => $label) : ?>
										<option value="<?php echo esc_attr($email_type); ?>" <?php selected($current_default_for, $email_type); ?>>
											<?php echo esc_html($label); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php _e('Select which email type this template should be the system default for. Only one template can be the system default per email type. Setting this will replace any existing system default for the selected email type.', 'pta-volunteer-sign-up-sheets'); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="template_author_id"><?php _e('Author', 'pta-volunteer-sign-up-sheets'); ?></label></th>
							<td>
								<?php
								$args = array(
									'role__in' => array('administrator', 'signup_sheet_manager', 'signup_sheet_author'),
									'capability' => 'manage_signup_sheets',
									'orderby' => 'display_name',
									'order' => 'ASC',
								);
								$users = get_users($args);
								?>
								<select id="template_author_id" name="template_author_id">
									<option value="0" <?php selected($template->author_id, 0); ?>><?php _e('Available to All', 'pta-volunteer-sign-up-sheets'); ?></option>
									<?php foreach ($users as $user) : ?>
										<option value="<?php echo $user->ID; ?>" <?php selected($template->author_id, $user->ID); ?>><?php echo esc_html($user->display_name); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php _e('Assign this template to a specific user, or make it available to all users.', 'pta-volunteer-sign-up-sheets'); ?></p>
							</td>
						</tr>
					<?php else : ?>
						<tr>
							<th scope="row"><?php _e('Author', 'pta-volunteer-sign-up-sheets'); ?></th>
							<td>
								<?php
								if ($template->author_id > 0) {
									$author = get_user_by('id', $template->author_id);
									echo $author ? esc_html($author->display_name) : __('Unknown', 'pta-volunteer-sign-up-sheets');
								} else {
									_e('Available to All', 'pta-volunteer-sign-up-sheets');
								}
								?>
							</td>
						</tr>
					<?php endif; ?>
				</table>

				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo $is_edit ? esc_attr__('Update Template', 'pta-volunteer-sign-up-sheets') : esc_attr__('Create Template', 'pta-volunteer-sign-up-sheets'); ?>">
					<a href="<?php echo admin_url('admin.php?page=' . $this->admin_settings_slug . '_email_templates'); ?>" class="button"><?php _e('Cancel', 'pta-volunteer-sign-up-sheets'); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Save task via AJAX
	 * 
	 * AJAX handler to save a single task (create or update)
	 * 
	 * @since 6.2.0
	 * @return void Sends JSON response and exits
	 */
	public function ajax_save_task() {
		check_ajax_referer( 'pta_sus_task_ajax', 'nonce' );
		
		if ( ! current_user_can( 'manage_signup_sheets' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'pta-volunteer-sign-up-sheets' ) ) );
		}
		
		$sheet_id = isset( $_POST['task_sheet_id'] ) ? absint( $_POST['task_sheet_id'] ) : 0;
		$task_id = isset( $_POST['task_id'] ) ? absint( $_POST['task_id'] ) : 0;
		$sheet_type = isset( $_POST['task_sheet_type'] ) ? sanitize_text_field( $_POST['task_sheet_type'] ) : '';
		$no_signups = isset( $_POST['task_no_signups'] ) ? absint( $_POST['task_no_signups'] ) : 0;
		
		if ( $sheet_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid sheet ID.', 'pta-volunteer-sign-up-sheets' ) ) );
		}
		
		// Build task data array from POST
		$task_data = array();
		$task = new PTA_SUS_Task();
		$task_properties = $task->get_properties();
		
		foreach ( $task_properties as $field => $nothing ) {
			$post_key = 'task_' . $field;
			if ( isset( $_POST[ $post_key ] ) ) {
				$task_data[ $post_key ] = $_POST[ $post_key ];
			} elseif ( in_array( $field, array( 'need_details', 'details_required', 'allow_duplicates', 'enable_quantities' ) ) ) {
				// Set to NO if checkbox not checked
				$task_data[ $post_key ] = 'NO';
			}
		}
		
		// Handle dates based on sheet type
		if ( "Single" === $sheet_type ) {
			// First try POST data, then check sheet's stored dates if no tasks exist
			$single_date = isset( $_POST['single_date'] ) ? sanitize_text_field( $_POST['single_date'] ) : '';
			if ( empty( $single_date ) ) {
				// Check if sheet has stored date (for new sheets with no tasks)
				$sheet = pta_sus_get_sheet( $sheet_id );
				if ( $sheet && ! empty( $sheet->first_date ) && $sheet->first_date !== '0000-00-00' ) {
					$single_date = $sheet->first_date;
				}
			}
			if ( empty( $single_date ) ) {
				PTA_SUS_Messages::add_error( __( 'Please set a date for this sheet before saving tasks. Use the "Save Date" button above the task list.', 'pta-volunteer-sign-up-sheets' ) );
				wp_send_json_error( array( 'message' => PTA_SUS_Messages::show_messages( false, 'admin' ) ) );
			}
			if ( false === pta_sus_check_date( $single_date ) ) {
				PTA_SUS_Messages::add_error( __( 'Invalid date format.', 'pta-volunteer-sign-up-sheets' ) );
				wp_send_json_error( array( 'message' => PTA_SUS_Messages::show_messages( false, 'admin' ) ) );
			}
			$task_data['task_dates'] = $single_date;
		} elseif ( "Recurring" === $sheet_type ) {
			// First try POST data, then check if we can reconstruct from sheet dates
			$recurring_dates = isset( $_POST['recurring_dates'] ) ? sanitize_text_field( $_POST['recurring_dates'] ) : '';
			if ( empty( $recurring_dates ) ) {
				// For Recurring, we can't fully reconstruct from first_date/last_date alone
				// User will need to re-enter dates, or we'll need sheet_dates field in Phase 2
				PTA_SUS_Messages::add_error( __( 'Please set dates for this sheet before saving tasks. Use the "Save Dates" button above the task list.', 'pta-volunteer-sign-up-sheets' ) );
				wp_send_json_error( array( 'message' => PTA_SUS_Messages::show_messages( false, 'admin' ) ) );
			}
			$dates = pta_sus_sanitize_dates( $recurring_dates );
			if ( count( $dates ) < 2 ) {
				PTA_SUS_Messages::add_error( __( 'Recurring events require at least two dates.', 'pta-volunteer-sign-up-sheets' ) );
				wp_send_json_error( array( 'message' => PTA_SUS_Messages::show_messages( false, 'admin' ) ) );
			}
			$task_data['task_dates'] = implode( ",", $dates );
		} elseif ( "Ongoing" === $sheet_type ) {
			// Ongoing sheets always use '0000-00-00'
			$task_data['task_dates'] = '0000-00-00';
		} elseif ( "Multi-Day" === $sheet_type ) {
			// Date is handled in the form field
			if ( isset( $_POST['task_dates'] ) ) {
				$task_date = sanitize_text_field( $_POST['task_dates'] );
				if ( empty( $task_date ) ) {
					PTA_SUS_Messages::add_error( __( 'Date is required for Multi-Day sheet tasks.', 'pta-volunteer-sign-up-sheets' ) );
					wp_send_json_error( array( 'message' => PTA_SUS_Messages::show_messages( false, 'admin' ) ) );
				}
				if ( false === pta_sus_check_date( $task_date ) ) {
					PTA_SUS_Messages::add_error( __( 'Invalid date format.', 'pta-volunteer-sign-up-sheets' ) );
					wp_send_json_error( array( 'message' => PTA_SUS_Messages::show_messages( false, 'admin' ) ) );
				}
				
				// Check if date is being changed and task has signups for old date
				if ( $task_id > 0 ) {
					$old_task = pta_sus_get_task( $task_id );
					if ( $old_task && $old_task->dates !== $task_date ) {
						// Date is being changed - check for signups on old date
						$main_options = get_option( 'pta_volunteer_sus_main_options', array() );
						$skip_signups_check = isset( $main_options['skip_signups_check'] ) && true == $main_options['skip_signups_check'];
						
						if ( ! $skip_signups_check && PTA_SUS_Task_Functions::task_has_signups( $task_id, $old_task->dates ) ) {
							PTA_SUS_Messages::add_error( sprintf( 
								__( 'Cannot change the date for this task because it has signups on date %s. Please clear signups first, or use the reschedule function.', 'pta-volunteer-sign-up-sheets' ),
								esc_html( $old_task->dates )
							) );
							wp_send_json_error( array( 'message' => PTA_SUS_Messages::show_messages( false, 'admin' ) ) );
						}
					}
				}
				
				$task_data['task_dates'] = $task_date;
			} else {
				PTA_SUS_Messages::add_error( __( 'Date is required for Multi-Day sheet tasks.', 'pta-volunteer-sign-up-sheets' ) );
				wp_send_json_error( array( 'message' => PTA_SUS_Messages::show_messages( false, 'admin' ) ) );
			}
		}
		
		// Validate task fields
		$clean_task_fields = pta_sus_clean_prefixed_array( $task_data, 'task_' );
		if ( false === $clean_task_fields ) {
			PTA_SUS_Messages::add_error( __( 'Invalid task data.', 'pta-volunteer-sign-up-sheets' ) );
			wp_send_json_error( array( 'message' => PTA_SUS_Messages::show_messages( false, 'admin' ) ) );
		}
		
		$results = PTA_SUS_Task_Functions::validate_task_fields( $clean_task_fields );
		if ( ! empty( $results['errors'] ) ) {
			// Errors are already added to PTA_SUS_Messages by validation
			wp_send_json_error( array( 'message' => PTA_SUS_Messages::show_messages( false, 'admin' ) ) );
		}
		
		// Normalize POST data for extensions that expect array format (index 0 for modal)
		// Extensions like Custom Fields expect $_POST['task_template_id'][0], $_POST['task_$slug'][0], etc.
		$index = 0; // Modal always uses index 0 since there's only one task
		$normalized_post = $_POST;
		
		// Standard task fields that should NOT be normalized (they're handled separately)
		$standard_fields = array( 'task_id', 'task_title', 'task_description', 'task_dates', 'task_qty', 'task_time_start', 'task_time_end', 
			'task_need_details', 'task_details_required', 'task_details_text', 'task_allow_duplicates', 'task_enable_quantities',
			'task_confirmation_email_template_id', 'task_reminder1_email_template_id', 'task_reminder2_email_template_id',
			'task_clear_email_template_id', 'task_reschedule_email_template_id', 'task_sheet_id', 'task_sheet_type', 'task_no_signups',
			'action', 'nonce', 'single_date', 'recurring_dates' );
		
		// Normalize task_template_id if present (not already in array format)
		if ( isset( $_POST['task_template_id'] ) && ! is_array( $_POST['task_template_id'] ) ) {
			$normalized_post['task_template_id'] = array( $index => absint( $_POST['task_template_id'] ) );
		}
		
		// Normalize any task_ prefixed custom fields (for extensions like Custom Fields)
		// These fields might come in as simple names (task_$slug) or already in array format (task_$slug[0])
		foreach ( $_POST as $key => $value ) {
			// Skip if already in standard fields list
			if ( in_array( $key, $standard_fields ) ) {
				continue;
			}
			
			// Only process fields that start with 'task_'
			if ( strpos( $key, 'task_' ) === 0 ) {
				// If value is already an array, it's already in the correct format (e.g., from form fields with name="task_$slug[0]")
				if ( is_array( $value ) ) {
					// Already normalized, keep as is
					continue;
				}
				
				// This is a simple field name (not array) - normalize to array format
				// This handles cases where the extension outputs fields without array notation
				$normalized_post[ $key ] = array( $index => $value );
			}
		}
		
		// Temporarily replace $_POST so extensions can read normalized data
		$original_post = $_POST;
		$_POST = $normalized_post;
		
		// Save task
		if ( $task_id > 0 ) {
			// Update existing task
			$result = pta_sus_update_task( $task_data, $task_id, $no_signups );
			if ( false === $result ) {
				$_POST = $original_post; // Restore original POST
				PTA_SUS_Messages::add_error( __( 'Error updating task.', 'pta-volunteer-sign-up-sheets' ) );
				wp_send_json_error( array( 'message' => PTA_SUS_Messages::show_messages( false, 'admin' ) ) );
			}
			
			// BACKWARDS COMPATIBILITY: Fire old hook with indexed array format
			// TODO: Remove after extensions are updated
			do_action( 'pta_sus_update_task', $task_data, $sheet_id, $task_id, $index );
			
			$action = 'updated';
			PTA_SUS_Messages::add_message( __( 'Task updated successfully.', 'pta-volunteer-sign-up-sheets' ) );
		} else {
			// Create new task
			$new_task_id = pta_sus_add_task( $task_data, $sheet_id, $no_signups );
			if ( false === $new_task_id ) {
				$_POST = $original_post; // Restore original POST
				PTA_SUS_Messages::add_error( __( 'Error creating task.', 'pta-volunteer-sign-up-sheets' ) );
				wp_send_json_error( array( 'message' => PTA_SUS_Messages::show_messages( false, 'admin' ) ) );
			}
			$task_id = $new_task_id;
			
			// BACKWARDS COMPATIBILITY: Fire old hook with indexed array format
			// TODO: Remove after extensions are updated
			do_action( 'pta_sus_add_task', $task_data, $sheet_id, $task_id, $index );
			
			$action = 'created';
			PTA_SUS_Messages::add_message( __( 'Task created successfully.', 'pta-volunteer-sign-up-sheets' ) );
		}
		
		// Restore original POST
		$_POST = $original_post;

		// Update sheet first_date and last_date for Multi-Day sheets only
		// (Single, Recurring, and Ongoing dates are managed via ajax_save_sheet_dates)
		if ( 'Multi-Day' === $sheet_type ) {
			$sheet = pta_sus_get_sheet( $sheet_id );
			if ( $sheet ) {
				$all_task_dates = PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet( $sheet_id );
				if ( ! empty( $all_task_dates ) ) {
					// Filter out '0000-00-00' dates
					$valid_dates = array_filter( $all_task_dates, function( $date ) {
						return $date !== '0000-00-00' && ! empty( $date );
					} );

					if ( ! empty( $valid_dates ) ) {
						sort( $valid_dates );
						$min_date = min( $valid_dates );
						$max_date = max( $valid_dates );
						$needs_update = false;

						if ( $sheet->first_date != $min_date ) {
							$sheet->first_date = $min_date;
							$needs_update = true;
						}
						if ( $sheet->last_date != $max_date ) {
							$sheet->last_date = $max_date;
							$needs_update = true;
						}
						if ( $needs_update ) {
							$sheet->save();
						}
					}
				}
			}
		}
		
		// Get updated task data for response
		$saved_task = pta_sus_get_task( $task_id );
		if ( ! $saved_task ) {
			PTA_SUS_Messages::add_error( __( 'Task saved but could not retrieve updated data.', 'pta-volunteer-sign-up-sheets' ) );
			wp_send_json_error( array( 'message' => PTA_SUS_Messages::show_messages( false, 'admin' ) ) );
		}
		
		/**
		 * NEW ACTION (6.0.0+): Task saved via modal form (fired after task is saved and retrieved)
		 * 
		 * Extensions should read field values directly from $_POST (no array notation needed)
		 * Field names should be simple (e.g., "task_template_id", not "task_template_id[0]")
		 * This hook fires for both created and updated tasks, after the task object is available
		 * 
		 * @param int $task_id Task ID
		 * @param int $sheet_id Sheet ID
		 * @param object $task Task object (PTA_SUS_Task instance)
		 * @param string $action Either 'created' or 'updated'
		 */
		do_action( 'pta_sus_task_saved', $task_id, $sheet_id, $saved_task, $action );
		
		// Return full task data for JavaScript to build table row (using task_ prefix)
		$task_response_data = array(
			'task_id' => $saved_task->id,
			'task_title' => stripslashes($saved_task->title),
			'task_description' => stripslashes($saved_task->description),
			'task_dates' => $saved_task->dates,
			'task_qty' => $saved_task->qty,
			'task_time_start' => $saved_task->time_start,
			'task_time_end' => $saved_task->time_end,
			'task_position' => isset( $saved_task->position ) ? $saved_task->position : 0,
		);
		
		wp_send_json_success( array(
			'message' => PTA_SUS_Messages::show_messages( false, 'admin' ),
			'task_id' => $task_id,
			'action' => $action,
			'task' => $task_response_data,
		) );
	}

	/**
	 * Get task data via AJAX
	 * 
	 * AJAX handler to retrieve task data for editing
	 * 
	 * @since 6.2.0
	 * @return void Sends JSON response and exits
	 */
	public function ajax_get_task() {
		check_ajax_referer( 'pta_sus_task_ajax', 'nonce' );
		
		if ( ! current_user_can( 'manage_signup_sheets' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'pta-volunteer-sign-up-sheets' ) ) );
		}
		
		$task_id = isset( $_POST['task_id'] ) ? absint( $_POST['task_id'] ) : 0;
		
		if ( $task_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid task ID.', 'pta-volunteer-sign-up-sheets' ) ) );
		}
		
		$task = pta_sus_get_task( $task_id );
		if ( ! $task ) {
			wp_send_json_error( array( 'message' => __( 'Task not found.', 'pta-volunteer-sign-up-sheets' ) ) );
		}
		
		// Check if task has signups (for Multi-Day tasks, to make date readonly)
		$task_has_signups = false;
		$sheet = pta_sus_get_sheet( $task->sheet_id );
		if ( $sheet && 'Multi-Day' === $sheet->type && ! empty( $task->dates ) ) {
			$task_has_signups = PTA_SUS_Task_Functions::task_has_signups( $task_id, $task->dates );
		}
		
		// Build base task data array (using task_ prefix to match JavaScript expectations)
		$task_data = array(
			'task_id' => array( $task->id ), // Extension expects array format
			'task_title' => stripslashes($task->title),
			'task_description' => stripslashes($task->description),
			'task_dates' => $task->dates,
			'task_qty' => $task->qty,
			'task_time_start' => $task->time_start,
			'task_time_end' => $task->time_end,
			'task_need_details' => $task->need_details,
			'task_details_required' => $task->details_required,
			'task_details_text' => stripslashes($task->details_text),
			'task_allow_duplicates' => $task->allow_duplicates,
			'task_enable_quantities' => $task->enable_quantities,
			'task_position' => $task->position ?? 0,
			'task_confirmation_email_template_id' => $task->confirmation_email_template_id ?? 0,
			'task_reminder1_email_template_id' => $task->reminder1_email_template_id ?? 0,
			'task_reminder2_email_template_id' => $task->reminder2_email_template_id ?? 0,
			'task_clear_email_template_id' => $task->clear_email_template_id ?? 0,
			'task_reschedule_email_template_id' => $task->reschedule_email_template_id ?? 0,
			'task_has_signups' => $task_has_signups,
		);
		
		// BACKWARDS COMPATIBILITY: Apply old filter for extensions using indexed array format
		// Extension expects task_id as array and will add task_template_id[0], task_$slug[0], etc.
		// TODO: Remove after extensions are updated to use new filter
		$task_data = apply_filters( 'pta_sus_admin_get_fields', $task_data, $task->sheet_id );
		
		/**
		 * NEW FILTER (6.0.0+): Get extension field data for a single task
		 * 
		 * Extensions should return field values as simple key-value pairs (not arrays)
		 * Example: array('task_template_id' => 123, 'task_custom_field' => 'value')
		 * 
		 * @param array $field_data Array of field values (key => value)
		 * @param int $task_id Task ID
		 * @param int $sheet_id Sheet ID
		 * @param object $task Task object
		 * @return array Modified field data array
		 */
		$extension_fields = apply_filters( 'pta_sus_task_get_extension_fields', array(), $task_id, $task->sheet_id, $task );
		
		// Merge new extension fields into task data (overrides old array format if present)
		if ( ! empty( $extension_fields ) ) {
			foreach ( $extension_fields as $key => $value ) {
				$task_data[ $key ] = $value;
			}
		}
		
		// Convert extension field data from array format to single values for modal (index 0)
		// Extensions return arrays like task_template_id[0], task_$slug[0], but modal needs single values
		$modal_task_data = array(
			'task_id' => $task->id,
			'task_title' => stripslashes($task->title),
			'task_description' => stripslashes($task->description),
			'task_dates' => $task->dates,
			'task_qty' => $task->qty,
			'task_time_start' => $task->time_start,
			'task_time_end' => $task->time_end,
			'task_need_details' => $task->need_details,
			'task_details_required' => $task->details_required,
			'task_details_text' => stripslashes($task->details_text),
			'task_allow_duplicates' => $task->allow_duplicates,
			'task_enable_quantities' => $task->enable_quantities,
			'task_position' => $task->position ?? 0,
			'task_confirmation_email_template_id' => $task->confirmation_email_template_id ?? 0,
			'task_reminder1_email_template_id' => $task->reminder1_email_template_id ?? 0,
			'task_reminder2_email_template_id' => $task->reminder2_email_template_id ?? 0,
			'task_clear_email_template_id' => $task->clear_email_template_id ?? 0,
			'task_reschedule_email_template_id' => $task->reschedule_email_template_id ?? 0,
			'task_has_signups' => $task_has_signups,
		);
		
		// Extract extension fields from array format (index 0) to single values
		// The extension returns arrays like: task_template_id => array(0 => 'value')
		foreach ( $task_data as $key => $value ) {
			if ( is_array( $value ) ) {
				// Check if it's a numeric array with index 0 (extension field format)
				if ( isset( $value[0] ) ) {
					// Extract index 0 value
					$modal_task_data[ $key ] = $value[0];
				} elseif ( ! empty( $value ) ) {
					// Non-numeric array, use as-is (shouldn't happen for extension fields)
					$modal_task_data[ $key ] = $value;
				} else {
					// Empty array, set to empty string
					$modal_task_data[ $key ] = '';
				}
			} elseif ( ! isset( $modal_task_data[ $key ] ) ) {
				// New field from extension that's not in our base array (non-array value)
				$modal_task_data[ $key ] = $value;
			}
		}
		
		wp_send_json_success( array( 'task' => $modal_task_data ) );
	}

	/**
	 * Delete task via AJAX
	 * 
	 * AJAX handler to delete a task
	 * 
	 * @since 6.2.0
	 * @return void Sends JSON response and exits
	 */
	public function ajax_delete_task() {
		check_ajax_referer( 'pta_sus_task_ajax', 'nonce' );
		
		if ( ! current_user_can( 'manage_signup_sheets' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'pta-volunteer-sign-up-sheets' ) ) );
		}
		
		$task_id = isset( $_POST['task_id'] ) ? absint( $_POST['task_id'] ) : 0;
		
		if ( $task_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid task ID.', 'pta-volunteer-sign-up-sheets' ) ) );
		}
		
		$task = pta_sus_get_task( $task_id );
		if ( ! $task ) {
			wp_send_json_error( array( 'message' => __( 'Task not found.', 'pta-volunteer-sign-up-sheets' ) ) );
		}
		
		// Check for signups (unless skip_signups_check option is enabled)
		$main_options = get_option( 'pta_volunteer_sus_main_options', array() );
		$skip_signups_check = isset( $main_options['skip_signups_check'] ) && true == $main_options['skip_signups_check'];
		
		if ( ! $skip_signups_check ) {
			$signups = PTA_SUS_Signup_Functions::get_signups_for_task( $task_id );
			if ( count( $signups ) > 0 ) {
				$people = _n( 'person', 'people', count( $signups ), 'pta-volunteer-sign-up-sheets' );
				PTA_SUS_Messages::add_error( sprintf( 
					__( 'The task "%1$s" cannot be removed because it has %2$d %3$s signed up. Please clear all spots first before removing this task.', 'pta-volunteer-sign-up-sheets' ),
					esc_html( $task->title ),
					count( $signups ),
					$people
				) );
				wp_send_json_error( array( 
					'message' => PTA_SUS_Messages::show_messages( false, 'admin' ),
				) );
			}
		}
		
		$result = $task->delete();
		if ( false === $result ) {
			PTA_SUS_Messages::add_error( __( 'Error removing task.', 'pta-volunteer-sign-up-sheets' ) );
			wp_send_json_error( array( 'message' => PTA_SUS_Messages::show_messages( false, 'admin' ) ) );
		}
		
		PTA_SUS_Messages::add_message( __( 'Task deleted successfully.', 'pta-volunteer-sign-up-sheets' ) );
		
		// Update sheet first_date and last_date after task deletion
		$sheet_id = $task->sheet_id;
		$sheet = pta_sus_get_sheet( $sheet_id );
		if ( $sheet ) {
			if ( 'Ongoing' === $sheet->type ) {
				// For Ongoing sheets, always set dates to '0000-00-00'
				$needs_update = false;
				if ( $sheet->first_date !== '0000-00-00' ) {
					$sheet->first_date = '0000-00-00';
					$needs_update = true;
				}
				if ( $sheet->last_date !== '0000-00-00' ) {
					$sheet->last_date = '0000-00-00';
					$needs_update = true;
				}
				if ( $needs_update ) {
					$sheet->save();
				}
			} elseif ( in_array( $sheet->type, array( 'Recurring', 'Multi-Day' ) ) ) {
				// For Recurring and Multi-Day sheets, calculate from remaining task dates
				$all_task_dates = PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet( $sheet_id );
				if ( ! empty( $all_task_dates ) ) {
					// Filter out '0000-00-00' dates
					$valid_dates = array_filter( $all_task_dates, function( $date ) {
						return $date !== '0000-00-00' && ! empty( $date );
					} );
					
					if ( ! empty( $valid_dates ) ) {
						sort( $valid_dates );
						$min_date = min( $valid_dates );
						$max_date = max( $valid_dates );
					} else {
						// No valid dates left, set to null or default
						$min_date = null;
						$max_date = null;
					}
					
					$needs_update = false;
					if ( $sheet->first_date !== $min_date ) {
						$sheet->first_date = $min_date;
						$needs_update = true;
					}
					if ( $sheet->last_date !== $max_date ) {
						$sheet->last_date = $max_date;
						$needs_update = true;
					}
					if ( $needs_update ) {
						$sheet->save();
					}
				}
			}
		}
		
		wp_send_json_success( array( 'message' => PTA_SUS_Messages::show_messages( false, 'admin' ) ) );
	}

	/**
	 * Reorder tasks via AJAX
	 * 
	 * AJAX handler to update task positions after drag/drop
	 * 
	 * @since 6.2.0
	 * @return void Sends JSON response and exits
	 */
	public function ajax_reorder_tasks() {
		check_ajax_referer( 'pta_sus_task_ajax', 'nonce' );
		
		if ( ! current_user_can( 'manage_signup_sheets' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'pta-volunteer-sign-up-sheets' ) ) );
		}
		
		$sheet_id = isset( $_POST['sheet_id'] ) ? absint( $_POST['sheet_id'] ) : 0;
		$task_order = $_POST['task_order'] ?? array();
		
		if ( $sheet_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid sheet ID.', 'pta-volunteer-sign-up-sheets' ) ) );
		}
		
		if ( ! is_array( $task_order ) || empty( $task_order ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid task order.', 'pta-volunteer-sign-up-sheets' ) ) );
		}
		
		$updated = 0;
		foreach ( $task_order as $position => $task_id ) {
			$task_id = absint( $task_id );
			if ( $task_id <= 0 ) {
				continue;
			}
			
			$task = pta_sus_get_task( $task_id );
			if ( ! $task || $task->sheet_id !== $sheet_id ) {
				continue;
			}
			
			$task->position = absint( $position );
			if ( $task->save() !== false ) {
				$updated++;
			}
		}
		
		wp_send_json_success( array( 
			'message' => sprintf( _n( '%d task position updated.', '%d task positions updated.', $updated, 'pta-volunteer-sign-up-sheets' ), $updated ),
			'updated' => $updated,
		) );
	}

	/**
	 * Save sheet dates and update all tasks via AJAX
	 * 
	 * For Single sheets: Updates all tasks with the single date
	 * For Recurring sheets: Updates all tasks with comma-separated dates
	 * For Ongoing sheets: Updates all tasks with '0000-00-00'
	 * 
	 * @since 6.2.0
	 * @return void Sends JSON response and exits
	 */
	public function ajax_save_sheet_dates() {
		check_ajax_referer( 'pta_sus_task_ajax', 'nonce' );
		
		if ( ! current_user_can( 'manage_signup_sheets' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'pta-volunteer-sign-up-sheets' ) ) );
		}
		
		$sheet_id = isset( $_POST['sheet_id'] ) ? absint( $_POST['sheet_id'] ) : 0;
		$sheet_type = isset( $_POST['sheet_type'] ) ? sanitize_text_field( $_POST['sheet_type'] ) : '';
		
		if ( $sheet_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid sheet ID.', 'pta-volunteer-sign-up-sheets' ) ) );
		}
		
		if ( ! in_array( $sheet_type, array( 'Single', 'Recurring', 'Ongoing' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid sheet type. Dates can only be set for Single, Recurring, or Ongoing sheets.', 'pta-volunteer-sign-up-sheets' ) ) );
		}
		
		$dates_to_set = '';
		$dates_array = array();
		
		// Validate and set dates based on sheet type
		if ( 'Single' === $sheet_type ) {
			$single_date = isset( $_POST['single_date'] ) ? sanitize_text_field( $_POST['single_date'] ) : '';
			if ( empty( $single_date ) ) {
				wp_send_json_error( array( 'message' => __( 'Please select a date.', 'pta-volunteer-sign-up-sheets' ) ) );
			}
			if ( false === pta_sus_check_date( $single_date ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid date format.', 'pta-volunteer-sign-up-sheets' ) ) );
			}
			$dates_to_set = $single_date;
			$dates_array = array( $single_date );
		} elseif ( 'Recurring' === $sheet_type ) {
			$recurring_dates = isset( $_POST['recurring_dates'] ) ? sanitize_text_field( $_POST['recurring_dates'] ) : '';
			if ( empty( $recurring_dates ) ) {
				wp_send_json_error( array( 'message' => __( 'Please select at least two dates.', 'pta-volunteer-sign-up-sheets' ) ) );
			}
			$dates_array = pta_sus_sanitize_dates( $recurring_dates );
			if ( count( $dates_array ) < 2 ) {
				wp_send_json_error( array( 'message' => __( 'Recurring events require at least two dates.', 'pta-volunteer-sign-up-sheets' ) ) );
			}
			$dates_to_set = implode( ',', $dates_array );
		} elseif ( 'Ongoing' === $sheet_type ) {
			$dates_to_set = '0000-00-00';
			$dates_array = array( '0000-00-00' );
		}
		
		// Get all existing tasks for this sheet
		$tasks = PTA_SUS_Task_Functions::get_tasks( $sheet_id );
		if ( empty( $tasks ) ) {
			// No tasks yet - store dates in sheet's first_date/last_date for future use
			$sheet = pta_sus_get_sheet( $sheet_id );
			if ( $sheet ) {
				if ( 'Single' === $sheet_type ) {
					$sheet->first_date = $dates_array[0];
					$sheet->last_date = $dates_array[0];
				} elseif ( 'Recurring' === $sheet_type ) {
					sort( $dates_array );
					$sheet->first_date = min( $dates_array );
					$sheet->last_date = max( $dates_array );
				} elseif ( 'Ongoing' === $sheet_type ) {
					$sheet->first_date = '0000-00-00';
					$sheet->last_date = '0000-00-00';
				}
				$sheet->save();
			}
			PTA_SUS_Messages::add_message( __( 'Dates saved. Add tasks and they will be assigned these dates.', 'pta-volunteer-sign-up-sheets' ) );
			wp_send_json_success( array( 
				'message' => PTA_SUS_Messages::show_messages( false, 'admin' ),
				'dates' => $dates_to_set,
				'tasks_updated' => 0,
			) );
		}
		
		// Get main options to check skip_signups_check
		$main_options = get_option( 'pta_volunteer_sus_main_options', array() );
		$skip_signups_check = isset( $main_options['skip_signups_check'] ) && true == $main_options['skip_signups_check'];
		
		// For Single sheets: Check if ANY tasks have signups before allowing date change
		if ( 'Single' === $sheet_type && ! $skip_signups_check ) {
			if ( PTA_SUS_Sheet_Functions::sheet_has_signups( $sheet_id ) ) {
				PTA_SUS_Messages::add_error( __( 'Cannot change the date for this sheet because tasks already have signups. Please clear all signups first, or use the reschedule function to change dates.', 'pta-volunteer-sign-up-sheets' ) );
				wp_send_json_error( array( 
					'message' => PTA_SUS_Messages::show_messages( false, 'admin' ),
					'has_signups' => true,
				) );
			}
		}
		
		// For Recurring sheets: Check for signups on removed dates
		if ( 'Recurring' === $sheet_type && ! $skip_signups_check ) {
			$old_dates = PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet( $sheet_id );
			if ( ! empty( $old_dates ) ) {
				$removed_dates = array_diff( $old_dates, $dates_array );
				if ( ! empty( $removed_dates ) ) {
					// Check if any tasks have signups on removed dates
					$dates_with_signups = array();
					foreach ( $tasks as $task ) {
						foreach ( $removed_dates as $removed_date ) {
							if ( PTA_SUS_Task_Functions::task_has_signups( $task->id, $removed_date ) ) {
								if ( !in_array($removed_date, $dates_with_signups, true)) {
									$dates_with_signups[] = $removed_date;
								}
								// Break out of inner loop once we find signups for this date
								break;
							}
						}
						// If we found signups, we can break early (we just need to know if any exist)
						if ( ! empty( $dates_with_signups ) ) {
							break;
						}
					}
					
					if ( ! empty( $dates_with_signups ) ) {
						$dates_list = implode( ', ', $dates_with_signups );
						PTA_SUS_Messages::add_error( sprintf( 
							__( 'Cannot remove the following date(s) because tasks have signups: %s. Please clear signups for these dates first, or use the reschedule function.', 'pta-volunteer-sign-up-sheets' ),
							esc_html( $dates_list )
						) );
						wp_send_json_error( array( 
							'message' => PTA_SUS_Messages::show_messages( false, 'admin' ),
							'has_signups' => true,
							'dates_with_signups' => $dates_with_signups,
						) );
					}
				}
			}
		}
		
		// Update all tasks with new dates
		$updated = 0;
		foreach ( $tasks as $task ) {
			$task->dates = $dates_to_set;
			if ( $task->save() !== false ) {
				$updated++;
			}
		}
		
		// Update sheet first_date and last_date (not needed for Ongoing sheets)
		if ( 'Ongoing' !== $sheet_type && ! empty( $dates_array ) ) {
			$sheet = pta_sus_get_sheet( $sheet_id );
			if ( $sheet ) {
				// Filter out '0000-00-00' dates
				$valid_dates = array_filter( $dates_array, function( $date ) {
					return $date !== '0000-00-00' && ! empty( $date );
				} );
				
				if ( ! empty( $valid_dates ) ) {
					sort( $valid_dates );
					$min_date = min( $valid_dates );
					$max_date = max( $valid_dates );
					$needs_update = false;
					
					if ( $sheet->first_date !== $min_date ) {
						$sheet->first_date = $min_date;
						$needs_update = true;
					}
					if ( $sheet->last_date !== $max_date ) {
						$sheet->last_date = $max_date;
						$needs_update = true;
					}
					if ( $needs_update ) {
						$sheet->save();
					}
				}
			}
		}
		
		PTA_SUS_Messages::add_message( sprintf( 
			_n( 'Dates saved. %d task updated.', 'Dates saved. %d tasks updated.', $updated, 'pta-volunteer-sign-up-sheets' ),
			$updated
		) );
		
		wp_send_json_success( array( 
			'message' => PTA_SUS_Messages::show_messages( false, 'admin' ),
			'dates' => $dates_to_set,
			'tasks_updated' => $updated,
		) );
	}


	// =========================================================================
	// SERVER-SIDE DATATABLES — Phase 1
	// =========================================================================

	/**
	 * AJAX handler for server-side DataTables admin views.
	 *
	 * Accepts standard DataTables server-side POST parameters plus view-specific
	 * parameters. Returns paginated, sorted, filtered JSON data and distinct
	 * filter-option values for the column select dropdowns.
	 *
	 * Action: wp_ajax_PTA_SUS_ADMIN_DT_DATA
	 *
	 * @since 6.2.0
	 */
	public function ajax_admin_dt_data() {
		check_ajax_referer( 'ajax-pta-nonce', 'ptaNonce' );

		if ( ! current_user_can( 'manage_signup_sheets' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		// DataTables standard params.
		$draw      = absint( isset( $_POST['draw'] ) ? $_POST['draw'] : 1 );
		$start     = absint( isset( $_POST['start'] ) ? $_POST['start'] : 0 );
		$length    = (int)($_POST['length'] ?? 100);
		$search    = sanitize_text_field($_POST['search']['value'] ?? '');
		$order_col = absint( isset( $_POST['order'][0]['column'] ) ? $_POST['order'][0]['column'] : 0 );
		$order_dir = ( isset( $_POST['order'][0]['dir'] ) && 'desc' === $_POST['order'][0]['dir'] ) ? 'desc' : 'asc';

		// View-specific params.
		$view_type      = sanitize_text_field( isset( $_POST['view_type'] ) ? $_POST['view_type'] : 'all_data' );
		$sheet_id       = absint( isset( $_POST['sheet_id'] ) ? $_POST['sheet_id'] : 0 );
		$hide_remaining = ! empty( $_POST['hide_remaining'] );

		// Report-builder params.
		$sheet_ids = isset( $_POST['sheet_ids'] ) ? array_map( 'absint', (array) $_POST['sheet_ids'] ) : array();
		$start_date = sanitize_text_field( isset( $_POST['start_date'] ) ? $_POST['start_date'] : '' );
		$end_date   = sanitize_text_field( isset( $_POST['end_date'] ) ? $_POST['end_date'] : '' );

		// Default show_expired / show_empty from main options when not provided explicitly.
		if ( isset( $_POST['show_expired'] ) ) {
			$show_expired = ! empty( $_POST['show_expired'] );
		} else {
			$show_expired = ! empty( $this->main_options['show_expired_tasks'] );
		}
		if ( isset( $_POST['show_empty'] ) ) {
			$show_empty = ! empty( $_POST['show_empty'] );
		} elseif ( 'single_sheet' === $view_type ) {
			$show_empty = true; // Single-sheet view always shows remaining slots.
		} else {
			$show_empty = ! empty( $this->main_options['show_all_slots_for_all_data'] );
		}

		// Per-column search filters (from select-filter dropdowns).
		// Also capture the total column count DT expects — used later to pad row data so DT
		// never encounters a missing cell (e.g. when an extension adds columns via a filter
		// that only fires on full admin page loads, not during wp_ajax_ requests).
		$column_filters = array();
		$expected_cols  = 0;
		if ( isset( $_POST['columns'] ) && is_array( $_POST['columns'] ) ) {
			$expected_cols = count( $_POST['columns'] );
			foreach ( $_POST['columns'] as $idx => $col ) {
				if ( ! empty( $col['search']['value'] ) ) {
					$column_filters[ absint( $idx ) ] = sanitize_text_field( $col['search']['value'] );
				}
			}
		}

		// Authoritative column slug list from the client (captured at page-render time when all
		// extension filters were active). Used to build cells in the correct column order, even
		// when extension hooks are registered inside admin_menu/admin_init and don't fire here.
		$client_column_slugs = array();
		if ( ! empty( $_POST['column_slugs'] ) ) {
			foreach ( explode( ',', sanitize_text_field( wp_unslash( $_POST['column_slugs'] ) ) ) as $slug ) {
				$slug = sanitize_key( $slug );
				if ( $slug ) {
					$client_column_slugs[] = $slug;
				}
			}
		}

		// Build or retrieve cached full dataset.
		$cache_args = array(
			'view_type'    => $view_type,
			'sheet_id'     => $sheet_id,
			'sheet_ids'    => $sheet_ids,
			'start_date'   => $start_date,
			'end_date'     => $end_date,
			'show_expired' => $show_expired,
			'show_empty'   => $show_empty,
			'column_slugs' => $client_column_slugs,
		);
		$cache_key = 'pta_adt_' . md5( wp_json_encode( $cache_args ) . '_' . get_current_user_id() );
		$all_rows  = get_transient( $cache_key );

		if ( false === $all_rows ) {
			$all_rows = $this->build_admin_dt_rows( $cache_args );
			set_transient( $cache_key, $all_rows, 5 * MINUTE_IN_SECONDS );
		}

		$records_total = count( $all_rows );

		// Remove remaining rows if "Hide Remaining" button is active.
		if ( $hide_remaining ) {
			$all_rows = array_values( array_filter( $all_rows, function( $row ) {
				return ! $row['is_remaining'];
			} ) );
		}

		// Apply per-column exact-match filters.
		foreach ( $column_filters as $col_idx => $filter_val ) {
			$all_rows = array_values( array_filter( $all_rows, function( $row ) use ( $col_idx, $filter_val ) {
				$cell_text = isset( $row['search_values'][ $col_idx ] ) ? $row['search_values'][ $col_idx ] : '';
				return $cell_text === $filter_val;
			} ) );
		}

		// Apply global search (case-insensitive, searches all cell text).
		if ( '' !== $search ) {
			$all_rows = array_values( array_filter( $all_rows, function( $row ) use ( $search ) {
				return false !== stripos( implode( ' ', $row['search_values'] ), $search );
			} ) );
		}

		$records_filtered = count( $all_rows );

		// Sort by the requested column.
		$all_rows = $this->sort_admin_dt_rows( $all_rows, $order_col, $order_dir );

		// Build distinct column values for the select-filter dropdowns.
		$filter_options = $this->build_filter_options( $all_rows );

		// Paginate.
		$page_rows = ( $length > 0 ) ? array_slice( $all_rows, $start, $length ) : $all_rows;

		// Build the data array for DT. Use plain PHP arrays (→ JSON arrays) so DT
		// treats each row as indexed cells. Row CSS classes are passed separately
		// in rowClasses[]; the JS createdRow callback applies them.
		// Pad each row to $expected_cols so DT never hits a missing-parameter warning
		// (can happen when an extension adds columns via a filter that only fires on
		// full page loads, not during wp_ajax_ requests).
		$data        = array();
		$row_classes = array();
		foreach ( $page_rows as $row ) {
			$cells = array_values( $row['cells'] );
			while ( $expected_cols > 0 && count( $cells ) < $expected_cols ) {
				$cells[] = '';
			}
			$data[]        = $cells;
			$row_classes[] = $row['DT_RowClass'];
		}

		wp_send_json( array(
			'draw'            => $draw,
			'recordsTotal'    => $records_total,
			'recordsFiltered' => $records_filtered,
			'data'            => $data,
			'filterOptions'   => $filter_options,
			'rowClasses'      => $row_classes,
		) );
	}

	/**
	 * Server-side export endpoint.
	 *
	 * Accepts a POST with the same params as ajax_admin_dt_data (minus DT pagination params)
	 * plus a `format` param ('csv', 'excel', 'print', 'pdf'). Outputs a CSV download or a
	 * full print-HTML page.
	 *
	 * @since 6.2.0
	 */
	public function ajax_admin_export() {
		if ( ! isset( $_POST['ptaNonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ptaNonce'] ) ), 'ajax-pta-nonce' ) ) {
			wp_die( 'Nonce verification failed.', 'Security Error', array( 'response' => 403 ) );
		}

		if ( ! current_user_can( 'manage_signup_sheets' ) ) {
			wp_die( 'Permission denied.', 'Permission Error', array( 'response' => 403 ) );
		}

		$format    = sanitize_key( isset( $_POST['format'] ) ? $_POST['format'] : 'csv' );
		$search    = sanitize_text_field( isset( $_POST['search'] ) ? wp_unslash( $_POST['search'] ) : '' );
		$order_col = absint( isset( $_POST['order_col'] ) ? $_POST['order_col'] : 0 );
		$order_dir = ( isset( $_POST['order_dir'] ) && 'desc' === $_POST['order_dir'] ) ? 'desc' : 'asc';

		$view_type      = sanitize_text_field( isset( $_POST['view_type'] ) ? $_POST['view_type'] : 'all_data' );
		$sheet_id       = absint( isset( $_POST['sheet_id'] ) ? $_POST['sheet_id'] : 0 );
		$hide_remaining = ! empty( $_POST['hide_remaining'] );

		$sheet_ids  = isset( $_POST['sheet_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['sheet_ids'] ) ) : array();
		$start_date = sanitize_text_field( isset( $_POST['start_date'] ) ? wp_unslash( $_POST['start_date'] ) : '' );
		$end_date   = sanitize_text_field( isset( $_POST['end_date'] ) ? wp_unslash( $_POST['end_date'] ) : '' );

		if ( isset( $_POST['show_expired'] ) ) {
			$show_expired = ! empty( $_POST['show_expired'] );
		} else {
			$show_expired = ! empty( $this->main_options['show_expired_tasks'] );
		}
		if ( isset( $_POST['show_empty'] ) ) {
			$show_empty = ! empty( $_POST['show_empty'] );
		} elseif ( 'single_sheet' === $view_type ) {
			$show_empty = true;
		} else {
			$show_empty = ! empty( $this->main_options['show_all_slots_for_all_data'] );
		}

		// Per-column search filters sent as a JSON-encoded object: { "colIdx": "value", ... }.
		$column_filters = array();
		if ( ! empty( $_POST['col_search'] ) ) {
			$raw = json_decode( wp_unslash( $_POST['col_search'] ), true );
			if ( is_array( $raw ) ) {
				foreach ( $raw as $idx => $val ) {
					$column_filters[ absint( $idx ) ] = sanitize_text_field( $val );
				}
			}
		}

		// Authoritative column slug list from the client.
		$client_column_slugs = array();
		if ( ! empty( $_POST['column_slugs'] ) ) {
			foreach ( explode( ',', sanitize_text_field( wp_unslash( $_POST['column_slugs'] ) ) ) as $slug ) {
				$slug = sanitize_key( $slug );
				if ( $slug ) {
					$client_column_slugs[] = $slug;
				}
			}
		}

		// Build or retrieve cached full dataset (same cache as DT data endpoint).
		$cache_args = array(
			'view_type'    => $view_type,
			'sheet_id'     => $sheet_id,
			'sheet_ids'    => $sheet_ids,
			'start_date'   => $start_date,
			'end_date'     => $end_date,
			'show_expired' => $show_expired,
			'show_empty'   => $show_empty,
			'column_slugs' => $client_column_slugs,
		);
		$cache_key = 'pta_adt_' . md5( wp_json_encode( $cache_args ) . '_' . get_current_user_id() );
		$all_rows  = get_transient( $cache_key );
		if ( false === $all_rows ) {
			$all_rows = $this->build_admin_dt_rows( $cache_args );
			set_transient( $cache_key, $all_rows, 5 * MINUTE_IN_SECONDS );
		}

		// Remove remaining rows if requested.
		if ( $hide_remaining ) {
			$all_rows = array_values( array_filter( $all_rows, function( $row ) {
				return ! $row['is_remaining'];
			} ) );
		}

		// Apply per-column exact-match filters.
		foreach ( $column_filters as $col_idx => $filter_val ) {
			$all_rows = array_values( array_filter( $all_rows, function( $row ) use ( $col_idx, $filter_val ) {
				$cell_text = isset( $row['search_values'][ $col_idx ] ) ? $row['search_values'][ $col_idx ] : '';
				return $cell_text === $filter_val;
			} ) );
		}

		// Apply global search.
		if ( '' !== $search ) {
			$all_rows = array_values( array_filter( $all_rows, function( $row ) use ( $search ) {
				return false !== stripos( implode( ' ', $row['search_values'] ), $search );
			} ) );
		}

		// Sort.
		$all_rows = $this->sort_admin_dt_rows( $all_rows, $order_col, $order_dir );

		// Resolve column list.
		$columns = $this->get_columns_for_view( $view_type, $sheet_id ? pta_sus_get_sheet( $sheet_id ) : null );
		if ( ! empty( $client_column_slugs ) ) {
			$override = array();
			foreach ( $client_column_slugs as $slug ) {
				$override[ $slug ] = isset( $columns[ $slug ] ) ? $columns[ $slug ] : $slug;
			}
			$columns = $override;
		}

		// Determine export format family.
		$is_print = in_array( $format, array( 'print', 'pdf' ), true );

		if ( $is_print ) {
			$title = ( 'single_sheet' === $view_type && $sheet_id )
				? get_the_title( $sheet_id )
				: __( 'All Signups', 'pta-volunteer-sign-up-sheets' );
			$this->export_as_print_html( $all_rows, $columns, $title );
		} else {
			$filename = ( 'single_sheet' === $view_type && $sheet_id )
				? sanitize_file_name( get_the_title( $sheet_id ) . '-signups.csv' )
				: 'all-signups.csv';
			$this->export_as_csv( $all_rows, $columns, $filename );
		}

		exit;
	}

	/**
	 * Output the row set as a UTF-8 CSV download.
	 *
	 * Uses search_values (plain text) for each cell so the download is free of HTML markup.
	 * The 'actions' column is excluded.
	 *
	 * @since 6.2.0
	 * @param array  $rows     Rows from build_admin_dt_rows().
	 * @param array  $columns  Column slug => label map (ordered).
	 * @param string $filename Suggested download filename.
	 */
	private function export_as_csv( $rows, $columns, $filename ) {
		// Build the list of (slug, index) pairs excluding 'actions'.
		$export_cols = array();
		$idx         = 0;
		foreach ( $columns as $slug => $label ) {
			if ( 'actions' !== $slug ) {
				$export_cols[] = array( 'idx' => $idx, 'label' => $label );
			}
			$idx++;
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );
		// UTF-8 BOM so Excel auto-detects the encoding.
		fputs( $out, "\xEF\xBB\xBF" );

		// Header row.
		$header = array_column( $export_cols, 'label' );
		fputcsv( $out, $header, ',', '"', '\\' );

		// Data rows.
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $export_cols as $col ) {
				$line[] = isset( $row['search_values'][ $col['idx'] ] ) ? $row['search_values'][ $col['idx'] ] : '';
			}
			fputcsv( $out, $line, ',', '"', '\\' );
		}

		fclose( $out );
	}

	/**
	 * Output the row set as a printable HTML page.
	 *
	 * The page auto-triggers window.print() on load and includes a manual Print button.
	 * Uses cell HTML (from 'cells') so formatting is preserved; the 'actions' column is excluded.
	 *
	 * @since 6.2.0
	 * @param array  $rows    Rows from build_admin_dt_rows().
	 * @param array  $columns Column slug => label map (ordered).
	 * @param string $title   Page/report title.
	 */
	private function export_as_print_html( $rows, $columns, $title ) {
		// Build the list of (slug, index) pairs excluding 'actions'.
		$export_cols = array();
		$idx         = 0;
		foreach ( $columns as $slug => $label ) {
			if ( 'actions' !== $slug ) {
				$export_cols[] = array( 'idx' => $idx, 'label' => $label, 'slug' => $slug );
			}
			$idx++;
		}

		$esc_title = esc_html( $title );
		$now       = esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) );

		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo $esc_title; ?></title>
<style>
body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; color: #333; }
h1 { font-size: 16px; margin-bottom: 4px; }
.report-meta { font-size: 10px; color: #666; margin-bottom: 12px; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
th { background: #f1f1f1; font-weight: bold; }
tr.remaining td { color: #aaa; font-style: italic; }
.no-print { margin-bottom: 12px; }
@media print {
	.no-print { display: none; }
	body { margin: 0; }
}
</style>
</head>
<body>
<div class="no-print">
	<button onclick="window.print()"><?php esc_html_e( 'Print', 'pta-volunteer-sign-up-sheets' ); ?></button>
	<button onclick="window.close()" style="margin-left:8px;"><?php esc_html_e( 'Close', 'pta-volunteer-sign-up-sheets' ); ?></button>
</div>
<h1><?php echo $esc_title; ?></h1>
<p class="report-meta"><?php
	/* translators: %s: date and time the report was generated */
	printf( esc_html__( 'Generated: %s', 'pta-volunteer-sign-up-sheets' ), $now );
?></p>
<table>
<thead>
<tr>
<?php foreach ( $export_cols as $col ) : ?>
<th><?php echo esc_html( $col['label'] ); ?></th>
<?php endforeach; ?>
</tr>
</thead>
<tbody>
<?php foreach ( $rows as $row ) :
	$tr_class = ! empty( $row['DT_RowClass'] ) ? ' class="' . esc_attr( $row['DT_RowClass'] ) . '"' : '';
	?>
<tr<?php echo $tr_class; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above ?>>
<?php foreach ( $export_cols as $col ) :
	$cell_html = isset( $row['cells'][ $col['idx'] ] ) ? $row['cells'][ $col['idx'] ] : '';
	?>
<td><?php echo wp_kses_post( $cell_html ); ?></td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<script>window.onload = function() { window.print(); };</script>
</body>
</html>
		<?php
	}

	/**
	 * Build the full row dataset for server-side DataTables.
	 *
	 * Each row contains:
	 *   'cells'         => HTML strings, one per column.
	 *   'search_values' => Plain-text strings for global search and column filters.
	 *   'sort_values'   => Sortable values (timestamps for date/ts, lowercase strings otherwise).
	 *   'is_remaining'  => bool — true for empty-slot rows.
	 *   'DT_RowClass'   => CSS class for the <tr>.
	 *
	 * @since  6.2.0
	 * @param  array $args {
	 *     @type string $view_type    'single_sheet' or 'all_data'.
	 *     @type int    $sheet_id     Sheet ID (single_sheet only).
	 *     @type array  $sheet_ids    Allowed sheet IDs (empty = all).
	 *     @type string $start_date   Date range start (Y-m-d).
	 *     @type string $end_date     Date range end (Y-m-d).
	 *     @type bool   $show_expired Include expired task-dates.
	 *     @type bool   $show_empty   Include remaining-slot rows.
	 * }
	 * @return array Array of row associative arrays.
	 */
	private function build_admin_dt_rows( $args ) {
		$rows      = array();
		$view_type = $args['view_type'];

		if ( 'single_sheet' === $view_type ) {
			$sheet = pta_sus_get_sheet( (int) $args['sheet_id'] );
			if ( ! $sheet ) {
				return array();
			}
			$sheets  = array( $sheet );
			$columns = $this->get_columns_for_view( $view_type, $sheet );
		} else {
			$sheet_args = array(
				'trash'       => false,
				'active_only' => false,
				'show_hidden' => true,
			);
			if ( ! current_user_can( 'manage_others_signup_sheets' ) ) {
				$sheet_args['author_id'] = get_current_user_id();
			}
			$sheets  = PTA_SUS_Sheet_Functions::get_sheets_by_args( $sheet_args );
			$sheets  = apply_filters( 'pta_sus_admin_view_all_data_sheets', $sheets );
			$columns = $this->get_columns_for_view( $view_type );

			// Report-builder: filter to specific sheets.
			if ( ! empty( $args['sheet_ids'] ) ) {
				$allowed = array_map( 'intval', $args['sheet_ids'] );
				$sheets  = array_filter( $sheets, function( $s ) use ( $allowed ) {
					return in_array( (int) $s->id, $allowed, true );
				} );
			}
		}

		// If the client sent authoritative column slugs (from the page-render context where
		// all extension filters fired), use those to override the column order/set. This
		// ensures extension columns added via admin_menu/admin_init hooks — which don't fire
		// during wp_ajax_ requests — are still included at their correct positions.
		if ( ! empty( $args['column_slugs'] ) ) {
			$override = array();
			foreach ( $args['column_slugs'] as $slug ) {
				$override[ $slug ] = isset( $columns[ $slug ] ) ? $columns[ $slug ] : $slug;
			}
			$columns = $override;
		}

		$show_expired   = ! empty( $args['show_expired'] );
		$show_empty     = ! empty( $args['show_empty'] );
		$show_all_slots = ! empty( $this->main_options['show_all_slots_for_all_data'] );
		$today          = current_time( 'Y-m-d' );

		foreach ( $sheets as $sheet ) {
			$all_task_dates = PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet( (int) $sheet->id );
			$tasks          = PTA_SUS_Task_Functions::get_tasks( $sheet->id );

			if ( empty( $all_task_dates ) || empty( $tasks ) ) {
				continue;
			}

			foreach ( $all_task_dates as $tdate ) {
				// Skip expired dates unless requested.
				if ( '0000-00-00' !== $tdate ) {
					if ( ! $show_expired && $tdate < $today ) {
						continue;
					}
					// Date-range filtering (report builder).
					if ( ! empty( $args['start_date'] ) && $tdate < $args['start_date'] ) {
						continue;
					}
					if ( ! empty( $args['end_date'] ) && $tdate > $args['end_date'] ) {
						continue;
					}
				}

				foreach ( $tasks as $task ) {
					$task_dates = explode( ',', $task->dates );
					if ( ! in_array( $tdate, $task_dates, true ) ) {
						continue;
					}

					$signups = PTA_SUS_Signup_Functions::get_signups_for_task( $task->id, $tdate );
					$i       = 0;

					foreach ( $signups as $signup ) {
						$rows[] = $this->format_admin_dt_row( $sheet, $task, $signup, $tdate, $i + 1, false, $columns );
						if ( 'YES' === $task->enable_quantities ) {
							$i += (int) $signup->item_qty;
						} else {
							$i++;
						}
					}

					// Remaining (empty) slots.
					if ( $show_empty && $i < (int) $task->qty ) {
						if ( $show_all_slots || 'single_sheet' === $view_type ) {
							for ( $x = $i + 1; $x <= (int) $task->qty; $x++ ) {
								$rows[] = $this->format_admin_dt_row( $sheet, $task, false, $tdate, $x, true, $columns );
							}
						} else {
							$rows[] = $this->format_admin_dt_remaining_summary( $sheet, $task, $tdate, (int) $task->qty - $i, $columns );
						}
					}
				}
			}
		}

		return $rows;
	}

	/**
	 * Format a single signup (or empty-slot) row for the server-side DT response.
	 *
	 * Uses output buffering on the existing output_signup_column_data() method so
	 * that cell HTML is identical between client-side and server-side modes.
	 *
	 * @since  6.2.0
	 * @param  object       $sheet       Sheet object.
	 * @param  object       $task        Task object.
	 * @param  object|false $signup      Signup object, or false for an empty slot.
	 * @param  string       $tdate       Raw task date (Y-m-d or '0000-00-00').
	 * @param  int          $slot_num    Slot number (1-based) to display in the # column.
	 * @param  bool         $is_remaining True for an empty-slot row.
	 * @param  array        $columns     Ordered slug => label column map.
	 * @return array Row data array.
	 */
	private function format_admin_dt_row( $sheet, $task, $signup, $tdate, $slot_num, $is_remaining, $columns ) {
		$cells         = array();
		$search_values = array();
		$sort_values   = array();

		foreach ( $columns as $slug => $label ) {
			ob_start();
			$this->output_signup_column_data( $slug, $slot_num, $sheet, $task, $signup, $tdate );
			$cell_html = ob_get_clean();

			$cells[]         = $cell_html;
			$search_values[] = wp_strip_all_tags( $cell_html );

			if ( 'date' === $slug ) {
				$sort_values[] = ( '0000-00-00' !== $tdate ) ? strtotime( $tdate ) : 0;
			} elseif ( 'ts' === $slug ) {
				$sort_values[] = ( $signup && ! empty( $signup->ts ) ) ? (int) $signup->ts : 0;
			} else {
				$sort_values[] = strtolower( wp_strip_all_tags( $cell_html ) );
			}
		}

		return array(
			'cells'         => $cells,
			'search_values' => $search_values,
			'sort_values'   => $sort_values,
			'is_remaining'  => $is_remaining,
			'DT_RowClass'   => $is_remaining ? 'remaining' : '',
		);
	}

	/**
	 * Format a summary "X remaining" row for the all-data view (show_all_slots off).
	 *
	 * Produces a single row representing all empty slots for one task-date, matching
	 * the summary row rendered by admin-view-all-signups-html.php.
	 *
	 * @since  6.2.0
	 * @param  object $sheet           Sheet object.
	 * @param  object $task            Task object.
	 * @param  string $tdate           Raw task date (Y-m-d or '0000-00-00').
	 * @param  int    $remaining_count Number of empty slots.
	 * @param  array  $columns         Ordered slug => label column map.
	 * @return array Row data array.
	 */
	private function format_admin_dt_remaining_summary( $sheet, $task, $tdate, $remaining_count, $columns ) {
		$cells         = array();
		$search_values = array();
		$sort_values   = array();

		$remaining_text = sprintf( __( '%d remaining', 'pta-volunteer-sign-up-sheets' ), $remaining_count );

		foreach ( $columns as $slug => $label ) {
			if ( 'slot' === $slug ) {
				$cell_html = '<strong>' . esc_html( $remaining_text ) . '</strong>';
			} else {
				ob_start();
				$this->output_signup_column_data( $slug, 0, $sheet, $task, false, $tdate );
				$cell_html = ob_get_clean();
			}

			$cells[]         = $cell_html;
			$search_values[] = wp_strip_all_tags( $cell_html );

			if ( 'date' === $slug ) {
				$sort_values[] = ( '0000-00-00' !== $tdate ) ? strtotime( $tdate ) : 0;
			} else {
				$sort_values[] = strtolower( wp_strip_all_tags( $cell_html ) );
			}
		}

		return array(
			'cells'         => $cells,
			'search_values' => $search_values,
			'sort_values'   => $sort_values,
			'is_remaining'  => true,
			'DT_RowClass'   => 'remaining',
		);
	}

	/**
	 * Sort the full row dataset by a column index and direction.
	 *
	 * Uses sort_values (timestamps for date/ts columns, lowercase strings otherwise).
	 * Numeric values are compared numerically; strings use strcmp.
	 *
	 * @since  6.2.0
	 * @param  array  $rows      Array of row data arrays from build_admin_dt_rows().
	 * @param  int    $order_col Column index (0-based) to sort by.
	 * @param  string $order_dir 'asc' or 'desc'.
	 * @return array Sorted rows.
	 */
	private function sort_admin_dt_rows( $rows, $order_col, $order_dir ) {
		if ( count( $rows ) < 2 ) {
			return $rows;
		}
		usort( $rows, function( $a, $b ) use ( $order_col, $order_dir ) {
			$a_val = isset( $a['sort_values'][ $order_col ] ) ? $a['sort_values'][ $order_col ] : '';
			$b_val = isset( $b['sort_values'][ $order_col ] ) ? $b['sort_values'][ $order_col ] : '';

			if ( is_numeric( $a_val ) && is_numeric( $b_val ) ) {
				if ( (float) $a_val < (float) $b_val ) {
					$cmp = -1;
				} elseif ( (float) $a_val > (float) $b_val ) {
					$cmp = 1;
				} else {
					$cmp = 0;
				}
			} else {
				$cmp = strcmp( (string) $a_val, (string) $b_val );
			}

			return 'desc' === $order_dir ? -$cmp : $cmp;
		} );
		return $rows;
	}

	/**
	 * Build distinct column values for the server-side select-filter dropdowns.
	 *
	 * Iterates the filtered (post-search, post-filter) row set and collects unique
	 * plain-text values per column index. The JS uses this to rebuild the footer
	 * <select> elements without re-fetching all data.
	 *
	 * @since  6.2.0
	 * @param  array $rows Filtered (but not yet paginated) row data arrays.
	 * @return array Associative array of column_index => string[].
	 */
	private function build_filter_options( $rows ) {
		$options = array();
		foreach ( $rows as $row ) {
			foreach ( $row['search_values'] as $idx => $val ) {
				if ( ! isset( $options[ $idx ] ) ) {
					$options[ $idx ] = array();
				}
				if ( '' !== $val && ! in_array( $val, $options[ $idx ], true ) ) {
					$options[ $idx ][] = $val;
				}
			}
		}
		return $options;
	}

	/**
	 * Return the ordered columns map for a given admin view type.
	 *
	 * Applies the same filters used by the PHP templates so that extensions which
	 * add custom columns via those filters work identically in server-side mode.
	 *
	 * @since  6.2.0
	 * @param  string      $view_type 'single_sheet' or 'all_data'.
	 * @param  object|null $sheet     Sheet object passed to the single_sheet filter.
	 * @return array Ordered associative array of slug => label.
	 */
	private function get_columns_for_view( $view_type, $sheet = null ) {
		if ( 'single_sheet' === $view_type ) {
			return apply_filters( 'pta_sus_admin_view_signups_columns', array(
				'date'        => __( 'Date', 'pta-volunteer-sign-up-sheets' ),
				'task'        => __( 'Task/Item', 'pta-volunteer-sign-up-sheets' ),
				'description' => __( 'Task Description', 'pta-volunteer-sign-up-sheets' ),
				'start'       => __( 'Start Time', 'pta-volunteer-sign-up-sheets' ),
				'end'         => __( 'End Time', 'pta-volunteer-sign-up-sheets' ),
				'slot'        => '#',
				'name'        => __( 'Name', 'pta-volunteer-sign-up-sheets' ),
				'email'       => __( 'E-mail', 'pta-volunteer-sign-up-sheets' ),
				'phone'       => __( 'Phone', 'pta-volunteer-sign-up-sheets' ),
				'details'     => __( 'Item Details', 'pta-volunteer-sign-up-sheets' ),
				'qty'         => __( 'Item Qty', 'pta-volunteer-sign-up-sheets' ),
				'ts'          => __( 'Signup Time', 'pta-volunteer-sign-up-sheets' ),
				'validated'   => __( 'Validated', 'pta-volunteer-sign-up-sheets' ),
				'actions'     => __( 'Actions', 'pta-volunteer-sign-up-sheets' ),
			), $sheet );
		}

		// all_data view.
		return apply_filters( 'pta_sus_admin_view_all_data_columns', array(
			'date'        => __( 'Date', 'pta-volunteer-sign-up-sheets' ),
			'sheet'       => __( 'Sheet', 'pta-volunteer-sign-up-sheets' ),
			'task'        => __( 'Task/Item', 'pta-volunteer-sign-up-sheets' ),
			'description' => __( 'Task Description', 'pta-volunteer-sign-up-sheets' ),
			'start'       => __( 'Start Time', 'pta-volunteer-sign-up-sheets' ),
			'end'         => __( 'End Time', 'pta-volunteer-sign-up-sheets' ),
			'slot'        => '#',
			'name'        => __( 'Name', 'pta-volunteer-sign-up-sheets' ),
			'email'       => __( 'E-mail', 'pta-volunteer-sign-up-sheets' ),
			'phone'       => __( 'Phone', 'pta-volunteer-sign-up-sheets' ),
			'details'     => __( 'Item Details', 'pta-volunteer-sign-up-sheets' ),
			'qty'         => __( 'Item Qty', 'pta-volunteer-sign-up-sheets' ),
			'validated'   => __( 'Validated', 'pta-volunteer-sign-up-sheets' ),
			'ts'          => __( 'Signup Time', 'pta-volunteer-sign-up-sheets' ),
			'actions'     => __( 'Actions', 'pta-volunteer-sign-up-sheets' ),
		) );
	}

	/**
	 * Clear all server-side DataTables transient cache entries.
	 *
	 * Called on any signup, task, or sheet CRUD operation so cached datasets are
	 * never stale after data changes.
	 *
	 * @since 6.2.0
	 */
	public function invalidate_admin_dt_cache() {
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_pta_adt_%'
			    OR option_name LIKE '_transient_timeout_pta_adt_%'"
		);
	}

} // End of Class
/* EOF */
