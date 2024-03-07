<?php

/**
 * A simple class that checks the passed value against validation rules.
 *
 * Supported validators
 * - required Checks if the value is empty
 * - varchar Checks if the value is valid varchar value
 * - integer Checks if the value is valid integer value
 * - datetime Checks if the value is valid DateTime value. Valid DateTime format must be configurated in $validators['datetime']['pattern'].
 * - after(YYYY-MM-DD HH:MM:SS | <datetime_source_fieldname>) Checks if date time value is after the configured one,
 * it can be a valid date time value or date time source field.
 *
 * - enum(<value_1>|<value_2>|default:<default_value>) Checks if the value is in the list of valid values. If not, default value (if configurated) will be assigned.
 * - hexcolor Checks if the value is valid Hex color value
 *
 * Example usage:
 * $validationRules = [
 *  "name" => "required|varchar",
 *  "startDate"=> "datetime"
 * ];

 * $dataArray = [
 *  "name" => "John Dow",
 *  "startDate"=> "2024-11-12 00:00"
 * ];
 *
 * Validator::init($dataArray);
 * Validator::validateAll($validationRules);
 *
 * if (Validator::isValidated() === false) {
 *  return Validator::getErrors();
 * }
 *
 * $validatedData = Validator::getValidated();
 */
class Validator
{
    /**
     * @var array Data to validate: ['<field_name>' => '<field_value>']
     */
    private static $data = [];

    /**
     * @var array $validators Array with settings for each validator
     */
    private static $validators = [
        'required' => [
            'pattern' => '/\S/',
            'message' => 'This field is required.'
        ],
        'varchar' => [
            'pattern' => '/^[a-zA-Z0-9_\-\.\s\(\)\/]{1,255}$/',
            'message' => 'Incorrect varchar field value.'
        ],
        'integer' => [
            'pattern' => '/^[0-9]+$/',
            'message' => 'Incorrect integer field value.'

        ],
        'datetime' => [
            'pattern' => 'Y-m-d H:i',
            'message' => 'Incorrect Date Time field value. Please use <YYYY-MM-DD HH:MM> format.'
        ],
        'after' => [
            'pattern' => 'Y-m-d H:i',
            'message' => 'Incorrect After Date Time field value must be in future.'
        ],
        'enum' => [
            'message' => 'Incorrect Enum field value.',
        ],
        'hexcolor' => [
            'pattern' => '/^#(?:[0-9a-fA-F]{3}){1,2}$/',
            'message' => 'Invalid hexadecimal color value'
        ]
    ];

    /**
     * @var string $errorMessage Main error message
     */
    private static $errorMessage = 'Invalid Data Provided';

    /**
     * @var array $errorFields Array to store errors for each field
     */
    private static $errorFields = [];

    /**
     * @var bool $isValidData А variable that indicates whether validation passed or failed
     */
    private static $isValidData = true;

    /**
     * Initializes the validator with data for validation.
     *
     * @param array $data Data for validation
     * @return void
     */
    public static function init(array $data) :void
    {
        unset($data['rules']);
        self::$data = $data;
    }

    /** Return errora array if validation fails.
     *
     * @return array Errors array
     */
    public static function getErrors() :array
    {
        return (self::$isValidData) ? [] : ['error_message' => self::$errorMessage, 'errors' => self::$errorFields];
    }

    /**
     * Return validated data array.
     *
     * @return array
     */
    public static function getValidated() :array
    {
        return self::$data;
    }

    /**
     * Validate all fields from data array.
     *
     * @param array $rules Validation rules array.
     * @return void
     */
    public static function validateAll(array $rules) :void
    {
        foreach (self::$data as $name => $value) {
            if (isset($rules[$name])) {
                self::validateField($name, $value,  $rules[$name]);
            }
        }
    }

    /**
     * Return whether validation passed or failed.
     *
     * @return bool
     */
    public static function isValidated() :bool
    {
        return self::$isValidData;
    }

