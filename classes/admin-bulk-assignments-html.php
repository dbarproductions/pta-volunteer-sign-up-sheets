<?php
/**
 * Bulk Assignments admin page template.
 *
 * Variables available from PTA_SUS_Admin_Bulk_Assignments::render_page():
 *   @var PTA_SUS_Admin_Bulk_Assignments $this
 *   @var array[]                        $providers         All registered providers.
 *   @var array|null                     $active_provider   Currently selected provider (or null).
 *   @var string                         $active_provider_id
 *   @var array                          $choices           int => label for the active provider.
 *   @var PTA_SUS_Sheet[]                $sheets            All eligible sheets.
 *   @var array[]                        $current_table     [ ['sheet'=>..., 'value_id'=>..., 'value_label'=>...] ]
 *   @var int                            $selected_value    Value ID from last submission.
 *   @var int[]                          $selected_sheets   Sheet IDs from last submission.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Group providers by section_label for the optgroup dropdown.
$grouped_providers = array();
foreach ( $providers as $p ) {
	$section = $p['section_label'] ?? __('Other', 'pta-volunteer-sign-up-sheets');
	$grouped_providers[ $section ][] = $p;
}
?>
<div class="wrap pta_sus">
	<h1><?php esc_html_e( 'Bulk Assignments', 'pta-volunteer-sign-up-sheets' ); ?></h1>
	<p><?php esc_html_e( 'Use this page to quickly assign templates and other settings to multiple sheets at once, without editing each sheet individually.', 'pta-volunteer-sign-up-sheets' ); ?></p>

	<?php PTA_SUS_Messages::show_messages( true, 'admin' ); ?>

	<?php if ( empty( $providers ) ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'No assignment types are currently registered. Activate an extension (Custom Fields, Locations, Waitlists, Customizer) or create email templates to enable bulk assignments.', 'pta-volunteer-sign-up-sheets' ); ?></p>
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
						<?php if ( $sheet->first_date ) : ?>
							<span style="color:#666;margin-left:6px;">
								<?php echo esc_html( pta_datetime( get_option( 'date_format' ), strtotime( $sheet->first_date ) ) ); ?>
							</span>
						<?php endif; ?>
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
						<?php echo $sheet->first_date
							? esc_html( pta_datetime( get_option( 'date_format' ), strtotime( $sheet->first_date ) ) )
							: '—';
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

	<?php endif; // end else (providers not empty) ?>

</div><!-- .wrap -->

<script>
(function($) {
	'use strict';

	// ── Assignment type change: submit the form in "browse" mode (GET) ──
	$('#bulk_assign_provider').on('change', function() {
		var providerId = $(this).val();
		var url = window.location.pathname + '?page=<?php echo esc_js( 'pta-sus-settings_bulk_assignments' ); ?>&provider=' + encodeURIComponent(providerId);
		window.location.href = url;
	});

	// ── Select All / Deselect All ──
	$('#pta-bulk-select-all').on('click', function(e) {
		e.preventDefault();
		$('#pta-bulk-sheet-list input[type="checkbox"]').prop('checked', true);
		updateCount();
	});
	$('#pta-bulk-deselect-all').on('click', function(e) {
		e.preventDefault();
		$('#pta-bulk-sheet-list input[type="checkbox"]').prop('checked', false);
		updateCount();
	});

	// ── Live sheet count ──
	function updateCount() {
		var total   = $('#pta-bulk-sheet-list input[type="checkbox"]').length;
		var checked = $('#pta-bulk-sheet-list input[type="checkbox"]:checked').length;
		$('#pta-bulk-sheet-count').text(checked + ' / ' + total + ' <?php echo esc_js( __( 'selected', 'pta-volunteer-sign-up-sheets' ) ); ?>');
	}
	$('#pta-bulk-sheet-list').on('change', 'input[type="checkbox"]', updateCount);
	updateCount();

	// ── Select2 for array-type (multi-select) value field ──
	<?php if ( $is_array_value_type ) : ?>
	if ( typeof $.fn.select2 !== 'undefined' ) {
		$('#bulk_assign_value').select2({
			width: '350px',
			closeOnSelect: false,
			placeholder: '<?php echo esc_js( __( '— Select groups —', 'pta-sus-groups' ) ); ?>'
		});
	}
	<?php endif; ?>

	// ── Always-show confirmation dialog on submit ──
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

})(jQuery);
</script>
