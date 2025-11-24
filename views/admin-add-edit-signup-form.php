<?php
/**
 * @var PTA_SUS_Admin $this
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$view_url = sprintf('?page=%s&action=view_signup&sheet_id=%s', $_REQUEST['page'], $_REQUEST['sheet_id']);
$nonced_view_url = wp_nonce_url( $view_url, 'view_signup', '_sus_nonce' );
if($this->success) {
	?>
<div class="pta-sus admin return-link"><a class="button-primary" href="<?php echo esc_url($nonced_view_url); ?>"><?php _e('RETURN TO SIGNUPS LIST', 'pta-volunteer-sign-up-sheets'); ?></a></div>
	<?php
}
$signup_id = isset($_REQUEST['signup_id']) ? absint($_REQUEST['signup_id']) : 0;
$edit = false;
if($signup_id > 0) {
	$signup=pta_sus_get_signup($signup_id);
	if(empty($signup)) {
		PTA_SUS_Messages::add_error(__('Invalid Signup', 'pta-volunteer-sign-up-sheets'));
        PTA_SUS_Messages::show_messages(true, 'admin');
		return;
	}
	$edit = true;
}
$add_edit_header = $edit ? __('EDIT SIGNUP', 'pta-volunteer-sign-up-sheets') :  __('ADD NEW SIGNUP', 'pta-volunteer-sign-up-sheets');
?>
<h2 class="pta-sus admin"><?php echo $add_edit_header; ?></h2>
<?php
if($edit) {
	/**
	 * @var object $signup
	 */
    $task_id = $signup->task_id;
    $date = $signup->date;
} else {
    $task_id = isset($_REQUEST['task_id']) ? absint($_REQUEST['task_id']) : 0;
	$date = isset($_REQUEST['date']) ? sanitize_text_field($_REQUEST['date']) : 0;
}
if(0 === $task_id || 0 === $date) {
    PTA_SUS_Messages::add_error(__('Invalid Data', 'pta-volunteer-sign-up-sheets'));
    PTA_SUS_Messages::show_messages(true, 'admin');
	return;
}
$task = apply_filters( 'pta_sus_admin_signup_get_task', pta_sus_get_task($task_id), $task_id);
do_action( 'pta_sus_admin_before_signup_form', $task, $date );

