<?php

class PTA_SUS_Volunteer {
	/**
	 * @var int $user_id
	 */
	protected $user_id;
	/**
	 * @var string $firstname
	 */
	protected $firstname;
	/**
	 * @var string $lastname
	 */
	protected $lastname;
	/**
	 * @var string $email
	 */
	protected $email;
	/**
	 * @var bool $validated;
	 */
	protected $validated = false;

	public $validation_options;
	public $validation_enabled;

	public function __construct($user_id=0) {
		$this->user_id = $user_id > 0 ? $user_id : get_current_user_id();
		$this->firstname = '';
		$this->lastname = '';
		$this->email = '';
		$this->validation_options = get_option('pta_volunteer_sus_validation_options', array());
		$this->validation_enabled = isset($this->validation_options['enable_validation']) && $this->validation_options['enable_validation'];
		if($this->user_id > 0) {
			$user_info = get_userdata($this->user_id);
			if($user_info) {
				$this->firstname = $user_info->first_name;
				$this->lastname = $user_info->last_name;
				$this->email = $user_info->user_email;
				$this->validated = true;
			} else {
				$this->user_id = 0;
				$this->validated = false;
			}
		} elseif($this->validation_enabled) {
			$user_info = pta_get_validated_user_info();
			if($user_info) {
				$this->user_id = $user_info->user_id;
				$this->firstname = sanitize_text_field($user_info->firstname);
				$this->lastname = sanitize_text_field($user_info->lastname);
				$this->email = sanitize_email($user_info->email);
				$this->validated = true;
			} else {
				$this->user_id = 0;
				$this->validated = false;
			}
		} else {
			$this->validated = false;
		}
	}

	public function get_user_id() {
		return $this->user_id;
	}
	public function get_firstname() {
		return $this->firstname;
	}
	public function get_lastname() {
		return $this->lastname;
	}
	public function get_email() {
		return $this->email;
	}
	public function is_validated() {
		return $this->validated;
	}

	public function can_modify_signup($signup) {
		if(current_user_can( 'manage_signup_sheets' )) {
			return true;
		}
		if($this->user_id > 0) {
			return (absint($signup->user_id) === $this->user_id);
		}
		if($this->validation_enabled) {
			return ($this->firstname === $signup->firstname && $this->lastname === $signup->lastname && $this->email === $signup->email);
		}
		return false;
	}

	public function get_detailed_signups() {
		$where = $signups = array();
		if($this->user_id > 0) {
			$where = array('user_id' => $this->user_id);
		} elseif ($this->validated) {
			$where = array(
				'firstname' => $this->firstname,
				'lastname' => $this->lastname,
				'email' => $this->email,
				'validated' => 1,
			);
		}
		if(!empty($where)) {
			$signups = PTA_SUS_Signup_Functions::get_detailed_signups($where);
		}
		return $signups;
	}

}