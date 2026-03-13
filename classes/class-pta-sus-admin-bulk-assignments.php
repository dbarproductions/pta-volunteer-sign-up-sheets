<?php
/**
 * PTA SUS Admin Bulk Assignments
 *
 * Handles the Bulk Assignments admin page: provider registry, form processing,
 * and page rendering. Extensions register their assignment types via the
 * 'pta_sus_bulk_assignment_providers' filter.
 *
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since   6.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PTA_SUS_Admin_Bulk_Assignments
 *
 * Manages the Bulk Assignments admin page. Providers are registered by the
 * core plugin (email template types) and optionally by extensions (signup
 * templates, locations, waitlists, layout templates, etc.).
 */
class PTA_SUS_Admin_Bulk_Assignments {

	/**
	 * Registered providers, sorted by priority after first access.
	 *
	 * @var array[]|null
	 */
	private $providers = null;

	/**
	 * Result data from the most recent form submission, used by the view.
	 *
	 * @var array|null
	 */
	private $last_result = null;

	// -------------------------------------------------------------------------
	// Provider registry
	// -------------------------------------------------------------------------

	/**
	 * Return all registered providers, sorted by priority ascending.
	 *
	 * Providers are loaded once and cached. Each provider is an array:
	 *   id                        string   Unique slug.
	 *   label                     string   Display label for the type dropdown.
	 *   section_label             string   Grouping label (e.g. 'Core Plugin — Email').
	 *   target                    string   'sheet', 'task', or 'both'.
	 *   priority                  int      Sort order (lower = first).
	 *   choices_callback          callable Returns array(0=>'Use System Default', id=>label, ...).
	 *   get_current_callback      callable Signature: fn(int $sheet_id): int
	 *   save_callback             callable Signature: fn(int[] $ids, int $value): array{saved,errors}
	 *   get_task_current_callback callable|null  Optional — task-level read.
	 *   save_task_callback        callable|null  Optional — task-level write.
	 *
	 * @return array[]
	 */
	public function get_providers(): array {
		if ( null !== $this->providers ) {
			return $this->providers;
		}

		// Start with the core plugin's built-in email-template providers.
		$defaults = $this->build_core_providers();

		/**
		 * Filter: pta_sus_bulk_assignment_providers
		 *
		 * Extensions use this filter to register their own assignment types.
		 * Hook at priority >= 20 so core providers (priority 5) load first.
		 *
		 * @param array[] $providers Indexed array of provider definition arrays.
		 */
		$providers = apply_filters( 'pta_sus_bulk_assignment_providers', $defaults );

		// Sort by priority.
		usort( $providers, function( $a, $b ) {
			return ( $a['priority'] ?? 10 ) <=> ( $b['priority'] ?? 10 );
		} );

		$this->providers = $providers;
		return $this->providers;
	}

	/**
	 * Build core-plugin provider definitions for the five email template types.
	 *
	 * @return array[]
	 */
	private function build_core_providers(): array {
		$email_types = array(
			'confirmation' => array(
				'label'    => __( 'Confirmation Email Template', 'pta-volunteer-sign-up-sheets' ),
				'field'    => 'confirmation_email_template_id',
				'priority' => 10,
			),
			'reminder1'    => array(
				'label'    => __( 'Reminder 1 Email Template', 'pta-volunteer-sign-up-sheets' ),
				'field'    => 'reminder1_email_template_id',
				'priority' => 20,
			),
			'reminder2'    => array(
				'label'    => __( 'Reminder 2 Email Template', 'pta-volunteer-sign-up-sheets' ),
				'field'    => 'reminder2_email_template_id',
				'priority' => 30,
			),
			'clear'        => array(
				'label'    => __( 'Clear Email Template', 'pta-volunteer-sign-up-sheets' ),
				'field'    => 'clear_email_template_id',
				'priority' => 40,
			),
			'reschedule'   => array(
				'label'    => __( 'Reschedule Email Template', 'pta-volunteer-sign-up-sheets' ),
				'field'    => 'reschedule_email_template_id',
				'priority' => 50,
			),
		);

		$providers = array();

		foreach ( $email_types as $type => $config ) {
			$field = $config['field'];

			$providers[] = array(
				'id'            => 'email_' . $type,
				'label'         => $config['label'],
				'section_label' => __( 'Core Plugin — Email', 'pta-volunteer-sign-up-sheets' ),
				'target'        => 'both',
				'priority'      => $config['priority'],

				'choices_callback'     => array( __CLASS__, 'get_email_template_choices' ),

				'get_current_callback' => function( int $sheet_id ) use ( $field ) {
					$sheet = pta_sus_get_sheet( $sheet_id );
					return $sheet ? (int) $sheet->$field : 0;
				},

				'save_callback'        => function( array $sheet_ids, int $value ) use ( $field ) {
					return PTA_SUS_Bulk_Assignments_Helper::update_sheet_field( $sheet_ids, $field, $value );
				},

				'get_task_current_callback' => function( int $task_id ) use ( $field ) {
					$task = pta_sus_get_task( $task_id );
					return $task ? (int) $task->$field : 0;
				},

				'save_task_callback' => function( array $task_ids, int $value ) use ( $field ) {
					return PTA_SUS_Bulk_Assignments_Helper::update_task_field( $task_ids, $field, $value );
				},
			);
		}

		return $providers;
	}

