<?php

declare(strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Extension;

/**
 * Class BrowserLogUtil
 * @package Magento\FunctionalTestingFramework\Extension
 */
class BrowserLogUtil
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
    public static function logErrors($log, $module, $stepEvent): void
    {
        $jsErrors = self::getLogsOfType($log, self::ERROR_TYPE_JAVASCRIPT);
        foreach ($jsErrors as $entry) {
            self::logError(self::ERROR_TYPE_JAVASCRIPT, $stepEvent, $entry);
            //Set javascript error in MagentoWebDriver internal array
            $module->setJsError("ERROR({$entry['level']}) - " . $entry['message']);
        }
    }

    /**
     * Loops through given log and returns entries of the given type.
     *
     * @param array  $log
     * @param string $type
     */
    public static function getLogsOfType($log, $type): array
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
    public static function filterLogsOfType($log, $type): array
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
    private static function logError(string $type, $stepEvent, array $entry): void
    {
        //TODO Add to overall log
        $stepEvent->getTest()->getScenario()->comment("{$type} ERROR({$entry['level']}) - " . $entry['message']);
    }
}
