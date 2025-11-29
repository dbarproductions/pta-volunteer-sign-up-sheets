<?php
/**
 * Sheet Object Class
 * 
 * Represents a volunteer sign-up sheet/event
 * 
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Sheet extends PTA_SUS_Base_Object {
	
	/**
	 * Get property definitions
	 * Defines all sheet properties and their types for sanitization
	 *
	 * @return array Associative array of property_name => type
	 */
	protected function get_property_definitions() {
		return array(
			'title' => 'text',
			'details' => 'textarea',
			'first_date' => 'date',
			'last_date' => 'date',
			'type' => 'text',
			'position' => 'text',
			'chair_name' => 'names',
			'chair_email' => 'emails',
			'sus_group' => 'array',
			'reminder1_days' => 'int',
			'reminder2_days' => 'int',
			'clear' => 'bool',
			'clear_type' => 'text',
			'clear_days' => 'int',
			'no_signups' => 'bool',
			'duplicate_times' => 'bool',
			'visible' => 'bool',
			'trash' => 'bool',
			'clear_emails' => 'text',
			'signup_emails' => 'text',
			'author_id' => 'int',
			'author_email' => 'email',
			'confirmation_email_template_id' => 'int',
			'reminder1_email_template_id' => 'int',
			'reminder2_email_template_id' => 'int',
			'clear_email_template_id' => 'int',
			'reschedule_email_template_id' => 'int',
			'signup_validation_email_template_id' => 'int',
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
			'details' => '',
			'first_date' => null,
			'last_date' => null,
			'type' => '',
			'position' => '',
			'chair_name' => '',
			'chair_email' => '',
			'sus_group' => 'none',
			'reminder1_days' => null,
			'reminder2_days' => null,
			'clear' => true,
			'clear_type' => 'days',
			'clear_days' => 0,
			'no_signups' => false,
			'duplicate_times' => false,
			'visible' => true,
			'trash' => false,
			'clear_emails' => 'default',
			'signup_emails' => 'default',
			'author_id' => 0,
			'author_email' => '',
			'confirmation_email_template_id' => 0,
			'reminder1_email_template_id' => 0,
			'reminder2_email_template_id' => 0,
			'clear_email_template_id' => 0,
			'reschedule_email_template_id' => 0,
			'signup_validation_email_template_id' => 0,
		);
	}
	
	/**
	 * Get database table name (without prefix)
	 *
	 * @return string
	 */
	protected function get_table_name() {
		return 'pta_sus_sheets';
	}
	
	/**
	 * Get required fields
	 * Returns array of field_name => label for required fields
	 *
	 * @return array
	 */
	protected function get_required_fields() {
		/**
		 * Filter required sheet fields
		 *
		 * @param array $required Array of field_name => label
		 * @param PTA_SUS_Sheet $this The sheet instance
		 */
		return apply_filters( 'pta_sus_sheet_required_fields', array(
			'title' => 'Title',
			'type' => 'Event Type',
		), $this );
	}
	
	/**
	 * Get all tasks for this sheet
	 * Convenience method to get related tasks
	 *
	 * @return array Array of task objects
	 */
	public function get_tasks($date='') {
		if ( empty( $this->id ) ) {
			return array();
		}
		
		return PTA_SUS_Task_Functions::get_tasks( $this->id, $date );
	}
	
	/**
	 * Get signup count for this sheet
	 * Convenience method to get total signups
	 *
	 * @return int Number of signups
	 */
    public function get_signup_count($date = '') {
        if (empty($this->id)) {
            return 0;
        }
        return PTA_SUS_Sheet_Functions::get_sheet_signup_count($this->id, $date);
    }

    /**
     * Get all unique dates from all tasks for this sheet
     * Convenience method to get all dates across all tasks
     *
     * @return array Array of unique date strings
     */
    public function get_all_task_dates() {
        if (empty($this->id)) {
            return array();
        }

        return PTA_SUS_Sheet_Functions::get_all_task_dates_for_sheet($this->id);
    }

    /**
     * Get the total number of spots for this sheet
     *
     * @param string $date
     * @return array|int
     */
    public function get_total_spots($date = '') {
        if (empty($this->id)) {
            return array();
        }
        return PTA_SUS_Sheet_Functions::get_sheet_total_spots($this->id, $date);
    }
	
	/**
	 * Check if sheet is active (not expired)
	 *
	 * @return bool
	 */
	public function is_active() {
		if ( empty( $this->last_date ) || $this->last_date === '0000-00-00' ) {
			return true; // No date set means always active
		}
		
		$current_date = current_time( 'mysql' );
		$last_date = $this->last_date . ' 23:59:59'; // End of last day
		
		return ( $last_date >= $current_date );
	}
	
	/**
	 * Check if sheet is in trash
	 *
	 * @return bool
	 */
	public function is_trashed() {
		return (bool) $this->trash;
	}
	
	/**
	 * Check if sheet is visible to public
	 *
	 * @return bool
	 */
	public function is_visible() {
		return (bool) $this->visible;
	}

    /**
     * Toggle visibility of this sheet
     * Flips the visible property and saves to database
     *
     * @return bool|int False on failure, sheet ID on success
     */
    public function toggle_visibility() {
        if (empty($this->id)) {
            return false;
        }

        // Toggle the visible property
        $this->visible = !$this->visible;

        // Save the change
        return $this->save();
    }
	
	/**
	 * Move sheet to trash
	 *
	 * @return bool|int False on failure, ID on success
	 */
	public function trash() {
		$this->trash = true;
		return $this->save();
	}
	
	/**
	 * Restore sheet from trash
	 *
	 * @return bool|int False on failure, ID on success
	 */
	public function restore() {
		$this->trash = false;
		return $this->save();
	}
	
	/**
	 * Get object type (for filters/hooks)
	 *
	 * @return string
	 */
	protected function get_object_type() {
		return 'sheet';
	}
}

