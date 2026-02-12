<?php
/*
Plugin Name: Volunteer Sign Up Sheets
Plugin URI: http://wordpress.org/plugins/pta-volunteer-sign-up-sheets
Description: Volunteer Sign Up Sheets and Management from Stephen Sherrard Plugins
Version: 6.1.0
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
    define('PTA_VOLUNTEER_SUS_VERSION_NUM', '6.1.0');

if (!defined('PTA_VOLUNTEER_SUS_DIR'))
	define('PTA_VOLUNTEER_SUS_DIR', plugin_dir_path( __FILE__ ) );

if (!defined('PTA_VOLUNTEER_SUS_URL'))
	define('PTA_VOLUNTEER_SUS_URL', plugin_dir_url( __FILE__ ) );

if ( !defined('SS_PLUGINS_PTA_VOLUNTEER_SUS_ID') )
	define( 'SS_PLUGINS_PTA_VOLUNTEER_SUS_ID', 15066 );

add_option(PTA_VOLUNTEER_SUS_VERSION_KEY, PTA_VOLUNTEER_SUS_VERSION_NUM);

if ( !defined('SS_PLUGINS_URL') )
	define( 'SS_PLUGINS_URL', 'https://stephensherrardplugins.com' );

if( !class_exists( 'PTA_Plugin_Updater' ) ) {
	// load our custom updater
	include( __DIR__ . '/PTA_Plugin_Updater.php' );
}

// License Manager handles all updater setup via the registry
// See PTA_SUS_License_Manager::setup_updaters()

if (!class_exists('PTA_SUS_Data')) require_once 'classes/data.php';
if (!class_exists('PTA_SUS_List_Table')) require_once 'classes/list-table.php';
if (!class_exists('PTA_SUS_Widget')) require_once 'classes/widget.php';
if (!class_exists('PTA_SUS_Emails')) require_once 'classes/class-pta_sus_emails.php';

// Load new model classes
if (!class_exists('PTA_SUS_Base_Object')) require_once 'classes/models/class-pta-sus-base-object.php';
if (!class_exists('PTA_SUS_Sheet')) require_once 'classes/models/class-pta-sus-sheet.php';
if (!class_exists('PTA_SUS_Task')) require_once 'classes/models/class-pta-sus-task.php';
if (!class_exists('PTA_SUS_Signup')) require_once 'classes/models/class-pta-sus-signup.php';
if (!class_exists('PTA_SUS_Email_Template')) require_once 'classes/models/class-pta-sus-email-template.php';

// Load helper function classes
if (!class_exists('PTA_SUS_Sheet_Functions')) require_once 'classes/class-pta_sus_sheet_functions.php';
if (!class_exists('PTA_SUS_Signup_Functions')) require_once 'classes/class-pta_sus_signup_functions.php';
if (!class_exists('PTA_SUS_Task_Functions')) require_once 'classes/class-pta_sus_task_functions.php';
if (!class_exists('PTA_SUS_Public_Display_Functions')) require_once 'classes/class-pta_sus_public_display_functions.php';
if (!class_exists('PTA_SUS_Validation')) require_once 'classes/class-pta_sus_validation.php';
if (!class_exists('PTA_SUS_Email_Functions')) require_once 'classes/class-pta_sus_email_functions.php';
if (!class_exists('PTA_SUS_Activation')) require_once 'classes/class-pta_sus_activation.php';
if (!class_exists('PTA_SUS_Options_Manager')) require_once 'classes/class-pta_sus_options_manager.php';
if (!class_exists('PTA_SUS_Cron_Manager')) require_once 'classes/class-pta_sus_cron_manager.php';
if (!class_exists('PTA_SUS_Blocks')) require_once 'classes/class-pta_sus_blocks.php';
if (!class_exists('PTA_SUS_Assets')) require_once 'classes/class-pta_sus_assets.php';
if (!class_exists('PTA_SUS_License_Manager')) require_once 'classes/class-pta_sus_license_manager.php';

// Register the main plugin with the License Manager
PTA_SUS_License_Manager::register( array(
	'slug'           => 'pta-volunteer-sign-up-sheets',
	'name'           => 'Volunteer Sign Up Sheets',
	'version'        => PTA_VOLUNTEER_SUS_VERSION_NUM,
	'item_id'        => SS_PLUGINS_PTA_VOLUNTEER_SUS_ID,
	'file'           => __FILE__,
	'license_key'    => 'pta_vol_sus_license_key',
	'license_status' => 'pta_vol_sus_license_status',
) );

// Allow extensions to register with the License Manager.
// Must fire on plugins_loaded so all extension plugin files have been included first.
// Priority 8: after pta_sus_load_plugin_components (5), before init_hooks (10).
add_action( 'plugins_loaded', function() {
	do_action( 'pta_sus_register_extensions' );
}, 8 );

// Set up updaters for all registered extensions
add_action( 'admin_init', array( 'PTA_SUS_License_Manager', 'setup_updaters' ) );

if(!class_exists('PTA_Sign_Up_Sheet')):

class PTA_Sign_Up_Sheet {
	
    public $data;
    public $public = null;
    public $emails;
    /**
     * Database version
     * 
     * @deprecated 6.0.0 Use PTA_SUS_Activation::get_db_version() instead
     * @var string
     */
    public $db_version = '5.2.0';
    public $main_options;
	public $validation_options;
    public $admin = null;
    
    public function __construct() {
	    $this->data = new PTA_SUS_Data();
        $this->emails = new PTA_SUS_Emails();
        // Sync db_version with activation class for backward compatibility
        if ( class_exists( 'PTA_SUS_Activation' ) ) {
            $this->db_version = PTA_SUS_Activation::get_db_version();
        }

        // Use static methods directly for activation/deactivation
        register_activation_hook(__FILE__, array('PTA_SUS_Activation', 'activate'));
        register_deactivation_hook( __FILE__, array('PTA_SUS_Activation', 'deactivate'));

        $this->main_options = get_option( 'pta_volunteer_sus_main_options' );
	    $this->validation_options = get_option( 'pta_volunteer_sus_validation_options' );
    }

    public function init_hooks() {
	    add_action('pta_sus_cron_job', array('PTA_SUS_Cron_Manager', 'run_hourly_cron'));

	    add_action('init', array($this, 'init'));
	    add_action('plugins_loaded', array($this, 'public_init' ));
	    add_action('plugins_loaded', array($this, 'setup_translation' ));

	    add_action( 'init', array('PTA_SUS_Blocks', 'register_blocks' ));

	    add_action( 'widgets_init', array($this, 'register_sus_widget') );

	    // Use static method directly for new blog creation
	    add_action( 'wpmu_new_blog', array('PTA_SUS_Activation', 'new_blog'), 10, 6);

	    add_action('wp_enqueue_scripts', array('PTA_SUS_Assets', 'register_scripts'), 1);
	    add_action('admin_enqueue_scripts', array('PTA_SUS_Assets', 'register_scripts'), 1);

	    if(is_admin()) {
		    if (!class_exists('PTA_SUS_Admin')) {
			    include_once(__DIR__ .'/classes/class-pta_sus_admin.php');
			    $this->admin = new PTA_SUS_Admin();
				add_action('init', array($this->admin, 'init_admin_hooks'));
		    }
	    }
	    if (!class_exists('PTA_SUS_Public')) {
		    include_once(__DIR__ .'/classes/class-pta_sus_public.php');
			$this->public = new PTA_SUS_Public();
		    add_action('init', array($this->public, 'init'));
		    add_action('wp_enqueue_scripts', array($this->public, 'add_css_and_js_to_frontend'));
	    }
    }

	/**
	 * Register scripts and styles
	 * 
	 * @deprecated 6.0.0 Use PTA_SUS_Assets::register_scripts() instead
	 * @return void
	 */
    public function register_scripts() {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Assets::register_scripts() ' . sprintf('Called from %s line %s', $file, $line)
		);
		
		PTA_SUS_Assets::register_scripts();
    }

	/**
	 * Get a sheet by id
	 *
	 * @param     int     sheet_id to retrieve
	 * @return    object    the sheet
	 */
	public function get_sheet($id = false) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'pta_sus_get_sheet() '.sprintf('Called from %s line %s', $file, $line) );
        return pta_sus_get_sheet($id);
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
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Sheet_Functions::get_sheets() '.sprintf('Called from %s line %s', $file, $line) );
		return PTA_SUS_Sheet_Functions::get_sheets($trash, $active_only, $show_hidden,$order_by,$order);
	}

	/**
	 * Get tasks by sheet
	 *
	 * @param     int        id of sheet
	 * @return    mixed    array of tasks
	 */
	public function get_tasks($sheet_id, $date = '') {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Task_Functions::get_tasks() '.sprintf('Called from %s line %s', $file, $line) );
		return PTA_SUS_Task_Functions::get_tasks($sheet_id, $date);
	}
	
	/**
	 * Get task by id
	 *
	 * @param     int        id of task
	 * @return    object    task
	 */
	public function get_task($task_id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '5.8.0', 'pta_sus_get_task() '.sprintf('Called from %s line %s', $file, $line) );
        return pta_sus_get_task($task_id);
	}
	
	/**
	 * Get signup by id
	 *
	 * @param     int        id of signup
	 * @return    object    signup
	 */
	public function get_signup($signup_id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '5.8.0', 'pta_sus_get_signup() '.sprintf('Called from %s line %s', $file, $line) );
        return pta_sus_get_signup($signup_id);
	}
	
	/**
	 * Get detailed signup by id
	 * @deprecated 6.0.0 use PTA_SUS_Signup_Functions::get_detailed_signups instead
	 * @param     int $signup_id       id of signup
	 * @return    Mixed object/false    Returns an object with the detailed signup info
	 */
	public function get_detailed_signup($signup_id) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function( __FUNCTION__, '6.0.0', 'PTA_SUS_Signup_Functions::get_detailed_signups() ' . sprintf('Called from %s line %s', $file, $line) );

        $signup_id = absint($signup_id);
        if (empty($signup_id)) {
            return false;
        }

        $results = PTA_SUS_Signup_Functions::get_detailed_signups(array('id' => $signup_id));

        if (!empty($results) && isset($results[0])) {
            return $results[0];
        }

        return false;
	}

	/**
	 * Get signups by task & date
	 *
	 * @deprecated 6.0.0 Use PTA_SUS_Signup_Functions::get_signups_for_task() instead
	 * @param    int        id of task
	 * @param    string     date (optional)
	 * @return    mixed    array of signups
	 */
	public function get_signups($task_id, $date='')	{
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Signup_Functions::get_signups_for_task() ' . sprintf('Called from %s line %s', $file, $line)
		);
		return PTA_SUS_Signup_Functions::get_signups_for_task($task_id, $date);
	}
	
	/**
	 * Get html output of sheets
	 *
	 * @deprecated 6.0.0 Use PTA_SUS_Public_Display_Functions::get_sheets_list() instead
	 * @param array $sheets Array of sheet objects
	 * @return string html output of sheets
	 */
	public function get_sheets_list($sheets) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Public_Display_Functions::get_sheets_list() ' . sprintf('Called from %s line %s', $file, $line)
		);
		// Use helper class directly - no need to go through public class instance
		return PTA_SUS_Public_Display_Functions::get_sheets_list($sheets);
	}
	
	/**
	 * Get html output of a single sheet
	 *
	 * @deprecated 6.0.0 Use PTA_SUS_Public_Display_Functions::get_single_sheet() instead
	 * @param int $id the ID of sheet to display
	 *
	 * @return string html output of sheet and all tasks
	 */
	public function get_single_sheet($id) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Public_Display_Functions::get_single_sheet() ' . sprintf('Called from %s line %s', $file, $line)
		);
		// Use helper class directly - no need to go through public class instance
		return PTA_SUS_Public_Display_Functions::get_single_sheet($id);
	}
	
	/**
	 * Get html table output of all tasks/items user has signed up for
	 *
	 * @deprecated 6.0.0 Use PTA_SUS_Public_Display_Functions::get_user_signups_list() instead
	 * @param array $atts Optional shortcode attributes
	 * @return string table list of user signups
	 */
	public function get_user_signups_list($atts = array()) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Public_Display_Functions::get_user_signups_list() ' . sprintf('Called from %s line %s', $file, $line)
		);
		// Use helper class directly - no need to go through public class instance
		return PTA_SUS_Public_Display_Functions::get_user_signups_list($atts);
	}
	
	/**
	 * Get html signup form for a specific task and date
	 *
	 * @deprecated 6.0.0 Use PTA_SUS_Public_Display_Functions::display_signup_form() instead
	 * @param int $task_id Task ID
	 * @param string $date Date (YYYY-MM-DD format)
	 * @param bool $skip_filled_check Whether to skip checking if spots are filled
	 * @return string html signup form
	 * 
	 * Note: For best performance when called via AJAX (e.g., from extensions),
	 * call PTA_SUS_Public_Display_Functions::initialize() first. The method
	 * will work without it thanks to lazy-loading fallbacks, but initializing
	 * first loads all options at once for better performance.
	 */
	public function get_signup_form($task_id, $date, $skip_filled_check = false) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Public_Display_Functions::display_signup_form() ' . sprintf('Called from %s line %s', $file, $line)
		);
		// Use helper class directly - no need to go through public class instance
		// Note: Options will be lazy-loaded if initialize() hasn't been called yet
		return PTA_SUS_Public_Display_Functions::display_signup_form($task_id, $date, $skip_filled_check);
	}

    public function register_sus_widget() {
        register_widget( 'PTA_SUS_Widget' );
    }


	/**
	 * Run hourly CRON job
	 * 
	 * @deprecated 6.0.0 Use PTA_SUS_Cron_Manager::run_hourly_cron() instead
	 * @return void
	 */
    public function cron_functions() {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Cron_Manager::run_hourly_cron() ' . sprintf('Called from %s line %s', $file, $line)
		);
		
		// Call the new static method
		PTA_SUS_Cron_Manager::run_hourly_cron();
    }

    public function public_init() {
	    if (strpos($_SERVER['REQUEST_URI'], 'favicon.ico') !== false) {
		    return;
	    }
    	if(!is_admin() || wp_doing_ajax()) {
		    if($this->public === null) {
			    $this->public = new PTA_SUS_Public();
				$this->public->init();
		    }
	    }
    }

	public function setup_translation() {
		load_plugin_textdomain( 'pta-volunteer-sign-up-sheets', false, dirname(plugin_basename( __FILE__ )) . '/languages/' );
	}

    public function init() {
        // Check our database version and run upgrades if needed
        if ( PTA_SUS_Activation::needs_upgrade() ) {
            PTA_SUS_Activation::run_upgrades();
        }

        // Check if options need upgrading (new options added during plugin upgrade)
        // Only runs when options version is outdated, not on every page load
        if ( class_exists( 'PTA_SUS_Options_Manager' ) && PTA_SUS_Options_Manager::needs_options_upgrade() ) {
            PTA_SUS_Options_Manager::init_all_options( true );
        }
    }

      
 
    /*
    *   Run activation procedure to set up tables and options when a new blog is added
     */
    /**
     * Handle new blog creation in multi-site
     * 
     * @deprecated 6.0.0 Use PTA_SUS_Activation::new_blog() instead
     * @param int $blog_id New blog ID
     * @param int $user_id User ID who created the blog
     * @param string $domain Blog domain
     * @param string $path Blog path
     * @param int $site_id Site ID
     * @param array $meta Blog meta data
     * @return void
     */
    public function new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta ) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(
            __METHOD__,
            '6.0.0',
            'PTA_SUS_Activation::new_blog() ' . sprintf('Called from %s line %s', $file, $line)
        );
        PTA_SUS_Activation::new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta);
    }
    
    /**
     * Activate the plugin
     * 
     * @deprecated 6.0.0 Use PTA_SUS_Activation::activate() instead
     * @param bool $networkwide Whether this is a network-wide activation
     * @return void
     */
    public function activate($networkwide) {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(
            __METHOD__,
            '6.0.0',
            'PTA_SUS_Activation::activate() ' . sprintf('Called from %s line %s', $file, $line)
        );
        PTA_SUS_Activation::activate($networkwide);
    }

    /**
     * Activate plugin for single site
     * 
     * @deprecated 6.0.0 Use PTA_SUS_Activation::activate_site() instead
     * @return void
     */
    public function pta_sus_activate() {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(
            __METHOD__,
            '6.0.0',
            'PTA_SUS_Activation::activate_site() ' . sprintf('Called from %s line %s', $file, $line)
        );
        PTA_SUS_Activation::activate_site();
    }
    
    /**
     * Deactivate the plugin
     * 
     * @deprecated 6.0.0 Use PTA_SUS_Activation::deactivate() instead
     * @return void
     */
    public function deactivate() {
        $trace = debug_backtrace();
        $caller = $trace[1] ?? array();
        $file = $caller['file'] ?? '';
        $line = $caller['line'] ?? '';
        _deprecated_function(
            __METHOD__,
            '6.0.0',
            'PTA_SUS_Activation::deactivate() ' . sprintf('Called from %s line %s', $file, $line)
        );
        PTA_SUS_Activation::deactivate();
    }

	/**
	 * Register Gutenberg block assets
	 * 
	 * @deprecated 6.0.0 Use PTA_SUS_Blocks::register_blocks() instead
	 * @return void
	 */
	public function block_assets() {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Blocks::register_blocks() ' . sprintf('Called from %s line %s', $file, $line)
		);
		
		PTA_SUS_Blocks::register_blocks();
	}

	/**
	 * Render volunteer signup sheet block
	 * 
	 * @deprecated 6.0.0 Use PTA_SUS_Blocks::render_volunteer_signup_block() instead
	 * @param array $attributes Block attributes
	 * @return string HTML output
	 */
	public function render_volunteer_signup_block( $attributes ) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Blocks::render_volunteer_signup_block() ' . sprintf('Called from %s line %s', $file, $line)
		);
		
		return PTA_SUS_Blocks::render_volunteer_signup_block($attributes);
	}

	/**
	 * Render user signups block
	 * 
	 * @deprecated 6.0.0 Use PTA_SUS_Blocks::render_user_signups_block() instead
	 * @param array $attributes Block attributes
	 * @return string HTML output
	 */
	public function render_user_signups_block($attributes) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Blocks::render_user_signups_block() ' . sprintf('Called from %s line %s', $file, $line)
		);
		
		return PTA_SUS_Blocks::render_user_signups_block($attributes);
	}

	/**
	 * Render upcoming events block
	 * 
	 * @deprecated 6.0.0 Use PTA_SUS_Blocks::render_upcoming_events_block() instead
	 * @param array $attributes Block attributes
	 * @return string HTML output
	 */
	public function render_upcoming_events_block($attributes) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Blocks::render_upcoming_events_block() ' . sprintf('Called from %s line %s', $file, $line)
		);
		
		return PTA_SUS_Blocks::render_upcoming_events_block($attributes);
	}

	/**
	 * Render validation form block
	 * 
	 * @deprecated 6.0.0 Use PTA_SUS_Blocks::render_validation_form_block() instead
	 * @param array $attributes Block attributes
	 * @return string HTML output
	 */
	public function render_validation_form_block($attributes) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Blocks::render_validation_form_block() ' . sprintf('Called from %s line %s', $file, $line)
		);
		
		return PTA_SUS_Blocks::render_validation_form_block($attributes);
	}


}

