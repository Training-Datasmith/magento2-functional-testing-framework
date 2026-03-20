<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Suite;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Exceptions\Collector\Exception_Collector;
use Magento\Functional_Testing_Framework\Exceptions\Fast_Fail_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Reference_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Xml_Exception;
use Magento\Functional_Testing_Framework\Suite\Generators\Group_Class_Generator;
use Magento\Functional_Testing_Framework\Suite\Handlers\Suite_Object_Handler;
use Magento\Functional_Testing_Framework\Suite\Objects\Suite_Object;
use Magento\Functional_Testing_Framework\Suite\Service\Suite_Generator_Service;
use Magento\Functional_Testing_Framework\Test\Handlers\Test_Object_Handler;
use Magento\Functional_Testing_Framework\Util\Filesystem\Dir_Setup_Util;
use Magento\Functional_Testing_Framework\Util\Generation_Error_Handler;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
use Magento\Functional_Testing_Framework\Util\Manifest\Base_Test_Manifest;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
use Magento\Functional_Testing_Framework\Util\Test_Generator;
/**
 * Class SuiteGenerator
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Suite_Generator
{
    public const YAML_CODECEPTION_DIST_FILENAME = 'codeception.dist.yml';
    public const YAML_CODECEPTION_CONFIG_FILENAME = 'codeception.yml';
    public const YAML_GROUPS_TAG = 'groups';
    public const YAML_EXTENSIONS_TAG = 'extensions';
    public const YAML_ENABLED_TAG = 'enabled';
    public const YAML_COPYRIGHT_TEXT = "# Copyright © Magento, Inc. All rights reserved.\n# See COPYING.txt for license details.\n";
    /**
     * Singelton Variable Instance.
     */
    private static ?\Magento\Functional_Testing_Framework\Suite\Suite_Generator $instance = null;
    /**
     * Group Class Generator initialized in constructor.
     */
    private readonly \Magento\Functional_Testing_Framework\Suite\Generators\Group_Class_Generator $group_class_generator;
    /**
     * Avoids instantiation of LoggingUtil by new.
     */
    private function __construct()
    {
        $this->group_class_generator = new Group_Class_Generator();
    }
    /**
     * Avoids instantiation of SuiteGenerator by clone.
     */
    private function __clone()
    {
    }
    /**
     * Singleton method which is used to retrieve the instance of the suite generator.
     */
    public static function get_instance(): Suite_Generator
    {
        if (!self::$instance) {
            // clear any previous configurations before any generation occurs.
            self::clear_previous_group_preconditions();
            self::clear_previous_session_config_entries();
            self::$instance = new Suite_Generator();
        }
        return self::$instance;
    }
    /**
     * Function which takes all suite configurations and generates to appropriate directory, updating yml configuration
     * as needed. Returns an array of all tests generated keyed by test name.
     *
     * @param BaseTestManifest $testManifest
     * @throws FastFailException
     */
    public function generate_all_suites($test_manifest): void
    {
        $suites = $test_manifest->get_suite_config();
        foreach ($suites as $suite_name => $suite_content) {
            try {
                if (empty($suite_content)) {
                    Logging_Util::get_instance()->get_logger(self::class)->notification("Suite '" . $suite_name . "' contains no tests and won't be generated.", [], true);
                    continue;
                }
                $first_element = array_values($suite_content)[0];
                // if the first element is a string we know that we simply have an array of tests
                if (is_string($first_element)) {
                    $this->generate_suite_from_test($suite_name, $suite_content);
                }
                // if our first element is an array we know that we have split the suites
                if (is_array($first_element)) {
                    $this->generate_split_suite_from_test($suite_name, $suite_content);
                }
            } catch (Fast_Fail_Exception $e) {
                throw $e;
            } catch (\Exception) {
            }
        }
    }
    /**
     * Function which takes a suite name and generates corresponding dir, test files, group class, and updates
     * yml configuration for group run.
     *
     * @param string $suiteName
     * @throws \Exception
     */
    public function generate_suite($suite_name): void
    {
        /**@var SuiteObject $suite **/
        $this->generate_suite_from_test($suite_name, []);
    }
    /**
     * Function which generate Testgroupmembership file.
     *
     * @param object $testManifest
     * @throws \Exception
     */
    public function generate_testgroupmembership($test_manifest): void
    {
        $suites = $this->get_suites_details($test_manifest);
        // Path to groups folder
        $base_dir = File_Path_Formatter::format(TESTS_MODULE_PATH);
        $path = $base_dir . '_generated/groups';
        $all_groups_content = $this->read_all_group_files($path);
        // Output file path
        $member_ship_file_path = $base_dir . '_generated/testgroupmembership.txt';
        $test_case_number = 0;
        foreach ($all_groups_content as $group_id => $group_info) {
            foreach ($group_info as $test_name) {
                // If file has -g then it is test suite
                if (str_contains((string) $test_name, '-g')) {
                    $suitename = explode(' ', (string) $test_name);
                    $suitename[1] = trim($suitename[1]);
                    if (!empty($suites[$suitename[1]])) {
                        foreach ($suites[$suitename[1]] as $key => $test) {
                            $suite_test = sprintf('%s:%s:%s:%s', $group_id, $key, $suitename[1], $test);
                            file_put_contents($member_ship_file_path, $suite_test . PHP_EOL, FILE_APPEND);
                        }
                    }
                } else {
                    $default_suite_test = sprintf('%s:%s:%s', $group_id, $test_case_number, $test_name);
                    file_put_contents($member_ship_file_path, $default_suite_test, FILE_APPEND);
                }
                $test_case_number++;
            }
            $test_case_number = 0;
        }
    }
    /**
     * Function to format suites details
     *
     * @param object $testManifest
     * @return array $suites
     */
    private function get_suites_details($test_manifest): array
    {
        // Get suits and subsuites data array
        $suites = $test_manifest->get_suite_config();
        // Add subsuites array[2nd dimension] to main array[1st dimension] to access it directly later
        if (!empty($suites)) {
            foreach ($suites as $sub_suites) {
                if (!empty($sub_suites)) {
                    foreach ($sub_suites as $sub_suite_name => $suite_test_names) {
                        if (!is_numeric($sub_suite_name)) {
                            $suites[$sub_suite_name] = $suite_test_names;
                        } else {
                            continue;
                        }
                    }
                }
            }
        }
        return $suites;
    }
    /**
     * Function to read all group* text files inside /groups folder
     *
     * @param object $path
     * @return array $allGroupsContent
     */
    private function read_all_group_files(string $path): array
    {
        // Read all group files
        if (is_dir($path)) {
            $group_files = glob("{$path}/group*.txt");
            if ($group_files === false) {
                throw new RuntimeException("glob(): error with '{$path}'");
            }
            sort($group_files, SORT_NATURAL);
        }
        // Read each file in the reverse order and form an array with groupId as key
        $group_number = 0;
        $all_groups_content = [];
        while (!empty($group_files)) {
            $group = array_pop($group_files);
            $all_groups_content[$group_number] = file($group);
            $group_number++;
        }
        return $all_groups_content;
    }
    /**
     * Function which takes a suite name and a set of test names. The function then generates all relevant supporting
     * files and classes for the suite. The function takes an optional argument for suites which are split by a parallel
     * run so that any pre/post conditions can be duplicated.
     *
     * @param array  $tests
     * @param string $originalSuiteName
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function generate_suite_from_test(string $suite_name, $tests = [], $original_suite_name = null): void
    {
        $relative_path = Test_Generator::GENERATED_DIR . DIRECTORY_SEPARATOR . $suite_name;
        $full_path = File_Path_Formatter::format(TESTS_MODULE_PATH) . $relative_path . DIRECTORY_SEPARATOR;
        Dir_Setup_Util::create_group_dir($full_path);
        $exception_collector = new Exception_Collector();
        try {
            $relevant_tests = [];
            if (!empty($tests)) {
                $this->validate_tests_referenced_in_suite($suite_name, $tests, $original_suite_name);
                foreach ($tests as $test_name) {
                    try {
                        $relevant_tests[$test_name] = Test_Object_Handler::get_instance()->get_object($test_name);
                    } catch (Fast_Fail_Exception $e) {
                        throw $e;
                    } catch (\Exception) {
                        $exception_collector->add_error(self::class, "Unable to find relevant test \"{$test_name}\" for suite \"{$suite_name}\"");
                    }
                }
            } else {
                $relevant_tests = Suite_Object_Handler::get_instance()->get_object($suite_name)->get_tests();
            }
            if (empty($relevant_tests)) {
                $exception_collector->reset();
                // There are suites that include no test on purpose for certain Magento edition.
                // To keep backward compatibility, we will return with no error.
                // This might inevitably hide some suite errors that are resulted by real broken tests.
                if (file_exists($full_path)) {
                    Dir_Setup_Util::rmdir_recursive($full_path);
                }
                return;
            }
            try {
                $this->generate_relevant_group_tests($suite_name, $relevant_tests);
            } catch (Fast_Fail_Exception $e) {
                throw $e;
            } catch (\Exception $e) {
                $exception_collector->add_error(self::class, "Failed to generate tests for suite \"{$suite_name}\"");
            }
            $group_namespace = $this->generate_group_file($suite_name, $relevant_tests, $original_suite_name);
            $this->append_entries_to_config($suite_name, $full_path, $group_namespace);
            if (Mftf_Application_Config::get_config()->verbose_enabled() && Mftf_Application_Config::get_config()->get_phase() === Mftf_Application_Config::GENERATION_PHASE) {
                print "suite {$suite_name} generated\n";
            }
            Logging_Util::get_instance()->get_logger(self::class)->info('suite generated', ['suite' => $suite_name, 'relative_path' => $relative_path]);
        } catch (Fast_Fail_Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            if (file_exists($full_path)) {
                Dir_Setup_Util::rmdir_recursive($full_path);
            }
            $exception_collector->add_error(self::class, $e->get_message());
            Generation_Error_Handler::get_instance()->add_error('suite', $suite_name, self::class . ': ' . $e->get_message());
        }
        $this->throw_collected_exceptions($exception_collector);
    }
    /**
     * Function which validates tests passed in as custom configuration against the configuration defined by the user to
     * prevent possible invalid test configurations from executing.
     *
     * @param array  $testsReferenced
     * @param string $originalSuiteName
     * @throws TestReferenceException
     * @throws XmlException
     */
    private function validate_tests_referenced_in_suite(string $suite_name, $tests_referenced, $original_suite_name): void
    {
        $suite_ref = $original_suite_name ?? $suite_name;
        $possible_test_ref = Suite_Object_Handler::get_instance()->get_object($suite_ref)->get_tests();
        $error_msg = 'Cannot reference tests which are not declared as part of suite';
        $invalid_test_ref = array_diff($tests_referenced, array_keys($possible_test_ref));
        if (!empty($invalid_test_ref)) {
            $test_list = implode('", "', $invalid_test_ref);
            $full_error = $error_msg . " (Suite: \"{$suite_ref}\" Tests: \"{$test_list}\")";
            throw new Test_Reference_Exception($full_error, ['suite' => $suite_ref, 'test' => $invalid_test_ref]);
        }
    }
    /**
     * Function for generating split groups of tests (following a parallel execution). Takes a paralle suite config
     * and generates applicable suites.
     *
     * @param string $suiteName
     * @param array  $suiteContent
     * @throws \Exception
     */
    private function generate_split_suite_from_test($suite_name, $suite_content): void
    {
        foreach ($suite_content as $suite_split_name => $tests) {
            try {
                $this->generate_suite_from_test($suite_split_name, $tests, $suite_name);
            } catch (Fast_Fail_Exception $e) {
                throw $e;
            } catch (\Exception) {
                // There are suites that include tests that reference tests from other Magento editions
                // To keep backward compatibility, we will catch such exceptions with no error.
                // This might inevitably hide some suite errors that are resulted by tests with broken references
                //TODO MQE-2484
            }
        }
    }
    /**
     * Function which takes a suite name, array of tests, and an original suite name. The function takes these args
     * and generates a group file which captures suite level preconditions.
     *
     * @param string $suiteName
     * @param array  $tests
     * @param string $originalSuiteName
     * @return null|string
     * @throws XmlException
     * @throws TestReferenceException
     */
    private function generate_group_file($suite_name, $tests, $original_suite_name)
    {
        // if there's an original suite name we know that this test came from a split group.
        if ($original_suite_name) {
            // create the new suite object
            /** @var SuiteObject $originalSuite */
            $original_suite = Suite_Object_Handler::get_instance()->get_object($original_suite_name);
            $suite_object = new Suite_Object($suite_name, $tests, [], $original_suite->get_hooks());
        } else {
            $suite_object = Suite_Object_Handler::get_instance()->get_object($suite_name);
            // we have to handle the case when there is a custom configuration for an existing suite.
            if (count($suite_object->get_tests()) !== count($tests)) {
                return $this->generate_group_file($suite_name, $tests, $suite_name);
            }
        }
        if (!$suite_object->requires_group_file()) {
            // if we do not require a group file we don't need a namespace
            return null;
        }
        // if the suite requires a group file, generate it and set the namespace
        return $this->group_class_generator->generate_group_class($suite_object);
    }
    /**
     * Function which accepts a suite name and suite path and appends a new group entry to the codeception.yml.dist
     * file in order to register the set of tests as a new group. Also appends group object location if required
     * by suite.
     *
     * @param string $groupNamespace
     */
    private function append_entries_to_config(string $suite_name, string $suite_path, ?string $group_namespace): void
    {
        Suite_Generator_Service::get_instance()->append_entries_to_config($suite_name, $suite_path, $group_namespace);
    }
    /**
     * Function which takes the current config.yml array and clears any previous configuration for suite group object
     * files.
     */
    private static function clear_previous_session_config_entries(): void
    {
        Suite_Generator_Service::get_instance()->clear_previous_session_config_entries();
    }
    /**
     * Function which takes a string which is the desired output directory (under _generated) and an array of tests
     * relevant to the suite to be generated. The function takes this information and creates a new instance of the
     * test generator which is then called to create all the test files for the suite.
     *
     *
     * @throws TestReferenceException
     */
    private function generate_relevant_group_tests(string $path, array $tests): void
    {
        Suite_Generator_Service::get_instance()->generate_relevant_group_tests($path, $tests);
    }
    /**
     * Function which on first execution deletes all generate php in the MFTF Group directory
     */
    private static function clear_previous_group_preconditions(): void
    {
        $group_file_path = Group_Class_Generator::get_group_dir_path();
        array_map(unlink(...), glob("{$group_file_path}*.php"));
    }
    /**
     * Log error and throw collected exceptions
     *
     * @throws \Exception
     */
    private function throw_collected_exceptions(\Magento\Functional_Testing_Framework\Exceptions\Collector\Exception_Collector $exception_collector): void
    {
        if (!empty($exception_collector->get_errors())) {
            foreach ($exception_collector->get_errors() as $error_message) {
                if (is_array($error_message)) {
                    foreach (array_unique($error_message) as $message) {
                        Logging_Util::get_instance()->get_logger(self::class)->error(trim((string) $message));
                    }
                } else {
                    Logging_Util::get_instance()->get_logger(self::class)->error(trim((string) $error_message));
                }
            }
            $exception_collector->throw_exception();
        }
    }
}