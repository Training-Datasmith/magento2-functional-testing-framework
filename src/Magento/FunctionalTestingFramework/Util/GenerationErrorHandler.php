<?php

declare(strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Util;

class GenerationErrorHandler
{
    /**
     * Generation Error Handler Instance
     */
    private static ?\Magento\FunctionalTestingFramework\Util\GenerationErrorHandler $instance = null;

    /**
     * Collected errors
     */
    private array $errors = [];

    /**
     * Singleton method to return GenerationErrorHandler
     *
     * @return GenerationErrorHandler
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new GenerationErrorHandler();
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
    public function addError($type, $entityName, $message, $generated = false): void
    {
        $error[$entityName] = [
            'message' => $message,
            'generated' => $generated,
        ];
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
    public function getAllErrors()
    {
        return $this->errors;
    }

    /**
     * Return all error message in a string
     */
    public function getAllErrorMessages(): string
    {
        $errMessages = '';

        foreach ($this->errors as $errors) {
            foreach ($errors as $error) {
                if (is_array($error['message'])) {
                    $errMessages .= (!empty($errMessages) ? PHP_EOL : '') . implode(PHP_EOL, $error['message']);
                } else {
                    $errMessages .= (!empty($errMessages) ? PHP_EOL : '') . $error['message'];
                }
            }
        }

        return $errMessages;
    }

    /**
     * Return errors for given type
     *
     * @param string $type
     * @return array
     */
    public function getErrorsByType($type)
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
    public function printErrorSummary(): void
    {
        foreach (array_keys($this->errors) as $type) {
            $totalErrors = count($this->getErrorsByType($type));
            $totalAnnotationErrors = 0;
            foreach ($this->getErrorsByType($type) as $error) {
                if ((is_array($error['generated']) && $error['generated'][0] === true)
                    || ($error['generated'] === true)) {
                    $totalAnnotationErrors++;
                }
            }
            $totalNotGenErrors = $totalErrors - $totalAnnotationErrors;
            if ($totalNotGenErrors > 0) {
                print(
                    'ERROR: '
                    . strval($totalNotGenErrors)
                    . ' '
                    . ucfirst((string) $type)
                    . '(s) failed to generate. See mftf.log for details.'
                    . PHP_EOL
                );
            }
            if ($totalAnnotationErrors > 0) {
                if ($type !== 'suite') {
                    print(
                        'ERROR: '
                        . strval($totalAnnotationErrors)
                        . ' '
                        . ucfirst((string) $type)
                        . '(s) generated with annotation errors. See mftf.log for details.'
                        . PHP_EOL
                    );
                } else {
                    print(
                        'ERROR:  '
                        . strval($totalAnnotationErrors)
                        . ' '
                        . ucfirst($type)
                        . '(s) has(have) tests with annotation errors or some included tests missing.'
                        . ' See mftf.log for details.'
                        . PHP_EOL
                    );
                }
            }
        }
        print(PHP_EOL);
    }
}