function pta_sus_load_plugin_components() {
	// Load global functions first (if they don't use translations)
	require_once(__DIR__ .'/pta-sus-global-functions.php');

	// Now load classes that might use translations
	require_once(__DIR__ .'/classes/class-pta_sus_messages.php');
	require_once(__DIR__ .'/classes/class-pta_sus_template_tags.php');
	require_once(__DIR__ .'/classes/class-pta_sus_template_tags_helper.php');
	require_once(__DIR__ .'/classes/class-pta_sus_volunteer.php');
	require_once(__DIR__ .'/classes/class-pta_sus_text_registry.php');

	// Initialize template tags after translations are loaded
	PTA_SUS_Template_Tags_Helper::setup();
	PTA_SUS_Text_Registry::setup();
}
add_action('plugins_loaded', 'pta_sus_load_plugin_components', 5); // Priority 5 to run before other plugins_loaded hooks

global $pta_sus;
$pta_sus = new PTA_Sign_Up_Sheet();
// Hook initialization to plugins_loaded with a later priority
add_action('plugins_loaded', array($pta_sus, 'init_hooks'), 10); // After components are loaded

// Move the AJAX class loading to plugins_loaded too
add_action('plugins_loaded', function() {
	require_once(dirname(__FILE__).'/classes/class-pta-sus-ajax.php');
}, 15);

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
	$export_items = PTA_SUS_Signup_Functions::get_gdpr_user_export_items($email_address);

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
	$results = PTA_SUS_Signup_Functions::gdpr_delete_user_data($email_address);
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