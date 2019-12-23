<?php
/**
 * @var bool $success
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$view_url = sprintf('?page=%s&action=view_signup&sheet_id=%s', $_REQUEST['page'], $_REQUEST['sheet_id']);
$nonced_view_url = wp_nonce_url( $view_url, 'view_signup', '_sus_nonce' );
if($success) {
	?>
<div class="pta-sus admin return-link"><a class="button-primary" href="<?php echo esc_url($nonced_view_url); ?>"><?php _e('RETURN TO SIGNUPS LIST', 'pta_volunteer_sus'); ?></a></div>
	<?php
}
$signup_id = isset($_REQUEST['signup_id']) ? absint($_REQUEST['signup_id']) : 0;
$edit = false;
if($signup_id > 0) {
	$signup=$this->data->get_signup($signup_id);
	if(empty($signup)) {
		echo '<div class="error"><p>'.__('Invalid Signup', 'pta_volunteer_sus').'</p></div>';
		return;
	}
	$edit = true;
}
$add_edit_header = $edit ? __('EDIT SIGNUP', 'pta_volunteer_sus') :  __('ADD NEW SIGNUP', 'pta_volunteer_sus');
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
	echo '<div class="error"><p>'.__('Invalid Data', 'pta_volunteer_sus').'</p></div>';
	return;
}
$task = apply_filters( 'pta_sus_admin_signup_get_task', $this->data->get_task($task_id), $task_id);
do_action( 'pta_sus_admin_before_signup_form', $task, $date );

if ("0000-00-00" == $date) {
	$show_date = false;
} else {
	$show_date = date_i18n(get_option('date_format'), strtotime($date));
}
?>
<h3 class="pta-sus admin sign-up-header"><?php printf(__('TASK: %s', 'pta_volunteer_sus'), esc_html($task->title) ); ?></h3>
<?php
if ($show_date) {
    ?><div class="pta-sus admin signup_date"><?php
	printf(__('DATE: %s', 'pta_volunteer_sus'), $show_date);
	?></div><?php
}
if (!empty($task->time_start)) { ?>
<span class="pta-sus admin time_start"><?php printf(__('TIME START: %s', 'pta_volunteer_sus'), esc_html(date_i18n(get_option("time_format"), strtotime($task->time_start))) ); ?></span><br/>
<?php
}
if (!empty($task->time_end)) { ?>
<span class="pta-sus admin time_end"><?php printf(__('TIME END: %s', 'pta_volunteer_sus'), esc_html(date_i18n(get_option("time_format"), strtotime($task->time_end))) ); ?></span><br/>
<?php
}
$signup_fields = apply_filters('pta_sus_admin_signup_fields', array(
    'user_id' => __('User', 'pta_volunteer_sus'),
    'firstname' => __('First Name', 'pta_volunteer_sus'),
    'lastname' => __('Last Name', 'pta_volunteer_sus'),
    'email' => __('E-mail', 'pta_volunteer_sus'),
    'phone' => __('Phone', 'pta_volunteer_sus'),
    'item' => $task->details_text,
    'item_qty' => __('Item QTY: ', 'pta_volunteer_sus')
), $task, $date);
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
if(empty($saved_values)) {
	// check for posted, in case of form error and need to edit/resubmit
	foreach ($signup_fields as $key => $label) {
		$saved_values[$key] = isset($posted[$key]) ? wp_kses_post($posted[$key]) : '';
	}
}
$loading_img = PTA_VOLUNTEER_SUS_URL.'assets/images/loading.gif';
?>
<form name="pta_sus_admin_signup_form" id="pta_sus_admin_signup_form" method="post" action="">
    <table class="pta-sus admin widefat">
    <?php foreach($signup_fields as $key => $label): ?>
    <?php switch($key) {
            case 'firstname':
		    case 'lastname':
		    case 'email':
		    case 'phone':
		    case 'item': ?>
                <tr>
                    <th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                    <td><input type="text" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($saved_values[$key]); ?>" /></td>
                </tr>
            <?php
		     break;
            case 'user_id':
                ?><tr><th><label for="user_id"><?php _e('Assign to WP User ', 'pta_volunteer_sus'); ?></label></th><td><?php
	            $args = array(
		            'show_option_none'        => __('None', 'pta_volunteer_sus'),
		            'option_none_value'       => 0,
		            'show'                    => 'display_name_with_login',
		            'selected'                => $saved_values[$key],
		            'name'                    => 'user_id',
		            'class'                   => 'pta-user-select',
		            'id'                      => 'user_id',
		            'include_selected'        => true,
	            );
	            wp_dropdown_users($args);
                ?><div id="loadingDiv" class="pta_loading"><img src="<?php echo esc_url($loading_img); ?>" alt="loading"></div></td></tr><?php
	            break;
            case 'item_qty':
	            $available = $this->data->get_available_qty($task_id, $date, $task->qty);
	            if($edit) {
	                // add back in the signup qty so can edit up to max available
                    $available += absint( $signup->item_qty);
                }
	            if ($task->enable_quantities == "YES") { ?>
		            <tr>
                        <th><label for="item_qty"><?php echo esc_html( sprintf(__('Item QTY (1 - %d): ', 'pta_volunteer_sus'), (int)$available) ); ?></label></th>
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
    </table>
    <p class="submit">
	    <input type="hidden" name="signup_id" value="<?php echo (int)($signup_id); ?>" />
        <input type="hidden" name="date" value="<?php echo esc_attr($date); ?>" />
        <input type="hidden" name="task_id" value="<?php echo esc_attr($task_id); ?>" />
        <input type="hidden" name="pta_admin_signup_form_mode" value="submitted" />
        <input type="submit" name="Submit" class="button-primary" value="<?php _e('SAVE', 'pta_volunteer_sus'); ?>" />
        <span class="pta-sus admin return-link"><a class="button-secondary" href="<?php echo esc_url($nonced_view_url); ?>"><?php _e('CANCEL AND RETURN TO SIGNUPS LIST', 'pta_volunteer_sus'); ?></a></span>
    </p>
    <?php wp_nonce_field('pta_sus_admin_signup','pta_sus_admin_signup_nonce', true, true); ?>
</form>
<?php