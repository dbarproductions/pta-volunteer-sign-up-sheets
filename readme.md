# Volunteer Sign Up Sheets

A powerful WordPress plugin for managing volunteer sign-ups for events, activities, and ongoing committees. An alternative to services like SignUpGenius that keeps your data on your own site.

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)

## Important: Getting the Plugin

This plugin uses a licensing system for automatic updates. While the plugin is **free**, you should obtain it from the official site to get a license key for automatic updates:

**[Download from Stephen Sherrard Plugins](https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets/)**

If you already have a license for the Complete Bundle, you can use that license to activate this plugin.

### Downloading from GitHub

If you download a release directly from GitHub, be aware that **GitHub automatically adds the version number to the folder name** in the zip file (e.g., `pta-volunteer-sign-up-sheets-6.0.0`).

WordPress requires the plugin folder to be named exactly `pta-volunteer-sign-up-sheets` for proper operation and updates. If you install the GitHub zip directly, it will appear as a separate plugin installation.

**To fix this:**
1. Extract the zip file
2. Rename the folder from `pta-volunteer-sign-up-sheets-X.X.X` to `pta-volunteer-sign-up-sheets`
3. Upload to your `wp-content/plugins/` directory

## Features

### Core Functionality
- **Four Event Types:** Single, Recurring, Multi-Day, and Ongoing events
- **Task Management:** Create multiple tasks per event with quantities, time slots, and descriptions
- **Privacy Protection:** Volunteer contact info hidden from public view
- **Spam Prevention:** Hidden honeypot field blocks automated signups
- **GDPR Compliance:** Integrates with WordPress privacy export/erasure tools

### Version 6.0 Highlights
- **Author System:** Delegate sheet management to committee chairs or event coordinators with a new "Signup Sheet Author" role
- **Email Template Management:** Create, edit, and assign custom email templates at the sheet or task level
- **Modern Task Editor:** Redesigned modal-based interface for easier task management
- **Task Copying:** Quickly reuse common tasks across multiple events
- **Author Filtering:** Display sheets by specific authors using shortcode/block parameters

### Display Options
- Gutenberg blocks and shortcodes for flexible placement
- Upcoming Events widget/block for sidebars
- User Signups List to show logged-in users their commitments
- Tables or div-based layouts with optional mobile CSS
- Full CSS customization support

### Email System
- Confirmation emails for signups
- Two configurable reminder emails per sheet
- Reschedule notifications
- Clear/cancellation confirmations
- Validation emails for non-WordPress users
- HTML or plain text format
- Template tags for dynamic content

### Admin Features
- DataTables interface with sorting, filtering, and search
- Export to Excel, CSV, PDF, or Print
- Bulk operations (trash, delete, restore, toggle visibility)
- Move signups between sheets/tasks/dates
- Live search to sign up volunteers by admin
- CRON management page with log viewing

### Validation System (v5.0+)
- Allow signups without WordPress accounts
- Email-based validation codes
- Validated users can view and clear their own signups
- Auto-cleanup of unvalidated signups

## Available Extensions

Extend functionality with official add-ons:

