<?php
/**
* Public pages
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Public {

	private $data;
    private $plugin_path;
    private $all_sheets_uri;
    public $main_options;
    public $email_options;
    public $integration_options;
    public $member_directory_active;
    public $submitted;
    public $err;
    public $success;
    public $errors;
    public $messages;
    private $cleared;
    private $messages_displayed = false;
	private $errors_displayed = false;
    private $task_item_header;
    private $start_time_header;
    private $end_time_header;
    private $item_details_header;
    private $item_qty_header;
    private $na_text;
	private $title_header;
	private $start_date_header;
	private $end_date_header;
	private $open_spots_header;
	private $no_contact_message;
	private $contact_label;
	private $show_hidden = false;
	private $signup_displayed = false;
	private $suppress_duplicates;
	private $use_divs = false;
	private $hidden;
	private $date;
	private $date_header;
	private $show_time;
	private $phone_required;
	private $show_full_name = false;
	private $show_phone = false;
	private $shortcode_id = false;
	private $show_headers = true;
	private $show_date_start = true;
	private $show_date_end = true;
	private $no_global_overlap = false;
    
    public function __construct() {
        $this->data = new PTA_SUS_Data();
        
        $this->plugin_path = dirname(__FILE__).'/';

        $this->all_sheets_uri = add_query_arg(array('sheet_id' => false, 'date' => false, 'signup_id' => false, 'task_id' => false));

	    $this->main_options = get_option( 'pta_volunteer_sus_main_options' );
	    $this->email_options = get_option( 'pta_volunteer_sus_email_options' );
	    $this->integration_options = get_option( 'pta_volunteer_sus_integration_options' );

        add_shortcode('pta_sign_up_sheet', array($this, 'display_sheet'));
	    add_shortcode('pta_user_signups', array($this, 'process_user_signups_shortcode'));
        
        add_action('wp_enqueue_scripts', array($this, 'add_css_and_js_to_frontend'));

        add_action('wp_loaded', array($this, 'ajax_actions'));
        add_action('wp_loaded', array($this, 'process_signup_form'));
        add_action('wp_loaded', array($this, 'set_up_filters'));
        
        $this->phone_required = isset($this->main_options['phone_required']) ? $this->main_options['phone_required'] : true;
	    $this->use_divs = isset($this->main_options['use_divs']) ? $this->main_options['use_divs'] : false;
	    $this->show_full_name = isset($this->main_options['show_full_name']) ? $this->main_options['show_full_name'] : false;
	    $this->suppress_duplicates = isset($this->main_options['suppress_duplicates']) ? $this->main_options['suppress_duplicates'] : true;
	    $this->no_global_overlap = isset($this->main_options['no_global_overlap']) ? $this->main_options['no_global_overlap'] : false;

        
    } // Construct

    public function ajax_actions() {
		if (isset($_GET['pta_pub_action']) && $_GET['pta_pub_action']=='autocomplete_volunteer' && current_user_can('manage_signup_sheets')) {

			$return=array();
			if (!$this->main_options['enable_signup_search'] || !isset($_GET["q"])) {
			  echo json_encode($return);
			  return;
			}

			$tables = $this->main_options['signup_search_tables'];

			$user_ids = array();

			if('signups' === $tables || 'both' === $tables) {
				$results = $this->data->get_signups2($_GET["q"]);
				foreach($results as $item) {
					$record = array();
					$record['user_id'] = $user_ids[]  = absint($item->user_id);
					$record['lastname']  = esc_html($item->lastname);
					$record['firstname'] = esc_html($item->firstname);
					$record['email']     = esc_html($item->email);
					$record['phone']     = esc_html($item->phone);
					$return[]  = $record;
				}
			}

			if('users' === $tables || 'both' === $tables) {
				$users = $this->data->get_users($_GET["q"]);
				foreach($users as $user) {
					if(!in_array($user->ID, $user_ids)) {
						$record = array();
						$record['user_id'] = absint($user->ID);
						$record['lastname']  = esc_html(get_user_meta($user->ID, 'last_name', true));
						$record['firstname'] = esc_html(get_user_meta($user->ID, 'first_name', true));
						$record['email']     = esc_html($user->user_email);
						$record['phone']     = esc_html(get_user_meta($user->ID, 'billing_phone', true));
						$return[]  = $record;
					}
				}
			}

			echo json_encode($return);
			exit;
		}
    }

    public function set_up_filters() {
        // Set up some public output strings used by multiple functions
        $this->task_item_header = apply_filters( 'pta_sus_public_output', __('Task/Item', 'pta_volunteer_sus'), 'task_item_header' );
        $this->start_time_header = apply_filters( 'pta_sus_public_output', __('Start Time', 'pta_volunteer_sus'), 'start_time_header' );
        $this->end_time_header = apply_filters( 'pta_sus_public_output', __('End Time', 'pta_volunteer_sus'), 'end_time_header' );
        $this->item_details_header = apply_filters( 'pta_sus_public_output', __('Item Details', 'pta_volunteer_sus'), 'item_details_header' );
        $this->item_qty_header = apply_filters( 'pta_sus_public_output', __('Item Qty', 'pta_volunteer_sus'), 'item_qty_header' );
        $this->na_text = apply_filters( 'pta_sus_public_output', __('N/A', 'pta_volunteer_sus'), 'not_applicable_text' );
	    
	    $this->title_header = apply_filters( 'pta_sus_public_output', __('Title', 'pta_volunteer_sus'), 'title_header' );
	    $this->start_date_header = apply_filters( 'pta_sus_public_output', __('Start Date', 'pta_volunteer_sus'), 'start_date_header' );
	    $this->end_date_header = apply_filters( 'pta_sus_public_output', __('End Date', 'pta_volunteer_sus'), 'end_date_header' );
	    $this->open_spots_header = apply_filters( 'pta_sus_public_output', __('Open Spots', 'pta_volunteer_sus'), 'open_spots_header' );
	    $this->date_header = apply_filters( 'pta_sus_public_output', __('Date', 'pta_volunteer_sus'), 'date_header' );
	    $this->no_contact_message = apply_filters( 'pta_sus_public_output', __('No Event Chair contact info provided', 'pta_volunteer_sus'), 'no_contact_message' );
	    $this->contact_label = apply_filters( 'pta_sus_public_output', __('Contact:', 'pta_volunteer_sus'), 'contact_label' );
	    
	    $this->hidden = '';
	    // Allow admin or volunteer managers to view hidden sign up sheets
	    if (current_user_can( 'manage_options' ) || current_user_can( 'manage_signup_sheets' )) {
		    $this->show_hidden = true;
		    $this->hidden = '<br/><span class="pta-sus-hidden">'.apply_filters( 'pta_sus_public_output', '(--'.__('Hidden!', 'pta_volunteer_sus').'--)', 'hidden_notice' ).'</span>';
	    }
    }

    public function process_signup_form() {
        
        $this->submitted = (isset($_POST['pta_sus_form_mode']) && $_POST['pta_sus_form_mode'] == 'submitted');
        $this->err = 0;
        $this->success = false;
        $this->errors = '';
        $this->messages = '';
        $this->messages_displayed = false; // reset
	    $this->errors_displayed = false; // reset
	    $this->cleared = false;
        
        // Process Sign-up Form
        if ($this->submitted) {
            // NONCE check
            if ( ! isset( $_POST['pta_sus_signup_nonce'] ) || ! wp_verify_nonce( $_POST['pta_sus_signup_nonce'], 'pta_sus_signup' ) ) {
                $this->err++;
                $this->errors .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('Sorry! Your security nonce did not verify!', 'pta_volunteer_sus'), 'nonce_error_message' ).'</p>';
                return;
            }
            // Check for spambots
            if (!empty($_POST['website'])) {
                $this->err++;
                $this->errors .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('Oops! You filled in the spambot field. Please leave it blank and try again.', 'pta_volunteer_sus'), 'spambot_error_message' ).'</p>';
                return;
            }
	
	        // Give other plugins a chance to modify signup data
	        $posted = apply_filters('pta_sus_signup_posted_values', $_POST);

            $task = $this->data->get_task(intval($posted['signup_task_id']));
	        $sheet = $this->data->get_sheet(intval($task->sheet_id));
	        
	        $details_required = isset($task->details_required) && "YES" == $task->details_required;

            //Error Handling
            if (
                empty($posted['signup_firstname'])
                || empty($posted['signup_lastname'])
                || empty($posted['signup_email'])
                || empty($posted['signup_validate_email'])
                || (false == $this->main_options['no_phone'] && empty($posted['signup_phone']) && $this->phone_required)
                || ("YES" == $task->need_details && $details_required && (!isset($posted['signup_item']) || empty($posted['signup_item'])))
                || ("YES" == $task->enable_quantities && !isset($posted['signup_item_qty']))
            ) {
                $this->err++;
                $this->errors .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('Please complete all required fields.', 'pta_volunteer_sus'), 'required_fields_error_message' ).'</p>';
            }

            // Check for non-allowed characters
            elseif (! $this->data->check_allowed_text(stripslashes($posted['signup_firstname'])))
                {
                    $this->err++;
                    $this->errors .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('Invalid Characters in First Name!  Please try again.', 'pta_volunteer_sus'), 'firstname_error_message' ).'</p>';
                }
            elseif (! $this->data->check_allowed_text(stripslashes($posted['signup_lastname'])))
                {
                    $this->err++;
                    $this->errors .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('Invalid Characters in Last Name!  Please try again.', 'pta_volunteer_sus'), 'lastname_error_message' ).'</p>';
                }
            elseif ( !is_email( trim($posted['signup_email']) ) )
                {
                    $this->err++;
                    $this->errors .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('Invalid Email!  Please try again.', 'pta_volunteer_sus'), 'email_error_message' ).'</p>';
                }
            elseif ( trim($posted['signup_email']) != trim($posted['signup_validate_email'])  )
                {
	            $this->err++;
	            $this->errors .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('Email and Confirmation Email do not match!  Please try again.', 'pta_volunteer_sus'), 'confirmation_email_error_message' ).'</p>';
                }
            elseif (false == $this->main_options['no_phone'] && preg_match("/[^0-9\-\.\(\)\ \+]/", $posted['signup_phone']))
                {
                    $this->err++;
                    $this->errors .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('Invalid Characters in Phone Number!  Please try again.', 'pta_volunteer_sus'), 'phone_error_message' ).'</p>';
                }
            elseif ( "YES" == $task->need_details && ! $this->data->check_allowed_text(stripslashes($posted['signup_item'])))
                {
                    $this->err++;
                    $this->errors .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('Invalid Characters in Signup Item!  Please try again.', 'pta_volunteer_sus'), 'item_details_error_message' ).'</p>';
                }
            elseif ( "YES" == $task->enable_quantities && (! $this->data->check_numbers($posted['signup_item_qty']) || (int)$posted['signup_item_qty'] < 1 || (int)$posted['signup_item_qty'] > $this->data->get_available_qty($task->id, $posted['signup_date'], $task->qty) ) )
                {
                    $this->err++;
                    $variable = (int)$this->data->get_available_qty($task->id, $posted['signup_date'], $task->qty);
                    $this->errors .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', sprintf(__('Please enter a number between 1 and %d for Item QTY!', 'pta_volunteer_sus'), $variable), 'item_quantity_error_message', $variable ).'</p>';
                }
            elseif (!$this->data->check_date($posted['signup_date']))
                {
                    $this->err++;
                    $this->errors .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('Hidden signup date field is invalid!  Please try again.', 'pta_volunteer_sus'), 'signup_date_error_message' ).'</p>';
                }
            // Allow extensions to bypass duplicate checks
	        $perform_duplicate_checks = apply_filters('pta_sus_perform_duplicate_checks', true, $task, $sheet);
            if($perform_duplicate_checks) {
	            // If no errors so far, Check for duplicate signups if not allowed
	            if (!$this->err && 'NO' == $task->allow_duplicates) {
		            if( $this->data->check_duplicate_signup( $posted['signup_task_id'], $posted['signup_date'], $posted['signup_firstname'], $posted['signup_lastname']) ) {
			            $this->err++;
			            $this->errors .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('You are already signed up for this task!', 'pta_volunteer_sus'), 'signup_duplicate_error_message' ).'</p>';
		            }
	            }
	            if (!$sheet->duplicate_times && !$this->err && $this->data->check_duplicate_time_signup($sheet, $task, $posted['signup_date'], $posted['signup_firstname'], $posted['signup_lastname'])) {
		            $this->err++;
		            $this->errors .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('You are already signed up for another task in this time frame!', 'pta_volunteer_sus'), 'signup_duplicate_time_error_message' ).'</p>';
	            }
	            if ($this->no_global_overlap && !$this->err && $this->data->check_duplicate_time_signup($sheet, $task, $posted['signup_date'], $posted['signup_firstname'], $posted['signup_lastname'], $check_all = true)) {
		            $this->err++;
		            $this->errors .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('You are already signed up for another task in this time frame!', 'pta_volunteer_sus'), 'signup_duplicate_time_error_message' ).'</p>';
	            }
            }
            
            // Add Signup
            if (!$this->err) {
            	$signup_task_id = $posted['signup_task_id'];
            	
                do_action( 'pta_sus_before_add_signup', $posted, $signup_task_id);
                $signup_id=$this->data->add_signup($posted,$signup_task_id);
                if ( $signup_id === false) {
                    $this->err++;
                    $this->errors .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('Error adding signup record.  Please try again.', 'pta_volunteer_sus'), 'add_signup_database_error_message' ).'</p>';
                } else {
	                do_action( 'pta_sus_after_add_signup', $posted,$posted['signup_task_id'], $signup_id);
                    if(!class_exists('PTA_SUS_Emails')) {
                        include_once(dirname(__FILE__).'/class-pta_sus_emails.php');
                    }
                    $emails = new PTA_SUS_Emails();
                    $this->success = true;
                    $this->messages .= '<p class="pta-sus updated">'.apply_filters( 'pta_sus_public_output', __('You have been signed up!', 'pta_volunteer_sus'), 'signup_success_message' ).'</p>';
                    if ($emails->send_mail(intval($signup_id)) === false) { 
                        $this->messages .= '<p class="pta-sus updated">'.apply_filters( 'pta_sus_public_output', __('ERROR SENDING EMAIL', 'pta_volunteer_sus'), 'email_send_error_message' ).'</p>';
                    }
                }
            }
            
        }

	    // Check if they clicked on a CLEAR link
	    // Perhaps add some sort of confirmation, maybe with jQuery?
	    if (isset($_GET['signup_id']) && $_GET['signup_id'] > 0 && is_user_logged_in()) {
		    // Make sure the signup exists first
		    $signup=$this->data->get_signup((int)$_GET['signup_id']);
		    if (!is_user_logged_in()) {
			    $this->messages .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('Not allowed!', 'pta_volunteer_sus'), 'clear_invalid_signup_error_message' ).'</p>';
		    } elseif (null == $signup) {
			    $this->messages .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('Not a valid signup!', 'pta_volunteer_sus'), 'clear_invalid_signup_error_message' ).'</p>';
		    } elseif ($signup->user_id != get_current_user_id() && !current_user_can( 'manage_signup_sheets' )) {
			    $this->messages .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('Not allowed!', 'pta_volunteer_sus'), 'clear_invalid_signup_error_message' ).'</p>';
		    } else {
			    // Send cleared emails
			    if(!class_exists('PTA_SUS_Emails')) {
				    include_once(dirname(__FILE__).'/class-pta_sus_emails.php');
			    }
			    $emails = new PTA_SUS_Emails();
			    $emails->send_mail((int)$_GET['signup_id'], $reminder=false, $clear=true);
			    $cleared = $this->data->delete_signup((int)$_GET['signup_id']);
			    if ($cleared) {
				    $this->messages .= '<p class="pta-sus updated">'.apply_filters( 'pta_sus_public_output', __('Signup Cleared', 'pta_volunteer_sus'), 'signup_cleared_message' ).'</p>';
				    $this->cleared = true;
				    do_action('pta_sus_signup_cleared', $signup);
			    } else {
				    $this->messages .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('ERROR clearing signup!', 'pta_volunteer_sus'), 'error_clearing_signup_message' ).'</p>';
			    }
		    }
	    }
    }
    
    public function get_sheets_list($sheets, $atts=array()) {
	    
	    $return = apply_filters( 'pta_sus_before_sheet_list_table', '' );
	    $return .= '<div class="pta-sus-sheets main">';
	    $columns = array();
	    $columns['column-title'] = $this->title_header;
	    if($this->show_date_start) {
	    	$columns['column-date_start'] = $this->start_date_header;
	    }
	    if($this->show_date_end) {
		    $columns['column-date_end'] = $this->end_date_header;
	    }
	    $columns['column-open_spots'] = $this->open_spots_header;
	    $columns['column-view_link'] = '';
	    $columns = apply_filters('pta_sus_sheet_column_headers',$columns, $sheets, $atts);

	    if($this->use_divs) {
		    $return .= '<div class="pta-sus-sheets-table">';
		    ob_start();
		    include(PTA_VOLUNTEER_SUS_DIR.'views/sheets-view-divs-header-row-html.php');
		    $return .= ob_get_clean();
	    } else {
		    $return .= '<table class="pta-sus-sheets"><thead>';
		    ob_start();
		    include(PTA_VOLUNTEER_SUS_DIR.'views/sheets-view-table-header-row-html.php');
		    $return .= ob_get_clean();
		    $return .= '</thead><tbody>';
	    }
	    
	    foreach ($sheets AS $sheet) {
		    if ( 'Single' == $sheet->type ) {
			    // if a date was passed in, skip any sheets not on that date
			    if($this->date && $this->date != $sheet->first_date) continue;
		    } else {
			    // Recurring or Multi-day sheets
			    $dates = $this->data->get_all_task_dates($sheet->id);
			    if($this->date && !in_array($this->date, $dates)) continue;
		    }
		    if ( '1' == $sheet->visible) {
			    $is_hidden = '';
		    } else {
			    $is_hidden = $this->hidden;
		    }
		    $open_spots = ($this->data->get_sheet_total_spots($sheet->id) - $this->data->get_sheet_signup_count($sheet->id));
		    $number_spots = $sheet->no_signups ? '' : absint($open_spots);
		    $open_spots_display = apply_filters('pta_sus_public_output', $number_spots, 'sheet_number_open_spots', absint($open_spots));
		    $sheet_args = array('sheet_id' => $sheet->id, 'date' => false, 'signup_id' => false, 'task_id' => false);
		    $sheet_url = apply_filters('pta_sus_view_sheet_url', add_query_arg($sheet_args), $sheet);
		    $ongoing_label = apply_filters( 'pta_sus_public_output', __('Ongoing', 'pta_volunteer_sus'), 'ongoing_event_type_start_end_label' );
		    $view_signup_text = apply_filters( 'pta_sus_public_output', __('View &amp; sign-up &raquo;', 'pta_volunteer_sus'), 'view_and_signup_link_text' );
		    $sheet_filled_text = apply_filters( 'pta_sus_public_output', __('Filled', 'pta_volunteer_sus'), 'sheet_filled_text' );
		
		    if($sheet->no_signups) {
			    $open_spots = 1;
			    $view_signup_text = apply_filters( 'pta_sus_public_output', __('View Event &raquo;', 'pta_volunteer_sus'), 'view_event_link_text' );
		    }
		
		    $view_signup_text = apply_filters( 'pta_sus_view_signup_text_for_sheet', $view_signup_text, $sheet );

		    $title = '<a class="pta-sus-link view" href="'.esc_url($sheet_url).'">'.esc_html($sheet->title).'</a>'.$is_hidden;
		    $start_date = ($sheet->first_date == '0000-00-00') ? $ongoing_label : pta_datetime(get_option('date_format'), strtotime($sheet->first_date));
		    $end_date = ($sheet->last_date == '0000-00-00') ? $ongoing_label : pta_datetime(get_option('date_format'), strtotime($sheet->last_date));
		    $view_link = ($open_spots > 0) ? '<a class="pta-sus-link view" href="'.esc_url($sheet_url).'">'.esc_html( $view_signup_text ).'</a>' : '&#10004; '.esc_html( $sheet_filled_text );

		    $row_data = array();
		    $row_data['column-title'] = $title;
		    if($this->show_date_start) {
		    	$row_data['column-date_start'] = $start_date;
		    }
		    if($this->show_date_end) {
			    $row_data['column-date_end'] = $end_date;
		    }
		    $row_data['column-open_spots'] = $open_spots_display;
		    $row_data['column-view_link'] = $view_link;
		    $row_data = apply_filters('pta_sus_sheet_display_row_data', $row_data, $sheet, $this->date);

		    if($this->use_divs) {
			    ob_start();
			    include(PTA_VOLUNTEER_SUS_DIR.'views/sheets-view-divs-row-html.php');
			    $return .= ob_get_clean();
		    } else {
			    ob_start();
			    include(PTA_VOLUNTEER_SUS_DIR.'views/sheets-view-table-row-html.php');
			    $return .= ob_get_clean();
		    }
		    
	    }
	    
	    if($this->use_divs) {
		    $return .= '
                    </div>
                    </div>
                ';
	    } else {
		    $return .= '
                        </tbody>
                    </table>
                    </div>
                ';
	    }
	    
	    $return .= apply_filters( 'pta_sus_after_sheet_list_table', '' );
	    return $return;
    }
    
    public function get_single_sheet($id) {
    	$return = '';
	    // Display Individual Sheet
	    $sheet = apply_filters( 'pta_sus_display_individual_sheet', $this->data->get_sheet($id), $id );
	    if ($sheet === false) {
		    $return .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __("Sign-up sheet not found.", 'pta_volunteer_sus'), 'sheet_not_found_error_message' ).'</p>';
		    return $return;
	    } else {
		    // Check if the sheet is visible and don't show unless it's an admin user
		    if ( false == apply_filters('pta_sus_public_sheet_visible',$sheet->visible, $sheet) && !current_user_can( 'manage_signup_sheets' ) ) {
			    $return .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __("You don't have permission to view this page.", 'pta_volunteer_sus'), 'no_permission_to_view_error_message' ).'</p>';
			    return $return;
		    }
		    
		    // check if there are any future dates for the sheet
		    $future_dates = false;
		    if($this->date && $this->date >= current_time('Y-m-d')) {
			    $task_dates = array($this->date);
			    $future_dates = true;
		    } else {
			    $task_dates = $this->data->get_all_task_dates($sheet->id);
			    foreach ($task_dates as $tdate) {
				    if($tdate >= current_time('Y-m-d') || "0000-00-00" == $tdate) {
					    $future_dates = true;
					    break;
				    }
			    }
		    }
		    
		    // Allow extensions to choose if they want to show header info if not on the main volunteer page
		    $this->show_headers = apply_filters( 'pta_sus_show_sheet_headers', $this->show_headers, $sheet );
		    $return .= apply_filters( 'pta_sus_before_display_single_sheet', '', $sheet );
		
		    // Show the view all link only if no sheet ID is specified within the shortcode
		    if($this->shortcode_id !== $id) {
			    $view_all_text = apply_filters('pta_sus_public_output', __('&laquo; View all Sign-up Sheets', 'pta_volunteer_sus'), 'view_all_sheets_link_text');
			    $view_all_url = apply_filters('pta_sus_view_all_sheets_url', $this->all_sheets_uri, $sheet);
			    $return .= '<p><a class="pta-sus-link view-all" href="'.esc_url($view_all_url).'">'.esc_html( $view_all_text ).'</a></p>';
		    }
		    
		    // *****************************************************************************
		    // Show headers only if show_headers is true
		    if ( $this->show_headers ) {
			    // AFTER MAKING PTA MEMBER DIRECTORY A CLASS, WE CAN ALSO CHECK IF IT EXISTS
			    if( isset($this->integration_options['enable_member_directory']) && true === $this->integration_options['enable_member_directory'] && function_exists('pta_member_directory_init') && '' != $sheet->position ) {
				    // Create Contact Form link
				    if($position = get_term_by( 'slug', $sheet->position, 'member_category' )) {
					    if ( isset($this->integration_options['contact_page_id']) && 0 < $this->integration_options['contact_page_id']) {
						    $contact_url = get_permalink( $this->integration_options['contact_page_id'] ) . '?id=' . esc_html($sheet->position);
						    $display_chair = esc_html($this->contact_label) . ' <a class="pta-sus-link contact" href="' . esc_url($contact_url) .'">'. esc_html($position->name) .'</a>';
					    } elseif ( isset($this->integration_options['directory_page_id']) && 0 < $this->integration_options['directory_page_id']) {
						    $contact_url = get_permalink( $this->integration_options['directory_page_id'] ) . '?id=' . $sheet->position;
						    $display_chair = esc_html($this->contact_label)  . ' <a class="pta-sus-link contact" href="' . esc_url($contact_url) .'">'. esc_html($position->name) .'</a>';
					    } else {
						    $display_chair = esc_html( $this->no_contact_message );
					    }
				    } else {
					    $display_chair = esc_html( $this->no_contact_message );
				    }
				
			    } else {
				    $chair_names = $this->data->get_chair_names_html($sheet->chair_name);
				    // Check if there is more than one chair name to display either Chair or Chairs
				    $names = str_getcsv($sheet->chair_name);
				    $count = count($names);
				    if ( $count > 1) {
					    $display_chair = apply_filters( 'pta_sus_public_output', __('Event Chairs:', 'pta_volunteer_sus'), 'event_chairs_label_plural') .' <a class="pta-sus-link contact" href="mailto:'.esc_attr($sheet->chair_email).'">'.esc_html($chair_names).'</a>';
				    } elseif ( 1 == $count && '' != $sheet->chair_name && '' != $sheet->chair_email ) {
					    $display_chair = apply_filters( 'pta_sus_public_output', __('Event Chair:', 'pta_volunteer_sus'), 'event_chair_label_singular') .' <a class="pta-sus-link contact" href="mailto:'.esc_attr($sheet->chair_email).'">'.esc_html($chair_names).'</a>';
				    } else {
					    $display_chair = esc_html( $this->no_contact_message );
				    }
			    }
			
			    $display_chair = apply_filters( 'pta_sus_display_chair_contact', $display_chair, $sheet );
			    
			    $return .= '
                        <div class="pta-sus-sheet">
                            <h2>'.esc_html($sheet->title).'</h2>
                    ';
			    if ( false == $this->main_options['hide_contact_info'] ) {
				    $return .= '<h2>'.$display_chair.'</h2>';
			    }
		    } else {
			    $return .= '<div class="pta-sus-sheet">';
		    }
		    if ( false == $sheet->visible && current_user_can( 'manage_signup_sheets' ) ) {
			    $return .= '<p class="pta-sus-hidden">'.apply_filters( 'pta_sus_public_output', __('This sheet is currently hidden from the public.', 'pta_volunteer_sus'), 'sheet_hidden_message' ).'</p>';
		    }
		
		    // Display Sign-up Form
		    if (!$this->submitted || $this->err) {
			    if (isset($_GET['task_id']) && isset($_GET['date'])) {
				    do_action('pta_sus_before_display_signup_form', $_GET['task_id'], $_GET['date'] );
				    $errors = '';
				    if(!$this->errors_displayed || !$this->suppress_duplicates) {
					    $errors = $this->errors;
					    $this->errors_displayed = true;
				    }
				    return $errors . $this->display_signup_form($_GET['task_id'], $_GET['date']);
			    }
		    }
		
		    // Sheet Details
		    if (!$this->submitted || $this->success || $this->err) {
			
			    // Make sure there are some future dates before showing anything
			    if($future_dates) {
				    // Only show details if there is something to show, and show headers is true
				    if('' != $sheet->details && $this->show_headers) {
					    $return .= '<h3 class="pta-sus details-header">'.apply_filters( 'pta_sus_public_output', __('DETAILS:', 'pta_volunteer_sus'), 'sheet_details_heading' ).'</h3>';
					    $return .= wp_kses_post($sheet->details);
				    }
				    $open_spots = ($this->data->get_sheet_total_spots($sheet->id) - $this->data->get_sheet_signup_count($sheet->id));
				    if ($open_spots > 0 && !$sheet->no_signups) {
					    $return .= '<h3 class="pta-sus sign-up-header">'.apply_filters( 'pta_sus_public_output', __('Sign up below...', 'pta_volunteer_sus'), 'sign_up_below' ).'</h3>';
				    } elseif (!$sheet->no_signups) {
					    $return .= '<h3 class="pta-sus filled-header">'.apply_filters( 'pta_sus_public_output', __('All spots have been filled.', 'pta_volunteer_sus'), 'sheet_all_spots_filled' ).'</h3>';
				    }
				
				    $task_dates = apply_filters( 'pta_sus_sheet_task_dates', $task_dates, $sheet->id );
				    $alt_view = apply_filters('pta_sus_display_alt_task_list', '', $sheet, $task_dates);
				    if('' === $alt_view) {
				    	foreach ($task_dates as $tdate) {
						    if( "0000-00-00" != $tdate && $tdate < current_time('Y-m-d')) continue; // Skip dates that have passed already
						    $return .= $this->display_task_list($sheet->id, $tdate, $sheet->no_signups);
					    }
				    } else {
				    	$return .= $alt_view;
				    }
			    }
			
			    $return .= '</div>';
		    }
	    }
	    return $return;
    }
    
    public function get_user_signups_list($atts) {
    	$return = '';
	    $current_user = wp_get_current_user();
	    if ( !($current_user instanceof WP_User) )
		    return '';
		/**
	     * @var string $show_time
	     */
	    extract( shortcode_atts( array(
		    'show_time' => 'yes'
	    ), $atts, 'pta_user_signups' ) );
	    $times = 'no' !== $show_time;
	    $details = empty($this->main_options['hide_signups_details_qty']) || false == $this->main_options['hide_signups_details_qty'];
	    $signups = apply_filters( 'pta_sus_user_signups', $this->data->get_user_signups($current_user->ID) );
	    if ($signups) {
		    $return .= apply_filters( 'pta_sus_before_user_signups_list_headers', '' );
		    $return .= '<h3 class="pta-sus user-heading">'.apply_filters( 'pta_sus_public_output', __('You have signed up for the following', 'pta_volunteer_sus'), 'user_signups_list_headers_h3' ).'</h3>';
		    $return .= '<h4 class="pta-sus user-heading">'.apply_filters( 'pta_sus_public_output', __('Click on Clear to remove yourself from a signup.', 'pta_volunteer_sus'), 'user_signups_list_headers_h4' ).'</h4>';
		    $return .= apply_filters( 'pta_sus_before_user_signups_list_table', '' );
		    $return .= '<div class="pta-sus-sheets user">';
		    
		    if($this->use_divs) {
			    $return .= '<div class="pta-sus-sheets-table">
                            <div class="pta-sus-sheets-row">
                                <div class="column-title head">'.esc_html($this->title_header).'</div>
                                <div class="column-date head">'.esc_html($this->date_header).'</div>
                                <div class="column-task head">'.esc_html($this->task_item_header).'</div>';
		    } else {
			    $return .= '
                        <table class="pta-sus-sheets" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="column-title">'.esc_html($this->title_header).'</th>
                                <th class="column-date">'.esc_html($this->date_header).'</th>
                                <th class="column-task">'.esc_html($this->task_item_header).'</th>';
		    }
		
		    $return .= apply_filters( 'pta_sus_user_signups_list_headers_after_task', '', $this->use_divs );
		    
		    if ($times) {
			    if($this->use_divs) {
				    $return .='
                                <div class="column-start-time head" style="text-align:right;">'.esc_html($this->start_time_header).'</div>
                                <div class="column-end-time head" style="text-align:right;">'.esc_html($this->end_time_header).'</div>';
			    } else {
				    $return .='
                                <th class="column-time start" style="text-align:right;">'.esc_html($this->start_time_header).'</th>
                                <th class="column-time end" style="text-align:right;">'.esc_html($this->end_time_header).'</th>';
			    }
		    }

		    if($details) {
		    	if($this->use_divs) {
				    $return .= '
	                                <div class="column-details head" style="text-align:center;">'.esc_html($this->item_details_header).'</div>
	                                <div class="column-qty head" style="text-align:center;">'.esc_html($this->item_qty_header).'</div>';
			    } else {
				    $return .= '
	                                <th class="column-details" style="text-align:center;">'.esc_html($this->item_details_header).'</th>
	                                <th class="column-qty" style="text-align:center;">'.esc_html($this->item_qty_header).'</th>';
			    }
		    }

		    if($this->use_divs) {
			    $return .= '<div class="column-clear_link head">&nbsp;</div>
                            </div>';
		    } else {
			    $return .= '<th class="column-clear_link">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>';
		    }
		    
		    foreach ($signups as $signup) {
			
			    if ( true == $signup->clear && ( 0 == $signup->clear_days || $signup->signup_date == "0000-00-00"
			                                     || ( strtotime( $signup->signup_date ) - current_time( 'timestamp' ) > ((int)$signup->clear_days * 60 * 60 * 24) ) ) ) {
				    $clear_args = array('sheet_id' => false, 'task_id' => false, 'signup_id' => (int)$signup->id);
				    $clear_url = add_query_arg($clear_args);
				    $clear_text = apply_filters( 'pta_sus_public_output', __('Clear', 'pta_volunteer_sus'), 'clear_signup_link_text');
			    } else {
				    $clear_url = '';
				    $clear_text = '';
			    }
				
			    if($this->use_divs) {
				    $return .= '<div class="pta-sus-sheets-row">
                            <div class="column-title">'.esc_html($signup->title).'</div>
                            <div class="column-date">'.(($signup->signup_date == "0000-00-00") ? esc_html($this->na_text) : pta_datetime(get_option("date_format"), strtotime($signup->signup_date))).'</div>
                            <div class="column-title" >'.esc_html($signup->task_title).'</div>';
			    } else {
				    $return .= '<tr>
                            <td>'.esc_html($signup->title).'</td>
                            <td>'.(($signup->signup_date == "0000-00-00") ? esc_html($this->na_text) : pta_datetime(get_option("date_format"), strtotime($signup->signup_date))).'</td>
                            <td>'.esc_html($signup->task_title).'</td>';
			    }
			
			    $return .= apply_filters( 'pta_sus_user_signups_list_content_after_task', '', $signup, $this->use_divs );
			    
			    if ($times) {
				    if($this->use_divs) {
					    $return .='
                            <div class="column-start-time" style="text-align:right;">'.(("" == $signup->time_start) ? esc_html($this->na_text) : pta_datetime(get_option("time_format"), strtotime($signup->time_start)) ).'</div>
                            <div class="column-end-time" style="text-align:right;">'.(("" == $signup->time_end) ? esc_html($this->na_text) : pta_datetime(get_option("time_format"), strtotime($signup->time_end)) ).'</div>';
				    } else {
					    $return .='
                            <td style="text-align:right;">'.(("" == $signup->time_start) ? esc_html($this->na_text) : pta_datetime(get_option("time_format"), strtotime($signup->time_start)) ).'</td>
                            <td style="text-align:right;">'.(("" == $signup->time_end) ? esc_html($this->na_text) : pta_datetime(get_option("time_format"), strtotime($signup->time_end)) ).'</td>';
				    }
				    
			    }
			    if($details) {
			    	if($this->use_divs) {
					    $return .= '
	                            <div class="column-item" style="text-align:center;">'.((" " !== $signup->item) ? esc_html($signup->item) : esc_html($this->na_text) ).'</div>
	                            <div class="column-qty" style="text-align:center;">'.(("" !== $signup->item_qty) ? (int)$signup->item_qty : esc_html($this->na_text) ).'</div>';
				    } else {
					    $return .= '
	                            <td style="text-align:center;">'.((" " !== $signup->item) ? esc_html($signup->item) : esc_html($this->na_text) ).'</td>
	                            <td style="text-align:center;">'.(("" !== $signup->item_qty) ? (int)$signup->item_qty : esc_html($this->na_text) ).'</td>';
				    }
			    }
			    if($this->use_divs) {
				    $return .= '<div class="column-clear" style="text-align:right;"><a class="pta-sus-link clear-signup" href="'.esc_url($clear_url).'">'.esc_html($clear_text).'</a></div>
                        </div>';
			    } else {
				    $return .= '<td style="text-align:right;"><a class="pta-sus-link clear-signup" href="'.esc_url($clear_url).'">'.esc_html($clear_text).'</a></td>
                        </tr>';
			    }

		    }
		    
		    if($this->use_divs) {
			    $return .= '</div></div>';
		    } else {
			    $return .= '</tbody></table></div>';
		    }
		    
		    $return .= apply_filters( 'pta_sus_after_user_signups_list_table', '' );
		    return $return;
	    }
    }

    public function process_user_signups_shortcode($atts) {

    	$return = $this->get_user_signups_list($atts);
    	if(empty($return)) {
    		$return = __('You do not have any current signups', 'pta_volunteer_sus');
	    }
	    return $return;
    }

	/**
     * Process shortcode to Output the volunteer sheets list or individual sheet
     * 
     * @param   array   attributes from shortcode call
	 * @return string sheets list or individual sheet
     */
    public function display_sheet($atts) {
	    
        do_action( 'pta_sus_before_process_shortcode', $atts );
        $return = '';
        if(isset($this->main_options['enable_test_mode']) && true === $this->main_options['enable_test_mode'] ) {
            if (current_user_can( 'manage_options' ) || current_user_can( 'manage_signup_sheets' )) {
                $return .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('Volunteer Sign-Up Sheets are in TEST MODE', 'pta_volunteer_sus'), 'admin_test_mode_message' ).'</p>';
            } elseif (is_page( $this->main_options['volunteer_page_id'] )) {
                $message = esc_html($this->main_options['test_mode_message']);
                return $message;
            } else {
                return '';
            }
        }
        if(isset($this->main_options['login_required']) && true === $this->main_options['login_required'] ) {
            if (!is_user_logged_in()) {
                $message = '<p class="pta-sus error">' . esc_html($this->main_options['login_required_message']) . '</p>';
                if(isset($this->main_options['show_login_link']) && true === $this->main_options['show_login_link']) {
                    $message .= '<p><a class="pta-sus-link login" href="'. wp_login_url( get_permalink() ) .'" title="Login">'.__("Login", "pta_volunteer_sus").'</a></p>';
                }
                return $message;
            }
        }
        extract( shortcode_atts( array(
            'id' => '',
            'date' => '',
            'show_time' => 'yes',
            'show_phone' => 'no',
            'show_headers' => 'yes',
            'show_date_start' => 'yes',
            'show_date_end' => 'yes',
            'order_by' => 'first_date',
            'order' => 'ASC',
            'list_title' => __('Current Volunteer Sign-up Sheets', 'pta_volunteer_sus'),
        ), $atts, 'pta_sign_up_sheet' ) );
	    /**
	     * Variables extracted from shortcode, with above default values
	     * @var mixed $id
	     * @var mixed $date
	     * @var string $show_time
	     * @var string $show_phone
	     * @var string $show_headers
	     * @var string $order_by
	     * @var string $order
	     * @var string $list_title
	     * @var string $show_date_start
	     * @var string $show_date_end
	     */
        // Allow plugins or themes to modify shortcode parameters
        $id = apply_filters( 'pta_sus_shortcode_id', $id );
        if('' == $id) {
	        $id = false;
        }
	    $this->shortcode_id = $id;
        $this->date = apply_filters( 'pta_sus_shortcode_date', $date );
        if('' == $this->date) {
	        $this->date = false;
        }
        if('' == $list_title) {
	        $list_title = __('Current Volunteer Sign-up Sheets', 'pta_volunteer_sus');
        }
        $list_title = apply_filters( 'pta_sus_shortcode_list_title', $list_title );
	    $order_by = apply_filters( 'pta_sus_shortcode_order_by', $order_by );
	    $order = apply_filters( 'pta_sus_shortcode_order', $order );
        if ( $show_time === 'no') {
	        $this->show_time = false;
        } else {
	        $this->show_time = true;
        }
	    if ( $show_phone === 'yes') {
		    $this->show_phone = true;
	    } else {
		    $this->show_phone = false;
	    }
	    if ( $show_headers === 'no') {
		    $this->show_headers = false;
	    } else {
		    $this->show_headers = true;
	    }
	    if ( $show_date_start === 'no') {
		    $this->show_date_start = false;
	    } else {
		    $this->show_date_start = true;
	    }
	    if ( $show_date_end === 'no') {
		    $this->show_date_end = false;
	    } else {
		    $this->show_date_end = true;
	    }
        
        if ($id === false && !empty($_GET['sheet_id']) && !$this->success && !$this->cleared) {
	        $id = (int)$_GET['sheet_id'];
        }

        if ($this->date === false && !empty($_GET['date']) && !$this->success && !$this->cleared) {
            // Make sure it's a valid date in our format first - Security check
            if ($this->data->check_date($_GET['date'])) {
                $this->date = $_GET['date'];
            }
        }

	    // Give other plugins a chance to restrict access to the sheets list
	    if(false == apply_filters('pta_sus_can_view_sheets', true, $atts)) {
		    return '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __("You don't have permission to view this page.", 'pta_volunteer_sus'), 'no_permission_to_view_error_message' ).'</p>';
	    }

        $return = apply_filters( 'pta_sus_before_display_sheets', $return, $id, $this->date );
        do_action( 'pta_sus_begin_display_sheets', $id, $this->date );

	    if( !$this->messages_displayed || !$this->suppress_duplicates) {
	    	// only show messages above first list if multiple shortcodes on one page
		    $return .= $this->messages;
		    $this->messages_displayed = true;
	    }
        
        if ($id === false) {
            
            // Display all active
            $return .= '<h2 class="pta-sus-list-title">'.apply_filters( 'pta_sus_public_output', esc_html($list_title), 'sheet_list_title' ).'</h2>';
            $sheets = $this->data->get_sheets(false, true, $this->show_hidden, $order_by, $order);

            // Move ongoing sheets to bottom of list if that setting is checked
            if ($this->main_options['show_ongoing_last']) {
                // Move ongoing events to end of our sheets array
                foreach ($sheets as $key => $sheet) {
                    if ('Ongoing' == $sheet->type) {
                        $move_me = $sheet;
                        unset($sheets[$key]);
                        $sheets[] = $move_me;
                    }
                }
            }

            // Allow plugins or themes to modify retrieved sheets
            $sheets = apply_filters( 'pta_sus_display_active_sheets', $sheets, $atts );

            if (empty($sheets)) {
                $return .= '<p>'.apply_filters( 'pta_sus_public_output', __('No sheets currently available at this time.', 'pta_volunteer_sus'), 'no_sheets_message' ).'</p>';
            } else {
                $sheets_table = $this->get_sheets_list($sheets, $atts);
                $return .= apply_filters('pta_sus_display_sheets_table', $sheets_table, $sheets);
            }
            
            // If current user has signed up for anything, list their signups and allow them to edit/clear them
            // If they aren't logged in, prompt them to login to see their signup info
            if ( !is_user_logged_in() ) {
                if (!$this->main_options['disable_signup_login_notice']) {
                    $return .= '<p>'. apply_filters( 'pta_sus_public_output', __('Please login to view and edit your volunteer sign ups.', 'pta_volunteer_sus'), 'user_not_loggedin_signups_list_message' ).'</p>';
                }
            } else {
                $user_signups_list = $this->get_user_signups_list($atts);
                $return .= apply_filters('pta_sus_display_user_signups_table', $user_signups_list);
            }

        } else {
            $return .= $this->get_single_sheet($id);
        }
        $return .= apply_filters( 'pta_sus_after_display_sheets', '', $id, $this->date );
        return $return;
    } // Display Sheet

	public function display_task_list($sheet_id, $date, $no_signups=false) {
		// Tasks

		$tasks = apply_filters('pta_sus_public_sheet_get_tasks', $this->data->get_tasks($sheet_id, $date), $sheet_id, $date);

		if (!$tasks ) {
			return '<p>'.apply_filters( 'pta_sus_public_output', __('No tasks were found for ', 'pta_volunteer_sus'), 'no_tasks_found_for_date' ) . mysql2date( get_option('date_format'), $date, $translate = true ).'</p>';
		}
		$return = apply_filters( 'pta_sus_before_task_list', '', $tasks );

		$show_all_slots = true;
		if(isset($this->main_options['show_remaining']) && true == $this->main_options['show_remaining']) {
			$show_all_slots = false;
		}
		$show_names = true;
		if($this->main_options['hide_volunteer_names']) {
			$show_names = false;
		}

		$sheet = $this->data->get_sheet($sheet_id);
		if ( current_user_can('manage_signup_sheets') || ( true == $sheet->clear &&  ( 0 == $sheet->clear_days || $date == "0000-00-00"
		       || ( strtotime( $date ) - current_time( 'timestamp' ) > ((int)$sheet->clear_days * 60 * 60 * 24) ))
		) ){
			$show_clear = true;
		} else {
			$show_clear = false;
		}

		$return .= '<div class="pta-sus-sheets tasks">';

		foreach($tasks as $task) {
			$task_dates = explode(',', $task->dates);
			// Don't show tasks that don't include our date, if one was passed in
			if ($date && !in_array($date, $task_dates)) continue;

			$columns = array();

			$show_details = false;
			$show_qty = false;
			if(isset($this->main_options['hide_details_qty']) && true != $this->main_options['hide_details_qty']) {
				if ( 'YES' == $task->need_details ) {
					$show_details = true;
				}
				if ( 'YES' == $task->enable_quantities && ( $show_names || $show_all_slots || $show_details ) ) {
					$show_qty = true;
				}
			}

			$one_row = false;
			if(!$show_all_slots && !$show_details && !$show_names && !$no_signups) {
				$one_row = true;
			}

			if(!$no_signups) {
				$columns['column-available-spots'] = apply_filters( 'pta_sus_public_output', __('Available Spots', 'pta_volunteer_sus'), 'task_available_spots_header' );

				if($this->show_phone && !$one_row) {
					$columns['column-phone'] = apply_filters( 'pta_sus_public_output', __('Phone', 'pta_volunteer_sus'), 'task_phone_header' );
				}

			}
			if ($show_details && !$one_row) {
				$columns['column-details'] = $this->item_details_header;
			}
			if ($show_qty && !$one_row) {
				$columns['column-quantity'] = $this->item_qty_header;
			}

			if(is_user_logged_in() && !$one_row && $show_clear) {
				$columns['column-clear'] = '';
			}

			$task_args = array('sheet_id' => $sheet_id, 'task_id' => $task->id, 'date' => $date, 'signup_id' => false);
			if (is_page($this->main_options['volunteer_page_id']) || false == $this->main_options['signup_redirect']) {
				$task_url = add_query_arg($task_args);
			} else {
				$main_page_url = get_permalink( $this->main_options['volunteer_page_id'] );
				$task_url = add_query_arg($task_args, $main_page_url);
			}
			$task_url = apply_filters( 'pta_sus_task_signup_url', $task_url, $task, $sheet_id, $date );

			$i=1;
			$signups = apply_filters( 'pta_sus_task_get_signups', $this->data->get_signups($task->id, $date), $task->id, $date);

			// Set qty to one for no_signups sheets
			$task_qty = $no_signups ? 1 : absint($task->qty);

			// Allow extensions to determine if signup links should be shown
			$allow_signups = apply_filters('pta_sus_allow_signups', true, $task, $sheet_id, $date);
			
			// Allow extensions to add/modify column headers
			$columns = apply_filters('pta_sus_task_column_headers', $columns, $task, $date, $one_row);

			ob_start();
			include(PTA_VOLUNTEER_SUS_DIR.'views/task-view-header-html.php');
			$return .= ob_get_clean();

			if($this->use_divs) {
				$additional_div_class = apply_filters('pta_sus_additional_task_table_class_divs', '');
				$return .= '<div class="pta-sus-tasks-table '.esc_attr($additional_div_class).'">';
				ob_start();
				include(PTA_VOLUNTEER_SUS_DIR.'views/task-view-divs-header-row-html.php');
				$return .= ob_get_clean();
			} else {
				$additional_table_class = apply_filters('pta_sus_additional_task_table_class', '');
				$return .= '<table class="pta-sus-tasks '.esc_attr($additional_table_class).'"><thead>';
				ob_start();
				include(PTA_VOLUNTEER_SUS_DIR.'views/task-view-table-header-row-html.php');
				$return .= ob_get_clean();
				$return .= '</thead><tbody>';
			}

			// One simple row if everything should be consolidated (no item details or names and consolidate option is set)
			$column_data = array();
			if($one_row) {
				$row_data = array();
				$filled = 0;
				foreach($signups as $signup) {
					$filled += $signup->item_qty;
				}
				$remaining = $task_qty - $filled;

				if($remaining > 0) {
					$filled_text = apply_filters( 'pta_sus_public_output', sprintf(__('%d Filled', 'pta_volunteer_sus'),(int)$filled ), 'task_number_spots_filled_message', (int)$filled );
				} else {
					$filled_text = apply_filters('pta_sus_public_output', __('Filled', 'pta_volunteer_sus'), 'task_spots_full_message');
				}
				$remaining_text = apply_filters( 'pta_sus_public_output', sprintf(__('%d remaining: &nbsp;', 'pta_volunteer_sus'), (int)$remaining), 'task_number_remaining', (int)$remaining );
				$separator = apply_filters('pta_sus_public_output', ', ', 'task_spots_filled_remaining_separator');
				$display_consolidated = $filled_text;
				if($remaining > 0 && $allow_signups) {
					if(false == $this->main_options['login_required_signup'] || is_user_logged_in()) {
						$display_consolidated .= esc_html($separator . $remaining_text).'<a class="pta-sus-link signup" href="'.esc_url($task_url).'">'.apply_filters( 'pta_sus_public_output', __('Sign up &raquo;', 'pta_volunteer_sus'), 'task_sign_up_link_text' ) . '</a>';
					} else {
						$display_consolidated .= ' - ' . esc_html($this->main_options['login_signup_message']);
					}
				}
				$row_data['column-available-spots'] = $display_consolidated;
				$row_data['extra-class'] = 'consolidated';
				$column_data[] = apply_filters('pta_sus_task_consolidated_row_data', $row_data, $task, $date);
			} else {
				// show signup rows
				foreach ($signups AS $signup) {
					$row_data = array();

					if($show_names) {
						if($this->show_full_name) {
							$display_signup = wp_kses_post($signup->firstname.' '.$signup->lastname);
						} else {
							$display_signup = wp_kses_post($signup->firstname.' '.$this->data->initials($signup->lastname));
						}
						$row_data['extra-class'] = 'signup';
					} else {
						$display_signup = apply_filters( 'pta_sus_public_output', __('Filled', 'pta_volunteer_sus'), 'task_spot_filled_message' );
						$row_data['extra-class'] = 'filled';
					}

					// hook to allow others to modify how the signed up names are displayed
					$display_signup = apply_filters( 'pta_sus_display_signup_name', $display_signup, $signup );

					if ( $show_clear && ($signup->user_id == get_current_user_id() || current_user_can('manage_signup_sheets')) ) {
						$clear_args = array('sheet_id' => false, 'task_id' => false, 'signup_id' => (int)$signup->id);
						$clear_url = add_query_arg($clear_args);
						$clear_text = apply_filters( 'pta_sus_public_output', __('Clear', 'pta_volunteer_sus'), 'clear_signup_link_text');
					} else {
						$clear_url = '';
						$clear_text = '';
					}

					$row_data['column-available-spots'] = '#'.$i.': '.$display_signup;
					if($this->show_phone) {
						$row_data['column-phone'] = $signup->phone;
					}

					if ($show_details) {
						$row_data['column-details'] = $signup->item;
					}
					if ($show_qty) {
						$row_data['column-quantity'] = ("YES" === $task->enable_quantities ? (int)($signup->item_qty) : "");
					}

					if(is_user_logged_in() && $show_clear) {
						$row_data['column-clear'] = '<a class="pta-sus-link clear-signup" href="'.esc_url($clear_url).'">'.esc_html($clear_text).'</a>';
					}
					if ('YES' === $task->enable_quantities) {
						$i += $signup->item_qty;
					} else {
						$i++;
					}
					$column_data[] = apply_filters('pta_sus_task_signup_display_row_data', $row_data, $task, $signup, $date);
				}

				$remaining = $task_qty - ($i - 1);

				$start = $i;

				if(!$show_all_slots) {
					$start = $remaining;
					$task_qty = $remaining;
				}

				if($remaining > 0) {
					// set up all the common data first to speed things up
					$row_data=array();
					$signup_message = '';
					if(!$no_signups) {
						if($allow_signups) {
							if(false == $this->main_options['login_required_signup'] || is_user_logged_in()) {
								$signup_message = '<a class="pta-sus-link signup" href="'.esc_url($task_url).'">'.apply_filters( 'pta_sus_public_output', __('Sign up &raquo;', 'pta_volunteer_sus'), 'task_sign_up_link_text' ) . '</a>';
							} else {
								if(isset($this->main_options['show_login_link']) && true === $this->main_options['show_login_link']) {
									$signup_message = '<a class="pta-sus-link login" href="'. wp_login_url( get_permalink() ) .'" title="Login">'.esc_html($this->main_options['login_signup_message']).'</a>';
								} else {
									$signup_message = esc_html($this->main_options['login_signup_message']);
								}
							}
						}
						if($this->show_phone) {
							$row_data['column-phone'] = '';
						}
					}
					if(is_user_logged_in() && $show_clear) {
						$row_data['column-clear'] = '';
					}
					if($show_details) {
						$row_data['column-details'] = '';
					}
					if($show_qty) {
						$row_data['column-quantity'] = '';
					}
					$row_data['extra-class'] = 'remaining';
					$row_data = apply_filters('pta_sus_task_remaining_display_row_data', $row_data, $task, $date);
					for ($i=$start; $i<=$task_qty; $i++) {
						if(!$no_signups) {
							if($show_all_slots) {
								$row_data['column-available-spots'] = '#'.$i.': ';
							} else {
								$row_data['column-available-spots'] = apply_filters( 'pta_sus_public_output', sprintf(__('%d remaining: &nbsp;', 'pta_volunteer_sus'), (int)$remaining), 'task_number_remaining', (int)$remaining );
							}
							$row_data['column-available-spots'] .= $signup_message;
						}
						$column_data[] = $row_data;
					}
				}
			}

			if($this->use_divs) {
				ob_start();
				include(PTA_VOLUNTEER_SUS_DIR.'views/task-view-divs-rows-html.php');
				$return .= ob_get_clean();
				$return .= '</div>'; // close "table" divs
			} else {
				ob_start();
				include(PTA_VOLUNTEER_SUS_DIR.'views/task-view-table-rows-html.php');
				$return .= ob_get_clean();
				$return .= '</body></table>'; // close body and table
			}

			$return .= apply_filters( 'pta_sus_after_task_list', '', $tasks );
		}

		$return .= '</div>'; // close wrapper for both table and divs layouts

		return $return;
	} // Display task list

	public function display_signup_form($task_id, $date) {
		if(true == $this->main_options['login_required_signup'] && !is_user_logged_in()) {
			$message = '<p class="pta-sus error">' . esc_html($this->main_options['login_signup_message']) . '</p>';
			if(isset($this->main_options['show_login_link']) && true === $this->main_options['show_login_link']) {
				$message .= '<p><a class="pta-sus-link login" href="'. wp_login_url( get_permalink() ) .'" title="Login">'.__("Login", "pta_volunteer_sus").'</a></p>';
			}
			return $message;
		}
		
		if( $this->suppress_duplicates && true == apply_filters('pta_sus_signup_form_already_displayed', $this->signup_displayed, $task_id, $date)) {
			// don't show more than one signup form on a page,
			// if admin put multiple shortcodes on a page and didn't set to redirect to main shortcode page
			return '';
		}

        $task = apply_filters( 'pta_sus_public_signup_get_task', $this->data->get_task($task_id), $task_id);
        do_action( 'pta_sus_before_signup_form', $task, $date );
		// Give other plugins a chance to restrict signup access
		if(false == apply_filters('pta_sus_can_signup', true, $task, $date)) {
			return '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __("You don't have permission to view this page.", 'pta_volunteer_sus'), 'no_permission_to_view_error_message' ).'</p>';
		}
        if ("0000-00-00" == $date) {
            $show_date = false;
        } else {
            $show_date = pta_datetime(get_option('date_format'), strtotime($date));
        }
        $phone_required = $this->phone_required ? 'required' : '';
		$details_required = isset($task->details_required) && "YES" == $task->details_required ? 'required' : '';
		
        $form = '<div class="pta-sus-sheets signup-form">';
        $form .= apply_filters( 'pta_sus_signup_page_before_form_title', '', $task, $date );
        $form .= '<h3 class="pta-sus sign-up-header">'.apply_filters( 'pta_sus_public_output', __('Sign Up', 'pta_volunteer_sus'), 'sign_up_form_heading' ).'</h3>';
        $form .= '<h4 class="pta-sus sign-up-header">'. apply_filters( 'pta_sus_public_output', __('You are signing up for... ', 'pta_volunteer_sus'), 'you_are_signing_up_for' ).'<br/><strong>'.esc_html($task->title).'</strong> ';
        if ($show_date) {
            $form .= sprintf(__('on %s', 'pta_volunteer_sus'), $show_date);
        }
        $form .= '</h4>';
        if ($this->show_time && !empty($task->time_start)) {
            $form .= '<span class="time_start">'.esc_html($this->start_time_header) . ': '. pta_datetime(get_option("time_format"), strtotime($task->time_start)) . '</span><br/>';
        }
        if ($this->show_time && !empty($task->time_end)) {
            $form .= '<span class="time_end">'.esc_html($this->end_time_header) . ': '. pta_datetime(get_option("time_format"), strtotime($task->time_end)) . '</span><br/>';
        }
        $firstname_label = apply_filters( 'pta_sus_public_output', __('First Name', 'pta_volunteer_sus'), 'firstname_label' );
        $lastname_label = apply_filters( 'pta_sus_public_output', __('Last Name', 'pta_volunteer_sus'), 'lastname_label' );
        $email_label = apply_filters( 'pta_sus_public_output', __('E-mail', 'pta_volunteer_sus'), 'email_label' );
		$validate_email_label = apply_filters( 'pta_sus_public_output', __('Confirm E-mail', 'pta_volunteer_sus'), 'confirm_email_label' );
        $phone_label = apply_filters( 'pta_sus_public_output', __('Phone', 'pta_volunteer_sus'), 'phone_label' );

        $form .= apply_filters( 'pta_sus_signup_form_before_form_fields', '<br/>', $task, $date );
		// Give other plugins a chance to modify signup data
		$posted = apply_filters('pta_sus_signup_posted_values', $_POST);
		$values = array(
			'signup_user_id' => isset($posted['signup_user_id']) ? absint($posted['signup_user_id']) : '',
			'signup_firstname' => isset($posted['signup_firstname']) ? sanitize_text_field($posted['signup_firstname']) : '',
			'signup_lastname' => isset($posted['signup_lastname']) ? sanitize_text_field($posted['signup_lastname']) : '',
			'signup_email' => isset($posted['signup_email']) ? sanitize_email($posted['signup_email']) : '',
			'signup_validate_email' => isset($posted['signup_validate_email']) ? sanitize_email($posted['signup_validate_email']) : '',
			'signup_phone' => isset($posted['signup_phone']) ? sanitize_text_field($posted['signup_phone']) : ''
		);
		$readonly_first="";
        $readonly_last="";
        $readonly_email="";
        // Prefill user data if they are signed in and not admin or signup sheet manager - don't change if posted (form error)
        if ( is_user_logged_in() && empty($posted) ) {
            $current_user = wp_get_current_user();
            if ( !($current_user instanceof WP_User) ) {
            	wp_die('Not a valid user');
            }
            if ( isset($this->main_options['login_required_signup']) && true === $this->main_options['login_required_signup']
                 && isset($this->main_options['readonly_signup']) && true === $this->main_options['readonly_signup']
                 && !current_user_can('manage_signup_sheets') ) {
               if (!empty($current_user->user_firstname))
                  $readonly_first="readonly='readonly'";
               if (!empty($current_user->user_lastname))
                  $readonly_last="readonly='readonly'";
               $readonly_email="readonly='readonly'";
	        }
            // Only populate values if regular user or if admin/manager and not using live search
	        if(!(current_user_can( 'manage_signup_sheets') && true === $this->main_options['enable_signup_search'])) {
	        	$values['signup_user_id'] = $current_user->ID;
	            $values['signup_firstname'] = $current_user->user_firstname;
	            $values['signup_lastname'] = $current_user->user_lastname;
	            $values['signup_email'] = $current_user->user_email;
	            $values['signup_validate_email'] = $current_user->user_email;
	            if (!$this->main_options['no_phone'] ) {
	                $phone = apply_filters('pta_sus_user_phone', get_user_meta( $current_user->ID, 'billing_phone', true ), $current_user );
	                $values['signup_phone'] = $phone;
	            }
				$values = apply_filters('pta_sus_prefilled_user_signup_values', $values);
	        }
        }
        // Default User Fields
        if (false == $this->main_options['disable_signup_login_notice']) {
            $form .= '<p>'.apply_filters( 'pta_sus_public_output', __('If you have an account, it is strongly recommended that you <strong>login before you sign up</strong> so that you can view and edit all your signups.', 'pta_volunteer_sus'), 'signup_login_notice' ).'</p>';
        }
        $form .= '
		<form name="pta_sus_signup_form" method="post" action="">
			<input type="hidden" name="signup_user_id" value="'.$values['signup_user_id'].'" />
			<p>
				<label class="required" for="signup_firstname">'.$firstname_label.'</label>
				<input type="text" class="required" id="signup_firstname" name="signup_firstname" value="'.((isset($values['signup_firstname'])) ? stripslashes(esc_attr($values['signup_firstname'])) : '').'" '.$readonly_first.' required />
			</p>
			<p>
				<label class="required" for="signup_lastname">'.$lastname_label.'</label>
				<input type="text" class="required" id="signup_lastname" name="signup_lastname" value="'.((isset($values['signup_lastname'])) ? stripslashes(esc_attr($values['signup_lastname'])) : '').'" '.$readonly_last.' required />
			</p>
			<p>
				<label class="required" for="signup_email">'.$email_label.'</label>
				<input type="email" class="required email" id="signup_email" name="signup_email" value="'.((isset($values['signup_email'])) ? esc_attr($values['signup_email']) : '').'" '.$readonly_email.' required />
			</p>
			<p>
				<label class="required" for="signup_validate_email">'.$validate_email_label.'</label>
				<input type="email" class="required email" id="signup_validate_email" name="signup_validate_email" value="'.((isset($values['signup_validate_email'])) ? esc_attr($values['signup_validate_email']) : '').'" '.$readonly_email.' required />
			</p>';
        if( false == $this->main_options['no_phone'] ) {
            $form .= '
            <p>
                <label class="'.esc_attr($phone_required).'" for="signup_phone">'.$phone_label.'</label>
                <input type="tel" class="phone '.$phone_required.'" id="signup_phone" name="signup_phone" value="'.((isset($values['signup_phone'])) ? esc_attr($values['signup_phone']) : '').'" '.esc_attr($phone_required).' />
            </p>';
        }

        $form .= apply_filters( 'pta_sus_signup_form_before_details_field', '', $task, $date );

        // Get the remaining fields, whether or not they are signed in

        // If details are needed for the task, show the field to fill in details.
        // Otherwise don't show the field, but fill it with a blank space
        if ($task->need_details == "YES") {
            $form .= '
            <p>
			    <label class="'.esc_attr($details_required).'" for="signup_item">'.esc_html($task->details_text).'</label>
			    <input type="text" id="signup_item" name="signup_item" value="'.((isset($posted['signup_item'])) ? stripslashes(esc_attr($posted['signup_item'])) : '').'" '.esc_attr($details_required).' />
		    </p>';
        }
        if ($task->enable_quantities == "YES") {
            $form .= '<p>';
            $available = $this->data->get_available_qty($task_id, $date, $task->qty);
            if ($available > 1) {
                $form .= '<label class="required" for="signup_item_qty">'.esc_html( apply_filters( 'pta_sus_public_output', sprintf(__('Item QTY (1 - %d): ', 'pta_volunteer_sus'), (int)$available), 'item_quantity_input_label', (int)$available ) ).'</label>
                <input type="text" id="signup_item_qty" name="signup_item_qty" value="'.((isset($posted['signup_item_qty'])) ? (int)($posted['signup_item_qty']) : '').'" />';
            } elseif ( 1 == $available) {
                $form .= '<strong>'.apply_filters( 'pta_sus_public_output', __('Only 1 remaining! Your quantity will be set to 1.', 'pta_volunteer_sus'), 'only_1_remaining' ).'</strong>';
                $form .= '<input type="hidden" name="signup_item_qty" value="1" />';
            }
            $form .= '</p>';
        } else {
            $form .= '<input type="hidden" name="signup_item_qty" value="1" />';
        }

        $form .= apply_filters( 'pta_sus_signup_form_after_details_field', '', $task, $date );

        // Spam check and form submission
        $go_back_args = array('task_id' => false, 'date' => false, 'sheet_id' => $task->sheet_id);
        $go_back_url = apply_filters( 'pta_sus_signup_goback_url', add_query_arg($go_back_args) );
        $form .= '
			<div style="visibility:hidden"> 
	            <input name="website" type="text" size="20" />
	        </div>
	        <p class="submit">
	            <input type="hidden" name="signup_date" value="'.esc_attr($date).'" />
                <input type="hidden" name="allow_duplicates" value="'.$task->allow_duplicates.'" />
	            <input type="hidden" name="signup_task_id" value="'.esc_attr($task_id).'" />
	        	<input type="hidden" name="pta_sus_form_mode" value="submitted" />
	        	<input type="submit" name="Submit" class="button-primary" value="'.esc_attr( apply_filters( 'pta_sus_public_output', __('Sign me up!', 'pta_volunteer_sus'), 'signup_button_text' ) ).'" />
	            <a class="pta-sus-link go-back" href="'.esc_url($go_back_url).'">'.esc_html( apply_filters( 'pta_sus_public_output', __('&laquo; go back to the Sign-Up Sheet', 'pta_volunteer_sus'), 'go_back_to_signup_sheet_text' ) ).'</a>
	        </p>
            ' . wp_nonce_field('pta_sus_signup','pta_sus_signup_nonce', true, false) . '
		</form>
		';
        $form .= '</div>';
        $this->signup_displayed = true; // prevent multiple forms on same page
        return $form;       
	} // Display Sign up form

	/**
    * Enqueue plugin css and js files
    */
    public function add_css_and_js_to_frontend() {

	    if ( ! isset( $this->main_options['disable_css'] ) || false == $this->main_options['disable_css'] ) {
		    wp_register_style( 'pta-sus-style', plugins_url( '../assets/css/style.css', __FILE__ ) );
		    wp_enqueue_style( 'pta-sus-style' );
	    }

	    if ( $this->main_options['enable_signup_search'] && isset( $_GET['task_id'] ) && current_user_can( 'manage_signup_sheets' ) ) {
		    wp_register_style( 'pta-sus-autocomplete', plugins_url( '../assets/css/jquery.autocomplete.css', __FILE__ ) );
		    wp_enqueue_style( 'pta-sus-autocomplete' );
		    wp_enqueue_script( 'jquery-ui-autocomplete' );
		    wp_enqueue_script( 'pta-sus-frontend', plugins_url( '../assets/js/frontend.js', __FILE__ ) );
	    }
    }

} // End of class
/* EOF */
