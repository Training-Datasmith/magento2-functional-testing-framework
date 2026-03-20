<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Suite\Objects;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Filter\Filter_Interface;
use Magento\Functional_Testing_Framework\Test\Objects\Test_Hook_Object;
use Magento\Functional_Testing_Framework\Test\Objects\Test_Object;
/**
 * Class SuiteObject
 */
class Suite_Object
{
    /**
     * SuiteObject constructor.
     * @param string           $name
     * @param TestObject[]     $includeTests
     * @param TestObject[]     $excludeTests
     * @param TestHookObject[] $hooks
     * @param string           $filename
     */
    public function __construct(
        /**
         * Name of the Suite.
         */
        private $name,
        /**
         * Array of Tests to include for the suite.
         */
        private $include_tests,
        /**
         * Array of Tests to exclude for the suite.
         */
        private $exclude_tests,
        /**
         * Array of before/after hooks to be executed for a suite.
         */
        private $hooks,
        /**
         * Filename of where the suite came from
         */
        private $filename = null
    )
    {
    }
    /**
     * Getter for suite name.
     *
     * @return string
     */
    public function get_name()
    {
        return $this->name;
    }
    /**
     * Returns an array of Test Objects based on specifications in exclude and include arrays.
     *
     * @return array
     * @throws TestFrameworkException
     */
    public function get_tests()
    {
        return $this->resolve_tests($this->include_tests, $this->exclude_tests);
    }
    /**
     * Takes an array of Test Objects to include and an array of Test Objects to exlucde. Loops through each Test
     * and determines any overlapping tests. Returns a resulting array of Test Objects based on this logic. Exclusion is
     * preferred to exclusiong (i.e. a test is specified in both include and exclude, it will be excluded).
     *
     * @param TestObject[] $includeTests
     * @param TestObject[] $excludeTests
     * @return TestObject[]
     * @throws TestFrameworkException
     */
    private function resolve_tests($include_tests, $exclude_tests)
    {
        $final_test_list = $include_tests;
        $matching_tests = array_intersect(array_keys($include_tests), array_keys($exclude_tests));
        // filter out tests for exclusion here
        foreach ($matching_tests as $test_name) {
            unset($final_test_list[$test_name]);
        }
        $filters = Mftf_Application_Config::get_config()->get_filter_list()->get_filters();
        /** @var FilterInterface $filter */
        foreach ($filters as $filter) {
            $filter->filter($final_test_list);
        }
        return $final_test_list;
    }
    /**
     * Convenience method for determining if a Suite will require group file generation.
     * A group file will only be generated when the user specifies a before/after statement.
     */
    public function requires_group_file(): bool
    {
        return !empty($this->hooks);
    }
    /**
     * Getter for the Hook Array which contains the before/after objects.
     *
     * @return TestHookObject[]
     */
    public function get_hooks()
    {
        return $this->hooks;
    }
    /**
     * Getter for before hooks.
     *
     * @return TestHookObject
     */
    public function get_before_hook()
    {
        return $this->hooks['before'] ?? null;
    }
    /**
     * Getter for after hooks.
     *
     * @return TestHookObject
     */
    public function get_after_hook()
    {
        return $this->hooks['after'] ?? null;
    }
    /**
     * Getter for the Suite Filename
     *
     * @return string
     */
    public function get_filename()
    {
        return $this->filename;
    }
}