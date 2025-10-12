<?php
/**
 * Task Object Class
 * 
 * Represents a task/item within a volunteer sign-up sheet
 * 
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Task extends PTA_SUS_Base_Object {
	
	/**
	 * Get property definitions
	 * Defines all task properties and their types for sanitization
	 *
	 * @return array Associative array of property_name => type
	 */
	protected function get_property_definitions() {
		return array(
			'sheet_id' => 'int',
			'title' => 'text',
			'description' => 'textarea',
			'dates' => 'dates',
			'time_start' => 'time',
			'time_end' => 'time',
			'qty' => 'int',
			'need_details' => 'yesno',
			'details_required' => 'yesno',
			'details_text' => 'text',
			'allow_duplicates' => 'yesno',
			'enable_quantities' => 'yesno',
			'position' => 'int',
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
			'sheet_id' => 0,
			'title' => '',
			'description' => '',
			'dates' => '',
			'time_start' => '',
			'time_end' => '',
			'qty' => 1,
			'need_details' => 'NO',
			'details_required' => 'YES',
			'details_text' => 'Item you are bringing',
			'allow_duplicates' => 'NO',
			'enable_quantities' => 'NO',
			'position' => 0,
		);
	}
	
	/**
	 * Get database table name (without prefix)
	 *
	 * @return string
	 */
	protected function get_table_name() {
		return 'pta_sus_tasks';
	}
	
	/**
	 * Get required fields
	 * Returns array of field_name => label for required fields
	 *
	 * @return array
	 */
	protected function get_required_fields() {
		/**
		 * Filter required task fields
		 *
		 * @param array $required Array of field_name => label
		 * @param PTA_SUS_Task $this The task instance
		 */
		return apply_filters( 'pta_sus_task_required_fields', array(), $this );
	}
	
	/**
	 * Get the parent sheet for this task
	 * Convenience method to get related sheet
	 *
	 * @return PTA_SUS_Sheet|false Sheet object or false if not found
	 */
    public function get_sheet() {
        if ( empty( $this->sheet_id ) ) {
            return false;
        }

        return pta_sus_get_sheet( $this->sheet_id );
    }

    /**
     * Get all signups for this task
     * Convenience method to get related signups
     *
     * @param string|null $date Optional specific date to get signups for
     * @return array Array of PTA_SUS_Signup objects
     */
    public function get_signups($date = null) {
        if (empty($this->id)) {
            return array();
        }

        return PTA_SUS_Signup_Functions::get_signups_for_task($this->id, $date);
    }
	
	/**
	 * Get dates as array
	 * Converts the comma-separated dates string to an array
	 *
	 * @return array Array of date strings
	 */
	public function get_dates_array() {
		if ( empty( $this->dates ) ) {
			return array();
		}
		
		return array_map( 'trim', explode( ',', $this->dates ) );
	}
	
	/**
	 * Get available spots for a specific date
	 *
	 * @param string $date Date to check
	 * @return int Number of available spots
	 */
	public function get_available_spots( $date ) {
		$signups = $this->get_signups( $date );
		$filled = 0;
		
		// Count filled spots, considering quantities if enabled
		if ( $this->enable_quantities === 'YES' ) {
			foreach ( $signups as $signup ) {
				$filled += isset( $signup->item_qty ) ? absint( $signup->item_qty ) : 1;
			}
		} else {
			$filled = count( $signups );
		}
		
		return max( 0, $this->qty - $filled );
	}
	
	/**
	 * Check if task has available spots for a date
	 *
	 * @param string $date Date to check
	 * @return bool
	 */
	public function has_available_spots( $date ) {
		return $this->get_available_spots( $date ) > 0;
	}
	
	/**
	 * Get object type (for filters/hooks)
	 *
	 * @return string
	 */
	protected function get_object_type() {
		return 'task';
	}
}

