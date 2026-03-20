<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Validation;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Xml_Exception;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
class Name_Validation_Util
{
    public const PHP_CLASS_REGEX_PATTERN = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/';
    public const DATA_ENTITY_NAME = 'data entity name';
    public const DATA_ENTITY_KEY = 'data entity key';
    public const METADATA_OPERATION_NAME = 'metadata operation name';
    public const PAGE = 'Page';
    public const SECTION = 'Section';
    public const SECTION_ELEMENT_NAME = 'section element name';
    public const ACTION_GROUP_NAME = 'action group name';
    public const TEST_NAME = 'test name';
    /**
     * The number of violations this instance has detected.
     */
    private int $count;
    /**
     * NameValidationUtil constructor.
     *
     */
    public function __construct()
    {
        $this->count = 0;
    }
    /**
     * Function which runs a validation against the blocklisted char defined in this class. Validation occurs to insure
     * allure report does not error/future devOps builds do not error against illegal char.
     *
     * @param string $name
     * @param string $type
     * @throws XmlException
     */
    public static function validate_name($name, $type): void
    {
        $starting_pos = 0;
        $illegal_char_array = [];
        $name_to_evaluate = $name;
        while ($starting_pos < strlen($name_to_evaluate)) {
            $starting_pos++;
            $partial_name = substr($name_to_evaluate, 0, $starting_pos);
            $valid = boolval(preg_match(self::PHP_CLASS_REGEX_PATTERN, $partial_name));
            if (!$valid) {
                $illegal_char = str_split($partial_name)[$starting_pos - 1];
                $illegal_char_array[] = $illegal_char;
                $name_to_evaluate = str_replace($illegal_char, '', $name_to_evaluate);
                $starting_pos--;
            }
        }
        if (!empty($illegal_char_array)) {
            $error_message = "{$type} name \"{$name}\" contains illegal characters, please fix and re-run.";
            foreach ($illegal_char_array as $diff_char) {
                $error_message .= "\nTest names cannot contain '{$diff_char}'";
            }
            throw new Xml_Exception($error_message);
        }
    }
    /**
     * Validates that the string is PascalCase.
     *
     * @param string $str
     * @param string $type
     * @param string $filename
     * @throws TestFrameworkException
     */
    public function validate_pascal_case($str, $type, $filename = null): void
    {
        if (!is_string($str) || !ctype_upper($str[0])) {
            $message = "The {$type} {$str} should be PascalCase with an uppercase first letter.";
            if ($filename !== null) {
                $message .= " See file {$filename}.";
            }
            Logging_Util::get_instance()->get_logger(self::class)->notification($message, [], false);
            $this->count++;
        }
    }
    /**
     * Validates that the string is camelCase.
     *
     * @param string $str
     * @param string $type
     * @param string $filename
     * @throws TestFrameworkException
     */
    public function validate_camel_case($str, $type, $filename = null): void
    {
        if (!is_string($str) || !ctype_lower($str[0])) {
            $message = "The {$type} {$str} should be camelCase with a lowercase first letter.";
            if ($filename !== null) {
                $message .= " See file {$filename}.";
            }
            Logging_Util::get_instance()->get_logger(self::class)->notification($message, [], false);
            $this->count++;
        }
    }
    /**
     * Validates that the string is of the pattern {Admin or Storefront}{Description}{Type}.
     *
     * @param string $str
     * @param string $type
     * @param string $filename
     * @throws TestFrameworkException
     */
    public function validate_affixes($str, $type, $filename = null): void
    {
        $is_prefix_admin = str_starts_with($str, 'Admin');
        $is_prefix_storefront = str_starts_with($str, 'Storefront');
        $is_suffix_type = str_ends_with($str, $type);
        if (!$is_prefix_admin && !$is_prefix_storefront || !$is_suffix_type) {
            $message = "The {$type} name {$str} should follow the pattern {Admin or Storefront}{Description}{$type}.";
            if ($filename !== null) {
                $message .= " See file {$filename}.";
            }
            Logging_Util::get_instance()->get_logger(self::class)->notification($message, [], false);
            $this->count++;
        }
    }
    /**
     * Outputs the number of validations detected by this instance.
     *
     * @param string $type
     * @throws TestFrameworkException
     */
    public function summarize($type): void
    {
        if ($this->count > 0) {
            Logging_Util::get_instance()->get_logger(self::class)->notification("{$this->count} {$type} violations detected. See mftf.log for details.", [], true);
        }
    }
}