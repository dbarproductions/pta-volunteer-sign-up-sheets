<?php
/**
 * PTA SUS Bulk Assignments Helper
 *
 * Static helper class for batch-updating core plugin sheet/task columns.
 * Extensions that store assignment data in their own tables implement their
 * own save_callback functions and do NOT use this class.
 *
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since   6.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PTA_SUS_Bulk_Assignments_Helper
 *
 * Provides batch DB update methods for core sheet and task columns used by
 * the Bulk Assignments admin page. Only columns in the explicit whitelist
 * may be updated to prevent arbitrary column injection.
 */
class PTA_SUS_Bulk_Assignments_Helper {

	/**
	 * Sheet columns that may be updated via bulk assignment.
	 *
	 * @var string[]
	 */
	private static $allowed_sheet_fields = array(
		'confirmation_email_template_id',
		'reminder1_email_template_id',
		'reminder2_email_template_id',
		'clear_email_template_id',
		'reschedule_email_template_id',
	);

	/**
	 * Task columns that may be updated via bulk assignment.
	 *
	 * @var string[]
	 */
	private static $allowed_task_fields = array(
		'confirmation_email_template_id',
		'reminder1_email_template_id',
		'reminder2_email_template_id',
		'clear_email_template_id',
		'reschedule_email_template_id',
	);

	/**
	 * Batch-update a single whitelisted column on multiple sheets.
	 *
	 * Loads each sheet via the model (uses internal cache), sets the property,
	 * and calls save() so that pta_sus_updated_sheet fires for each sheet,
	 * keeping extension caches in sync.
	 *
	 * @param int[]  $sheet_ids Array of sheet IDs (already absint-sanitized).
	 * @param string $field     Column name — must be in $allowed_sheet_fields.
	 * @param int    $value     New value; 0 clears the assignment (use system default).
	 * @return array{saved: int, errors: string[]}
	 */
	public static function update_sheet_field( array $sheet_ids, string $field, int $value ): array {
		$result = array( 'saved' => 0, 'errors' => array() );

		if ( ! in_array( $field, self::$allowed_sheet_fields, true ) ) {
			$result['errors'][] = sprintf(
				/* translators: %s: field name */
				__( 'Field "%s" is not permitted for bulk update.', 'pta-volunteer-sign-up-sheets' ),
				esc_html( $field )
			);
			return $result;
		}

		if ( empty( $sheet_ids ) ) {
			return $result;
		}

		foreach ( $sheet_ids as $sheet_id ) {
			$sheet = pta_sus_get_sheet( absint( $sheet_id ) );
			if ( ! $sheet || ! $sheet->id ) {
				$result['errors'][] = sprintf(
					/* translators: %d: sheet ID */
					__( 'Sheet ID %d not found.', 'pta-volunteer-sign-up-sheets' ),
					absint( $sheet_id )
				);
				continue;
			}
			$sheet->$field = $value;
			$saved = $sheet->save();
			if ( $saved ) {
				$result['saved']++;
			} else {
				$result['errors'][] = sprintf(
					/* translators: %d: sheet ID */
					__( 'Failed to save sheet ID %d.', 'pta-volunteer-sign-up-sheets' ),
					absint( $sheet_id )
				);
			}
		}

		return $result;
	}

	/**
	 * Batch-update a single whitelisted column on multiple tasks.
	 *
	 * @param int[]  $task_ids Array of task IDs (already absint-sanitized).
	 * @param string $field    Column name — must be in $allowed_task_fields.
	 * @param int    $value    New value; 0 clears the assignment.
	 * @return array{saved: int, errors: string[]}
	 */
	public static function update_task_field( array $task_ids, string $field, int $value ): array {
		$result = array( 'saved' => 0, 'errors' => array() );

		if ( ! in_array( $field, self::$allowed_task_fields, true ) ) {
			$result['errors'][] = sprintf(
				/* translators: %s: field name */
				__( 'Field "%s" is not permitted for bulk update.', 'pta-volunteer-sign-up-sheets' ),
				esc_html( $field )
			);
			return $result;
		}

		if ( empty( $task_ids ) ) {
			return $result;
		}

		foreach ( $task_ids as $task_id ) {
			$task = pta_sus_get_task( absint( $task_id ) );
			if ( ! $task || ! $task->id ) {
				$result['errors'][] = sprintf(
					/* translators: %d: task ID */
					__( 'Task ID %d not found.', 'pta-volunteer-sign-up-sheets' ),
					absint( $task_id )
				);
				continue;
			}
			$task->$field = $value;
			$saved = $task->save();
			if ( $saved ) {
				$result['saved']++;
			} else {
				$result['errors'][] = sprintf(
					/* translators: %d: task ID */
					__( 'Failed to save task ID %d.', 'pta-volunteer-sign-up-sheets' ),
					absint( $task_id )
				);
			}
		}

		return $result;
	}

	/**
	 * Return the list of allowed sheet field names (for use in provider registration).
	 *
	 * @return string[]
	 */
	public static function get_allowed_sheet_fields(): array {
		return self::$allowed_sheet_fields;
	}

	/**
	 * Return the list of allowed task field names (for use in provider registration).
	 *
	 * @return string[]
	 */
	public static function get_allowed_task_fields(): array {
		return self::$allowed_task_fields;
	}
}
