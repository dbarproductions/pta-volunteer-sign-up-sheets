<?php
/**
 * Public Pages Class
 * 
 * Handles all public-facing functionality for the Volunteer Sign-Up Sheets plugin.
 * This class processes signup forms, displays sheets and tasks, manages validation,
 * and provides hooks for extensions to customize behavior.
 * 
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Public {

    /**
     * URI for all sheets list (with query args removed)
     * 
     * @var string
     */
    private $all_sheets_uri;
    
    /**
     * Main plugin options array
     * 
     * @var array
     */
    public $main_options;
    
    /**
     * Email options array
     * 
     * @var array
     */
    public $email_options;
    
    /**
     * Integration options array
     * 
     * @var array
     */
    public $integration_options;
    
    /**
     * Validation options array
     * 
     * @var array
     */
	public $validation_options;

    /**
     * Whether signup form was submitted
     * 
     * @var bool
     */
    public $submitted;
    
    /**
     * Error count for form validation
     * 
     * @var int
     */
    public $err;
    
    /**
     * Whether signup was successful
     * 
     * @var bool
     */
    public $success;

    /**
     * Whether signup spots were filled
     * 
     * @var bool
     */
	public $filled = false;
    
    /**
     * Whether signup was cleared
     * 
     * @var bool
     */
    private $cleared;
    
    /**
     * Whether messages have been displayed
     * 
     * @var bool
     */
    private $messages_displayed = false;
    
    /**
     * Header text for task/item column
     * 
     * @var string
     */
    private $task_item_header;
    
    /**
     * Header text for start time column
     * 
     * @var string
     */
    private $start_time_header;
    
    /**
     * Header text for end time column
     * 
     * @var string
     */
    private $end_time_header;
    
    /**
     * Header text for item details column
     * 
     * @var string
     */
    private $item_details_header;
    
    /**
     * Header text for item quantity column
     * 
     * @var string
     */
    private $item_qty_header;
    
    /**
     * Text to display for "not applicable" values
     * 
     * @var string
     */
    private $na_text;
    
    /**
     * Header text for title column
     * 
     * @var string
     */
	private $title_header;
	
	/**
	 * Header text for start date column
	 * 
	 * @var string
	 */
	private $start_date_header;
	
	/**
	 * Header text for end date column
	 * 
	 * @var string
	 */
	private $end_date_header;
	
	/**
	 * Header text for open spots column
	 * 
	 * @var string
	 */
	private $open_spots_header;
	
	/**
	 * Message to display when no contact info is provided
	 * 
	 * @var string
	 */
	private $no_contact_message;
	
	/**
	 * Label text for contact information
	 * 
	 * @var string
	 */
	private $contact_label;
	
	/**
	 * Whether to show hidden sheets (admin/manager only)
	 * 
	 * @var bool
	 */
	private $show_hidden = false;
	
	/**
	 * Whether signup form has been displayed
	 * 
	 * @var bool
	 */
	private $signup_displayed = false;
	
	/**
	 * Whether to suppress duplicate signups
	 * 
	 * @var bool
	 */
	private $suppress_duplicates;
	
	/**
	 * Whether to use divs instead of tables for layout
	 * 
	 * @var bool
	 */
	private $use_divs = false;
	
	/**
	 * HTML string for hidden sheet notice
	 * 
	 * @var string
	 */
	private $hidden;
	
	/**
	 * Current date filter value
	 * 
	 * @var string|null
	 */
	private $date;
	
	/**
	 * Header text for date column
	 * 
	 * @var string
	 */
	private $date_header;
	
	/**
	 * Whether to show time columns
	 * 
	 * @var bool
	 */
	private $show_time = true;
	
	/**
	 * Whether phone number is required
	 * 
	 * @var bool
	 */
	private $phone_required;
	
	/**
	 * Whether to show full name instead of first name only
	 * 
	 * @var bool
	 */
	private $show_full_name = false;
	
	/**
	 * Whether to show phone numbers in signup lists
	 * 
	 * @var bool
	 */
	private $show_phone = false;
	
	/**
	 * Whether to show email addresses in signup lists
	 * 
	 * @var bool
	 */
	private $show_email = false;
	
	/**
	 * Shortcode ID attribute value
	 * 
	 * @var string|false
	 */
	private $shortcode_id = false;
	
	/**
	 * Whether to show table headers
	 * 
	 * @var bool
	 */
	private $show_headers = true;
	
	/**
	 * Whether to show start date column
	 * 
	 * @var bool
	 */
	private $show_date_start = true;
	
	/**
	 * Whether to show end date column
	 * 
	 * @var bool
	 */
	private $show_date_end = true;
	
	/**
	 * Whether to prevent global time overlap checking
	 * 
	 * @var bool
	 */
	private $no_global_overlap;

    /**
     * Whether validation email has been sent
     * 
     * @var bool
     */
	public $validation_sent = false;
	
	/**
	 * Whether validation system is enabled
	 * 
	 * @var bool
	 */
	public $validation_enabled = false;


	/**
	 * Volunteer object for current user
	 * 
	 * @var PTA_SUS_Volunteer
	 */
	private $volunteer;

    /**
     * Whether signup form has been processed (prevents duplicate processing)
     * 
     * @var bool
     */
	private $processed = false;
    
    /**
     * Constructor
     * 
     * Initializes the public class, loads options, and sets up default values.
     * 
     * @since 1.0.0
     */
    public function __construct() {
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

    }

    /**
     * Initialize the public class
     * 
     * Sets up the volunteer object, initializes display functions, processes forms,
     * registers shortcodes, and fires the initialization action hook.
     * 
     * @since 1.0.0
     * @see PTA_SUS_Public_Display_Functions::initialize()
     * @hook pta_sus_public_init Fires after public class initialization
     */
	public function init() {
		$this->volunteer = new PTA_SUS_Volunteer(get_current_user_id());
		$this->set_up_filters();
		
		// Initialize display helper class with current options
		PTA_SUS_Public_Display_Functions::initialize(
			array(
				'phone_required' => $this->phone_required,
				'suppress_duplicates' => $this->suppress_duplicates,
				'use_divs' => $this->use_divs,
				'show_time' => $this->show_time,
				'show_date_start' => $this->show_date_start,
				'show_date_end' => $this->show_date_end,
				'show_headers' => $this->show_headers,
				'show_phone' => $this->show_phone,
				'show_email' => $this->show_email,
				'shortcode_id' => $this->shortcode_id,
				'all_sheets_uri' => $this->all_sheets_uri,
				'title_header' => $this->title_header,
				'start_date_header' => $this->start_date_header,
				'end_date_header' => $this->end_date_header,
				'open_spots_header' => $this->open_spots_header,
				'start_time_header' => $this->start_time_header,
				'end_time_header' => $this->end_time_header,
				'item_details_header' => $this->item_details_header,
				'item_qty_header' => $this->item_qty_header,
				'contact_label' => $this->contact_label,
				'no_contact_message' => $this->no_contact_message,
				'hidden' => $this->hidden,
				'date' => $this->date,
			),
			$this->volunteer
		);
		
		$this->process_signup_form();
		if($this->validation_enabled) {
			$this->process_validation_form();
			$this->maybe_validate_volunteer();
		}

		// Sync form state after processing
		PTA_SUS_Public_Display_Functions::update_form_state($this->submitted, $this->err, $this->success, $this->cleared);
		PTA_SUS_Public_Display_Functions::set_messages_displayed($this->messages_displayed);
		PTA_SUS_Public_Display_Functions::set_validation_sent($this->validation_sent);


		// Get any messages saved in cookies if we did a redirect
		pta_get_messages_from_cookie();

		add_shortcode('pta_sign_up_sheet', array($this, 'display_sheet'));
		add_shortcode('pta_user_signups', array($this, 'process_user_signups_shortcode'));
		add_shortcode('pta_validation_form', array($this, 'process_validation_form_shortcode'));

		do_action('pta_sus_public_init');

	}

    /**
     * Set up filter hooks and initialize display strings
     * 
     * Initializes all public-facing text strings (headers, labels, messages) using
     * the 'pta_sus_public_output' filter to allow customization. Also sets up filter
     * hooks for extension integration.
     * 
     * @since 1.0.0
     * @hook pta_sus_validate_signup Filter for signup validation (used by extensions)
     * @hook pta_sus_add_signup Filter for adding signups (used by extensions)
     */
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

    /**
     * Handle volunteer validation via URL parameters
     * 
     * Processes validation actions from URL parameters:
     * - 'clear_validation': Clears validation cookie
     * - 'validate_user': Validates a user via validation code
     * - 'validate_signup': Validates a specific signup via validation code
     * 
     * @since 1.0.0
     * @return void
     */
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
			PTA_SUS_Public_Display_Functions::update_form_state($this->submitted, $this->err, $this->success, $this->cleared);
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
					PTA_SUS_Public_Display_Functions::update_form_state($this->submitted, $this->err, $this->success, $this->cleared);
					if (PTA_SUS_Email_Functions::send_mail($signup_id) === false) {
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
	 * Process validation form submission and send validation email
	 * 
	 * Handles the user validation form submission, validates input, sends validation
	 * email, and sets cookies to prevent form resubmission.
	 * 
	 * @since 1.0.0
	 * @return void
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
			if(PTA_SUS_Email_Functions::send_user_validation_email($firstname, $lastname, $email)) {
				PTA_SUS_Messages::add_message(apply_filters( 'pta_sus_public_output', __('Validation email sent.','pta-volunteer-sign-up-sheets'),'validation_email_sent_message' ));
				$this->validation_sent = true;
				PTA_SUS_Public_Display_Functions::set_validation_sent($this->validation_sent);
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

	/**
	 * Validate signup form fields
	 * 
	 * Validates all signup form fields including required fields, field formats,
	 * task availability, and business rules. Uses the PTA_SUS_Validation helper class
	 * for core validation logic.
	 * 
	 * @since 1.0.0
	 * @param array $posted Posted form data with 'signup_' prefix
	 * @return bool True if validation passes, false otherwise
	 * @hook pta_sus_before_add_signup Fires before validation (used by extensions)
	 * @see PTA_SUS_Validation::validate_signup_fields()
	 */
	public function validate_signup_form_fields($posted) {
		// Moved these for compatibility with check-in extension to allow automatically adding extra slots
		$signup_task_id = $posted['signup_task_id'];
		do_action( 'pta_sus_before_add_signup', $posted, $signup_task_id);

		$task = pta_sus_get_task((int)$posted['signup_task_id']);
		if (!$task) {
			$this->err++;
			PTA_SUS_Public_Display_Functions::update_form_state($this->submitted, $this->err, $this->success, $this->cleared);
			PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('This task is no longer available. Please try selecting a different task.', 'pta-volunteer-sign-up-sheets'), 'task_no_longer_available_error' ));
			return false;
		}
		$sheet = pta_sus_get_sheet((int)$task->sheet_id);
		if (!$sheet) {
			$this->err++;
			PTA_SUS_Public_Display_Functions::update_form_state($this->submitted, $this->err, $this->success, $this->cleared);
			PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('This sheet is no longer available. Please try selecting a different sheet.', 'pta-volunteer-sign-up-sheets'), 'sheet_no_longer_available_error' ));
			return false;
		}

		// Use validation helper class for signup field validation
		// This consolidates all validation logic while maintaining state management here
		$validation_errors = PTA_SUS_Validation::validate_signup_fields($posted, $task, $sheet, $this->main_options);
		
		// Update instance error count and filled flag
		$this->err += $validation_errors;
		if ($validation_errors > 0) {
			// Check if filled flag should be set (spots were full)
			$available = $task->get_available_spots($posted['signup_date']);
		$available = apply_filters('pta_sus_process_signup_available_slots', $available, $posted, $sheet, $task);
			if ($available < 1) {
			$this->filled = true;
			}
		}

		return ! ( $this->err > 0 );
	}

	/**
	 * Extension hook wrapper for signup validation
	 * 
	 * This method is called via the 'pta_sus_validate_signup' filter hook.
	 * Extensions can use this filter to add custom validation logic.
	 * 
	 * @since 1.0.0
	 * @param bool $valid Current validation status (ignored, always calls validation)
	 * @param array $posted Posted form data
	 * @return bool True if validation passes, false otherwise
	 * @hook pta_sus_validate_signup Filter hook for extension validation
	 */
	public function extension_validate_signup_form_fields($valid,$posted) {
		return $this->validate_signup_form_fields($posted);
	}

	/**
	 * Add a new signup
	 * 
	 * Processes and saves a new signup to the database. Handles validation requirements,
	 * sends confirmation or validation emails, and manages redirects. Can be bypassed
	 * by extensions using the 'pta_sus_signup_add_signup_to_main_database' filter.
	 * 
	 * @since 1.0.0
	 * @param array $posted Posted form data with 'signup_' prefix
	 * @param int $signup_task_id Task ID for the signup
	 * @param bool $redirect Whether to redirect after successful signup (default: true)
	 * @param PTA_SUS_Task|false $task Optional pre-loaded task object (for optimization)
	 * @param PTA_SUS_Sheet|false $sheet Optional pre-loaded sheet object (for optimization)
	 * @return int|bool Signup ID on success, false on failure
	 * @hook pta_sus_after_add_signup Fires after signup is added
	 * @hook pta_sus_signup_add_signup_to_main_database Filter to bypass database save
	 * @hook pta_sus_signup_database_bypass Action for extensions that bypass database
	 * @hook pta_sus_signup_database_bypass_success Filter for bypass success status
	 * @hook pta_sus_public_redirect_after_signup Filter to control redirect behavior
	 * @see pta_sus_add_signup()
	 */
	public function add_signup($posted, $signup_task_id, $redirect = true, $task = false, $sheet = false) {
		$validate_signups =  $this->validation_enabled && isset($this->validation_options['enable_signup_validation']) && $this->validation_options['enable_signup_validation'];
		$posted['signup_validated'] = $validate_signups ? $this->volunteer->is_validated() : 1;
		$needs_validation = ($this->validation_enabled && $validate_signups && !$this->volunteer->is_validated());
		// Allow extensions to bypass adding signup to main database
		if( apply_filters( 'pta_sus_signup_add_signup_to_main_database', true, $posted, $signup_task_id ) ) {
			// Pass task object through to avoid reloading (uses cache if not provided)
			$signup_id = pta_sus_add_signup($posted, $signup_task_id, $task);
			if ( $signup_id === false) {
				$this->err++;
				PTA_SUS_Public_Display_Functions::update_form_state($this->submitted, $this->err, $this->success, $this->cleared);
				PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Error adding signup record.  Please try again.', 'pta-volunteer-sign-up-sheets'), 'add_signup_database_error_message' ));
				return false;
			} else {
				do_action( 'pta_sus_after_add_signup', $posted,$posted['signup_task_id'], $signup_id);

				$this->success = true;
				PTA_SUS_Public_Display_Functions::update_form_state($this->submitted, $this->err, $this->success, $this->cleared);
				if($needs_validation) {
					$sent = PTA_SUS_Email_Functions::send_mail($signup_id,false,false,false,'validate_signup');
					if($sent) {
						PTA_SUS_Messages::add_message(apply_filters( 'pta_sus_public_output', __('Signup Validation email sent.','pta-volunteer-sign-up-sheets'), 'signup_validation_sent_message' ));
						$this->validation_sent = true;
						PTA_SUS_Public_Display_Functions::set_validation_sent($this->validation_sent);
					} else {
						PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Signup Validation email failed to send.','pta-volunteer-sign-up-sheets'), 'signup_validation_send_error_message' ));
					}
				} else {
					PTA_SUS_Messages::add_message(apply_filters( 'pta_sus_public_output', __('You have been signed up!', 'pta-volunteer-sign-up-sheets'), 'signup_success_message' ),true);
					$sent = PTA_SUS_Email_Functions::send_mail(intval($signup_id));
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

	/**
	 * Extension hook wrapper for adding signups
	 * 
	 * This method is called via the 'pta_sus_add_signup' filter hook.
	 * Extensions can use this filter to intercept signup processing.
	 * 
	 * @since 1.0.0
	 * @param int|bool $signup_id Signup ID (may be false if not yet created)
	 * @param array $posted Posted form data
	 * @param int $signup_task_id Task ID
	 * @return int|bool Signup ID on success, false on failure
	 * @hook pta_sus_add_signup Filter hook for extension signup processing
	 */
	public function extension_add_signup($signup_id, $posted, $signup_task_id) {
		return $this->add_signup($posted, $signup_task_id);
	}

    /**
     * Process signup form submission
     * 
     * Handles signup form submissions and signup clearing via GET parameters.
     * Validates nonces, processes form data, and manages form state.
     * 
     * @since 1.0.0
     * @return void|bool Returns false on nonce failure, cleared status on clear action, void otherwise
     * @hook pta_sus_signup_posted_values Filter to modify posted values before validation
     * @hook pta_sus_signup_form_error_count Filter to modify error count after validation
     * @hook pta_sus_signup_form_errors Action fired when form has errors
     */
    public function process_signup_form() {
		if(is_admin() && !wp_doing_ajax()) return;
        
        $this->submitted = (isset($_POST['pta_sus_form_mode']) && $_POST['pta_sus_form_mode'] === 'submitted');
        $this->err = 0;
        $this->success = false;
        $this->messages_displayed = false; // reset
	    $this->cleared = false;
		// Update helper class state
		PTA_SUS_Public_Display_Functions::update_form_state($this->submitted, $this->err, $this->success, $this->cleared);
		PTA_SUS_Public_Display_Functions::set_messages_displayed($this->messages_displayed);
        
        // Process Sign-up Form
        if ($this->submitted && !$this->processed) {
			$this->processed = true;
            // NONCE check
            if ( ! isset( $_POST['pta_sus_signup_nonce'] ) || ! wp_verify_nonce( $_POST['pta_sus_signup_nonce'], 'pta_sus_signup' ) ) {
                $this->err++;
				PTA_SUS_Public_Display_Functions::update_form_state($this->submitted, $this->err, $this->success, $this->cleared);
                PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Sorry! Your security nonce did not verify!', 'pta-volunteer-sign-up-sheets'), 'nonce_error_message' ));
                return false;
            }
            // Check for spambots
            if (!empty($_POST['website'])) {
                $this->err++;
				PTA_SUS_Public_Display_Functions::update_form_state($this->submitted, $this->err, $this->success, $this->cleared);
                PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Oops! You filled in the spambot field. Please leave it blank and try again.', 'pta-volunteer-sign-up-sheets'), 'spambot_error_message' ));
                return false;
            }
	
	        // Give other plugins a chance to modify signup data
	        $posted = apply_filters('pta_sus_signup_posted_values', $_POST);

	        $this->validate_signup_form_fields($posted);
			$signup_task_id = $posted['signup_task_id'];
	        $task = pta_sus_get_task((int)$posted['signup_task_id']);
			if (!$task) {
				// Error already added in validate_signup_form_fields, just return
				return;
			}
	        $sheet = pta_sus_get_sheet((int)$task->sheet_id);
			if (!$sheet) {
				// Error already added in validate_signup_form_fields, just return
				return;
			}

            // Allow other plugins to validate
	        $this->err = apply_filters('pta_sus_signup_form_error_count' , $this->err, $posted, $task, $sheet);
			// Update helper class state
			PTA_SUS_Public_Display_Functions::update_form_state($this->submitted, $this->err, $this->success, $this->cleared);
            
            // Add Signup
            if (absint($this->err) < 1) {
				$this->add_signup($posted, $signup_task_id, true, $task, $sheet);
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
		    $signup=pta_sus_get_signup((int)$_GET['signup_id']);
		    if (null === $signup) {
			    PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('Not a valid signup!', 'pta-volunteer-sign-up-sheets'), 'clear_invalid_signup_error_message' ));
		    } elseif (!$this->volunteer->can_modify_signup($signup)) {
			    PTA_SUS_Messages::add_error(apply_filters( 'pta_sus_public_output', __('You are not allowed to do that!', 'pta-volunteer-sign-up-sheets'), 'clear_not_allowed_error_message' ));
		    } else {
			    // Send cleared emails
			    PTA_SUS_Email_Functions::send_mail((int)$_GET['signup_id'], false, true);
			    $cleared = $signup->delete();
			    if ($cleared) {
				    PTA_SUS_Messages::add_message(apply_filters( 'pta_sus_public_output', __('Signup Cleared', 'pta-volunteer-sign-up-sheets'), 'signup_cleared_message' ),true);
				    $this->cleared = true;
					PTA_SUS_Public_Display_Functions::update_form_state($this->submitted, $this->err, $this->success, $this->cleared);
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
    
    /**
     * Get sheets list display
     * 
     * Wrapper method that calls the static helper function to display a list of sheets.
     * Updates display options if needed before calling the helper.
     * 
     * @since 1.0.0
     * @param array $sheets Array of sheet objects to display
     * @param array $atts Optional shortcode attributes
     * @return string HTML output for sheets list
     * @see PTA_SUS_Public_Display_Functions::get_sheets_list()
     */
    public function get_sheets_list($sheets, $atts=array()) {
		// Update date in display options if it changed
		if ($this->date !== null) {
			PTA_SUS_Public_Display_Functions::set_date($this->date);
		}
		// Use helper class method - options are stored in static properties
		return PTA_SUS_Public_Display_Functions::get_sheets_list($sheets, $atts, $this->date);
    }
    
	/**
	 * Get single sheet display
	 * Wrapper method that calls the static helper function
	 * Maintains backward compatibility for extensions using $pta_sus->public->get_single_sheet()
	 * 
	 * @deprecated 6.0.0 Use PTA_SUS_Public_Display_Functions::get_single_sheet() instead
	 * @param int $id Sheet ID
	 * @return string HTML output for single sheet
	 */
    public function get_single_sheet($id) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Public_Display_Functions::get_single_sheet() ' . sprintf('Called from %s line %s', $file, $line)
		);
		// Update display options with current state
		PTA_SUS_Public_Display_Functions::initialize(
			array(
				'show_headers' => $this->show_headers,
				'shortcode_id' => $this->shortcode_id,
				'all_sheets_uri' => $this->all_sheets_uri,
				'submitted' => $this->submitted,
				'err' => $this->err,
				'success' => $this->success,
				'date' => $this->date,
			),
			$this->volunteer
		);
		return PTA_SUS_Public_Display_Functions::get_single_sheet($id);
	}
    
	/**
	 * Get user signups list
	 * Wrapper method that calls the static helper function
	 * Maintains backward compatibility for extensions using $pta_sus->public->get_user_signups_list()
	 * 
	 * @deprecated 6.0.0 Use PTA_SUS_Public_Display_Functions::get_user_signups_list() instead
	 * @param array $atts Shortcode attributes
	 * @return string HTML output for user signups list
	 */
	public function get_user_signups_list($atts) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Public_Display_Functions::get_user_signups_list() ' . sprintf('Called from %s line %s', $file, $line)
		);
		// Update helper class with current volunteer object
		PTA_SUS_Public_Display_Functions::initialize(
			array(),
			$this->volunteer
		);
		return PTA_SUS_Public_Display_Functions::get_user_signups_list($atts);
    }

	/**
	 * Get clear validation message and link
	 * 
	 * Returns HTML for the clear validation message and link if the user is validated
	 * but not logged in, and the clear validation feature is enabled.
	 * 
	 * @since 1.0.0
	 * @return string HTML output for clear validation message, empty string if not applicable
	 */
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

    /**
     * Process [pta_user_signups] shortcode
     * 
     * Displays a list of signups for the current user (based on validation cookie or login).
     * Shows messages and includes clear validation link if applicable.
     * 
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string HTML output for user signups list
     * @hook pta_sus_public_output Filter for 'no_user_signups_message' text
     */
    public function process_user_signups_shortcode($atts) {
		$return = PTA_SUS_Messages::show_messages();
	    $this->messages_displayed = true;
		PTA_SUS_Public_Display_Functions::set_messages_displayed($this->messages_displayed);
		PTA_SUS_Messages::clear_messages();
    	$return .= $this->get_user_signups_list($atts);
    	if(empty($return)) {
    		$return = apply_filters( 'pta_sus_public_output', __('You do not have any current signups', 'pta-volunteer-sign-up-sheets'),'no_user_signups_message');
	    } else {
		    $return .= $this->maybe_get_clear_validation_message();
	    }
		return $return;
	}

	/**
	 * Process [pta_validation_form] shortcode
	 * 
	 * Wrapper method that calls the static helper function.
	 * Maintains backward compatibility for shortcode hooks.
	 * 
	 * @deprecated 6.0.0 Use PTA_SUS_Public_Display_Functions::process_validation_form_shortcode() instead
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes
	 *   - hide_when_validated (string) 'yes' to hide form when user is validated
	 * @return string HTML output for validation form or status message
	 * @see PTA_SUS_Public_Display_Functions::process_validation_form_shortcode()
	 */
	public function process_validation_form_shortcode($atts) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Public_Display_Functions::process_validation_form_shortcode() ' . sprintf('Called from %s line %s', $file, $line)
		);
		
		// Initialize display functions with current volunteer object
		PTA_SUS_Public_Display_Functions::initialize(
			array(),
			$this->volunteer
		);
		PTA_SUS_Public_Display_Functions::set_validation_sent($this->validation_sent);
		
		return PTA_SUS_Public_Display_Functions::process_validation_form_shortcode($atts);
	}

	/**
	 * Display sheet (main shortcode/block handler)
	 * 
	 * Wrapper method that calls the static helper function.
	 * Maintains backward compatibility for shortcode/block hooks.
	 * 
	 * @deprecated 6.0.0 Use PTA_SUS_Public_Display_Functions::display_sheet() instead
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes
	 *   - id (int) Specific sheet ID to display
	 *   - date (string) Date filter (YYYY-MM-DD format)
	 *   - group (string) Group filter
	 *   - list_title (string) Title for sheets list
	 *   - show_headers (string) 'yes' or 'no' to show/hide headers
	 *   - show_time (string) 'yes' or 'no' to show/hide time columns
	 *   - show_phone (string) 'yes' or 'no' to show/hide phone numbers
	 *   - show_email (string) 'yes' or 'no' to show/hide email addresses
	 *   - order_by (string) Field to sort by (default: 'first_date')
	 *   - order (string) Sort order 'ASC' or 'DESC' (default: 'ASC')
	 * @return string HTML output for sheet(s) display
	 * @see PTA_SUS_Public_Display_Functions::display_sheet()
	 */
    public function display_sheet($atts) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		// Only show deprecation if not called from WordPress core (shortcode/block system)
		if (!empty($file) && strpos($file, 'wp-includes') === false && strpos($file, 'wp-admin') === false) {
			_deprecated_function(
				__METHOD__,
				'6.0.0',
				'PTA_SUS_Public_Display_Functions::display_sheet() ' . sprintf('Called from %s line %s', $file, $line)
			);
		}
		// Update helper class with current state before calling
		PTA_SUS_Public_Display_Functions::update_form_state($this->submitted, $this->err, $this->success, $this->cleared);
		PTA_SUS_Public_Display_Functions::set_messages_displayed($this->messages_displayed);
		PTA_SUS_Public_Display_Functions::set_validation_sent($this->validation_sent);
		PTA_SUS_Public_Display_Functions::initialize(
			array(
				'show_hidden' => $this->show_hidden,
			),
			$this->volunteer
		);
		return PTA_SUS_Public_Display_Functions::display_sheet($atts);
	}

	/**
	 * Generate signup row data
	 * 
	 * Wrapper method that calls the static helper function.
	 * Maintains backward compatibility for extensions using $pta_sus->public->generate_signup_row_data()
	 * 
	 * @since 1.0.0
	 * @param PTA_SUS_Signup|object $signup Signup object
	 * @param PTA_SUS_Task|object $task Task object
	 * @param int $i Row number/index
	 * @param bool $show_names Whether to show volunteer names (default: true)
	 * @param bool $show_clear Whether to show clear link if volunteer can modify (default: false)
	 * @return array Associative array with row data keys for table/div display
	 * @see PTA_SUS_Public_Display_Functions::generate_signup_row_data()
	 */
	public function generate_signup_row_data($signup, $task, $i, $show_names = true, $show_clear=false) {
		return PTA_SUS_Public_Display_Functions::generate_signup_row_data($signup, $task, $i, $this->volunteer, $show_names, $show_clear);
	}

	/**
	 * Generate consolidated signup row data
	 * 
	 * Wrapper method that calls the static helper function.
	 * Maintains backward compatibility for extensions using $pta_sus->public->generated_consolidated_signup_row_data()
	 * 
	 * @since 1.0.0
	 * @param array $signups Array of signup objects
	 * @param int $task_qty Total quantity for the task
	 * @param string $task_url URL for signing up to the task
	 * @param bool $allow_signups Whether signups are allowed for this task
	 * @return array Associative array with keys: 'column-available-spots', 'extra-class'
	 * @see PTA_SUS_Public_Display_Functions::generated_consolidated_signup_row_data()
	 */
	public function generated_consolidated_signup_row_data($signups, $task_qty, $task_url, $allow_signups) {
		return PTA_SUS_Public_Display_Functions::generated_consolidated_signup_row_data($signups, $task_qty, $task_url, $allow_signups, $this->volunteer);
	}

	/**
	 * Get default task column values
	 * 
	 * Wrapper method that calls the static helper function.
	 * Maintains backward compatibility for extensions using $pta_sus->public->get_default_task_column_values()
	 * 
	 * @since 1.0.0
	 * @param PTA_SUS_Task|object $task Task object
	 * @param string $date Date string (YYYY-MM-DD format)
	 * @return array Associative array with keys: 'column-description', 'column-date', 'column-start-time', 'column-end-time', 'column-task'
	 * @see PTA_SUS_Public_Display_Functions::get_default_task_column_values()
	 */
	public function get_default_task_column_values($task, $date) {
		return PTA_SUS_Public_Display_Functions::get_default_task_column_values($task, $date);
	}

	/**
	 * Get task row data
	 * 
	 * Wrapper method that calls the static helper function.
	 * Maintains backward compatibility for extensions using $pta_sus->public->get_task_row_data()
	 * 
	 * @since 1.0.0
	 * @param PTA_SUS_Task|object $task Task object
	 * @param string $date Date string (YYYY-MM-DD format)
	 * @param int $sheet_id Sheet ID
	 * @param bool $show_clear Whether to show clear link if volunteer can modify (default: false)
	 * @param bool $no_signups Whether this is a no-signups task (default: false)
	 * @param array $signups Optional pre-loaded signups array (default: empty, will load if needed)
	 * @return array Array of row data arrays, each with column keys for table/div display
	 * @see PTA_SUS_Public_Display_Functions::get_task_row_data()
	 */
	public function get_task_row_data($task, $date, $sheet_id, $show_clear=false, $no_signups = false, $signups = array()) {
		return PTA_SUS_Public_Display_Functions::get_task_row_data($task, $date, $sheet_id, $this->volunteer, $show_clear, $no_signups, $signups);
	}

	/**
	 * Display task list for a specific sheet and date
	 * 
	 * Wrapper method that calls the static helper function.
	 * Maintains backward compatibility for extensions using $pta_sus->public->display_task_list()
	 * 
	 * @deprecated 6.0.0 Use PTA_SUS_Public_Display_Functions::display_task_list() instead
	 * @since 1.0.0
	 * @param int $sheet_id Sheet ID
	 * @param string $date Date string (YYYY-MM-DD format)
	 * @param bool $no_signups Whether this is a no-signups sheet (default: false)
	 * @param PTA_SUS_Sheet|object|false $sheet Optional pre-loaded sheet object (for optimization)
	 * @return string HTML output for task list
	 * @see PTA_SUS_Public_Display_Functions::display_task_list()
	 */
	public function display_task_list($sheet_id, $date, $no_signups = false, $sheet = false) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? array();
		$file = $caller['file'] ?? '';
		$line = $caller['line'] ?? '';
		_deprecated_function(
			__METHOD__,
			'6.0.0',
			'PTA_SUS_Public_Display_Functions::display_task_list() ' . sprintf('Called from %s line %s', $file, $line)
		);
		// Update display options if they've changed
		PTA_SUS_Public_Display_Functions::initialize(
			array(
				'show_phone' => $this->show_phone,
				'show_email' => $this->show_email,
				'use_divs' => $this->use_divs,
			),
			$this->volunteer
		);
		return PTA_SUS_Public_Display_Functions::display_task_list($sheet_id, $date, $no_signups, $sheet);
	}

	/**
	 * Display signup form for a specific task and date
	 * 
	 * Wrapper method that calls the static helper function to display the signup form.
	 * Updates display options if needed before calling the helper.
	 * 
	 * @since 1.0.0
	 * @param int $task_id Task ID
	 * @param string $date Date string (YYYY-MM-DD format)
	 * @param bool $skip_filled_check Whether to skip checking if spots are filled (default: false)
	 * @return string HTML output for signup form
	 * @see PTA_SUS_Public_Display_Functions::display_signup_form()
	 */
	public function display_signup_form($task_id, $date, $skip_filled_check = false) {
		// Update date in display options if it changed
		if ($this->date !== null) {
			PTA_SUS_Public_Display_Functions::set_date($this->date);
		}
		// Use helper class method - options are stored in static properties
		return PTA_SUS_Public_Display_Functions::display_signup_form(
			$task_id,
			$date,
			$skip_filled_check,
			$this->filled // Pass by reference so it can be updated
		);
	}

	/**
	 * Enqueue plugin CSS and JavaScript files
	 * 
	 * Registers and enqueues frontend assets including main stylesheet, mobile stylesheet,
	 * autocomplete assets (if enabled), and inline JavaScript for URL cleanup and clear
	 * link confirmation dialogs.
	 * 
	 * @since 1.0.0
	 * @return void
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
            // Use non-minified version for debugging (switch back to .min.js for production)
            wp_enqueue_script('pta-sus-live-search', plugins_url( '../assets/js/livesearch-listener.js', __FILE__ ), array('pta-sus-autocomplete'), '', true);
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

	    // Enqueue AJAX SPA script (only if enabled in settings)
	    $main_options = get_option('pta_volunteer_sus_main_options');
	    if (!empty($main_options['enable_ajax_navigation'])) {
	        wp_enqueue_script('pta-sus-ajax', plugins_url( '../assets/js/pta-sus-ajax.min.js', __FILE__ ), array('jquery'), PTA_VOLUNTEER_SUS_VERSION_NUM, true);
	        wp_localize_script('pta-sus-ajax', 'pta_sus_vars', array(
	            'ajaxurl' => admin_url('admin-ajax.php'),
	            'nonce'   => wp_create_nonce('ajax-pta-nonce'),
	            'atts'    => array(), // Default empty, will be updated by display_sheet
	        ));
	    }
    }

} // End of class
/* EOF */
