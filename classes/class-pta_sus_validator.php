<?php

/**
 * Validation Helper Class
 * Validates user input before saving to database
 *
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.0.0
 */
class PTA_SUS_Validator {

    /**
     * Validate task data from form submission
     *
     * @param array $data Task data to validate
     * @return array|WP_Error Validated data or WP_Error on failure
     */
    public static function validate_task_data($data)
    {
        $errors = new WP_Error();

        // Validate dates
        if (isset($data['dates'])) {
            $valid_dates = pta_sus_sanitize_dates($data['dates']);
            if (empty($valid_dates)) {
                $errors->add('invalid_dates', 'At least one valid date is required');
            } else {
                $data['dates'] = implode(',', $valid_dates);
            }
        }

    // Validate other fields...

        if ($errors->has_errors()) {
            return $errors;
        }

        return $data;
    }

    /**
     * Validate sheet data from form submission
     *
     * @param array $data Sheet data to validate
     * @return array|WP_Error Validated data or WP_Error on failure
     */
    public static function validate_sheet_data($data)
    {
        // Validate sheet fields
        // ...
        return $data;
    }

    /**
     * Validate signup data from form submission
     *
     * @param array $data Signup data to validate
     * @return array|WP_Error Validated data or WP_Error on failure
     */
    public static function validate_signup_data($data)
    {
        // Validate signup fields
        // ...
        return $data;
    }
}