	/**
	 * Return an array of email template choices for use in a provider dropdown.
	 *
	 * The zero entry uses the label "Use System Default" matching the sheet edit form.
	 *
	 * @return array int => string  (0 => 'Use System Default', id => title, ...)
	 */
	public static function get_email_template_choices(): array {
		$choices = array( 0 => __( 'Use System Default', 'pta-volunteer-sign-up-sheets' ) );
		$templates = PTA_SUS_Email_Functions::get_available_templates( true );
		foreach ( $templates as $tpl ) {
			$choices[ (int) $tpl->id ] = $tpl->title;
		}
		return $choices;
	}

	/**
	 * Find and return a single provider by ID.
	 *
	 * @param string $provider_id Provider slug.
	 * @return array|null Provider array or null if not found.
	 */
	public function get_provider( string $provider_id ): ?array {
		foreach ( $this->get_providers() as $provider ) {
			if ( $provider['id'] === $provider_id ) {
				return $provider;
			}
		}
		return null;
	}

	// -------------------------------------------------------------------------
	// Value-type helpers
	// -------------------------------------------------------------------------

	/**
	 * Return true if the provider uses string values instead of integer IDs.
	 *
	 * Providers set 'value_type' => 'string' in their definition to opt in.
	 * All built-in core providers and most extensions use the default 'int' type.
	 *
	 * @param array|null $provider Provider definition array, or null.
	 * @return bool
	 */
	private function is_string_value_type( $provider ): bool {
		return is_array( $provider ) && ( ( $provider['value_type'] ?? 'int' ) === 'string' );
	}

	/**
	 * Return true if the provider uses an array of string values (multi-select).
	 *
	 * Providers set 'value_type' => 'array' in their definition to opt in.
	 * Used by the Groups extension which assigns a set of group slugs per sheet.
	 *
	 * @param array|null $provider Provider definition array, or null.
	 * @return bool
	 */
	private function is_array_value_type( $provider ): bool {
		return is_array( $provider ) && ( ( $provider['value_type'] ?? 'int' ) === 'array' );
	}

	/**
	 * Sanitize a submitted value according to the provider's value_type.
	 *
	 * @param mixed      $raw      Raw submitted value from $_POST (string or array).
	 * @param array|null $provider Provider definition array, or null/empty array.
	 * @return int|string|array    Sanitized integer, slug string, or array of slug strings.
	 */
	private function sanitize_provider_value( $raw, $provider ) {
		if ( $this->is_array_value_type( $provider ) ) {
			$raw = is_array( $raw ) ? $raw : array();
			return array_values( array_map( 'sanitize_key', $raw ) );
		}
		if ( $this->is_string_value_type( $provider ) ) {
			return sanitize_key( (string) $raw );
		}
		return absint( $raw );
	}

	// -------------------------------------------------------------------------
	// Form processing (called from admin_init before any output)
	// -------------------------------------------------------------------------

