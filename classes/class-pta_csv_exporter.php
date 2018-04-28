<?php
/**
* Exports reports from PTA plugins in CSV format
* This class is shared/used by several PTA plugins
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_CSV_EXPORTER {

	public $main_options;

	public function  __construct() {
		// For exporting data from various admin pages as Excel CSV
		add_action('admin_init', array($this, 'export_csv'));

		$this->main_options = get_option( 'pta_volunteer_sus_main_options');
	}


	/*****************************************************************************************
	 ********************************** EXPORT CSV FUNCTIONS ********************************* 
	 *****************************************************************************************/

	/**
	 * Exports previously prepared report arrays as a CSV Excel file
	 * This little function is called by the admin_init wordpress hook
	 * It checks if an export link was clicked on, validates the Nonce, and then 
	 * echos the export_program function output to create the CSV file
	 * Needs to be hooked to admin_init so that it can write file headers before
	 * Wordpress outputs its own header info
	 * 
	 * @return CSV exports a csv file to the broswer for open/save
	 */
	public function export_csv() {
		$export = isset($_REQUEST['pta-action']) ? $_REQUEST['pta-action'] : '';
		if ( in_array($export, array('export', 'export_transposed','export_all')) ) {
			check_admin_referer('pta-export');
			if (!current_user_can('manage_options') && !current_user_can('manage_pta') && !current_user_can('manage_signup_sheets'))  {
				wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
			}
	        
			// Grab the option name where the report is stored, or use the default location
			$sheet_id = isset($_REQUEST['sheet_id']) ? intval($_REQUEST['sheet_id']) : 0;

			switch($export) {
				case 'export':
					$this->export_report($sheet_id);
					break;
				case 'export_all':
					$this->export_all();
					break;
			}
		}

	}


	/**
	 * This function creates the header info and CSV output for the above function
	 * @param  wordpress option 'pta_reports' Report data stored in the Wordpress Option table
	 * @return headers             header info so browser knows we're sending it a CSV file
	 * @return string   the CSV data to go with the file
	 */
	public function export_report($sheet_id) {

		$data = new PTA_SUS_Data();
		if ( !$sheet_id ) {
			wp_die( __( 'Invalid sheet id!' ) );
		}
		$sheet = $data->get_sheet($sheet_id);
		if ( !$sheet ) {
			wp_die( __( 'Invalid sheet id!' ) );
		}
		
		$show_all_dates = isset($this->main_options['show_dates_csv_export']) && true == $this->main_options['show_dates_csv_export'];

		header("Content-type: text/csv");
		header("Content-Disposition: attachment; filename=report-".date('Ymd-His').".csv");
		header("Pragma: no-cache");
		header("Expires: 0");
		$fp = fopen('php://output', 'w');
		
		$old_tdate="";

		$headers = apply_filters('pta_sus_export_headers', array(
				__("Task Date", 'pta_volunteer_sus'),
				__("Task/Item", 'pta_volunteer_sus'),
				__("Start Time", 'pta_volunteer_sus'),
				__("End Time", 'pta_volunteer_sus'),
				__("Volunteer Name", 'pta_volunteer_sus'),
				__("Volunteer Email", 'pta_volunteer_sus'),
				__("Volunteer Phone", 'pta_volunteer_sus'),
				__("Item Details", 'pta_volunteer_sus'),
				__("Item Qty", 'pta_volunteer_sus')
				), $sheet);
		fputcsv($fp, $headers);
		
		$tasks = $data->get_tasks($sheet_id);
		$all_task_dates = $data->get_all_task_dates((int)$sheet->id);
		foreach ($all_task_dates as $tdate) {
			// check if we want to show expired tasks and Skip any task whose date has already passed
			if ( !$this->main_options['show_expired_tasks']) {
				if ($tdate < date("Y-m-d") && "0000-00-00" != $tdate) continue;
			}

			foreach ($tasks as $task) {
				$task_dates = explode(',', $task->dates);
				if(!in_array($tdate, $task_dates)) continue;

				$i=1;
				$signups = $data->get_signups($task->id, $tdate);
				foreach ($signups AS $signup) {
					if($show_all_dates) {
						$export_tdate = $tdate;
					} else {
						// to make sure the same date is only shown once in CSV
						if ($tdate==$old_tdate) {
							$export_tdate="";
						} else {
							$export_tdate=$tdate;
							$old_tdate=$tdate;
						}
					}
					
					if ('YES' === $task->enable_quantities) {
						$i += $signup->item_qty;
					} else {
						$i++;
					} 
					// Save report row
					$line = apply_filters('pta_sus_csv_export_line_values', array(
							$export_tdate,
							$task->title,
							(("" == $task->time_start) ? __("N/A", 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($task->time_start)) ),
							(("" == $task->time_end) ? __("N/A", 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($task->time_end)) ),
							$signup->firstname.' '.$signup->lastname,
							$signup->email,
							$signup->phone,
							$signup->item,
							$signup->item_qty
					), $signup, $task, $tdate );
					fputcsv($fp, $this->clean_csv($line));
				}
				if(isset($this->main_options['show_remaining_slots_csv_export']) && true == $this->main_options['show_remaining_slots_csv_export']) {
					// Remaining empty spots
					for ($i=$i; $i<=$task->qty; $i++) {
						if($show_all_dates) {
							$export_tdate = $tdate;
						} else {
							// to make sure the same date is only shown once in CSV
							if ($tdate==$old_tdate) {
								$export_tdate="";
							} else {
								$export_tdate=$tdate;
								$old_tdate=$tdate;
							}
						}
						// Save empty signup row in report
						$line = array(
							$export_tdate,
							$task->title,
							(("" == $task->time_start) ? __("N/A", 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($task->time_start)) ),
							(("" == $task->time_end) ? __("N/A", 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($task->time_end)) )
						);
						fputcsv($fp, $this->clean_csv($line));
					}
				}
				
			}

		}

		fclose($fp);
		exit;
	}

	private function export_all() {
		$data = new PTA_SUS_Data();
		$rows = $data->get_all_data();
		if ( !$rows ) {
			wp_die( __( 'Nothing to export!' ) );
		}

		header("Content-type: text/csv");
		header("Content-Disposition: attachment; filename=report-".date('Ymd-His').".csv");
		header("Pragma: no-cache");
		header("Expires: 0");
		$fp = fopen('php://output', 'w');

		$headers = apply_filters('pta_sus_export_all_data_headers', array(
			__("Sheet", 'pta_volunteer_sus'),
			__("Signup Date", 'pta_volunteer_sus'),
			__("Task/Item", 'pta_volunteer_sus'),
			__("Start Time", 'pta_volunteer_sus'),
			__("End Time", 'pta_volunteer_sus'),
			__("Volunteer Name", 'pta_volunteer_sus'),
			__("Volunteer Email", 'pta_volunteer_sus'),
			__("Volunteer Phone", 'pta_volunteer_sus'),
			__("Item Details", 'pta_volunteer_sus'),
			__("Item Qty", 'pta_volunteer_sus')
		));
		fputcsv($fp, $headers);

		$ongoing_label = apply_filters( 'pta_sus_public_output', __('Ongoing', 'pta_volunteer_sus'), 'ongoing_event_type_start_end_label' );

		foreach ($rows as $row) {

			// Save report row
			$line = apply_filters('pta_sus_csv_export_all_data_line_values', array(
				$row->sheet_title,
				(($row->signup_date == '0000-00-00') ? esc_html( $ongoing_label ) : date_i18n(get_option('date_format'), strtotime($row->signup_date))),
				$row->task_title,
				(("" == $row->task_time_start) ? __("N/A", 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($row->task_time_start)) ),
				(("" == $row->task_time_end) ? __("N/A", 'pta_volunteer_sus') : date_i18n(get_option("time_format"), strtotime($row->task_time_end)) ),
				$row->firstname.' '.$row->lastname,
				$row->email,
				$row->phone,
				$row->item,
				$row->item_qty
			), $row);
			fputcsv($fp, $this->clean_csv($line));

		}

		fclose($fp);
		exit;
	}


	/**
	 * Small helper function to get any quotes in proper format
	 * @param  string $value input string to clean
	 * @return string        cleaned value
	 */
	private function clean_csv($line_arr)
	{
		foreach ($line_arr as $key=>$value) {
			// let's clean any html breaks out as well
			$value = str_replace('<br/>', ', ', $value);
			$value = str_replace('<br />', ', ', $value);
			// let's also convert any underscores to spaces
			$value = str_replace('_', ' ', $value);
			$value = str_replace('"', '""', $value);
			// Strip any remaining html tags
			$value = strip_tags($value);
			$line_arr[$key]=$value;
		}
		return $line_arr;
	}

} // End Class

$pta_sus_csv_exporter = new PTA_SUS_CSV_EXPORTER();

/* EOF */
