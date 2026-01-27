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
<?php if ( "Single" === $f['sheet_type'] ): ?>
	<h2><?php echo __('Select the date for ', 'pta-volunteer-sign-up-sheets'). stripslashes(esc_attr($f['sheet_title'])); ?></h2>
	<p>
		<label for="single_date"><strong><?php _e('Date:', 'pta-volunteer-sign-up-sheets'); ?></strong></label>
		<input type="text" class="singlePicker" id="single_date" name="single_date" value="<?php echo ((isset($f['single_date']) ? esc_attr($f['single_date']) : '')); ?>" size="12" />
		<em><?php _e('Select a date for the event.  All tasks will then be assigned to this date.', 'pta-volunteer-sign-up-sheets'); ?></em>
	</p>
<?php elseif ( "Recurring" === $f['sheet_type']): ?>
	<h2><?php echo __('Select ALL the dates for ', 'pta-volunteer-sign-up-sheets'). stripslashes(esc_attr($f['sheet_title'])); ?></h2>
	<p>
		<label for="recurring_dates"><strong><?php _e('Dates:', 'pta-volunteer-sign-up-sheets'); ?></strong></label>
		<input type="text" id="multi999Picker" name="recurring_dates" value="<?php echo ((isset($f['recurring_dates']) ? esc_attr($f['recurring_dates']) : '')); ?>" size="40" />
		<em><?php _e('Select all the dates for the event. Copies of the tasks will be created for each date.', 'pta-volunteer-sign-up-sheets'); ?></em>
	</p>
<?php endif; ?>

