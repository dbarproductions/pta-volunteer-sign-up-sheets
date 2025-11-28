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
			'live_search'           => true,
			'get_tasks_for_sheet'   => false,
		);

		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_pta_sus_' . $ajax_event, array( __CLASS__, $ajax_event ) );

			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_pta_sus_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

    /**
     * AJAX handler for live search - validates security and calls search method
     */
    public static function live_search() {
        // Verify action and capability
        if (!isset($_POST['pta_pub_action']) || $_POST['pta_pub_action'] !== 'autocomplete_volunteer' || !current_user_can('manage_signup_sheets')) {
            wp_send_json_error(array('message' => __('Unauthorized request.', 'pta-volunteer-sign-up-sheets')));
            return;
        }

        check_ajax_referer('ajax-pta-nonce', 'security');

        // Perform the actual search
        $results = self::perform_live_search();
        wp_send_json_success($results);
    }

    /**
     * Perform the live search logic (without security checks)
     * This method can be called by other plugins/extensions after they've done their own security validation
     * 
     * @return array Array of search results
     */
    public static function perform_live_search() {
        $main_options = get_option('pta_volunteer_sus_main_options');
        $return = array();

        // Check if search is enabled and query exists
        if (empty($main_options['enable_signup_search']) || !isset($_POST['q'])) {
            return $return;
        }

        $search = sanitize_text_field($_POST['q']);

        // Don't search if query is too short (optional - prevents excessive queries)
        if (strlen($search) < 2) {
            return $return;
        }

        $tables = $main_options['signup_search_tables'] ?? 'both';
        $user_ids = array();

        // Search signups table
        if ('signups' === $tables || 'both' === $tables) {
            $results = PTA_SUS_Signup_Functions::search_signups_by_name($search);

            foreach ($results as $signup) {
                // Fix: Properly track user_ids to avoid duplicates
                $user_id = absint($signup->user_id);
                if ($user_id > 0 && !in_array($user_id, $user_ids)) {
                    $user_ids[] = $user_id;
                }

                $record = array();
                $record['user_id'] = $user_id;
                $record['lastname'] = esc_html($signup->lastname);
                $record['firstname'] = esc_html($signup->firstname);
                $record['email'] = esc_html($signup->email);
                $record['phone'] = esc_html($signup->phone);
                $record = apply_filters('pta_sus_signups_table_search_record', $record, $signup);
                $return[] = $record;
            }
        }

        // Search WordPress users table
        if ('users' === $tables || 'both' === $tables) {
            $users = pta_sus_search_users($search);

            foreach ($users as $user) {
                // Skip if already found in signups
                if (in_array($user->ID, $user_ids)) {
                    continue;
                }

                $record = array();
                $record['user_id'] = absint($user->ID);
                $record['lastname'] = esc_html(get_user_meta($user->ID, 'last_name', true));
                $record['firstname'] = esc_html(get_user_meta($user->ID, 'first_name', true));
                $record['email'] = esc_html($user->user_email);
                $record['phone'] = esc_html(get_user_meta($user->ID, 'billing_phone', true));

                // Get additional user meta
                $record = apply_filters('pta_sus_users_table_search_record', $record, $user);
                $return[] = $record;
            }
        }

        return $return;
    }

	public static function get_tasks_for_sheet() {
		check_ajax_referer( 'ajax-pta-nonce', 'security' );
		$response = array('success' => false, 'message' => __('There was a problem getting tasks for the selected event','pta-volunteer-sign-up-sheets'), 'tasks' => array(), 'dates' => array(), 'admin' => false);
		$sheet_id = isset($_POST['sheet_id']) ? absint($_POST['sheet_id']) : 0;
		$old_task_id = isset($_POST['old_task_id']) ? absint($_POST['old_task_id']) : 0;
		$old_signup_id = isset($_POST['old_signup_id']) ? absint($_POST['old_signup_id']) : 0;
		$qty = isset($_POST['qty']) ? absint( $_POST['qty']) : 1;
		global $pta_sus;
		$sheet = pta_sus_get_sheet($sheet_id);
		if($sheet) {

			$sheet_tasks = PTA_SUS_Task_Functions::get_tasks($sheet_id);
			$available_task_ids = array();


			if(!empty($sheet_tasks)) {
				$tasks = array();
				foreach ($sheet_tasks as $sheet_task) {
					if($old_task_id == $sheet_task->id) {
						// if task IDs are the same, don't use if not recurring type sheet (can't move to same task unless it has more than 1 date)
		                $sheet = pta_sus_get_sheet($sheet_task->sheet_id);
		                if($sheet && 'Recurring' !== $sheet->type) {
		                    continue; // skip so can't select same task
		                }
					}
					$display = $sheet_task->title;
					$time = $location = '';
					// Maybe add a start time, if is set
					if(!empty($sheet_task->time_start)) {
						$time = sprintf(__(' - starting at: %s', 'pta-volunteer-sign-up-sheets'), pta_datetime( get_option('time_format'), strtotime($sheet_task->time_start)));
					}

					$task_dates = pta_sus_sanitize_dates($sheet_task->dates);
					$check_date = false;
					if($old_task_id == $sheet_task->id && count($task_dates) > 1) {
						$old_signup = pta_sus_get_signup($old_signup_id);
						if($old_signup) {
							$check_date = $old_signup->date;
						}
					}

		            foreach ($task_dates as $date) {
		            	if($check_date && $date == $check_date) {
		            		// can't move to same date
		            		continue;
			            }
						// Check available qty
			            $available = $sheet_task->get_available_spots($date);
						if(!$available || $qty > $available) {
							continue; // not enough left
						}
	                    if('0000-00-00' === $date) {
		                    $date_display = __(' - Ongoing','pta-volunteer-sign-up-sheets');
			            } else {
	                        $date_display = sprintf(__('on %s','pta-volunteer-sign-up-sheets') ,pta_datetime(get_option('date_format'), strtotime($date)));
		                }
	                    $key = $sheet_task->id . '|'.$date;
	                    $tasks[$key] = $display . ' '.$date_display . $time . $location;
		            }
				}
				$response = array('success' => true, 'message' => '', 'tasks' => $tasks);
			}
		}
		wp_send_json( $response);
	}

}

PTA_SUS_AJAX::init();
