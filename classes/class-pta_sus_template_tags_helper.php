<?php
class PTA_SUS_Template_Tags_Helper {
	private static $template_tags_registry = array();

	public static function register_template_tag($tag, $description, $category = 'general') {
		self::$template_tags_registry[$category][] = array(
			'tag' => $tag,
			'description' => $description
		);
	}

	public static function init() {
		// Register the default tags on init
		self::register_template_tag(
			'{sheet_title}',
			__('Sheet Title','pta-volunteer-sign-up-sheets'),
			'Sheet'
		);
		self::register_template_tag(
			'{sheet_details}',
			__('Sheet - Program/Event Details','pta-volunteer-sign-up-sheets'),
			'Sheet'
		);
		self::register_template_tag(
			'{contact_emails}',
			__('Sheet Chair/Contact Email(s)','pta-volunteer-sign-up-sheets'),
			'Sheet'
		);
		self::register_template_tag(
			'{contact_names}',
			__('Sheet Chair/Contact Name(s)','pta-volunteer-sign-up-sheets'),
			'Sheet'
		);
		self::register_template_tag(
			'{task_title}',
			__('Task Title','pta-volunteer-sign-up-sheets'),
			'Task'
		);
		self::register_template_tag(
			'{task_description}',
			__('Task Description','pta-volunteer-sign-up-sheets'),
			'Task'
		);
		self::register_template_tag(
			'{date}',
			__('Task/Signup Date','pta-volunteer-sign-up-sheets'),
			'Task'
		);
		self::register_template_tag(
			'{start_time}',
			__('Task Start Time','pta-volunteer-sign-up-sheets'),
			'Task'
		);
		self::register_template_tag(
			'{end_time}',
			__('Task End Time','pta-volunteer-sign-up-sheets'),
			'Task'
		);
		self::register_template_tag(
			'{details_text}',
			__('Task/Item Details','pta-volunteer-sign-up-sheets'),
			'Task'
		);
		self::register_template_tag(
			'{firstname}',
			__('Signup First Name','pta-volunteer-sign-up-sheets'),
			'Signup'
		);
		self::register_template_tag(
			'{lastname}',
			__('Signup Last Name','pta-volunteer-sign-up-sheets'),
			'Signup'
		);
		self::register_template_tag(
			'{email}',
			__('Signup Email','pta-volunteer-sign-up-sheets'),
			'Signup'
		);
		self::register_template_tag(
			'{phone}',
			__('Signup Phone','pta-volunteer-sign-up-sheets'),
			'Signup'
		);
		self::register_template_tag(
			'{item_details}',
			__('Signup Task/Item Details','pta-volunteer-sign-up-sheets'),
			'Signup'
		);
		self::register_template_tag(
			'{item_qty}',
			__('Signup Task/Item Quantity','pta-volunteer-sign-up-sheets'),
			'Signup'
		);
		self::register_template_tag(
			'{site_name}',
			__('Site Name','pta-volunteer-sign-up-sheets'),
			'General'
		);
		self::register_template_tag(
			'{site_url}',
			__('Signup URL','pta-volunteer-sign-up-sheets'),
			'General'
		);
		self::register_template_tag(
			'{signup_expiration_hours}',
			__('Signup Expiration Hours - Signup Validation','pta-volunteer-sign-up-sheets'),
			'Validation'
		);
		self::register_template_tag(
			'{validation_code_expiration_hours}',
			__('Validation Code Expiration Hours - Site Validation','pta-volunteer-sign-up-sheets'),
			'Validation'
		);
	}

	public static function render_helper_panel() {
		// Add floating button in top-right
		echo '<div id="pta-sus-template-helper-toggle" class="pta-sus-helper-btn">';
		echo '<span class="dashicons dashicons-editor-help"></span> '.__('Template Tags', 'pta-volunteer-sign-up-sheets');
		echo '</div>';

		// Add modal/panel markup
		echo '<div id="pta-sus-template-helper-panel" class="pta-sus-helper-panel">';
		echo '<div class="pta-sus-helper-search">';
		echo '<input type="text" placeholder="'.__('Search template tags...', 'pta-volunteer-sign-up-sheets').'" />';
		echo '</div>';
		echo '<div class="pta-sus-description"><em>&nbsp;'.__('Click on a tag to copy to your clipboard.', 'pta-volunteer-sign-up-sheets').'</em></div>';
		echo '<div class="pta-sus-helper-tags">';
		// Render organized template tags
		foreach(self::$template_tags_registry as $category => $tags) {
			echo "<h3>$category</h3>";
			foreach($tags as $tag_data) {
				echo "<div class='tag-item' data-tag='{$tag_data['tag']}'>";
				echo "<code>{$tag_data['tag']}</code>";
				echo "<span>{$tag_data['description']}</span>";
				echo "</div>";
			}
		}
		echo '</div></div>';
	}
}
PTA_SUS_Template_Tags_Helper::init();