	/**
	 * Process a submitted bulk-assignment form.
	 *
	 * Validates the nonce and capability, resolves the provider, sanitizes
	 * inputs, calls the provider's save_callback, and queues result messages.
	 *
	 * @return void
	 */
	public function process_form(): void {
		if ( ! current_user_can( 'manage_signup_sheets' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pta-volunteer-sign-up-sheets' ) );
		}

		if ( ! check_admin_referer( 'pta_sus_bulk_assign', 'pta_sus_bulk_assign_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pta-volunteer-sign-up-sheets' ) );
		}

		$provider_id = isset( $_POST['bulk_assign_provider'] ) ? sanitize_key( $_POST['bulk_assign_provider'] ) : '';
		$provider    = $this->get_provider( $provider_id );

		if ( ! $provider ) {
			PTA_SUS_Messages::add_error( __( 'Invalid assignment type selected.', 'pta-volunteer-sign-up-sheets' ) );
			return;
		}

		// For array-type providers, bulk_assign_value is submitted as an array (multi-select).
		if ( $this->is_array_value_type( $provider ) ) {
			$raw_value = isset( $_POST['bulk_assign_value'] ) && is_array( $_POST['bulk_assign_value'] )
				? $_POST['bulk_assign_value']
				: array();
		} else {
			$raw_value = $_POST['bulk_assign_value'] ?? '';
		}
		$value     = $this->sanitize_provider_value( $raw_value, $provider );
		$sheet_ids = isset( $_POST['bulk_assign_sheets'] ) && is_array( $_POST['bulk_assign_sheets'] )
			? array_map( 'absint', $_POST['bulk_assign_sheets'] )
			: array();

		// Remove any zero values that slipped through.
		$sheet_ids = array_filter( $sheet_ids );

		if ( empty( $sheet_ids ) ) {
			PTA_SUS_Messages::add_error( __( 'No sheets were selected.', 'pta-volunteer-sign-up-sheets' ) );
			return;
		}

		if ( ! is_callable( $provider['save_callback'] ) ) {
			PTA_SUS_Messages::add_error( __( 'This assignment type does not support saving.', 'pta-volunteer-sign-up-sheets' ) );
			return;
		}

		$result = call_user_func( $provider['save_callback'], $sheet_ids, $value );

		$saved  = isset( $result['saved'] ) ? (int) $result['saved'] : 0;
		$errors = isset( $result['errors'] ) ? (array) $result['errors'] : array();

		if ( $saved > 0 ) {
			PTA_SUS_Messages::add_message(
				sprintf(
					/* translators: 1: count of sheets updated, 2: provider label */
					_n(
						'%1$s sheet updated for "%2$s".',
						'%1$s sheets updated for "%2$s".',
						$saved,
						'pta-volunteer-sign-up-sheets'
					),
					number_format_i18n( $saved ),
					esc_html( $provider['label'] )
				)
			);
		}

		foreach ( $errors as $error ) {
			PTA_SUS_Messages::add_error( $error );
		}

		if ( 0 === $saved && empty( $errors ) ) {
			PTA_SUS_Messages::add_message( __( 'No changes were made.', 'pta-volunteer-sign-up-sheets' ) );
		}
	}

	// -------------------------------------------------------------------------
	// Page rendering
	// -------------------------------------------------------------------------

