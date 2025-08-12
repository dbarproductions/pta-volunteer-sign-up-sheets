<?php
/**
* Public pages
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Public {

	private $data;
	private $emails;

    private $all_sheets_uri;
    public $main_options;
    public $email_options;
    public $integration_options;
	public $validation_options;

    public $submitted;
    public $err;
    public $success;

	public $filled = false;
    private $cleared;
    private $messages_displayed = false;
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
	private $show_time = true;
	private $phone_required;
	private $show_full_name = false;
	private $show_phone = false;
	private $show_email = false;
	private $shortcode_id = false;
	private $show_headers = true;
	private $show_date_start = true;
	private $show_date_end = true;
	private $no_global_overlap;

	public $validation_sent = false;
	public $validation_enabled = false;


	/**
	 * @var PTA_SUS_Volunteer $volunteer
	 */
	private $volunteer;

	private $processed = false;
    
    public function __construct() {
		global $pta_sus;
        $this->data = $pta_sus->data;

        $this->all_sheets_uri = add_query_arg(array('sheet_id' => false, 'date' => false, 'signup_id' => false, 'task_id' => false));

	    $this->main_options = get_option( 'pta_volunteer_sus_main_options' );
	    $this->email_options = get_option( 'pta_volunteer_sus_email_options' );
	    $this->integration_options = get_option( 'pta_volunteer_sus_integration_options' );
	    $this->validation_options = get_option( 'pta_volunteer_sus_validation_options' );
		$this->validation_enabled = isset($this->validation_options['enable_validation']) && $this->validation_options['enable_validation'];
        
        $this->phone_required = $this->main_options['phone_required'] ?? true;
	    $this->use_divs = $this->main_options['use_divs'] ?? false;
	    $this->show_full_name = $this->main_options['show_full_name'] ?? false;
	    $this->suppress_duplicates = $this->main_options['suppress_duplicates'] ?? true;
	    $this->no_global_overlap = $this->main_options['no_global_overlap'] ?? false;

    } // Construct

	public function init() {
		$this->volunteer = new PTA_SUS_Volunteer(get_current_user_id());
		$this->set_up_filters();
		$this->process_signup_form();
		if($this->validation_enabled) {
			$this->process_validation_form();
			$this->maybe_validate_volunteer();
		}


		// Get any messages save in cookies if we did a redirect
		if(isset($_COOKIE['pta_sus_messages'])) {
			$messages = json_decode(stripslashes($_COOKIE['pta_sus_messages']), true);
			if($messages) {
				foreach($messages as $msg) {
					PTA_SUS_Messages::add_message($msg);
				}
			}
			setcookie('pta_sus_messages', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
		}

		if(isset($_COOKIE['pta_sus_errors'])) {
			$errors = json_decode(stripslashes($_COOKIE['pta_sus_errors']), true);
			if($errors) {
				foreach($errors as $error) {
					PTA_SUS_Messages::add_error($error);
				}
			}
			setcookie('pta_sus_errors', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
		}


		add_shortcode('pta_sign_up_sheet', array($this, 'display_sheet'));
		add_shortcode('pta_user_signups', array($this, 'process_user_signups_shortcode'));
		add_shortcode('pta_validation_form', array($this, 'process_validation_form_shortcode'));

		do_action('pta_sus_public_init');

	}

    public function set_up_filters() {
        // Set up some public output strings used by multiple functions
        $this->task_item_header = apply_filters( 'pta_sus_public_output', __('Task/Item', 'pta-volunteer-sign-up-sheets'), 'task_item_header' );
        $this->start_time_header = apply_filters( 'pta_sus_public_output', __('Start Time', 'pta-volunteer-sign-up-sheets'), 'start_time_header' );
        $this->end_time_header = apply_filters( 'pta_sus_public_output', __('End Time', 'pta-volunteer-sign-up-sheets'), 'end_time_header' );
        $this->item_details_header = apply_filters( 'pta_sus_public_output', __('Item Details', 'pta-volunteer-sign-up-sheets'), 'item_details_header' );
        $this->item_qty_header = apply_filters( 'pta_sus_public_output', __('Item Qty', 'pta-volunteer-sign-up-sheets'), 'item_qty_header' );
        $this->na_text = apply_filters( 'pta_sus_public_output', __('N/A', 'pta-volunteer-sign-up-sheets'), 'not_applicable_text' );
	    
	    $this->title_header = apply_filters( 'pta_sus_public_output', __('Title', 'pta-volunteer-sign-up-sheets'), 'title_header' );
	    $this->start_date_header = apply_filters( 'pta_sus_public_output', __('Start Date', 'pta-volunteer-sign-up-sheets'), 'start_date_header' );
	    $this->end_date_header = apply_filters( 'pta_sus_public_output', __('End Date', 'pta-volunteer-sign-up-sheets'), 'end_date_header' );
	    $this->open_spots_header = apply_filters( 'pta_sus_public_output', __('Open Spots', 'pta-volunteer-sign-up-sheets'), 'open_spots_header' );
	    $this->date_header = apply_filters( 'pta_sus_public_output', __('Date', 'pta-volunteer-sign-up-sheets'), 'date_header' );
	    $this->no_contact_message = apply_filters( 'pta_sus_public_output', __('No Event Chair contact info provided', 'pta-volunteer-sign-up-sheets'), 'no_contact_message' );
	    $this->contact_label = apply_filters( 'pta_sus_public_output', __('Contact:', 'pta-volunteer-sign-up-sheets'), 'contact_label' );
	    
	    $this->hidden = '';
	    // Allow admin or volunteer managers to view hidden sign up sheets
	    if (current_user_can( 'manage_options' ) || current_user_can( 'manage_signup_sheets' )) {
		    $this->show_hidden = true;
		    $this->hidden = '<br/><span class="pta-sus-hidden">'.apply_filters( 'pta_sus_public_output', '(--'.__('Hidden!', 'pta-volunteer-sign-up-sheets').'--)', 'hidden_notice' ).'</span>';
	    }

		// actions for other plugins to use for functions that are currently in this class
	    add_filter('pta_sus_validate_signup', array($this, 'extension_validate_signup_form_fields'), 10, 2);
		add_filter('pta_sus_add_signup',array($this, 'extension_add_signup'), 10, 3);
    }

	private function maybe_validate_volunteer() {
		if(empty($_GET['pta-sus-action'])) return;
		if(!$this->validation_enabled) return;
		$action = sanitize_text_field($_GET['pta-sus-action']);
		$this->success = false;
		if('clear_validation' === $action) {
			if(!wp_verify_nonce($_GET['pta-sus-clear-validation-nonce'], 'pta-sus-clear-validation')) {
				PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Sorry! Your security nonce did not verify!', 'pta-volunteer-sign-up-sheets'), 'nonce_error_message' ));
				return;
			}
			pta_clear_validated_user_cookie();
			PTA_SUS_Messages::add_message(apply_filters( 'pta_sus_public_output', __('Your validation code has been cleared.', 'pta-volunteer-sign-up-sheets'), 'validation_cleared_message' ));
			setcookie(
				'pta_sus_validation_cleared',
				'1',
				time() + (120),
				COOKIEPATH,
				COOKIE_DOMAIN,
				is_ssl(),
				true
			);
			pta_clean_redirect();
			return;
		}
		$code = !empty($_GET['code']) ? sanitize_text_field($_GET['code']) : false;
		if(!$code) return;
		$user_info = pta_validate_code($code);
		if(empty($user_info)) {
			PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Invalid or Expired Code','pta-volunteer-sign-up-sheets'),'validation_invalid_code_error' ));
			return;
		}
		$firstname = sanitize_text_field($user_info->firstname);
		$lastname = sanitize_text_field($user_info->lastname);
		$email = sanitize_email($user_info->email);

		if('validate_user' === $action) {
			PTA_SUS_Messages::add_message(apply_filters( 'pta_sus_public_output', __('User Validation successful.','pta-volunteer-sign-up-sheets'),'user_validation_success_message' ));
			$this->success = true;
			pta_set_validated_user_cookie($firstname, $lastname, $email);
		}
		if('validate_signup' === $action) {
			if(empty($_GET['validate_signup_id'])) {
				PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Missing Signup ID.','pta-volunteer-sign-up-sheets'),'signup_validation_signup_id_error' ));
				return;
			}
			$signup_id = absint($_GET['validate_signup_id']);
			$signup = PTA_SUS_Signup_Functions::get_signup($signup_id);
			if(empty($signup)) {
				PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Signup not found.','pta-volunteer-sign-up-sheets'),'signup_validation_signup_not_found_error' ));
				return;
			}
			if($signup->firstname === $firstname && $signup->lastname === $lastname && $signup->email === $email) {
				$validated = PTA_SUS_Signup_Functions::validate_signup($signup_id);
				if($validated) {
					PTA_SUS_Messages::add_message(apply_filters( 'pta_sus_public_output', __('Signup validated.','pta-volunteer-sign-up-sheets'),'signup_validation_success_message' ));
					pta_set_validated_user_cookie($firstname, $lastname, $email);
					$this->success = true;
					$emails = new PTA_SUS_Emails();
					if ($emails->send_mail($signup_id) === false) {
						PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('ERROR SENDING EMAIL', 'pta-volunteer-sign-up-sheets'), 'email_send_error_message' ));
					}
					pta_clean_redirect();
				} else {
					PTA_SUS_Messages::add_error( apply_filters( 'pta_sus_public_output', __( 'Signup validation failed.', 'pta-volunteer-sign-up-sheets' ), 'signup_validation_failed_error' ) );
				}
			} else {
				PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Signup info does not match validation code info.','pta-volunteer-sign-up-sheets'),'signup_validation_info_mismatch_error' ));
			}
		}
	}

	/**
	 * Processes the validation form submission and sends validation email
	 */
	private function process_validation_form() {
		if(isset($_POST['pta-sus-validate-form-submit']) && !$this->validation_sent) {
			if(!wp_verify_nonce($_POST['pta-sus-validate-form-nonce'], 'pta-sus-validate-form')) {
				PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Form Validation Failed!','pta-volunteer-sign-up-sheets'),'validation_form_nonce_error' ));
				return;
			}
			if(empty($_POST['firstname']) || empty($_POST['lastname']) || empty($_POST['email'])) {
				PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output',__('Please complete all required fields.', 'pta-volunteer-sign-up-sheets'), 'required_fields_error_message' ));
				return;
			}
			$firstname = sanitize_text_field($_POST['firstname']);
			$lastname = sanitize_text_field($_POST['lastname']);
			$email = sanitize_email($_POST['email']);
			if(empty($this->emails)) {
				$this->emails = new PTA_SUS_Emails();
			}
			if($this->emails->send_user_validation_email($firstname, $lastname, $email)) {
				PTA_SUS_Messages::add_message(apply_filters( 'pta_sus_public_output', __('Validation email sent.','pta-volunteer-sign-up-sheets'),'validation_email_sent_message' ));
				$this->validation_sent = true;
				$resubmit = $this->validation_options['validation_form_resubmission_minutes'] ?? 1;
				$resubmit_time = time() + (absint($resubmit) * 60);
				// When form is submitted, set a cookie
				setcookie(
					'pta_sus_validation_form_submitted',
					'1',
					$resubmit_time,
					COOKIEPATH,
					COOKIE_DOMAIN,
					is_ssl(),
					true
				);

			} else {
				PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Validation email failed to send.','pta-volunteer-sign-up-sheets'),'validation_email_send_error' ));
			}
		}

	}

	public function validate_signup_form_fields($posted) {
		// Moved these for compatibility with check-in extension to allow automatically adding extra slots
		$signup_task_id = $posted['signup_task_id'];
		do_action( 'pta_sus_before_add_signup', $posted, $signup_task_id);

		$task = $this->data->get_task(intval($posted['signup_task_id']));
		$sheet = $this->data->get_sheet(intval($task->sheet_id));

		$details_required = isset($task->details_required) && "YES" == $task->details_required;

		$available = $this->data->get_available_qty($task->id, $posted['signup_date'], $task->qty);

		// Allow extensions to modify available slots - e.g. allow waitlist extension to add extra available spots so signup can be processed
		$available = apply_filters('pta_sus_process_signup_available_slots', $available, $posted, $sheet, $task);

		if($available < 1) {
			$this->err++;
			$this->filled = true;
			PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('All spots have already been filled.', 'pta-volunteer-sign-up-sheets'), 'no_spots_available_signup_error_message' ));
		}

		//Error Handling
		if (
			empty(sanitize_text_field($posted['signup_firstname']))
			|| empty(sanitize_text_field($posted['signup_lastname']))
			|| empty($posted['signup_email'])
			|| empty($posted['signup_validate_email'])
			|| ( ! $this->main_options['no_phone'] && empty($posted['signup_phone']) && $this->phone_required)
			|| ("YES" == $task->need_details && $details_required && '' === sanitize_text_field($posted['signup_item']) )
			|| ("YES" == $task->enable_quantities && !isset($posted['signup_item_qty']))
		) {
			$this->err++;
			PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Please complete all required fields.', 'pta-volunteer-sign-up-sheets'), 'required_fields_error_message' ));
		}

		// Check for non-allowed characters
		elseif (! $this->data->check_allowed_text(stripslashes($posted['signup_firstname'])))
		{
			$this->err++;
			PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Invalid Characters in First Name!  Please try again.', 'pta-volunteer-sign-up-sheets'), 'firstname_error_message' ));
		}
		elseif (! $this->data->check_allowed_text(stripslashes($posted['signup_lastname'])))
		{
			$this->err++;
			PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Invalid Characters in Last Name!  Please try again.', 'pta-volunteer-sign-up-sheets'), 'lastname_error_message' ));
		}
		elseif ( !is_email( trim($posted['signup_email']) ) )
		{
			$this->err++;
			PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Invalid Email!  Please try again.', 'pta-volunteer-sign-up-sheets'), 'email_error_message' ));
		}
		elseif ( trim($posted['signup_email']) != trim($posted['signup_validate_email'])  )
		{
			$this->err++;
			PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Email and Confirmation Email do not match!  Please try again.', 'pta-volunteer-sign-up-sheets'), 'confirmation_email_error_message' ));
		}
		elseif ( ! $this->main_options['no_phone'] && preg_match("/[^0-9\-\.\(\)\ \+]/", $posted['signup_phone']))
		{
			$this->err++;
			PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Invalid Characters in Phone Number!  Please try again.', 'pta-volunteer-sign-up-sheets'), 'phone_error_message' ));
		}
		elseif ( "YES" == $task->need_details && ! $this->data->check_allowed_text(stripslashes($posted['signup_item'])))
		{
			$this->err++;
			PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Invalid Characters in Signup Item!  Please try again.', 'pta-volunteer-sign-up-sheets'), 'item_details_error_message' ));
		}
		elseif ( "YES" == $task->enable_quantities && (! $this->data->check_numbers($posted['signup_item_qty']) || (int)$posted['signup_item_qty'] < 1 || (int)$posted['signup_item_qty'] > $available ) )
		{
			$this->err++;
			PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', sprintf(__('Please enter a number between 1 and %d for Item QTY!', 'pta-volunteer-sign-up-sheets'), (int)$available), 'item_quantity_error_message', $available ));
		}
		elseif (!$this->data->check_date($posted['signup_date']))
		{
			$this->err++;
			PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Hidden signup date field is invalid!  Please try again.', 'pta-volunteer-sign-up-sheets'), 'signup_date_error_message' ));
		}
		// Allow extensions to bypass duplicate checks
		$perform_duplicate_checks = apply_filters('pta_sus_perform_duplicate_checks', true, $task, $sheet);
		if($perform_duplicate_checks) {
			// If no errors so far, Check for duplicate signups if not allowed
			if (!$this->err && 'NO' == $task->allow_duplicates) {
				if( $this->data->check_duplicate_signup( $posted['signup_task_id'], $posted['signup_date'], $posted['signup_firstname'], $posted['signup_lastname']) ) {
					$this->err++;
					PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('You are already signed up for this task!', 'pta-volunteer-sign-up-sheets'), 'signup_duplicate_error_message' ));
				}
			}
			if (!$sheet->duplicate_times && !$this->err && $this->data->check_duplicate_time_signup($sheet, $task, $posted['signup_date'], $posted['signup_firstname'], $posted['signup_lastname'])) {
				$this->err++;
				PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('You are already signed up for another task in this time frame!', 'pta-volunteer-sign-up-sheets'), 'signup_duplicate_time_error_message' ));
			}
			if ($this->no_global_overlap && !$this->err && $this->data->check_duplicate_time_signup($sheet, $task, $posted['signup_date'], $posted['signup_firstname'], $posted['signup_lastname'], $check_all = true)) {
				$this->err++;
				PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('You are already signed up for another task in this time frame!', 'pta-volunteer-sign-up-sheets'), 'signup_duplicate_time_error_message' ));
			}
		}
		return ! ( $this->err > 0 );
	}

	public function extension_validate_signup_form_fields($valid,$posted) {
		return $this->validate_signup_form_fields($posted);
	}

	public function add_signup($posted, $signup_task_id, $redirect = true) {
		$validate_signups =  $this->validation_enabled && isset($this->validation_options['enable_signup_validation']) && $this->validation_options['enable_signup_validation'];
		$posted['signup_validated'] = $validate_signups ? $this->volunteer->is_validated() : 1;
		$needs_validation = ($this->validation_enabled && $validate_signups && !$this->volunteer->is_validated());
		// Allow extensions to bypass adding signup to main database
		if( apply_filters( 'pta_sus_signup_add_signup_to_main_database', true, $posted, $signup_task_id ) ) {
			$signup_id=$this->data->add_signup($posted,$signup_task_id);
			if ( $signup_id === false) {
				$this->err++;
				PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Error adding signup record.  Please try again.', 'pta-volunteer-sign-up-sheets'), 'add_signup_database_error_message' ));
				return false;
			} else {
				do_action( 'pta_sus_after_add_signup', $posted,$posted['signup_task_id'], $signup_id);
				if(!class_exists('PTA_SUS_Emails')) {
					include_once(dirname(__FILE__).'/class-pta_sus_emails.php');
				}
				if(empty($this->emails)) {
					$this->emails = new PTA_SUS_Emails();
				}

				$this->success = true;
				if($needs_validation) {
					$sent = $this->emails->send_mail($signup_id,false,false,false,'validate_signup');
					if($sent) {
						PTA_SUS_Messages::add_message(apply_filters( 'pta_sus_public_output', __('Signup Validation email sent.','pta-volunteer-sign-up-sheets'), 'signup_validation_sent_message' ));
						$this->validation_sent = true;
					} else {
						PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Signup Validation email failed to send.','pta-volunteer-sign-up-sheets'), 'signup_validation_send_error_message' ));
					}
				} else {
					PTA_SUS_Messages::add_message(apply_filters( 'pta_sus_public_output', __('You have been signed up!', 'pta-volunteer-sign-up-sheets'), 'signup_success_message' ),true);
					$sent = $this->emails->send_mail(intval($signup_id));
					if (!$sent) {
						PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('ERROR SENDING EMAIL', 'pta-volunteer-sign-up-sheets'), 'email_send_error_message' ));
					}
				}

				// only redirect if not doing ajax - so we don't break calendar popup signups
				// allow extension to bypass redirect
				$redirect = apply_filters('pta_sus_public_redirect_after_signup', $redirect, $posted, $signup_task_id);
				if($redirect && !defined('DOING_AJAX')) {
					pta_clean_redirect();
				} else {
					return $signup_id;
				}

			}
		} else {
			// for extensions that bypass adding the signup to main database
			// allow extensions to process signup after all validation and set success
			do_action('pta_sus_signup_database_bypass', $posted, $signup_task_id);
			$this->success = apply_filters('pta_sus_signup_database_bypass_success', $this->success, $posted, $signup_task_id);

			return $this->success;
		}
	}

	public function extension_add_signup($signup_id, $posted, $signup_task_id) {
		return $this->add_signup($posted, $signup_task_id);
	}

    public function process_signup_form() {
		if(is_admin() && !wp_doing_ajax()) return;
        
        $this->submitted = (isset($_POST['pta_sus_form_mode']) && $_POST['pta_sus_form_mode'] == 'submitted');
        $this->err = 0;
        $this->success = false;
        $this->messages_displayed = false; // reset
	    $this->cleared = false;
        
        // Process Sign-up Form
        if ($this->submitted && !$this->processed) {
			$this->processed = true;
            // NONCE check
            if ( ! isset( $_POST['pta_sus_signup_nonce'] ) || ! wp_verify_nonce( $_POST['pta_sus_signup_nonce'], 'pta_sus_signup' ) ) {
                $this->err++;
                PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Sorry! Your security nonce did not verify!', 'pta-volunteer-sign-up-sheets'), 'nonce_error_message' ));
                return false;
            }
            // Check for spambots
            if (!empty($_POST['website'])) {
                $this->err++;
                PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Oops! You filled in the spambot field. Please leave it blank and try again.', 'pta-volunteer-sign-up-sheets'), 'spambot_error_message' ));
                return false;
            }
	
	        // Give other plugins a chance to modify signup data
	        $posted = apply_filters('pta_sus_signup_posted_values', $_POST);

	        $this->validate_signup_form_fields($posted);
			$signup_task_id = $posted['signup_task_id'];
	        $task = $this->data->get_task(intval($posted['signup_task_id']));
	        $sheet = $this->data->get_sheet(intval($task->sheet_id));

            // Allow other plugins to validate
	        $this->err = apply_filters('pta_sus_signup_form_error_count' , $this->err, $posted, $task, $sheet);
            
            // Add Signup
            if (absint($this->err) < 1) {
				$this->add_signup($posted, $signup_task_id,$task, $sheet);
            } else {
				// allow other plugins to do something if there were errors
	            do_action('pta_sus_signup_form_errors', $this->err, $posted, $signup_task_id);
            }
            
        }

	    // Check if they clicked on a CLEAR link
	    if (isset($_GET['signup_id']) && $_GET['signup_id'] > 0 ) {
			// Verify Nonce
		    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'pta_sus_clear_signup' ) ) {
			    wp_die( apply_filters( 'pta_sus_public_output', __('Security check failed!', 'pta-volunteer-sign-up-sheets'), 'clear_invalid_nonce_message' ) );
		    }
			if(!$this->volunteer->is_validated()) {
				PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('You need to be validated first!', 'pta-volunteer-sign-up-sheets'), 'clear_not_validated_message' ));
			}

		    // Make sure the signup exists first
		    $signup=$this->data->get_signup((int)$_GET['signup_id']);
		    if (null == $signup) {
			    PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Not a valid signup!', 'pta-volunteer-sign-up-sheets'), 'clear_invalid_signup_error_message' ));
		    } elseif (!$this->volunteer->can_modify_signup($signup)) {
			    PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('You are not allowed to do that!', 'pta-volunteer-sign-up-sheets'), 'clear_not_allowed_error_message' ));
		    } else {
			    // Send cleared emails
			    if(!class_exists('PTA_SUS_Emails')) {
				    include_once(dirname(__FILE__).'/class-pta_sus_emails.php');
			    }
			    $emails = new PTA_SUS_Emails();
			    $emails->send_mail((int)$_GET['signup_id'], $reminder=false, $clear=true);
			    $cleared = $this->data->delete_signup((int)$_GET['signup_id']);
			    if ($cleared) {
				    PTA_SUS_Messages::add_message(apply_filters( 'pta_sus_public_output', __('Signup Cleared', 'pta-volunteer-sign-up-sheets'), 'signup_cleared_message' ),true);
				    $this->cleared = true;
				    do_action('pta_sus_signup_cleared', $signup);
				    if(!defined('DOING_AJAX')) {
					    pta_clean_redirect();
				    } else {
					    return $cleared;
				    }
			    } else {
				    PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('ERROR clearing signup!', 'pta-volunteer-sign-up-sheets'), 'error_clearing_signup_message' ));
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
		    $return .= '<div class="pta-sus-sheets-table pta-sus">';
		    ob_start();
		    include(PTA_VOLUNTEER_SUS_DIR.'views/sheets-view-divs-header-row-html.php');
		    $return .= ob_get_clean();
	    } else {
		    $return .= '<table class="pta-sus pta-sus-sheets"><thead class="pta-sus-table-head">';
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
		    $ongoing_label = apply_filters( 'pta_sus_public_output', __('Ongoing', 'pta-volunteer-sign-up-sheets'), 'ongoing_event_type_start_end_label' );
		    $view_signup_text = apply_filters( 'pta_sus_public_output', __('View &amp; sign-up &raquo;', 'pta-volunteer-sign-up-sheets'), 'view_and_signup_link_text' );
		    $sheet_filled_text = apply_filters( 'pta_sus_public_output', __('Filled', 'pta-volunteer-sign-up-sheets'), 'sheet_filled_text' );
		
		    if($sheet->no_signups) {
			    $open_spots = 1;
			    $view_signup_text = apply_filters( 'pta_sus_public_output', __('View Event &raquo;', 'pta-volunteer-sign-up-sheets'), 'view_event_link_text' );
		    }
		
		    $view_signup_text = apply_filters( 'pta_sus_view_signup_text_for_sheet', $view_signup_text, $sheet );

		    $title = '<a class="pta-sus-link view" href="'.esc_url($sheet_url).'">'.esc_html($sheet->title).'</a>'.$is_hidden;
		    $start_date = ($sheet->first_date == '0000-00-00') ? $ongoing_label : pta_datetime(get_option('date_format'), strtotime($sheet->first_date));
		    $end_date = ($sheet->last_date == '0000-00-00') ? $ongoing_label : pta_datetime(get_option('date_format'), strtotime($sheet->last_date));
		    $view_link = ($open_spots > 0) ? '<a class="pta-sus-link view" href="'.esc_url($sheet_url).'">'.esc_html( $view_signup_text ).'</a>' : '&#10004; '.esc_html( $sheet_filled_text );
			// allow extensions to modify the view link
		    $view_link = apply_filters('pta_sus_view_link_for_sheet', $view_link, $sheet, $open_spots );

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
		    $return .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __("Sign-up sheet not found.", 'pta-volunteer-sign-up-sheets'), 'sheet_not_found_error_message' ).'</p>';
		    return $return;
	    } else {
		    // Check if the sheet is visible and don't show unless it's an admin user
		    if ( $sheet->trash || ( ! apply_filters( 'pta_sus_public_sheet_visible', $sheet->visible, $sheet ) && !current_user_can( 'manage_signup_sheets' ) ) ) {
			    $return .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __("You don't have permission to view this page.", 'pta-volunteer-sign-up-sheets'), 'no_permission_to_view_error_message' ).'</p>';
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
			    $view_all_text = apply_filters('pta_sus_public_output', __('&laquo; View all Sign-up Sheets', 'pta-volunteer-sign-up-sheets'), 'view_all_sheets_link_text');
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
				    $names = explode( ',', sanitize_text_field($sheet->chair_name));
				    $count = count($names);
				    if ( $count > 1) {
					    $display_chair = apply_filters( 'pta_sus_public_output', __('Event Chairs:', 'pta-volunteer-sign-up-sheets'), 'event_chairs_label_plural') .' <a class="pta-sus-link contact" href="mailto:'.esc_attr($sheet->chair_email).'">'.esc_html($chair_names).'</a>';
				    } elseif ( 1 == $count && '' != $sheet->chair_name && '' != $sheet->chair_email ) {
					    $display_chair = apply_filters( 'pta_sus_public_output', __('Event Chair:', 'pta-volunteer-sign-up-sheets'), 'event_chair_label_singular') .' <a class="pta-sus-link contact" href="mailto:'.esc_attr($sheet->chair_email).'">'.esc_html($chair_names).'</a>';
				    } else {
					    $display_chair = esc_html( $this->no_contact_message );
				    }
			    }
			
			    $display_chair = apply_filters( 'pta_sus_display_chair_contact', $display_chair, $sheet );
			    
			    $return .= '
                        <div class="pta-sus-sheet">
                            <h2>'.esc_html($sheet->title).'</h2>
                    ';
			    if ( ! $this->main_options['hide_contact_info'] ) {
				    $return .= '<h2>'.$display_chair.'</h2>';
			    }
		    } else {
			    $return .= '<div class="pta-sus-sheet">';
		    }
		    if ( ! $sheet->visible && current_user_can( 'manage_signup_sheets' ) ) {
			    $return .= '<p class="pta-sus-hidden">'.apply_filters( 'pta_sus_public_output', __('This sheet is currently hidden from the public.', 'pta-volunteer-sign-up-sheets'), 'sheet_hidden_message' ).'</p>';
		    }
		
		    // Display Sign-up Form
		    if (!$this->submitted || $this->err) {
			    if (isset($_GET['task_id']) && isset($_GET['date'])) {
				    do_action('pta_sus_before_display_signup_form', $_GET['task_id'], $_GET['date'] );
				    return $this->display_signup_form($_GET['task_id'], $_GET['date']);
			    }
		    }

		    $return .= apply_filters( 'pta_sus_single_sheet_display_before_details', '', $sheet );
		
		    // Sheet Details
		    if (!$this->submitted || $this->success || $this->err) {
			
			    // Make sure there are some future dates before showing anything
			    if($future_dates) {
				    // Only show details if there is something to show, and show headers is true
				    if('' != $sheet->details && $this->show_headers) {
					    $return .= '<h3 class="pta-sus details-header">'.apply_filters( 'pta_sus_public_output', __('DETAILS:', 'pta-volunteer-sign-up-sheets'), 'sheet_details_heading' ).'</h3>';
					    $return .= wp_kses_post($sheet->details);
				    }
				    $open_spots = ($this->data->get_sheet_total_spots($sheet->id) - $this->data->get_sheet_signup_count($sheet->id));
				    if ($open_spots > 0 && !$sheet->no_signups) {
					    $return .= '<h3 class="pta-sus sign-up-header">'.apply_filters( 'pta_sus_public_output', __('Sign up below...', 'pta-volunteer-sign-up-sheets'), 'sign_up_below' ).'</h3>';
				    } elseif (!$sheet->no_signups) {
					    $return .= '<h3 class="pta-sus filled-header">'.apply_filters( 'pta_sus_public_output', __('All spots have been filled.', 'pta-volunteer-sign-up-sheets'), 'sheet_all_spots_filled' ).'</h3>';
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
		if(!$this->volunteer->is_validated()) {
			if( $this->validation_enabled && isset($this->validation_options['enable_user_validation_form']) && $this->validation_options['enable_user_validation_form'] ) {
				if ( (isset($_COOKIE['pta_sus_validation_form_submitted']) && empty($_COOKIE['pta_sus_validation_cleared'])) || $this->validation_sent ) {
					$minutes = $this->validation_options['validation_form_resubmission_minutes'] ?? 1;
					$return .= '<p>'.apply_filters( 'pta_sus_public_output', sprintf(__('User Validation email has been sent. Please check your email. If you did not receive the email, you can return and submit the form again after %d minutes.', 'pta-volunteer-sign-up-sheets'),$minutes),'validation_form_already_submitted_message', absint($minutes)).'</p>';
					return $return;
				}
				return pta_get_validation_form();
			}
		}
		$signups = $this->volunteer->get_detailed_signups();
		/**
	     * @var string $show_time
	     */
	    extract( shortcode_atts( array(
		    'show_time' => 'yes'
	    ), $atts, 'pta_user_signups' ) );
	    $times = 'no' !== $show_time;
	    $details = !$this->main_options['hide_signups_details_qty'];

	    if (!empty($signups)) {
		    $return .= apply_filters( 'pta_sus_before_user_signups_list_headers', '' );
		    $return .= '<h3 class="pta-sus user-heading">'.apply_filters( 'pta_sus_public_output', __('You have signed up for the following', 'pta-volunteer-sign-up-sheets'), 'user_signups_list_headers_h3' ).'</h3>';
		    $return .= '<h4 class="pta-sus user-heading">'.apply_filters( 'pta_sus_public_output', __('Click on Clear to remove yourself from a signup.', 'pta-volunteer-sign-up-sheets'), 'user_signups_list_headers_h4' ).'</h4>';
		    $return .= apply_filters( 'pta_sus_before_user_signups_list_table', '' );
		    $return .= '<div class="pta-sus-sheets user">';
		    
		    if($this->use_divs) {
			    $return .= '<div class="pta-sus-sheets-table">
                            <div class="pta-sus-sheets-row pta-sus-header-row">
                                <div class="column-title head">'.esc_html($this->title_header).'</div>
                                <div class="column-date head">'.esc_html($this->date_header).'</div>
                                <div class="column-task head">'.esc_html($this->task_item_header).'</div>';
		    } else {
			    $return .= '
                        <table class="pta-sus pta-sus-sheets">
                        <thead class="pta-sus-table-head">
                            <tr class="pta-sus pta-sus-header-row">
                                <th class="column-title">'.esc_html($this->title_header).'</th>
                                <th class="column-date">'.esc_html($this->date_header).'</th>
                                <th class="column-task">'.esc_html($this->task_item_header).'</th>';
		    }
		
		    $return .= apply_filters( 'pta_sus_user_signups_list_headers_after_task', '', $this->use_divs );
		    
		    if ($times) {
			    if($this->use_divs) {
				    $return .='
                                <div class="column-start-time head" >'.esc_html($this->start_time_header).'</div>
                                <div class="column-end-time head" >'.esc_html($this->end_time_header).'</div>';
			    } else {
				    $return .='
                                <th class="column-time start" >'.esc_html($this->start_time_header).'</th>
                                <th class="column-time end" >'.esc_html($this->end_time_header).'</th>';
			    }
		    }

		    if($details) {
		    	if($this->use_divs) {
				    $return .= '
	                                <div class="column-details head" >'.esc_html($this->item_details_header).'</div>
	                                <div class="column-qty head" >'.esc_html($this->item_qty_header).'</div>';
			    } else {
				    $return .= '
	                                <th class="column-details" >'.esc_html($this->item_details_header).'</th>
	                                <th class="column-qty" >'.esc_html($this->item_qty_header).'</th>';
			    }
		    }

		    if($this->use_divs) {
			    $return .= '<div class="column-clear_link head">&nbsp;</div>';
		    } else {
			    $return .= '<th class="column-clear_link">&nbsp;</th>';
		    }

		    $return  .= apply_filters( 'pta_sus_user_signups_list_headers_after_clear', '', $this->use_divs );

		    if($this->use_divs) {
			    $return .= '</div>';
		    } else {
			    $return .= '</tr>';
		    }

		    if(!$this->use_divs) {
			    $return .= '</thead><tbody>';
		    }
		    
		    foreach ($signups as $signup) {
				$sheet = false;
				$url = false;
				if(isset($this->main_options['volunteer_page_id']) && absint($this->main_options['volunteer_page_id']) > 0) {
					$url = get_permalink(absint($this->main_options['volunteer_page_id']));
				}
				if($url && $signup->sheet_id) {
					$sheet_args = array('sheet_id' => $signup->sheet_id, 'date' => false, 'signup_id' => false, 'task_id' => false);
					$sheet = $this->data->get_sheet($signup->sheet_id);
					$url = apply_filters('pta_sus_view_sheet_url', add_query_arg($sheet_args,$url), $sheet);

				}
			
			    if ( $sheet && pta_sus_show_clear($sheet,$signup->signup_date,$signup->time_start) ) {
				    $clear_args = array('sheet_id' => false, 'task_id' => false, 'signup_id' => (int)$signup->id);
				    $raw_clear_url = add_query_arg($clear_args);
					$clear_url = wp_nonce_url( $raw_clear_url, 'pta_sus_clear_signup' );
				    $clear_text = apply_filters( 'pta_sus_public_output', __('Clear', 'pta-volunteer-sign-up-sheets'), 'clear_signup_link_text');
			    } else {
				    $clear_url = '';
				    $clear_text = '';
			    }

				if($url) {
					$title = '<a href="'.$url.'" title="'.esc_attr($signup->title).'">'.esc_html($signup->title).'</a>';
				} else {
					$title = esc_html($signup->title);
				}
				
			    if($this->use_divs) {
				    $return .= '<div class="pta-sus-sheets-row">
                            <div class="column-title">'.$title.'</div>
                            <div class="column-date">'.(($signup->signup_date == "0000-00-00") ? esc_html($this->na_text) : pta_datetime(get_option("date_format"), strtotime($signup->signup_date))).'</div>
                            <div class="column-task" >'.esc_html($signup->task_title).'</div>';
			    } else {
				    $return .= '<tr class="pta-sus signup">
                            <td class="pta-sus title-td" data-label="'.esc_attr($this->title_header).'">'.$title.'</td>
                            <td class="pta-sus date-td" data-label="'.esc_attr($this->date_header).'">'.(($signup->signup_date == "0000-00-00") ? esc_html($this->na_text) : pta_datetime(get_option("date_format"), strtotime($signup->signup_date))).'</td>
                            <td class="pta-sus task-td" data-label="'.esc_attr($this->task_item_header).'">'.esc_html($signup->task_title).'</td>';
			    }
			
			    $return .= apply_filters( 'pta_sus_user_signups_list_content_after_task', '', $signup, $this->use_divs );
			    
			    if ($times) {
				    if($this->use_divs) {
					    $return .='
                            <div class="column-start-time" >'.(("" == $signup->time_start) ? esc_html($this->na_text) : pta_datetime(get_option("time_format"), strtotime($signup->time_start)) ).'</div>
                            <div class="column-end-time" >'.(("" == $signup->time_end) ? esc_html($this->na_text) : pta_datetime(get_option("time_format"), strtotime($signup->time_end)) ).'</div>';
				    } else {
					    $return .='
                            <td class="pta-sus start-time-td" data-label="'.esc_attr($this->start_time_header).'" >'.(("" == $signup->time_start) ? esc_html($this->na_text) : pta_datetime(get_option("time_format"), strtotime($signup->time_start)) ).'</td>
                            <td class="pta-sus end-time-td" data-label="'.esc_attr($this->end_time_header).'" >'.(("" == $signup->time_end) ? esc_html($this->na_text) : pta_datetime(get_option("time_format"), strtotime($signup->time_end)) ).'</td>';
				    }
				    
			    }
			    if($details) {
			    	if($this->use_divs) {
					    $return .= '
	                            <div class="column-item" >'.(("" !== $signup->item) ? esc_html($signup->item) : esc_html($this->na_text) ).'</div>
	                            <div class="column-qty" >'.(("" !== $signup->item_qty) ? (int)$signup->item_qty : esc_html($this->na_text) ).'</div>';
				    } else {
					    $return .= '
	                            <td class="pta-sus item-td" data-label="'.esc_attr($this->item_details_header).'" >'.(("" !== $signup->item) ? esc_html($signup->item) : esc_html($this->na_text) ).'</td>
	                            <td class="pta-sus qty-td" data-label="'.esc_attr($this->item_qty_header).'" >'.(("" !== $signup->item_qty) ? (int)$signup->item_qty : esc_html($this->na_text) ).'</td>';
				    }
			    }
			    if($this->use_divs) {
				    $return .= '<div class="column-clear" ><a class="pta-sus-link clear-signup clear-signup-link" href="'.esc_url($clear_url).'">'.esc_html($clear_text).'</a></div>';
			    } else {
				    $return .= '<td class="pta-sus clear-td" data-label="" ><a class="pta-sus-link clear-signup clear-signup-link" href="'.esc_url($clear_url).'">'.esc_html($clear_text).'</a></td>';
			    }

			    $return .= apply_filters( 'pta_sus_user_signups_list_content_after_clear', '', $signup, $this->use_divs );

			    if($this->use_divs) {
				    $return .= '</div>';
			    } else {
				    $return .= '</tr>';
			    }

		    }
		    
		    if($this->use_divs) {
			    $return .= '</div></div>';
		    } else {
			    $return .= '</tbody></table></div>';
		    }

	    }
	    $return .= apply_filters( 'pta_sus_after_user_signups_list_table', '' );
	    return $return;
    }

	public function maybe_get_clear_validation_message() {
		$return = '';
		if($this->volunteer->is_validated() && !is_user_logged_in() && $this->validation_enabled && isset($this->validation_options['enable_clear_validation']) && $this->validation_options['enable_clear_validation']) {
			$message = $this->validation_options['clear_validation_message'] ?? '';
			if($message) {
				$return .= '<div class="pta-sus clear-validation-message">'. wpautop( $message).'</div>';
			}
			$args = array('pta-sus-action' => 'clear_validation','validate_signup_id' => false, 'code' => false);
			$raw_url = add_query_arg($args);
			$url = wp_nonce_url( $raw_url, 'pta-sus-clear-validation', 'pta-sus-clear-validation-nonce' );
			$link_text = $this->validation_options['clear_validation_link_text'] ?? 'Clear Validation';
			$return .= '<p><a href="'.esc_url($url).'">'.esc_html($link_text).'</a></p>';
		}
		return $return;
	}

    public function process_user_signups_shortcode($atts) {
		$return = PTA_SUS_Messages::show_messages();
	    $this->messages_displayed = true;
		PTA_SUS_Messages::clear_messages();
    	$return .= $this->get_user_signups_list($atts);
    	if(empty($return)) {
    		$return = apply_filters( 'pta_sus_public_output', __('You do not have any current signups', 'pta-volunteer-sign-up-sheets'),'no_user_signups_message');
	    } else {
		    $return .= $this->maybe_get_clear_validation_message();
	    }
	    return $return;
    }

	public function process_validation_form_shortcode($atts) {
		// don't show anything if the system is not enabled
		if(!$this->validation_enabled || !(isset($this->validation_options['enable_user_validation_form']) && $this->validation_options['enable_user_validation_form']) ) {
			return '<p>'.apply_filters( 'pta_sus_public_output', __('User Validation is currently disabled.', 'pta-volunteer-sign-up-sheets'), 'user_validation_disabled_message').'</p>';
		}
		$atts = shortcode_atts(array(
			'hide_when_validated' => 'no'
		), $atts, 'pta_validation_form');
		$return = PTA_SUS_Messages::show_messages();
		$this->messages_displayed = true;
		PTA_SUS_Messages::clear_messages();
		// Return empty if user is validated and hide_when_validated is enabled
		if($this->volunteer->is_validated() && 'yes' === $atts['hide_when_validated']) {
			return '';
		}
		// Return empty if signup form is being displayed, in case they have the validation form displayed on the same page
		if(isset($_GET['task_id'])) {
			return '';
		}
		if(!$this->volunteer->is_validated()) {
			if($this->validation_sent) {
				return $return;
			}
			if (isset($_COOKIE['pta_sus_validation_form_submitted']) && empty($_COOKIE['pta_sus_validation_cleared'])) {
				$minutes = $this->validation_options['validation_form_resubmission_minutes'] ?? 1;
				$return .= '<p>'.apply_filters( 'pta_sus_public_output', sprintf(__('User Validation email has been sent. Please check your email. If you did not receive the email, you can return and submit the form again after %d minutes.', 'pta-volunteer-sign-up-sheets'),$minutes),'validation_form_already_submitted_message', absint($minutes)).'</p>';
				return $return;
			}
			$return .= pta_get_validation_form();
		} elseif(!isset($_GET['pta-sus-action']) || ($_GET['pta-sus-action'] != 'validate_signup' && $_GET['pta-sus-action'] != 'validate_user') ){
			$return .= '<p>' . apply_filters( 'pta_sus_public_output', __( 'You are already validated.', 'pta-volunteer-sign-up-sheets' ), 'already_validated_message' ) . '</p>';
		}
		$return .= $this->maybe_get_clear_validation_message();
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
                $return .= '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('Volunteer Sign-Up Sheets are in TEST MODE', 'pta-volunteer-sign-up-sheets'), 'admin_test_mode_message' ).'</p>';
            } elseif (is_page( $this->main_options['volunteer_page_id'] )) {
                return esc_html($this->main_options['test_mode_message']);
            } else {
                return '';
            }
        }
        if(isset($this->main_options['login_required']) && true === $this->main_options['login_required'] ) {
            if (!is_user_logged_in()) {
                $message = '<p class="pta-sus error">' . esc_html($this->main_options['login_required_message']) . '</p>';
                if(isset($this->main_options['show_login_link']) && true === $this->main_options['show_login_link']) {
                    $message .= '<p><a class="pta-sus-link login" href="'. wp_login_url( get_permalink() ) .'" title="Login">'.apply_filters( 'pta_sus_public_output', __("Login", "pta_volunteer_sus"), 'login_link_text').'</a></p>';
                }
                return $message;
            }
        }
	    if($this->validation_enabled && isset($this->validation_options['require_validation_to_view']) && true === $this->validation_options['require_validation_to_view'] ) {
		    if (!$this->volunteer->is_validated()) {
			    return pta_get_validation_required_message();
		    }
	    }
        extract( shortcode_atts( array(
            'id' => '',
            'date' => '',
            'show_time' => 'yes',
            'show_phone' => 'no',
            'show_email' => 'no',
            'show_headers' => 'yes',
            'show_date_start' => 'yes',
            'show_date_end' => 'yes',
            'order_by' => 'first_date',
            'order' => 'ASC',
            'list_title' => __('Current Volunteer Sign-up Sheets', 'pta-volunteer-sign-up-sheets'),
        ), $atts, 'pta_sign_up_sheet' ) );
	    /**
	     * Variables extracted from shortcode, with above default values
	     * @var mixed $id
	     * @var mixed $date
	     * @var string $show_time
	     * @var string $show_phone
	     * @var string $show_email
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
	        $list_title = __('Current Volunteer Sign-up Sheets', 'pta-volunteer-sign-up-sheets');
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
	    if ( $show_email === 'yes') {
		    $this->show_email = true;
	    } else {
		    $this->show_email = false;
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

		// Give other plugins a chance to create their own output and not go any further
	    $alt_display = apply_filters('pta_sus_main_shortcode_alt_display', '', $atts);
		if(!empty($alt_display)) {
			return $alt_display;
		}

	    // Give other plugins a chance to restrict access to the sheets list
	    if( ! apply_filters( 'pta_sus_can_view_sheets', true, $atts ) ) {
		    PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __("You don't have permission to view this page.", 'pta-volunteer-sign-up-sheets'), 'no_permission_to_view_error_message' ));
		    return PTA_SUS_Messages::show_messages();
	    }

        $return = apply_filters( 'pta_sus_before_display_sheets', $return, $id, $this->date );
        do_action( 'pta_sus_begin_display_sheets', $id, $this->date );

	    if( !$this->messages_displayed || !$this->suppress_duplicates) {
	    	// only show messages above first list if multiple shortcodes on one page
		    $return .= PTA_SUS_Messages::show_messages();
			PTA_SUS_Messages::clear_messages();
		    $this->messages_displayed = true;
	    }
        
        if ($id === false) {
            // Display all active
	        // allow modification of list title header
	        $title_header =  '<h2 class="pta-sus-list-title">'.apply_filters( 'pta_sus_public_output', esc_html($list_title), 'sheet_list_title' ).'</h2>';
			$title_header = apply_filters('pta_sus_sheet_list_title_header_html', $title_header, $list_title);
            $return .= $title_header;
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
                $return .= '<p>'.apply_filters( 'pta_sus_public_output', __('No sheets currently available at this time.', 'pta-volunteer-sign-up-sheets'), 'no_sheets_message' ).'</p>';
            } else {
                $sheets_table = $this->get_sheets_list($sheets, $atts);
                $return .= apply_filters('pta_sus_display_sheets_table', $sheets_table, $sheets);
            }
            
            // If current user has signed up for anything, list their signups and allow them to edit/clear them
            // If they aren't logged in, prompt them to login to see their signup info
	        if( !isset($this->main_options['disable_user_signups']) || ! $this->main_options['disable_user_signups'] ) {
	        	if ( !$this->volunteer->is_validated() ) {
	                if (!$this->main_options['disable_signup_login_notice']) {
	                    $return .= '<p>'. apply_filters( 'pta_sus_public_output', __('Please login to view and edit your volunteer sign ups.', 'pta-volunteer-sign-up-sheets'), 'user_not_loggedin_signups_list_message' ).'</p>';
	                }
					if($this->validation_enabled && isset($this->validation_options['enable_user_validation_form']) && $this->validation_options['enable_user_validation_form']) {
						if ( (isset($_COOKIE['pta_sus_validation_form_submitted']) && empty($_COOKIE['pta_sus_validation_cleared']) || $this->validation_sent) ) {
							$minutes = $this->validation_options['validation_form_resubmission_minutes'] ?? 1;
							$return .= '<p>'.apply_filters( 'pta_sus_public_output', sprintf(__('User Validation email has been sent. Please check your email. If you did not receive the email, you can return and submit the form again after %d minutes.', 'pta-volunteer-sign-up-sheets'),$minutes),'validation_form_already_submitted_message', absint($minutes)).'</p>';
						} else {
							$return .= pta_get_validation_form();
						}

					}
	            } else {
	                $user_signups_list = $this->get_user_signups_list($atts);
	                $return .= apply_filters('pta_sus_display_user_signups_table', $user_signups_list);
			        $return .= $this->maybe_get_clear_validation_message();
	            }
	        }

        } else {
            $return .= $this->get_single_sheet($id);
        }
        $return .= apply_filters( 'pta_sus_after_display_sheets', '', $id, $this->date );
        return $return;
    } // Display Sheet

	public function generate_signup_row_data($signup, $task, $i, $show_names = true, $show_clear=false) {
		$row_data = array();

		if($show_names) {
			if($this->show_full_name) {
				$display_signup = wp_kses_post($signup->firstname.' '.$signup->lastname);
			} else {
				$display_signup = wp_kses_post($signup->firstname.' '.$this->data->initials($signup->lastname));
			}
			$row_data['extra-class'] = 'signup';
		} else {
			$display_signup = apply_filters('pta_sus_public_output', __('Filled', 'pta-volunteer-sign-up-sheets'), 'task_spot_filled_message');
			$row_data['extra-class'] = 'filled';
		}

		$display_signup = apply_filters('pta_sus_display_signup_name', $display_signup, $signup);

		$clear_url = '';
		$clear_text = '';
		if ($this->volunteer->can_modify_signup($signup)) {
			$clear_args = array('sheet_id' => false, 'task_id' => false, 'signup_id' => (int)$signup->id);
			$raw_clear_url = add_query_arg($clear_args);
			$clear_url = wp_nonce_url($raw_clear_url, 'pta_sus_clear_signup');
			$clear_text = apply_filters('pta_sus_public_output', __('Clear', 'pta-volunteer-sign-up-sheets'), 'clear_signup_link_text');
		}

		$row_data['column-available-spots'] = '#'.$i.': '.$display_signup;
		$row_data['column-phone'] = $signup->phone;
		$row_data['column-email'] = $signup->email;
		$row_data['column-details'] = $signup->item;
		$row_data['column-quantity'] = (int)($signup->item_qty);

		if($show_clear && $this->volunteer->is_validated()) {
			$row_data['column-clear'] = '<a class="pta-sus-link clear-signup" href="'.esc_url($clear_url).'">'.esc_html($clear_text).'</a>';
		} else {
			$row_data['column-clear'] = '';
		}

		return apply_filters('pta_sus_generate_signup_row_data', $row_data, $signup, $task, $i);
	}

	public function generated_consolidated_signup_row_data($signups, $task_qty, $task_url, $allow_signups) {
		$row_data = array();
		$filled = 0;
		foreach($signups as $signup) {
			$filled += $signup->item_qty;
		}
		$remaining = $task_qty - $filled;

		if($remaining > 0) {
			$filled_text = apply_filters( 'pta_sus_public_output', sprintf(__('%d Filled', 'pta-volunteer-sign-up-sheets'),(int)$filled ), 'task_number_spots_filled_message', (int)$filled );
		} else {
			$filled_text = apply_filters('pta_sus_public_output', __('Filled', 'pta-volunteer-sign-up-sheets'), 'task_spots_full_message');
		}
		$remaining_text = apply_filters( 'pta_sus_public_output', sprintf(__('%d remaining: &nbsp;', 'pta-volunteer-sign-up-sheets'), (int)$remaining), 'task_number_remaining', (int)$remaining );
		$separator = apply_filters('pta_sus_public_output', ', ', 'task_spots_filled_remaining_separator');
		$display_consolidated = $filled_text;
		if($remaining > 0 && $allow_signups) {
			if( ! $this->main_options['login_required_signup'] || $this->volunteer->is_validated() ) {
				$display_consolidated .= esc_html($separator . $remaining_text).'<a class="pta-sus-link signup" href="'.esc_url($task_url).'">'.apply_filters( 'pta_sus_public_output', __('Sign up &raquo;', 'pta-volunteer-sign-up-sheets'), 'task_sign_up_link_text' ) . '</a>';
			} else {
				if ( isset( $this->main_options['show_login_link'] ) && true === $this->main_options['show_login_link'] ) {
					$signup_message = '<a class="pta-sus-link login" href="' . wp_login_url( get_permalink() ) . '" title="Login">' . esc_html( $this->main_options['login_signup_message'] ) . '</a>';
				} else {
					$signup_message = esc_html( $this->main_options['login_signup_message'] );
				}
				$display_consolidated .= ' - ' . $signup_message;
			}
		}
		$row_data['column-available-spots'] = $display_consolidated;
		$row_data['extra-class'] = 'consolidated';
		return $row_data;
	}

	public function get_default_task_column_values($task, $date) {
		$display_date = $date != "0000-00-00" ? mysql2date( get_option('date_format'), $date, $translate = true ) : '';
		$start_time = '' !== $task->time_start ? pta_datetime(get_option("time_format"), strtotime($task->time_start)) : '';
		$end_time = '' !== $task->time_end ? pta_datetime(get_option("time_format"), strtotime($task->time_end)) : '';
		$description = wp_kses_post($task->description);
		$task_title = sanitize_text_field($task->title);
		$row_data = array();
		$row_data['column-description'] = $description;
		$row_data['column-date'] = $display_date;
		$row_data['column-start-time'] = $start_time;
		$row_data['column-end-time'] = $end_time;
		$row_data['column-task'] = $task_title;
		return $row_data;
	}

	public function get_task_row_data($task, $date, $sheet_id, $show_clear=false, $no_signups = false, $signups = array()) {
		$column_data = array();
		$show_all_slots = true;
		if(isset($this->main_options['show_remaining']) && $this->main_options['show_remaining']) {
			$show_all_slots = false;
		}
		$show_names = true;
		if($this->main_options['hide_volunteer_names']) {
			$show_names = false;
		}

		$show_details = false;

		if ( isset( $this->main_options['hide_details_qty'] ) && ! $this->main_options['hide_details_qty'] ) {
			if ( 'YES' == $task->need_details ) {
				$show_details = true;
			}
		}

		$default_data = $this->get_default_task_column_values($task, $date);
		$display_date = $default_data['column-date'];
		$start_time = $default_data['column-start-time'];
		$end_time = $default_data['column-end-time'];
		$description = $default_data['column-description'];
		$task_title = $default_data['column-task'];

		$allow_signups = apply_filters( 'pta_sus_allow_signups', true, $task, $sheet_id, $date );
		$task_qty      = $no_signups ? 1 : absint( $task->qty );
		if(empty($signups)) {
			$signups = apply_filters( 'pta_sus_task_get_signups', $this->data->get_signups( $task->id, $date ), $task->id, $date );
		}

		$one_row = false;
		if ( ! $show_all_slots && ! $show_details && ! $show_names && ! $no_signups ) {
			$one_row = true;
		}

		$task_args = array( 'sheet_id'  => $sheet_id, 'task_id'   => $task->id, 'date'      => $date, 'signup_id' => false );
		if ( is_page( $this->main_options['volunteer_page_id'] ) || ! $this->main_options['signup_redirect'] ) {
			$task_url = add_query_arg( $task_args );
		} else {
			$main_page_url = get_permalink( $this->main_options['volunteer_page_id'] );
			$task_url      = add_query_arg( $task_args, $main_page_url );
		}
		$task_url = apply_filters( 'pta_sus_task_signup_url', $task_url, $task, $sheet_id, $date );

		if ( $one_row ) {
			// Consolidated single row view
			$row_data = $this->generated_consolidated_signup_row_data($signups,$task_qty,$task_url,$allow_signups);
			$default_data = $this->get_default_task_column_values($task, $date);
			$row_data = array_merge($row_data, $default_data);
			$row_data['column-num'] = '';
			$column_data[] = apply_filters( 'pta_sus_task_consolidated_row_data', $row_data, $task, $date );
		} else {
			// Individual rows for each signup
			$i = 1;
			foreach ( $signups as $signup ) {
				$row_data = $this->generate_signup_row_data( $signup, $task, $i, $show_names, $show_clear );
				$default_data = $this->get_default_task_column_values($task, $date);
				$row_data = array_merge($row_data, $default_data);
				$row_data['column-num'] = $i;
				if ( 'YES' === $task->enable_quantities ) {
					$i += $signup->item_qty;
				} else {
					$i ++;
				}
				$column_data[] = apply_filters( 'pta_sus_task_signup_display_row_data', $row_data, $task, $signup, $date );
			}

			// Add remaining spots rows
			$remaining = $task_qty - ( $i - 1 );
			$start     = $i;

			if ( ! $show_all_slots ) {
				$start    = $remaining;
				$task_qty = $remaining;
			}
			if ( $remaining > 0 ) {
				// set up all the common data first to speed things up
				$row_data       = array();
				$signup_message = '';
				if ( ! $no_signups ) {
					if ( $allow_signups ) {
						if ( ! $this->main_options['login_required_signup'] || $this->volunteer->is_validated() ) {
							$signup_message = '<a class="pta-sus-link signup" href="' . esc_url( $task_url ) . '">' . apply_filters( 'pta_sus_public_output', __( 'Sign up &raquo;', 'pta-volunteer-sign-up-sheets' ), 'task_sign_up_link_text' ) . '</a>';
						} else {
							if ( isset( $this->main_options['show_login_link'] ) && true === $this->main_options['show_login_link'] ) {
								$signup_message = '<a class="pta-sus-link login" href="' . wp_login_url( get_permalink() ) . '" title="Login">' . esc_html( $this->main_options['login_signup_message'] ) . '</a>';
							} else {
								$signup_message = esc_html( $this->main_options['login_signup_message'] );
							}
						}
					}

					$row_data['column-phone'] = '';
					$row_data['column-email'] = '';

				}
				$row_data['column-clear']    = '';
				$row_data['column-details']  = '';
				$row_data['column-quantity'] = '';
				$row_data['column-task'] = $task_title;
				$row_data['extra-class'] = 'remaining';
				$row_data['column-description'] = $description;
				$row_data['column-date'] = $display_date;
				$row_data['column-start-time'] = $start_time;
				$row_data['column-end-time'] = $end_time;
				$row_data['column-num'] = $i;
				$row_data                = apply_filters( 'pta_sus_task_remaining_display_row_data', $row_data, $task, $date );
				for ( $i = $start; $i <= $task_qty; $i ++ ) {
					if ( ! $no_signups ) {
						if ( $show_all_slots ) {
							$row_data['column-available-spots'] = '#' . $i . ': ';
						} else {
							$row_data['column-available-spots'] = apply_filters( 'pta_sus_public_output', sprintf( __( '%d remaining: &nbsp;', 'pta-volunteer-sign-up-sheets' ), (int) $remaining ), 'task_number_remaining', (int) $remaining );
						}
						$row_data['column-available-spots'] .= $signup_message;
					}
					$column_data[] = $row_data;
				}
			}
		}

		return apply_filters('pta_sus_task_row_data', $column_data, $task, $date);
	}



	public function display_task_list($sheet_id, $date, $no_signups=false) {
		// Tasks

		$tasks = apply_filters('pta_sus_public_sheet_get_tasks', $this->data->get_tasks($sheet_id, $date), $sheet_id, $date);

		if (!$tasks ) {
			return '<p>'.apply_filters( 'pta_sus_public_output', __('No tasks were found for ', 'pta-volunteer-sign-up-sheets'), 'no_tasks_found_for_date' ) . mysql2date( get_option('date_format'), $date, $translate = true ).'</p>';
		}
		$return = apply_filters( 'pta_sus_before_task_list', '', $tasks );

		$show_all_slots = true;
		if( isset($this->main_options['show_remaining']) && $this->main_options['show_remaining'] ) {
			$show_all_slots = false;
		}
		$show_names = true;
		if($this->main_options['hide_volunteer_names']) {
			$show_names = false;
		}

		$sheet = $this->data->get_sheet($sheet_id);
		$show_date = true;
		if('single' === strtolower($sheet->type) && isset($this->main_options['hide_single_date_header']) && $this->main_options['hide_single_date_header']) {
			$show_date = false;
		}

		$return .= '<div class="pta-sus-sheets tasks">';

		foreach($tasks as $task) {
			$task_dates = explode(',', $task->dates);
			// Don't show tasks that don't include our date, if one was passed in
			if ($date && !in_array($date, $task_dates)) continue;

			$columns = array();

			$show_clear = pta_sus_show_clear($sheet, $date, $task->time_start);
			$show_details = false;
			$show_qty = false;
			if( isset($this->main_options['hide_details_qty']) && ! $this->main_options['hide_details_qty'] ) {
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
				$columns['column-available-spots'] = apply_filters( 'pta_sus_public_output', __('Available Spots', 'pta-volunteer-sign-up-sheets'), 'task_available_spots_header' );

				if($this->show_phone && !$one_row) {
					$columns['column-phone'] = apply_filters( 'pta_sus_public_output', __('Phone', 'pta-volunteer-sign-up-sheets'), 'task_phone_header' );
				}

				if($this->show_email && !$one_row) {
					$columns['column-email'] = apply_filters( 'pta_sus_public_output', __('Email', 'pta-volunteer-sign-up-sheets'), 'task_email_header' );
				}

			}
			if ($show_details && !$one_row) {
				$columns['column-details'] = $this->item_details_header;
			}
			if ($show_qty && !$one_row) {
				$columns['column-quantity'] = $this->item_qty_header;
			}

			if($this->volunteer->is_validated() && !$one_row && $show_clear) {
				$columns['column-clear'] = '';
			}

			$i=1;
			$signups = apply_filters( 'pta_sus_task_get_signups', $this->data->get_signups($task->id, $date), $task->id, $date);

			// Set qty to one for no_signups sheets
			$task_qty = $no_signups ? 1 : absint($task->qty);
			
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
				$return .= '<table class="pta-sus pta-sus-tasks '.esc_attr($additional_table_class).'"><thead class="pta-sus-table-head">';
				ob_start();
				include(PTA_VOLUNTEER_SUS_DIR.'views/task-view-table-header-row-html.php');
				$return .= ob_get_clean();
				$return .= '</thead><tbody>';
			}

			$column_data = $this->get_task_row_data($task,$date,$sheet_id,$show_clear,$no_signups);

			$column_data = apply_filters('pta_sus_task_display_rows', $column_data, $task, $date, $one_row);

			if($this->use_divs) {
				ob_start();
				include(PTA_VOLUNTEER_SUS_DIR.'views/task-view-divs-rows-html.php');
				$return .= ob_get_clean();
				$return .= '</div>'; // close "table" divs
			} else {
				ob_start();
				include(PTA_VOLUNTEER_SUS_DIR.'views/task-view-table-rows-html.php');
				$return .= ob_get_clean();
				$return .= '</tbody></table>'; // close body and table
			}

			$return .= apply_filters( 'pta_sus_after_single_task_list', '', $task, $date );
		}

		$return .= apply_filters( 'pta_sus_after_all_tasks_list', '', $tasks );

		$return .= '</div>'; // close wrapper for both table and divs layouts

		return $return;
	} // Display task list

	public function display_signup_form($task_id, $date, $skip_filled_check = false) {
		if( $this->main_options['login_required_signup'] && !is_user_logged_in()) {
			$message = '<p class="pta-sus error">' . esc_html($this->main_options['login_signup_message']) . '</p>';
			if(isset($this->main_options['show_login_link']) && true === $this->main_options['show_login_link']) {
				$message .= '<p><a class="pta-sus-link login" href="'. wp_login_url( get_permalink() ) .'" title="Login">'.esc_html($this->main_options['login_signup_message']).'</a></p>';
			}
			return $message;
		}
		if($this->validation_enabled && isset($this->validation_options['require_validation_to_signup']) && $this->validation_options['require_validation_to_signup']) {
			if(!$this->volunteer->is_validated()) {
				return pta_get_validation_required_message();
			}
		}
		
		if( $this->suppress_duplicates && apply_filters( 'pta_sus_signup_form_already_displayed', $this->signup_displayed, $task_id, $date ) ) {
			// don't show more than one signup form on a page,
			// if admin put multiple shortcodes on a page and didn't set to redirect to main shortcode page
			return '';
		}

        $task = apply_filters( 'pta_sus_public_signup_get_task', $this->data->get_task($task_id), $task_id);
        do_action( 'pta_sus_before_signup_form', $task, $date );

		$go_back_args = array('task_id' => false, 'date' => false, 'sheet_id' => $task->sheet_id);
		$go_back_url = apply_filters( 'pta_sus_signup_goback_url', add_query_arg($go_back_args) );

		$available = $this->data->get_available_qty($task->id, $date, $task->qty);
		if(!$skip_filled_check) {
			// Check if nothing available before showing the sign-up form, or if it was filled before they submitted the form
			if($available < 1 && !$this->filled) {
				$this->filled = true;
				$message = '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __('All spots have already been filled.', 'pta-volunteer-sign-up-sheets'), 'no_spots_available_signup_error_message' ).'</p>';
				$message .= '<p><a class="pta-sus-link go-back" href="'.esc_url($go_back_url).'">'.esc_html( apply_filters( 'pta_sus_public_output', __('&laquo; go back to the Sign-Up Sheet', 'pta-volunteer-sign-up-sheets'), 'go_back_to_signup_sheet_text' ) ).'</a></p>';
				return $message;
			} elseif( $this->filled ) {
				return '<p><a class="pta-sus-link go-back" href="'.esc_url($go_back_url).'">'.esc_html( apply_filters( 'pta_sus_public_output', __('&laquo; go back to the Sign-Up Sheet', 'pta-volunteer-sign-up-sheets'), 'go_back_to_signup_sheet_text' ) ).'</a></p>';
			}
		}

		// Give other plugins a chance to restrict signup access
		if( ! apply_filters( 'pta_sus_can_signup', true, $task, $date ) ) {
			return '<p class="pta-sus error">'.apply_filters( 'pta_sus_public_output', __("You don't have permission to view this page.", 'pta-volunteer-sign-up-sheets'), 'no_permission_to_view_error_message' ).'</p>';
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
        $form .= '<h3 class="pta-sus sign-up-header">'.apply_filters( 'pta_sus_public_output', __('Sign Up', 'pta-volunteer-sign-up-sheets'), 'sign_up_form_heading' ).'</h3>';
        $form .= '<h4 class="pta-sus sign-up-header">'. apply_filters( 'pta_sus_public_output', __('You are signing up for... ', 'pta-volunteer-sign-up-sheets'), 'you_are_signing_up_for' ).'<br/><strong>'.esc_html($task->title).'</strong> ';
        if ($show_date) {
            $form .= apply_filters( 'pta_sus_public_output', sprintf(__('on %s', 'pta-volunteer-sign-up-sheets'), $show_date), 'sign_up_for_date',$show_date );
        }
        $form .= '</h4>';
        if ($this->show_time && !empty($task->time_start)) {
            $form .= '<span class="time_start">'.esc_html($this->start_time_header) . ': '. pta_datetime(get_option("time_format"), strtotime($task->time_start)) . '</span><br/>';
        }
        if ($this->show_time && !empty($task->time_end)) {
            $form .= '<span class="time_end">'.esc_html($this->end_time_header) . ': '. pta_datetime(get_option("time_format"), strtotime($task->time_end)) . '</span><br/>';
        }
		if($this->main_options['show_task_description_on_signup_form']) {
			if(!empty($task->description)) {
				$form .= '<div class="pta-sus task-description">'.wp_kses_post($task->description).'</div>';
			}
		}
        $firstname_label = apply_filters( 'pta_sus_public_output', __('First Name', 'pta-volunteer-sign-up-sheets'), 'firstname_label' );
        $lastname_label = apply_filters( 'pta_sus_public_output', __('Last Name', 'pta-volunteer-sign-up-sheets'), 'lastname_label' );
        $email_label = apply_filters( 'pta_sus_public_output', __('E-mail', 'pta-volunteer-sign-up-sheets'), 'email_label' );
		$validate_email_label = apply_filters( 'pta_sus_public_output', __('Confirm E-mail', 'pta-volunteer-sign-up-sheets'), 'confirm_email_label' );
        $phone_label = apply_filters( 'pta_sus_public_output', __('Phone', 'pta-volunteer-sign-up-sheets'), 'phone_label' );

        $form .= apply_filters( 'pta_sus_signup_form_before_form_fields', '<br/>', $task, $date );
		// Give other plugins a chance to modify signup data
		$posted = apply_filters('pta_sus_signup_posted_values', $_POST);

		// Initialize values array with posted data if it exists
		$values = array(
			'signup_user_id' => isset($posted['signup_user_id']) ? absint($posted['signup_user_id']) : '',
			'signup_firstname' => isset($posted['signup_firstname']) ? sanitize_text_field($posted['signup_firstname']) : '',
			'signup_lastname' => isset($posted['signup_lastname']) ? sanitize_text_field($posted['signup_lastname']) : '',
			'signup_email' => isset($posted['signup_email']) ? sanitize_email($posted['signup_email']) : '',
			'signup_validate_email' => isset($posted['signup_validate_email']) ? sanitize_email($posted['signup_validate_email']) : '',
			'signup_phone' => isset($posted['signup_phone']) ? sanitize_text_field($posted['signup_phone']) : ''
		);

		$readonly_first = "";
		$readonly_last = "";
		$readonly_email = "";

		// Only pre-fill if no form submission error and user is validated
		if (!isset($_POST['pta_sus_form_mode']) && $this->volunteer->is_validated()) {

			// Set readonly attributes if required
			if (isset($this->main_options['readonly_signup']) &&
			    true === $this->main_options['readonly_signup'] &&
			    !current_user_can('manage_signup_sheets')) {

				if (!empty($this->volunteer->get_firstname())) {
					$readonly_first = "readonly='readonly'";
				}
				if (!empty($this->volunteer->get_lastname())) {
					$readonly_last = "readonly='readonly'";
				}
				$readonly_email = "readonly='readonly'";
			}

			// Pre-populate form for regular users, or admins when live search is disabled
			if (!current_user_can('manage_signup_sheets') ||
			    !$this->main_options['enable_signup_search']) {

				// Set values from volunteer object
				$values['signup_user_id'] = $this->volunteer->get_user_id();
				$values['signup_firstname'] = $this->volunteer->get_firstname();
				$values['signup_lastname'] = $this->volunteer->get_lastname();
				$values['signup_email'] = $this->volunteer->get_email();
				$values['signup_validate_email'] = $this->volunteer->get_email();

				// Handle phone separately since it's not in volunteer object
				if (!$this->main_options['no_phone'] && $this->volunteer->get_user_id() > 0) {
					$phone = apply_filters('pta_sus_user_phone',
						get_user_meta($this->volunteer->get_user_id(), 'billing_phone', true),
						get_user_by('id', $this->volunteer->get_user_id())
					);
					$values['signup_phone'] = $phone;
				}

				$values = apply_filters('pta_sus_prefilled_user_signup_values', $values);
			}
		}

		// Default User Fields
        if ( !is_user_logged_in() && ! $this->main_options['disable_signup_login_notice'] ) {
            $form .= '<p>'.apply_filters( 'pta_sus_public_output', __('If you have an account, it is strongly recommended that you <strong>login before you sign up</strong> so that you can view and edit all your signups.', 'pta-volunteer-sign-up-sheets'), 'signup_login_notice' ).'</p>';
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
        if( ! $this->main_options['no_phone'] ) {
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
			$available = apply_filters('pta_sus_signup_form_available_qty', $available, $task, $date);
            if ($available > 1) {
                $form .= '<label class="required" for="signup_item_qty">'.esc_html( apply_filters( 'pta_sus_public_output', sprintf(__('Item QTY (1 - %d): ', 'pta-volunteer-sign-up-sheets'), (int)$available), 'item_quantity_input_label', (int)$available ) ).'</label>
                <input type="number" id="signup_item_qty" name="signup_item_qty" value="'.((isset($posted['signup_item_qty'])) ? (int)($posted['signup_item_qty']) : '').'" min="1" max="'.esc_attr($available).'" />';
            } elseif ( 1 == $available) {
                $form .= '<strong>'.apply_filters( 'pta_sus_public_output', __('Only 1 remaining! Your quantity will be set to 1.', 'pta-volunteer-sign-up-sheets'), 'only_1_remaining' ).'</strong>';
                $form .= '<input type="hidden" name="signup_item_qty" value="1" />';
            }
            $form .= '</p>';
        } else {
            $form .= '<input type="hidden" name="signup_item_qty" value="1" />';
        }

        $form .= apply_filters( 'pta_sus_signup_form_after_details_field', '', $task, $date );

        // Spam check and form submission
        $form .= '
			<div style="visibility:hidden"> 
	            <input name="website" type="text" size="20" />
	        </div>
	        <p class="submit">
	            <input type="hidden" name="signup_date" value="'.esc_attr($date).'" />
                <input type="hidden" name="allow_duplicates" value="'.$task->allow_duplicates.'" />
	            <input type="hidden" name="signup_task_id" value="'.esc_attr($task_id).'" />
	        	<input type="hidden" name="pta_sus_form_mode" value="submitted" />
	        	<input type="submit" name="Submit" class="button-primary" value="'.esc_attr( apply_filters( 'pta_sus_public_output', __('Sign me up!', 'pta-volunteer-sign-up-sheets'), 'signup_button_text' ) ).'" />
	            <a class="pta-sus-link go-back" href="'.esc_url($go_back_url).'">'.esc_html( apply_filters( 'pta_sus_public_output', __('&laquo; go back to the Sign-Up Sheet', 'pta-volunteer-sign-up-sheets'), 'go_back_to_signup_sheet_text' ) ).'</a>
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
	    static $done = false;
	    if ($done) return;
	    $done = true;

	    if ( ! isset( $this->main_options['disable_css'] ) || ! $this->main_options['disable_css'] ) {
		    wp_register_style( 'pta-sus-style', plugins_url( '../assets/css/style.min.css', __FILE__ ) );
		    wp_enqueue_style( 'pta-sus-style' );
	    }

		if(isset($this->main_options['enable_mobile_css']) && $this->main_options['enable_mobile_css']) {
			wp_enqueue_style( 'pta-sus-style-mobile', plugins_url( '../assets/css/mobile.min.css', __FILE__ ) );
		}

	    if ( $this->main_options['enable_signup_search'] && isset( $_GET['task_id'] ) && current_user_can( 'manage_signup_sheets' ) ) {
		    wp_enqueue_style('pta-sus-autocomplete');
		    wp_enqueue_script('pta-sus-autocomplete');
			wp_enqueue_script('pta-sus-live-search', plugins_url( '../assets/js/frontend-listener.min.js', __FILE__ ), array('pta-sus-autocomplete'), '', true);
	    }

	    // Always enqueue URL cleanup script
	    // Register script with no source
	    wp_register_script('pta-sus-url-cleanup', '', array(), '', true);
	    wp_enqueue_script('pta-sus-url-cleanup');
	    $inline_script = "
	        if(document.querySelector('.pta-sus-messages[data-clear-url]')) {
	            window.history.replaceState({}, '', window.location.pathname);
	        }
	        
	        document.addEventListener('DOMContentLoaded', function() {
			    var clearLinks = document.querySelectorAll('.clear-signup-link');
			    clearLinks.forEach(function(link) {
			        link.addEventListener('click', function(e) {
			            e.preventDefault();
			            if(confirm('Are you sure you want to clear this signup?')) {
			                window.location.href = this.href;
			            }
			        });
			    });
			});
	    ";
	    wp_add_inline_script('pta-sus-url-cleanup', $inline_script);
    }

} // End of class
/* EOF */
