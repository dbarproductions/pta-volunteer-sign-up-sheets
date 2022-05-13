<?php
/**
* Admin Setting page
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Options {
	public $main_options;
	public $email_options;
	public $integration_options;
	public $member_directory_active;
	private $settings_page_slug = 'pta-sus-settings_settings';

	public function __construct() {
		$this->main_options = get_option( 'pta_volunteer_sus_main_options' );
		$this->email_options = get_option( 'pta_volunteer_sus_email_options' );
		$this->integration_options = get_option( 'pta_volunteer_sus_integration_options' );
		add_action('admin_init', array($this, 'register_options'));

	} // End Construct

	/**
    * Admin Page: Options/Settings
    */
    function admin_options() {
        if (!current_user_can('manage_options') && !current_user_can('manage_signup_sheets'))  {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'pta-volunteer-sign-up-sheets' ) );
        }
        $docs_link = '<a href="https://stephensherrardplugins.com/docs/pta-volunteer-sign-up-sheets-documentation/" target="_blank">'.__('Documentation', 'pta-volunteer-sign-up-sheets') . '</a>';

        ?>
        <div class="wrap pta_sus">
            <div id="icon-themes" class="icon32"></div>
            <h2><?php _e('PTA Volunteer Sign-up Sheets Settings', 'pta-volunteer-sign-up-sheets'); ?></h2>
            <?php settings_errors(); ?>
            <?php $active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'main_options'; ?> 
            <h2 class="nav-tab-wrapper">  
                <a href="?page=<?php echo $this->settings_page_slug?>&tab=main_options" class="nav-tab <?php echo $active_tab == 'main_options' ? 'nav-tab-active' : ''; ?>"><?php _e('Main Settings', 'pta-volunteer-sign-up-sheets'); ?></a>
                <a href="?page=<?php echo $this->settings_page_slug?>&tab=email_options" class="nav-tab <?php echo $active_tab == 'email_options' ? 'nav-tab-active' : ''; ?>"><?php _e('Email Settings', 'pta-volunteer-sign-up-sheets'); ?></a>
                <a href="?page=<?php echo $this->settings_page_slug?>&tab=integration_options" class="nav-tab <?php echo $active_tab == 'integration_options' ? 'nav-tab-active' : ''; ?>"><?php _e('Integration Settings', 'pta-volunteer-sign-up-sheets'); ?></a>
                <?php do_action('pta_sus_settings_nav_tabs', $active_tab); ?>
            </h2> 
            <form action="options.php" method="post">
                <?php 

                if ( 'main_options' == $active_tab ) {
                	settings_fields('pta_volunteer_sus_main_options'); 
                	do_settings_sections('pta_volunteer_sus_main'); 
                } elseif ( 'email_options' == $active_tab ) {
                	settings_fields('pta_volunteer_sus_email_options'); 
                	do_settings_sections('pta_volunteer_sus_email'); 
                } elseif ( 'integration_options' == $active_tab ) {
                	settings_fields('pta_volunteer_sus_integration_options'); 
                	do_settings_sections('pta_volunteer_sus_integration'); 
                } else {
                    // Allow extensions to create their own tabs
                    do_action('pta_sus_extensions_settings_tabs', $active_tab);
                }
                       
                submit_button();
                ?>
            </form>
            <?php if ('main_options' == $active_tab && !$this->main_options['hide_donation_button']): ?>
                <h5><?php _e('Please help support continued development of this plugin! Any amount helps!', 'pta-volunteer-sign-up-sheets'); ?></h5>
                <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
                    <input type="hidden" name="cmd" value="_s-xclick">
                    <input type="hidden" name="hosted_button_id" value="R4HF689YQ9DEE">
                    <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif" name="submit" alt="PayPal - The safer, easier way to pay online!">
                    <img alt="" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
                </form>
            <?php endif; 
            if ('main_options' == $active_tab): ?>
                <h3><?php echo $docs_link; ?></h3>
            <?php endif; 
            do_action('pta_sus_settings_after_submit_button', $active_tab);
            ?>
        </div>        
        <?php
    }

    public function register_options() {
    	// Main Settings
        register_setting( 'pta_volunteer_sus_main_options', 'pta_volunteer_sus_main_options', array($this, 'pta_sus_validate_main_options') );
        add_settings_section('pta_volunteer_main', __('Main Settings', 'pta-volunteer-sign-up-sheets'), array($this, 'pta_volunteer_main_description'), 'pta_volunteer_sus_main');
        add_settings_field('enable_test_mode', __('Enable Test Mode', 'pta-volunteer-sign-up-sheets'), array($this, 'enable_test_mode_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('test_mode_message', __('Test Mode Message:', 'pta-volunteer-sign-up-sheets'), array($this, 'test_mode_message_text_input'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('volunteer_page_id', __('Volunteer Sign Up Page', 'pta-volunteer-sign-up-sheets'), array($this, 'volunteer_page_id_select'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('signup_redirect', __('Redirect Sign Ups to Main Page?', 'pta-volunteer-sign-up-sheets'), array($this, 'signup_redirect_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('suppress_duplicates', __('Suppress Duplicate Output?', 'pta-volunteer-sign-up-sheets'), array($this, 'suppress_duplicates_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('use_divs', __('Use divs?', 'pta-volunteer-sign-up-sheets'), array($this, 'use_divs_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('disable_css', __('Disable plugin CSS?', 'pta-volunteer-sign-up-sheets'), array($this, 'disable_css_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('disable_user_signups', __('Disable User Signups List?', 'pta-volunteer-sign-up-sheets'), array($this, 'disable_user_signups_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('hide_volunteer_names', __('Hide volunteer names from public?', 'pta-volunteer-sign-up-sheets'), array($this, 'hide_volunteer_names_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('show_full_name', __('Show full name?', 'pta-volunteer-sign-up-sheets'), array($this, 'show_full_name_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('show_remaining', __('Consolidate remaining slots?', 'pta-volunteer-sign-up-sheets'), array($this, 'show_remaining_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('hide_details_qty', __('Hide Details and Quantities', 'pta-volunteer-sign-up-sheets'), array($this, 'hide_details_qty_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('hide_signups_details_qty', __('Hide User Signups Details and Quantities', 'pta-volunteer-sign-up-sheets'), array($this, 'hide_signups_details_qty_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('hide_contact_info', __('Hide chair contact info from public?', 'pta-volunteer-sign-up-sheets'), array($this, 'hide_contact_info_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('show_ongoing_in_widget', __('Show Ongoing events in Widget?', 'pta-volunteer-sign-up-sheets'), array($this, 'show_ongoing_in_widget_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('show_ongoing_last', __('Show Ongoing events last?', 'pta-volunteer-sign-up-sheets'), array($this, 'show_ongoing_last_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('phone_required', __('Phone Required?', 'pta-volunteer-sign-up-sheets'), array($this, 'phone_required_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('no_phone', __('Remove Phone field from Signup form?', 'pta-volunteer-sign-up-sheets'), array($this, 'no_phone_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('login_required', __('Login Required?', 'pta-volunteer-sign-up-sheets'), array($this, 'login_required_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('login_required_signup', __('Login Required for Signup?', 'pta-volunteer-sign-up-sheets'), array($this, 'login_required_signup_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('login_required_message', __('Login Required Message:', 'pta-volunteer-sign-up-sheets'), array($this, 'login_required_message_text_input'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('login_to_signup_message', __('Login to Sign-Up Message:', 'pta-volunteer-sign-up-sheets'), array($this, 'login_signup_message_text_input'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('readonly_signup', __('Read Only Signup?', 'pta-volunteer-sign-up-sheets'), array($this, 'readonly_signup_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('show_login_link', __('Show Login Link?', 'pta-volunteer-sign-up-sheets'), array($this, 'show_login_link_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('disable_signup_login_notice', __('Disable Login Notices?', 'pta-volunteer-sign-up-sheets'), array($this, 'disable_signup_login_notice_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('no_global_overlap', __('Prevent Global Overlapping Signups?', 'pta-volunteer-sign-up-sheets'), array($this, 'no_global_overlap_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('enable_cron_notifications', __('Enable CRON Notifications?', 'pta-volunteer-sign-up-sheets'), array($this, 'enable_cron_notifications_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('detailed_reminder_admin_emails', __('Detailed Reminder Notifications?', 'pta-volunteer-sign-up-sheets'), array($this, 'detailed_reminder_admin_emails_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('skip_signups_check', __('Skip Signups Check?', 'pta-volunteer-sign-up-sheets'), array($this, 'skip_signups_check_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('show_expired_tasks', __('Show Expired Tasks?', 'pta-volunteer-sign-up-sheets'), array($this, 'show_expired_tasks_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('clear_expired_signups', __('Automatically clear expired signups?', 'pta-volunteer-sign-up-sheets'), array($this, 'clear_expired_signups_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('enable_signup_search', __('Enable Sign-up form live search?', 'pta-volunteer-sign-up-sheets'), array($this, 'enable_signup_search_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('signup_search_tables', __('Live Search Tables', 'pta-volunteer-sign-up-sheets'), array($this, 'signup_search_tables_select'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('admin_only_settings', __('Admin Only Settings Access?', 'pta-volunteer-sign-up-sheets'), array($this, 'admin_only_settings_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('disable_datei18n', __('Disable Date/Time Translation?', 'pta-volunteer-sign-up-sheets'), array($this, 'disable_datei18n_settings_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('disable_grouping', __('Disable Grouping?', 'pta-volunteer-sign-up-sheets'), array($this, 'disable_grouping_settings_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('show_all_slots_for_all_data', __('Show All Slots for All Data?', 'pta-volunteer-sign-up-sheets'), array($this, 'show_all_slots_for_all_data_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('hide_donation_button', __('Hide donation button?', 'pta-volunteer-sign-up-sheets'), array($this, 'hide_donation_button_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');

        // Email Settings
        register_setting( 'pta_volunteer_sus_email_options', 'pta_volunteer_sus_email_options', array($this, 'pta_sus_validate_email_options') );
        add_settings_section('pta_volunteer_email', __('Email Settings', 'pta-volunteer-sign-up-sheets'), array($this, 'pta_volunteer_email_description'), 'pta_volunteer_sus_email');
        add_settings_field('from_email', __('FROM email:', 'pta-volunteer-sign-up-sheets'), array($this, 'from_email_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('replyto_email', __('Reply-To email:', 'pta-volunteer-sign-up-sheets'), array($this, 'replyto_email_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
	    add_settings_field('replyto_chairs', __('Reply-To Chairs?', 'pta-volunteer-sign-up-sheets'), array($this, 'replyto_chairs_checkbox'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('cc_email', __('CC email:', 'pta-volunteer-sign-up-sheets'), array($this, 'cc_email_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('confirmation_email_subject', __('Confirmation email subject:', 'pta-volunteer-sign-up-sheets'), array($this, 'confirmation_email_subject_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('confirmation_email_template', __('Confirmation email template:', 'pta-volunteer-sign-up-sheets'), array($this, 'confirmation_email_template_textarea_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('clear_email_subject', __('Cleared signup email subject:', 'pta-volunteer-sign-up-sheets'), array($this, 'clear_email_subject_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('clear_email_template', __('Cleared signup email template:', 'pta-volunteer-sign-up-sheets'), array($this, 'clear_email_template_textarea_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('reminder_email_subject', __('Reminder email subject:', 'pta-volunteer-sign-up-sheets'), array($this, 'reminder_email_subject_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('reminder_email_template', __('Reminder email template:', 'pta-volunteer-sign-up-sheets'), array($this, 'reminder_email_template_textarea_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
	    add_settings_field('reminder2_email_subject', __('Reminder 2 email subject:', 'pta-volunteer-sign-up-sheets'), array($this, 'reminder2_email_subject_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
	    add_settings_field('reminder2_email_template', __('Reminder 2 email template:', 'pta-volunteer-sign-up-sheets'), array($this, 'reminder2_email_template_textarea_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('reminder_email_limit', __('Max Reminders per Hour:', 'pta-volunteer-sign-up-sheets'), array($this, 'reminder_email_limit_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('reschedule_email_subject', __('Reschedule email subject:', 'pta-volunteer-sign-up-sheets'), array($this, 'reschedule_email_subject_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('reschedule_email_template', __('Reschedule email template:', 'pta-volunteer-sign-up-sheets'), array($this, 'reschedule_email_template_textarea_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
	    add_settings_field('individual_emails', __('Separate CC/BCC to individual TO emails?', 'pta-volunteer-sign-up-sheets'), array($this, 'individual_emails_checkbox'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
	    add_settings_field('admin_clear_emails', __('Send emails when clear from admin?', 'pta-volunteer-sign-up-sheets'), array($this, 'admin_clear_emails_checkbox'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
	    add_settings_field('no_chair_emails', __('Disable chair emails?', 'pta-volunteer-sign-up-sheets'), array($this, 'no_chair_emails_checkbox'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('no_confirmation_emails', __('Disable Confirmation emails?', 'pta-volunteer-sign-up-sheets'), array($this, 'no_confirmation_emails_checkbox'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('no_reminder_emails', __('Disable Reminder emails?', 'pta-volunteer-sign-up-sheets'), array($this, 'no_reminder_emails_checkbox'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
	    add_settings_field('disable_emails', __('Disable ALL emails?', 'pta-volunteer-sign-up-sheets'), array($this, 'disable_emails_checkbox'), 'pta_volunteer_sus_email', 'pta_volunteer_email');

        // Integration Settings
        register_setting( 'pta_volunteer_sus_integration_options', 'pta_volunteer_sus_integration_options', array($this, 'pta_sus_validate_integration_options') );
        add_settings_section('pta_volunteer_integration', __('Integration Settings', 'pta-volunteer-sign-up-sheets'), array($this, 'pta_volunteer_integration_description'), 'pta_volunteer_sus_integration');
        if (is_plugin_active( 'pta-member-directory/pta-member-directory.php' )) {
            add_settings_field('enable_member_directory', __('Enable PTA Member Directory:', 'pta-volunteer-sign-up-sheets'), array($this, 'enable_member_directory_checkbox'), 'pta_volunteer_sus_integration', 'pta_volunteer_integration');
            add_settings_field('directory_page_id', __('Member Directory Page:', 'pta-volunteer-sign-up-sheets'), array($this, 'directory_page_id_select'), 'pta_volunteer_sus_integration', 'pta_volunteer_integration');
            add_settings_field('contact_page_id', __('Contact Form Page:', 'pta-volunteer-sign-up-sheets'), array($this, 'contact_page_id_select'), 'pta_volunteer_sus_integration', 'pta_volunteer_integration');
            $this->member_directory_active = true;
        } else {
            $this->member_directory_active = false;
        }       
    } // Register Options

    protected function validate_options($inputs, $fields, $options) {
        $err = 0;
        foreach ($fields as $field => $type) {
        	switch ($type) {
        		case 'integer':
        			if( is_numeric($inputs[$field]) || '' === $inputs[$field] ) {
		                $this->{$options}[$field] = (int)$inputs[$field];
		            } else {
		                $err++;
		                $message = sprintf(__('Invalid entry for %s!', 'pta-volunteer-sign-up-sheets'), $field);
		                add_settings_error( $field, $field, $message, $type = 'error' );
		            }
        			break;
    			case 'email':
                    // Allow cc_email to be blank
                    if ('cc_email' === $field && '' == $inputs[$field]) {
	                    $this->{$options}[$field] = '';
	                    break;
                    }
    				if(is_email($inputs[$field])) {
		                $this->{$options}[$field] = $inputs[$field];
		            } else {
		                $err++;
		                $message = sprintf(__('Invalid email for %s!', 'pta-volunteer-sign-up-sheets'), $field);
		                add_settings_error( $field, $field, $message, $type = 'error' );
		            }
		            break;
        		case 'text':
		                $this->{$options}[$field] = sanitize_text_field( $inputs[$field] );
		            break;
	            case 'bool':
	                if(isset($inputs[$field]) && true == $inputs[$field]) {
		                $this->{$options}[$field] = true;
                    } else {
		                $this->{$options}[$field] = false;
                    }
	            	break;
	            case 'textarea':
	            	$this->{$options}[$field] = wp_kses_post($inputs[$field]);
	            	break;
        		default:
        			$err++;
	                $message = sprintf(__('Unrecognized field type: %s!', 'pta-volunteer-sign-up-sheets'), $type);
	                add_settings_error( $field, $field, $message, $type = 'error' );
        			break;
        	}
        }
        
        if(!$err) {
            $field = 'volunteer_settings_form'; // set any field here
            $message = __("Settings successfully saved!", 'pta-volunteer-sign-up-sheets');
            add_settings_error( $field, $field, $message, $type = 'updated' );
        }
        return $this->{$options};
    }

    public function pta_sus_validate_main_options($inputs) {
    	$options = "main_options";
	    $fields = array(
		    'enable_test_mode'                => 'bool',
		    'test_mode_message'               => 'text',
		    'volunteer_page_id'               => 'integer',
		    'hide_volunteer_names'            => 'bool',
		    'show_remaining'                  => 'bool',
		    'hide_contact_info'               => 'bool',
		    'show_ongoing_in_widget'          => 'bool',
		    'show_ongoing_last'               => 'bool',
		    'no_phone'                        => 'bool',
		    'login_required'                  => 'bool',
		    'login_required_signup'           => 'bool',
		    'login_required_message'          => 'text',
		    'login_signup_message'            => 'text',
		    'readonly_signup'                 => 'bool',
		    'show_login_link'                 => 'bool',
		    'disable_signup_login_notice'     => 'bool',
		    'enable_cron_notifications'       => 'bool',
		    'detailed_reminder_admin_emails'  => 'bool',
		    'show_expired_tasks'              => 'bool',
		    'clear_expired_signups'           => 'bool',
		    'enable_signup_search'            => 'bool',
		    'hide_donation_button'            => 'bool',
		    'signup_search_tables'            => 'text',
		    'hide_details_qty'                => 'bool',
		    'hide_signups_details_qty'        => 'bool',
		    'signup_redirect'                 => 'bool',
		    'phone_required'                  => 'bool',
		    'details_required'                => 'bool',
		    'use_divs'                        => 'bool',
		    'disable_css'                     => 'bool',
		    'disable_user_signups'            => 'bool',
		    'show_full_name'                  => 'bool',
		    'suppress_duplicates'             => 'bool',
		    'show_remaining_slots_csv_export' => 'bool',
		    'show_dates_csv_export'           => 'bool',
		    'no_global_overlap'               => 'bool',
		    'admin_only_settings'             => 'bool',
		    'disable_datei18n'                => 'bool',
		    'disable_grouping'                => 'bool',
		    'show_all_slots_for_all_data'     => 'bool',
		    'skip_signups_check'              => 'bool'
	    );
    	return $this->validate_options($inputs, $fields, $options);
    }

    public function pta_sus_validate_email_options($inputs) {
    	$options = "email_options";
	    $fields = array(
		    'from_email'                  => 'email',
		    'replyto_email'               => 'email',
		    'cc_email'                    => 'email',
		    'confirmation_email_subject'  => 'text',
		    'confirmation_email_template' => 'textarea',
		    'clear_email_subject'         => 'text',
		    'clear_email_template'        => 'textarea',
		    'reminder_email_subject'      => 'text',
		    'reminder_email_template'     => 'textarea',
		    'reminder2_email_subject'     => 'text',
		    'reminder2_email_template'    => 'textarea',
		    'reminder_email_limit'        => 'integer',
		    'reschedule_email_subject'    => 'text',
		    'reschedule_email_template'   => 'textarea',
		    'individual_emails'           => 'bool',
		    'admin_clear_emails'          => 'bool',
		    'no_chair_emails'             => 'bool',
		    'no_confirmation_emails'      => 'bool',
		    'no_reminder_emails'          => 'bool',
		    'disable_emails'              => 'bool',
		    'replyto_chairs'              => 'bool',
	    );
    	return $this->validate_options($inputs, $fields, $options);
    }

    public function pta_sus_validate_group_options($inputs) {
        $options = "group_options";
        $fields = array(
            'enable_groups' => 'bool',
            );
        return $this->validate_options($inputs, $fields, $options);
    }

    public function pta_sus_validate_integration_options($inputs) {
    	$options = "integration_options";
    	$fields = array(
    		'enable_member_directory' => 'bool',
            'directory_page_id' =>'integer',
            'contact_page_id' => 'integer',
    		);
    	return $this->validate_options($inputs, $fields, $options);
    }

    public function pta_volunteer_main_description() {
        echo '<p> ' . __('Main plugin settings', 'pta-volunteer-sign-up-sheets') . '</p>';
    }

    public function pta_volunteer_email_description() {
        echo '<p> ' . __('Email settings', 'pta-volunteer-sign-up-sheets') . '</p>';
    }

    public function pta_volunteer_integration_description() {
        echo '<p> ' . __('Integration with other plugins', 'pta-volunteer-sign-up-sheets') . '</p>';
        if (!is_plugin_active( 'pta-member-directory/pta-member-directory.php' )) {
            $link = '<a href="http://wordpress.org/plugins/pta-member-directory/">http://wordpress.org/plugins/pta-member-directory/</a>';
            echo '<p> ' . __('This plugin can integrate with the PTA Member Directory and Contact Form plugin to set contacts for each sign-up sheet, with contact links being directed to the contact form.', 'pta-volunteer-sign-up-sheets') . '</p>';
            echo '<p> ' . sprintf(__('Search for "PTA Member Directory" from your Install Plugins page, or download from Wordpress.org here: %s', 'pta-volunteer-sign-up-sheets'), $link ) . '</p>';
        }
    }

    public function volunteer_page_id_select() {
        $args = array(
            'name'          => 'pta_volunteer_sus_main_options[volunteer_page_id]',
            'selected'      => $this->main_options['volunteer_page_id'],
            'show_option_none'  => __('None', 'pta-volunteer-sign-up-sheets'),
            'option_none_value' => 0,
            'post_status' => array('publish','private')
            );
        wp_dropdown_pages( $args );
        echo '<em> ' . __('The page where you put the shortcode</em> [pta_sign_up_sheet] <em>to generate the main sign-ups page. If you are using the widget, this MUST be set to a page with the main shortcode on it, or your widget links will NOT work!', 'pta-volunteer-sign-up-sheets') . '</em>';
    }

    public function directory_page_id_select() {
        $args = array(
            'name'          => 'pta_volunteer_sus_integration_options[directory_page_id]',
            'selected'      => $this->integration_options['directory_page_id'],
            'show_option_none'  => __('None', 'pta-volunteer-sign-up-sheets'),
            'option_none_value' => 0,
            'post_status' => array('publish','private')
            );
        wp_dropdown_pages( $args );
        echo '<em> ' . __('The member directory page where you put the shortcode [pta_member_directory]', 'pta-volunteer-sign-up-sheets') . '</em>';
    }

    public function contact_page_id_select() {
        $args = array(
            'name'          => 'pta_volunteer_sus_integration_options[contact_page_id]',
            'selected'      => $this->integration_options['contact_page_id'],
            'show_option_none'  => __('None', 'pta-volunteer-sign-up-sheets'),
            'option_none_value' => 0,
            'post_status' => array('publish','private')
            );
        wp_dropdown_pages( $args );
        echo '<em> ' . __('The member directory contact form page where you put the shortcode [pta_member_contact] if you are using the separate contact form.', 'pta-volunteer-sign-up-sheets') . '</em>';
    }

	public function signup_search_tables_select() {
		$selected = $this->main_options['signup_search_tables'];
		$options = array(
			'signups' => __('Sign-Ups', 'pta-volunteer-sign-up-sheets'),
			'users' => __('WP Users', 'pta-volunteer-sign-up-sheets'),
			'both' => __('Both Tables', 'pta-volunteer-sign-up-sheets')
		);
		?>
		<select id="signup_search_tables" name="pta_volunteer_sus_main_options[signup_search_tables]">
			<?php foreach ($options as $value => $display): ?>
			<option value="<?php echo esc_attr($value); ?>" <?php selected($value, $selected); ?> ><?php echo esc_html($display); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
		echo '<em> ' . __('If live search is enabled, select if you want to search for volunteers in the sign-up table, or for users in the WP Users table, or both tables.', 'pta-volunteer-sign-up-sheets') . '</em>';
	}

    public function test_mode_message_text_input() {
        echo '<input id="test_mode_message" name="pta_volunteer_sus_main_options[test_mode_message]" size="80" type="text" value="'.esc_attr($this->main_options["test_mode_message"]).'" />';
        echo '<em> ' . __('The message users see on volunteer sign-up pages when in test mode.', 'pta-volunteer-sign-up-sheets') . '</em>';
    }

    public function login_required_message_text_input() {
	    echo '<input id="login_required_message" name="pta_volunteer_sus_main_options[login_required_message]" size="80" type="text" value="'.esc_attr($this->main_options["login_required_message"]).'" />';
        echo '<em> ' . __('The message users see on volunteer sign-up pages when they are not logged in and the Login Required option is enabled.', 'pta-volunteer-sign-up-sheets') . '</em>';
    }

	public function login_signup_message_text_input() {
		echo '<input id="login_signup_message" name="pta_volunteer_sus_main_options[login_signup_message]" size="20" type="text" value="'.esc_attr($this->main_options["login_signup_message"]).'" />';
		echo '<em> ' . __('The message users see on task list and sign-up form when they are not logged in and the "Login Required for Signup" option is enabled.', 'pta-volunteer-sign-up-sheets') . '</em>';
	}

    public function from_email_text_input() {
	    echo '<input id="from_email" name="pta_volunteer_sus_email_options[from_email]" size="40" type="text" value="'.esc_attr($this->email_options["from_email"]).'" />';
        echo '<em> ' . __('The email address that confirmation and reminder emails will be sent from.', 'pta-volunteer-sign-up-sheets') . '</em>';
    }

    public function replyto_email_text_input() {
	    echo '<input id="replyto_email" name="pta_volunteer_sus_email_options[replyto_email]" size="40" type="text" value="'.esc_attr($this->email_options["replyto_email"]).'" />';
        echo '<em> ' . __('The Reply-To email address for confirmation and reminder emails.', 'pta-volunteer-sign-up-sheets') . '</em>';
    }

    public function cc_email_text_input() {
	    echo '<input id="cc_email" name="pta_volunteer_sus_email_options[cc_email]" size="40" type="text" value="'.esc_attr($this->email_options["cc_email"]).'" />';
        echo '<em> ' . __('Global CC email address for signup confirmation and signup cleared emails. This email is in ADDITION TO the chair contact emails for sheets and will apply to ALL sheets. Useful for notifying admin or the head volunteer coordinator. Leave blank to only notify the chairs entered for each sheet.', 'pta-volunteer-sign-up-sheets') . '</em>';
    }

    public function confirmation_email_subject_text_input() {
	    echo '<input id="confirmation_email_subject" name="pta_volunteer_sus_email_options[confirmation_email_subject]" size="60" type="text" value="'.esc_attr($this->email_options["confirmation_email_subject"]).'" />';
        echo '<em> ' . __('Subject line for signup confirmation email messages. Template tags can be used.', 'pta-volunteer-sign-up-sheets') .'</em>';
    }

    public function clear_email_subject_text_input() {
	    echo '<input id="clear_email_subject" name="pta_volunteer_sus_email_options[clear_email_subject]" size="60" type="text" value="'.esc_attr($this->email_options["clear_email_subject"]).'" />';
        echo '<em> ' . __('Subject line for cleared signup email messages. Template tags can be used.', 'pta-volunteer-sign-up-sheets') .'</em>';
    }

    public function reminder_email_subject_text_input() {
	    echo '<input id="reminder_email_subject" name="pta_volunteer_sus_email_options[reminder_email_subject]" size="60" type="text" value="'.esc_attr($this->email_options["reminder_email_subject"]).'" />';
        echo '<em> '. __('Subject line for signup reminder email messages. Template tags can be used.', 'pta-volunteer-sign-up-sheets') . '</em>';
    }
	
	public function reminder2_email_subject_text_input() {
		echo '<input id="reminder2_email_subject" name="pta_volunteer_sus_email_options[reminder2_email_subject]" size="60" type="text" value="'.esc_attr($this->email_options["reminder2_email_subject"]).'" />';
		echo '<em> '. __('Subject line for signup reminder #2 email messages. LEAVE BLANK to use same subject template (above) for both reminders. Template tags can be used.', 'pta-volunteer-sign-up-sheets') . '</em>';
	}

    public function reschedule_email_subject_text_input() {
        echo '<input id="reschedule_email_subject" name="pta_volunteer_sus_email_options[reschedule_email_subject]" size="60" type="text" value="'.esc_attr($this->email_options["reschedule_email_subject"]).'" />';
        echo '<em> '. __('Subject line for reschedule email messages. Template tags can be used.', 'pta-volunteer-sign-up-sheets') . '</em>';
    }

    public function reminder_email_limit_text_input() {
        echo "<input id='reminder_email_limit' name='pta_volunteer_sus_email_options[reminder_email_limit]' size='5' type='text' value='{$this->email_options['reminder_email_limit']}' />";
        echo '<em> '. __('Max # of reminder emails to send out in a hour. Leave blank or zero for no limit.', 'pta-volunteer-sign-up-sheets') . '</em>';
    }

    public function enable_member_directory_checkbox() {
        if(isset($this->integration_options['enable_member_directory']) && true === $this->integration_options['enable_member_directory']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        $pta_md_link = '<a href="http://wordpress.org/plugins/pta-member-directory/">'.__('PTA Member Directory and Contact Form', 'pta-volunteer-sign-up-sheets').'</a>';
        ?>
        <input name="pta_volunteer_sus_integration_options[enable_member_directory]" type="checkbox" value="1" <?php echo $checked; ?> />
        <em><?php printf( __('Enable integration with the %s', 'pta-volunteer-sign-up-sheets'), $pta_md_link); ?></em>
        <?php
    }

    public function enable_test_mode_checkbox() {
        if(isset($this->main_options['enable_test_mode']) && true === $this->main_options['enable_test_mode']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[enable_test_mode]" type="checkbox" value="1" <?php echo $checked; ?> />
        <em><?php _e('Puts Volunteer Sign-up Sheet system in test mode. Only admin level users can view public side sign-up sheets.', 'pta-volunteer-sign-up-sheets'); ?></em>
        <?php
    }

    public function hide_volunteer_names_checkbox() {
        if(isset($this->main_options['hide_volunteer_names']) && true === $this->main_options['hide_volunteer_names']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[hide_volunteer_names]" type="checkbox" value="1" <?php echo $checked; ?> />
        <em><?php _e('If checked filled sign-up slots will show "Filled" instead of first name and last initial of volunteer.', 'pta-volunteer-sign-up-sheets'); ?></em>
        <?php
    }
	
	public function show_full_name_checkbox() {
		if(isset($this->main_options['show_full_name']) && true === $this->main_options['show_full_name']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_main_options[show_full_name]" type="checkbox" value="1" <?php echo $checked; ?> />
        <em><?php _e('If checked, and not hiding names (above), this will show the full first and last name instead of first name and last initial', 'pta-volunteer-sign-up-sheets'); ?></em>
		<?php
	}

	public function hide_details_qty_checkbox() {
		if(isset($this->main_options['hide_details_qty']) && true === $this->main_options['hide_details_qty']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
		<input name="pta_volunteer_sus_main_options[hide_details_qty]" type="checkbox" value="1" <?php echo $checked; ?> />
		<em><?php _e('If checked the item details and quantity fields will be hidden from the task list on the sheet details page.', 'pta-volunteer-sign-up-sheets'); ?></em>
		<?php
	}

	public function hide_signups_details_qty_checkbox() {
		if(isset($this->main_options['hide_signups_details_qty']) && true === $this->main_options['hide_signups_details_qty']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
		<input name="pta_volunteer_sus_main_options[hide_signups_details_qty]" type="checkbox" value="1" <?php echo $checked; ?> />
		<em><?php _e('If checked the item details and quantity fields will be hidden from the list of User Signups, when a user is signed in.', 'pta-volunteer-sign-up-sheets'); ?></em>
		<?php
	}

    public function show_remaining_checkbox() {
        if ( isset( $this->main_options['show_remaining'] ) && true === $this->main_options['show_remaining'] ) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[show_remaining]" type="checkbox"
               value="1" <?php echo $checked; ?> />
        <?php
        echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( 'When checked, the number of remaining sign-up slots for a task/item will be shown on one line in the task list with a single sign-up link, instead of showing a separate line for each of the remaining quantity of that task/item.', 'pta-volunteer-sign-up-sheets' ) . '</em>';
    }
	
	public function phone_required_checkbox() {
		if ( isset( $this->main_options['phone_required'] ) && true === $this->main_options['phone_required'] ) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_main_options[phone_required]" type="checkbox"
               value="1" <?php echo $checked; ?> />
		<?php
		echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( 'When checked, and as long as you are not hiding the phone field, the phone field will be required. Un-check if you want to show the phone field but have it be optional.', 'pta-volunteer-sign-up-sheets' ) . '</em>';
	}
	
	public function use_divs_checkbox() {
		if ( isset( $this->main_options['use_divs'] ) && true === $this->main_options['use_divs'] ) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_main_options[use_divs]" type="checkbox"
               value="1" <?php echo $checked; ?> />
		<?php
		echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( 'When checked, styled divs will be used to replace all tables in the public output (sheet list, task list, user signup list).', 'pta-volunteer-sign-up-sheets' ) . '</em>';
	}
	
	public function disable_css_checkbox() {
		if ( isset( $this->main_options['disable_css'] ) && true === $this->main_options['disable_css'] ) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_main_options[disable_css]" type="checkbox"
               value="1" <?php echo $checked; ?> />
		<?php
		echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( 'When checked, the plugin will NOT queue up its own CSS style sheet.', 'pta-volunteer-sign-up-sheets' ) . '</em>';
	}

	public function disable_user_signups_checkbox() {
		if ( isset( $this->main_options['disable_user_signups'] ) && true === $this->main_options['disable_user_signups'] ) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_main_options[disable_user_signups]" type="checkbox"
               value="1" <?php echo $checked; ?> />
		<?php
		echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( "When checked, the list that shows the loggged in user's signups will NOT be shown below the list of signup sheets. You can then use the separate shortcode for the user signups list to display that list anywhere you wish.", 'pta-volunteer-sign-up-sheets' ) . '</em>';
	}

    public function hide_contact_info_checkbox() {
        if(isset($this->main_options['hide_contact_info']) && true === $this->main_options['hide_contact_info']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[hide_contact_info]" type="checkbox" value="1" <?php echo $checked; ?> />
        <em><?php _e('If checked sheet chair contact info will NOT be shown to the public.', 'pta-volunteer-sign-up-sheets'); ?></em>
        <?php
    }

    public function show_ongoing_in_widget_checkbox() {
        if(isset($this->main_options['show_ongoing_in_widget']) && true === $this->main_options['show_ongoing_in_widget']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[show_ongoing_in_widget]" type="checkbox" value="1" <?php echo $checked; ?> />
        <em><?php _e('If checked, Ongoing events will be shown in sidebar widget.', 'pta-volunteer-sign-up-sheets'); ?></em>
        <?php
    }

    public function show_ongoing_last_checkbox() {
        if(isset($this->main_options['show_ongoing_last']) && true === $this->main_options['show_ongoing_last']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[show_ongoing_last]" type="checkbox" value="1" <?php echo $checked; ?> />
        <em><?php _e('If checked, Ongoing events will be shown at the bottom of sign up sheet lists (and widget, if enabled).', 'pta-volunteer-sign-up-sheets'); ?></em>
        <?php
    }

    public function no_phone_checkbox() {
        if(isset($this->main_options['no_phone']) && true === $this->main_options['no_phone']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[no_phone]" type="checkbox" value="1" <?php echo $checked; ?> />
        <em><?php _e('If checked, the Phone field will be removed from the public Sign-Up form.', 'pta-volunteer-sign-up-sheets'); ?></em>
        <?php
    }

    public function login_required_checkbox() {
        if(isset($this->main_options['login_required']) && true === $this->main_options['login_required']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[login_required]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('Force user to be logged in before they can view or sign-up for any volunteer sheets.', 'pta-volunteer-sign-up-sheets').'</em>';
    }

	public function login_required_signup_checkbox() {
		if(isset($this->main_options['login_required_signup']) && true === $this->main_options['login_required_signup']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
		<input name="pta_volunteer_sus_main_options[login_required_signup]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('Force user to be logged in before they can sign-up for any volunteer sheets. If the above box is checked, this has no effect. But you can un-check the above "Login Required" option to allow guests to view the sign-up sheets, but then check this box to prevent guests from signing up.', 'pta-volunteer-sign-up-sheets').'</em>';
	}

    public function readonly_signup_checkbox() {
        if(isset($this->main_options['readonly_signup']) && true === $this->main_options['readonly_signup']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[readonly_signup]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('If you require users to be logged in to view and sign-up, enabling this option will make name and email fields on the signup form "read only", if the information already exists in their user meta. They will not be able to alter first name, last name, or email when signing up.', 'pta-volunteer-sign-up-sheets').'</em>';
    }

    public function show_login_link_checkbox() {
        if(isset($this->main_options['show_login_link']) && true === $this->main_options['show_login_link']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[show_login_link]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('If login is required, this will show a login link under the login required message.', 'pta-volunteer-sign-up-sheets').'</em>';
    }

    public function disable_signup_login_notice_checkbox() {
        if(isset($this->main_options['disable_signup_login_notice']) && true === $this->main_options['disable_signup_login_notice']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[disable_signup_login_notice]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('Turn off the notice strongly suggesting volunteers login before signing up for a volunteer slot (on signup form page) and the notice to login to view/edit signups on the main volunteer list page.', 'pta-volunteer-sign-up-sheets').'</em>';
    }

	public function no_global_overlap_checkbox() {
		if(isset($this->main_options['no_global_overlap']) && true === $this->main_options['no_global_overlap']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_main_options[no_global_overlap]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('Checking this option will check ALL user signups, across ALL sheets, to see if the same user has already signed up for another task on the same date with overlapping times. If so, an error message will be shown and they will not be able to sign up. This is a global setting. If you only want to check for overlapping times on a single sheet, use the setting on that sheet. Checking this will ignore that per sheet setting and always check all signups for the user.', 'pta-volunteer-sign-up-sheets').'</em>';
	}

    public function enable_cron_notifications_checkbox() {
        if(isset($this->main_options['enable_cron_notifications']) && true === $this->main_options['enable_cron_notifications']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[enable_cron_notifications]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('Sends site admin an email whenever a CRON job is completed (such as sending reminders or deleting expired signups).', 'pta-volunteer-sign-up-sheets').'</em>';
    }

    public function detailed_reminder_admin_emails_checkbox() {
        if(isset($this->main_options['detailed_reminder_admin_emails']) && true === $this->main_options['detailed_reminder_admin_emails']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[detailed_reminder_admin_emails]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('Admin reminder emails notification will include the message body of all reminders sent, useful for troubleshooting.', 'pta-volunteer-sign-up-sheets').'</em>';
    }

    public function skip_signups_check_checkbox() {
        if(isset($this->main_options['skip_signups_check']) && true === $this->main_options['skip_signups_check']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[skip_signups_check]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('Skip the check that compares current task signup count against the task quantity entered on the Admin edit tasks page. This will allow you to change the quantity for a task to a number lower than the number already signed up (useful if you want to save old signups for a recurring task, but change the quantity for future occurrences). NOTE: This will also skip the check for existing signups when you remove a task from a sheet! If you remove the task, you will no longer be able to view any signups for that tasks (even though they are still in the signups table).', 'pta-volunteer-sign-up-sheets').'</em>';
    }

    public function show_expired_tasks_checkbox() {
        if(isset($this->main_options['show_expired_tasks']) && true === $this->main_options['show_expired_tasks']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[show_expired_tasks]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('Shows expired tasks on the admin View Signups page for a sheet. Expired tasks are not counted in the Total Spots column.', 'pta-volunteer-sign-up-sheets').'</em>';
    }

    public function clear_expired_signups_checkbox() {
        if(isset($this->main_options['clear_expired_signups']) && true === $this->main_options['clear_expired_signups']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[clear_expired_signups]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('Automatically clears expired signups from the database (runs with hourly CRON function). Expired signups are not counted in the Filled Spots column.', 'pta-volunteer-sign-up-sheets').'</em>';
    }

    public function hide_donation_button_checkbox() {
        if(isset($this->main_options['hide_donation_button']) && true === $this->main_options['hide_donation_button']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[hide_donation_button]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('Hides the donation button at bottom of settings pages.', 'pta-volunteer-sign-up-sheets').'</em>';
    }

	public function enable_signup_search_checkbox() {
		if(isset($this->main_options['enable_signup_search']) && true === $this->main_options['enable_signup_search']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
		<input name="pta_volunteer_sus_main_options[enable_signup_search]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('Allows admin and sign-up sheet managers to do live search of volunteers by first or last name on the sign-up form, allowing them to sign-up other volunteers quickly from the front end. If this is disabled, the extra javascript will not be enqueued.', 'pta-volunteer-sign-up-sheets').'</em>';
	}

	public function signup_redirect_checkbox() {
		if(isset($this->main_options['signup_redirect']) && true === $this->main_options['signup_redirect']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
		<input name="pta_volunteer_sus_main_options[signup_redirect]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('If checked, sign-up links will always go to the main volunteer page (set above). Un-check this if you are using different shortcodes on different pages to display different sheets and want to stay on that page when displaying the sign up form.', 'pta-volunteer-sign-up-sheets').'</em>';
	}

	public function individual_emails_checkbox() {
		if(isset($this->email_options['individual_emails']) && true === $this->email_options['individual_emails']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
		<input name="pta_volunteer_sus_email_options[individual_emails]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('If checked, any CC or BCC email addresses (chairs, global CC, admin) will be sent individually as separate emails, as opposed to one email with several CC or BCC addresses in the header. This could solve some email issues on some servers when using the the built-in wp_mail function (PHP sendmail), instead of an SMTP email plugin (a better choice), where none or only some of the CC/BCC recipients actually get the email. Note, however, that this could send out a large number of emails at once, and you should be aware of any server limits on the number of emails sent per hour.', 'pta-volunteer-sign-up-sheets').'</em>';
	}
	
	public function admin_clear_emails_checkbox() {
		if(isset($this->email_options['admin_clear_emails']) && true === $this->email_options['admin_clear_emails']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_email_options[admin_clear_emails]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('If checked, clear emails will be sent when a spot is cleared from the admin view signups page.', 'pta-volunteer-sign-up-sheets').'</em>';
	}

	public function no_chair_emails_checkbox() {
		if(isset($this->email_options['no_chair_emails']) && true === $this->email_options['no_chair_emails']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_email_options[no_chair_emails]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('If checked, sign-up and clear emails will NOT get copied to chairs/contacts.', 'pta-volunteer-sign-up-sheets').'</em>';
	}

    public function no_confirmation_emails_checkbox() {
		if(isset($this->email_options['no_confirmation_emails']) && true === $this->email_options['no_confirmation_emails']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_email_options[no_confirmation_emails]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('If checked, sign-up confirmation emails will not be sent. NOTE: This will also disable the emails to the chair when someone signs up as those are CC/BCC fields in the confirmation email.', 'pta-volunteer-sign-up-sheets').'</em>';
	}

    public function no_reminder_emails_checkbox() {
		if(isset($this->email_options['no_reminder_emails']) && true === $this->email_options['no_reminder_emails']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_email_options[no_reminder_emails]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('If checked, sign-up reminder emails will not be sent.', 'pta-volunteer-sign-up-sheets').'</em>';
	}
	
	public function disable_emails_checkbox() {
		if(isset($this->email_options['disable_emails']) && true === $this->email_options['disable_emails']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_email_options[disable_emails]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('If checked, ALL emails will be disabled (including reminder emails). Useful if you want to clear and manually sign up users without emails being sent (when making corrections, for example). Reminders will start to get checked and sent again after you turn this off.', 'pta-volunteer-sign-up-sheets').'</em>';
	}
	
	public function replyto_chairs_checkbox() {
		if(isset($this->email_options['replyto_chairs']) && true === $this->email_options['replyto_chairs']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_email_options[replyto_chairs]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('If checked, Chair emails will be set as the reply-to address in notification emails. Above reply-to email will be ignored when this is checked.', 'pta-volunteer-sign-up-sheets').'</em>';
	}
	
	public function suppress_duplicates_checkbox() {
		if ( isset( $this->main_options['suppress_duplicates'] ) && true === $this->main_options['suppress_duplicates'] ) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_main_options[suppress_duplicates]" type="checkbox"
               value="1" <?php echo $checked; ?> />
		<?php
		echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( 'When checked, the plugin will suppress duplicated signup forms and messages when you are using multiple shortcodes on the same page without using the redirect option (redirecting to a page with only one shortcode for signup). If you have a theme or plugin that is triggering shortcode functions more than once on a page, causing a blank page, or if you never use more than one shortcode on a page without redirecting, you can un-check this option.', 'pta-volunteer-sign-up-sheets' ) . '</em>';
	}

	public function admin_only_settings_checkbox() {
		if ( isset( $this->main_options['admin_only_settings'] ) && true === $this->main_options['admin_only_settings'] ) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_main_options[admin_only_settings]" type="checkbox"
               value="1" <?php echo $checked; ?> />
		<?php
		echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( 'Check this to only allow Admin to access the Settings and CRON Functions pages for this plugin.', 'pta-volunteer-sign-up-sheets' ) . '</em>';
	}

	public function disable_datei18n_settings_checkbox() {
		if ( isset( $this->main_options['disable_datei18n'] ) && true === $this->main_options['disable_datei18n'] ) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_main_options[disable_datei18n]" type="checkbox"
               value="1" <?php echo $checked; ?> />
		<?php
		echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( 'Check this to disable the WordPress Date/Time Translation function, and to use the simple PHP Date format function instead (not translatable). This will fix issues with times shown due to other plugins that set a timezone offset after the changes in WordPress version 5.3', 'pta-volunteer-sign-up-sheets' ) . '</em>';
	}

	public function disable_grouping_settings_checkbox() {
        if ( isset( $this->main_options['disable_grouping'] ) && true === $this->main_options['disable_grouping'] ) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_main_options[disable_grouping]" type="checkbox"
               value="1" <?php echo $checked; ?> />
		<?php
		echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( 'Check this to disable the row grouping feature of DataTables on the admin side View Signups and View All Data pages. Checking this will completely disable that feature and show all columns by default.', 'pta-volunteer-sign-up-sheets' ) . '</em>';
    }

    public function show_all_slots_for_all_data_checkbox() {
        if ( isset( $this->main_options['show_all_slots_for_all_data'] ) && true === $this->main_options['show_all_slots_for_all_data'] ) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_main_options[show_all_slots_for_all_data]" type="checkbox"
               value="1" <?php echo $checked; ?> />
		<?php
		echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( 'Check this to show each empty slot in its own row on the admin View/Export ALL Data page. Useful in you want to print a manual signup form for multiple sheets at once. NOTE that this will greatly slow down the load time and dataTables initialization time of that page.', 'pta-volunteer-sign-up-sheets' ) . '</em>';
    }

    public function confirmation_email_template_textarea_input() {
        echo "<textarea id='confirmation_email_template' name='pta_volunteer_sus_email_options[confirmation_email_template]' cols='55' rows='15' >";
        echo esc_textarea( $this->email_options['confirmation_email_template'] );
        echo '</textarea>';
        echo '<br />' . __('Email user receives when they sign up for a volunteer slot.', 'pta-volunteer-sign-up-sheets');
        echo '<br />' . __('Available Template Tags: ', 'pta-volunteer-sign-up-sheets') . '{sheet_title} {sheet_details} {task_title} {task_description} {date} {start_time} {end_time} {details_text} {item_details} {item_qty} {firstname} {lastname} {phone} {email} {contact_emails} {contact_names} {site_name} {site_url}';
    }

    public function reminder_email_template_textarea_input() {
        echo "<textarea id='reminder_email_template' name='pta_volunteer_sus_email_options[reminder_email_template]' cols='55' rows='15' >";
        echo esc_textarea( $this->email_options['reminder_email_template'] );
        echo '</textarea>';
        echo '<br />' . __('Reminder email sent to volunteers.', 'pta-volunteer-sign-up-sheets');
        echo '<br />' . __('Available Template Tags: ', 'pta-volunteer-sign-up-sheets') . '{sheet_title} {sheet_details} {task_title} {task_description} {date} {start_time} {end_time} {details_text} {item_details} {item_qty} {firstname} {lastname} {phone} {email} {contact_emails} {contact_names} {site_name} {site_url}';
    }
	
	public function reminder2_email_template_textarea_input() {
		echo "<textarea id='reminder2_email_template' name='pta_volunteer_sus_email_options[reminder2_email_template]' cols='55' rows='15' >";
		echo esc_textarea( $this->email_options['reminder2_email_template'] );
		echo '</textarea>';
		echo '<br />' . __('Reminder #2 email sent to volunteers. LEAVE BLANK to use the same (first) message template for both reminders', 'pta-volunteer-sign-up-sheets');
		echo '<br />' . __('Available Template Tags: ', 'pta-volunteer-sign-up-sheets') . '{sheet_title} {sheet_details} {task_title} {task_description} {date} {start_time} {end_time} {details_text} {item_details} {item_qty} {firstname} {lastname} {phone} {email} {contact_emails} {contact_names} {site_name} {site_url}';
	}

    public function reschedule_email_template_textarea_input() {
        echo "<textarea id='reschedule_email_template' name='pta_volunteer_sus_email_options[reschedule_email_template]' cols='55' rows='15' >";
        echo esc_textarea( $this->email_options['reschedule_email_template'] );
        echo '</textarea>';
        echo '<br />' . __('Reschedule email sent to volunteers. Template tags will show the new dates and times.', 'pta-volunteer-sign-up-sheets');
        echo '<br />' . __('Reschedule emails will be sent hourly via the same CRON job and limits set for reminder emails.', 'pta-volunteer-sign-up-sheets');
        echo '<br />' . __('Available Template Tags: ', 'pta-volunteer-sign-up-sheets') . '{sheet_title} {sheet_details} {task_title} {task_description} {date} {start_time} {end_time} {details_text} {item_details} {item_qty} {firstname} {lastname} {phone} {email} {contact_emails} {contact_names} {site_name} {site_url}';
    }

    public function clear_email_template_textarea_input() {
        echo "<textarea id='clear_email_template' name='pta_volunteer_sus_email_options[clear_email_template]' cols='55' rows='15' >";
        echo esc_textarea( $this->email_options['clear_email_template'] );
        echo '</textarea>';
        echo '<br />' . __('Cleared signup email sent to volunteers when they clear themselves from a signup.', 'pta-volunteer-sign-up-sheets');
        echo '<br />' . __('Available Template Tags: ', 'pta-volunteer-sign-up-sheets') . '{sheet_title} {sheet_details} {task_title} {task_description} {date} {start_time} {end_time} {details_text} {item_details} {item_qty} {firstname} {lastname} {phone} {email} {contact_emails} {contact_names} {site_name} {site_url}';
    }

} // End Class
/* EOF */