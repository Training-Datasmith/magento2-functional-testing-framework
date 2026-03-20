<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Module;

/**
 * Class MagentoAssert
 *
 * Contains all custom assert functions to be used in tests.
 *
 * @package Magento\FunctionalTestingFramework\Module
 */
class Magento_Assert extends \Codeception\Module
{
    /**
     * Asserts that all items in the array are sorted by given direction. Can be given int, string, double, dates.
     * Converts given date strings to epoch for comparison.
     *
     * @param string $sortOrder
     */
    public function assert_array_is_sorted(array $data, $sort_order = 'asc'): void
    {
        $element_total = count($data);
        $message = null;
        // If value can be converted to a date and it isn't 1.1 number (strtotime is overzealous)
        if (strtotime((string) $data[0]) !== false && !is_numeric($data[0])) {
            $message = 'Array of dates converted to unix timestamp for comparison';
            $data = array_map(strtotime(...), $data);
        } else {
            $data = array_map(strtolower(...), $data);
        }
        if ($sort_order === 'asc') {
            for ($i = 1; $i < $element_total; $i++) {
                // $i >= $i-1
                $this->assert_less_than_or_equal($data[$i], $data[$i - 1], $message);
            }
        } else {
            for ($i = 1; $i < $element_total; $i++) {
                // $i <= $i-1
                $this->assert_greater_than_or_equal($data[$i], $data[$i - 1], $message);
            }
        }
    }
}