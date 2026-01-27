<?php
/**
 * Signup Object Class
 * 
 * Represents a volunteer signup for a specific task
 * 
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Signup extends PTA_SUS_Base_Object {
	
	/**
	 * Get property definitions
	 * Defines all signup properties and their types for sanitization
	 *
	 * @return array Associative array of property_name => type
	 */
	protected function get_property_definitions() {
		return array(
			'task_id' => 'int',
			'date' => 'date',
			'user_id' => 'int',
			'item' => 'text',
			'firstname' => 'text',
			'lastname' => 'text',
			'email' => 'email',
			'phone' => 'phone',
			'reminder1_sent' => 'bool',
			'reminder2_sent' => 'bool',
			'item_qty' => 'int',
			'ts' => 'int',
			'validated' => 'bool',
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
			'task_id' => 0,
			'date' => '',
			'user_id' => 0,
			'item' => '',
			'firstname' => '',
			'lastname' => '',
			'email' => '',
			'phone' => '',
			'reminder1_sent' => false,
			'reminder2_sent' => false,
			'item_qty' => 1,
			'ts' => null,
			'validated' => true,
		);
	}
	
	/**
	 * Get database table name (without prefix)
	 *
	 * @return string
	 */
	protected function get_table_name() {
		return 'pta_sus_signups';
	}
	
	/**
	 * Get required fields
	 * Returns array of field_name => label for required fields
	 *
	 * @return array
	 */
	protected function get_required_fields() {
		/**
		 * Filter required signup fields
		 *
		 * @param array $required Array of field_name => label
		 * @param PTA_SUS_Signup $this The signup instance
		 */
		return apply_filters( 'pta_sus_signup_required_fields', array(), $this );
	}
	
	/**
	 * Get the parent task for this signup
	 * Convenience method to get related task
	 *
	 * @return PTA_SUS_Task|false Task object or false if not found
	 */
	public function get_task() {
		if ( empty( $this->task_id ) ) {
			return false;
		}
		
		// Once Task class is fully integrated, use:
		// return PTA_SUS_Task::get_by_id( $this->task_id );
		
		// For now, use the old method if available
		global $pta_sus;
		if ( isset( $pta_sus->data ) && method_exists( $pta_sus->data, 'get_task' ) ) {
			return $pta_sus->data->get_task( $this->task_id );
		}
		
		return false;
	}
	
	/**
	 * Get the sheet for this signup (through the task)
	 * Convenience method to get related sheet
	 *
	 * @return object|false Sheet object or false if not found
	 */
	public function get_sheet() {
		$task = $this->get_task();
		if ( ! $task ) {
			return false;
		}
		
		// If task is a Task object, use its method
		if ( is_object( $task ) && method_exists( $task, 'get_sheet' ) ) {
			return $task->get_sheet();
		}
		
		// Otherwise, use old method
		if ( isset( $task->sheet_id ) ) {
			global $pta_sus;
			if ( isset( $pta_sus->data ) && method_exists( $pta_sus->data, 'get_sheet' ) ) {
				return $pta_sus->data->get_sheet( $task->sheet_id );
			}
		}
		
		return false;
	}
	
	/**
	 * Get volunteer's full name
	 *
	 * @return string
	 */
	public function get_full_name() {
		return trim( $this->firstname . ' ' . $this->lastname );
	}
	
	/**
	 * Check if this signup is validated
	 *
	 * @return bool
	 */
	public function is_validated() {
		return (bool) $this->validated;
	}
	
	/**
	 * Check if reminder 1 has been sent
	 *
	 * @return bool
	 */
	public function reminder1_sent() {
		return (bool) $this->reminder1_sent;
	}
	
	/**
	 * Check if reminder 2 has been sent
	 *
	 * @return bool
	 */
	public function reminder2_sent() {
		return (bool) $this->reminder2_sent;
	}
	
	/**
	 * Mark reminder 1 as sent
	 *
	 * @return bool|int False on failure, ID on success
	 */
	public function mark_reminder1_sent() {
		$this->reminder1_sent = true;
		return $this->save();
	}
	
	/**
	 * Mark reminder 2 as sent
	 *
	 * @return bool|int False on failure, ID on success
	 */
	public function mark_reminder2_sent() {
		$this->reminder2_sent = true;
		return $this->save();
	}
	
	/**
	 * Save override - set timestamp on first save
	 *
	 * @return int|false Object ID on success, false on failure
	 */
	public function save() {
		// Set timestamp on first save if not already set
		if ( $this->is_new && empty( $this->ts ) ) {
			$this->ts = current_time( 'timestamp' );
		}
		
		return parent::save();
	}
	
	/**
	 * Get object type (for filters/hooks)
	 *
	 * @return string
	 */
	protected function get_object_type() {
		return 'signup';
	}
}

