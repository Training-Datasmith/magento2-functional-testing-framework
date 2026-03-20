<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Exceptions\Collector;

class Exception_Collector
{
    /**
     * Private array containing all errors to be thrown as part of the exception.
     */
    private array $errors = [];
    /**
     * Function to add a filename and message for the filename
     *
     * @param string $filename
     * @param string $message
     */
    public function add_error($filename, $message): void
    {
        $error[$filename] = $message;
        $this->errors = array_merge_recursive($this->errors, $error);
    }
    /**
     * Function which throws an exception when there are errors present.
     *
     * @throws \Exception
     */
    public function throw_exception(): void
    {
        if (empty($this->errors)) {
            return;
        }
        $error_msg = implode("\n\n", $this->format_errors($this->errors));
        throw new \Exception("\n" . $error_msg);
    }
    /**
     * Return all errors
     *
     * @return array
     */
    public function get_errors()
    {
        return $this->errors ?? [];
    }
    /**
     * Reset error to empty array
     */
    public function reset(): void
    {
        $this->errors = [];
    }
    /**
     * If there are multiple exceptions for a single file, the function flattens the array so they can be printed
     * as separate messages.
     *
     * @param array $errors
     */
    private function format_errors($errors): array
    {
        $flattened_errors = [];
        foreach ($errors as $error_msg) {
            if (is_array($error_msg)) {
                $flattened_errors = array_merge($flattened_errors, $this->format_errors($error_msg));
                continue;
            }
            $flattened_errors[] = $error_msg;
        }
        return $flattened_errors;
    }
}