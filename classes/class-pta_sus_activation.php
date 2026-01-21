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
	private static $db_version = '6.2.0';

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

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Database Tables
		// **********************************************************
        $sheets_table = $wpdb->prefix . 'pta_sus_sheets';
		$sql = "CREATE TABLE {$sheets_table} (
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
			author_id INT NOT NULL DEFAULT 0,
			author_email VARCHAR(100) NOT NULL DEFAULT '',
			confirmation_email_template_id INT(11) NOT NULL DEFAULT 0,
			reminder1_email_template_id INT(11) NOT NULL DEFAULT 0,
			reminder2_email_template_id INT(11) NOT NULL DEFAULT 0,
			clear_email_template_id INT(11) NOT NULL DEFAULT 0,
			reschedule_email_template_id INT(11) NOT NULL DEFAULT 0,
			signup_validation_email_template_id INT(11) NOT NULL DEFAULT 0,
			PRIMARY KEY id (id),
			KEY `first_date` (`first_date`),
			KEY `last_date` (`last_date`),
			KEY `author_id` (`author_id`)
		) $charset_collate;";
        $tasks_table = $wpdb->prefix . 'pta_sus_tasks';
		$sql .= "CREATE TABLE {$tasks_table} (
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
			confirmation_email_template_id INT(11) NOT NULL DEFAULT 0,
			reminder1_email_template_id INT(11) NOT NULL DEFAULT 0,
			reminder2_email_template_id INT(11) NOT NULL DEFAULT 0,
			clear_email_template_id INT(11) NOT NULL DEFAULT 0,
			reschedule_email_template_id INT(11) NOT NULL DEFAULT 0,
			PRIMARY KEY id (id),
			KEY `sheet_id` (`sheet_id`)
		) $charset_collate;";
        $signups_table = $wpdb->prefix . 'pta_sus_signups';
		$sql .= "CREATE TABLE {$signups_table} (
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
		$email_templates_table = $wpdb->prefix . 'pta_sus_email_templates';
		$sql .= "CREATE TABLE {$email_templates_table} (
			id INT(11) NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL DEFAULT '',
			subject VARCHAR(255) NOT NULL DEFAULT '',
			body TEXT NOT NULL,
			from_name VARCHAR(255) NOT NULL DEFAULT '',
			from_email VARCHAR(100) NOT NULL DEFAULT '',
			author_id INT(11) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY author_id (author_id)
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
			$role->add_cap( 'manage_others_signup_sheets' );
		}

		// add capability to all super admins
		$supers = get_super_admins();
		foreach ( $supers as $admin ) {
			$user = new WP_User( 0, $admin );
			$user->add_cap( 'manage_signup_sheets' );
			$user->add_cap( 'manage_others_signup_sheets' );
		}

		// Add manage_others_signup_sheets capability to Signup Sheet Manager role
		$role = get_role( 'signup_sheet_manager' );
		if ( is_object( $role ) ) {
			$role->add_cap( 'manage_others_signup_sheets' );
		}

		// Create Signup Sheet Author role
		$author_role = get_role( 'signup_sheet_author' );
		if ( ! is_object( $author_role ) ) {
			$role = get_role( 'author' );
			$author_caps = is_object( $role ) ? $role->capabilities : array();
			add_role( 'signup_sheet_author', 'Signup Sheet Author', $author_caps );
			$author_role = get_role( 'signup_sheet_author' );
			if ( is_object( $author_role ) ) {
				$author_role->add_cap( 'read' );
				$author_role->add_cap( 'manage_signup_sheets' );
				// Do NOT add manage_others_signup_sheets - Authors can only manage their own sheets
			}
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

	/**
	 * Run database upgrades
	 * 
	 * Checks current database version and runs appropriate upgrade functions
	 * in sequence. This allows for incremental upgrades without requiring
	 * deactivation/reactivation.
	 * 
	 * @since 6.1.0
	 * @return void
	 */
	public static function run_upgrades() {
		$current_version = get_option( "pta_sus_db_version", '0.0.0' );
		
		// Run upgrades in sequence based on version
		if ( version_compare( $current_version, '6.1.0', '<' ) ) {
			self::upgrade_to_6_1_0();
		}
		
		if ( version_compare( $current_version, '6.2.0', '<' ) ) {
			self::upgrade_to_6_2_0();
		}
		
		// Check for missing user_validation template migration (runs regardless of version if templates were migrated)
		// This handles cases where sites upgraded to 6.2.0 before user_validation migration was added
		if ( get_option( 'pta_sus_email_templates_migrated', false ) ) {
			self::maybe_migrate_user_validation_template();
		}
	}

	/**
	 * Upgrade database to version 6.1.0
	 * 
	 * Adds author_id and author_email columns to pta_sus_sheets table,
	 * creates Signup Sheet Author role, and adds manage_others_signup_sheets
	 * capability to Administrator and Signup Sheet Manager roles.
	 * 
	 * @since 6.1.0
	 * @return void
	 */
	private static function upgrade_to_6_1_0() {
		global $wpdb;
		
		// Create new data object to get table name

		$table_name = $wpdb->prefix . 'pta_sus_sheets';
		
		// Check if columns already exist
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}", ARRAY_A );
		$column_names = array();
		foreach ( $columns as $column ) {
			$column_names[] = $column['Field'];
		}
		
		// Add author_id column if it doesn't exist
		if ( ! in_array( 'author_id', $column_names ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN author_id INT NOT NULL DEFAULT 0" );
		}
		
		// Add author_email column if it doesn't exist
		if ( ! in_array( 'author_email', $column_names ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN author_email VARCHAR(100) NOT NULL DEFAULT ''" );
		}
		
		// Check if index exists on author_id
		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table_name} WHERE Key_name = 'author_id'", ARRAY_A );
		if ( empty( $indexes ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD INDEX author_id (author_id)" );
		}
		
		// Set existing sheets to author_id = 0 and author_email = '' (legacy sheets)
		// Only update if they're currently NULL or empty (shouldn't happen, but safety check)
		// Note: Using direct query since we're setting fixed values and checking for NULL is safe
		$wpdb->query( 
			"UPDATE " . esc_sql( $table_name ) . " SET author_id = 0, author_email = '' WHERE author_id IS NULL OR author_email IS NULL"
		);
		
		// Add manage_others_signup_sheets capability to Administrator role
		$role = get_role( 'administrator' );
		if ( is_object( $role ) && ! $role->has_cap( 'manage_others_signup_sheets' ) ) {
			$role->add_cap( 'manage_others_signup_sheets' );
		}
		
		// Add manage_others_signup_sheets capability to Signup Sheet Manager role
		$role = get_role( 'signup_sheet_manager' );
		if ( is_object( $role ) && ! $role->has_cap( 'manage_others_signup_sheets' ) ) {
			$role->add_cap( 'manage_others_signup_sheets' );
		}
		
		// Add capability to all super admins
		$supers = get_super_admins();
		foreach ( $supers as $admin ) {
			$user = new WP_User( 0, $admin );
			if ( ! $user->has_cap( 'manage_others_signup_sheets' ) ) {
				$user->add_cap( 'manage_others_signup_sheets' );
			}
		}
		
		// Create Signup Sheet Author role if it doesn't exist
		$author_role = get_role( 'signup_sheet_author' );
		if ( ! is_object( $author_role ) ) {
			$role = get_role( 'author' );
			$author_caps = is_object( $role ) ? $role->capabilities : array();
			add_role( 'signup_sheet_author', 'Signup Sheet Author', $author_caps );
			$author_role = get_role( 'signup_sheet_author' );
			if ( is_object( $author_role ) ) {
				$author_role->add_cap( 'read' );
				$author_role->add_cap( 'manage_signup_sheets' );
				// Do NOT add manage_others_signup_sheets - Authors can only manage their own sheets
			}
		}
		
		// Update database version
		update_option( "pta_sus_db_version", '6.1.0' );
	}

	/**
	 * Upgrade database to version 6.2.0
	 * 
	 * Creates email templates table, adds email template ID columns to sheets table,
	 * migrates existing email templates from options to database, and migrates
	 * Customizer extension templates if active.
	 * 
	 * @since 6.2.0
	 * @return void
	 */
	private static function upgrade_to_6_2_0() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		
		// Create email templates table (dbDelta will create if it doesn't exist, update if it does)
		$email_templates_table = $wpdb->prefix . 'pta_sus_email_templates';
		// Check if table exists first
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $email_templates_table ) . "'" );
		
		if ( $table_exists !== $email_templates_table ) {
			// Match the exact format used in activate_site() for consistency
			$sql = "CREATE TABLE {$email_templates_table} (
				id INT(11) NOT NULL AUTO_INCREMENT,
				title VARCHAR(255) NOT NULL DEFAULT '',
				subject VARCHAR(255) NOT NULL DEFAULT '',
				body TEXT NOT NULL,
				from_name VARCHAR(255) NOT NULL DEFAULT '',
				from_email VARCHAR(100) NOT NULL DEFAULT '',
				author_id INT(11) NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY id (id),
				KEY `author_id` (`author_id`)
			) $charset_collate;";
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
			
			// Double-check table was created - if not, try direct query
			$table_exists_after = $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $email_templates_table ) . "'" );
			if ( $table_exists_after !== $email_templates_table ) {
				// If dbDelta failed, try direct query as fallback
				$wpdb->query( $sql );
			}
		}

        $sheet_table = $wpdb->prefix . 'pta_sus_sheets';
		
		// Check existing columns
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$sheet_table}", ARRAY_A );
		$column_names = array();
		foreach ( $columns as $column ) {
			$column_names[] = $column['Field'];
		}
		
		$template_columns = array(
			'confirmation_email_template_id',
			'reminder1_email_template_id',
			'reminder2_email_template_id',
			'clear_email_template_id',
			'reschedule_email_template_id',
		);
		
		foreach ( $template_columns as $column_name ) {
			if ( ! in_array( $column_name, $column_names ) ) {
				$wpdb->query( "ALTER TABLE {$sheet_table} ADD COLUMN {$column_name} INT(11) NOT NULL DEFAULT 0" );
			}
		}
		
		// Add email template ID columns to tasks table
		$task_table = $wpdb->prefix . 'pta_sus_tasks';
		
		// Check existing columns
		$task_columns = $wpdb->get_results( "SHOW COLUMNS FROM {$task_table}", ARRAY_A );
		$task_column_names = array();
		foreach ( $task_columns as $column ) {
			$task_column_names[] = $column['Field'];
		}
		
		foreach ( $template_columns as $column_name ) {
			if ( ! in_array( $column_name, $task_column_names ) ) {
				$wpdb->query( "ALTER TABLE {$task_table} ADD COLUMN {$column_name} INT(11) NOT NULL DEFAULT 0" );
			}
		}
		
		// Migrate existing email templates from options to database
		self::migrate_email_templates();
		
		// Update database version
		update_option( "pta_sus_db_version", '6.2.0' );
	}

	/**
	 * Migrate email templates from options to database
	 * 
	 * Converts existing email templates from options arrays to PTA_SUS_Email_Template records,
	 * migrates Customizer extension templates if active, and maps sheet assignments.
	 * 
	 * @since 6.2.0
	 * @return void
	 */
	private static function migrate_email_templates() {
		global $wpdb;
		
		// Check if migration has already been done
		$migration_done = get_option( 'pta_sus_email_templates_migrated', false );
		if ( $migration_done ) {
			return; // Already migrated
		}
		
		$email_options = get_option( 'pta_volunteer_sus_email_options', array() );
		$validation_options = get_option( 'pta_volunteer_sus_validation_options', array() );
		$existing_defaults = get_option( 'pta_volunteer_sus_email_template_defaults', array() );
		$defaults = array();
		$from_email = isset( $email_options['from_email'] ) ? $email_options['from_email'] : '';
		
		// Helper function to check if a system default template already exists
		$check_existing_default = function( $email_type ) use ( $existing_defaults ) {
			if ( ! isset( $existing_defaults[ $email_type ] ) || $existing_defaults[ $email_type ] <= 0 ) {
				return false; // No existing default
			}
			
			$template_id = absint( $existing_defaults[ $email_type ] );
			$template = new PTA_SUS_Email_Template( $template_id );
			
			// Verify template exists and is still marked as system default
			if ( $template->id > 0 && $template->is_system_default() ) {
				return $template_id; // Valid existing default
			}
			
			return false; // Template doesn't exist or isn't a system default anymore
		};
		
		// Migrate main plugin email templates
		$email_types = array(
			'confirmation' => array(
				'subject_key' => 'confirmation_email_subject',
				'template_key' => 'confirmation_email_template',
				'title' => __( 'Confirmation Email (System Default)', 'pta-volunteer-sign-up-sheets' ),
			),
			'reminder1' => array(
				'subject_key' => 'reminder_email_subject',
				'template_key' => 'reminder_email_template',
				'title' => __( 'Reminder 1 Email (System Default)', 'pta-volunteer-sign-up-sheets' ),
			),
			'reminder2' => array(
				'subject_key' => 'reminder2_email_subject',
				'template_key' => 'reminder2_email_template',
				'title' => __( 'Reminder 2 Email (System Default)', 'pta-volunteer-sign-up-sheets' ),
			),
			'clear' => array(
				'subject_key' => 'clear_email_subject',
				'template_key' => 'clear_email_template',
				'title' => __( 'Clear Email (System Default)', 'pta-volunteer-sign-up-sheets' ),
			),
			'reschedule' => array(
				'subject_key' => 'reschedule_email_subject',
				'template_key' => 'reschedule_email_template',
				'title' => __( 'Reschedule Email (System Default)', 'pta-volunteer-sign-up-sheets' ),
			),
		);
		
		foreach ( $email_types as $email_type => $config ) {
			// Check if system default already exists
			$existing_template_id = $check_existing_default( $email_type );
			if ( $existing_template_id ) {
				$defaults[ $email_type ] = $existing_template_id;
				continue; // Skip creating duplicate
			}
			
			$subject = isset( $email_options[ $config['subject_key'] ] ) ? $email_options[ $config['subject_key'] ] : '';
			$body = isset( $email_options[ $config['template_key'] ] ) ? $email_options[ $config['template_key'] ] : '';
			
			// Skip reminder2 if empty (will fall back to reminder1)
			if ( 'reminder2' === $email_type && ( empty( $subject ) || empty( $body ) ) ) {
				continue; // Leave as 0, will use reminder1 fallback
			}
			
			// Only create template if both subject and body exist
			if ( ! empty( $subject ) && ! empty( $body ) ) {
				$template = new PTA_SUS_Email_Template();
				$template->title = $config['title'];
				$template->subject = $subject;
				$template->body = $body;
				$template->from_email = $from_email;
				$template->author_id = 0;
				$template->created_at = current_time( 'mysql' );
				$template->updated_at = current_time( 'mysql' );
				
				$template_id = $template->save();
				if ( $template_id > 0 ) {
					$defaults[ $email_type ] = $template_id;
				}
			}
		}
		
		// Migrate validation email templates
		$existing_signup_validation_id = $check_existing_default( 'signup_validation' );
		if ( $existing_signup_validation_id ) {
			$defaults['signup_validation'] = $existing_signup_validation_id;
		} elseif ( ! empty( $validation_options['signup_validation_email_subject'] ) && ! empty( $validation_options['signup_validation_email_template'] ) ) {
			$template = new PTA_SUS_Email_Template();
			$template->title = __( 'Signup Validation Email (System Default)', 'pta-volunteer-sign-up-sheets' );
			$template->subject = $validation_options['signup_validation_email_subject'];
			$template->body = $validation_options['signup_validation_email_template'];
			$template->from_email = $from_email;
			$template->author_id = 0;
			$template->created_at = current_time( 'mysql' );
			$template->updated_at = current_time( 'mysql' );
			
			$template_id = $template->save();
			if ( $template_id > 0 ) {
				$defaults['signup_validation'] = $template_id;
			}
		}
		
		// Migrate user validation email template
		$existing_user_validation_id = $check_existing_default( 'user_validation' );
		if ( $existing_user_validation_id ) {
			$defaults['user_validation'] = $existing_user_validation_id;
		} elseif ( ! empty( $validation_options['user_validation_email_subject'] ) && ! empty( $validation_options['user_validation_email_template'] ) ) {
			$template = new PTA_SUS_Email_Template();
			$template->title = __( 'User Validation Email (System Default)', 'pta-volunteer-sign-up-sheets' );
			$template->subject = $validation_options['user_validation_email_subject'];
			$template->body = $validation_options['user_validation_email_template'];
			$template->from_email = $from_email;
			$template->author_id = 0;
			$template->created_at = current_time( 'mysql' );
			$template->updated_at = current_time( 'mysql' );
			
			$template_id = $template->save();
			if ( $template_id > 0 ) {
				$defaults['user_validation'] = $template_id;
			}
		}
		
		// Migrate Customizer extension templates if active
		if ( class_exists( 'PTA_SUS_CUSTOMIZER_INTEGRATOR' ) ) {
			self::migrate_customizer_templates( $from_email );
		}
		
		// Save system default template IDs (merge with existing to preserve any that weren't migrated)
		if ( ! empty( $defaults ) ) {
			$merged_defaults = array_merge( $existing_defaults, $defaults );
			update_option( 'pta_volunteer_sus_email_template_defaults', $merged_defaults );
		} elseif ( ! empty( $existing_defaults ) ) {
			// Even if no new defaults were created, preserve existing ones
			update_option( 'pta_volunteer_sus_email_template_defaults', $existing_defaults );
		}
		
		// Mark migration as complete
		update_option( 'pta_sus_email_templates_migrated', true );
	}

	/**
	 * Public wrapper to migrate Customizer extension email templates
	 *
	 * Can be called manually from admin tools page to re-run the migration
	 * if the Customizer extension was activated after the initial upgrade.
	 *
	 * @since 6.2.0
	 * @return void
	 */
	public static function run_customizer_template_migration() {
		$email_options = get_option( 'pta_volunteer_sus_email_options', array() );
		$from_email = isset( $email_options['from_email'] ) ? $email_options['from_email'] : get_bloginfo( 'admin_email' );
		self::migrate_customizer_templates( $from_email );
	}

	/**
	 * Migrate Customizer extension email templates
	 *
	 * Converts Customizer's custom email templates to PTA_SUS_Email_Template records
	 * and maps sheet assignments to new template IDs.
	 *
	 * @since 6.2.0
	 * @param string $from_email Default from email
	 * @return void
	 */
	private static function migrate_customizer_templates( $from_email ) {
		global $wpdb;
		
		$custom_emails = get_option( 'pta_volunteer_sus_customizer_custom_emails', array() );
		$sheet_emails = get_option( 'pta_volunteer_sus_customizer_sheet_emails', array() );
		
		if ( empty( $custom_emails ) ) {
			return; // No Customizer templates to migrate
		}
		
		// Map old slugs to new template IDs
		$slug_to_id_map = array();
		
		// Migrate each Customizer template
		foreach ( $custom_emails as $slug => $email_data ) {
			if ( empty( $email_data['name'] ) || empty( $email_data['subject'] ) || empty( $email_data['body'] ) ) {
				continue; // Skip invalid templates
			}
			
			$template = new PTA_SUS_Email_Template();
			$template->title = sanitize_text_field( $email_data['name'] );
			$template->subject = sanitize_text_field( $email_data['subject'] );
			$template->body = wp_kses_post( $email_data['body'] );
			$template->from_email = $from_email;
			$template->author_id = 0; // Available to all users (migrated template)
			$template->created_at = current_time( 'mysql' );
			$template->updated_at = current_time( 'mysql' );
			
			$template_id = $template->save();
			if ( $template_id > 0 ) {
				$slug_to_id_map[ $slug ] = $template_id;
			}
		}
		
		// Map sheet assignments from old slugs to new template IDs
		if ( ! empty( $sheet_emails ) && ! empty( $slug_to_id_map ) ) {
			$sheet_table = $wpdb->prefix . 'pta_sus_sheets';
			
			// Map email type to column name
			$email_type_map = array(
				'confirmation' => 'confirmation_email_template_id',
				'reminder' => 'reminder1_email_template_id',
				'reminder2' => 'reminder2_email_template_id',
				'reschedule' => 'reschedule_email_template_id',
				'clear' => 'clear_email_template_id',
				// Note: 'validate' (signup_validation) is system-wide only, not sheet/task level
			);
			
			foreach ( $sheet_emails as $sheet_id => $email_assignments ) {
				if ( ! is_array( $email_assignments ) ) {
					continue;
				}
				
				$updates = array();
				$update_needed = false;
				
				foreach ( $email_assignments as $email_type => $slug ) {
					if ( empty( $slug ) || ! isset( $slug_to_id_map[ $slug ] ) ) {
						continue; // Skip invalid or unmapped slugs
					}
					
					if ( ! isset( $email_type_map[ $email_type ] ) ) {
						continue; // Skip unknown email types
					}
					
					$column_name = $email_type_map[ $email_type ];
					$template_id = $slug_to_id_map[ $slug ];
					
					$updates[ $column_name ] = $template_id;
					$update_needed = true;
				}
				
				// Update sheet with new template IDs
				if ( $update_needed ) {
					$sheet_id = absint( $sheet_id );
					$wpdb->update(
						$sheet_table,
						$updates,
						array( 'id' => $sheet_id ),
						array_fill( 0, count( $updates ), '%d' ),
						array( '%d' )
					);
				}
			}
		}
		
		// Handle backward compatibility for old Customizer versions
		$customizer_version = defined( 'PTA_VOL_SUS_CUSTOMIZER_VERSION' ) ? PTA_VOL_SUS_CUSTOMIZER_VERSION : '0.0.0';
		
		// If Customizer version is old (before templates moved to main plugin), disable its hooks
		if ( version_compare( $customizer_version, '4.1.0', '<' ) ) {
			// Remove Customizer's email template filter hooks
			remove_filter( 'pta_sus_email_subject', array( 'PTA_SUS_CUSTOMIZER_INTEGRATOR', 'customize_email_subject' ) );
			remove_filter( 'pta_sus_email_template', array( 'PTA_SUS_CUSTOMIZER_INTEGRATOR', 'customize_email_template' ) );
			
			// Add dismissible admin notice (site-wide, not per-user)
			$notice_dismissed = get_option( 'pta_sus_email_template_notice_dismissed', false );
			if ( ! $notice_dismissed ) {
				add_action( 'admin_notices', array( __CLASS__, 'show_customizer_migration_notice' ) );
			}
		}
	}

	/**
	 * Migrate user_validation email template if missing
	 * 
	 * Checks if the user_validation template was migrated and migrates it
	 * if it's missing from the system defaults. This handles cases where
	 * sites upgraded to 6.2.0 before the user_validation migration was added.
	 * 
	 * @since 6.2.0
	 * @return void
	 */
	private static function maybe_migrate_user_validation_template() {
		$defaults = get_option( 'pta_volunteer_sus_email_template_defaults', array() );
		
		// Check if system default already exists and is valid
		if ( isset( $defaults['user_validation'] ) && $defaults['user_validation'] > 0 ) {
			$template_id = absint( $defaults['user_validation'] );
			$template = new PTA_SUS_Email_Template( $template_id );
			// If template exists and is still marked as system default, skip migration
			if ( $template->id > 0 && $template->is_system_default() ) {
				return; // Already migrated and valid
			}
			// Template was deleted or changed, so we'll create a new one below
		}
		
		$validation_options = get_option( 'pta_volunteer_sus_validation_options', array() );
		$email_options = get_option( 'pta_volunteer_sus_email_options', array() );
		
		// Check if we have user_validation data to migrate
		if ( empty( $validation_options['user_validation_email_subject'] ) || empty( $validation_options['user_validation_email_template'] ) ) {
			return; // No data to migrate
		}
		
		$from_email = isset( $email_options['from_email'] ) ? $email_options['from_email'] : get_bloginfo( 'admin_email' );
		
		$template = new PTA_SUS_Email_Template();
		$template->title = __( 'User Validation Email (System Default)', 'pta-volunteer-sign-up-sheets' );
		$template->subject = $validation_options['user_validation_email_subject'];
		$template->body = $validation_options['user_validation_email_template'];
		$template->from_email = $from_email;
		$template->author_id = 0;
		$template->created_at = current_time( 'mysql' );
		$template->updated_at = current_time( 'mysql' );
		
		$template_id = $template->save();
		if ( $template_id > 0 ) {
			$defaults['user_validation'] = $template_id;
			update_option( 'pta_volunteer_sus_email_template_defaults', $defaults );
		}
	}

	/**
	 * Show admin notice about email templates migration
	 * 
	 * Displays a dismissible notice informing users that email templates
	 * have been moved to the main plugin.
	 * 
	 * @since 6.2.0
	 * @return void
	 */
	public static function show_customizer_migration_notice() {
		// Check if notice was dismissed
		if ( isset( $_GET['pta_sus_dismiss_template_notice'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'pta_sus_dismiss_template_notice' ) ) {
			update_option( 'pta_sus_email_template_notice_dismissed', true );
			return;
		}
		
		$dismiss_url = wp_nonce_url( add_query_arg( 'pta_sus_dismiss_template_notice', '1' ), 'pta_sus_dismiss_template_notice' );
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<strong><?php _e( 'PTA Volunteer Sign-Up Sheets:', 'pta-volunteer-sign-up-sheets' ); ?></strong>
				<?php _e( 'Email templates have been migrated to the main plugin. You can now manage all email templates from the "Email Templates" menu under Sign-up Sheets. Custom email templates from the Customizer extension have been preserved and are available to all users.', 'pta-volunteer-sign-up-sheets' ); ?>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" class="notice-dismiss"><span class="screen-reader-text"><?php _e( 'Dismiss this notice.', 'pta-volunteer-sign-up-sheets' ); ?></span></a>
			</p>
		</div>
		<?php
	}
}