if ("0000-00-00" === $date) {
	$show_date = false;
} else {
	$show_date = pta_datetime(get_option('date_format'), strtotime($date));
}
?>
<h3 class="pta-sus admin sign-up-header"><?php printf(__('TASK: %s', 'pta-volunteer-sign-up-sheets'), esc_html($task->title) ); ?></h3>
<?php
if ($show_date) {
    ?><div class="pta-sus admin signup_date"><?php
	printf(__('DATE: %s', 'pta-volunteer-sign-up-sheets'), $show_date);
	?></div><?php
}
if (!empty($task->time_start)) { ?>
<span class="pta-sus admin time_start"><?php printf(__('TIME START: %s', 'pta-volunteer-sign-up-sheets'), esc_html(pta_datetime(get_option("time_format"), strtotime($task->time_start))) ); ?></span><br/>
<?php
}
if (!empty($task->time_end)) { ?>
<span class="pta-sus admin time_end"><?php printf(__('TIME END: %s', 'pta-volunteer-sign-up-sheets'), esc_html(pta_datetime(get_option("time_format"), strtotime($task->time_end))) ); ?></span><br/>
<?php
}
$signup_fields =array(
    'user_id' => __('User', 'pta-volunteer-sign-up-sheets'),
    'firstname' => __('First Name', 'pta-volunteer-sign-up-sheets'),
    'lastname' => __('Last Name', 'pta-volunteer-sign-up-sheets'),
    'email' => __('E-mail', 'pta-volunteer-sign-up-sheets')
);
if(true !== $this->main_options['no_phone']) {
	$signup_fields['phone'] = __('Phone', 'pta-volunteer-sign-up-sheets');
}
if('YES' === $task->need_details) {
    $signup_fields['item'] = $task->details_text;
}
if('YES' === $task->enable_quantities) {
	$signup_fields['item_qty'] = __('Item QTY: ', 'pta-volunteer-sign-up-sheets');
}
$signup_fields = apply_filters('pta_sus_admin_signup_fields', $signup_fields, $task, $date);
// Give other plugins a chance to modify signup data
$posted = apply_filters('pta_sus_admin_signup_posted_values', $_POST, $task, $date);
$saved_values = array();
if($edit) {
    foreach ($signup_fields as $key => $label) {
        if(in_array($key, array('user_id','firstname','lastname','email','phone','item','item_qty'))) {
	        $saved_values[$key] = wp_kses_post(stripslashes($signup->$key));
        }
    }
}
// Let other plugins modify or add to the saved values
$saved_values = apply_filters( 'pta_sus_admin_saved_signup_values', $saved_values, $signup_id, $task, $date);
// make sure we have everything set in saved values array, even if it should be empty
foreach ($signup_fields as $key => $label) {
	// get posted if form was submitted, but there were errors
	if(isset($_POST['pta_admin_signup_form_mode']) && 'submitted' === $_POST['pta_admin_signup_form_mode'] && !empty($posted[$key])) {
        $saved_values[$key] = wp_kses_post($posted[$key]);
    } elseif (empty($saved_values[$key])) {
        $saved_values[$key] = '';
    }
}
$loading_img = PTA_VOLUNTEER_SUS_URL.'assets/images/loading.gif';
$required_fields = $this->get_required_signup_fields($task_id);
?>
<form name="pta_sus_admin_signup_form" id="pta_sus_admin_signup_form" method="post" action="">
    <table class="pta-sus admin widefat">
        <?php foreach($signup_fields as $key => $label):
            $required = in_array($key, $required_fields) ? 'required' : '';
            ?>
            <?php switch($key) {
                case 'firstname':
                case 'lastname':
                case 'item': ?>
                    <tr>
                        <th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                        <td><input type="text" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($saved_values[$key]); ?>" <?php echo $required; ?> />
                        <?php
                        if('firstname' === $key) {
                            _e('Start typing in First Name field to live search by first or last name.', 'pta-volunteer-sign-up-sheets');
                        }
                        ?>
                        </td>
                    </tr>
                <?php
                 break;
                case 'email':
                case 'phone': ?>
                    <tr>
                        <th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                        <td><input type="<?php echo esc_attr($key); ?>" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($saved_values[$key]); ?>" <?php echo $required; ?> /></td>
                    </tr>
                <?php
                 break;
                case 'user_id':
                    ?><tr><th><label for="user_id"><?php _e('Assign to WP User ', 'pta-volunteer-sign-up-sheets'); ?></label></th><td><?php
                    $args = array(
                        'show_option_none'        => __('None', 'pta-volunteer-sign-up-sheets'),
                        'option_none_value'       => 0,
                        'show'                    => 'display_name_with_login',
                        'selected'                => $saved_values[$key],
                        'name'                    => 'user_id',
                        'class'                   => 'pta-user-select',
                        'id'                      => 'user_id',
                        'include_selected'        => true,
                    );
                    wp_dropdown_users($args);
                    ?><div id="loadingDiv" class="pta_loading"><img src="<?php echo esc_url($loading_img); ?>" alt="loading"></div>
                    <?php _e('Select by Display Name and Email', 'pta-volunteer-sign-up-sheets'); ?>
                    </td></tr><?php
                    break;
                case 'item_qty':
                    $available = $task->get_available_spots($date);
                    if($edit) {
                        // add back in the signup qty so can edit up to max available
                        $available += absint( $signup->item_qty);
                    }
                    if ($task->enable_quantities === "YES") { ?>
                        <tr>
                            <th><label for="item_qty"><?php echo esc_html( sprintf(__('Item QTY (1 - %d): ', 'pta-volunteer-sign-up-sheets'), (int)$available) ); ?></label></th>
                            <td><input type="number" id="item_qty" name="item_qty" value="<?php echo esc_attr($saved_values[$key]); ?>" min="1" max="<?php echo absint($available); ?>"/></td>
                        </tr>
                        <?php
                    } else { ?>
                        <input type="hidden" name="item_qty" value="1" />
                        <?php
                    }
                    break;
                default:
                    do_action('pta_sus_admin_signup_custom_form_fields', $key, $saved_values, $task, $date);
                    break;
            }
        endforeach; ?>
        <tr>
            <th><?php _e('Send email?', 'pta-volunteer-sign-up-sheets'); ?></th>
            <td><input type="checkbox" value="yes" name="send_email" /><em><?php _e('Check if an email notification should be sent to the user/volunteer.', 'pta-volunteer-sign-up-sheets'); ?></em></td>
        </tr>
    </table>
    <p class="submit">
	    <input type="hidden" name="signup_id" value="<?php echo (int)($signup_id); ?>" />
        <input type="hidden" name="date" value="<?php echo esc_attr($date); ?>" />
        <input type="hidden" name="task_id" value="<?php echo esc_attr($task_id); ?>" />
        <input type="hidden" name="pta_admin_signup_form_mode" value="submitted" />
        <input type="submit" name="Submit" class="button-primary" value="<?php _e('SAVE', 'pta-volunteer-sign-up-sheets'); ?>" />
        <span class="pta-sus admin return-link"><a class="button-secondary" href="<?php echo esc_url($nonced_view_url); ?>"><?php _e('CANCEL AND RETURN TO SIGNUPS LIST', 'pta-volunteer-sign-up-sheets'); ?></a></span>
    </p>
    <?php wp_nonce_field('pta_sus_admin_signup','pta_sus_admin_signup_nonce', true, true); ?>
</form>
<?php
/**
 * EOF
 */