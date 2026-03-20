<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Suite\Handlers;

use Magento\Functional_Testing_Framework\Exceptions\Fast_Fail_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Reference_Exception;
use Magento\Functional_Testing_Framework\Object_Manager\Object_Handler_Interface;
use Magento\Functional_Testing_Framework\Object_Manager_Factory;
use Magento\Functional_Testing_Framework\Suite\Objects\Suite_Object;
use Magento\Functional_Testing_Framework\Suite\Parsers\Suite_Data_Parser;
use Magento\Functional_Testing_Framework\Suite\Util\Suite_Object_Extractor;
/**
 * Class SuiteObjectHandler
 */
class Suite_Object_Handler implements Object_Handler_Interface
{
    /**
     * Singleton instance of suite object handler.
     */
    private static ?\Magento\Functional_Testing_Framework\Suite\Handlers\Suite_Object_Handler $instance = null;
    /**
     * Array of suite objects keyed by suite name.
     *
     * @var SuiteObject[]
     */
    private $suite_objects;
    /**
     * Avoids instantiation of SuiteObjectHandler by new.
     */
    private function __construct()
    {
    }
    /**
     * Avoids instantiation of SuiteObjectHandler by clone.
     */
    private function __clone()
    {
    }
    /**
     * Function to enforce singleton design pattern
     *
     * @throws FastFailException
     */
    public static function get_instance(): Object_Handler_Interface
    {
        if (self::$instance === null) {
            self::$instance = new Suite_Object_Handler();
            self::$instance->init_suite_data();
        }
        return self::$instance;
    }
    /**
     * Function to return a single suite object by name
     *
     * @param string $objectName
     */
    public function get_object($object_name): Suite_Object
    {
        if (!array_key_exists($object_name, $this->suite_objects)) {
            throw new Test_Reference_Exception("Suite {$object_name} is not defined in xml or is invalid.");
        }
        return $this->suite_objects[$object_name];
    }
    /**
     * Function to return all objects the handler is responsible for
     */
    public function get_all_objects(): array
    {
        return $this->suite_objects;
    }
    /**
     * Function which return all tests referenced by suites.
     *
     * @throws TestFrameworkException
     */
    public function get_all_test_references(): array
    {
        $tests_referenced_in_suites = [];
        $suites = $this->get_all_objects();
        foreach ($suites as $suite) {
            /** @var SuiteObject $suite */
            $test_keys = array_keys($suite->get_tests());
            $test_to_suite_name = array_fill_keys($test_keys, [$suite->get_name()]);
            $tests_referenced_in_suites = array_merge_recursive($tests_referenced_in_suites, $test_to_suite_name);
        }
        return $tests_referenced_in_suites;
    }
    /**
     * Method to parse all suite data xml into objects.
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     * @throws FastFailException
     */
    private function init_suite_data(): void
    {
        try {
            $suite_data_parser = Object_Manager_Factory::get_object_manager()->create(Suite_Data_Parser::class);
        } catch (\Exception $e) {
            throw new Fast_Fail_Exception('Suite Data Parser Error: ' . $e->get_message());
        }
        $suite_object_extractor = new Suite_Object_Extractor();
        $this->suite_objects = $suite_object_extractor->parse_suite_data_into_objects($suite_data_parser->read_suite_data());
    }
}