<?php
/**
 * Blocks Class
 * 
 * Handles Gutenberg block registration and rendering for the Volunteer
 * Sign-Up Sheets plugin. This includes signup sheet blocks, user signups
 * blocks, upcoming events blocks, and validation form blocks.
 * 
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Blocks {

	/**
	 * Register all Gutenberg blocks
	 * 
	 * Registers all block types with their render callbacks. Called during
	 * WordPress 'init' action hook.
	 * 
	 * @since 6.0.0
	 * @return void
	 */
	public static function register_blocks() {
		$plugin_dir = defined('PTA_VOLUNTEER_SUS_DIR') ? PTA_VOLUNTEER_SUS_DIR : dirname(__DIR__);
		
		register_block_type( $plugin_dir . '/blocks/signup-sheet/block.json', array(
			'render_callback' => array( __CLASS__, 'render_volunteer_signup_block' )
		) );

		register_block_type( $plugin_dir . '/blocks/user-signups/block.json', array(
			'render_callback' => array( __CLASS__, 'render_user_signups_block' )
		) );

		register_block_type( $plugin_dir . '/blocks/upcoming-events/block.json', array(
			'render_callback' => array( __CLASS__, 'render_upcoming_events_block' )
		) );

		register_block_type( $plugin_dir . '/blocks/validation-form/block.json', array(
			'render_callback' => array( __CLASS__, 'render_validation_form_block' )
		) );
	}

	/**
	 * Render volunteer signup sheet block
	 * 
	 * Converts block attributes to shortcode format and executes the
	 * [pta_sign_up_sheet] shortcode.
	 * 
	 * @since 6.0.0
	 * @param array $attributes Block attributes
	 * @return string HTML output from shortcode
	 */
	public static function render_volunteer_signup_block( $attributes ) {
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

	/**
	 * Render user signups block
	 * 
	 * Calls the display helper function directly to avoid deprecation notices
	 * from going through the shortcode handler. Handles messages and empty state
	 * similar to the shortcode handler.
	 * 
	 * @since 6.0.0
	 * @param array $attributes Block attributes
	 * @return string HTML output
	 */
	public static function render_user_signups_block($attributes) {
		// Initialize display functions if needed (uses global $pta_sus->public->volunteer if available)
		global $pta_sus;
		if (isset($pta_sus, $pta_sus->public, $pta_sus->public->volunteer)) {
			PTA_SUS_Public_Display_Functions::initialize(array(), $pta_sus->public->volunteer);
		} else {
			PTA_SUS_Public_Display_Functions::initialize();
		}
		
		// Show any messages
		$return = PTA_SUS_Messages::show_messages();
		
		// Set messages as displayed and clear them
		PTA_SUS_Public_Display_Functions::set_messages_displayed(true);
		PTA_SUS_Messages::clear_messages();
		
		// Build attributes array for helper function
		$atts = array(
			'show_time' => $attributes['show_time'] ?? 'yes'
		);
		
		// Get the signups list using the new helper function
		$return .= PTA_SUS_Public_Display_Functions::get_user_signups_list($atts);
		
		// Handle empty state
		if (empty($return)) {
			$return = apply_filters('pta_sus_public_output', __('You do not have any current signups', 'pta-volunteer-sign-up-sheets'), 'no_user_signups_message');
		} else {
			// Add clear validation message if needed (via global $pta_sus->public if available)
			if (isset($pta_sus, $pta_sus->public)) {
				$return .= $pta_sus->public->maybe_get_clear_validation_message();
			}
		}
		
		return $return;
	}

	/**
	 * Render upcoming events block
	 * 
	 * Uses the PTA_SUS_Widget to render the upcoming events list.
	 * 
	 * @since 6.0.0
	 * @param array $attributes Block attributes
	 * @return string HTML output from widget
	 */
	public static function render_upcoming_events_block($attributes) {
		$widget = new PTA_SUS_Widget();

		$widget_args = array(
			'before_widget' => '',
			'after_widget' => '',
			'before_title' => '<h2>',
			'after_title' => '</h2>'
		);

		$instance = array(
			'title' => !empty($attributes['title']) ? sanitize_text_field($attributes['title']) : 'Current Volunteer Opportunities',
			'num_items' => absint($attributes['num_items'] ?? 10),
			'show_what' => sanitize_key($attributes['show_what'] ?? 'both'),
			'sort_by' => sanitize_key($attributes['sort_by'] ?? 'first_date'),
			'order' => sanitize_text_field($attributes['order'] ?? 'ASC'),
			'list_class' => sanitize_text_field($attributes['list_class'] ?? '')
		);

		ob_start();
		$widget->widget($widget_args, $instance);
		return ob_get_clean();
	}

	/**
	 * Render validation form block
	 * 
	 * Renders the validation form. For block editor preview and REST requests,
	 * returns a simple form. For frontend rendering, uses the display helper
	 * class to process the validation form.
	 * 
	 * @since 6.0.0
	 * @param array $attributes Block attributes
	 * @return string HTML output
	 */
	public static function render_validation_form_block($attributes) {
		// For block editor preview and REST requests
		if ((defined('REST_REQUEST') && REST_REQUEST) || is_admin()) {
			return pta_get_validation_form();
		}

		// For frontend rendering, initialize display functions if needed
		global $pta_sus;
		if (isset($pta_sus, $pta_sus->public, $pta_sus->public->volunteer)) {
			PTA_SUS_Public_Display_Functions::initialize(array(), $pta_sus->public->volunteer);
			PTA_SUS_Public_Display_Functions::set_validation_sent($pta_sus->public->validation_sent);
		} else {
			PTA_SUS_Public_Display_Functions::initialize();
		}
		
		return PTA_SUS_Public_Display_Functions::process_validation_form_shortcode($attributes);
	}
}

