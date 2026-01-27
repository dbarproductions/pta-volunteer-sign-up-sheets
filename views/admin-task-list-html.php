<?php
/**
 * Task List View - Modal-based task management
 * 
 * @var array $f passed in array of sheet/task data
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$sheet_id = isset($f['sheet_id']) ? (int)$f['sheet_id'] : 0;
$sheet_type = isset($f['sheet_type']) ? $f['sheet_type'] : '';
$sheet_title = isset($f['sheet_title']) ? esc_html(stripslashes($f['sheet_title'])) : '';
$no_signups = isset($f['sheet_no_signups']) ? absint($f['sheet_no_signups']) : 0;

// Get all tasks for this sheet
$tasks = PTA_SUS_Task_Functions::get_tasks($sheet_id);

// Get unique task titles from ALL sheets for the "Add New Task" dropdown
// This allows copying tasks from any sheet to the current sheet
$unique_tasks = PTA_SUS_Task_Functions::get_unique_task_titles(true);

// Get current dates from existing tasks (if any), or from sheet if no tasks exist
$current_single_date = '';
$current_recurring_dates = '';
if (!empty($tasks)) {
	// Get dates from first task (all tasks should have same dates for Single/Recurring)
	$first_task = $tasks[0];
	if (!empty($first_task->dates) && $first_task->dates !== '0000-00-00') {
		if ("Single" === $sheet_type) {
			$current_single_date = $first_task->dates;
		} elseif ("Recurring" === $sheet_type) {
			$current_recurring_dates = $first_task->dates;
		}
	}
} else {
	// No tasks yet - check if sheet has dates stored in first_date/last_date
	$sheet = pta_sus_get_sheet($sheet_id);
	if ($sheet) {
		if ("Single" === $sheet_type && !empty($sheet->first_date) && $sheet->first_date !== '0000-00-00') {
			$current_single_date = $sheet->first_date;
		} elseif ("Recurring" === $sheet_type && !empty($sheet->first_date) && $sheet->first_date !== '0000-00-00') {
			// For Recurring, we need to reconstruct the dates from first_date and last_date
			// Since we can't store the full list in first_date/last_date, we'll need to check if there's a better way
			// For now, we'll leave it empty and let the user re-enter, or we could store in a meta field
			// Actually, let's check if first_date and last_date are the same (Single) or different (Recurring)
			if ($sheet->first_date !== $sheet->last_date && $sheet->last_date !== '0000-00-00') {
				// We have a range, but not the full list - for now, leave empty
				// In Phase 2, we'll have sheet_dates field to store the full list
			}
		}
	}
}

// Use passed values if available, otherwise use current task dates or sheet dates
$single_date = isset($f['single_date']) ? esc_attr($f['single_date']) : $current_single_date;
$recurring_dates = isset($f['recurring_dates']) ? esc_attr($f['recurring_dates']) : $current_recurring_dates;

// Check if sheet has signups (for Single sheets, make date readonly)
$main_options = get_option('pta_volunteer_sus_main_options', array());
$skip_signups_check = isset($main_options['skip_signups_check']) && true == $main_options['skip_signups_check'];
$sheet_has_signups = false;
$date_readonly = false;
if ("Single" === $sheet_type && !$skip_signups_check && !empty($tasks)) {
	$sheet_has_signups = PTA_SUS_Sheet_Functions::sheet_has_signups($sheet_id);
	$date_readonly = $sheet_has_signups;
}

do_action( 'pta_sus_tasks_form_start', $f, count($tasks) );
?>

<?php if ( "Single" === $sheet_type ): ?>
	<div class="pta-sus-sheet-dates-section">
		<h2><?php echo __('Select the date for ', 'pta-volunteer-sign-up-sheets') . $sheet_title; ?></h2>
		<p>
			<label for="single_date"><strong><?php _e('Date:', 'pta-volunteer-sign-up-sheets'); ?></strong></label>
			<input type="text" class="singlePicker" id="single_date" name="single_date" value="<?php echo esc_attr($single_date); ?>" size="12" <?php echo $date_readonly ? 'readonly="readonly"' : ''; ?> data-readonly="<?php echo $date_readonly ? 'true' : 'false'; ?>" />
			<?php if (!empty($single_date)) : ?>
				<span class="dashicons dashicons-yes-alt" style="color: #46b450; vertical-align: middle; margin-left: 5px;" title="<?php esc_attr_e('Date is set', 'pta-volunteer-sign-up-sheets'); ?>"></span>
			<?php else : ?>
				<span class="dashicons dashicons-warning" style="color: #d63638; vertical-align: middle; margin-left: 5px;" title="<?php esc_attr_e('Date not set - required before adding tasks', 'pta-volunteer-sign-up-sheets'); ?>"></span>
			<?php endif; ?>
			<button type="button" id="pta-sus-save-dates" class="button button-primary" data-sheet-id="<?php echo $sheet_id; ?>" data-sheet-type="<?php echo esc_attr($sheet_type); ?>" <?php echo $date_readonly ? 'disabled="disabled" style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
				<?php _e('Save Date', 'pta-volunteer-sign-up-sheets'); ?>
			</button>
			<span class="spinner" style="float: none; margin-left: 10px;"></span>
		</p>
		<p class="description">
			<?php if ($date_readonly) : ?>
				<em style="color: #d63638;"><strong><?php _e('This date field is locked because tasks already have signups. Please clear all signups first, or use the reschedule function to change dates.', 'pta-volunteer-sign-up-sheets'); ?></strong></em>
			<?php else : ?>
				<em><?php _e('Select a date for the event. All tasks will be assigned to this date. Click "Save Date" to update all existing tasks.', 'pta-volunteer-sign-up-sheets'); ?></em>
			<?php endif; ?>
			<?php if (!empty($single_date)) : ?>
				<br/><strong><?php _e('Current date:', 'pta-volunteer-sign-up-sheets'); ?></strong> <?php echo esc_html($single_date); ?>
			<?php endif; ?>
		</p>
		<div id="pta-sus-dates-message" class="pta-sus-dates-message" style="display:none;"></div>
	</div>
<?php elseif ( "Recurring" === $sheet_type): ?>
	<div class="pta-sus-sheet-dates-section">
		<h2><?php echo __('Select ALL the dates for ', 'pta-volunteer-sign-up-sheets') . $sheet_title; ?></h2>
		<p>
			<label for="recurring_dates"><strong><?php _e('Dates:', 'pta-volunteer-sign-up-sheets'); ?></strong></label>
			<input type="text" id="multi999Picker" name="recurring_dates" value="<?php echo esc_attr($recurring_dates); ?>" size="40" />
			<?php if (!empty($recurring_dates)) : ?>
				<span class="dashicons dashicons-yes-alt" style="color: #46b450; vertical-align: middle; margin-left: 5px;" title="<?php esc_attr_e('Dates are set', 'pta-volunteer-sign-up-sheets'); ?>"></span>
			<?php else : ?>
				<span class="dashicons dashicons-warning" style="color: #d63638; vertical-align: middle; margin-left: 5px;" title="<?php esc_attr_e('Dates not set - required before adding tasks', 'pta-volunteer-sign-up-sheets'); ?>"></span>
			<?php endif; ?>
			<button type="button" id="pta-sus-save-dates" class="button button-primary" data-sheet-id="<?php echo $sheet_id; ?>" data-sheet-type="<?php echo esc_attr($sheet_type); ?>">
				<?php _e('Save Dates', 'pta-volunteer-sign-up-sheets'); ?>
			</button>
			<span class="spinner" style="float: none; margin-left: 10px;"></span>
		</p>
		<p class="description">
			<em><?php _e('Select all the dates for the event (comma-separated). All tasks will be assigned to these dates. Click "Save Dates" to update all existing tasks.', 'pta-volunteer-sign-up-sheets'); ?></em>
			<?php if (!empty($recurring_dates)) : ?>
				<br/><strong><?php _e('Current dates:', 'pta-volunteer-sign-up-sheets'); ?></strong> <?php echo esc_html($recurring_dates); ?>
			<?php endif; ?>
		</p>
		<div id="pta-sus-dates-message" class="pta-sus-dates-message" style="display:none;"></div>
	</div>
<?php endif; ?>

<h2><?php echo __('Tasks for ', 'pta-volunteer-sign-up-sheets') . $sheet_title; ?></h2>

<div class="pta-sus-task-management">
	<div class="pta-sus-add-task-controls">
		<button type="button" id="pta-sus-add-new-task" class="button button-primary">
			<?php _e('Add New Task', 'pta-volunteer-sign-up-sheets'); ?>
		</button>
		<select id="pta-sus-copy-task-select" class="pta-sus-copy-task-select">
			<option value="0"><?php _e('New Empty Task', 'pta-volunteer-sign-up-sheets'); ?></option>
			<?php foreach ($unique_tasks as $task) : ?>
				<option value="<?php echo $task->id; ?>"><?php echo esc_html(stripslashes($task->title)); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="button" id="pta-sus-add-copy-task" class="button">
			<?php _e('Add Copy', 'pta-volunteer-sign-up-sheets'); ?>
		</button>
	</div>

	<table class="wp-list-table widefat fixed striped pta-sus-task-list">
		<thead>
			<tr>
				<th class="column-drag" style="width: 50px; white-space: nowrap;"><?php _e('Drag', 'pta-volunteer-sign-up-sheets'); ?></th>
				<th class="column-title"><?php _e('Task/Item', 'pta-volunteer-sign-up-sheets'); ?></th>
				<?php if ( "Multi-Day" === $sheet_type ) : ?>
					<th class="column-date"><?php _e('Date', 'pta-volunteer-sign-up-sheets'); ?></th>
				<?php endif; ?>
				<?php if (!$no_signups) : ?>
					<th class="column-qty"><?php _e('# Needed', 'pta-volunteer-sign-up-sheets'); ?></th>
				<?php endif; ?>
				<th class="column-time"><?php _e('Start Time', 'pta-volunteer-sign-up-sheets'); ?></th>
				<th class="column-time"><?php _e('End Time', 'pta-volunteer-sign-up-sheets'); ?></th>
				<th class="column-actions" style="width: 150px;"><?php _e('Actions', 'pta-volunteer-sign-up-sheets'); ?></th>
			</tr>
		</thead>
		<tbody id="pta-sus-task-list-body">
			<?php if (empty($tasks)) : ?>
				<tr class="pta-sus-no-tasks-row">
					<td colspan="<?php 
						$colspan = 6; // drag, title, start time, end time, actions
						if ("Multi-Day" === $sheet_type) $colspan++;
						if (!$no_signups) $colspan++;
						echo $colspan;
					?>" class="pta-sus-no-tasks" style="text-align: center; padding: 20px; color: #666;">
						<?php _e('No tasks have been created yet. Click "Add New Task" to get started.', 'pta-volunteer-sign-up-sheets'); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ($tasks as $task) : 
					$task_dates = !empty($task->dates) ? $task->dates : '';
					$start_time = !empty($task->time_start) ? pta_datetime(get_option("time_format"), strtotime($task->time_start)) : '';
					$end_time = !empty($task->time_end) ? pta_datetime(get_option("time_format"), strtotime($task->time_end)) : '';
				?>
					<tr data-task-id="<?php echo $task->id; ?>" data-position="<?php echo $task->position; ?>">
						<td class="column-drag">
							<span class="dashicons dashicons-move" style="cursor: move;"></span>
						</td>
						<td class="column-title">
							<strong><?php echo esc_html(stripslashes($task->title)); ?></strong>
							<?php if (!empty($task->description)) : ?>
								<br/><small><?php echo wp_trim_words(strip_tags($task->description), 10); ?></small>
							<?php endif; ?>
						</td>
						<?php if ( "Multi-Day" === $sheet_type ) : ?>
							<td class="column-date"><?php echo esc_html($task_dates); ?></td>
						<?php endif; ?>
						<?php if (!$no_signups) : ?>
							<td class="column-qty"><?php echo $task->qty; ?></td>
						<?php endif; ?>
						<td class="column-time"><?php echo esc_html($start_time); ?></td>
						<td class="column-time"><?php echo esc_html($end_time); ?></td>
						<td class="column-actions">
							<button type="button" class="button button-small pta-sus-edit-task" data-task-id="<?php echo $task->id; ?>">
								<?php _e('Edit', 'pta-volunteer-sign-up-sheets'); ?>
							</button>
							<button type="button" class="button button-small pta-sus-copy-task" data-task-id="<?php echo $task->id; ?>">
								<?php _e('Copy', 'pta-volunteer-sign-up-sheets'); ?>
							</button>
							<button type="button" class="button button-small pta-sus-delete-task" data-task-id="<?php echo $task->id; ?>">
								<?php _e('Delete', 'pta-volunteer-sign-up-sheets'); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	
	<div id="pta-sus-save-order-container" style="display: none; margin-top: 10px;">
		<button type="button" id="pta-sus-save-order" class="button button-primary">
			<?php _e('Save Order', 'pta-volunteer-sign-up-sheets'); ?>
		</button>
		<span class="pta-sus-order-message" style="margin-left: 10px; color: #d63638;">
			<?php _e('Task order has changed. Click "Save Order" to save your changes.', 'pta-volunteer-sign-up-sheets'); ?>
		</span>
	</div>
</div>

<?php
// tasks move - filter by author for Signup Sheet Authors
$can_manage_others = current_user_can( 'manage_others_signup_sheets' );
$author_id = $can_manage_others ? null : get_current_user_id();
$args = array(
	'trash' => false,
	'active_only' => false,
	'show_hidden' => true,
	'author_id' => $author_id,
);
$rows = PTA_SUS_Sheet_Functions::get_sheets_by_args( $args );
if (count($rows)>1) : ?>
	<hr />
	<h2><?php _e('Move tasks ', 'pta-volunteer-sign-up-sheets'); ?></h2>
	<form name="move_tasks" id="pta-sus-move-tasks" method="post" action="">
	<?php wp_nonce_field('pta_sus_move_tasks','pta_sus_move_tasks_nonce'); ?>
	<input type="hidden" name="sheet_id" value="<?php echo $sheet_id; ?>" />
	<input type="hidden" name="tasks_mode" value="move_tasks" />
	<label for="new_sheet_id"><?php _e('Move all tasks of this sheet to sheet', 'pta-volunteer-sign-up-sheets'); ?></label>
	<select id="new_sheet_id" name="new_sheet_id" required>
        <option value=""><?php _e("Please Select a Sheet", "pta_volunteer_sus"); ?></option>
	<?php foreach ($rows as $row) :
		if ($row->id === $sheet_id) continue; ?>
		<option value="<?php echo esc_attr($row->id); ?>"><?php echo esc_html(stripslashes($row->title)); ?></option>
	<?php endforeach; ?>
	</select>
	<input type="submit" name="Submit" class="button-primary" value="<?php _e("Move", "pta_volunteer_sus"); ?>" />
	</form>
<?php endif; ?>

<?php
// Hidden data for JavaScript
?>
<script type="text/javascript">
var ptaSusTaskData = {
	sheetId: <?php echo $sheet_id; ?>,
	sheetType: '<?php echo esc_js($sheet_type); ?>',
	noSignups: <?php echo $no_signups ? 'true' : 'false'; ?>,
	ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
	nonce: '<?php echo wp_create_nonce('pta_sus_task_ajax'); ?>',
	singleDate: '<?php echo esc_js($single_date); ?>',
	recurringDates: '<?php echo esc_js($recurring_dates); ?>',
	loadingText: '<?php echo esc_js(__('Loading...', 'pta-volunteer-sign-up-sheets')); ?>'
};
</script>

<?php
do_action( 'pta_sus_tasks_form_bottom', $f );
?>

