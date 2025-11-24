<?php
class PTA_SUS_Text_Registry {

	private static $initialized = false;

	public static function init() {
		add_action('pta_sus_customizer_init', array(__CLASS__, 'register_text_filters'));
	}

    public static function setup() {
        // Only hook init once
        // Hook to plugins_loaded instead of init to ensure it runs before Customizer's init()
        // which fires on plugins_loaded priority 20
        if (!self::$initialized) {
            add_action('plugins_loaded', array(__CLASS__, 'init'), 10);
            self::$initialized = true;
        }
    }

    public static function register_text_filters() {
        // Store raw strings - they'll be translated when actually used via pta_sus_public_output filter
        // This avoids triggering translation loading too early (before init)
        $filters = array(
            array(
                'id' => 'validation_invalid_code_error',
                'default' => 'Invalid or Expired Code', // No __() - will be translated when used
                'screen' => 'validation',
                'desc' => 'Error message for invalid/expired validation code',
            ),
            array(
                'id' => 'validation_cleared_message',
                'default' => 'Your validation code has been cleared.',
                'screen' => 'validation',
                'desc' => 'Message shown after clearing validation code/cookie.',
            ),
            array(
                'id' => 'user_validation_success_message',
                'default' => 'User Validation successful.',
                'screen' => 'validation',
                'desc' => 'Message shown after successful user validation.',
            ),
            array(
                'id' => 'signup_validation_signup_id_error',
                'default' => 'Missing Signup ID.',
                'screen' => 'validation',
                'desc' => 'Error message shown for signup validation if the signup ID is missing.',
            ),
            array(
                'id' => 'signup_validation_signup_not_found_error',
                'default' => 'Signup not found.',
                'screen' => 'validation',
                'desc' => 'Error message shown for signup validation if the no matching signup found for the signup ID.',
            ),
            array(
                'id' => 'signup_validation_success_message',
                'default' => 'Signup validated.',
                'screen' => 'validation',
                'desc' => 'Success message shown for signup validation.',
            ),
            array(
                'id' => 'signup_validation_failed_error',
                'default' => 'Signup validation failed.',
                'screen' => 'validation',
                'desc' => 'Error message shown when signup validation fails (database error).',
            ),
            array(
                'id' => 'signup_validation_info_mismatch_error',
                'default' => 'Signup info does not match validation code info.',
                'screen' => 'validation',
                'desc' => 'Error message shown when signup validation info does not match the user info in the database for the validation code.',
            ),
            array(
                'id' => 'validation_form_nonce_error',
                'default' => 'Form Validation Failed!',
                'screen' => 'validation',
                'desc' => 'Error message shown when the validation form security nonce is invalid, or has been tampered with.',
            ),
            array(
                'id' => 'validation_email_sent_message',
                'default' => 'Validation email sent.',
                'screen' => 'validation',
                'desc' => 'Message shown after validation form submission and validation email has been sent.',
            ),
            array(
                'id' => 'validation_email_send_error',
                'default' => 'Validation email failed to send.',
                'screen' => 'validation',
                'desc' => 'Error message shown after validation form submission if the wp_mail function returns false indicating there was an error sending the validation email.',
            ),
            array(
                'id' => 'signup_validation_sent_message',
                'default' => 'Signup Validation email sent.',
                'screen' => 'validation',
                'desc' => 'Message shown after a non-validated user signs up when signup validation is enabled, and the signup validation email has been successfully sent.',
            ),
            array(
                'id' => 'signup_validation_send_error_message',
                'default' => 'Signup Validation email failed to send.',
                'screen' => 'validation',
                'desc' => 'Error message shown after a non-validated user signs up when signup validation is enabled, but the wp_mail function returns false indicating that there was an error sending the signup validation email.',
            ),
            array(
                'id' => 'validation_form_already_submitted_message',
                'default' => 'User Validation email has been sent. Please check your email. If you did not receive the email, you can return and submit the form again after XXXX minutes.',
                'screen' => 'validation',
                'desc' => 'Message shown instead of validation form if the form sent cookie has been set and not expired yet.',
                'variable' => 'Number of minutes to wait before submitting the form again.',
            ),
            array(
                'id' => 'user_validation_disabled_message',
                'default' => 'User Validation is currently disabled.',
                'screen' => 'validation',
                'desc' => 'Message shown instead of validation form if the validation system is disabled.',
            ),
            array(
                'id' => 'already_validated_message',
                'default' => 'You are already validated.',
                'screen' => 'validation',
                'desc' => 'Message shown instead of validation form if the user is already validated.',
            ),
            array(
                'id' => 'clear_invalid_nonce_message',
                'default' => 'Security check failed!',
                'screen' => 'user_signups',
                'desc' => 'Error message shown if the security nonce is invalid, or tampered with, when the user clicks on a Clear link.',
            ),
            array(
                'id' => 'clear_not_validated_message',
                'default' => 'You need to be validated first!',
                'screen' => 'user_signups',
                'desc' => 'Error message shown if a non-validated user clicks on a clear link.',
            ),
            array(
                'id' => 'clear_not_allowed_error_message',
                'default' => 'You are not allowed to do that!',
                'screen' => 'user_signups',
                'desc' => 'Error message shown if a user clicks on a clear link for a signup that is not theirs, or manipulates the URL arguments to try to clear a signup from someone else.',
            ),
            array(
                'id' => 'no_user_signups_message',
                'default' => 'You do not have any current signups',
                'screen' => 'user_signups',
                'desc' => 'Message shown in place of the User Signups List if they are not signed up for anything',
            ),
            array(
                'id' => 'login_link_text',
                'default' => 'Login',
                'screen' => 'list',
                'desc' => 'Text for the login link on the main sheets page if login is required to view the sheets and the user is not logged in.',
            ),
            array(
                'id' => 'sign_up_for_date',
                'default' => 'on XXXX',
                'screen' => 'signup',
                'desc' => 'The "on {DATE} part of the display on the signup form after the "You are signing up for..." text.',
                'variable' => 'The date of the task/item the user is signing up for.',
            ),
        );
        foreach($filters as $filter) {
            $desc = $filter['desc'] ?? '';
            $variable = $filter['variable'] ?? null;
            // Pass text domain for main plugin
            PTA_SUS_Customizer_Text_Registry::register_text(
                $filter['id'],
                $filter['default'],
                $filter['screen'],
                $desc,
                $variable,
                'pta-volunteer-sign-up-sheets' // Main plugin text domain
            );
        }
    }
}