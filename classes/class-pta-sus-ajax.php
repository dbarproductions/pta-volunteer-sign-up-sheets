<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * PTA_SUS_AJAXPTA_SUS_AJAX
 *
 * AJAX Event Handler
 *
 * @class 		PTA_SUS_AJAX
 */
class PTA_SUS_AJAX {

	/**
	 * Hook in methods
	 */
	public static function init() {

		// pta_sus_EVENT => nopriv
		$ajax_events = array(
			'live_search'         => true,
		);

		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_pta_sus_' . $ajax_event, array( __CLASS__, $ajax_event ) );

			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_pta_sus_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	public static function live_search() {
		if (isset($_POST['pta_pub_action']) && $_POST['pta_pub_action']=='autocomplete_volunteer' && current_user_can('manage_signup_sheets')) {
			check_ajax_referer( 'ajax-pta-nonce', 'security' );
			$main_options = get_option( 'pta_volunteer_sus_main_options' );
			$return=array();
			if (!$main_options['enable_signup_search'] || !isset($_POST["q"])) {
			  wp_send_json($return);
			  exit;
			}

			$tables = $main_options['signup_search_tables'];

			$user_ids = array();
			global $pta_sus;
			$search = sanitize_text_field( $_POST["q"]);
			if('signups' === $tables || 'both' === $tables) {
				$results = $pta_sus->data->get_signups2($search);
				foreach($results as $item) {
					$record = array();
					$record['user_id'] = $user_ids[]  = absint($item->user_id);
					$record['lastname']  = esc_html($item->lastname);
					$record['firstname'] = esc_html($item->firstname);
					$record['email']     = esc_html($item->email);
					$record['phone']     = esc_html($item->phone);
					$record = apply_filters('pta_sus_signups_table_search_record', $record);
					$return[]  = $record;
				}
			}

			if('users' === $tables || 'both' === $tables) {
				$users = $pta_sus->data->get_users($search);
				foreach($users as $user) {
					if(!in_array($user->ID, $user_ids)) {
						$record = array();
						$record['user_id'] = absint($user->ID);
						$record['lastname']  = esc_html(get_user_meta($user->ID, 'last_name', true));
						$record['firstname'] = esc_html(get_user_meta($user->ID, 'first_name', true));
						$record['email']     = esc_html($user->user_email);
						$record['phone']     = esc_html(get_user_meta($user->ID, 'billing_phone', true));
						// get additional user meta
						$record = apply_filters('pta_sus_users_table_search_record', $record);
						$return[]  = $record;
					}
				}
			}

			wp_send_json($return);
			exit;
		}
    }
	


}

PTA_SUS_AJAX::init();