	/**
	 * Render the Bulk Assignments admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_signup_sheets' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'pta-volunteer-sign-up-sheets' ) );
		}

		$providers = $this->get_providers();

		// Sheet-tab providers: only those with target 'sheet' or 'both'.
		$sheet_providers = array_values( array_filter( $providers, function( $p ) {
			return in_array( $p['target'] ?? 'sheet', array( 'sheet', 'both' ), true );
		} ) );

		// Active provider: persist across submission via GET param or fall back to first available.
		$active_provider_id = isset( $_GET['provider'] )
			? sanitize_key( $_GET['provider'] )
			: ( isset( $_POST['bulk_assign_provider'] ) ? sanitize_key( $_POST['bulk_assign_provider'] ) : '' );

		$active_provider = $this->get_provider( $active_provider_id );
		// Ensure active_provider is valid for the sheet tab (must be sheet or both).
		if ( $active_provider && ! in_array( $active_provider['target'] ?? 'sheet', array( 'sheet', 'both' ), true ) ) {
			$active_provider = null;
		}
		if ( ! $active_provider && ! empty( $sheet_providers ) ) {
			$active_provider = $sheet_providers[0];
		}

		// Choices and current-assignments data for the active provider.
		$choices            = array();
		$current_table      = array();
		$is_array_value_type = $this->is_array_value_type( $active_provider );

		// Determine the default and raw POST value based on value_type.
		if ( $is_array_value_type ) {
			$raw_selected = isset( $_POST['bulk_assign_value'] ) && is_array( $_POST['bulk_assign_value'] )
				? $_POST['bulk_assign_value']
				: array();
			$selected_value = isset( $_POST['bulk_assign_value'] )
				? $this->sanitize_provider_value( $raw_selected, $active_provider )
				: array();
		} else {
			$selected_value = isset( $_POST['bulk_assign_value'] )
				? $this->sanitize_provider_value( $_POST['bulk_assign_value'], $active_provider )
				: ( $this->is_string_value_type( $active_provider ) ? '' : 0 );
		}

		$selected_sheets = isset( $_POST['bulk_assign_sheets'] ) && is_array( $_POST['bulk_assign_sheets'] )
			? array_map( 'absint', $_POST['bulk_assign_sheets'] )
			: array();

		if ( $active_provider && is_callable( $active_provider['choices_callback'] ) ) {
			$choices = call_user_func( $active_provider['choices_callback'] );
		}

		// All non-trashed, active (non-expired) sheets, including hidden ones.
		$sheets = PTA_SUS_Sheet_Functions::get_sheets( false, true, true, 'title', 'ASC' );

		// Build current-assignments table.
		if ( $active_provider && is_callable( $active_provider['get_current_callback'] ) ) {
			foreach ( $sheets as $sheet ) {
				$current_value_id = call_user_func( $active_provider['get_current_callback'], (int) $sheet->id );

				// For array-type providers, build label by joining each slug's display name.
				if ( $is_array_value_type && is_array( $current_value_id ) ) {
					$labels = array();
					foreach ( $current_value_id as $vid ) {
						$labels[] = isset( $choices[ $vid ] ) ? $choices[ $vid ] : $vid;
					}
					$value_label = ! empty( $labels ) ? implode( ', ', $labels ) : __( '— None —', 'pta-volunteer-sign-up-sheets' );
				} else {
					$value_label = isset( $choices[ $current_value_id ] ) ? $choices[ $current_value_id ] : __( '— Unknown —', 'pta-volunteer-sign-up-sheets' );
				}

				$current_table[] = array(
					'sheet'       => $sheet,
					'value_id'    => $current_value_id,
					'value_label' => $value_label,
				);
			}
		}

		// â”€â”€ Task tab data â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
		$active_tab = ( isset( $_GET['tab'] ) && 'tasks' === $_GET['tab'] ) ? 'tasks' : 'sheets';

		// Providers that support task-level assignments.
		$task_providers = array_values( array_filter( $providers, function( $p ) {
			return in_array( $p['target'] ?? 'sheet', array( 'both', 'task' ), true );
		} ) );

		// Group task providers for the task-tab type dropdown.
		$task_grouped_providers = array();
		foreach ( $task_providers as $p ) {
			$section = $p['section_label'] ?? __( 'Other', 'pta-volunteer-sign-up-sheets' );
			$task_grouped_providers[ $section ][] = $p;
		}

		// Active task-tab provider (URL param 'tprovider', independent of sheet-tab).
		$task_active_provider_id = isset( $_GET['tprovider'] ) ? sanitize_key( $_GET['tprovider'] ) : '';
		$task_active_provider    = null;
		foreach ( $task_providers as $p ) {
			if ( $p['id'] === $task_active_provider_id ) {
				$task_active_provider = $p;
				break;
			}
		}
		if ( ! $task_active_provider && ! empty( $task_providers ) ) {
			$task_active_provider    = $task_providers[0];
			$task_active_provider_id = $task_active_provider['id'];
		}

		$task_choices = array();
		if ( $task_active_provider && is_callable( $task_active_provider['choices_callback'] ) ) {
			$task_choices = call_user_func( $task_active_provider['choices_callback'] );
		}
		$task_selected_value = 0;

		include dirname( __FILE__ ) . '/admin-bulk-assignments-html.php';
	}

	// -------------------------------------------------------------------------
	// AJAX: load tasks for task tab
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler: return task table HTML for selected sheets + provider.
	 *
	 * POST params: provider_id, sheet_ids[], nonce
	 *
	 * @return void
	 */
	public function ajax_get_tasks(): void {
		check_ajax_referer( 'pta_sus_bulk_assign', 'nonce' );

		if ( ! current_user_can( 'manage_signup_sheets' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pta-volunteer-sign-up-sheets' ) ) );
		}

		$provider_id = isset( $_POST['provider_id'] ) ? sanitize_key( $_POST['provider_id'] ) : '';
		$provider    = $this->get_provider( $provider_id );

		if ( ! $provider || ! in_array( $provider['target'] ?? 'sheet', array( 'both', 'task' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid assignment type.', 'pta-volunteer-sign-up-sheets' ) ) );
		}

		if ( ! is_callable( $provider['get_task_current_callback'] ?? null ) ) {
			wp_send_json_error( array( 'message' => __( 'This assignment type does not support task-level assignments.', 'pta-volunteer-sign-up-sheets' ) ) );
		}

		$sheet_ids = isset( $_POST['sheet_ids'] ) && is_array( $_POST['sheet_ids'] )
			? array_filter( array_map( 'absint', $_POST['sheet_ids'] ) )
			: array();

		if ( empty( $sheet_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No sheets selected.', 'pta-volunteer-sign-up-sheets' ) ) );
		}

		$choices = is_callable( $provider['choices_callback'] ) ? call_user_func( $provider['choices_callback'] ) : array();

		// Build task rows grouped by sheet.
		$rows = array();
		foreach ( $sheet_ids as $sheet_id ) {
			$sheet = pta_sus_get_sheet( $sheet_id );
			if ( ! $sheet ) {
				continue;
			}
			$tasks = PTA_SUS_Task_Functions::get_tasks( $sheet_id );
			foreach ( $tasks as $task ) {
				$current_id  = (int) call_user_func( $provider['get_task_current_callback'], (int) $task->id );
				$value_label = isset( $choices[ $current_id ] ) ? $choices[ $current_id ] : __( '&mdash; None &mdash;', 'pta-volunteer-sign-up-sheets' );
				$rows[] = array(
					'sheet'       => $sheet,
					'task'        => $task,
					'current_id'  => $current_id,
					'value_label' => $value_label,
				);
			}
		}

		if ( empty( $rows ) ) {
			wp_send_json_error( array( 'message' => __( 'No tasks found for the selected sheets.', 'pta-volunteer-sign-up-sheets' ) ) );
		}

		$html  = $this->render_task_table_html( $rows );
		$count = count( $rows );

		wp_send_json_success( array( 'html' => $html, 'count' => $count ) );
	}

	// -------------------------------------------------------------------------
	// AJAX: save task-level assignments
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler: save task-level bulk assignment.
	 *
	 * POST params: provider_id, task_ids[], value, nonce
	 *
	 * @return void
	 */
	public function ajax_save_tasks(): void {
		check_ajax_referer( 'pta_sus_bulk_assign', 'nonce' );

		if ( ! current_user_can( 'manage_signup_sheets' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pta-volunteer-sign-up-sheets' ) ) );
		}

		$provider_id = isset( $_POST['provider_id'] ) ? sanitize_key( $_POST['provider_id'] ) : '';
		$provider    = $this->get_provider( $provider_id );

		if ( ! $provider || ! in_array( $provider['target'] ?? 'sheet', array( 'both', 'task' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid assignment type.', 'pta-volunteer-sign-up-sheets' ) ) );
		}

		if ( ! is_callable( $provider['save_task_callback'] ?? null ) ) {
			wp_send_json_error( array( 'message' => __( 'This assignment type does not support task-level saving.', 'pta-volunteer-sign-up-sheets' ) ) );
		}

		$raw_value = $_POST['value'] ?? '';
		$value     = $this->sanitize_provider_value( $raw_value, $provider );

		$task_ids = isset( $_POST['task_ids'] ) && is_array( $_POST['task_ids'] )
			? array_filter( array_map( 'absint', $_POST['task_ids'] ) )
			: array();

		if ( empty( $task_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No tasks were selected.', 'pta-volunteer-sign-up-sheets' ) ) );
		}

		$result = call_user_func( $provider['save_task_callback'], array_values( $task_ids ), $value );

		$saved  = isset( $result['saved'] ) ? (int) $result['saved'] : 0;
		$errors = isset( $result['errors'] ) ? (array) $result['errors'] : array();

		if ( ! empty( $errors ) && 0 === $saved ) {
			wp_send_json_error( array( 'message' => implode( ' ', $errors ), 'saved' => 0 ) );
		}

		if ( 0 === $saved ) {
			wp_send_json_success( array( 'message' => __( 'No changes were made.', 'pta-volunteer-sign-up-sheets' ), 'saved' => 0 ) );
		}

		$message = sprintf(
			/* translators: 1: count of tasks updated, 2: provider label */
			_n(
				'%1$s task updated for &#8220;%2$s&#8221;.',
				'%1$s tasks updated for &#8220;%2$s&#8221;.',
				$saved,
				'pta-volunteer-sign-up-sheets'
			),
			number_format_i18n( $saved ),
			esc_html( $provider['label'] )
		);

		$response = array( 'message' => $message, 'saved' => $saved );
		if ( ! empty( $errors ) ) {
			$response['warnings'] = implode( ' ', $errors );
		}
		wp_send_json_success( $response );
	}

	// -------------------------------------------------------------------------
	// Task table HTML helper (used by ajax_get_tasks)
	// -------------------------------------------------------------------------

	/**
	 * Render the task table HTML for the task-tab AJAX response.
	 *
	 * @param array[] $rows  Each row: ['sheet'=>PTA_SUS_Sheet, 'task'=>PTA_SUS_Task, 'current_id'=>int, 'value_label'=>string]
	 * @return string
	 */
	private function render_task_table_html( array $rows ): string {
		$date_format = get_option( 'date_format' );
		ob_start();
		?>
		<table class="widefat striped" id="pta-task-assign-table" style="max-width:900px;">
			<thead>
				<tr>
					<th style="width:30px;"><input type="checkbox" id="pta-task-check-all" title="<?php esc_attr_e( 'Select / deselect all', 'pta-volunteer-sign-up-sheets' ); ?>" /></th>
					<th><?php esc_html_e( 'Sheet Title', 'pta-volunteer-sign-up-sheets' ); ?></th>
					<th><?php esc_html_e( 'Task Title', 'pta-volunteer-sign-up-sheets' ); ?></th>
					<th><?php esc_html_e( 'First Sheet Date', 'pta-volunteer-sign-up-sheets' ); ?></th>
					<th><?php esc_html_e( 'Current Assignment', 'pta-volunteer-sign-up-sheets' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) :
					$sheet       = $row['sheet'];
					$task        = $row['task'];
					$current_id  = $row['current_id'];
					$value_label = $row['value_label'];
					$is_default  = ( 0 === $current_id );
				?>
				<tr>
					<td><input type="checkbox" class="pta-task-checkbox" value="<?php echo absint( $task->id ); ?>" /></td>
					<td><?php echo esc_html( $sheet->title ); ?></td>
					<td><?php echo esc_html( $task->title ); ?></td>
					<td><?php
					if ( $sheet->first_date && '0000-00-00' !== $sheet->first_date ) {
						echo esc_html( pta_datetime( $date_format, strtotime( $sheet->first_date ) ) );
					} elseif ( '0000-00-00' === $sheet->first_date ) {
						esc_html_e( 'Ongoing', 'pta-volunteer-sign-up-sheets' );
					} else {
						echo '&#8212;';
					}
				?></td>
					<td<?php echo $is_default ? ' style="color:#888;"' : ''; ?>><?php echo esc_html( $value_label ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}
}
