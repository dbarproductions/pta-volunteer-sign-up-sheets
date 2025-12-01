<?php
/**
 * Assets Manager Class
 * 
	 * Handles registration of all scripts and styles for the Volunteer Sign-Up Sheets
	 * plugin. This includes DataTables, date pickers, Select2, Quill editor, and autocomplete assets.
	 * These assets are registered (not enqueued) so they can be conditionally loaded
	 * and shared with other PTA extensions.
 * 
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Assets {

	/**
	 * Register all scripts and styles
	 * 
	 * Registers all plugin assets so they can be enqueued by the plugin or
	 * other extensions when needed. Assets are registered (not enqueued) to
	 * allow conditional loading and sharing between PTA extensions.
	 * 
	 * Called during both 'wp_enqueue_scripts' and 'admin_enqueue_scripts' hooks.
	 * 
	 * @since 6.0.0
	 * @return void
	 * 
	 * @see register_datatables() DataTables library registration
	 * @see register_datepicker() Date picker library registration
	 * @see register_select2() Select2 library registration
	 * @see register_quill() Quill editor library registration
	 * @see register_autocomplete() Autocomplete library registration
	 */
	public static function register_scripts() {
		self::register_datatables();
		self::register_datepicker();
		self::register_select2();
		self::register_quill();
		self::register_autocomplete();
	}

	/**
	 * Register DataTables assets
	 * 
	 * Registers DataTables CSS and JavaScript. These can be enqueued by
	 * extensions using the handles 'pta-datatables-style' and 'pta-datatables'.
	 * 
	 * @since 6.0.0
	 * @return void
	 */
	public static function register_datatables() {
		$plugin_url = defined('PTA_VOLUNTEER_SUS_URL') ? PTA_VOLUNTEER_SUS_URL : plugins_url('', dirname(__FILE__));
		
		wp_register_style(
			'pta-datatables-style',
			$plugin_url . '/datatables/datatables.min.css',
			array(),
			'1.10.23'
		);
		
		wp_register_script(
			'pta-datatables',
			$plugin_url . '/datatables/datatables.min.js',
			array('jquery'),
			'1.10.23',
			true
		);
	}

	/**
	 * Register date picker assets
	 * 
	 * Registers jQuery date picker CSS and JavaScript. These can be enqueued
	 * by extensions using the handles 'pta-jquery-datepick' (style and script).
	 * 
	 * @since 6.0.0
	 * @return void
	 */
	public static function register_datepicker() {
		$plugin_url = defined('PTA_VOLUNTEER_SUS_URL') ? PTA_VOLUNTEER_SUS_URL : plugins_url('', dirname(__FILE__));
		
		// jQuery Plugin (dependency for date picker)
		wp_register_script(
			'jquery-plugin',
			$plugin_url . '/assets/js/jquery.plugin.min.js',
			array('jquery'),
			'1.0.0',
			false
		);
		
		// Date picker script
		wp_register_script(
			'pta-jquery-datepick',
			$plugin_url . '/assets/js/jquery.datepick.min.js',
			array('jquery', 'jquery-plugin'),
			'5.1.0',
			false
		);
		
		// Date picker style
		wp_register_style(
			'pta-jquery-datepick',
			$plugin_url . '/assets/css/jquery.datepick.css',
			array(),
			'5.1.0'
		);
	}

	/**
	 * Register Select2 assets
	 * 
	 * Registers Select2 CSS and JavaScript from CDN. These can be enqueued
	 * by extensions using the handles 'pta-select2' (style and script).
	 * 
	 * Note: Select2 is loaded from CDN and is used by several PTA extensions.
	 * 
	 * @since 6.0.0
	 * @return void
	 */
	public static function register_select2() {
		wp_register_style(
			'pta-select2',
			'https://cdn.jsdelivr.net/npm/select2/dist/css/select2.min.css',
			array(),
			'4.1.0'
		);
		
		wp_register_script(
			'pta-select2',
			'https://cdn.jsdelivr.net/npm/select2/dist/js/select2.min.js',
			array('jquery'),
			'4.1.0',
			false
		);
	}

	/**
	 * Register Quill editor assets
	 * 
	 * Registers Quill CSS and JavaScript from CDN. These can be enqueued by
	 * extensions using the handles 'pta-quill' (style and script).
	 * 
	 * Note: Quill is loaded from CDN and is used by Custom Fields extension
	 * as an alternative to wp_editor() for HTML fields.
	 * 
	 * @since 6.0.0
	 * @return void
	 */
	public static function register_quill() {
		wp_register_style(
			'pta-quill',
			'https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css',
			array(),
			'2.0.3'
		);
		
		wp_register_script(
			'pta-quill',
			'https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js',
			array(),
			'2.0.3',
			false
		);
	}

	/**
	 * Register autocomplete assets
	 * 
	 * Registers autocomplete CSS and JavaScript. These can be enqueued by
	 * extensions using the handles 'pta-sus-autocomplete' (style and script).
	 * 
	 * Also localizes the script with AJAX URL and nonce for autocomplete functionality.
	 * Shared with Calendar extension.
	 * 
	 * @since 6.0.0
	 * @return void
	 */
	public static function register_autocomplete() {
		$plugin_url = defined('PTA_VOLUNTEER_SUS_URL') ? PTA_VOLUNTEER_SUS_URL : plugins_url('', dirname(__FILE__));
		
		$version = defined('PTA_VOLUNTEER_SUS_VERSION_NUM') ? PTA_VOLUNTEER_SUS_VERSION_NUM : '6.0.0';
		
		wp_register_style(
			'pta-sus-autocomplete',
			$plugin_url . '/assets/css/jquery.autocomplete.min.css',
			array(),
			$version
		);
		
		// Register livesearch.js (non-minified for easier debugging)
		// Note: If a minified version exists, ensure it's updated or removed
		wp_register_script(
			'pta-sus-autocomplete',
			$plugin_url . '/assets/js/livesearch.js',
			array(),
			$version . '-' . filemtime(plugin_dir_path(__DIR__) . 'assets/js/livesearch.js'), // Add filemtime to bust cache
			true
		);
		
		// Localize script with AJAX data
		wp_localize_script(
			'pta-sus-autocomplete',
			'ptaSUS',
			array(
				'ajaxurl' => admin_url('admin-ajax.php'),
				'ptanonce' => wp_create_nonce('ajax-pta-nonce')
			)
		);
	}
}

