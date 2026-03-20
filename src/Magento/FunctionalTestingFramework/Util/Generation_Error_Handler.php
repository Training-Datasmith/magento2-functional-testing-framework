<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util;

class Generation_Error_Handler
{
    /**
     * Generation Error Handler Instance
     */
    private static ?\Magento\Functional_Testing_Framework\Util\Generation_Error_Handler $instance = null;
    /**
     * Collected errors
     */
    private array $errors = [];
    /**
     * Singleton method to return GenerationErrorHandler
     *
     * @return GenerationErrorHandler
     */
    public static function get_instance()
    {
        if (!self::$instance) {
            self::$instance = new Generation_Error_Handler();
        }
        return self::$instance;
    }
    /**
     * GenerationErrorHandler constructor
     */
    private function __construct()
    {
    }
    /**
     * Add a generation error into error handler
     *
     * @param string  $type
     * @param string  $entityName
     * @param string  $message
     * @param boolean $generated
     * @throws \Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException
     */
    public function add_error($type, $entity_name, $message, $generated = false): void
    {
        $error[$entity_name] = ['message' => $message, 'generated' => $generated];
        if (isset($this->errors[$type])) {
            $this->errors[$type] = array_merge_recursive($this->errors[$type], $error);
        } else {
            $this->errors[$type] = $error;
        }
    }
    /**
     * Return all errors
     *
     * @return array
     */
    public function get_all_errors()
    {
        return $this->errors;
    }
    /**
     * Return all error message in a string
     */
    public function get_all_error_messages(): string
    {
        $err_messages = '';
        foreach ($this->errors as $errors) {
            foreach ($errors as $error) {
                if (is_array($error['message'])) {
                    $err_messages .= (!empty($err_messages) ? PHP_EOL : '') . implode(PHP_EOL, $error['message']);
                } else {
                    $err_messages .= (!empty($err_messages) ? PHP_EOL : '') . $error['message'];
                }
            }
        }
        return $err_messages;
    }
    /**
     * Return errors for given type
     *
     * @param string $type
     * @return array
     */
    public function get_errors_by_type($type)
    {
        return $this->errors[$type] ?? [];
    }
    /**
     * Reset error to empty array
     */
    public function reset(): void
    {
        $this->errors = [];
    }
    /**
     * Print error summary in console
     */
    public function print_error_summary(): void
    {
        foreach (array_keys($this->errors) as $type) {
            $total_errors = count($this->get_errors_by_type($type));
            $total_annotation_errors = 0;
            foreach ($this->get_errors_by_type($type) as $error) {
                if (is_array($error['generated']) && $error['generated'][0] === true || $error['generated'] === true) {
                    $total_annotation_errors++;
                }
            }
            $total_not_gen_errors = $total_errors - $total_annotation_errors;
            if ($total_not_gen_errors > 0) {
                print 'ERROR: ' . strval($total_not_gen_errors) . ' ' . ucfirst((string) $type) . '(s) failed to generate. See mftf.log for details.' . PHP_EOL;
            }
            if ($total_annotation_errors > 0) {
                if ($type !== 'suite') {
                    print 'ERROR: ' . strval($total_annotation_errors) . ' ' . ucfirst((string) $type) . '(s) generated with annotation errors. See mftf.log for details.' . PHP_EOL;
                } else {
                    print 'ERROR:  ' . strval($total_annotation_errors) . ' ' . ucfirst($type) . '(s) has(have) tests with annotation errors or some included tests missing.' . ' See mftf.log for details.' . PHP_EOL;
                }
            }
        }
        print PHP_EOL;
    }
}