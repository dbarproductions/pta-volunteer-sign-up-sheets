<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class PTA_SUS_Messages {
    private static $errors = array();
    private static $messages = array();

	private static $data_clear_url = null;

    public static function add_error($text) {
        self::$errors[] = wp_kses_post($text);
    }

    public static function add_message($text, $data_clear_url=false) {
        self::$messages[] = wp_kses_post($text);
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
	public static function show_messages($echo=false, $context='public') {
		$output = '';
		if ($context === 'admin') {
			if (sizeof(self::$errors) > 0) {
				foreach (self::$errors as $error) {
					$output .= '<div id="message" class="error inline"><p><strong>' . $error . '</strong></p></div>';
				}
			}
			if (sizeof(self::$messages) > 0) {
				foreach (self::$messages as $message) {
					$output .= '<div id="message" class="updated inline"><p><strong>' . $message . '</strong></p></div>';
				}
			}
		} else {
			if (sizeof(self::$errors) > 0) {
				$output .=  '<div id="pta-sus-messages" class="pta-sus error-div fade">';
				foreach (self::$errors as $error) {
					$output .=  '<p class="pta-sus error">' . $error . '</p>';
				}
				$output .=  '</div>';
			}
			if (sizeof(self::$messages) > 0) {
				$output .=  '<div id="pta-sus-messages" class="pta-sus notice-div fade"'. (self::$data_clear_url ? ' data-clear-url="true"' : '') . '>';
				foreach (self::$messages as $message) {
					$output .=  '<p class="pta-sus updated">' .$message . '</p>';
				}
				$output .=  '</div>';
			}
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
