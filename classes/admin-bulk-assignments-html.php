<?php
/**
 * Bulk Assignments admin page template.
 *
 * Variables available from PTA_SUS_Admin_Bulk_Assignments::render_page():
 *   @var PTA_SUS_Admin_Bulk_Assignments $this
 *   @var array[]                        $providers              All registered providers (all targets).
 *   @var array[]                        $sheet_providers        Providers with target 'sheet' or 'both' (sheet tab only).
 *   @var array|null                     $active_provider        Currently selected provider for sheet tab (or null).
 *   @var string                         $active_provider_id
 *   @var array                          $choices                int => label for the active sheet-tab provider.
 *   @var PTA_SUS_Sheet[]                $sheets                 All eligible sheets.
 *   @var array[]                        $current_table          [ ['sheet'=>..., 'value_id'=>..., 'value_label'=>...] ]
 *   @var int                            $selected_value         Value ID from last sheet-tab submission.
 *   @var int[]                          $selected_sheets        Sheet IDs from last sheet-tab submission.
 *   @var string                         $active_tab             'sheets' or 'tasks'.
 *   @var array[]                        $task_providers         Providers supporting task-level assignments.
 *   @var array                          $task_grouped_providers Providers grouped by section_label for task tab.
 *   @var array|null                     $task_active_provider   Active task-tab provider.
 *   @var string                         $task_active_provider_id
 *   @var array                          $task_choices           Choices for the active task-tab provider.
 *   @var int                            $task_selected_value    Selected value for task tab (default 0).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Group sheet-tab providers (target='sheet' or 'both') by section_label for the optgroup dropdown.
$grouped_providers = array();
foreach ( $sheet_providers as $p ) {
	$section = $p['section_label'] ?? __( 'Other', 'pta-volunteer-sign-up-sheets' );
	$grouped_providers[ $section ][] = $p;
}

$page_slug = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : 'pta-sus-settings_bulk_assignments';
$base_url  = admin_url( 'admin.php?page=' . $page_slug );
?>
<div class="wrap pta_sus">
	<h1><?php esc_html_e( 'Bulk Assignments', 'pta-volunteer-sign-up-sheets' ); ?></h1>
	<p><?php esc_html_e( 'Use this page to quickly assign templates and other settings to multiple sheets or tasks at once, without editing each one individually.', 'pta-volunteer-sign-up-sheets' ); ?></p>

	<?php PTA_SUS_Messages::show_messages( true, 'admin' ); ?>

	<?php if ( empty( $sheet_providers ) && empty( $task_providers ) ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'No assignment types are currently registered. Activate an extension (Custom Fields, Locations, Waitlists, Customizer) or create email templates to enable bulk assignments.', 'pta-volunteer-sign-up-sheets' ); ?></p>
		</div>
	<?php else : ?>

	<?php /* ── Tab navigation ── */ ?>
	<nav class="nav-tab-wrapper" style="margin-bottom:20px;">
		<?php if ( ! empty( $sheet_providers ) ) : ?>
		<a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'sheets', 'provider' => $active_provider_id ), $base_url ) ); ?>"
		   class="nav-tab<?php echo 'sheets' === $active_tab ? ' nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Sheet Assignments', 'pta-volunteer-sign-up-sheets' ); ?>
		</a>
		<?php endif; ?>
		<?php if ( ! empty( $task_providers ) ) : ?>
		<a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'tasks', 'tprovider' => $task_active_provider_id ), $base_url ) ); ?>"
		   class="nav-tab<?php echo 'tasks' === $active_tab ? ' nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Task Assignments', 'pta-volunteer-sign-up-sheets' ); ?>
		</a>
		<?php endif; ?>
	</nav>

	<?php /* ═══════════════════════════════════════════════════════════════════
	       SHEET ASSIGNMENTS TAB
	       ═══════════════════════════════════════════════════════════════════ */ ?>
	<div id="tab-sheets"<?php echo 'sheets' !== $active_tab ? ' style="display:none;"' : ''; ?>>

	<?php if ( empty( $sheet_providers ) ) : ?>
		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'No sheet-level assignment types are currently registered.', 'pta-volunteer-sign-up-sheets' ); ?></p>
		</div>
	<?php else : ?>

	<form id="pta-bulk-assign-form" method="post" action="">
		<?php wp_nonce_field( 'pta_sus_bulk_assign', 'pta_sus_bulk_assign_nonce' ); ?>
		<input type="hidden" name="pta_sus_bulk_assign_mode" value="submitted" />

		<?php /* ── Step 1: Assignment type ── */ ?>
		<h2><?php esc_html_e( 'Step 1: What are you assigning?', 'pta-volunteer-sign-up-sheets' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="bulk_assign_provider"><?php esc_html_e( 'Assignment Type', 'pta-volunteer-sign-up-sheets' ); ?></label>
				</th>
				<td>
					<select name="bulk_assign_provider" id="bulk_assign_provider">
						<?php foreach ( $grouped_providers as $section => $group ) : ?>
							<optgroup label="<?php echo esc_attr( $section ); ?>">
								<?php foreach ( $group as $p ) : ?>
									<option value="<?php echo esc_attr( $p['id'] ); ?>"<?php selected( $active_provider_id, $p['id'] ); ?>>
										<?php echo esc_html( $p['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Changing this type reloads the page so the correct choices and current assignments are displayed.', 'pta-volunteer-sign-up-sheets' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php /* ── Step 2: Which value ── */ ?>
		<h2><?php esc_html_e( 'Step 2: Which value?', 'pta-volunteer-sign-up-sheets' ); ?></h2>
		<?php if ( ! $active_provider ) : ?>
			<p><?php esc_html_e( 'Select an assignment type above.', 'pta-volunteer-sign-up-sheets' ); ?></p>
		<?php elseif ( empty( $choices ) ) : ?>
			<div class="notice notice-info inline">
				<p>
					<?php
					printf(
						/* translators: %s: provider label */
						esc_html__( 'No options are available for "%s" yet. Create one in the relevant extension settings first.', 'pta-volunteer-sign-up-sheets' ),
						'<strong>' . esc_html( $active_provider['label'] ) . '</strong>'
					);
					?>
				</p>
			</div>
		<?php else : ?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="bulk_assign_value"><?php echo esc_html( $active_provider['label'] ); ?></label>
				</th>
				<td>
					<?php if ( $is_array_value_type ) : ?>
					<select name="bulk_assign_value[]" id="bulk_assign_value" multiple size="<?php echo min( 8, count( $choices ) ); ?>">
						<?php foreach ( $choices as $val_id => $val_label ) : ?>
							<option value="<?php echo esc_attr( $val_id ); ?>"<?php echo in_array( (string) $val_id, array_map( 'strval', $selected_value ), true ) ? ' selected' : ''; ?>>
								<?php echo esc_html( $val_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Hold Ctrl (Windows) or Cmd (Mac) to select multiple values.', 'pta-volunteer-sign-up-sheets' ); ?></p>
					<?php else : ?>
					<select name="bulk_assign_value" id="bulk_assign_value">
						<?php foreach ( $choices as $val_id => $val_label ) : ?>
							<option value="<?php echo esc_attr( $val_id ); ?>"<?php selected( $selected_value, $val_id ); ?>>
								<?php echo esc_html( $val_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<?php /* ── Step 3: Which sheets ── */ ?>
		<h2><?php esc_html_e( 'Step 3: Which sheets?', 'pta-volunteer-sign-up-sheets' ); ?></h2>

		<?php if ( empty( $sheets ) ) : ?>
			<p><?php esc_html_e( 'No active sheets found.', 'pta-volunteer-sign-up-sheets' ); ?></p>
		<?php else : ?>
			<p>
				<a href="#" id="pta-bulk-select-all" class="button button-secondary"><?php esc_html_e( 'Select All', 'pta-volunteer-sign-up-sheets' ); ?></a>
				<a href="#" id="pta-bulk-deselect-all" class="button button-secondary"><?php esc_html_e( 'Deselect All', 'pta-volunteer-sign-up-sheets' ); ?></a>
				<span id="pta-bulk-sheet-count" style="margin-left:10px;">
					<?php
					printf(
						/* translators: %d: number of sheets */
						esc_html( _n( '%d sheet', '%d sheets', count( $sheets ), 'pta-volunteer-sign-up-sheets' ) ),
						count( $sheets )
					);
					?>
				</span>
			</p>
			<div id="pta-bulk-sheet-list" style="max-height:350px;overflow-y:auto;border:1px solid #ddd;padding:10px;background:#fff;">
				<?php foreach ( $sheets as $sheet ) : ?>
					<label style="display:block;padding:4px 0;cursor:pointer;">
						<input
							type="checkbox"
							name="bulk_assign_sheets[]"
							value="<?php echo absint( $sheet->id ); ?>"
							<?php checked( in_array( (int) $sheet->id, $selected_sheets, true ) ); ?>
						/>
						<strong><?php echo esc_html( $sheet->title ); ?></strong>
						<span style="color:#666;margin-left:6px;">
							<?php if ( $sheet->first_date && '0000-00-00' !== $sheet->first_date ) : ?>
								<?php echo esc_html( pta_datetime( get_option( 'date_format' ), strtotime( $sheet->first_date ) ) ); ?>
							<?php elseif ( '0000-00-00' === $sheet->first_date ) : ?>
								<?php esc_html_e( 'Ongoing', 'pta-volunteer-sign-up-sheets' ); ?>
							<?php endif; ?>
						</span>
					</label>
				<?php endforeach; ?>
			</div>

			<p class="submit" style="margin-top:16px;">
				<input
					type="submit"
					name="pta_sus_bulk_assign_submit"
					id="pta-bulk-assign-submit"
					class="button button-primary"
					value="<?php esc_attr_e( 'Apply to Selected Sheets', 'pta-volunteer-sign-up-sheets' ); ?>"
				/>
			</p>
		<?php endif; ?>
		<?php endif; // end else (choices not empty) ?>

	</form><!-- #pta-bulk-assign-form -->

	<?php /* ── Current Assignments table ── */ ?>
	<?php if ( $active_provider && ! empty( $current_table ) ) : ?>
		<hr />
		<h2>
			<?php
			printf(
				/* translators: %s: provider label */
				esc_html__( 'Current Assignments: %s', 'pta-volunteer-sign-up-sheets' ),
				esc_html( $active_provider['label'] )
			);
			?>
		</h2>
		<table class="widefat striped" style="max-width:800px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Sheet Title', 'pta-volunteer-sign-up-sheets' ); ?></th>
					<th><?php esc_html_e( 'First Date', 'pta-volunteer-sign-up-sheets' ); ?></th>
					<th><?php esc_html_e( 'Current Assignment', 'pta-volunteer-sign-up-sheets' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $current_table as $row ) :
					$sheet       = $row['sheet'];
					$value_label = $row['value_label'];
					$value_id    = $row['value_id'];
					$is_default  = empty( $value_id ); // true for both int 0 and empty string ''
				?>
				<tr>
					<td><?php echo esc_html( $sheet->title ); ?></td>
					<td>
						<?php
						if ( $sheet->first_date && '0000-00-00' !== $sheet->first_date ) {
							echo esc_html( pta_datetime( get_option( 'date_format' ), strtotime( $sheet->first_date ) ) );
						} elseif ( '0000-00-00' === $sheet->first_date ) {
							esc_html_e( 'Ongoing', 'pta-volunteer-sign-up-sheets' );
						} else {
							echo '&mdash;';
						}
						?>
					</td>
					<td<?php echo $is_default ? ' style="color:#888;"' : ''; ?>>
						<?php echo esc_html( $value_label ); ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php endif; // end else (sheet_providers not empty) ?>

	</div><!-- #tab-sheets -->

	<?php /* ═══════════════════════════════════════════════════════════════════
	       TASK ASSIGNMENTS TAB
	       ═══════════════════════════════════════════════════════════════════ */ ?>
	<?php if ( ! empty( $task_providers ) ) : ?>
	<div id="tab-tasks"<?php echo 'tasks' !== $active_tab ? ' style="display:none;"' : ''; ?>>

		<?php if ( empty( $task_choices ) ) : ?>
			<div class="notice notice-info inline">
				<p>
					<?php
					if ( $task_active_provider ) {
						printf(
							/* translators: %s: provider label */
							esc_html__( 'No options are available for "%s" yet. Create one in the relevant extension settings first.', 'pta-volunteer-sign-up-sheets' ),
							'<strong>' . esc_html( $task_active_provider['label'] ) . '</strong>'
						);
					} else {
						esc_html_e( 'No assignment types available for task-level assignments.', 'pta-volunteer-sign-up-sheets' );
					}
					?>
				</p>
			</div>
		<?php else : ?>

		<?php /* ── Task step 1: Assignment type ── */ ?>
		<h2><?php esc_html_e( 'Step 1: What are you assigning?', 'pta-volunteer-sign-up-sheets' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="task-assign-provider"><?php esc_html_e( 'Assignment Type', 'pta-volunteer-sign-up-sheets' ); ?></label>
				</th>
				<td>
					<select id="task-assign-provider">
						<?php foreach ( $task_grouped_providers as $section => $group ) : ?>
							<optgroup label="<?php echo esc_attr( $section ); ?>">
								<?php foreach ( $group as $p ) : ?>
									<option value="<?php echo esc_attr( $p['id'] ); ?>"<?php selected( $task_active_provider_id, $p['id'] ); ?>>
										<?php echo esc_html( $p['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Changing this type reloads the page so the correct choices are displayed.', 'pta-volunteer-sign-up-sheets' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php /* ── Task step 2: Which value ── */ ?>
		<h2><?php esc_html_e( 'Step 2: Which value?', 'pta-volunteer-sign-up-sheets' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="task-assign-value"><?php echo esc_html( $task_active_provider['label'] ); ?></label>
				</th>
				<td>
					<select id="task-assign-value">
						<?php foreach ( $task_choices as $val_id => $val_label ) : ?>
							<option value="<?php echo esc_attr( $val_id ); ?>"<?php selected( $task_selected_value, $val_id ); ?>>
								<?php echo esc_html( $val_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<?php /* ── Task step 3: Select sheets ── */ ?>
		<h2><?php esc_html_e( 'Step 3: Select sheets to load tasks from', 'pta-volunteer-sign-up-sheets' ); ?></h2>
		<p><?php esc_html_e( 'Check the sheets whose tasks you want to work with, then click "Load Tasks".', 'pta-volunteer-sign-up-sheets' ); ?></p>

		<?php if ( empty( $sheets ) ) : ?>
			<p><?php esc_html_e( 'No active sheets found.', 'pta-volunteer-sign-up-sheets' ); ?></p>
		<?php else : ?>
			<p>
				<a href="#" id="pta-task-sheet-select-all" class="button button-secondary"><?php esc_html_e( 'Select All', 'pta-volunteer-sign-up-sheets' ); ?></a>
				<a href="#" id="pta-task-sheet-deselect-all" class="button button-secondary"><?php esc_html_e( 'Deselect All', 'pta-volunteer-sign-up-sheets' ); ?></a>
			</p>
			<div id="pta-task-sheet-list" style="max-height:300px;overflow-y:auto;border:1px solid #ddd;padding:10px;background:#fff;max-width:600px;">
				<?php foreach ( $sheets as $sheet ) : ?>
					<label style="display:block;padding:4px 0;cursor:pointer;">
						<input type="checkbox" class="pta-task-sheet-cb" value="<?php echo absint( $sheet->id ); ?>" />
						<strong><?php echo esc_html( $sheet->title ); ?></strong>
						<span style="color:#666;margin-left:6px;">
							<?php if ( $sheet->first_date && '0000-00-00' !== $sheet->first_date ) : ?>
								<?php echo esc_html( pta_datetime( get_option( 'date_format' ), strtotime( $sheet->first_date ) ) ); ?>
							<?php elseif ( '0000-00-00' === $sheet->first_date ) : ?>
								<?php esc_html_e( 'Ongoing', 'pta-volunteer-sign-up-sheets' ); ?>
							<?php endif; ?>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
			<p style="margin-top:12px;">
				<button type="button" id="pta-load-tasks-btn" class="button button-secondary">
					<?php esc_html_e( 'Load Tasks for Selected Sheets', 'pta-volunteer-sign-up-sheets' ); ?>
				</button>
				<span class="spinner" id="pta-load-tasks-spinner" style="float:none;vertical-align:middle;"></span>
			</p>
		<?php endif; ?>

		<?php /* ── Task step 4: Task table (loaded via AJAX) ── */ ?>
		<div id="pta-task-table-wrap" style="display:none;">
			<h2><?php esc_html_e( 'Step 4: Select tasks to apply to', 'pta-volunteer-sign-up-sheets' ); ?></h2>
			<div id="pta-task-stale-warning" class="notice notice-warning inline" style="display:none;">
				<p><?php esc_html_e( 'Assignment type or value changed since tasks were loaded &mdash; click &ldquo;Load Tasks&rdquo; again to refresh current assignment values.', 'pta-volunteer-sign-up-sheets' ); ?></p>
			</div>
			<p>
				<a href="#" id="pta-task-select-all" class="button button-secondary"><?php esc_html_e( 'Select All', 'pta-volunteer-sign-up-sheets' ); ?></a>
				<a href="#" id="pta-task-deselect-all" class="button button-secondary"><?php esc_html_e( 'Deselect All', 'pta-volunteer-sign-up-sheets' ); ?></a>
				<span id="pta-task-count" style="margin-left:10px;"></span>
			</p>
			<div id="pta-task-table-inner"></div>
			<p style="margin-top:12px;">
				<button type="button" id="pta-apply-tasks-btn" class="button button-primary">
					<?php esc_html_e( 'Apply to Selected Tasks', 'pta-volunteer-sign-up-sheets' ); ?>
				</button>
				<span class="spinner" id="pta-apply-tasks-spinner" style="float:none;vertical-align:middle;"></span>
			</p>
		</div><!-- #pta-task-table-wrap -->

		<div id="pta-task-result" style="display:none;margin-top:12px;"></div>

		<?php endif; // end else (task_choices not empty) ?>

	</div><!-- #tab-tasks -->
	<?php endif; // end if task_providers not empty ?>

	<?php endif; // end else (providers not empty) ?>

</div><!-- .wrap -->

<script>
(function($) {
	'use strict';

	var pageSlug  = '<?php echo esc_js( $page_slug ); ?>';
	var nonce     = '<?php echo esc_js( wp_create_nonce( 'pta_sus_bulk_assign' ) ); ?>';
	var ajaxUrl   = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

	// ── Sheet tab: assignment type change → page reload ──────────────────────
	$('#bulk_assign_provider').on('change', function() {
		var providerId = $(this).val();
		window.location.href = '<?php echo esc_js( admin_url( 'admin.php' ) ); ?>?page=' + pageSlug + '&tab=sheets&provider=' + encodeURIComponent(providerId);
	});

	// ── Sheet tab: Select All / Deselect All ──────────────────────────────────
	$('#pta-bulk-select-all').on('click', function(e) {
		e.preventDefault();
		$('#pta-bulk-sheet-list input[type="checkbox"]').prop('checked', true);
		updateSheetCount();
	});
	$('#pta-bulk-deselect-all').on('click', function(e) {
		e.preventDefault();
		$('#pta-bulk-sheet-list input[type="checkbox"]').prop('checked', false);
		updateSheetCount();
	});
	function updateSheetCount() {
		var total   = $('#pta-bulk-sheet-list input[type="checkbox"]').length;
		var checked = $('#pta-bulk-sheet-list input[type="checkbox"]:checked').length;
		$('#pta-bulk-sheet-count').text(checked + ' / ' + total + ' <?php echo esc_js( __( 'selected', 'pta-volunteer-sign-up-sheets' ) ); ?>');
	}
	$('#pta-bulk-sheet-list').on('change', 'input[type="checkbox"]', updateSheetCount);
	updateSheetCount();

	// ── Sheet tab: array-type (multi-select) value field ─────────────────────
	<?php if ( $is_array_value_type ) : ?>
	if ( typeof $.fn.select2 !== 'undefined' ) {
		$('#bulk_assign_value').select2({
			width: '350px',
			closeOnSelect: false,
			placeholder: '<?php echo esc_js( __( '— Select groups —', 'pta-sus-groups' ) ); ?>'
		});
	}
	<?php endif; ?>

	// ── Sheet tab: confirmation dialog on submit ──────────────────────────────
	$('#pta-bulk-assign-form').on('submit', function(e) {
		var checked = $('#pta-bulk-sheet-list input[type="checkbox"]:checked').length;
		if (checked === 0) {
			e.preventDefault();
			alert('<?php echo esc_js( __( 'Please select at least one sheet.', 'pta-volunteer-sign-up-sheets' ) ); ?>');
			return;
		}
		var msg = checked === 1
			? '<?php echo esc_js( __( 'You are about to update 1 sheet. Continue?', 'pta-volunteer-sign-up-sheets' ) ); ?>'
			: '<?php echo esc_js( __( 'You are about to update', 'pta-volunteer-sign-up-sheets' ) ); ?>' + ' ' + checked + ' <?php echo esc_js( __( 'sheets. Continue?', 'pta-volunteer-sign-up-sheets' ) ); ?>';
		if (!confirm(msg)) {
			e.preventDefault();
		}
	});

	// ── Task tab: assignment type change → page reload (stay on task tab) ────
	$('#task-assign-provider').on('change', function() {
		var providerId = $(this).val();
		window.location.href = '<?php echo esc_js( admin_url( 'admin.php' ) ); ?>?page=' + pageSlug + '&tab=tasks&tprovider=' + encodeURIComponent(providerId);
	});

	// ── Task tab: sheet list Select All / Deselect All ────────────────────────
	$('#pta-task-sheet-select-all').on('click', function(e) {
		e.preventDefault();
		$('.pta-task-sheet-cb').prop('checked', true);
	});
	$('#pta-task-sheet-deselect-all').on('click', function(e) {
		e.preventDefault();
		$('.pta-task-sheet-cb').prop('checked', false);
	});

	// ── Task tab: stale-warning when provider or value changes after load ─────
	var tasksLoaded        = false;
	var loadedProviderId   = '';
	var loadedValue        = '';

	function markStale() {
		if ( tasksLoaded ) {
			var currentProvider = $('#task-assign-provider').val();
			var currentValue    = $('#task-assign-value').val();
			if ( currentProvider !== loadedProviderId || currentValue !== loadedValue ) {
				$('#pta-task-stale-warning').show();
			} else {
				$('#pta-task-stale-warning').hide();
			}
		}
	}
	$('#task-assign-value').on('change', markStale);

	// ── Task tab: Load Tasks button ───────────────────────────────────────────
	$('#pta-load-tasks-btn').on('click', function() {
		var sheetIds = [];
		$('.pta-task-sheet-cb:checked').each(function() {
			sheetIds.push( $(this).val() );
		});
		if ( sheetIds.length === 0 ) {
			alert('<?php echo esc_js( __( 'Please select at least one sheet.', 'pta-volunteer-sign-up-sheets' ) ); ?>');
			return;
		}

		var providerId = $('#task-assign-provider').val();
		$('#pta-load-tasks-spinner').addClass('is-active');
		$('#pta-load-tasks-btn').prop('disabled', true);
		$('#pta-task-result').hide();

		var data = {
			action:      'pta_sus_bulk_assign_get_tasks',
			nonce:       nonce,
			provider_id: providerId,
			sheet_ids:   sheetIds
		};

		$.post( ajaxUrl, data, function( response ) {
			$('#pta-load-tasks-spinner').removeClass('is-active');
			$('#pta-load-tasks-btn').prop('disabled', false);

			if ( ! response.success ) {
				$('#pta-task-result')
					.html('<div class="notice notice-error inline"><p>' + escHtml( response.data.message ) + '</p></div>')
					.show();
				return;
			}

			$('#pta-task-table-inner').html( response.data.html );
			$('#pta-task-table-wrap').show();
			updateTaskCount();

			// Record what was loaded so we can detect stale state.
			tasksLoaded      = true;
			loadedProviderId = providerId;
			loadedValue      = $('#task-assign-value').val();
			$('#pta-task-stale-warning').hide();

			// Wire up the header checkbox for Select All in the injected table.
			$('#pta-task-check-all').on('change', function() {
				$('.pta-task-checkbox').prop('checked', $(this).is(':checked'));
				updateTaskCount();
			});
			$('#pta-task-table-inner').on('change', '.pta-task-checkbox', updateTaskCount);

		}).fail(function() {
			$('#pta-load-tasks-spinner').removeClass('is-active');
			$('#pta-load-tasks-btn').prop('disabled', false);
			$('#pta-task-result')
				.html('<div class="notice notice-error inline"><p><?php echo esc_js( __( 'Request failed. Please try again.', 'pta-volunteer-sign-up-sheets' ) ); ?></p></div>')
				.show();
		});
	});

	// ── Task tab: task list Select All / Deselect All (static buttons) ────────
	$('#pta-task-select-all').on('click', function(e) {
		e.preventDefault();
		$('.pta-task-checkbox').prop('checked', true);
		$('#pta-task-check-all').prop('checked', true);
		updateTaskCount();
	});
	$('#pta-task-deselect-all').on('click', function(e) {
		e.preventDefault();
		$('.pta-task-checkbox').prop('checked', false);
		$('#pta-task-check-all').prop('checked', false);
		updateTaskCount();
	});

	function updateTaskCount() {
		var total   = $('.pta-task-checkbox').length;
		var checked = $('.pta-task-checkbox:checked').length;
		$('#pta-task-count').text(
			checked + ' / ' + total + ' <?php echo esc_js( __( 'selected', 'pta-volunteer-sign-up-sheets' ) ); ?>'
		);
	}

	// ── Task tab: Apply to Selected Tasks ─────────────────────────────────────
	$('#pta-apply-tasks-btn').on('click', function() {
		var taskIds = [];
		$('.pta-task-checkbox:checked').each(function() {
			taskIds.push( $(this).val() );
		});
		if ( taskIds.length === 0 ) {
			alert('<?php echo esc_js( __( 'Please select at least one task.', 'pta-volunteer-sign-up-sheets' ) ); ?>');
			return;
		}

		var providerId = $('#task-assign-provider').val();
		var value      = $('#task-assign-value').val();
		var valueText  = $('#task-assign-value option:selected').text();
		var msg        = taskIds.length === 1
			? '<?php echo esc_js( __( 'You are about to update 1 task. Continue?', 'pta-volunteer-sign-up-sheets' ) ); ?>'
			: '<?php echo esc_js( __( 'You are about to update', 'pta-volunteer-sign-up-sheets' ) ); ?>' + ' ' + taskIds.length + ' <?php echo esc_js( __( 'tasks. Continue?', 'pta-volunteer-sign-up-sheets' ) ); ?>';

		if ( ! confirm(msg) ) {
			return;
		}

		$('#pta-apply-tasks-spinner').addClass('is-active');
		$('#pta-apply-tasks-btn').prop('disabled', true);
		$('#pta-task-result').hide();

		var data = {
			action:      'pta_sus_bulk_assign_save_tasks',
			nonce:       nonce,
			provider_id: providerId,
			value:       value,
			task_ids:    taskIds
		};

		$.post( ajaxUrl, data, function( response ) {
			$('#pta-apply-tasks-spinner').removeClass('is-active');
			$('#pta-apply-tasks-btn').prop('disabled', false);

			if ( ! response.success ) {
				$('#pta-task-result')
					.html('<div class="notice notice-error inline"><p>' + escHtml( response.data.message ) + '</p></div>')
					.show();
				return;
			}

			var html = '<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>';
			if ( response.data.warnings ) {
				html += '<div class="notice notice-warning inline"><p>' + escHtml( response.data.warnings ) + '</p></div>';
			}
			$('#pta-task-result').html(html).show();

			// Reload tasks so Current Assignment column reflects the new values.
			$('#pta-load-tasks-btn').trigger('click');

		}).fail(function() {
			$('#pta-apply-tasks-spinner').removeClass('is-active');
			$('#pta-apply-tasks-btn').prop('disabled', false);
			$('#pta-task-result')
				.html('<div class="notice notice-error inline"><p><?php echo esc_js( __( 'Request failed. Please try again.', 'pta-volunteer-sign-up-sheets' ) ); ?></p></div>')
				.show();
		});
	});

	// ── Utility: simple HTML escaping for user-supplied strings in notices ────
	function escHtml(str) {
		return $('<div>').text(str).html();
	}

})(jQuery);
</script>
