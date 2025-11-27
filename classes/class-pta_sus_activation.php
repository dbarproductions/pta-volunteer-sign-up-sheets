<?php
/**
 * Activation Helper Class
 * 
 * Handles plugin activation, deactivation, and database setup for both
 * single-site and multi-site installations.
 * 
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Activation {

	/**
	 * Database version
	 * 
	 * @var string
	 */
	private static $db_version = '5.2.0';

	/**
	 * Get database version
	 * 
	 * @return string Database version
	 */
	public static function get_db_version() {
		return self::$db_version;
	}

	/**
	 * Activate plugin for single site or network
	 * 
	 * Handles activation for both single-site and multi-site installations.
	 * For network activations, runs activation for each blog.
	 * 
	 * @since 6.0.0
	 * @param bool $networkwide Whether this is a network-wide activation
	 * @return void
	 */
	public static function activate( $networkwide = false ) {
		global $wpdb;
		
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			// Check if it is a network activation - if so, run the activation function for each blog id
			if ( $networkwide ) {
				// Get all blog ids
				$blogids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
				foreach ( $blogids as $blog_id ) {
					switch_to_blog( $blog_id );
					self::activate_site();
					restore_current_blog();
				}
				return;
			}
		}
		self::activate_site();
	}

	/**
	 * Activate plugin for a single site
	 * 
	 * Creates database tables, sets up roles and capabilities, and schedules
	 * CRON jobs. This is called for each site in a multi-site installation.
	 * 
	 * @since 6.0.0
	 * @return void
	 */
	public static function activate_site() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Create new data object here so it works for multi-site activation
		$data = new PTA_SUS_Data();

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Database Tables
		// **********************************************************
		$sql = "CREATE TABLE {$data->tables['sheet']['name']} (
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
			clear_type varchar(20) NOT NULL DEFAULT 'days',
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
		$sql .= "CREATE TABLE {$data->tables['task']['name']} (
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
		$sql .= "CREATE TABLE {$data->tables['signup']['name']} (
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
		$validation_codes = $wpdb->prefix . 'pta_sus_validation_codes';
		$sql .= "CREATE TABLE {$validation_codes} (
			id INT NOT NULL AUTO_INCREMENT,
			firstname VARCHAR(100),
			lastname VARCHAR(100),
			email VARCHAR(100),
			code VARCHAR(200),
			ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY id (id)
		) $charset_collate;";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		update_option( "pta_sus_db_version", self::$db_version );

		// Add custom role and capability
		$role = get_role( 'author' );
		add_role( 'signup_sheet_manager', 'Sign-up Sheet Manager', $role->capabilities );
		$role = get_role( 'signup_sheet_manager' );
		if ( is_object( $role ) ) {
			$role->add_cap( 'manage_signup_sheets' );
		}

		$role = get_role( 'administrator' );
		if ( is_object( $role ) ) {
			$role->add_cap( 'manage_signup_sheets' );
		}

		// add capability to all super admins
		$supers = get_super_admins();
		foreach ( $supers as $admin ) {
			$user = new WP_User( 0, $admin );
			$user->add_cap( 'manage_signup_sheets' );
		}

		// Initialize plugin options with defaults
		// This ensures all options exist with default values on first activation
		if ( class_exists( 'PTA_SUS_Options_Manager' ) ) {
			PTA_SUS_Options_Manager::init_all_options( true );
		}

		// Schedule our Cron job for sending out email reminders
		// Wordpress only checks when someone visits the site, so
		// we'll keep this at hourly so that it hopefully runs at
		// least once a day
		if ( ! wp_next_scheduled( 'pta_sus_cron_job' ) ) {
			wp_schedule_event( time(), 'hourly', 'pta_sus_cron_job' );
		}
	}

	/**
	 * Deactivate plugin
	 * 
	 * Removes custom roles, capabilities, and scheduled CRON jobs.
	 * 
	 * @since 6.0.0
	 * @return void
	 */
	public static function deactivate() {
		// Check permissions and referer
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Remove custom role and capability
		$role = get_role( 'signup_sheet_manager' );
		if ( is_object( $role ) ) {
			$role->remove_cap( 'manage_signup_sheets' );
			$role->remove_cap( 'read' );
			remove_role( 'signup_sheet_manager' );
		}
		$role = get_role( 'administrator' );
		if ( is_object( $role ) ) {
			$role->remove_cap( 'manage_signup_sheets' );
		}

		wp_clear_scheduled_hook( 'pta_sus_cron_job' );
	}

	/**
	 * Handle new blog creation in multi-site
	 * 
	 * WordPress action hook callback for when a new blog is created in a
	 * multi-site installation. Activates the plugin for the new blog.
	 * 
	 * @since 6.0.0
	 * @param int $blog_id New blog ID
	 * @param int $user_id User ID who created the blog
	 * @param string $domain Blog domain
	 * @param string $path Blog path
	 * @param int $site_id Site ID
	 * @param array $meta Blog meta data
	 * @return void
	 */
	public static function new_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
		global $wpdb;

		if ( is_plugin_active_for_network( 'pta-volunteer-sign-up-sheets/pta-volunteer-sign-up-sheets.php' ) ) {
			switch_to_blog( $blog_id );
			self::activate_site();
			restore_current_blog();
		}
	}

	/**
	 * Check if database needs upgrade
	 * 
	 * Compares current database version with plugin version and returns
	 * true if upgrade is needed.
	 * 
	 * @since 6.0.0
	 * @return bool True if upgrade needed, false otherwise
	 */
	public static function needs_upgrade() {
		$current = get_option( "pta_sus_db_version" );
		return ( $current < self::$db_version );
	}
}

