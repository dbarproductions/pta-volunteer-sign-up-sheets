=== Volunteer Sign Up Sheets ===
Contributors: DBAR Productions
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=R4HF689YQ9DEE
Tags: Volunteer, Volunteers, Sign Up, Signup, Events
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 6.9.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 6.0.1

Easily create and manage sign-up sheets for activities and events, while protecting the privacy of the volunteers' personal information.

== Description ==

An alternative to sites like SignUpGenius for your events, this plugin lets you keep your signup sheets on your own site. Easily create and manage sign up sheets for your school, organization, business, or anything else where people need to sign up.

**Documentation:** https://stephensherrardplugins.com/docs/pta-volunteer-sign-up-sheets-documentation/

**Key Features:**

* Four event types: Single, Recurring, Multi-Day, and Ongoing
* Author System for delegated sheet management (v6.0)
* Email Template Management with sheet/task level assignment (v6.0)
* Modern modal-based task editor (v6.0)
* Validation system for non-WordPress user signups (v5.0)
* HTML or plain text email templates
* Two reminder emails per sheet
* Gutenberg blocks and shortcodes
* DataTables admin interface with export to Excel, CSV, PDF
* GDPR privacy integration
* Extensive hooks and filters for customization

**Available Extensions:**

* [Calendar Display](https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-calendar-display/)
* [Custom Fields](https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-custom-fields/)
* [Customizer](https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-customizer/)
* [Groups](https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheet-groups/)
* [Waitlists](https://stephensherrardplugins.com/plugins/volunteer-sign-up-sheets-waitlists/)
* [Mailchimp](https://stephensherrardplugins.com/plugins/volunteer-sign-up-sheets-mailchimp/)
* [Automated Emails](https://stephensherrardplugins.com/plugins/volunteer-sign-up-sheets-automated-and-conditional-emails/)

== Installation ==

1. Download the plugin from https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets/
2. Upload via WordPress Admin > Plugins > Add New > Upload Plugin
3. Activate the plugin
4. Enter your license key for automatic updates

== Frequently Asked Questions ==

= Is there documentation? =

Yes! Full documentation is available at:
https://stephensherrardplugins.com/docs/pta-volunteer-sign-up-sheets-documentation/

= How do I display the signup sheets? =

Use the "Sign Up Sheets" block in the block editor, or use the shortcode [pta_sign_up_sheet] on any page.

= Where do I submit feature requests? =

Please submit feature requests here (not in the support forum):
https://stephensherrardplugins.com/support/forum/feature-requests/pta-volunteer-sign-up-sheet-feature-requests/

== Changelog ==

= 6.0.1 =
* Fixed sheet first/last dates not updating when editing dates for Single sheet types
* Fixed admin signup edit validation incorrectly blocking edits when all task spots are filled
* Tested with PHP 8.5.0 and WordPress 6.9.1

= 6.0.0 =
* NEW: Author System - "Signup Sheet Author" role for delegated sheet management
* NEW: Email Template Management - Create and assign custom templates at sheet/task level
* NEW: Modern Task Editor - Modal-based interface for easier task management
* NEW: Task Copying - Reuse tasks across sheets
* NEW: Author Filtering - author_id and author_email shortcode/block parameters
* NEW: Auto-fill Contact Info - Pre-populate chair fields from current user
* IMPROVED: Performance optimization with refactored core architecture
* IMPROVED: Enhanced permission checks throughout admin interface
* IMPROVED: Bulk operations respect author permissions
* IMPROVED: Email template consolidation (auto-migrates from Customizer extension)
* Database upgrade functions add new features without plugin deactivation

= 5.8.2 =
* Fixed SQL error in get_signup_ids() function

= 5.8.1 =
* Fix for date format on View/Export All Data page for non-English languages
* Fixed Text Registry action hook for Customizer compatibility

= 5.8.0 =
* Reworked action processing on admin sheets list to prevent duplicate actions on page reload

= 5.7.0 =
* Added login link to consolidated signup row message
* Refactored admin messages for consistency
* Allow HTML in messages with wp_kses_post sanitization
* Moved signup database functions to Signups Functions class

= 5.6.8 =
* Updated get_detailed_signups to allow retrieval of all signups without filters

= 5.6.7 =
* Compatibility fix for Member Directory Contact Emails template tag
* Added missing autocomplete listener script

= 5.6.6 =
* Added {task_date} template tag
* Fixed duplicate row IDs on add/edit tasks page

= 5.6.5 =
* Copied sheets now default to hidden

= 5.6.4 =
* Fixed security check when clearing signups from admin
* Allow zero in required item details field

= 5.6.1 =
* Replaced GitHub updater with software licensing system

= 5.5.5 =
* Sanitize/escape values in Upcoming Events widget
* Fixed translation text domain loading
* CSS fix for Divs display option

= 5.5.0 =
* New email template tags
* Improved template tag helper UI

= 5.4.0 =
* Template Tags Helper feature for email templates
* Fix for {contact_emails} template tag

= 5.3.0 =
* Email template tag functions in separate class
* Switched to vanilla JS autocomplete

= 5.2.0 =
* Clear time option: days or hours
* Clear calculation uses task start time if available

= 5.1.0 =
* Require Validation to Signup option
* Option to disable validation email CC

= 5.0.0 =
* NEW: Validation system for non-WordPress user signups
* NEW: Updated Gutenberg blocks with live preview
* NEW: User Signups List, Upcoming Events, and Validation Form blocks
* NEW: [pta_validation_form] shortcode
* NEW: CRON log file viewing
* Requires PHP 7.4+

= 4.6.0 =
* HTML format emails option
* Automatic expired sheet deletion
* Mobile responsive CSS option
* Per-sheet email recipient settings
* Signup timestamp tracking

= 4.5.0 =
* Database check for filled spots with appropriate error message

= 4.0.0 =
* Move signups between sheets/tasks/dates
* Disable confirmation/reminder emails option
* DataTables StateSave module
* Changed translation text domain

For older changelog entries, see the plugin's GitHub repository.