    /**
     * Validate a single field.
     *
     * @param string $fieldName Field name
     * @param string|null $fieldValue Field value
     * @param string $rules Validation rules for the field. e.x. 'required|varchar'
     * @return bool
     */
    public static function validateField(string $fieldName, null|string $fieldValue, string $rules) :bool
    {
        $fieldValue = trim((string) $fieldValue);
        $validations = preg_split('/(?<!\([^)]\x5c)[|](?![^(]*\))/',$rules);

        foreach ($validations as $validator) {
            $validatorName = $validator;

            // get validator name
            if (preg_match('/^(.*?)\(/', $validator, $matches)) {
                $validatorName = $matches[1];
            }

            // get validator params
            $validatorParams = '';
            if (preg_match('/\((.*?)\)/', $validator, $matches)) {
                $validatorParams = $matches[1];
            }

            $methodName = 'validate' . ucfirst($validatorName);
            if (method_exists(self::class, $methodName)) {
                $result = call_user_func_array([self::class, $methodName], [$fieldName, $fieldValue, $validatorParams]);
                if (!$result) {
                    return $result;
                }
            } else {
                $result = call_user_func_array([self::class, 'validateDefault'], [$fieldName, $fieldValue]);
                if (!$result) {
                    return $result;
                }
            }
        }

        return $result;
    }

    /**
     * Required validator validation logic.
     *
     * Checks a regular expression from $validators['requierd']['pattern']
     *
     * @param string $fieldName Field name
     * @param string $value Field value
     * @param string $validatorParams Validation rules parameters if setted
     * @return bool
     */
    private static function validateRequired(string $fieldName = '', string $value = '', string $validatorParams = '') :bool
    {
        $settings = self::$validators['required'];

        if (!preg_match("{$settings['pattern']}", $value)) {
            self::setError([$fieldName => $settings['message']]);
            return false;
        }
        self::$data[$fieldName] = $value;

        return true;
    }

    /**
     * Hexcolor validator validation logic.
     *
     * Checks a regular expression from $validators['hexcolor']['pattern']
     *
     * @param string $fieldName Field name
     * @param string $value Field value
     * @param string $validatorParams Validation rules parameters if setted
     * @return bool
     */
    private static function validateHexcolor(string $fieldName = '', string $value = '', string $validatorParams = '') :bool
    {
        if ($value) {
            $settings = self::$validators['hexcolor'];

            if (!preg_match("{$settings['pattern']}", $value)) {
                self::setError([$fieldName => $settings['message']]);
                return false;
            }
        } else {
            $value = null;
        }
        self::$data[$fieldName] = $value;

        return true;
    }

    /**
     * Integer validator validation logic.
     *
     * Checks a regular expression from $validators['integer']['pattern']
     *
     * @param string $fieldName Field name
     * @param string $value Field value
     * @param string $validatorParams Validation rules parameters if setted
     * @return bool
     */
    private static function validateInteger(string $fieldName = '', string $value = '', string $validatorParams = '') :bool
    {
        if ($value) {
            $settings = self::$validators['integer'];

            if (!preg_match("{$settings['pattern']}", $value)) {
                self::setError([$fieldName => $settings['message']]);
                return false;
            }
        } else {
            $value = null;
        }
        self::$data[$fieldName] = $value;

        return true;
    }

    /**
     * Varchar validator validation logic.
     *
     * Checks a regular expression from $validators['varchar']['pattern']
     *
     * @param string $fieldName Field name
     * @param string $value Field value
     * @param string $validatorParams Validation rules parameters if setted
     * @return bool
     */
    private static function validateVarchar(string $fieldName = '', string $value = '', string $validatorParams = '') :bool
    {
        if ($value) {
            $settings = self::$validators['varchar'];

            if (!preg_match("{$settings['pattern']}", $value)) {
                self::setError([$fieldName => $settings['message']]);
                return false;
            }
        } else {
            $value = null;
        }
        self::$data[$fieldName] = $value;

        return true;
    }

    /**
     * Date Time validator validation logic.
     *
     * Checks if a value in valid a DateTime format. Format configurated in $validators['datetime']['pattern']
     *
     * @param string $fieldName Field name
     * @param string $value Field value
     * @param string $validatorParams Validation rules parameters if setted
     * @return bool
     */
    private static function validateDatetime(string $fieldName = '', string $value = '', string $validatorParams = '') :bool
    {
        if ($value) {
            $settings = self::$validators['datetime'];

            try {
                $dateTime = DateTime::createFromFormat($settings['pattern'], $value);
                if (!$dateTime) {
                    throw new Exception;
                }
                if ($value != $dateTime->format($settings['pattern'])) {
                    throw new Exception;
                }
            } catch (Exception $e) {
                self::setError([$fieldName => $settings['message']]);
                return false;
            }
        } else {
            $value = null;
        }
        self::$data[$fieldName] = $value;

        return true;
    }

