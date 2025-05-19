<?php
class PTA_SUS_Text_Registry {

	private static $initialized = false;

	public static function init() {
		add_action('pta_sus_customizer_init', array(__CLASS__, 'register_text_filters'));
	}

	public static function setup() {
		// Only hook init once
		if (!self::$initialized) {
			add_action('init', array(__CLASS__, 'init'), 5);
			self::$initialized = true;
		}
	}

	public static function register_text_filters() {
		$filters = array(
			array(
				'id' => 'validation_invalid_code_error',
				'default' => __('Invalid or Expired Code', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'validation',
				'desc' => __('Error message for invalid/expired validation code', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'validation_cleared_message',
				'default' => __('Your validation code has been cleared.', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'validation',
				'desc' => __('Message shown after clearing validation code/cookie.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'user_validation_success_message',
				'default' => __('User Validation successful.', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'validation',
				'desc' => __('Message shown after successful user validation.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'signup_validation_signup_id_error',
				'default' => __('Missing Signup ID.', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'validation',
				'desc' => __('Error message shown for signup validation if the signup ID is missing.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'signup_validation_signup_not_found_error',
				'default' => __('Signup not found.', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'validation',
				'desc' => __('Error message shown for signup validation if the no matching signup found for the signup ID.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'signup_validation_success_message',
				'default' => __('Signup validated.', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'validation',
				'desc' => __('Success message shown for signup validation.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'signup_validation_failed_error',
				'default' => __('Signup validation failed.', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'validation',
				'desc' => __('Error message shown when signup validation fails (database error).', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'signup_validation_info_mismatch_error',
				'default' => __('Signup info does not match validation code info.', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'validation',
				'desc' => __('Error message shown when signup validation info does not match the user info in the database for the validation code.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'validation_form_nonce_error',
				'default' => __('Form Validation Failed!', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'validation',
				'desc' => __('Error message shown when the validation form security nonce is invalid, or has been tampered with.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'validation_email_sent_message',
				'default' => __('Validation email sent.', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'validation',
				'desc' => __('Message shown after validation form submission and validation email has been sent.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'validation_email_send_error',
				'default' => __('Validation email failed to send.', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'validation',
				'desc' => __('Error message shown after validation form submission if the wp_mail function returns false indicating there was an error sending the validation email.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'signup_validation_sent_message',
				'default' => __('Signup Validation email sent.', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'validation',
				'desc' => __('Message shown after a non-validated user signs up when signup validation is enabled, and the signup validation email has been successfully sent.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'signup_validation_send_error_message',
				'default' => __('Signup Validation email failed to send.', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'validation',
				'desc' => __('Error message shown after a non-validated user signs up when signup validation is enabled, but the wp_mail function returns false indicating that there was an error sending the signup validation email.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'validation_form_already_submitted_message',
				'default' => __('User Validation email has been sent. Please check your email. If you did not receive the email, you can return and submit the form again after XXXX minutes.', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'validation',
				'desc' => __('Message shown instead of validation form if the form sent cookie has been set and not expired yet.', 'pta-volunteer-sign-up-sheets'),
				'variable' => __('Number of minutes to wait before submitting the form again.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'user_validation_disabled_message',
				'default' => __('User Validation is currently disabled.', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'validation',
				'desc' => __('Message shown instead of validation form if the validation system is disabled.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'already_validated_message',
				'default' => __('You are already validated.', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'validation',
				'desc' => __('Message shown instead of validation form if the user is already validated.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'clear_invalid_nonce_message',
				'default' => __('Security check failed!', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'user_signups',
				'desc' => __('Error message shown if the security nonce is invalid, or tampered with, when the user clicks on a Clear link.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'clear_not_validated_message',
				'default' => __('You need to be validated first!', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'user_signups',
				'desc' => __('Error message shown if a non-validated user clicks on a clear link.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'clear_not_allowed_error_message',
				'default' => __('You are not allowed to do that!', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'user_signups',
				'desc' => __('Error message shown if a user clicks on a clear link for a signup that is not theirs, or manipulates the URL arguments to try to clear a signup from someone else.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'no_user_signups_message',
				'default' => __('You do not have any current signups', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'user_signups',
				'desc' => __('Message shown in place of the User Signups List if they are not signed up for anything', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'login_link_text',
				'default' => __('Login', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'list',
				'desc' => __('Text for the login link on the main sheets page if login is required to view the sheets and the user is not logged in.', 'pta-volunteer-sign-up-sheets'),
			),
			array(
				'id' => 'sign_up_for_date',
				'default' => __('on XXXX', 'pta-volunteer-sign-up-sheets'),
				'screen' => 'signup',
				'desc' => __('The "on {DATE} part of the display on the signup form after the "You are signing up for..." text.', 'pta-volunteer-sign-up-sheets'),
				'variable' => __('The date of the task/item the user is signing up for.', 'pta-volunteer-sign-up-sheets'),
			),
		);
		foreach($filters as $filter) {
			$desc = $filter['desc'] ?? '';
			$variable = $filter['variable'] ?? null;
			PTA_SUS_Customizer_Text_Registry::register_text($filter['id'], $filter['default'], $filter['screen'], $desc, $variable);
		}
	}
}