| Extension | Description |
|-----------|-------------|
| [Calendar Display](https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-calendar-display/) | Display events in calendar format |
| [Custom Fields](https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-custom-fields/) | Add custom fields to sheets, tasks, or signup forms |
| [Customizer](https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets-customizer/) | Customize text, styling, and layout templates |
| [Groups](https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheet-groups/) | Organize sheets by categories/groups |
| [Waitlists](https://stephensherrardplugins.com/plugins/volunteer-sign-up-sheets-waitlists/) | Automatic waitlist management |
| [Mailchimp](https://stephensherrardplugins.com/plugins/volunteer-sign-up-sheets-mailchimp/) | Mailing list opt-in integration |
| [Automated Emails](https://stephensherrardplugins.com/plugins/volunteer-sign-up-sheets-automated-and-conditional-emails/) | Triggered and conditional email campaigns |

## Installation

### From the Official Site (Recommended)
1. "Purchase" the free plugin from [stephensherrardplugins.com](https://stephensherrardplugins.com/plugins/pta-volunteer-sign-up-sheets/)
2. Download the zip file
3. Upload via WordPress Admin > Plugins > Add New > Upload Plugin
4. Activate and enter your license key for automatic updates

### Manual Installation
1. Download/clone this repository
2. Ensure the folder is named `pta-volunteer-sign-up-sheets`
3. Upload to `wp-content/plugins/`
4. Activate via the WordPress Plugins page

## Quick Start

1. **Create a page** for your signup sheets
2. **Add the block or shortcode:** Use the "Sign Up Sheets" block or `[pta_sign_up_sheet]`
3. **Create your first sheet:** Go to Volunteer Sign-Up Sheets > Add New in WordPress admin
4. **Configure settings:** Set up email templates, permissions, and display options

For detailed instructions, see the [full documentation](https://stephensherrardplugins.com/docs/pta-volunteer-sign-up-sheets-documentation/).

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher

## Contributing

Contributions are welcome! This plugin has been serving the volunteer community since 2013 and continues to evolve.

### How to Contribute

1. **Fork** the repository
2. **Create a feature branch:** `git checkout -b feature/my-new-feature`
3. **Follow WordPress Coding Standards**
4. **Test thoroughly** with the latest WordPress and PHP versions
5. **Commit your changes:** `git commit -am 'Add new feature'`
6. **Push to your fork:** `git push origin feature/my-new-feature`
7. **Submit a Pull Request**

### Development Notes

- The `feature/base-object-class` branch contains the V6 architecture refactor
- Check the `Development Notes/` folder for architectural decisions
- Use the `pta_sus_` prefix for all hooks and filters

### Reporting Issues

- **Bugs:** Open an issue with steps to reproduce
- **Feature Requests:** Submit via the [feature request forum](https://stephensherrardplugins.com/support/forum/feature-requests/pta-volunteer-sign-up-sheet-feature-requests/)
- **Security Issues:** Please report privately via the contact form on the website

## Documentation

- [Full Documentation](https://stephensherrardplugins.com/docs/pta-volunteer-sign-up-sheets-documentation/)
- [Support Forums](https://stephensherrardplugins.com/support/)

## Changelog

### Version 6.2.0
- Added a reply-to email address field to the email templates system
- You can now use the {chair_name} and {chair_email} tags in the From name/email field and reply-to email field to automatically use the first valid chair name/email for a sheet with the email template
- Updated the older version 1.x datatables library to the newest version 2.x, and updated admin view scripts to use the newer methods. Both versions are still registered for backward compatibility with extensions that make use of the registered datatables library (e.g. Customizer)

### Version 6.1.1
- Fixed global functions file not being loaded during plugin activation, causing fallback sanitization to be used when creating/migrating email templates
- Sign Up Sheets block now shows a groups multi-select field when the Groups extension is active, replacing the old plain text input 
- Tested with PHP 8.5.3 and WordPress 6.9.1

### Version 6.1.0
- **Centralized License Manager** - A new "Licenses" submenu page under Sign-up Sheets that lets you manage license keys for the main plugin and all extensions in one place. Includes an "Activate All" feature for All Access or bundle license keys.
- Added new option to disable signups after a task's start time has passed (Settings > Main Settings). When enabled, volunteers cannot sign up for tasks once the task date and start time have passed. Tasks without a start time are not affected.
- Added new sub-option to completely hide tasks from the public sign-up sheet once the task start time has passed (Settings > Main Settings). Only applies when the "Disable Signup After Task Start Time" option is also enabled. Tasks without a start time are not affected.
- Fixed task copy feature in the modal task editor carrying over dates and signup restrictions from the original task, which caused incorrect "date cannot be changed" warnings and stale dates appearing when copying tasks to a new sheet

### Version 6.0.3
- Fixed options initialization to properly handle fresh installs where options don't yet exist in the database
- Added automatic creation of system default email templates during options initialization, ensuring templates exist for fresh installs, upgrades, and file-upload installs that bypass the activation hook
- Fixed email sending to use the new template system as the primary source for subject, body, from name, and from email, with legacy email options as fallback only
- Fixed from name and from email fields from email templates not being applied when sending emails
- Fixed clear links not showing in the user signups list
- Fixed task modal editor not properly selecting extension dropdown values of 0 (e.g., "Use Sheet Template" for Custom Fields signup template selector), causing a blank selection to appear instead

### Version 6.0.2
-Added checks to the pta_sanitize_value global function to see if an array is already serialized for the database before sanitizing and serializing possibly a second time, which could cause issues in extensions that use this global function.
-Tested with PHP 8.5.0 and WordPress 6.9.1

### Version 6.0.1
- Fixed sheet first/last dates not updating when editing dates for Single sheet types
- Fixed admin signup edit validation incorrectly blocking edits when all task spots are filled
- Tested with PHP 8.5.0 and WordPress 6.9.1

### Version 6.0.0
**Major Release - New Features:**
- **Author System** - New "Signup Sheet Author" role for delegated management
- **Email Template Management** - Create and assign custom templates at sheet/task level
- **Modern Task Editor** - Modal-based interface with improved UX
- **Task Copying** - Reuse tasks across sheets
- **Author Filtering** - `author_id` and `author_email` shortcode parameters
- **Auto-fill Contact Info** - Pre-populate chair fields from current user

**Improvements:**
- Performance optimization with refactored core architecture
- Enhanced permission checks throughout admin
- Better bulk operations respecting author permissions
- Email template consolidation (migrates from Customizer extension)

### Version 5.8.2
- Fixed SQL error in `get_signup_ids()` function

### Version 5.8.x
- Date format fixes for international languages
- Action processing improvements on admin pages

### Version 5.0.0
**Major Release:**
- Validation system for non-WordPress user signups
- New Gutenberg blocks (Sign Up Sheets, User Signups List, Upcoming Events, Validation Form)
- CRON log file viewing
- PHP 7.4+ requirement

### Version 4.6.0
- HTML format emails
- Mobile responsive CSS option
- Automatic expired sheet cleanup
- Per-sheet email recipient settings
- Signup timestamp tracking

[View full changelog in readme.txt](readme.txt)

## License

This plugin is licensed under the [GNU General Public License v2.0](https://www.gnu.org/licenses/gpl-2.0.html).

## Author

**Stephen Sherrard** - [Stephen Sherrard Plugins](https://stephensherrardplugins.com)

---

*If this plugin helps your organization, please consider [making a donation](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=R4HF689YQ9DEE) to support continued development.*
