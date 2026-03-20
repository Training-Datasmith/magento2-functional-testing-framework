<?php

declare (strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Extension;

/**
 * Class BrowserLogUtil
 * @package Magento\FunctionalTestingFramework\Extension
 */
class Browser_Log_Util
{
    public const LOG_TYPE_BROWSER = 'browser';
    public const ERROR_TYPE_JAVASCRIPT = 'javascript';
    /**
     * Loops throw errors in log and logs them to allure. Uses Module to set the error itself
     *
     * @param array                         $log
     * @param \Codeception\Module\WebDriver $module
     * @param \Codeception\Event\StepEvent  $stepEvent
     */
    public static function log_errors($log, $module, $step_event): void
    {
        $js_errors = self::get_logs_of_type($log, self::ERROR_TYPE_JAVASCRIPT);
        foreach ($js_errors as $entry) {
            self::log_error(self::ERROR_TYPE_JAVASCRIPT, $step_event, $entry);
            //Set javascript error in MagentoWebDriver internal array
            $module->set_js_error("ERROR({$entry['level']}) - " . $entry['message']);
        }
    }
    /**
     * Loops through given log and returns entries of the given type.
     *
     * @param array  $log
     * @param string $type
     */
    public static function get_logs_of_type($log, $type): array
    {
        $errors = [];
        foreach ($log as $entry) {
            if (array_key_exists('source', $entry) && $entry['source'] === $type) {
                $errors[] = $entry;
            }
        }
        return $errors;
    }
    /**
     * Loops through given log and filters entries of the given type.
     *
     * @param array  $log
     * @param string $type
     */
    public static function filter_logs_of_type($log, $type): array
    {
        $errors = [];
        foreach ($log as $entry) {
            if (array_key_exists('source', $entry) && $entry['source'] !== $type) {
                $errors[] = $entry;
            }
        }
        return $errors;
    }
    /**
     * Logs errors to console/report.
     * @param \Codeception\Event\StepEvent $stepEvent
     */
    private static function log_error(string $type, $step_event, array $entry): void
    {
        //TODO Add to overall log
        $step_event->get_test()->get_scenario()->comment("{$type} ERROR({$entry['level']}) - " . $entry['message']);
    }
}