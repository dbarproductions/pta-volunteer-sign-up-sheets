<?php
/**
 * Task Edit Modal
 * 
 * This modal contains all task fields for editing
 * Loaded via AJAX or included directly
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// This will be populated via JavaScript/AJAX
$task_id = 0;
$task_data = array();
// Get sheet info from global data or passed parameters
$sheet_id = isset($f['sheet_id']) ? (int)$f['sheet_id'] : (isset($_GET['sheet_id']) ? (int)$_GET['sheet_id'] : 0);
$sheet_type = isset($f['sheet_type']) ? $f['sheet_type'] : (isset($_GET['sheet_type']) ? sanitize_text_field($_GET['sheet_type']) : '');
$no_signups = isset($f['sheet_no_signups']) ? absint($f['sheet_no_signups']) : (isset($_GET['no_signups']) ? absint($_GET['no_signups']) : 0);
?>

<div id="pta-sus-task-modal" style="display: none;">
	<div class="pta-sus-task-modal-content">
		<form id="pta-sus-task-form" method="post">
			<?php wp_nonce_field('pta_sus_save_task', 'pta_sus_task_nonce'); ?>
			<input type="hidden" id="task_id" name="task_id" value="0" />
			<input type="hidden" id="task_sheet_id" name="task_sheet_id" value="<?php echo $sheet_id; ?>" />
			<input type="hidden" id="task_sheet_type" name="task_sheet_type" value="<?php echo esc_attr($sheet_type); ?>" />
			<input type="hidden" id="task_no_signups" name="task_no_signups" value="<?php echo $no_signups; ?>" />
			
			<div class="pta-sus-task-modal-header">
				<h2 id="pta-sus-task-modal-title"><?php _e('Add New Task', 'pta-volunteer-sign-up-sheets'); ?></h2>
			</div>
			
			<div class="pta-sus-task-modal-body">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="task_title"><?php _e('Task/Item:', 'pta-volunteer-sign-up-sheets'); ?> <span class="required">*</span></label>
						</th>
						<td>
							<input type="text" id="task_title" name="task_title" value="" size="40" required />
						</td>
					</tr>
					
					<?php if ( "Multi-Day" === $sheet_type ) : ?>
					<tr>
						<th scope="row">
							<label for="task_dates"><?php _e('Date:', 'pta-volunteer-sign-up-sheets'); ?> <span class="required">*</span></label>
						</th>
						<td>
							<input type="text" class="singlePicker" id="task_dates" name="task_dates" value="" size="10" />
						</td>
					</tr>
					<?php endif; ?>
					
					<?php if (!$no_signups) : ?>
					<tr>
						<th scope="row">
							<label for="task_qty"><?php _e('# Needed:', 'pta-volunteer-sign-up-sheets'); ?></label>
						</th>
						<td>
							<input type="number" id="task_qty" name="task_qty" value="1" min="1" style="width: 4.5em;" />
						</td>
					</tr>
					<?php endif; ?>
					
					<tr>
						<th scope="row">
							<label for="task_time_start"><?php _e('Start Time:', 'pta-volunteer-sign-up-sheets'); ?></label>
						</th>
						<td>
							<input type="text" class="pta-timepicker" id="task_time_start" name="task_time_start" value="" size="10" />
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="task_time_end"><?php _e('End Time:', 'pta-volunteer-sign-up-sheets'); ?></label>
						</th>
						<td>
							<input type="text" class="pta-timepicker" id="task_time_end" name="task_time_end" value="" size="10" />
						</td>
					</tr>
					
					<?php do_action('pta_sus_task_form_task_loop_after_times', array(), 0); ?>
					
					<tr>
						<th scope="row">
							<label for="task_description"><?php _e('Task Description:', 'pta-volunteer-sign-up-sheets'); ?></label>
						</th>
						<td>
							<?php
							// Always use Quill editor for task descriptions (used on public side for displaying task lists)
							?>
							<div id="task_description-quill-container" class="pta-quill-container" style="min-height: 200px;"></div>
							<input type="hidden" name="task_description" id="task_description" value="" data-quill-field="true" />
							<p class="description"><?php _e('Optional task description. HTML is allowed.', 'pta-volunteer-sign-up-sheets'); ?></p>
						</td>
					</tr>
					
					<?php if (!$no_signups) : ?>
					<tr>
						<th scope="row"><?php _e('Task Options:', 'pta-volunteer-sign-up-sheets'); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" id="task_allow_duplicates" name="task_allow_duplicates" value="YES" />
									<?php _e('Allow Duplicates?', 'pta-volunteer-sign-up-sheets'); ?>
								</label><br/>
								<label>
									<input type="checkbox" id="task_enable_quantities" name="task_enable_quantities" value="YES" />
									<?php _e('Enable Quantities?', 'pta-volunteer-sign-up-sheets'); ?>
								</label><br/>
								<label>
									<input type="checkbox" class="details_checkbox" id="task_need_details" name="task_need_details" value="YES" />
									<?php _e('Details Needed?', 'pta-volunteer-sign-up-sheets'); ?>
								</label>
							</fieldset>
						</td>
					</tr>
					
					<tr class="pta_toggle" style="display: none;">
						<th scope="row"><?php _e('Details Options:', 'pta-volunteer-sign-up-sheets'); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" class="details_required" id="task_details_required" name="task_details_required" value="YES" />
									<?php _e('Details Required?', 'pta-volunteer-sign-up-sheets'); ?>
								</label><br/>
								<label>
									<?php _e('Details text:', 'pta-volunteer-sign-up-sheets'); ?>
									<input type="text" class="details_text" id="task_details_text" name="task_details_text" value="<?php echo esc_attr(__("Item you are bringing", "pta_volunteer_sus")); ?>" size="25" />
								</label>
							</fieldset>
						</td>
					</tr>
					<?php endif; ?>
					
					<?php
					// Email Template Options
					$available_templates = PTA_SUS_Email_Functions::get_available_templates(true);
					$email_types = PTA_SUS_Email_Functions::get_email_types();
					
					// Get sheet to check its template assignments
					$sheet = pta_sus_get_sheet($sheet_id);
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
					?>
					<tr>
						<th scope="row">
							<label>
								<a href="#" class="task_email_templates_trigger" id="email_templates_trigger_modal"><?php _e('Email Template Options', 'pta-volunteer-sign-up-sheets'); ?></a>
							</label>
						</th>
						<td>
							<div class="pta_sus_task_email_templates" id="task_email_templates_modal" style="display:none;">
								<p><em><?php _e('Select email templates for this task. Leave as "Use Sheet Email Template" to use the template assigned to the sheet (or system default if no sheet template is set). You can also select a specific template to override the sheet setting.', 'pta-volunteer-sign-up-sheets'); ?></em></p>
								<?php foreach ($email_types as $email_type => $email_type_label) : 
									// Skip validation email types as they're system-wide only
									if ('user_validation' === $email_type || 'signup_validation' === $email_type) continue;
									
									$property_name = $email_type . '_email_template_id';
								?>
									<p>
										<label for="task_<?php echo esc_attr($property_name); ?>">
											<strong><?php echo esc_html($email_type_label); ?>:</strong>
										</label>
										<select name="task_<?php echo esc_attr($property_name); ?>" id="task_<?php echo esc_attr($property_name); ?>">
											<option value="0">
												<?php _e('Use Sheet Email Template', 'pta-volunteer-sign-up-sheets'); ?>
											</option>
											<?php foreach ($available_templates as $template) : ?>
												<option value="<?php echo $template->id; ?>">
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
						</td>
					</tr>
					
					<?php do_action('pta_sus_task_form_task_loop_before_li_close', array(), 0); ?>
				</table>
			</div>
			
			<div class="pta-sus-task-modal-footer">
				<button type="button" id="pta-sus-task-save" class="button button-primary">
					<?php _e('Save & Close', 'pta-volunteer-sign-up-sheets'); ?>
				</button>
				<button type="button" id="pta-sus-task-cancel" class="button">
					<?php _e('Cancel', 'pta-volunteer-sign-up-sheets'); ?>
				</button>
			</div>
		</form>
	</div>
</div>

