<?php
/**
 * Created by PhpStorm.
 * User: Stephen
 * Date: 9/21/2016
 * Time: 5:01 PM
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
$sheets = $this->data->get_all_sheet_ids_and_titles();
$user = wp_get_current_user();
$name = $user->user_firstname . ' ' . $user->user_lastname;
$selected = isset($_POST['sheet_select']) ? absint($_POST['sheet_select']) : 0;
$from_name = isset($_POST['from_name']) ? sanitize_text_field($_POST['from_name']) : $name;
$reply = isset($_POST['reply_to']) ? sanitize_text_field($_POST['reply_to']) : $user->user_email;
$subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
$mail_message = isset($_POST['message']) ? wp_kses_post($_POST['message']) : '';
$checked = isset($_POST['individually']) ? absint($_POST['individually']) : 0;
?>
<div class="wrap pta_sus">
	<h2><?php _e('Email Volunteers', 'pta_volunteer_sus'); ?></h2>
	<?php echo $messages; ?>
	<p><?php _e('Compose and send a message to all volunteers currently in the sign-ups database table, or select a specific sheet to send an email to all volunteers signed up for that specific sheet.','pta_volunteer_sus'); ?></p>
	<form id="email_volunteers" method="post" action="">
		<table class="form-table">
			<tr>
				<th><label for="sheet_select"><?php _e('Recipients', 'pta_volunteer_sus'); ?></label></th>
				<td>
					<select name="sheet_select">
						<option value="0"><?php _e('All Sheets', 'pta_volunteer_sus'); ?></option>
						<?php foreach ($sheets as $id => $title): ?>
						<option value="<?php echo absint($id); ?>" <?php selected($selected, $id); ?> ><?php echo esc_html($title); ?></option>
						<?php endforeach; ?>
					</select>
					<br/><em><?php _e('Select a sheet to email all volunteers currently signed up for that sheet, or select "All Sheets" to email ALL volunteers currently signed up for ANY sheet.', 'pta_volunteer_sus'); ?></em>
				</td>
			</tr>
			<tr>
				<th><label for="from_name"><?php _e('From Name:', 'pta_volunteer_sus'); ?></label></th>
				<td>
					<input type="text" name="from_name" value="<?php echo esc_attr($from_name); ?>" size="35" />
				</td>
			</tr>
			<tr>
				<th><label for="reply_to"><?php _e('Reply To:', 'pta_volunteer_sus'); ?></label></th>
				<td>
					<input type="text" name="reply_to" value="<?php echo esc_attr($reply); ?>" size="35" />
					<br/><em><?php _e('Reply to email address. Should be your own email address, unless you want them to reply to another email.', 'pta_volunteer_sus'); ?></em>
				</td>
			</tr>
			<tr>
				<th><label for="subject"><?php _e('Subject:', 'pta_volunteer_sus'); ?></label></th>
				<td>
					<input type="text" name="subject" value="<?php echo esc_attr($subject); ?>" size="60" />
				</td>
			</tr>
			<tr>
				<th><label for="message"><?php _e('Message:', 'pta_volunteer_sus'); ?></label></th>
				<td>
					<textarea name="message" rows="20" cols="100" ><?php echo $mail_message; ?></textarea>
				</td>
			</tr>
			<tr>
				<th><label for="individually"><?php _e('Send Individual Emails?', 'pta_volunteer_sus'); ?></label></th>
				<td>
					<input type="checkbox" name="individually" value="1" <?php checked($checked, 1); ?> /><?php _e('YES. ', 'pta_volunteer_sus'); ?>&nbsp;
					<em><?php _e('Check this to send a single individual email to each volunteer (along with a copy to yourself). Leave un-checked to send one email to yourself with all volunteers added as BCC recipients. If you are not using a SMTP mailer plugin, and some volunteers are not receiving the emails due to your server not liking the formatting of the BCC headers, then check this box so emails are sent one at a time.', 'pta_volunteer_sus'); ?></em>
				</td>
			</tr>
		</table>
		<?php wp_nonce_field('pta_sus_email_volunteers','pta_sus_email_volunteers_nonce'); ?>
		<p class="submit">
			<input type="hidden" name="email_volunteers_mode" value="submitted" />
			<input type="hidden" name="user_email" value="<?php echo esc_attr($user->user_email); ?>" />
			<input type="submit" name="Submit" class="button-primary" value="<?php _e('SEND Email', 'pta_volunteer_sus'); ?>" />&nbsp;&nbsp;
			<?php echo sprintf(__('A copy will also be sent to your registered email address: %s', 'pta_volunteer_sus'), esc_html($user->user_email)); ?>
		</p>
	</form>
</div>