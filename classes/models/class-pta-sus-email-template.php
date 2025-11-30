<?php
/**
 * Email Template Object Class
 * 
 * Represents an email template for volunteer sign-up sheets
 * Templates are generic and can be assigned to any email type
 * 
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Email_Template extends PTA_SUS_Base_Object {
	
	/**
	 * Get property definitions
	 * Defines all email template properties and their types for sanitization
	 *
	 * @return array Associative array of property_name => type
	 */
	protected function get_property_definitions() {
		return array(
			'title' => 'text',
			'subject' => 'text',
			'body' => 'textarea', // Single body field - format determined by global use_html setting
			'from_name' => 'text',
			'from_email' => 'email',
			'author_id' => 'int',
			'created_at' => 'datetime',
			'updated_at' => 'datetime',
		);
	}
	
	/**
	 * Get property default values
	 * Defines default values for properties (matching database defaults)
	 *
	 * @return array Associative array of property_name => default_value
	 */
	protected function get_property_defaults() {
		return array(
			'title' => '',
			'subject' => '',
			'body' => '',
			'from_name' => '',
			'from_email' => '',
			'author_id' => 0,
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);
	}
	
	/**
	 * Get database table name (without prefix)
	 *
	 * @return string
	 */
	protected function get_table_name() {
		return 'pta_sus_email_templates';
	}
	
	/**
	 * Get required fields
	 * Returns array of field_name => label for required fields
	 *
	 * @return array
	 */
	protected function get_required_fields() {
		/**
		 * Filter required email template fields
		 *
		 * @param array $required Array of field_name => label
		 * @param PTA_SUS_Email_Template $this The template instance
		 */
		return apply_filters( 'pta_sus_email_template_required_fields', array(
			'title' => __( 'Title', 'pta-volunteer-sign-up-sheets' ),
			'subject' => __( 'Subject', 'pta-volunteer-sign-up-sheets' ),
			'body' => __( 'Body', 'pta-volunteer-sign-up-sheets' ),
		), $this );
	}
	
	/**
	 * Get object type (for filters/hooks)
	 *
	 * @return string
	 */
	protected function get_object_type() {
		return 'email_template';
	}
	
	/**
	 * Override save to update updated_at timestamp
	 *
	 * @return bool|int False on failure, template ID on success
	 */
	public function save() {
		// Update updated_at timestamp
		$this->updated_at = current_time( 'mysql' );
		
		// If this is a new template, set created_at
		if ( $this->is_new ) {
			$this->created_at = current_time( 'mysql' );
		}
		
		return parent::save();
	}
	
	/**
	 * Get the From name for outgoing emails
	 * Returns template's from_name if set, otherwise returns default
	 *
	 * @param string $default Default from name to use if template doesn't have one
	 * @return string
	 */
	public function get_from_name( $default = '' ) {
		if ( ! empty( $this->from_name ) ) {
			return wp_specialchars_decode( esc_html( $this->from_name ), ENT_QUOTES );
		}
		return $default;
	}
	
	/**
	 * Get the From email for outgoing emails
	 * Returns template's from_email if set, otherwise returns default
	 *
	 * @param string $default Default from email to use if template doesn't have one
	 * @return string
	 */
	public function get_from_email( $default = '' ) {
		if ( ! empty( $this->from_email ) && is_email( $this->from_email ) ) {
			return sanitize_email( $this->from_email );
		}
		return $default;
	}
	
	/**
	 * Get content type for email headers
	 * Returns appropriate MIME type based on global use_html setting
	 *
	 * @return string MIME type (text/plain or text/html)
	 */
	public function get_content_type_header() {
		$email_options = get_option( 'pta_volunteer_sus_email_options', array() );
		$use_html = isset( $email_options['use_html'] ) && $email_options['use_html'];
		return $use_html ? 'text/html' : 'text/plain';
	}
	
	/**
	 * Format plain text from HTML body
	 * Converts HTML to plain text for plain content type emails
	 *
	 * @param string $text HTML text to convert
	 * @return string Plain text
	 */
	public function format_plain_text( $text ) {
		if ( empty( $text ) ) {
			return '';
		}
		
		// Strip HTML tags and decode entities
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		
		// Word wrap to 70 characters
		return wordwrap( $text, 70, "\n" );
	}
	
	/**
	 * Get formatted body content
	 * Returns body formatted according to global use_html setting
	 *
	 * @return string Formatted body content
	 */
	public function get_formatted_body() {
		$email_options = get_option( 'pta_volunteer_sus_email_options', array() );
		$use_html = isset( $email_options['use_html'] ) && $email_options['use_html'];
		if ( $use_html ) {
			return wp_kses_post( $this->body );
		} else {
			return $this->format_plain_text( $this->body );
		}
	}
	
	/**
	 * Replace template tags in subject and body
	 * Uses PTA_SUS_Template_Tags to replace placeholders
	 *
	 * @param PTA_SUS_Signup|object|array $signup Signup object or data
	 * @param PTA_SUS_Task|object|array $task Task object or data (optional)
	 * @param PTA_SUS_Sheet|object|array $sheet Sheet object or data (optional)
	 * @return array Array with 'subject' and 'body' keys containing replaced content
	 */
	public function replace_placeholders( $signup = null, $task = null, $sheet = null ) {
		// Use existing template tags system
		if ( class_exists( 'PTA_SUS_Template_Tags' ) ) {
			$tags = PTA_SUS_Template_Tags::register_default_tags( $signup, $task, $sheet );
		} else {
			$tags = array();
		}
		
		// Allow extensions to add custom tags
		$tags = apply_filters( 'pta_sus_email_template_tags', $tags, $signup, $task, $sheet, $this );
		
		// Replace tags in subject and body
		$subject = $this->subject;
		$body = $this->body;
		
		if ( ! empty( $tags ) ) {
			$find = array_keys( $tags );
			$replace = array_values( $tags );
			$subject = str_replace( $find, $replace, $subject );
			$body = str_replace( $find, $replace, $body );
		}
		
		// Format body according to global use_html setting
		$email_options = get_option( 'pta_volunteer_sus_email_options', array() );
		$use_html = isset( $email_options['use_html'] ) && $email_options['use_html'];
		if ( $use_html ) {
			$body = wp_kses_post( $body );
		} else {
			$body = $this->format_plain_text( $body );
		}
		
		return array(
			'subject' => $subject,
			'body' => $body,
		);
	}
	
	/**
	 * Check if template is a system default
	 * Uses the option array as the source of truth, not the database field
	 *
	 * @return bool
	 */
	public function is_system_default() {
		return PTA_SUS_Email_Functions::is_system_default_template($this->id);
	}
	
	/**
	 * Check if current user can edit this template
	 *
	 * @return bool
	 */
	public function can_edit() {
		// System defaults can only be edited by admins/managers
		if ( $this->is_system_default() ) {
			return current_user_can( 'manage_others_signup_sheets' );
		}
		
		// If user can manage others, they can edit any template
		if ( current_user_can( 'manage_others_signup_sheets' ) ) {
			return true;
		}
		
		// Authors can only edit their own templates
		// Use loose comparison to handle string/int type differences from database
		if ( $this->author_id > 0 && (int) $this->author_id === (int) get_current_user_id() ) {
			return true;
		}
		
		// Templates with author_id = 0 (available to all) can be edited by anyone with manage_signup_sheets
		if ( $this->author_id === 0 && current_user_can( 'manage_signup_sheets' ) ) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Check if current user can delete this template
	 *
	 * @return bool
	 */
	public function can_delete() {
		// System defaults cannot be deleted
		if ( $this->is_system_default() ) {
			return false;
		}
		
		// If user can manage others, they can delete any non-system template
		if ( current_user_can( 'manage_others_signup_sheets' ) ) {
			return true;
		}
		
		// Authors can only delete their own templates
		// Use loose comparison to handle string/int type differences from database
		if ( $this->author_id > 0 && (int) $this->author_id === (int) get_current_user_id() ) {
			return true;
		}
		
		// Templates with author_id = 0 (available to all) can be deleted by anyone with manage_signup_sheets
		if ( $this->author_id === 0 && current_user_can( 'manage_signup_sheets' ) ) {
			return true;
		}
		
		return false;
	}
}

