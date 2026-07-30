<?php
	/**
     * @var int $sheet_id
     * @var object $sheet
     * @var PTA_SUS_Admin $this
     * @var array $tasks
     * @var array $dates
	 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Note: the $this->success case (form already submitted) is handled by the
// caller (admin_sheet_page()) before this view is ever included, since a
// "Cancel entire Sheet" submission may have deleted $sheet itself.
$return = add_query_arg(array('action' => false, 'sheet_id' => false, '_sus_nonce' => false ));
$is_recurring = ('Recurring' === $sheet->type);
?>
<p><strong><?php _e('Use this form to cancel this entire Sheet, one or more Tasks, or (Recurring sheets only) one or more Dates.', 'pta-volunteer-sign-up-sheets'); ?></strong></p>
<p><strong><?php _e('Cancelling permanently clears/deletes all affected signups. Cancelling Task(s) also permanently deletes those Tasks; cancelling the Sheet also permanently deletes all Tasks and the Sheet itself.', 'pta-volunteer-sign-up-sheets'); ?></strong></p>
<p><em><?php _e('Note: cancelling does not trigger the Waitlist feature to automatically fill the cancelled spot(s).', 'pta-volunteer-sign-up-sheets'); ?></em></p>
<hr/>
<form id="pta-cancel-sheet-form" method="post" action="">
    <table class="pta-reschedule-table widefat">
        <tr>
            <th><label for="cancel_scope"><?php _e('What do you want to cancel?', 'pta-volunteer-sign-up-sheets'); ?></label></th>
            <td>
                <select id="cancel_scope" name="cancel_scope">
                    <option value="sheet"><?php _e('The entire Sheet (event)', 'pta-volunteer-sign-up-sheets'); ?></option>
                    <option value="tasks"><?php _e('One or more Tasks', 'pta-volunteer-sign-up-sheets'); ?></option>
                    <?php if ($is_recurring): ?>
                    <option value="dates"><?php _e('One or more Dates (applies to all Tasks on this Sheet)', 'pta-volunteer-sign-up-sheets'); ?></option>
                    <?php endif; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="send_emails"><?php _e('Send Emails?', 'pta-volunteer-sign-up-sheets'); ?></label></th>
            <td>
                <input type="checkbox" value="yes" id="send_emails" name="send_emails"><strong><?php _e('Yes', 'pta-volunteer-sign-up-sheets' ); ?></strong>&nbsp;-&nbsp;<em><?php _e('Checking this will send a Cancellation email to everyone signed up for the cancelled Sheet/Task(s)/Date(s).', 'pta-volunteer-sign-up-sheets' ); ?></em>
                <p class="description" id="pta-cancel-sync-email-warning" style="display:none;">
                    <?php _e('Note: cancelling a Sheet or Task(s) sends these emails immediately, while the page waits. If this Sheet has a large number of signups (several hundred) and/or the server is slow, this could take a while or time out. (Cancelling by Date instead sends emails in the background via the hourly CRON job, so this does not apply.)', 'pta-volunteer-sign-up-sheets'); ?>
                </p>
            </td>
        </tr>
    </table>

    <div id="pta-cancel-sheet-section" class="pta-cancel-scope-section" style="display:none;">
        <p><strong><?php _e('The entire sheet, all of its tasks, and all signups on it will be permanently deleted.', 'pta-volunteer-sign-up-sheets'); ?></strong></p>
    </div>

    <div id="pta-cancel-tasks-section" class="pta-cancel-scope-section" style="display:none;">
        <h3><?php _e('Select Task(s) to cancel', 'pta-volunteer-sign-up-sheets'); ?></h3>
        <table class="pta-reschedule-table widefat">
            <thead>
                <tr>
                    <th></th>
                    <th><?php _e('Task', 'pta-volunteer-sign-up-sheets'); ?></th>
                    <th><?php _e('Dates', 'pta-volunteer-sign-up-sheets'); ?></th>
                    <th><?php _e('Current Signups', 'pta-volunteer-sign-up-sheets'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($tasks as $task):
                $task_signup_count = count(PTA_SUS_Signup_Functions::get_signups_for_task($task->id));
                $task_dates = $task->get_dates_array();
                $task_dates_display = implode(', ', array_map(function($d) {
                    return pta_datetime(get_option('date_format'), strtotime($d));
                }, $task_dates));
            ?>
                <tr>
                    <td><input type="checkbox" class="pta-cancel-task-cb" name="cancel_task_ids[]" value="<?php echo esc_attr($task->id); ?>" data-signup-count="<?php echo esc_attr($task_signup_count); ?>" /></td>
                    <th><?php echo esc_html($task->title); ?></th>
                    <td><?php echo esc_html($task_dates_display); ?></td>
                    <td><?php echo esc_html($task_signup_count); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description"><?php _e('If you check every Task in this list, use "The entire Sheet (event)" above instead.', 'pta-volunteer-sign-up-sheets'); ?></p>
    </div>

    <?php if ($is_recurring): ?>
    <div id="pta-cancel-dates-section" class="pta-cancel-scope-section" style="display:none;">
        <h3><?php _e('Select Date(s) to cancel', 'pta-volunteer-sign-up-sheets'); ?></h3>
        <table class="pta-reschedule-table widefat">
            <thead>
                <tr>
                    <th></th>
                    <th><?php _e('Date', 'pta-volunteer-sign-up-sheets'); ?></th>
                    <th><?php _e('Current Signups (all Tasks)', 'pta-volunteer-sign-up-sheets'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($dates as $date):
                $date_signup_count = PTA_SUS_Sheet_Functions::get_sheet_signup_count($sheet_id, $date);
            ?>
                <tr>
                    <td><input type="checkbox" class="pta-cancel-date-cb" name="cancel_dates[]" value="<?php echo esc_attr($date); ?>" data-signup-count="<?php echo esc_attr($date_signup_count); ?>" /></td>
                    <th><?php echo esc_html(pta_datetime(get_option('date_format'), strtotime($date))); ?></th>
                    <td><?php echo esc_html($date_signup_count); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description"><?php _e('If you check every Date in this list, use "The entire Sheet (event)" above instead.', 'pta-volunteer-sign-up-sheets'); ?></p>
    </div>
    <?php endif; ?>

    <div id="pta-cancel-summary" class="notice notice-warning" style="display:none; padding: 10px;"></div>

    <p class="submit">
        <input type="hidden" name="sheet_id" value="<?php echo (int)($sheet_id); ?>" />
        <input type="hidden" name="pta_admin_cancel_form_mode" value="submitted" />
        <input type="submit" id="pta-cancel-submit" name="Submit" class="button-primary" value="<?php esc_attr_e('CANCEL', 'pta-volunteer-sign-up-sheets'); ?>" />
        <span class="pta-sus admin return-link"><a class="button-secondary" href="<?php echo esc_url($return); ?>"><?php _e('DO NOT CANCEL - RETURN', 'pta-volunteer-sign-up-sheets'); ?></a></span>
    </p>
    <?php wp_nonce_field('pta_sus_admin_cancel','pta_sus_admin_cancel_nonce', true, true); ?>
</form>
