<?php
/**
 * @var array $f passed in array of tasks data
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
$count = (isset($f['task_title'])) ? count($f['task_title']) : 3;
$no_signups = absint($f['sheet_no_signups']);
do_action( 'pta_sus_tasks_form_start', $f, $count );
if ($count < 3) $count = 3;
?>
<form name="add_tasks" id="pta-sus-modify-tasks" method="post" action="">
<?php if ( "Single" == $f['sheet_type'] ): ?>
	<h2><?php echo __('Select the date for ', 'pta_volunteer_sus'). stripslashes(esc_attr($f['sheet_title'])); ?></h2>
	<p>
		<label for="single_date"><strong><?php _e('Date:', 'pta_volunteer_sus'); ?></strong></label>
		<input type="text" class="singlePicker" id="single_date" name="single_date" value="<?php echo ((isset($f['single_date']) ? esc_attr($f['single_date']) : '')); ?>" size="12" />
		<em><?php _e('Select a date for the event.  All tasks will then be assigned to this date.', 'pta_volunteer_sus'); ?></em>
	</p>
<?php elseif ( "Recurring" == $f['sheet_type']): ?>
	<h2><?php echo __('Select ALL the dates for ', 'pta_volunteer_sus'). stripslashes(esc_attr($f['sheet_title'])); ?></h2>
	<p>
		<label for="recurring_dates"><strong><?php _e('Dates:', 'pta_volunteer_sus'); ?></strong></label>
		<input type="text" id="multi999Picker" name="recurring_dates" value="<?php echo ((isset($f['recurring_dates']) ? esc_attr($f['recurring_dates']) : '')); ?>" size="40" />
		<em><?php _e('Select all the dates for the event. Copies of the tasks will be created for each date.', 'pta_volunteer_sus'); ?></em>
	</p>
<?php endif; ?>

<h2><?php echo __('Tasks for ', 'pta_volunteer_sus'). stripslashes(esc_attr($f['sheet_title'])); ?></h2>
<h3><?php _e('Tasks/Items', 'pta_volunteer_sus'); ?></h3>
<p><em><?php _e('Enter tasks or items below. Drag and drop to change sort order. Times are optional. If you need details for an item or task (such as what dish they are bringing for a lunch) check the Details Needed box.<br/>
		Click on (+) to add additional tasks, or (-) to remove a task.  At least one task/item must be entered.  If # needed is left blank, the value will be set to 1.', 'pta_volunteer_sus'); ?></em></p>
<ul class="tasks">
<?php for ($i = 0; $i < $count; $i++) :
	do_action( 'pta_sus_tasks_form_task_loop_start', $f, $i ); ?>
	<li id="task-<?php echo $i; ?>">
	<?php _e('Task/Item:', 'pta_volunteer_sus'); ?> <input type="text" name="task_title[<?php echo $i; ?>]" id="task_title[<?php echo $i; ?>]" value="<?php echo ((isset($f['task_title'][$i]) ? esc_attr($f['task_title'][$i]) : '')); ?>" size="40" />&nbsp;&nbsp;
	<?php if ( "Multi-Day" == $f['sheet_type'] ) : ?>
		<?php _e('Date:','pta_volunteer_sus'); ?> <input type="text" class="singlePicker" name="task_dates[<?php echo $i; ?>]" id="singlePicker[<?php echo $i; ?>]" value="<?php echo ((isset($f['task_dates'][$i]) ? esc_attr($f['task_dates'][$i]) : '')); ?>" size="10" />&nbsp;&nbsp;
	<?php endif; ?>
	<?php if (!$no_signups) : ?>
		<?php _e('# Needed:','pta_volunteer_sus'); ?> <input type="text" name="task_qty[<?php echo $i; ?>]" id="task_qty[<?php echo $i; ?>]" value="<?php echo((isset($f['task_qty'][$i]) ? (int)$f['task_qty'][$i] : '')); ?>" size="3" />
	<?php endif; ?>
	&nbsp;&nbsp;<?php _e('Start Time:', 'pta_volunteer_sus'); ?> <input type="text" class="pta-timepicker" id="timepicker_start[<?php echo $i; ?>]" name="task_time_start[<?php echo $i; ?>]" value="<?php echo((isset($f['task_time_start'][$i]) ? esc_attr($f['task_time_start'][$i]) : '')); ?>" size="10" />
	&nbsp;&nbsp;<?php _e('End Time:', 'pta_volunteer_sus'); ?> <input type="text" class="pta-timepicker" id="timepicker_end[<?php echo $i; ?>]" name="task_time_end[<?php echo $i; ?>]" value="<?php echo((isset($f['task_time_end'][$i]) ? esc_attr($f['task_time_end'][$i]) : '')); ?>" size="10" />

	<?php do_action('pta_sus_task_form_task_loop_after_times', $f, $i); ?>

	<a href="#" class="task_description_trigger"  id="description_trigger_<?php echo $i; ?>"><?php _e('Add/Edit Task Description', 'pta_volunteer_sus'); ?></a>
    <div class="pta_sus_task_description" id="task_description_<?php echo $i; ?>">
	    <?php
	    $content = isset($f['task_description'][$i]) ? wp_kses_post($f['task_description'][$i]) : '';
	    ?>
        <p>
            <label for="task_description[<?php echo $i; ?>]"><?php _e('Optional Task Description (HTML allowed):', 'pta_volunteer_sus'); ?></label><br/>
	        <textarea name="task_description[<?php echo $i; ?>]" id="task_description[<?php echo $i; ?>]" rows="4" cols="150"><?php echo $content; ?></textarea>
        </p>
    </div>

	<?php if(!$no_signups) : ?>
		<br/><?php _e('Allow Duplicates? ', 'pta_volunteer_sus');
		if (!isset($f['task_allow_duplicates'][$i])) {
			$f['task_allow_duplicates'][$i] = "NO";
		} ?>
		<input type="checkbox" name="task_allow_duplicates[<?php echo $i; ?>]" id="task_allow_duplicates[<?php echo $i; ?>]" value="YES"
		<?php if (isset($f['task_allow_duplicates'][$i]) &&  $f['task_allow_duplicates'][$i] === "YES") {
			echo 'checked="checked" ';
		} ?>
		/>
		&nbsp;&nbsp;<?php _e('Enable Quantities? ', 'pta_volunteer_sus');
		if (!isset($f['task_enable_quantities'][$i])) {
			$f['task_enable_quantities'][$i] = "NO";
		} ?>
		<input type="checkbox" name="task_enable_quantities[<?php echo $i; ?>]" id="task_enable_quantities[<?php echo $i; ?>]" value="YES"
		<?php if (isset($f['task_enable_quantities'][$i]) &&  $f['task_enable_quantities'][$i] === "YES") {
			echo 'checked="checked" ';
		} ?>
		/>
		&nbsp;&nbsp;<?php _e('Details Needed? ', 'pta_volunteer_sus');
		if (!isset($f['task_need_details'][$i])) {
			$f['task_need_details'][$i] = "NO";
		} ?>
		<input type="checkbox" class="details_checkbox" name="task_need_details[<?php echo $i; ?>]" id="task_need_details[<?php echo $i; ?>]" value="YES"
		<?php if (isset($f['task_need_details'][$i]) &&  $f['task_need_details'][$i] === "YES") {
			echo 'checked="checked" ';
		} ?>
		/>
		<span class="pta_toggle">&nbsp;&nbsp;<?php _e('Details Required? ', 'pta_volunteer_sus'); ?>
		<input type="checkbox" class="details_required" name="task_details_required[<?php echo $i; ?>]" id="task_details_required[<?php echo $i; ?>]" value="YES"
		<?php if (isset($f['task_details_required'][$i]) &&  $f['task_details_required'][$i] === "YES") {
			echo 'checked="checked" ';
		} ?>
		/></span>
		<span class="pta_toggle"><br /><?php _e('Details text:','pta_volunteer_sus'); ?> <input type="text" class="details_text" name="task_details_text[<?php echo $i; ?>]" id="task_details_text[<?php echo $i; ?>]" value="<?php echo ((isset($f['task_details_text'][$i]) ? esc_attr($f['task_details_text'][$i]) : __("Item you are bringing", "pta_volunteer_sus" ) )); ?>" size="25" /></span>
	<?php endif;
	do_action('pta_sus_task_form_task_loop_before_li_close', $f, $i); // use this to insert extra task fields ?>
	&nbsp;&nbsp;<input type="hidden" name="task_id[<?php echo $i; ?>]" id="task_id[<?php echo $i; ?>]" value="<?php echo ((isset($f['task_id'][$i]) ? (int)$f['task_id'][$i] : '')); ?>" />
				<a href="#" class="add-task-after">(+)</a>
				<a href="#" class="remove-task">(-)</a>
				</li>
	<?php do_action( 'pta_sus_tasks_form_task_loop_end', $f, $i );
endfor;
do_action( 'pta_sus_tasks_form_after_tasks', $f ); ?>
</ul>
<?php wp_nonce_field('pta_sus_add_tasks','pta_sus_add_tasks_nonce'); ?>
<hr />
<p class="submit">
<input type="hidden" name="sheet_id" value="<?php echo (int)$f["sheet_id"]; ?>" />
<input type="hidden" name="sheet_title" value="<?php echo $f["sheet_title"]; ?>" />
<input type="hidden" name="sheet_type" value="<?php echo $f["sheet_type"]; ?>" />
<input type="hidden" name="sheet_no_signups" value="<?php echo (int)$f["sheet_no_signups"]; ?>" />
<input type="hidden" name="tasks_mode" value="submitted" />
<input type="submit" name="Submit" class="button-primary" value="<?php _e("Save", "pta_volunteer_sus"); ?>" />
</p>
</form>
<?php
// tasks move
$rows = $this->data->get_sheets(false, false, true);
if (count($rows)>1) : ?>
	<h2><?php _e('Move tasks ', 'pta_volunteer_sus'); ?></h2>
	<form name="move_tasks" id="pta-sus-move-tasks" method="post" action="">
	<?php wp_nonce_field('pta_sus_move_tasks','pta_sus_move_tasks_nonce'); ?>
	<input type="hidden" name="sheet_id" value="<?php echo (int)$f["sheet_id"]; ?>" />
	<input type="hidden" name="tasks_mode" value="move_tasks" />
	<label for="new_sheet_id"><?php _e('Move all tasks of this sheet to sheet', 'pta_volunteer_sus'); ?></label>
	<select id="new_sheet_id" name="new_sheet_id" required>
        <option value=""><?php _e("Please Select a Sheet", "pta_volunteer_sus"); ?></option>
	<?php foreach ($rows as $row) :
		if ($row->id == $f["sheet_id"]) continue; ?>
		<option value=<?php echo esc_attr($row->id); ?>><?php echo esc_html($row->title); ?></option>
	<?php endforeach; ?>
	</select>
	<input type="submit" name="Submit" class="button-primary" value="<?php _e("Move", "pta_volunteer_sus"); ?>" />
	</form>
<?php endif;