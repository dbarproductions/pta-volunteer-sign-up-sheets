<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$license 	= get_option( 'pta_vol_sus_license_key' );
$status 	= get_option( 'pta_vol_sus_license_status' );

?>
<hr/>
<h3><?php _e('Plugin License', 'pta-volunteer-sign-up-sheets'); ?></h3>
<?php PTA_SUS_Messages::show_messages(true, 'admin'); ?>
<form method="post" action="">

	<table class="form-table">
		<tbody>
		<tr valign="top">
			<th scope="row" valign="top">
				<?php _e('License Key', 'pta-volunteer-sign-up-sheets'); ?>
			</th>
			<td>
				<input id="pta_vol_sus_license_key" name="pta_vol_sus_license_key" type="text" class="regular-text" value="<?php esc_attr_e( $license ); ?>" />
				<label class="description" for="pta_vol_sus_license_key"><?php _e('Enter your license key', 'pta-volunteer-sign-up-sheets'); ?></label>
			</td>
		</tr>
		<?php if( false !== $license ) { ?>
			<tr valign="top">
				<th scope="row" valign="top">
					<?php _e('Activate License', 'pta-volunteer-sign-up-sheets'); ?>
				</th>
				<td>
					<?php if( $status == 'valid' ) { ?>
						<span style="color:green;"><?php _e('Active', 'pta-volunteer-sign-up-sheets'); ?></span>
						<?php wp_nonce_field( 'pta_vol_sus_license','pta_vol_sus_license_nonce' ); ?>
						<input type="hidden" name="pta_vol_sus_deactivate_mode" value="deactivated" />
						<input type="submit" class="button-secondary" name="pta_sus_license_deactivate" value="<?php _e('Deactivate License', 'pta-volunteer-sign-up-sheets'); ?>"/>
					<?php } else {
						wp_nonce_field( 'pta_vol_sus_license','pta_vol_sus_license_nonce' ); ?>
						<input type="hidden" name="pta_vol_sus_activate_mode" value="activated" />
						<input type="submit" class="button-secondary" name="pta_sus_license_activate" value="<?php _e('Activate License', 'pta-volunteer-sign-up-sheets'); ?>"/>
					<?php } ?>
				</td>
			</tr>
		<?php }  ?>

		</tbody>
	</table>
	<?php if( false === $license) {
		wp_nonce_field( 'pta_vol_sus_license','pta_vol_sus_license_nonce' ); ?>
		<p class="submit">
			<input type="hidden" name="pta_sus_license_save_mode" value="submitted" />
			<input type="submit" class="button-secondary" name="pta_sus_license_save_activate" value="<?php _e('Save License Key', 'pta-volunteer-sign-up-sheets'); ?>"/>
		</p>
	<?php } ?>

</form>