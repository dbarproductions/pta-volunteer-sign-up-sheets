<?php
/**
 * Centralized Licenses Admin Page
 *
 * Displays license management for the main plugin and all registered extensions.
 *
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.2.0
 *
 * @var array $registered All registered extensions from PTA_SUS_License_Manager.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Plugin Licenses', 'pta-volunteer-sign-up-sheets' ); ?></h1>

	<?php PTA_SUS_Messages::show_messages( true, 'admin' ); ?>

	<div class="pta-sus-activate-all" style="background:#fff;border:1px solid #ccd0d4;padding:15px 20px;margin:15px 0;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Activate All Extensions', 'pta-volunteer-sign-up-sheets' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'If you have an All Access or bundle license key, enter it here to activate all extensions at once. Extensions that already have a valid license will be skipped.', 'pta-volunteer-sign-up-sheets' ); ?>
		</p>
		<form method="post" action="">
			<?php wp_nonce_field( 'pta_sus_activate_all', 'pta_sus_activate_all_nonce' ); ?>
			<input type="hidden" name="pta_sus_activate_all_mode" value="activate_all" />
			<table class="form-table">
				<tbody>
				<tr valign="top">
					<th scope="row" valign="top">
						<label for="pta_sus_activate_all_key"><?php esc_html_e( 'License Key', 'pta-volunteer-sign-up-sheets' ); ?></label>
					</th>
					<td>
						<input id="pta_sus_activate_all_key" name="pta_sus_activate_all_key"
						       type="text" class="regular-text" value="" />
						<input type="submit" class="button-primary" value="<?php esc_attr_e( 'Activate All Extensions', 'pta-volunteer-sign-up-sheets' ); ?>" />
					</td>
				</tr>
				</tbody>
			</table>
		</form>
	</div>

	<hr />

	<h2><?php esc_html_e( 'Individual Plugin Licenses', 'pta-volunteer-sign-up-sheets' ); ?></h2>

	<?php if ( empty( $registered ) ) : ?>
		<p><?php esc_html_e( 'No plugins are currently registered with the license manager.', 'pta-volunteer-sign-up-sheets' ); ?></p>
	<?php else : ?>
		<?php foreach ( $registered as $slug => $ext ) : ?>
			<?php PTA_SUS_License_Manager::render_license_form( $slug ); ?>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
