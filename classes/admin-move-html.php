<?php
	/**
     * @var int $sheet_id
     * @var object $sheet
     * @var int $signup_id
     * @var object $task
     * @var object $signup
     * @var PTA_SUS_Admin $this
	 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$view_url = sprintf('?page=%s&action=view_signup&sheet_id=%s', $_REQUEST['page'], $_REQUEST['sheet_id']);
$return = wp_nonce_url( $view_url, 'view_signup', '_sus_nonce' );
if($this->success) {
    PTA_SUS_Messages::add_message( __( 'Move Signup was processed successfully.', 'pta-volunteer-sign-up-sheets' ));
    PTA_SUS_Messages::show_messages(true, 'admin');
    ?>
    <p class="submit">
        <span class="pta-sus admin return-link"><a class="button-secondary" href="<?php echo esc_url($return); ?>"><?php _e('RETURN', 'pta-volunteer-sign-up-sheets'); ?></a></span>
    </p>
    <?php
    return;
}

$available_sheets = $this->data->get_sheets(false,true);
foreach($available_sheets as $i => $available_sheet) {
    if($available_sheet->no_signups) {
        unset($available_sheets[$i]);
    }
}
?>
<p><strong><?php _e('Use this to move a signup to the selected Sheet, Task, and Date.', 'pta-volunteer-sign-up-sheets'); ?></strong></p>
<h3><?php _e('NOTE!! This function will only change the Task ID and DATE of the existing signup database entry!', 'pta-volunteer-sign-up-sheets'); ?></h3>
<p><?php _e('Item Details will remain the same and might not match with the new Task you move the signup to, and may need to be edited. In addition, if you are using the Custom Fields extension with different signup template fields for the new Sheet and Task you select, those values will be empty and you will need to manually edit the new signup to add them.', 'pta-volunteer-sign-up-sheets'); ?></p>
<hr/>

<div id="pta-move-signup-wrapper">
    <div id="pta-ajax-messages"></div>
    <form name="pta_move_signup" id="pta_move_signup" method="post" action="">
        <h3><?php _e('Select Sheet/Event, Task, and Date to move the signup to.', 'pta-volunteer-sign-up-sheets'); ?></h3>
        <p>
            <label for="pta_sheet_id"><?php _e('Select Sheet/Event', 'pta-volunteer-sign-up-sheets'); ?></label>
            <select name="pta_sheet_id" id="pta_sheet_id" required>
                <?php if(count($available_sheets) > 1) : ?>
                <option value=""><?php _e('Please Select A Sheet/Event.', 'pta-volunteer-sign-up-sheets'); ?></option>
                <?php endif; ?>
                <?php foreach($available_sheets as $sheet): ?>
                    <option value="<?php echo absint($sheet->id); ?>"><?php echo esc_html($sheet->title); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="hidden task_select">
            <label for="pta_task_id"><?php _e('Select A Task.', 'pta-volunteer-sign-up-sheets'); ?></label>
            <select name="pta_task_id" id="pta_task_id" required>
                <option value=""><?php _e('Please Select A Task.', 'pta-volunteer-sign-up-sheets'); ?></option>
            </select>
        </p>
        <div id="ajax-confirm-message"></div>
        <p class="hidden admin_confirm">
            <label for="pta_admin_confirm"><?php _e('Please check to confirm the move.', 'pta-volunteer-sign-up-sheets'); ?></label>
            <input type="checkbox" name="pta_admin_confirm" id="pta_admin_confirm" value="yes" />
        </p>
        <input name="old_signup_id" id="old_signup_id" type="hidden" value="<?php echo esc_attr($signup_id); ?>" />
        <input name="old_task_id" id="old_task_id" type="hidden" value="<?php echo esc_attr($task->id); ?>" />
        <input name="signup_qty" id="signup_qty" type="hidden" value="<?php echo esc_attr($signup->item_qty); ?>" />

        <p class="submit move-signup">
            <input type="hidden" name="pta_admin_move_form_mode" value="submitted" />
            <input type="submit" id="pta-move-signup-submit" name="Submit" class="button-primary" value="<?php _e('MOVE SIGNUP', 'pta-volunteer-sign-up-sheets'); ?>" disabled />
        </p>
        <p><span class="pta-sus admin return-link"><a class="button-secondary" href="<?php echo esc_url($return); ?>"><?php _e('CANCEL AND RETURN', 'pta-volunteer-sign-up-sheets'); ?></a></span></p>
        <?php wp_nonce_field('pta_sus_admin_move','pta_sus_admin_move_nonce', true, true); ?>
    </form>
</div>