<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
$validation_options = get_option( 'pta_volunteer_sus_validation_options' );
$header = $validation_options['validation_form_header'] ?? '<p>'.__('To view and manage your signups you must either login or fill out the form below to receive a validation link via email.','pta-volunteer-sign-up-sheets').'</p>';
$firstname_label = apply_filters( 'pta_sus_public_output', __('First Name', 'pta-volunteer-sign-up-sheets'), 'firstname_label' );
$lastname_label = apply_filters( 'pta_sus_public_output', __('Last Name', 'pta-volunteer-sign-up-sheets'), 'lastname_label' );
$email_label = apply_filters( 'pta_sus_public_output', __('E-mail', 'pta-volunteer-sign-up-sheets'), 'email_label' );
?>
<div class="pta-sus validation-form-wrapper">
    <?php echo wp_kses_post($header); ?>
    <?php PTA_SUS_Messages::show_messages(true); ?>
    <form id="pta-sus-validation-form" action="" method="post" enctype="multipart/form-data">
        <table class="pta-sus waitlist-validation-form">
            <tr>
                <th><label for="firstname"><?php echo esc_html($firstname_label); ?></label></th>
                <td><input type="text" name="firstname" id="firstname" value="" required size="50" /></td>
            </tr>
            <tr>
                <th><label for="lastname"><?php echo esc_html($lastname_label); ?></label></th>
                <td><input type="text" name="lastname" id="lastname" value="" required size="50" /></td>
            </tr>
            <tr>
                <th><label for="email"><?php echo esc_html($email_label); ?></label></th>
                <td><input type="email" name="email" id="email" value="" required size="50" /></td>
            </tr>
        </table>
        <?php do_action( 'pta_sus_validation_form_before_submit_button' ); ?>
        <p class="submit">
            <?php $value =  apply_filters( 'pta_sus_public_output', __('Send Validation Link', 'pta-volunteer-sign-up-sheets'), 'validation_form_submit_button_label' ); ?>
            <input name="pta-sus-validate-form-submit" class="button-primary pta-sus-validate-button" type="submit" value="<?php echo esc_attr( $value ); ?>" />
	        <?php wp_nonce_field( 'pta-sus-validate-form', 'pta-sus-validate-form-nonce' ); ?>
        </p>
    </form>
</div>