<?php

declare (strict_types=1);
/**
 * Copyright 2021 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Manifest;

use Magento\Functional_Testing_Framework\Test\Objects\Test_Object;
use Magento\Functional_Testing_Framework\Util\Filesystem\Dir_Setup_Util;
use Magento\Functional_Testing_Framework\Util\Sorter\Parallel_Group_Sorter;
abstract class Base_Parallel_Test_Manifest extends Base_Test_Manifest
{
    /**
     * An associate array of test name to size of test.
     *
     * @var string[]
     */
    protected $test_name_to_size = [];
    /**
     * Class variable to store resulting group config.
     *
     * @var array
     */
    protected $test_groups;
    /**
     * An instance of the group sorter which will take suites and tests organizing them to be run together.
     */
    protected \Magento\Functional_Testing_Framework\Util\Sorter\Parallel_Group_Sorter $parallel_group_sorter;
    /**
     * Path to the directory that will contain all test group files
     */
    protected string $dir_path;
    /**
     * An array of test name count in a single group
     * @var array
     */
    protected $test_counts_to_group = [];
    /**
     * BaseParallelTestManifest constructor.
     *
     * @param array  $suiteConfiguration
     * @param string $runConfig
     * @param string $testPath
     */
    public function __construct($suite_configuration, $run_config, $test_path)
    {
        $this->dir_path = dirname($test_path) . DIRECTORY_SEPARATOR . 'groups';
        $this->parallel_group_sorter = new Parallel_Group_Sorter();
        parent::__construct($test_path, $run_config, $suite_configuration);
    }
    /**
     * Takes a test name and set of tests, records the names in a file for codeception to consume.
     *
     * @param TestObject $testObject
     */
    public function add_test($test_object): void
    {
        $this->test_name_to_size[$test_object->get_codeception_name()] = $test_object->get_estimated_duration();
    }
    /**
     * Function which generates test groups based on arg passed.
     *
     * @param integer $number
     * @return void
     */
    abstract public function create_test_groups($number);
    /**
     * Function which generates the actual manifest once the relevant tests have been added to the array.
     */
    public function generate(): void
    {
        Dir_Setup_Util::create_group_dir($this->dir_path);
        $suites = $this->get_flattened_suite_configuration($this->suite_configuration ?? []);
        foreach ($this->test_groups as $group_number => $group_contents) {
            $this->generate_group_file($group_contents, $group_number, $suites);
        }
        $this->generate_group_summary_file($this->test_counts_to_group);
    }
    /**
     * Function which simply returns the private sorter used by the manifest.
     *
     * @return ParallelGroupSorter
     */
    public function get_sorter()
    {
        return $this->parallel_group_sorter;
    }
    /**
     * Function which takes an array containing entries representing the test execution as well as the associated group
     * for the entry in order to generate a txt file used by devops for parllel execution in Jenkins. The results
     * are checked against a flattened list of suites in order to generate proper entries.
     *
     * @param array   $testGroup
     * @param integer $nodeNumber
     * @return void
     */
    protected function generate_group_file($test_group, $node_number, array $suites)
    {
        foreach ($test_group as $entry_name => $test_value) {
            $file_resource = fopen($this->dir_path . DIRECTORY_SEPARATOR . "group{$node_number}.txt", 'a');
            $this->test_counts_to_group["group{$node_number}"] ??= 0;
            if (!empty($suites[$entry_name])) {
                $line = "-g {$entry_name}";
                $this->test_counts_to_group["group{$node_number}"] += count($suites[$entry_name]);
            } else {
                $line = $this->relative_dir_path . DIRECTORY_SEPARATOR . $entry_name . '.php';
                $this->test_counts_to_group["group{$node_number}"]++;
            }
            fwrite($file_resource, $line . PHP_EOL);
            fclose($file_resource);
        }
    }
    /**
     * @return void
     */
    protected function generate_group_summary_file(array $groups)
    {
        $file_resource = fopen($this->dir_path . DIRECTORY_SEPARATOR . 'mftf_group_summary.txt', 'w');
        $contents = 'Total Number of Groups: ' . count($groups) . PHP_EOL;
        foreach ($groups as $key => $value) {
            $contents .= $key . ' - ' . $value . ' tests' . PHP_EOL;
        }
        fwrite($file_resource, $contents);
        fclose($file_resource);
    }
    /**
     * Function which recusrively parses a given potentially multidimensional array of suites containing their split
     * groups. The result is a flattened array of suite names to relevant tests for generation of the manifest.
     *
     * @param array $multiDimensionalSuites
     * @return array
     */
    protected function get_flattened_suite_configuration($multi_dimensional_suites)
    {
        $suites = [];
        foreach ($multi_dimensional_suites as $suite_name => $suite_content) {
            $value = array_values($suite_content)[0];
            if (is_array($value)) {
                $suites = array_merge($suites, $this->get_flattened_suite_configuration($suite_content));
                continue;
            }
            $suites[$suite_name] = $suite_content;
        }
        return $suites;
    }
}