    /**
     * Enum validator validation logic.
     *
     * Checks if the value is in the list of valid values. If not, default value (if defined) will be assigned.
     * Checks if a value in valid a DateTime format. Format configurated in $validators['datetime']['pattern']
     * List of valid values and default value must be setted in configuration rule. E.x. enum(<value_1>|<value_2>|default:<default_value>)
     *
     * @param string $fieldName Field name
     * @param string $value Field value
     * @param string $validatorParams Enum validation rule parameters
     * @return bool
     */
    private static function validateEnum(string $fieldName = '', string $value = '', string $validatorParams = '') :bool
    {
        if ($value) {
            $settings = self::$validators['enum'];
            if (empty($validatorParams)) {
                self::setError([$fieldName => 'Invalid validator configuration.']);
                return false;
            }

            //get enum values
            $enumValues = explode('|', $validatorParams);

            // get default value if set
            $key = array_filter($enumValues, function ($value) use (&$enumValues) {
                if (strpos($value, 'default') !== false) {
                    $key = array_search($value, $enumValues);
                    unset($enumValues[$key]);
                    return $key;
                }
            });

            $defaulValue = null;
            if (count($key)) {
                list(,$defaulValue) = explode(':', end($key));
            }

            if (!in_array($value, $enumValues)) {
                if ($defaulValue) {
                    self::$data[$fieldName] = $defaulValue;
                    return true;
                }
                self::setError([$fieldName => $settings['message']]);
                return false;
            }
        } else {
            $value = null;
        }
        self::$data[$fieldName] = $value;

        return true;
    }

    /**
     * Date Time validator validation logic.
     *
     * Checks whether the date time value is after the configured one, it can be a valid date time value or date time source field.
     * E.x. after(startDate) in this case value will be taken from startDate field.
     *
     * @param string $fieldName Field name
     * @param string $value Field value
     * @param string $validatorParams Validation rules parameters
     * @return bool
     */
    private static function validateAfter(string $fieldName = '', string $value = '', string $validatorParams = '') :bool
    {
        if ($value) {
            $settings = self::$validators['after'];
            if (empty($validatorParams)) {
                self::setError([$fieldName => 'Invalid validator configuration.']);
                return false;
            }

            //validate current value
            $isValidDateTime = self::validateDatetime($fieldName, $value);
            if (!$isValidDateTime) {
                return false;
            }

            //validate after value
            $afterValue = isset(self::$data[$validatorParams]) ? self::$data[$validatorParams] : $validatorParams;
            $isValidAfterDateTime = self::validateDatetime($fieldName, $afterValue);
            if (!$isValidAfterDateTime) {
                return false;
            }

            // compate two dates
            $currentValueObj = DateTime::createFromFormat($settings['pattern'], $value);
            $afterValueObj = DateTime::createFromFormat($settings['pattern'], $afterValue);

            if ($currentValueObj <= $afterValueObj) {
                self::setError([$fieldName => $settings['message']]);
                return false;
            }
        } else {
            $value = null;
        }
        self::$data[$fieldName] = $value;

        return true;
    }

    /**
     * Default validator validation logic.
     *
     * If validator provided in validation rule, but validation logic is not implemented, this behavior will be applyed.
     * Checks a regular expression from $validators['default']['pattern'] if defined, otherwise the original value will be left.
     *
     * @param string $fieldName Field name
     * @param string $value Field value
     * @param string $validatorParams Validation rule parameters if setted
     * @return bool
     */
    private static function validateDefault(string $fieldName = '', string $value = '', string $validatorParams = '') :bool
    {
        if ($value) {
            $settings = isset(self::$validators['default']) ? self::$validators['default'] : ['pattern' => '', 'message' => ''];

            if ($settings['pattern'] && !preg_match("{$settings['pattern']}", $value)) {
                self::setError([$fieldName => $settings['message']]);
                return false;
            }
        } else {
            $value = null;
        }
        self::$data[$fieldName] = $value;

        return true;
    }

    /**
     * If field validation fails, set error message for this field.
     *
     * @param array $errorField Array in format ['<field_name>' => 'Error message for this field']
     * @param string $message Error message to main error message
     * @return void
     */
    private static function setError(array $errorField = [], string $message = '') :void
    {
        self::$isValidData = false;
        if ($message) {
            self::$errorMessage = $message;
        }
        self::$data[key($errorField)] = null;
        self::$errorFields = array_merge(self::$errorFields, $errorField);
    }

}