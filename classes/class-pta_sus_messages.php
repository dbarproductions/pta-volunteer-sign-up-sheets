<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class PTA_SUS_Messages {
    private static $errors = array();
    private static $messages = array();

	private static $data_clear_url = null;

    public static function add_error($text) {
        self::$errors[] = sanitize_text_field($text);
    }

    public static function add_message($text, $data_clear_url=false) {
        self::$messages[] = sanitize_text_field($text);
		self::$data_clear_url = $data_clear_url;

    }

	public static function get_errors() {
		return self::$errors;
	}

	public static function get_messages() {
		return self::$messages;
	}

	/**
	 * @param $echo bool
	 *
	 * @return string|void
	 */
	public static function show_messages($echo=false) {
		$output = '';
        if (sizeof(self::$errors) > 0) {
			$output .=  '<div id="pta-sus-messages" class="pta-sus error-div fade">';
            foreach (self::$errors as $error) {
                $output .=  '<p class="pta-sus error">' . esc_html($error) . '</p>';
            }
			$output .=  '</div>';
        }
        if (sizeof(self::$messages) > 0) {
	        $output .=  '<div id="pta-sus-messages" class="pta-sus notice-div fade"'. (self::$data_clear_url ? ' data-clear-url="true"' : '') . '>';
            foreach (self::$messages as $message) {
                $output .=  '<p class="pta-sus updated">' . esc_html($message) . '</p>';
            }
	        $output .=  '</div>';
        }
		if(!empty($output)) {
			if($echo) {
				echo $output;
			} else {
				return $output;
			}
		}
    }

	public static function clear_messages() {
		self::$messages = array();
		self::$errors = array();
	}
}