<h2><?php echo __('Tasks for ', 'pta-volunteer-sign-up-sheets'). stripslashes(esc_attr($f['sheet_title'])); ?></h2>
<h3><?php _e('Tasks/Items', 'pta-volunteer-sign-up-sheets'); ?></h3>
<p><em><?php _e('Enter tasks or items below. Drag and drop to change sort order. Times are optional. If you need details for an item or task (such as what dish they are bringing for a lunch) check the Details Needed box.<br/>
		Click on (+) to add additional tasks, or (-) to remove a task.  At least one task/item must be entered.  If # needed is left blank, the value will be set to 1.', 'pta-volunteer-sign-up-sheets'); ?></em></p>
<ul class="tasks">
<?php for ($i = 0; $i < $count; $i++) :
	do_action( 'pta_sus_tasks_form_task_loop_start', $f, $i ); ?>
	<li id="task-<?php echo $i; ?>">
	<?php _e('Task/Item:', 'pta-volunteer-sign-up-sheets'); ?> <input type="text" name="task_title[<?php echo $i; ?>]" id="task_title[<?php echo $i; ?>]" value="<?php echo ((isset($f['task_title'][$i]) ? esc_attr($f['task_title'][$i]) : '')); ?>" size="40" />&nbsp;&nbsp;
	<?php if ( "Multi-Day" === $f['sheet_type'] ) : ?>
		<?php _e('Date:','pta-volunteer-sign-up-sheets'); ?> <input type="text" class="singlePicker" name="task_dates[<?php echo $i; ?>]" id="singlePicker[<?php echo $i; ?>]" value="<?php echo ((isset($f['task_dates'][$i]) ? esc_attr($f['task_dates'][$i]) : '')); ?>" size="10" />&nbsp;&nbsp;
	<?php endif; ?>
	<?php if (!$no_signups) : ?>
		<?php _e('# Needed:','pta-volunteer-sign-up-sheets'); ?> <input type="number" name="task_qty[<?php echo $i; ?>]" id="task_qty[<?php echo $i; ?>]" value="<?php echo((isset($f['task_qty'][$i]) ? (int)$f['task_qty'][$i] : '')); ?>"  min="1" style="width: 4.5em;" />
	<?php endif; ?>
	&nbsp;&nbsp;<?php _e('Start Time:', 'pta-volunteer-sign-up-sheets'); ?> <input type="text" class="pta-timepicker" id="timepicker_start[<?php echo $i; ?>]" name="task_time_start[<?php echo $i; ?>]" value="<?php echo((isset($f['task_time_start'][$i]) ? esc_attr($f['task_time_start'][$i]) : '')); ?>" size="10" />
	&nbsp;&nbsp;<?php _e('End Time:', 'pta-volunteer-sign-up-sheets'); ?> <input type="text" class="pta-timepicker" id="timepicker_end[<?php echo $i; ?>]" name="task_time_end[<?php echo $i; ?>]" value="<?php echo((isset($f['task_time_end'][$i]) ? esc_attr($f['task_time_end'][$i]) : '')); ?>" size="10" />

	<?php do_action('pta_sus_task_form_task_loop_after_times', $f, $i); ?>

	<a href="#" class="task_description_trigger"  id="description_trigger_<?php echo $i; ?>"><?php _e('Add/Edit Task Description', 'pta-volunteer-sign-up-sheets'); ?></a>
    <div class="pta_sus_task_description" id="task_description_<?php echo $i; ?>">
	    <?php
	    $content = isset($f['task_description'][$i]) ? wp_kses_post($f['task_description'][$i]) : '';
	    ?>
        <p>
            <label for="task_description[<?php echo $i; ?>]"><?php _e('Optional Task Description (HTML allowed):', 'pta-volunteer-sign-up-sheets'); ?></label><br/>
	        <textarea name="task_description[<?php echo $i; ?>]" id="task_description[<?php echo $i; ?>]" rows="4" cols="150"><?php echo $content; ?></textarea>
        </p>
    </div>

	<?php
	// Get available templates for dropdowns
	$available_templates = PTA_SUS_Email_Functions::get_available_templates(true);
	$email_types = PTA_SUS_Email_Functions::get_email_types();
	
	// Get sheet to check its template assignments (for "Use Sheet Template" option)
	$sheet = pta_sus_get_sheet($f['sheet_id']);
	$sheet_template_ids = array();
	if ($sheet) {
		$sheet_template_ids = array(
			'confirmation' => isset($sheet->confirmation_email_template_id) ? absint($sheet->confirmation_email_template_id) : 0,
			'reminder1' => isset($sheet->reminder1_email_template_id) ? absint($sheet->reminder1_email_template_id) : 0,
			'reminder2' => isset($sheet->reminder2_email_template_id) ? absint($sheet->reminder2_email_template_id) : 0,
			'clear' => isset($sheet->clear_email_template_id) ? absint($sheet->clear_email_template_id) : 0,
			'reschedule' => isset($sheet->reschedule_email_template_id) ? absint($sheet->reschedule_email_template_id) : 0,
		);
	}
	
	// Get current task template IDs (if editing)
	$task_template_ids = array();
	$task_template_ids['confirmation'] = isset($f['task_confirmation_email_template_id'][$i]) ? absint($f['task_confirmation_email_template_id'][$i]) : 0;
	$task_template_ids['reminder1'] = isset($f['task_reminder1_email_template_id'][$i]) ? absint($f['task_reminder1_email_template_id'][$i]) : 0;
	$task_template_ids['reminder2'] = isset($f['task_reminder2_email_template_id'][$i]) ? absint($f['task_reminder2_email_template_id'][$i]) : 0;
	$task_template_ids['clear'] = isset($f['task_clear_email_template_id'][$i]) ? absint($f['task_clear_email_template_id'][$i]) : 0;
	$task_template_ids['reschedule'] = isset($f['task_reschedule_email_template_id'][$i]) ? absint($f['task_reschedule_email_template_id'][$i]) : 0;
	?>
	<br/><a href="#" class="task_email_templates_trigger" id="email_templates_trigger_<?php echo $i; ?>"><?php _e('Email Template Options', 'pta-volunteer-sign-up-sheets'); ?></a>
	<div class="pta_sus_task_email_templates" id="task_email_templates_<?php echo $i; ?>" style="display:none;">
		<p><em><?php _e('Select email templates for this task. Leave as "Use Sheet Template" to use the template assigned to the sheet, or "Use System Default" if no sheet template is set.', 'pta-volunteer-sign-up-sheets'); ?></em></p>
		<?php foreach ($email_types as $email_type => $email_type_label) : 
			// Skip validation email types as they're system-wide only, not task/sheet level
			if ('user_validation' === $email_type || 'signup_validation' === $email_type) continue;
			
			$property_name = $email_type . '_email_template_id';
			$current_template_id = isset($task_template_ids[$email_type]) ? $task_template_ids[$email_type] : 0;
			$sheet_template_id = isset($sheet_template_ids[$email_type]) ? $sheet_template_ids[$email_type] : 0;
		?>
			<p>
				<label for="task_<?php echo esc_attr($property_name); ?>[<?php echo $i; ?>]">
					<strong><?php echo esc_html($email_type_label); ?>:</strong>
				</label>
				<select name="task_<?php echo esc_attr($property_name); ?>[<?php echo $i; ?>]" id="task_<?php echo esc_attr($property_name); ?>[<?php echo $i; ?>]">
					<option value="0" <?php selected($current_template_id, 0); ?>>
						<?php 
						if ($sheet_template_id > 0) {
							_e('Use Sheet Template', 'pta-volunteer-sign-up-sheets');
						} else {
							_e('Use System Default', 'pta-volunteer-sign-up-sheets');
						}
						?>
					</option>
					<?php foreach ($available_templates as $template) : ?>
						<option value="<?php echo $template->id; ?>" <?php selected($current_template_id, $template->id); ?>>
							<?php echo esc_html($template->title); ?>
							<?php if ($template->is_system_default()) : ?>
								<?php echo ' ' . __('(System Default)', 'pta-volunteer-sign-up-sheets'); ?>
							<?php endif; ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php endforeach; ?>
	</div>

	<?php if(!$no_signups) : ?>
		<br/><?php _e('Allow Duplicates? ', 'pta-volunteer-sign-up-sheets');
		if (!isset($f['task_allow_duplicates'][$i])) {
			$f['task_allow_duplicates'][$i] = "NO";
		} ?>
		<input type="checkbox" name="task_allow_duplicates[<?php echo $i; ?>]" id="task_allow_duplicates[<?php echo $i; ?>]" value="YES"
		<?php if (isset($f['task_allow_duplicates'][$i]) &&  $f['task_allow_duplicates'][$i] === "YES") {
			echo 'checked="checked" ';
		} ?>
		/>
		&nbsp;&nbsp;<?php _e('Enable Quantities? ', 'pta-volunteer-sign-up-sheets');
		if (!isset($f['task_enable_quantities'][$i])) {
			$f['task_enable_quantities'][$i] = "NO";
		} ?>
		<input type="checkbox" name="task_enable_quantities[<?php echo $i; ?>]" id="task_enable_quantities[<?php echo $i; ?>]" value="YES"
		<?php if (isset($f['task_enable_quantities'][$i]) &&  $f['task_enable_quantities'][$i] === "YES") {
			echo 'checked="checked" ';
		} ?>
		/>
		&nbsp;&nbsp;<?php _e('Details Needed? ', 'pta-volunteer-sign-up-sheets');
		if (!isset($f['task_need_details'][$i])) {
			$f['task_need_details'][$i] = "NO";
		} ?>
		<input type="checkbox" class="details_checkbox" name="task_need_details[<?php echo $i; ?>]" id="task_need_details[<?php echo $i; ?>]" value="YES"
		<?php if (isset($f['task_need_details'][$i]) &&  $f['task_need_details'][$i] === "YES") {
			echo 'checked="checked" ';
		} ?>
		/>
		<span class="pta_toggle">&nbsp;&nbsp;<?php _e('Details Required? ', 'pta-volunteer-sign-up-sheets'); ?>
		<input type="checkbox" class="details_required" name="task_details_required[<?php echo $i; ?>]" id="task_details_required[<?php echo $i; ?>]" value="YES"
		<?php if (isset($f['task_details_required'][$i]) &&  $f['task_details_required'][$i] === "YES") {
			echo 'checked="checked" ';
		} ?>
		/></span>
		<span class="pta_toggle"><br /><?php _e('Details text:','pta-volunteer-sign-up-sheets'); ?> <input type="text" class="details_text" name="task_details_text[<?php echo $i; ?>]" id="task_details_text[<?php echo $i; ?>]" value="<?php echo ((isset($f['task_details_text'][$i]) ? esc_attr($f['task_details_text'][$i]) : __("Item you are bringing", "pta_volunteer_sus" ) )); ?>" size="25" /></span>
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
	<h2><?php _e('Move tasks ', 'pta-volunteer-sign-up-sheets'); ?></h2>
	<form name="move_tasks" id="pta-sus-move-tasks" method="post" action="">
	<?php wp_nonce_field('pta_sus_move_tasks','pta_sus_move_tasks_nonce'); ?>
	<input type="hidden" name="sheet_id" value="<?php echo (int)$f["sheet_id"]; ?>" />
	<input type="hidden" name="tasks_mode" value="move_tasks" />
	<label for="new_sheet_id"><?php _e('Move all tasks of this sheet to sheet', 'pta-volunteer-sign-up-sheets'); ?></label>
	<select id="new_sheet_id" name="new_sheet_id" required>
        <option value=""><?php _e("Please Select a Sheet", "pta_volunteer_sus"); ?></option>
	<?php foreach ($rows as $row) :
		if ($row->id === $f["sheet_id"]) continue; ?>
		<option value=<?php echo esc_attr($row->id); ?>><?php echo esc_html($row->title); ?></option>
	<?php endforeach; ?>
	</select>
	<input type="submit" name="Submit" class="button-primary" value="<?php _e("Move", "pta_volunteer_sus"); ?>" />
	</form>
<?php endif;
do_action( 'pta_sus_tasks_form_bottom', $f ); ?>