<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Manifest;

use Magento\Functional_Testing_Framework\Suite\Handlers\Suite_Object_Handler;
use Magento\Functional_Testing_Framework\Test\Objects\Test_Object;
abstract class Base_Test_Manifest
{
    /**
     * Relative dir path from functional yml file. For devOps execution flexibility.
     */
    protected string $relative_dir_path;
    /**
     * TestManifest constructor.
     *
     * @param string $path
     * @param string $runTypeConfig
     * @param array  $suiteConfiguration
     */
    public function __construct(
        $path,
        /**
         * Type of manifest to generate. (Currently describes whether to path to a dir or for each test).
         */
        protected $run_type_config,
        /**
         * Suite configuration in the format suite name to test name. Overwritten during a custom configuration.
         */
        protected $suite_configuration
    )
    {
        $relative_dir_path = substr($path, strlen(TESTS_BP));
        $this->relative_dir_path = ltrim($relative_dir_path, DIRECTORY_SEPARATOR);
    }
    /**
     * Returns a string indicating the generation config (e.g. singleRun).
     *
     * @return string
     */
    public function get_manifest_config()
    {
        return $this->run_type_config;
    }
    /**
     * Takes a test name and set of tests, records the names in a file for codeception to consume.
     *
     * @param TestObject $testObject
     * @return void
     */
    abstract public function add_test($test_object);
    /**
     * Function which generates the actual manifest(s) once the relevant tests have been added to the array.
     *
     * @return void
     */
    abstract public function generate();
    /**
     * Getter for the suite configuration.
     *
     * @return array
     */
    public function get_suite_config()
    {
        if ($this->suite_configuration === null) {
            return [];
        }
        $suite_to_test_names = [];
        if (empty($this->suite_configuration)) {
            // if there is no configuration passed we can assume the user wants all suites generated as specified.
            foreach (Suite_Object_Handler::get_instance()->get_all_objects() as $suite => $suite_obj) {
                $suite_to_test_names[$suite] = array_keys($suite_obj->get_tests());
            }
        } else {
            // we need to loop through the configuration to make sure we capture suites with no specific config
            foreach ($this->suite_configuration as $suite_name => $test) {
                if (empty($test)) {
                    $suite_to_test_names[$suite_name] = array_keys(Suite_Object_Handler::get_instance()->get_object($suite_name)->get_tests());
                    continue;
                }
                $suite_to_test_names[$suite_name] = $test;
            }
        }
        return $suite_to_test_names;
    }
}