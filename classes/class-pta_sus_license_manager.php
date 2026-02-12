<?php
/**
 * Centralized License Manager
 *
 * Manages license registration, updater setup, license form rendering,
 * and license form processing for the main plugin and all extensions.
 *
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PTA_SUS_License_Manager {

	/**
	 * Registry of all registered extensions.
	 *
	 * @var array
	 */
	private static $registered = array();

	/**
	 * Register an extension with the license manager.
	 *
	 * @param array $args {
	 *     Required registration arguments.
	 *
	 *     @type string $slug           Unique slug for the extension.
	 *     @type string $name           Display name of the extension.
	 *     @type string $version        Current version number.
	 *     @type int    $item_id        EDD item ID on the license server.
	 *     @type string $file           Full path to the extension's main plugin file.
	 *     @type string $license_key    Option name for the stored license key.
	 *     @type string $license_status Option name for the stored license status.
	 * }
	 */
	public static function register( $args ) {
		$required = array( 'slug', 'name', 'version', 'item_id', 'file', 'license_key', 'license_status' );
		foreach ( $required as $key ) {
			if ( empty( $args[ $key ] ) ) {
				return;
			}
		}
		self::$registered[ $args['slug'] ] = $args;
	}

	/**
	 * Get all registered extensions.
	 *
	 * @return array
	 */
	public static function get_all_registered() {
		return self::$registered;
	}

	/**
	 * Set up plugin updaters for all registered extensions.
	 *
	 * Hooked to admin_init.
	 */
	public static function setup_updaters() {
		$doing_cron = defined( 'DOING_CRON' ) && DOING_CRON;
		if ( ! current_user_can( 'manage_options' ) && ! $doing_cron ) {
			return;
		}

		foreach ( self::$registered as $ext ) {
			$license_key = trim( get_option( $ext['license_key'], '' ) );
			new PTA_Plugin_Updater( SS_PLUGINS_URL, $ext['file'], array(
				'version' => $ext['version'],
				'license' => $license_key,
				'item_id' => $ext['item_id'],
				'author'  => 'Stephen Sherrard',
				'beta'    => false,
			) );
		}
	}

	/**
	 * Render the license form HTML for a single registered extension.
	 *
	 * @param string $slug The extension slug.
	 */
	public static function render_license_form( $slug ) {
		if ( ! isset( self::$registered[ $slug ] ) ) {
			return;
		}

		$ext     = self::$registered[ $slug ];
		$license = get_option( $ext['license_key'] );
		$status  = get_option( $ext['license_status'] );

		// Build unique field names from the slug
		$nonce_action = 'pta_sus_license_' . $slug;
		$nonce_name   = 'pta_sus_license_nonce_' . $slug;
		$key_field    = 'pta_sus_license_key_' . $slug;
		$activate_field   = 'pta_sus_activate_mode_' . $slug;
		$deactivate_field = 'pta_sus_deactivate_mode_' . $slug;
		$delete_field     = 'pta_sus_delete_mode_' . $slug;
		$save_field       = 'pta_sus_license_save_mode_' . $slug;
		?>
		<div class="pta-sus-license-card" style="background:#fff;border:1px solid #ccd0d4;padding:15px 20px;margin-bottom:15px;">
			<h3 style="margin-top:0;"><?php echo esc_html( $ext['name'] ); ?></h3>
			<form method="post" action="">
				<table class="form-table">
					<tbody>
					<tr valign="top">
						<th scope="row" valign="top">
							<?php esc_html_e( 'License Key', 'pta-volunteer-sign-up-sheets' ); ?>
						</th>
						<td>
							<input id="<?php echo esc_attr( $key_field ); ?>"
							       name="<?php echo esc_attr( $key_field ); ?>"
							       type="text" class="regular-text"
							       value="<?php echo esc_attr( $license ); ?>" />
						</td>
					</tr>
					<?php if ( false !== $license ) : ?>
						<tr valign="top">
							<th scope="row" valign="top">
								<?php esc_html_e( 'Status', 'pta-volunteer-sign-up-sheets' ); ?>
							</th>
							<td>
								<?php if ( 'valid' === $status ) : ?>
									<span style="color:green;font-weight:bold;"><?php esc_html_e( 'Active', 'pta-volunteer-sign-up-sheets' ); ?></span>
									<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
									<input type="hidden" name="<?php echo esc_attr( $deactivate_field ); ?>" value="deactivated" />
									<input type="hidden" name="pta_sus_license_slug" value="<?php echo esc_attr( $slug ); ?>" />
									<input type="submit" class="button-secondary" value="<?php esc_attr_e( 'Deactivate License', 'pta-volunteer-sign-up-sheets' ); ?>" />
								<?php else : ?>
									<span style="color:red;font-weight:bold;"><?php esc_html_e( 'Inactive', 'pta-volunteer-sign-up-sheets' ); ?></span>
									<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
									<input type="hidden" name="<?php echo esc_attr( $activate_field ); ?>" value="activated" />
									<input type="hidden" name="pta_sus_license_slug" value="<?php echo esc_attr( $slug ); ?>" />
									<input type="submit" class="button-secondary" value="<?php esc_attr_e( 'Activate License', 'pta-volunteer-sign-up-sheets' ); ?>" />
								<?php endif; ?>
							</td>
						</tr>
					<?php endif; ?>
					</tbody>
				</table>
				<?php if ( false === $license ) : ?>
					<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
					<input type="hidden" name="<?php echo esc_attr( $save_field ); ?>" value="submitted" />
					<input type="hidden" name="pta_sus_license_slug" value="<?php echo esc_attr( $slug ); ?>" />
					<p class="submit">
						<input type="submit" class="button-secondary" value="<?php esc_attr_e( 'Save License Key', 'pta-volunteer-sign-up-sheets' ); ?>" />
					</p>
				<?php endif; ?>
			</form>
			<?php if ( false !== $license ) : ?>
				<form method="post" action="" style="margin-top:5px;">
					<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
					<input type="hidden" name="<?php echo esc_attr( $delete_field ); ?>" value="delete" />
					<input type="hidden" name="pta_sus_license_slug" value="<?php echo esc_attr( $slug ); ?>" />
					<input type="submit" class="button-link-delete" value="<?php esc_attr_e( 'Delete Key', 'pta-volunteer-sign-up-sheets' ); ?>"
					       onclick="return confirm('<?php echo esc_js( __( 'This will remove the stored license key and status. You will need to enter a new key to receive updates. Continue?', 'pta-volunteer-sign-up-sheets' ) ); ?>');" />
					<span class="description"><?php esc_html_e( 'Remove the stored key without contacting the license server. Use this if your old key expired and you need to enter a new one.', 'pta-volunteer-sign-up-sheets' ); ?></span>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Process license form submission for a single extension.
	 *
	 * Checks for POST data matching the extension's form fields, validates nonce,
	 * makes API calls, and updates options.
	 *
	 * @param string $slug The extension slug.
	 */
	public static function maybe_process_license( $slug ) {
		if ( ! isset( self::$registered[ $slug ] ) ) {
			return;
		}

		$ext = self::$registered[ $slug ];

		$nonce_action = 'pta_sus_license_' . $slug;
		$nonce_name   = 'pta_sus_license_nonce_' . $slug;
		$key_field    = 'pta_sus_license_key_' . $slug;
		$activate_field   = 'pta_sus_activate_mode_' . $slug;
		$deactivate_field = 'pta_sus_deactivate_mode_' . $slug;
		$delete_field     = 'pta_sus_delete_mode_' . $slug;
		$save_field       = 'pta_sus_license_save_mode_' . $slug;

		$activated   = isset( $_POST[ $activate_field ] ) && 'activated' === $_POST[ $activate_field ];
		$deactivated = isset( $_POST[ $deactivate_field ] ) && 'deactivated' === $_POST[ $deactivate_field ];
		$deleted     = isset( $_POST[ $delete_field ] ) && 'delete' === $_POST[ $delete_field ];
		$saved       = isset( $_POST[ $save_field ] ) && 'submitted' === $_POST[ $save_field ];

		if ( ! $activated && ! $deactivated && ! $deleted && ! $saved ) {
			return;
		}

		if ( ! isset( $_POST[ $nonce_name ] ) || ! wp_verify_nonce( $_POST[ $nonce_name ], $nonce_action ) ) {
			PTA_SUS_Messages::add_error( __( 'Invalid Referrer!', 'pta-volunteer-sign-up-sheets' ) );
			return;
		}

		if ( $deleted ) {
			self::delete_license( $slug );
		} elseif ( $activated || $saved ) {
			self::activate_license( $slug, sanitize_text_field( $_POST[ $key_field ] ) );
		} elseif ( $deactivated ) {
			self::deactivate_license( $slug, sanitize_text_field( $_POST[ $key_field ] ) );
		}
	}

	/**
	 * Activate a license key for an extension.
	 *
	 * @param string $slug    Extension slug.
	 * @param string $license License key to activate.
	 * @return bool True if activation succeeded, false otherwise.
	 */
	private static function activate_license( $slug, $license ) {
		$ext     = self::$registered[ $slug ];
		$license = trim( $license );
		$old     = get_option( $ext['license_key'] );

		if ( $old && $old !== $license ) {
			delete_option( $ext['license_status'] );
		}

		$api_params = array(
			'edd_action' => 'activate_license',
			'license'    => $license,
			'item_id'    => $ext['item_id'],
			'url'        => home_url(),
		);

		$response = wp_remote_post( SS_PLUGINS_URL, array(
			'timeout'   => 15,
			'sslverify' => false,
			'body'      => $api_params,
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$message = is_wp_error( $response )
				? $response->get_error_message()
				: __( 'License Site Communication Error! Please try again in a few minutes.', 'pta-volunteer-sign-up-sheets' );
			PTA_SUS_Messages::add_error( $ext['name'] . ' — ' . $message );
			return false;
		}

		$body         = wp_remote_retrieve_body( $response );
		$license_data = json_decode( $body );

		if ( empty( $license_data ) || ! is_object( $license_data ) ) {
			PTA_SUS_Messages::add_error(
				$ext['name'] . ' — ' . __( 'Unexpected response from the license server.', 'pta-volunteer-sign-up-sheets' )
			);
			return false;
		}

		if ( isset( $license_data->success ) && false === $license_data->success ) {
			$message = self::get_license_error_message( $license_data );
			PTA_SUS_Messages::add_error( $ext['name'] . ' — ' . $message );
			return false;
		}

		if ( ! isset( $license_data->license ) ) {
			PTA_SUS_Messages::add_error(
				$ext['name'] . ' — ' . __( 'Unexpected response from the license server.', 'pta-volunteer-sign-up-sheets' )
			);
			return false;
		}

		update_option( $ext['license_status'], $license_data->license );
		update_option( $ext['license_key'], $license );

		if ( 'valid' === $license_data->license ) {
			PTA_SUS_Messages::add_message(
				/* translators: %s: extension name */
				sprintf( __( '%s — License Activated!', 'pta-volunteer-sign-up-sheets' ), $ext['name'] )
			);
			return true;
		}

		PTA_SUS_Messages::add_error(
			/* translators: %s: extension name */
			sprintf( __( '%s — Not A Valid License!', 'pta-volunteer-sign-up-sheets' ), $ext['name'] )
		);
		return false;
	}

	/**
	 * Deactivate a license key for an extension.
	 *
	 * @param string $slug    Extension slug.
	 * @param string $license License key to deactivate.
	 */
	private static function deactivate_license( $slug, $license ) {
		$ext     = self::$registered[ $slug ];
		$license = trim( $license );

		$api_params = array(
			'edd_action' => 'deactivate_license',
			'license'    => $license,
			'item_id'    => $ext['item_id'],
			'url'        => home_url(),
		);

		$response = wp_remote_post( SS_PLUGINS_URL, array(
			'timeout'   => 15,
			'sslverify' => false,
			'body'      => $api_params,
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$message = is_wp_error( $response )
				? $response->get_error_message()
				: __( 'License Site Communication Error! Please try again in a few minutes.', 'pta-volunteer-sign-up-sheets' );
			PTA_SUS_Messages::add_error( $ext['name'] . ' — ' . $message );
			return;
		}

		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( 'deactivated' === $license_data->license ) {
			delete_option( $ext['license_status'] );
			delete_option( $ext['license_key'] );
			PTA_SUS_Messages::add_message(
				/* translators: %s: extension name */
				sprintf( __( '%s — License Deactivated!', 'pta-volunteer-sign-up-sheets' ), $ext['name'] )
			);
		} elseif ( 'failed' === $license_data->license ) {
			PTA_SUS_Messages::add_error(
				/* translators: %s: extension name */
				sprintf( __( '%s — Deactivation Failed! Please try again in a few minutes.', 'pta-volunteer-sign-up-sheets' ), $ext['name'] )
			);
		}
	}

	/**
	 * Delete a stored license key and status locally.
	 *
	 * Does not contact the license server. Use this when the user needs to
	 * clear an expired or problematic key so they can enter a new one.
	 *
	 * @param string $slug Extension slug.
	 */
	private static function delete_license( $slug ) {
		$ext = self::$registered[ $slug ];
		delete_option( $ext['license_status'] );
		delete_option( $ext['license_key'] );
		PTA_SUS_Messages::add_message(
			/* translators: %s: extension name */
			sprintf( __( '%s — License key deleted.', 'pta-volunteer-sign-up-sheets' ), $ext['name'] )
		);
	}

	/**
	 * Get a human-readable error message from a license API response.
	 *
	 * @param object $license_data Decoded API response.
	 * @return string Error message.
	 */
	private static function get_license_error_message( $license_data ) {
		if ( ! isset( $license_data->error ) ) {
			return __( 'An unknown license error occurred.', 'pta-volunteer-sign-up-sheets' );
		}

		switch ( $license_data->error ) {
			case 'expired':
				return sprintf(
					__( 'Your license key expired on %s.', 'pta-volunteer-sign-up-sheets' ),
					date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
				);
			case 'revoked':
			case 'disabled':
				return __( 'Your license key has been disabled.', 'pta-volunteer-sign-up-sheets' );
			case 'missing':
				return __( 'Invalid license.', 'pta-volunteer-sign-up-sheets' );
			case 'invalid':
			case 'site_inactive':
				return __( 'Your license is not active for this URL.', 'pta-volunteer-sign-up-sheets' );
			case 'item_name_mismatch':
				return __( 'This appears to be an invalid license key for this plugin.', 'pta-volunteer-sign-up-sheets' );
			case 'no_activations_left':
				return __( 'Your license key has reached its activation limit.', 'pta-volunteer-sign-up-sheets' );
			default:
				return sprintf(
					/* translators: %s: error code from the license API */
					__( 'License activation error: %s', 'pta-volunteer-sign-up-sheets' ),
					sanitize_text_field( $license_data->error )
				);
		}
	}

	/**
	 * Try to activate all registered extensions with a single license key.
	 *
	 * Loops through registered extensions that don't have a valid license
	 * and attempts to activate each one. Includes a 1-second delay between
	 * API calls to prevent rate limiting.
	 */
	public static function maybe_activate_all() {
		if ( ! isset( $_POST['pta_sus_activate_all_mode'] ) || 'activate_all' !== $_POST['pta_sus_activate_all_mode'] ) {
			return;
		}

		if ( ! isset( $_POST['pta_sus_activate_all_nonce'] ) || ! wp_verify_nonce( $_POST['pta_sus_activate_all_nonce'], 'pta_sus_activate_all' ) ) {
			PTA_SUS_Messages::add_error( __( 'Invalid Referrer!', 'pta-volunteer-sign-up-sheets' ) );
			return;
		}

		$license = isset( $_POST['pta_sus_activate_all_key'] ) ? sanitize_text_field( $_POST['pta_sus_activate_all_key'] ) : '';
		if ( empty( $license ) ) {
			PTA_SUS_Messages::add_error( __( 'Please enter a license key.', 'pta-volunteer-sign-up-sheets' ) );
			return;
		}

		$first = true;
		foreach ( self::$registered as $slug => $ext ) {
			$status = get_option( $ext['license_status'] );
			if ( 'valid' === $status ) {
				continue;
			}

			if ( ! $first ) {
				sleep( 1 );
			}
			$first = false;

			self::activate_license( $slug, $license );
		}
	}

	/**
	 * Render the centralized Licenses admin page.
	 *
	 * This is the callback for the admin submenu page. Processes forms first,
	 * then outputs the view.
	 */
	public static function render_licenses_page() {
		if ( ! current_user_can( 'manage_others_signup_sheets' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'pta-volunteer-sign-up-sheets' ) );
		}

		// Process "Activate All" first if submitted
		self::maybe_activate_all();

		// Process individual license forms
		if ( isset( $_POST['pta_sus_license_slug'] ) ) {
			$slug = sanitize_text_field( $_POST['pta_sus_license_slug'] );
			self::maybe_process_license( $slug );
		}

		$registered = self::$registered;
		include PTA_VOLUNTEER_SUS_DIR . 'views/html-admin-licenses-page.php';
	}
}
