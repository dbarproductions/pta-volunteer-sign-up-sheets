=== Volunteer Sign Up Sheets ===
Contributors: DBAR Productions
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=R4HF689YQ9DEE
Tags: Volunteer, Sign Up, Signup, Signups, Events
Requires at least: 3.3
Requires PHP: 5.6
Tested up to: 5.7.0
Stable tag: trunk

Easily create and manage sign-up sheets for activities and events, while protecting the privacy of the volunteers' personal information.

== Description ==

**PLEASE DO NOT USE THE SUPPORT FORUM FOR FEATURE REQUESTS!!**
You may submit new features here:
<https://stephensherrardplugins.com/support/forum/feature-requests/pta-volunteer-sign-up-sheet-feature-requests/>

**PLEASE READ THE DOCUMENTATION BEFORE POSTING SUPPORT REQUESTS**
Read the documentation here:
<https://stephensherrardplugins.com/docs/pta-volunteer-sign-up-sheets-documentation/>

**An alternative to sites like Signup Genius for your events, this plugin lets you keep your signup sheets on your own site. Easily create and manage sign up sheets for your school, organization, business, or anything else that where need people to sign up.**

**Features:**

*   Version 3.6 adds the ability to Reschedule a sheet to new date and times, copy a sheet with new dates and times, or create multiple copies of a sheet at specified day intervals. These new functions allow optionally copying the signups, and have a new email template to notify those signups of the new dates and times.
*   [pta_user_signups] shortcode allows you to show a list of the current logged in user's signups on any page (with clear links, if allowed).
*   Integrates with the GDPR privacy functions of WordPress 4.9.6. Exported personal data from WordPress will include any signups for the specified email or user ID associated with that email. If the user requests their data be deleted, that same user signup data will be deleted along with all other WordPress data for that user.
*   Option to output lists as divs instead of tables (for easier custom styling and mobile responsive design).
*   Extensive hooks and filters that make it easy to extend or modify functionality
*   Supports the calendar display extension: <https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-calendar-display/>
*   Create custom fields that can be used for sheets, tasks, or signup forms, with the Custom Fields extension: <https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-custom-fields/>
*   Customize public side text and layout options with the Customizer extension: <https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-customizer/>
*   Group, or categorize, sheets/events with the Groups extension, which can also import groups from the WP Groups and BuddyPress plugins: <https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheet-groups/>
*   Specify any type of sheet as a "No Signup Event". This allows you to create non-volunteer events for display only (no signup links or available spots will be shown). You can still create tasks/items with dates, start and end times for these sheets, which could be useful for showing the schedule/agenda for an event, but you won't be able to specify quantity or other normal task options. This is useful for a combination volunteer sign-up and event calendar type of list/display, especially when used with the Calendar Display extension.
*   You can now move tasks from one sheet (they will be deleted from that sheet) to another sheet that you specify (they will be merged/added-to the selected sheet). Useful for merging two or more sheets into one for easier management.
*   Admin can live search user or sign-up tables for volunteers on the public sign-up form, to sign up volunteers from the public side.
*   Admin can view, edit, add, and clear signups from the View Signups page in the Admin dashboard.
*   Signups are shown on the Admin side using jQuery DataTables for easy sorting/filtering/searching, showing/hiding columns, and organizing your signup data for quick and easy export to Excel, CSV, PDF, or Print.
*   You can optionally enter a description for each task (in addition to the main content area for the whole sheet) that will be shown above the task signup table for each task (when not empty)
*	The ability to allow duplicate signups on a per task basis, changing the label for the item details form field on a per task/item basis, as well as allowing volunteers to specify quanitites on a per task/item basis.
*   Easily create volunteer sign-up sheets with unlimited number of tasks/items for each
*	Supports Single, Recurring, Ongoing or Multi-Day Events
*  	All Sheets can be hidden from the public (visible only to logged in users)
*   No volunteer contact info is shown to the public (emails and phone are always hidden). Default public view shows only first name and last name for filled spots, but you can optionall show the full name, and there is also an option to simply show "Filled" for filled spots.
*   Hidden spambot field helps prevent automatic spambot form submissions
*	Up to 2 automatic reminder emails can be set up at individually specified intervals for each sheet (e.g., 7 days and 1 day before event)
*   Shortcodes for all sheets, or use argument to show a specific sheet on a page
*   Widget to show upcoming events that need volunteers in page sidebars
*   Individual sheets can be set to hidden until you are ready to have people sign up (useful for testing individual sheets)
*   Test Mode for entire volunteer system, which displays a message of your choosing to the public while you test the system
*   "manage_signup_sheets" capability so you can set up other users who can create and manage sign-up sheets without giving them full admin level access.
*	Integration with the PTA Member Directory & Contact Form plugin to quickly specify contacts for each sign-up sheet, linked to the contact form with the proper recipient already selected. http://wordpress.org/plugins/pta-member-directory/
*	Gutenberg Block Editor "Sign Up Sheets" block for creating shortcodes with all possible arguments, or use the free Shortcodes extension ( available at <https://wordpress.org/plugins/pta-shortcodes/> ) to easily generate shortcodes with all possible arguments in the Classic Editor
*	Wordpress Multisite compatibility

**More Details:**

With this plugin you can define four different types of events:  Single, Recurring, Multi-Day, or Ongoing events. Single events are for events that take place on just a single date. Recurring events are events that happen more than once (such as a weekly function), but have the same needs for each date. Multi-Day events are events that are spread across more than one day, but have different needs for each day. Ongoing events do not have any dates associated with them, but are for committees or helpers that are needed on an ongoing basis.

For each of these types of events, you can create as many tasks or items as needed. For each of these tasks/items, you can specify how many items or people are needed, a description, a start and end time, the date (for multi-day events), and whether or not item details are needed (for example, if you want the volunteer to enter the type of dish they are bringing for a luncheon), and optionally enable quantities. The order of tasks/items can easily be sorted by drag and drop.

Sheets can also be specified as a "No Sign Up" event, which can be useful for general organization events and meetings, especially when used in conjunction with the Calendar Display extension: <https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-calendar-display>

Each sign-up sheet can be set to visible or hidden, so that you can create sign-up sheets ahead of time, but only make them visible to the public when you are ready for them to start signing up. There is also a test mode which will only show sign-up sheets on the public side to admin users or those who you give the "manage_signup_sheets" capability. Everyone else will see a message of your choosing while you are in test mode. When not in test mode, admins and users with the "manage_signup_sheets" capability can still view hidden sheets on the public side (for testing those sheets without putting the whole system into test mode).

In the settings, you can choose to require that users be logged in to view and/or sign-up for any volunteer sign-up sheets, and pick the message they will see if they are not logged in. Even if you keep the sheets open to the public, you can choose which personal info to show, or simply show "Filled" for filled slots.

There is also a hidden spambot field to prevent signup form submission from spambots.

If a user is logged in when they sign up, the system will keep track of the user ID, and on the main volunteer sign-ups page, they will also see a list of items/tasks that they have signed up for, and it will give them a link to clear each sign up if they need to cancel or reschedule. If they are not logged in when they sign up, but they use the same email as a registered user, that sign-up will be linked to that user's account. You can also use the shortcode [pta_user_signups] to show the list of the current user's signups on any page (along with clear links, if allowed).

Admin users can add/edit signups from the View Signups page in the admin dashboard, or they can use the "live search" option on the front end sign up form to search for volunteers in either the plugin's signups table, the WordPress users table, or both. If the admin then selects a volunteer, they can sign up that volunteer, and the signup will be assigned to that user's account (if your volunteers have user accounts).

There is a shortcode for a main sign-up sheet page - [pta_sign_up_sheet] - that will show a list of all active (and non-hidden) sign-up sheets, showing the number of open volunteer slots with links to view each individual sheet. Individual sheets have links next to each open task/item for signing up.  When signing up, if the user is already logged in, their name and contact info (phone and email) will be pre-filled in the sign-up page form if that info exists in the user's meta data. You can also enter shortcode arguments to display a specific sign-up sheet on any page. Additionally, there are shortcode arguments for many other features (see documentation).

There is a sidebar widget to show upcoming volunteer events and how many spots still need to be filled for each, linked to each individual sign-up sheet. You can choose whether or not to show Ongoing type events in the widget, and if they should be at the top or bottom of the list (since they don't have dates associated with them).

Admin users can view sign-ups for each sheet, and add, edit, or clear any spots with a simple link. Each sheet can also be exported to Excel, CSV, PDF or Print formats. Admin side signups are displayed using the jQuery DataTables plugin, which allows sorting, filtering, searching, and showing/hiding columns, so you can arrange the data anyway you want before exporting or printing.

Committee/Event contact info can be entered for each sheet, or, if you are using the PTA Member Directory plugin, you can select one of the positions from the directory as the contact. When a user signs up, a confirmation email is sent to the user as well as a notification email to the contacts for that event (can be optionally disabled).

Automatic Email reminders can be set for each sign-up sheet. You can specify the number of days before the event to send the reminder emails, and there can be two sets of reminders for each sheet (for example, first reminder can be sent 7 days before the event, and the second reminder can be sent the day before the event). You can set an hourly limit for reminder emails in case your hosting account limits the number of outgoing emails per hour.

Simple to use custom email templates for sign-up confirmations and reminders.

Admin can use an Email Volunteers form page to quickly send an email to all volunteers for a specific sheet, or to all volunteers.

Sheets and tasks/signups can be shown via tables, or via table-style divs. CSS can be optionally disabled so that you can more easily style the displays the way you wish. Version 3 of the Customizer Extension has extensive styling options, plus allows you to create custom layout templates that can be assigned on a per sheet basis. <https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-customizer/>

Custom Fields can be added to sheets, tasks, or sign-up forms (for collecting additional info from volunteers at signup, or displaying addition info for sheets/tasks, such as Location) via the Custom Fields extension: <https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-custom-fields/>

Text displayed on the public side, such as columns headers, can be modified, along with additional layout options, and custom layout templates, with the Customizer extension: <https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-customizer/>

Much more! Read the documentation for all the current features.

**Available Extensions:**

*   [Volunteer Sign Up Sheets Customizer](https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-customizer/) - Edit all public facing text displays. Customize the styling of sheet/task list tables. Create custom Layout Templates that can be assigned on a per sheet basis, to display only the columns you want in the order that you want them, and much more!
*   [Volunteer Sign Up Sheets Custom Fields](https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-custom-fields/) - Create custom fields that can be used to display additional info for sheets and/or tasks, and can also be used to collect (and optionally display) additional information from users when they sign up for a task/item.
*   [Volunteer Sign Up Sheets Calendar Display](https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-calendar-display/) - Display your events and signups in one or more custom calendars, with a variety of display options.
*   [Volunteer Sign Up Sheets Groups](https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheet-groups/) - Organize your sign up sheets by Groups, or Categories. Can also import groups from BuddyPress and the WordPress Groups extension.

== Installation ==

Extract the zip file and just drop the contents in the wp-content/plugins/ directory of your WordPress installation and then activate the Plugin from Plugins page.

== Frequently Asked Questions ==

**Can this plugin do (insert a feature request here)?**
**Can you add (insert a feature request here)?**

PLEASE DO NOT USE THE SUPPORT FORUM FOR FEATURE REQUESTS!!

This plugin has a lot of options and features, but I have been getting overwhelmed with feature requests recently. This plugin already does MUCH more than I originally intended, and more than we needed for our own school PTA web site. I have created some extensions that I thought would be helpful to the largest number of people, which you can find at:
<https://stephensherrardplugins.com>

PLEASE USE THE FEATURE REQUEST FORUM TO REQUEST NEW FEATURES!!
<https://stephensherrardplugins.com/support/forum/feature-requests/>

**Is there any documentation or help on how to use this?**

Documentation can be found at:
<https://stephensherrardplugins.com/docs/pta-volunteer-sign-up-sheets-documentation/>

**How do I display the signup sheets?**

Use the shortcode [pta_sign_up_sheet] to display the sign-up sheets on a page. There are also shortcode arguments for id (specific sheet id), date (specific date), show_time (yes or no), and list_title (change the title of the main list of signup sheets). To make generating shortcodes with arguments easier, download the free PTA Shortcodes plugin extension from:
<https://wordpress.org/plugins-wp/pta-shortcodes/>

**Can I display the sheets in a calendar view?**

Yes, now you can! Calendar display extension can be found here:
<https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-calendar-display/>

**Can I change or add my own fields to the Signup Form?**

Yes, check out the Custom Fields extension here:
<https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-custom-fields/>

**Can I modify text and the way the tables are arranged and displayed?**

Yes, version 3 of the Customizer Extension adds a ton of new layout options! Check out the Customizer extension here:
<https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-customizer/>

**Can I create additional custom email templates so that different sheets can have different emails?**

Yes, version 3.2 of the Customizer Extension adds the ability to define custom email templates for any type of email (confirmation, reminder, reschedule, clear) and assign them on a per sheet basis:
<https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-customizer/>

**Can signup sheets be assigned to and displayed by groups or categories?**

YES. The groups extension plugin now supports BuddyPress Groups as well as the Groups plugin found at WordPress.org. You can import groups from either of those plugins and can also restrict access and visibility to only members of the corresponding groups assigned to a sheet.  You can now also assign multiple groups to a sheet, and display multiple groups on a page (either in one list, or separate lists by group). This is a paid extension that can be found at: <https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheet-groups/>

**Is this plugin GDPR compliant?**

Maybe? That all depends on you and what else you have done with your site!

Version 2.4 of the plugin integrates with the GDPR features of WordPress 4.9.6. Meaning, if someone requests an export of their personal data, any signup data associated with that person's provided email (along with any user ID associated with that email) will be included in the personal data export that you generate from WordPress admin. Similarly, if a person requests that their info be deleted, then any signups associated with the provided email (or a user ID associated with that email) will also be deleted and those signup slots will become available again.

This alone will not make your site GDPR compliant. You will need to study up on GDPR and take all the necessary actions for your site to make sure you are in compliance. If you have people using the signup sheets in countries covered by the GDPR, then somewhere on your site, you should be letting them know what information you are collecting when they signup for something, based on what fields you have activated for signups in this plugin. You may also want to set this plugin so that only registered users can signup for events, and then you can present your privacy policy and some sort of agreement check box for them when they are registering for your site.

If your site is going to be affected by GDPR, then you should contact a lawyer to make sure you do everything needed to be in compliance.

== Changelog ==
**Version 3.6.1**

*   Minor updates to filter hooks in email class to pass reschedule email variable to extensions. Needed for the Customizer extension to be able to send custom reschedule emails for different sheets.

**Version 3.6.0**

*   New Feature: Reschedule/Copy, and Multi-Copy of Sheets. Reschedule allows you to change dates and times of sheet and tasks. Copy (different than the simple copy) allows you to copy the sheet, tasks, and signups (optional) to new dates and times, without deleting the original sheet. Multi-Copy allows you to specify an interval in days (such as 7 days for a weekly event), and the number of copies you wish to make, and then it will make that many copies of the sheet with new dates at the specified interval, also copying tasks and optionally copying the signups as well.
*   New Reschedule Email Subject and Message template settings that will be used for the new Reschedule/Copy functions when you check the checkbox to send emails when using those functions. Since this could lead to a large number of emails for sheets with lots of tasks and signups, the signup IDs are saved in the database and the emails are sent out hourly during the same CRON job that sends out reminders. If you have a limit set for the max # of reminders to send out each hour, that limit is also used for Reschedule emails as well.
*   Tested with WordPress 5.7.0

**Version 3.5.3**

*   Added validation check to make sure task date is not empty for Multi-Day tasks on Admin side Add/Edit tasks page.
*   Updated dataTables scripts to latest versions

**Version 3.5.2**

*   Fixed typo in the function to send emails to volunteers (from Admin) that prevented the email from being copies to the sender (the FROM address) as well.
*   Added filter hook for the admin View All Data page to allow other plugins to filter/modify the sheets shown

**Version 3.5.1**

*   Minor code change when adding signup to make sure there is a valid WP user for a passed in user ID before trying to access the user object. In case of custom extensions passing in a user ID that is not a valid WP User ID.
*   Minor changes to email class. No longer explicitly set headers for plain text mode (already default for wp_mail, and possibly avoid conflicts with SMTP mailer plugins). Wrap reply to emails inside triangle brackets for better compatibility with PHP Mailer
*   Minor code cleanup and optimizing of data class

**Version 3.5.0**

*   Added new "Skip Signups Check" option in the main settings. If you enable this, then the validation function that checks the quantity entered for tasks, on the admin Add/Edit Tasks page, against the number of current signups for that task, will be skipped. It will also skip the check for task signups when you delete a task. This will allow you to change the quantity for a task to less than the number of current signups for that task, and will also allow you to delete a task that already has signups. It will NOT delete any existing signups, HOWEVER if you delete the task, you will no longer be able to view signups for that task. This is useful if you have a Recurring sheet with old signups you want to keep, but where you want to reduce the quantity for a task for future occurrences. But, it will work with any type of sheet.

**Version 3.4.0**

*   Add setting to disable the user's signup list that shows below the main signup sheet list. Now that there is a separate shortcode to show the user's signup list, you can disable it below the sheet list and place that shortcode anywhere else you wish. Also handy if you are trying to use multiple shortcodes on one page so you don't see the user's signup list more than once.
*   Added action 'pta_sus_reminder_sent' after email reminder sent
*   Added filters for error checking and messages when signup form is submitted to allow other plugins to validate and add their own error messages
*   Minor code cleanup

**Version 3.3.0**

*   Added shortcode argument show_email to allow showing the signup email in list of signups. Default value is "no" (do NOT show email by default).
*   Added Show Email setting to block editor to automatically insert the show_email argument in shortcodes it generates
*   Ensure row data for all default fields are always populated so they can potentially be shown by custom templates in the Customizer extension, even if those fields are hidden by a shortcode attribute


**Version 3.2.9**

*   Fixed code that tries to get initials from the last name and created a PHP Notice if a volunteer enters only spaces for last name
*   No longer allow people to enter only spaces for first or last name fields on the signup form (will show a required fields error when they submit the form)

**Version 3.2.8**

*   Fixed slot number display for admin single sheet view signups page

**Version 3.2.7**

*   Fixed bug introduced in a recent release where the messaging recommended to login would be shown even when the user was logged in (is the message was not disabled in settings)
*   Fixed issue that caused the Calendar extension pop-up signup form to not have user values pre-filled in some cases (and also showed the login recommendation message)

**Version 3.2.6**

*   Modified the dropdown page select boxes in the settings to allow selection of private pages, instead of just published pages.

**Version 3.2.5**

*   Reworked the live search javascript and Ajax functions on both public and admin side to allow filtering/adding user data by extensions

**Version 3.2.4**

*   Disable auto-populate signup sheet fields for admins and signup sheet managers when live search is enabled, so signup fields don't start out filled with the admin's or signup sheet manager's info
*   Added filter hooks to allow other plugins to add to or modify user signup fields when auto-populating the form fields for logged in users

**Version 3.2.3**

*   Add code to dataTables on admin side to allow proper sorting of data by date (when showing date column)
*   Added an option to show all empty slots on their own row in the View/Export ALL Data admin page. Useful if you need to print sign up forms for multiple events at once, but will take that page much longer to load and much longer for dataTables to initialize the table.
*   Reworked admin side view signups and view all signups output of remaining slots to work better with extensions that create custom columns for those views

**Version 3.2.2**

*   Fixed error introduced in last update with Admin sheets list table not getting initialized properly after certain actions from that page (copy, trash)

**Version 3.2.1**

*   Changed default value of option to remove signups from database after event ends. Will only affect new installs where options have not been set yet. Now you must specifically check that option for signups to be automatically cleared after an event is over.
*   Added Screen Option panel to the admin All Sheets list table page, allowing you to set the number of sheets to show per page as well as toggle column visibility.
*   Fixed the "Hide Remaining" button on the All Signup Data dataTables page.

**Version 3.2.0**

*   Fixed an issue where time picker would not initialize on new task rows added on the admin edit tasks page
*   Changed order of Date and Task Title in table groups on admin View Signups page to match the order of the public side display (date first, then task title)
*   Added option to disable row grouping in the DataTables signups pages in admin. If disabled, all columns will show by default
*   Removed the slot # from the front of names on admin view signups & view all data pages to make it easier to sort & filter by names. Slot # is now its own column
*   Added a checkbox to the Admin side Add/Edit signup form to indicate if an email notice of the signup/edit should be sent to the user/volunter.
*   Added an option to hide details and quantities from the list of a user's signups (below sheet table, or with separate shortcode)

**Version 3.1.2**

*   Added updated Danish translation.
*   Readme file updates and layout changes
*   No code changes

**Version 3.1.1**

*   Added small amount of default styling to task date and task title (above the tables) to make them stand out a bit more
*   Added extra CSS classes to various row types (signup, remaining, consolidated) on the task/signup list tables displays to make it easier to style those rows differently
*   Re-arranged default order of the task info above the table to show the date first, similar to the old layout
*   Added hooks to allow extensions to create alternate layouts for the tasks/signups list (Custom table layouts can now be defined on a per sheet basis using the Customizer Extension)
*   Modified the admin side add/edit signup form to only shows signup fields needed for the task, and also not showing the phone field if that is disabled in the main setting. This also fixed an issue with the Item Details field being set as required even if you didn't check the Details Needed box for the task (details required field is hidden and defaults to checked).
*   Slightly modified and optimized the way rows and columns are output to be more efficient and easier for extensions to add/modify displayed columns

**Version 3.1.0**

*   Added an option to disable the WordPress date/time translation function, date_i18n, when formatting dates and times for output. WordPress modified the way they do things in 5.3, and if any other plugin or theme is using the date_default_timezone_set function (WordPress specifically states that function should NOT be used any more), it will throw off the display times. This option will correct for that by using the simple PHP date function to format date and time displays. However, this will NOT allow translation, and so dates/times will be shown in the language locale set on your server.
*   Remove the no longer used CSV Exporter class file (export functions are now handled by DataTables)

**Version 3.0.0**

*   THIS IS A MAJOR UPDATE - PLEASE TEST ON A STAGING SITE FIRST! ALSO REQUIRES UPDATING ANY EXTENSIONS YOU MAY OWN!
*   Plugin display name modified to remove the "PTA". Slugs/filemanes, shortcodes, html classes, and text domains, all remain unchanged.
*   Added an optional Task Description field for each task on a sheet, and associated template tag to add task description to emails
*   When viewing a sheet, each task is now split to its own table, or table-styled div layout, and only columns applicable to that task are shown. For example, if there are no Item Details or Quantities for a task, those columns will no longer be shown.
*   Task start and end times are now shown under the date in the "header" area of each task, and only if they are set for that task. They are no longer shown as columns in the signups list table. This gives more space per column on the screen for the shown columns, and allows more room for extensions that add additional columns to the signups list display.
*   Added a PTA Block (under widgets group) to create sign up sheet shortcodes using the Gutenberg editor
*   Added new show_date_start and show_date_end arguments to the shortcode to allow to remove one or both of those columns from the main sheet list
*   Added the jQuery DataTables plugin for viewing signups in the Admin dashboard, and included functionality for export to CSV, Excel, PDF, or Print. Columns can be hidden/shown, rearranged, and data can be filtered and sorted however you like before you export or print. This now replaces the old "Export To CSV" button and function on the admin View Signups page.
*   Added the ability for Admin to Add/Edit Signups on the admin View Signups page for a sheet.
*   Cleanup and simplify some CSS for div output mode
*   Moved the "Clear" column for individual sheets view to be after the Item Details and Item Quantity columns, so that it's always at the end of each row
*   No longer show an empty clear column if not allowing clear for a sheet, or if within the minimum time allowed for clear.
*   Clear column will now ALWAYS be shown to admin and signup sheet manager users on public side for easy clearing of signups (if showing individual signups in a task list)
*   Added search box to Admin All Sheets page to search for sheets on the sheet title.
*   Added Visibility and Sheet Type filters to Admin All Sheet list page.
*   Code re-organizing and rewriting
*   Additional hooks to allow new extensions (such as the new Custom Fields extension)
*   Rename "timepicker" class for admin task time selector to avoid conflicts with other time pickers using the same class name with different timepicker scripts
*   Updated datepicker jquery plugin to latest version
*   Korean translation added, courtesy of Jeehyo Chung and Julia Choi

**Version 2.4.3**

*   Added a FROM email address field to the admin Email Volunteers page so you can specify a from email address that matches your sending domain instead of using the email of the logged in user.
*   Updated the "Tested up to" WordPress version

**Version 2.4.2.3**

*  Fixed typo in the filter hook for the item quantity error message that would cause a fatal error when that error should be shown and when used with another plugin (such as the Customizer or Calendar Display) that was tapping into that filter hook to modify the text.

**Version 2.4.2.2**

* Update version number in header

**Version 2.4.2.1**

*   Small fix to strip slashes from sanitized subject and message body of the Email Volunteers form before sending.

**Version 2.4.2**

*   Fix to allow Sign Up Sheet managers to save plugin settings (previously they could see the page but would get a permissions level error if tried to save the settings).
*   New option to only allow admin level users to view Settings and CRON Functions pages. This way you can allow Signup Sheet Mangers to create/edit sheets, but keep them away from the settings and cron functions.
*   Added {email} template tag for all emails, which will show the volunteer's email they signed up with (different from the {contact_emails} template tag).
*   Code fix to prevent reminder emails from also getting sent to chairs/contacts when you set the emails to be sent individually (instead of CC/BCC).

**Version 2.4.1**

*   Fix to show start and end time columns on the user signups list displayed by the new [pta_user_signups] shortcode. Times will now be displayed by default. As with the main shortcode, you can now set a show_time argument to "no" to hide the start and end time columns.

**Version 2.4.0**

*   Added GDPR functions to hook in with new WordPress 4.9.6 GDPR functions. Signup data included in personal data exporter, and also for personal data eraser.
*   Added [pta_user_signups] shortcode to display list of user signups (with clear links, if allowed) on any page.

**Version 2.3.1**

*   Option to show date on every line of CSV export of sheets
*   Minor fix in SQL query to prevent PHP notices for extra unused query argument
*   Additional filter hooks for extensions

**Version 2.3.0**

*   Added sort by and order options to the widget.

**Version 2.2.3**

*   Updated database prepare queries to eliminate new WordPress notices in WP 4.8.3 when passing in a date value that may not be used in the SQL query.

**Version 2.2.2**

*   Added "Prevent Global Overlapping Signups" option to prevent users from signing up for tasks on the same date with overlapping times. As opposed to the per sheet option, this will check ALL user signups across ALL sheets and, when enabled, will always check for overlaps, regardless of the per sheet setting.
*   Added order_by and order arguments to the shortcode for sorting the order of the main list of sheets. order_by can be set to "first_date", "last_date", "title", or "id". order can be either "ASC" or "DESC".

**Version 2.2.1**

*   Logic fix for View Signups on Admin side to ensure that tasks/items with qty of only 1 still get shown even if no signups (i.e., 1 remaining).
*   Minor tweak to email class to check for valid signup ID before proceeding
*   Bumped up database size for the item details field to VARCHAR 500 to support those who are trying to use that field to collect a lot of extra information
*   Doubled size of Task/Item title input on admin side tasks form. Moved checkbox options to next line.

**Version 2.2.0**

*   Lots of extra hooks added for developers to extend the functionality
*   Rewrote admin side "View Signups" page with hooks for modifying columns and data, as well as consolidating remaining spots
*   Added option to show/hide remaining empty slots as rows in the CSV export for a sheet. Default is now NOT to show those empty slots.
*   Fixed problem with contact_emails template tag being replace with "N/A" if emails to chairs was disabled in email settings
*   Added option to use chair emails as the reply-to email address
*   Added option to disable ALL emails (including reminders).
*   Removed the "transposed simplified" csv export option, as it didn't always work right (3rd party contribution), and there is now the option to not show empty rows in regular csv exports
*   Stripslashes from email subject and message templates before sending email
*   More global/public accessible functions for generating html output (user signups, tasks list, signup form) and getting data from the plugin for use in other plugins

**Version 2.1.0**

*   Added shortcode argument to show headers for sheets (title, contacts, and description). Shown by default
*   Added shortcode argument to show phone numbers on signup lists. Hidden by default
*   Modified logic so that the "View All Sheets" link will NOT show on pages where you have specified a specific sheet ID in the shortcode.
*   Code modifications to fix unpredictable output after signing up or clearing a signup (or using the back link from a sign up page) when using multiple sign up sheet shortcodes on one page

**Version 2.0.4**

*   Fixed issue where the wrong date could be used for a task signup if the date was specified in the shortcode, but the particular task was for a different date
*   Updated display sheet function to work properly when you specify both a sheet ID and a Date in the shortcode to show a specific sheet, but only want to show the tasks for a specific date (applies to recurring and multi-day tasks where there is more than one date)
*   Development feedback link added to bottom of main settings page. Please take a minute to contribute your feedback. Thanks!

**Version 2.0.3**

*   Remove any extra spaces before/after email fields on sign-up form before validating
*   Remove slashes from previously filled in fields (names and item details), when special characters have been escaped for safety, on signup form when there is a validation error
*   Increase the size of the signup item details database field from VARCHAR(100) to VARCHAR(300) to allow more text for those using the field for more than just simple item details

**Version 2.0.2**

*   Changed conditions for the option to set "readonly" attribute for name and emails fields to be set when the login required to signup option is set (instead of the login to view option)
*   If using the login to signup option along with the login link option, the login to sign up text will now be linked to the login page on individual sheets in the signup column.
*   Added email field to live search for sign-up forms, and also to the displayed results, so you can now search for WordPress users by email if the users do not have a first or last name saved in their profile yet.
*   The contact_names email template tag will now also pull names from the Member Directory plugin if you are using that integration and a position was specified as the contact.
*   Added option to enable/disable duplicate output suppression (added in version 2.0.0) in case you have a plugin or theme that triggers shortcodes more than once on a page (before the html body) causing a blank page. If you disable this, then you should not use more than one shortcode on a single page without redirecting to a page with only one shortcode for the signup form.
*   Changed the "clear" class on the clear signup links to "clear-signup" to avoid CSS conflicts with some themes and plugins that define anything with a "clear" class as hidden or display:none.
*   Updated Norwegian translation

**Version 2.0.1**

*   Option to show full name when not hiding name (full first and last name instead of first name and last initial)
*   Added optional reminder 2 email subject and message templates. If either is left blank (subject and message are checked independent of each other), then the same subject and/or message template will be used for both reminders (default)
*   CSS fix so new div class styles don't get applied to old table elements
*   Additional CSS classes added to table elements for easier targetting for custom styling

**Version 2.0.0**

*   Added option in email settings to send clear emails when a signup is cleared from the admin pages
*   Add option in email settings to disable emails to chairs/contacts. This will stop all signup and clear emails from being copied to the specified chair/contact emails for a sheet. Global CC emails will still be sent.
*   Added email template tag for chair/contact names (previously only had tag for contact emails)
*   Minor tweak for clear sign-up links when used with the calendar view extension
*   Refactor all public display lists and tables into their own accessible functions, for easier customizing
*   Added option to create output using divs instead of tables, for easier styling and better responsive design (when customizing the CSS)
*   Added option to disable loading the plugin's CSS styles. Use this when you want to create your own CSS styles for layout and messages.
*   Added code to check if the signup form and any messages (errors or signup notices) have already been displayed on a page, and to suppress them from being output more than once for those people who want to put more than one sign-up sheet shortcode on one page without using the redirect option for sign-up forms.
*   Added required attributes to first name, last name, and email fields on signup form. Browsers that support the required attribute will show a message if they try to submit the form without filling out those fields.
*   Added option to set whether the phone fields is required, allowing you to have it on the signup form but still allow people to sign up without filling it out. If set to required, the required attribute will also be added to the html form field inputs.
*   Added new check box on the task fields to set if the details field is required. This check box will only show if you first check "details needed". Setting it as not required (un-checked) will allow you to use the field to collect optional info from the volunteers, without forcing them to fill out the field.
*   Added Norwegian translation

== Additional Info ==

This plugin is a major fork and and almost a complete rewrite of the Sign-Up Sheets plugin by DLS software (now called Sign-Up Sheets Lite). We needed much more functionality than their plugin offered for our PTA web site, so I started with the Sign-Up Sheets plugin and added much more functionality to it, and eventually ended up rewriting quite a bit of it to fit our needs. If you need a much more simple sign-up sheets system, you may want to check out their plugin.

**PLEASE USE THE FEATURE REQUEST FORUM TO REQUEST NEW FEATURES!!**
<https://stephensherrardplugins.com/support/forum/feature-requests/>
