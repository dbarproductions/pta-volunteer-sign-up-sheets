<?php
/**
* Admin Setting page
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PTA_SUS_Options {
	public $main_options;
	public $email_options;
	public $integration_options;
    public $validation_options;
	public $member_directory_active;
	private $settings_page_slug = 'pta-sus-settings_settings';

	public function __construct() {
		$this->main_options = get_option( 'pta_volunteer_sus_main_options' );
		$this->email_options = get_option( 'pta_volunteer_sus_email_options' );
		$this->integration_options = get_option( 'pta_volunteer_sus_integration_options' );
        $this->validation_options = get_option( 'pta_volunteer_sus_validation_options' );
		add_action('admin_init', array($this, 'register_options'));

	} // End Construct

	/**
    * Admin Page: Options/Settings
    */
    function admin_options() {
        // Check permissions - only Admins and Managers (with manage_others_signup_sheets) can access
        // Authors (only manage_signup_sheets) should NOT be able to access settings
        if (!current_user_can('manage_options') && !current_user_can('manage_others_signup_sheets'))  {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'pta-volunteer-sign-up-sheets' ) );
        }
        $docs_link = '<a class="button-secondary" href="https://stephensherrardplugins.com/docs/pta-volunteer-sign-up-sheets-documentation/" target="_blank">'.__('Documentation', 'pta-volunteer-sign-up-sheets') . '</a>';

        ?>
        <div class="wrap pta_sus">
            <div id="icon-themes" class="icon32"></div>
            <h2><?php _e('PTA Volunteer Sign-up Sheets Settings', 'pta-volunteer-sign-up-sheets'); ?></h2>
            <p><?php echo $docs_link; ?></p>
            <?php settings_errors(); ?>
            <?php $active_tab = $_GET['tab'] ?? 'main_options'; ?>
            <h2 class="nav-tab-wrapper">  
                <a href="?page=<?php echo $this->settings_page_slug?>&tab=main_options" class="nav-tab <?php echo $active_tab == 'main_options' ? 'nav-tab-active' : ''; ?>"><?php _e('Main Settings', 'pta-volunteer-sign-up-sheets'); ?></a>
                <a href="?page=<?php echo $this->settings_page_slug?>&tab=email_options" class="nav-tab <?php echo $active_tab == 'email_options' ? 'nav-tab-active' : ''; ?>"><?php _e('Email Settings', 'pta-volunteer-sign-up-sheets'); ?></a>
                <a href="?page=<?php echo $this->settings_page_slug?>&tab=validation_options" class="nav-tab <?php echo $active_tab == 'validation_options' ? 'nav-tab-active' : ''; ?>"><?php _e('Validation Settings', 'pta-volunteer-sign-up-sheets'); ?></a>
                <a href="?page=<?php echo $this->settings_page_slug?>&tab=integration_options" class="nav-tab <?php echo $active_tab == 'integration_options' ? 'nav-tab-active' : ''; ?>"><?php _e('Integration Settings', 'pta-volunteer-sign-up-sheets'); ?></a>
                <a href="?page=<?php echo $this->settings_page_slug?>&tab=license" class="nav-tab <?php echo $active_tab == 'license' ? 'nav-tab-active' : ''; ?>"><?php _e('License', 'pta-volunteer-sign-up-sheets'); ?></a>
                <?php do_action('pta_sus_settings_nav_tabs', $active_tab); ?>
            </h2>
	        <?php
            if('email_options' == $active_tab ) {
	            PTA_SUS_Template_Tags_Helper::render_helper_panel();
            }
	        if('license' === $active_tab) {
                $this->maybe_process_license_form();
		        include( PTA_VOLUNTEER_SUS_DIR . 'views/html-admin-license-form.php' );
	        } else { ?>
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
                } elseif('validation_options' == $active_tab ) {
                	settings_fields('pta_volunteer_sus_validation_options');
                	do_settings_sections('pta_volunteer_sus_validation');
                } else {
                    // Allow extensions to create their own tabs
                    do_action('pta_sus_extensions_settings_tabs', $active_tab);
                }
                submit_button();
                ?>
            </form>
            <?php } ?>
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
	    add_settings_field('enable_mobile_css', __('Enable Mobile CSS?', 'pta-volunteer-sign-up-sheets'), array($this, 'enable_mobile_css_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('disable_user_signups', __('Disable User Signups List?', 'pta-volunteer-sign-up-sheets'), array($this, 'disable_user_signups_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('hide_volunteer_names', __('Hide volunteer names from public?', 'pta-volunteer-sign-up-sheets'), array($this, 'hide_volunteer_names_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('show_full_name', __('Show full name?', 'pta-volunteer-sign-up-sheets'), array($this, 'show_full_name_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('show_remaining', __('Consolidate remaining slots?', 'pta-volunteer-sign-up-sheets'), array($this, 'show_remaining_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('hide_details_qty', __('Hide Details and Quantities', 'pta-volunteer-sign-up-sheets'), array($this, 'hide_details_qty_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('hide_signups_details_qty', __('Hide User Signups Details and Quantities', 'pta-volunteer-sign-up-sheets'), array($this, 'hide_signups_details_qty_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('hide_contact_info', __('Hide chair contact info from public?', 'pta-volunteer-sign-up-sheets'), array($this, 'hide_contact_info_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('hide_single_date_header', __('Hide Date header for Single date events?', 'pta-volunteer-sign-up-sheets'), array($this, 'hide_single_date_header_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
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
	    add_settings_field('show_task_description_on_signup_form', __('Show Task Description on Signup Form?', 'pta-volunteer-sign-up-sheets'), array($this, 'show_task_description_on_signup_form_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('enable_cron_notifications', __('Enable CRON Notifications?', 'pta-volunteer-sign-up-sheets'), array($this, 'enable_cron_notifications_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('detailed_reminder_admin_emails', __('Detailed Reminder Notifications?', 'pta-volunteer-sign-up-sheets'), array($this, 'detailed_reminder_admin_emails_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('skip_signups_check', __('Skip Signups Check?', 'pta-volunteer-sign-up-sheets'), array($this, 'skip_signups_check_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('show_expired_tasks', __('Show Expired Tasks?', 'pta-volunteer-sign-up-sheets'), array($this, 'show_expired_tasks_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('clear_expired_signups', __('Automatically clear expired signups?', 'pta-volunteer-sign-up-sheets'), array($this, 'clear_expired_signups_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('clear_expired_sheets', __('Automatically clear expired sheets?', 'pta-volunteer-sign-up-sheets'), array($this, 'clear_expired_sheets_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('num_days_expired', __('# of days before clear?', 'pta-volunteer-sign-up-sheets'), array($this, 'num_days_expired_input'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('enable_signup_search', __('Enable Sign-up form live search?', 'pta-volunteer-sign-up-sheets'), array($this, 'enable_signup_search_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('signup_search_tables', __('Live Search Tables', 'pta-volunteer-sign-up-sheets'), array($this, 'signup_search_tables_select'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('admin_only_settings', __('Admin Only Settings Access?', 'pta-volunteer-sign-up-sheets'), array($this, 'admin_only_settings_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('disable_datei18n', __('Disable Date/Time Translation?', 'pta-volunteer-sign-up-sheets'), array($this, 'disable_datei18n_settings_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('disable_grouping', __('Disable Grouping?', 'pta-volunteer-sign-up-sheets'), array($this, 'disable_grouping_settings_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
	    add_settings_field('show_all_slots_for_all_data', __('Show All Slots for All Data?', 'pta-volunteer-sign-up-sheets'), array($this, 'show_all_slots_for_all_data_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('hide_donation_button', __('Hide donation button?', 'pta-volunteer-sign-up-sheets'), array($this, 'hide_donation_button_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');
        add_settings_field('enable_ajax_navigation', __('Enable AJAX Navigation?', 'pta-volunteer-sign-up-sheets'), array($this, 'enable_ajax_navigation_checkbox'), 'pta_volunteer_sus_main', 'pta_volunteer_main');

        // Email Settings
        register_setting( 'pta_volunteer_sus_email_options', 'pta_volunteer_sus_email_options', array($this, 'pta_sus_validate_email_options') );
        add_settings_section('pta_volunteer_email', __('Email Settings', 'pta-volunteer-sign-up-sheets'), array($this, 'pta_volunteer_email_description'), 'pta_volunteer_sus_email');
	    add_settings_field('use_html', __('Send HTML emails?', 'pta-volunteer-sign-up-sheets'), array($this, 'use_html_checkbox'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('from_email', __('FROM email:', 'pta-volunteer-sign-up-sheets'), array($this, 'from_email_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('replyto_email', __('Reply-To email:', 'pta-volunteer-sign-up-sheets'), array($this, 'replyto_email_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
	    add_settings_field('replyto_chairs', __('Reply-To Chairs?', 'pta-volunteer-sign-up-sheets'), array($this, 'replyto_chairs_checkbox'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        add_settings_field('cc_email', __('CC email:', 'pta-volunteer-sign-up-sheets'), array($this, 'cc_email_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
        // Email templates are now managed via the Email Templates admin page
        add_settings_field('reminder_email_limit', __('Max Reminders per Hour:', 'pta-volunteer-sign-up-sheets'), array($this, 'reminder_email_limit_text_input'), 'pta_volunteer_sus_email', 'pta_volunteer_email');
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

        // Validation Settings
        register_setting( 'pta_volunteer_sus_validation_options', 'pta_volunteer_sus_validation_options', array($this, 'pta_sus_validate_validation_options') );
        add_settings_section('pta_volunteer_validation', __('Validation Settings', 'pta-volunteer-sign-up-sheets'), array($this, 'pta_volunteer_validation_description'), 'pta_volunteer_sus_validation');
        add_settings_field('enable_validation', __('Enable Validation:', 'pta-volunteer-sign-up-sheets'), array($this, 'enable_validation_checkbox'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');
	    add_settings_field('require_validation_to_view', __('Require Validation to View:', 'pta-volunteer-sign-up-sheets'), array($this, 'require_validation_to_view_checkbox'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');
	    add_settings_field('require_validation_to_signup', __('Require Validation to Signup:', 'pta-volunteer-sign-up-sheets'), array($this, 'require_validation_to_signup_checkbox'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');
	    add_settings_field('validation_required_message', __('Validation Required Message:', 'pta-volunteer-sign-up-sheets'), array($this, 'validation_required_message_text_input'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');
	    add_settings_field('validation_page_link_text', __('Validation Page link text:', 'pta-volunteer-sign-up-sheets'), array($this, 'validation_page_link_text_text_input'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');
	    add_settings_field('validation_page_id', __('Validation Page:', 'pta-volunteer-sign-up-sheets'), array($this, 'validation_page_id_select'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');
        add_settings_field('enable_signup_validation', __('Enable Signup Validation:', 'pta-volunteer-sign-up-sheets'), array($this, 'enable_signup_validation_checkbox'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');
        add_settings_field('signup_expiration_hours', __('Signup Expiration (hours):', 'pta-volunteer-sign-up-sheets'), array($this, 'signup_expiration_hours_number_input'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');
	    add_settings_field('signup_validation_email_template_id', __('Signup Validation Email Template:', 'pta-volunteer-sign-up-sheets'), array($this, 'signup_validation_email_template_select'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');
	    add_settings_field('disable_cc_validation_signup_emails', __('Disable CC for Signup Validation emails:', 'pta-volunteer-sign-up-sheets'), array($this, 'disable_cc_validation_signup_emails_checkbox'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');
	    add_settings_field('enable_user_validation_form', __('Enable User Validation Form:', 'pta-volunteer-sign-up-sheets'), array($this, 'enable_user_validation_form_checkbox'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');
	    add_settings_field('validation_code_expiration_hours', __('Validation Code Expiration (hours):', 'pta-volunteer-sign-up-sheets'), array($this, 'validation_code_expiration_hours_number_input'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');
	    add_settings_field('validation_form_header', __('User Validation Form Header:', 'pta-volunteer-sign-up-sheets'), array($this, 'validation_form_header_textarea'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');
	    add_settings_field('user_validation_email_template_id', __('User Validation Email Template:', 'pta-volunteer-sign-up-sheets'), array($this, 'user_validation_email_template_select'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');
	    add_settings_field('validation_form_resubmission_minutes', __('Validation Form Resubmission Time (minutes):', 'pta-volunteer-sign-up-sheets'), array($this, 'validation_form_resubmission_minutes_number_input'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');
	    add_settings_field('enable_clear_validation', __('Enable Clear Validation link:', 'pta-volunteer-sign-up-sheets'), array($this, 'enable_clear_validation_checkbox'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');
	    add_settings_field('clear_validation_message', __('Clear Validation Message:', 'pta-volunteer-sign-up-sheets'), array($this, 'clear_validation_message_textarea'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');
	    add_settings_field('clear_validation_link_text', __('Clear Validation link text:', 'pta-volunteer-sign-up-sheets'), array($this, 'clear_validation_link_text_input'), 'pta_volunteer_sus_validation', 'pta_volunteer_validation');

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
	                if( isset($inputs[$field]) && $inputs[ $field ] ) {
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
		    'enable_test_mode'                     => 'bool',
		    'test_mode_message'                    => 'text',
		    'volunteer_page_id'                    => 'integer',
		    'hide_volunteer_names'                 => 'bool',
		    'show_remaining'                       => 'bool',
		    'hide_contact_info'                    => 'bool',
		    'show_ongoing_in_widget'               => 'bool',
		    'show_ongoing_last'                    => 'bool',
		    'no_phone'                             => 'bool',
		    'login_required'                       => 'bool',
		    'login_required_signup'                => 'bool',
		    'login_required_message'               => 'text',
		    'login_signup_message'                 => 'text',
		    'readonly_signup'                      => 'bool',
		    'show_login_link'                      => 'bool',
		    'disable_signup_login_notice'          => 'bool',
		    'enable_cron_notifications'            => 'bool',
		    'detailed_reminder_admin_emails'       => 'bool',
		    'show_expired_tasks'                   => 'bool',
		    'clear_expired_signups'                => 'bool',
		    'clear_expired_sheets'                 => 'bool',
            'num_days_expired'                     => 'integer',
		    'enable_signup_search'                 => 'bool',
		    'hide_donation_button'                 => 'bool',
		    'signup_search_tables'                 => 'text',
		    'hide_details_qty'                     => 'bool',
		    'hide_signups_details_qty'             => 'bool',
		    'signup_redirect'                      => 'bool',
		    'phone_required'                       => 'bool',
		    'details_required'                     => 'bool',
		    'use_divs'                             => 'bool',
		    'disable_css'                          => 'bool',
            'enable_mobile_css'                    => 'bool',
		    'disable_user_signups'                 => 'bool',
		    'show_full_name'                       => 'bool',
		    'suppress_duplicates'                  => 'bool',
		    'show_remaining_slots_csv_export'      => 'bool',
		    'show_dates_csv_export'                => 'bool',
		    'no_global_overlap'                    => 'bool',
		    'admin_only_settings'                  => 'bool',
		    'disable_datei18n'                     => 'bool',
		    'disable_grouping'                     => 'bool',
		    'show_all_slots_for_all_data'          => 'bool',
		    'skip_signups_check'                   => 'bool',
		    'show_task_description_on_signup_form' => 'bool',
            'hide_single_date_header'              => 'bool',
            'enable_ajax_navigation'               => 'bool'
	    );
    	return $this->validate_options($inputs, $fields, $options);
    }

    public function pta_sus_validate_email_options($inputs) {
    	$options = "email_options";
	    $fields = array(
            'use_html'                    => 'bool',
		    'from_email'                  => 'email',
		    'replyto_email'               => 'email',
		    'cc_email'                    => 'email',
		    // Email templates are now managed via the Email Templates admin page
		    'reminder_email_limit'        => 'integer',
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

    public function pta_sus_validate_integration_options($inputs) {
    	$options = "integration_options";
    	$fields = array(
    		'enable_member_directory' => 'bool',
            'directory_page_id' =>'integer',
            'contact_page_id' => 'integer',
    		);
    	return $this->validate_options($inputs, $fields, $options);
    }

	public function pta_sus_validate_validation_options($inputs) {
		$options = "validation_options";
		$fields = array(
            'enable_validation' => 'bool',
            'enable_signup_validation' => 'bool',
            'signup_expiration_hours' => 'integer',
            'validation_code_expiration_hours' => 'integer',
            'signup_validation_email_template_id' => 'integer',
            'user_validation_email_template_id' => 'integer',
            'validation_form_header' => 'textarea',
            'enable_user_validation_form' => 'bool',
            'validation_form_resubmission_minutes' => 'integer',
            'require_validation_to_view' => 'bool',
            'require_validation_to_signup' => 'bool',
            'validation_required_message' => 'text',
            'validation_page_link_text' => 'text',
            'validation_page_id' => 'integer',
            'enable_clear_validation' => 'bool',
            'clear_validation_link_text' => 'text',
            'clear_validation_message' => 'textarea',
            'disable_cc_validation_signup_emails' => 'bool',
		);
		return $this->validate_options($inputs, $fields, $options);
	}

    public function pta_volunteer_main_description() {
        echo '<p> ' . __('The main plugin settings that control the overall functionality.', 'pta-volunteer-sign-up-sheets') . '</p>';
    }

    public function pta_volunteer_email_description() {
        echo '<p> ' . __('Set up email templates and email options', 'pta-volunteer-sign-up-sheets') . '</p>';
    }

    public function pta_volunteer_validation_description() {
        echo '<p> ' . __('If you have disabled the main settings that requires users to be logged in to view and/or signup for signup sheets, you can enable user validation via unique codes, sent via email, to not only validate a signup, but to also allow users without a user account to view and clear their signups.', 'pta-volunteer-sign-up-sheets') . '</p>';
	    echo '<p> ' . __('If a user is logged in to a WordPress user account, they are automatically considered validated, and many of the below options will not be applicable. Signups are automatically validated if a user is logged in, and no cookies or validation codes will be needed to view or clear signups when the user is signed in.', 'pta-volunteer-sign-up-sheets') . '</p>';
    }

    public function pta_volunteer_integration_description() {
        echo '<p> ' . __('Integration with other plugins', 'pta-volunteer-sign-up-sheets') . '</p>';
        if (!is_plugin_active( 'pta-member-directory/pta-member-directory.php' )) {
            $link = '<a href="https://wordpress.org/plugins/pta-member-directory/">https://wordpress.org/plugins/pta-member-directory/</a>';
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

	public function validation_page_id_select() {
		$args = array(
			'name'          => 'pta_volunteer_sus_validation_options[validation_page_id]',
			'selected'      => $this->validation_options['validation_page_id'],
			'show_option_none'  => __('None', 'pta-volunteer-sign-up-sheets'),
			'option_none_value' => 0,
			'post_status' => array('publish','private')
		);
		wp_dropdown_pages( $args );
		echo '<em> ' . __('The validation form page where you put the shortcode [pta_validation_form], or where you inserted the Validation Form block. Validation links are also sent to this page, so you MUST select a page and include the shortcode, or block, on that page.', 'pta-volunteer-sign-up-sheets') . '</em>';
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

    // Email template subject and body fields have been removed - templates are now managed via the Email Templates admin page

    public function reminder_email_limit_text_input() {
        echo "<input id='reminder_email_limit' name='pta_volunteer_sus_email_options[reminder_email_limit]' size='5' type='text' value='{$this->email_options['reminder_email_limit']}' />";
        echo '<em> '. __('Max # of reminder emails to send out in a hour. Leave blank or zero for no limit.', 'pta-volunteer-sign-up-sheets') . '</em>';
    }

    public function num_days_expired_input() {
	    echo "<input id='num_days_expired' name='pta_volunteer_sus_main_options[num_days_expired]' type='number' value='{$this->main_options['num_days_expired']}' min='1' style='width: 5em;'/>";
	    echo '<em> '. __('# of days after the last sheet date (when clearing sheets automatically), or signup date (when clearing signups automatically), before clearing the sheet or signup from the database. Min/default is 1 calendar day. Note this is calendar days and NOT based on hours or task times.', 'pta-volunteer-sign-up-sheets') . '</em>';
    }

    public function signup_validation_email_template_select() {
		$templates = PTA_SUS_Email_Functions::get_available_templates( true );
		if ( empty( $templates ) ) {
			echo '<p>' . __( 'No email templates available. Please create templates on the Email Templates page.', 'pta-volunteer-sign-up-sheets' ) . '</p>';
			return;
		}
		
		$current = isset( $this->validation_options['signup_validation_email_template_id'] ) ? absint( $this->validation_options['signup_validation_email_template_id'] ) : 0;
		$defaults = get_option( 'pta_volunteer_sus_email_template_defaults', array() );
		$system_default_id = isset( $defaults['signup_validation'] ) ? absint( $defaults['signup_validation'] ) : 0;
		
		// Build options array
		$template_options = array(
			0 => __( 'Use System Default', 'pta-volunteer-sign-up-sheets' ) . ( $system_default_id > 0 ? ' (' . __( 'ID:', 'pta-volunteer-sign-up-sheets' ) . ' ' . $system_default_id . ')' : '' ),
		);
		foreach ( $templates as $template ) {
			$label = $template->title;
			if ( $template->is_system_default() ) {
				$label .= ' ' . __( '(System Default)', 'pta-volunteer-sign-up-sheets' );
			}
			$template_options[ $template->id ] = $label;
		}
		
		echo '<select id="signup_validation_email_template_id" name="pta_volunteer_sus_validation_options[signup_validation_email_template_id]">';
		foreach ( $template_options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . __( 'Select the email template to use for signup validation emails. Leave set to "Use System Default" to use the global default template.', 'pta-volunteer-sign-up-sheets' ) . '</p>';
		echo '<p class="description"><strong>' . __( 'Important:', 'pta-volunteer-sign-up-sheets' ) . '</strong> ' . __( 'The template must include the {validation_link} tag to enable validation. You can also use {signup_expiration_hours} to show how much time they have to validate the signup before it is deleted.', 'pta-volunteer-sign-up-sheets' ) . '</p>';
	}

	public function user_validation_email_template_select() {
		$templates = PTA_SUS_Email_Functions::get_available_templates( true );
		if ( empty( $templates ) ) {
			echo '<p>' . __( 'No email templates available. Please create templates on the Email Templates page.', 'pta-volunteer-sign-up-sheets' ) . '</p>';
			return;
		}
		
		$current = isset( $this->validation_options['user_validation_email_template_id'] ) ? absint( $this->validation_options['user_validation_email_template_id'] ) : 0;
		$defaults = get_option( 'pta_volunteer_sus_email_template_defaults', array() );
		$system_default_id = isset( $defaults['user_validation'] ) ? absint( $defaults['user_validation'] ) : 0;
		
		// Build options array
		$template_options = array(
			0 => __( 'Use System Default', 'pta-volunteer-sign-up-sheets' ) . ( $system_default_id > 0 ? ' (' . __( 'ID:', 'pta-volunteer-sign-up-sheets' ) . ' ' . $system_default_id . ')' : '' ),
		);
		foreach ( $templates as $template ) {
			$label = $template->title;
			if ( $template->is_system_default() ) {
				$label .= ' ' . __( '(System Default)', 'pta-volunteer-sign-up-sheets' );
			}
			$template_options[ $template->id ] = $label;
		}
		
		echo '<select id="user_validation_email_template_id" name="pta_volunteer_sus_validation_options[user_validation_email_template_id]">';
		foreach ( $template_options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . __( 'Select the email template to use for user validation emails. Leave set to "Use System Default" to use the global default template.', 'pta-volunteer-sign-up-sheets' ) . '</p>';
		echo '<p class="description"><strong>' . __( 'Important:', 'pta-volunteer-sign-up-sheets' ) . '</strong> ' . __( 'The template must include the {validation_link} tag to enable validation. You can also use {validation_code_expiration_hours} to show how much time they have to validate themselves before the code expires.', 'pta-volunteer-sign-up-sheets' ) . '</p>';
	}

    public function clear_validation_link_text_input() {
        echo '<input id="clear_validation_link_text" name="pta_volunteer_sus_validation_options[clear_validation_link_text]" size="60" type="text" value="'.esc_attr($this->validation_options["clear_validation_link_text"]).'" />';
        echo '<em> '. __('Text for the link to clear the browser user validation cookie.', 'pta-volunteer-sign-up-sheets') . '</em>';

    }

    public function signup_expiration_hours_number_input() {
        echo "<input id='signup_expiration_hours' name='pta_volunteer_sus_validation_options[signup_expiration_hours]' type='number' value='{$this->validation_options['signup_expiration_hours']}' min='1' style='width: 5em;'/>";
        echo '<em> '. __('Number of hours after a user signs up before the signup is deleted if it is not validated. Min/default is 1 hour.', 'pta-volunteer-sign-up-sheets') . '</em>';
    }

	public function validation_code_expiration_hours_number_input() {
		echo "<input id='validation_code_expiration_hours' name='pta_volunteer_sus_validation_options[validation_code_expiration_hours]' type='number' value='{$this->validation_options['validation_code_expiration_hours']}' min='1' style='width: 5em;'/>";
		echo '<em> '. __('Number of hours that a user validation code, and the associated browser cookie, is valid. The browser cookie will be set to expire after this number of hours, and the validation code will be deleted from the database. The user will need to be validated again after this time if they have not visited the site within that amount of time.', 'pta-volunteer-sign-up-sheets') . '</em>';
	}

    public function validation_form_resubmission_minutes_number_input() {
	    echo  "<input id='validation_form_resubmission_minutes' name='pta_volunteer_sus_validation_options[validation_form_resubmission_minutes]' type='number' value='{$this->validation_options['validation_form_resubmission_minutes']}' min='1' style='width: 5em;'/>";
        echo '<em> '. __('Number of minutes after a user submits the validation form before they can resubmit the form. Min/default is 1 minute.', 'pta-volunteer-sign-up-sheets') . '</em>';
    }

	public function validation_required_message_text_input() {
		echo  "<input id='validation_required_message' name='pta_volunteer_sus_validation_options[validation_required_message]' size='60' type='text' value='{$this->validation_options['validation_required_message']}' />";
		echo '<em> '. __('Message to show when validation is required to view sheets and signup info.', 'pta-volunteer-sign-up-sheets') . '</em>';
	}

	public function validation_page_link_text_text_input() {
		echo  "<input id='validation_page_link_text' name='pta_volunteer_sus_validation_options[validation_page_link_text]' size='60' type='text' value='{$this->validation_options['validation_page_link_text']}' />";
		echo '<em> '. __('Text for the link to the page with the validation form.', 'pta-volunteer-sign-up-sheets') . '</em>';
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

	public function enable_mobile_css_checkbox() {
		if ( isset( $this->main_options['enable_mobile_css'] ) && true === $this->main_options['enable_mobile_css'] ) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_main_options[enable_mobile_css]" type="checkbox"
               value="1" <?php echo $checked; ?> />
		<?php
		echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( 'When checked, the plugin will queue a very small mobile CSS stylesheet to collapse plugin tables to a single column when screen width is less than 600px. This is independent of the above setting, so you can still disable the main css (above) and use custom CSS of your own, or from the Customizer extension, and still enqueue the mobile CSS.', 'pta-volunteer-sign-up-sheets' ) . '</em>';
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

	public function hide_single_date_header_checkbox() {
		if(isset($this->main_options['hide_single_date_header']) && true === $this->main_options['hide_single_date_header']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_main_options[hide_single_date_header]" type="checkbox" value="1" <?php echo $checked; ?> />
        <em><?php _e('Check this to hide the date header above the table for each task on the view sheet page when the sheet type is Single.', 'pta-volunteer-sign-up-sheets'); ?></em>
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

    public function show_task_description_on_signup_form_checkbox() {
	    if(isset($this->main_options['show_task_description_on_signup_form']) && true === $this->main_options['show_task_description_on_signup_form']) {
		    $checked = 'checked="checked"';
	    } else {
		    $checked = '';
	    }
	    ?>
        <input name="pta_volunteer_sus_main_options[show_task_description_on_signup_form]" type="checkbox" value="1" <?php echo $checked; ?> />
	    <?php
	    echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('Checking this option will show the task description on the signup form.', 'pta-volunteer-sign-up-sheets').'</em>';
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

	public function clear_expired_sheets_checkbox() {
		if(isset($this->main_options['clear_expired_sheets']) && true === $this->main_options['clear_expired_sheets']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_main_options[clear_expired_sheets]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('Automatically clears expired sheets, including all associated tasks and signups, from the database a specified number of days (below) after the last date for the sheet (runs with hourly CRON function). This is independent of the clear signups option above and will ALWAYS delete all associated tasks and signups from the database.', 'pta-volunteer-sign-up-sheets').'</em>';
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

    public function enable_ajax_navigation_checkbox() {
        if(isset($this->main_options['enable_ajax_navigation']) && true === $this->main_options['enable_ajax_navigation']) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_main_options[enable_ajax_navigation]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('Enables SPA-style AJAX navigation for sign-up sheets. Pages load without full browser refresh.', 'pta-volunteer-sign-up-sheets').'</em>';
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

	public function use_html_checkbox() {
		if(isset($this->email_options['use_html']) && true === $this->email_options['use_html']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_email_options[use_html]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __('YES.', 'pta-volunteer-sign-up-sheets') . ' <em> '. __('If checked, emails will be sent in HTML format and you can use HTML tags in the email templates.', 'pta-volunteer-sign-up-sheets').'</em>';
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

    public function enable_validation_checkbox() {
        if ( isset( $this->validation_options['enable_validation'] ) && true === $this->validation_options['enable_validation'] ) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_validation_options[enable_validation]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( 'Check this to enable the validation system for non-logged-in users.', 'pta-volunteer-sign-up-sheets' ) . '</em>';
    }

    public function enable_signup_validation_checkbox() {
        if ( isset( $this->validation_options['enable_signup_validation'] ) && true === $this->validation_options['enable_signup_validation'] ) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_validation_options[enable_signup_validation]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( 'Check this to send a validation email to a non-validated user when they sign up for anything (if validation is enabled and you are NOT requiring validation to view and NOT requiring validation to signup). An email will be sent to the signup email address with a link they must click on to validate the signup. The signup will be marked as non-validated until they click on the link. Non-validated signups will be deleted after the expiration hours you set below.', 'pta-volunteer-sign-up-sheets' ) . '</em>';
    }

	public function disable_cc_validation_signup_emails_checkbox() {
		if ( isset( $this->validation_options['disable_cc_validation_signup_emails'] ) && true === $this->validation_options['disable_cc_validation_signup_emails'] ) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_validation_options[disable_cc_validation_signup_emails]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( 'When this is checked, the signup validation emails will NOT get copied to the global CC email address (in email settings). Uncheck if you want the CC email to also receive the emails with signup validation links (when signup validation is enabled).', 'pta-volunteer-sign-up-sheets' ) . '</em>';
	}

    public function enable_user_validation_form_checkbox() {
        if ( isset( $this->validation_options['enable_user_validation_form'] ) && true === $this->validation_options['enable_user_validation_form'] ) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        ?>
        <input name="pta_volunteer_sus_validation_options[enable_user_validation_form]" type="checkbox" value="1" <?php echo $checked; ?> />
        <?php
        echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( 'Check this to enable the user validation form on the front end in place of the User Signups List if the user is not validated or logged in. This will allow users to validate themselves in order to view and clear their signups. The validation form shortcode or block will always show the validation form regardless of this setting. This just automatically shows it where the user signups list would be when they are not validated or logged in.', 'pta-volunteer-sign-up-sheets' ) . '</em>';
    }

    public function require_validation_to_view_checkbox() {
	    if ( isset( $this->validation_options['require_validation_to_view'] ) && true === $this->validation_options['require_validation_to_view'] ) {
		    $checked = 'checked="checked"';
	    } else {
		    $checked = '';
	    }
	    ?>
        <input name="pta_volunteer_sus_validation_options[require_validation_to_view]" type="checkbox" value="1" <?php echo $checked; ?> />
	    <?php
	    echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( 'Check this to require the user to be validated before they can view signup sheets or signup info. You will need to enable the validation form so that they can be validated if you are not using WordPress user accounts.', 'pta-volunteer-sign-up-sheets' ) . '</em>';
    }

	public function require_validation_to_signup_checkbox() {
		if ( isset( $this->validation_options['require_validation_to_signup'] ) && true === $this->validation_options['require_validation_to_signup'] ) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_validation_options[require_validation_to_signup]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( 'Check this to require the user to be validated before they can signup, if you are not already requiring validation to view. You will need to enable the validation form so that they can be validated if you are not using WordPress user accounts. If this is checked, then the Enable Signup Validation option below no longer matters, as they will first need to validate using the validation form before they can signup.', 'pta-volunteer-sign-up-sheets' ) . '</em>';
	}

	public function enable_clear_validation_checkbox() {
		if ( isset( $this->validation_options['enable_clear_validation'] ) && true === $this->validation_options['enable_clear_validation'] ) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>
        <input name="pta_volunteer_sus_validation_options[enable_clear_validation]" type="checkbox" value="1" <?php echo $checked; ?> />
		<?php
		echo __( 'YES.', 'pta-volunteer-sign-up-sheets' ) . ' <em> ' . __( 'Keep this checked to allow users to clear the validation cookie from their browser via a link on the page where you put the validation form shortcode. It will also appear below the lists of their signups. Useful to clear cookies on public computers, or if they have signed up spouses or family members using different info and need to revalidate with different info.', 'pta-volunteer-sign-up-sheets' ) . '</em>';
	}

    // Email template textarea/editor fields have been removed - templates are now managed via the Email Templates admin page

    // Validation email template textarea fields have been removed - templates are now selected via dropdowns above

	public function validation_form_header_textarea() {
        wp_editor(wpautop($this->validation_options['validation_form_header']),'validation_form_header',array('wpautop' => true, 'media_buttons' => false, 'textarea_rows' => 5,'textarea_name' => 'pta_volunteer_sus_validation_options[validation_form_header]'));
		echo '<br />' . __('Info to display above the user validation form. HTML is allowed.', 'pta-volunteer-sign-up-sheets');
	}

	public function clear_validation_message_textarea() {
		wp_editor(wpautop($this->validation_options['clear_validation_message']),'clear_validation_message',array('wpautop' => true, 'media_buttons' => false, 'textarea_rows' => 5,'textarea_name' => 'pta_volunteer_sus_validation_options[clear_validation_message]'));
		echo '<br />' . __('Info to display above the user clear validation link (when enabled). HTML is allowed. You can leave it blank for no message.', 'pta-volunteer-sign-up-sheets');
	}

    public function maybe_process_license_form() {
        $deactivated = $saved = false;
	    if (($activated = isset($_POST['pta_vol_sus_activate_mode']) && 'activated' == $_POST['pta_vol_sus_activate_mode'])
	        || ($deactivated = isset($_POST['pta_vol_sus_deactivate_mode']) && 'deactivated' == $_POST['pta_vol_sus_deactivate_mode'])
            || ($saved = isset($_POST['pta_sus_license_save_mode']) && 'submitted' === $_POST['pta_sus_license_save_mode'])) {
		    if ( ! wp_verify_nonce( $_POST['pta_vol_sus_license_nonce'], 'pta_vol_sus_license' ) ) {
			    PTA_SUS_Messages::add_error( __( 'Invalid Referrer!', 'pta-volunteer-sign-up-sheets' ) );
		    } elseif ( $activated || $saved ) {
			    // retrieve the license from the posted data
			    $license = trim( $_POST['pta_vol_sus_license_key'] );
			    $old     = get_option( 'pta_vol_sus_license_key' );
			    if ( $old && $old != $license ) {
				    delete_option( 'pta_vol_sus_license_status' ); // new license has been entered, so must reactivate
			    }
			    // data to send in our API request
			    $api_params = array(
				    'edd_action' => 'activate_license',
				    'license'    => $license,
				    'item_id'    => SS_PLUGINS_PTA_VOLUNTEER_SUS_ID,    // ID of this plugin
				    'url'        => home_url()
			    );
			    // Call the custom API.
			    $response = wp_remote_post( SS_PLUGINS_URL, array(
				    'timeout'   => 15,
				    'sslverify' => false,
				    'body'      => $api_params
			    ) );
			    // make sure the response came back okay
			    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

				    if ( is_wp_error( $response ) ) {
					    $message = $response->get_error_message();
				    } else {
					    $message = __( 'License Site Communication Error! Please try again in a few minutes.', 'pta-volunteer-sign-up-sheets' );
				    }
				    PTA_SUS_Messages::add_message($message);

			    } else {

				    $license_data = json_decode( wp_remote_retrieve_body( $response ) );

				    if ( false === $license_data->success ) {

					    switch ( $license_data->error ) {

						    case 'expired' :

							    $message = sprintf(
								    __( 'Your license key expired on %s.', 'pta-volunteer-sign-up-sheets' ),
								    date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
							    );
							    break;

						    case 'revoked' :
						    case 'disabled':

							    $message = __( 'Your license key has been disabled.', 'pta-volunteer-sign-up-sheets' );
							    break;

						    case 'missing' :

							    $message = __( 'Invalid license.', 'pta-volunteer-sign-up-sheets' );
							    break;

						    case 'invalid' :
						    case 'site_inactive' :

							    $message = __( 'Your license is not active for this URL.', 'pta-volunteer-sign-up-sheets' );
							    break;

						    case 'item_name_mismatch' :

							    $message = __( 'This appears to be an invalid license key', 'pta-volunteer-sign-up-sheets' );
							    break;

						    case 'no_activations_left':

							    $message = __( 'Your license key has reached its activation limit.', 'pta-volunteer-sign-up-sheets' );
							    break;

						    default :

							    $message = __( 'License Site Communication Error! Please try again in a few minutes.', 'pta-volunteer-sign-up-sheets' );
							    break;
					    }

					    PTA_SUS_Messages::add_error($message);

				    } else {
					    update_option( 'pta_vol_sus_license_status', $license_data->license );
					    // update the license key
					    update_option( 'pta_vol_sus_license_key', $license );
					    if ( 'valid' == $license_data->license ) {
						    PTA_SUS_Messages::add_message( __( 'License Updated!', 'pta-volunteer-sign-up-sheets' ) );
					    } else {
						    PTA_SUS_Messages::add_error(__( 'Not A Valid License!', 'pta-volunteer-sign-up-sheets' ) );
					    }
				    }

			    }

		    } elseif ( $deactivated ) {
			    // retrieve the license from the posted data
			    $license = trim( $_POST['pta_vol_sus_license_key'] );
			    // data to send in our API request
			    $api_params = array(
				    'edd_action' => 'deactivate_license',
				    'license'    => $license,
				    'item_id'    => SS_PLUGINS_PTA_VOLUNTEER_SUS_ID,    // ID of this plugin
				    'url'        => home_url()
			    );
			    // Call the custom API.
			    $response = wp_remote_post( SS_PLUGINS_URL, array(
				    'timeout'   => 15,
				    'sslverify' => false,
				    'body'      => $api_params
			    ) );
			    // make sure the response came back okay
			    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

				    if ( is_wp_error( $response ) ) {
					    $message = $response->get_error_message();
				    } else {
					    $message = __( 'License Site Communication Error! Please try again in a few minutes.', 'pta-volunteer-sign-up-sheets' );
				    }

				    PTA_SUS_Messages::add_error($message);

			    } else {
				    // decode the license data
				    $license_data = json_decode( wp_remote_retrieve_body( $response ) );
				    // $license_data->license will be either "deactivated" or "failed"
				    if ( $license_data->license == 'deactivated' ) {
					    delete_option( 'pta_vol_sus_license_status' );
					    delete_option( 'pta_vol_sus_license_key' );
					    PTA_SUS_Messages::add_message(  __( 'License Deactivated!', 'pta-volunteer-sign-up-sheets' ) );
				    } elseif ( $license_data->license == 'failed' ) {
					    PTA_SUS_Messages::add_error(__( 'Deactivation Failed!  Please try again in a few minutes.', 'pta-volunteer-sign-up-sheets' ) );
				    }
			    }
		    }
	    }
    }

} // End Class
/* EOF */