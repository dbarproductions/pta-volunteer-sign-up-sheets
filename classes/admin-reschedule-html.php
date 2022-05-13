<?php
	/**
     * @var int $sheet_id
     * @var object $sheet
     * @var PTA_SUS_Admin $this
     * @var array $tasks
     * @var bool $success
	 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
$return = add_query_arg(array('action' => false, 'sheet_id' => false, '_sus_nonce' => false ));
if($success) {
    ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e( 'Reschedule/Copy was processed successfully.', 'pta-volunteer-sign-up-sheets' ); ?></p>
    </div>
    <p class="submit">
        <span class="pta-sus admin return-link"><a class="button-secondary" href="<?php echo esc_url($return); ?>"><?php _e('RETURN', 'pta-volunteer-sign-up-sheets'); ?></a></span>
    </p>
    <?php
    return;
}
?>
<p><strong><?php _e('Use this form to reschedule, or copy, a sheet to the new date, or dates, specified.', 'pta-volunteer-sign-up-sheets'); ?></strong></p>
<p><strong><?php _e("If you choose to Reschedule, only the dates get changed on the original sheet, tasks, and signups (if you don't clear the signups).", 'pta-volunteer-sign-up-sheets'); ?></strong></p>
<p><strong><?php _e("If you choose to Copy, a new sheet will be created with new tasks and new signups (if you don't clear the signups), and the old sheet will remain along with its tasks and signups.", 'pta-volunteer-sign-up-sheets'); ?></strong></p>
<p><strong><?php _e("Multi-Copy works like Copy, except that you specify the number of days to add to each date, and the number of copies to make. Signups will also be copied to each new date unless you check the box to clear signups.", 'pta-volunteer-sign-up-sheets'); ?></strong></p>
<hr/>
<form id="pta-reschedule-sheet-form" method="post" action="">
    <table id="pta-sheet-reschedule" class="pta-reschedule-table widefat">
        <tr>
            <th><label for="method"><?php _e('Method', 'pta-volunteer-sign-up-sheets'); ?></label></th>
            <td>
                <select id="method" name="method">
                    <option value="reschedule"><?php _e('Reschedule', 'pta-volunteer-sign-up-sheets'); ?></option>
                    <option value="copy"><?php _e('Copy', 'pta-volunteer-sign-up-sheets'); ?></option>
                    <option value="multi-copy"><?php _e('Multi-Copy', 'pta-volunteer-sign-up-sheets'); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="clear_signups"><?php _e('Clear Signups?', 'pta-volunteer-sign-up-sheets'); ?></label></th>
            <td>
                <input type="checkbox" value="yes" id="clear_signups" name="clear_signups"><strong><?php _e('Yes', 'pta-volunteer-sign-up-sheets' ); ?></strong>&nbsp;-&nbsp;<em><?php _e('Checking this will clear/delete signups on the original sheet when you choose Reschedule. If you choose copy, the signups will NOT be copied to the new Sheet', 'pta-volunteer-sign-up-sheets' ); ?></em>
            </td>
        </tr>
        <tr>
            <th><label for="send_emails"><?php _e('Send Emails?', 'pta-volunteer-sign-up-sheets'); ?></label></th>
            <td>
                <input type="checkbox" value="yes" id="send_emails" name="send_emails"><strong><?php _e('Yes', 'pta-volunteer-sign-up-sheets' ); ?></strong>&nbsp;-&nbsp;<em><?php _e('Checking this will send the Rescheduled email to all signup emails. NOT Recommended if using Multi-Copy as it will generate emails for every copy.', 'pta-volunteer-sign-up-sheets' ); ?></em>
            </td>
        </tr>
        <?php if ("Single" == $sheet->type): ?>
            <tr class="pta-hide-if-multi">
                <th><label for="new_date"><?php _e('Select New Sheet Date:', 'pta-volunteer-sign-up-sheets'); ?><span style="color: red">*</span></label></th>
                <td>
                    <input type="text" class="singlePicker" id="new_date" name="new_date" value="" size="12" required />
                    <em><?php _e('Select a new date for the sheet.  All tasks will then be assigned to this date.', 'pta-volunteer-sign-up-sheets'); ?></em>
                </td>
            </tr>
        <?php endif; ?>
        <tr class="pta-show-if-multi" id="pta-interval-row">
            <th><label for="interval"><?php _e('Offset Interval in Days:', 'pta-volunteer-sign-up-sheets'); ?><span style="color: red">*</span></label></th>
            <td>
                <input class="pta-multi-input" id="interval" name="interval" type="number" min="1" />
                <em><?php _e('Each copy will be offset this number of days from the previous date.', 'pta-volunteer-sign-up-sheets'); ?></em>
            </td>
        </tr>
        <tr class="pta-show-if-multi" id="pta-interval-row">
            <th><label for="copies"><?php _e('Number of Copies:', 'pta-volunteer-sign-up-sheets'); ?><span style="color: red">*</span></label></th>
            <td>
                <input class="pta-multi-input" id="copies" name="copies" type="number" min="1" />
                <em><?php _e('How many copies to make. Setting this too high may cause a server timeout, depending on how many tasks and signups need to be copied.', 'pta-volunteer-sign-up-sheets'); ?></em>
            </td>
        </tr>
    </table>
    <?php if ("Single" == $sheet->type): ?>
        <h3><?php _e('Select new times for each Task (optional)', 'pta-volunteer-sign-up-sheets'); ?></h3>
    <?php elseif ( "Multi-Day" == $sheet->type): ?>
        <h3 class="pta-hide-if-multi"><?php _e('Select new Dates (required) and times (optional) for each Task', 'pta-volunteer-sign-up-sheets'); ?></h3>
        <h3 class="pta-show-if-multi"><?php _e('Select new times (optional) for each Task', 'pta-volunteer-sign-up-sheets'); ?></h3>
    <?php endif; ?>
    <h4><?php _e('Leave times blank to keep original times', 'pta-volunteer-sign-up-sheets'); ?></h4>
    <table class="pta-reschedule-table widefat">
        <thead>
            <tr>
                <th><?php _e('Task', 'pta-volunteer-sign-up-sheets'); ?></th>
                <?php if ( "Multi-Day" == $sheet->type): ?>
                    <th class="pta-hide-if-multi"><?php _e('New Date', 'pta-volunteer-sign-up-sheets'); ?><span style="color: red">*</span></th>
                <?php endif; ?>
                <th><?php _e('New Start Time', 'pta-volunteer-sign-up-sheets'); ?></th>
                <th><?php _e('New End Time', 'pta-volunteer-sign-up-sheets'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($tasks as $task): ?>
            <tr>
                <th><?php printf(__('%s on %s', 'pta-volunteer-sign-up-sheets'), esc_html($task->title), esc_html(pta_datetime(get_option('date_format'), strtotime($task->dates)))); ?></th>
                <?php if ( "Multi-Day" == $sheet->type): ?>
                    <td class="pta-hide-if-multi">
                        <input type="text" class="singlePicker" id="new_task_date[<?php echo esc_attr($task->id); ?>]" name="new_task_date[<?php echo esc_attr($task->id); ?>]" value="" size="12" required />
                    </td>
                <?php endif; ?>
                <td>
                    <input type="text" class="pta-timepicker" id="new_task_start_time[<?php echo esc_attr($task->id); ?>]" name="new_task_start_time[<?php echo esc_attr($task->id); ?>]" value="" size="12" />
                </td>
                <td>
                    <input type="text" class="pta-timepicker" id="new_task_end_time[<?php echo esc_attr($task->id); ?>]" name="new_task_end_time[<?php echo esc_attr($task->id); ?>]" value="" size="12" />
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p class="submit">
        <input type="hidden" name="sheet_id" value="<?php echo (int)($sheet_id); ?>" />
        <input type="hidden" name="pta_admin_reschedule_form_mode" value="submitted" />
        <input type="submit" name="Submit" class="button-primary" value="<?php _e('RESCHEDULE/COPY', 'pta-volunteer-sign-up-sheets'); ?>" />
        <span class="pta-sus admin return-link"><a class="button-secondary" href="<?php echo esc_url($return); ?>"><?php _e('CANCEL AND RETURN', 'pta-volunteer-sign-up-sheets'); ?></a></span>
    </p>
    <?php wp_nonce_field('pta_sus_admin_reschedule','pta_sus_admin_reschedule_nonce', true, true); ?>
</form>