<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Suite\Util;

use Exception;
use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Exceptions\Fast_Fail_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Reference_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Xml_Exception;
use Magento\Functional_Testing_Framework\Suite\Objects\Suite_Object;
use Magento\Functional_Testing_Framework\Test\Handlers\Test_Object_Handler;
use Magento\Functional_Testing_Framework\Test\Objects\Test_Object;
use Magento\Functional_Testing_Framework\Test\Util\Base_Object_Extractor;
use Magento\Functional_Testing_Framework\Test\Util\Test_Hook_Object_Extractor;
use Magento\Functional_Testing_Framework\Test\Util\Test_Object_Extractor;
use Magento\Functional_Testing_Framework\Util\Generation_Error_Handler;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
use Magento\Functional_Testing_Framework\Util\Module_Path_Extractor;
use Magento\Functional_Testing_Framework\Util\Validation\Name_Validation_Util;
/**
 * Class SuiteObjectExtractor
 * @package Magento\FunctionalTestingFramework\Suite\Util
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Suite_Object_Extractor extends Base_Object_Extractor
{
    public const SUITE_ROOT_TAG = 'suites';
    public const SUITE_TAG_NAME = 'suite';
    public const INCLUDE_TAG_NAME = 'include';
    public const EXCLUDE_TAG_NAME = 'exclude';
    public const MODULE_TAG_NAME = 'module';
    public const TEST_TAG_NAME = 'test';
    public const GROUP_TAG_NAME = 'group';
    /**
     * TestHookObjectExtractor initialized in constructor.
     */
    private readonly \Magento\Functional_Testing_Framework\Test\Util\Test_Hook_Object_Extractor $test_hook_object_extractor;
    /**
     * SuiteObjectExtractor constructor
     */
    public function __construct()
    {
        $this->test_hook_object_extractor = new Test_Hook_Object_Extractor();
    }
    /**
     * Takes an array of parsed xml and converts into an array of suite objects.
     *
     * @throws FastFailException
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function parse_suite_data_into_objects(array $parsed_suite_data): array
    {
        $suite_objects = [];
        // make sure there are suites defined before trying to parse as objects.
        if (!array_key_exists(self::SUITE_ROOT_TAG, $parsed_suite_data)) {
            return $suite_objects;
        }
        foreach ($parsed_suite_data[self::SUITE_ROOT_TAG] as $parsed_suite) {
            if (!is_array($parsed_suite)) {
                // skip non array items parsed from suite (suite objects will always be arrays)
                continue;
            }
            $this->validate_suite_name($parsed_suite);
            try {
                // extract include and exclude references
                $group_tests_to_include = $parsed_suite[self::INCLUDE_TAG_NAME] ?? [];
                $group_tests_to_exclude = $parsed_suite[self::EXCLUDE_TAG_NAME] ?? [];
                // resolve references as test objects
                // continue if failed in include
                $include = $this->extract_test_objects_from_suite_ref($group_tests_to_include);
                $include_tests = $include['objects'] ?? [];
                $step_error = $include['status'] ?? 0;
                $include_message = '';
                if ($step_error !== 0) {
                    $include_message = 'ERROR: ' . strval($step_error) . ' test(s) not included for suite ' . $parsed_suite[self::NAME];
                }
                // it's ok if failed in exclude
                $exclude = $this->extract_test_objects_from_suite_ref($group_tests_to_exclude);
                $exclude_tests = $exclude['objects'] ?? [];
                // parse any object hooks
                $suite_hooks = $this->parse_object_hooks($parsed_suite);
                // log error if suite is empty
                if ($this->is_suite_empty($suite_hooks, $include_tests, $exclude_tests)) {
                    Logging_Util::get_instance()->get_logger(self::class)->error('Unable to parse suite ' . $parsed_suite[self::NAME] . '. Suite must not be empty.');
                    Generation_Error_Handler::get_instance()->add_error('suite', $parsed_suite[self::NAME], self::class . ': ' . 'Suite must not be empty.');
                    continue;
                }
                // add all test if include tests is completely empty
                if (empty($include_tests)) {
                    $include_tests = Test_Object_Handler::get_instance()->get_all_objects();
                }
                if (!empty($include_message)) {
                    Logging_Util::get_instance()->get_logger(self::class)->error($include_message);
                    if (Mftf_Application_Config::get_config()->verbose_enabled() && Mftf_Application_Config::get_config()->get_phase() === Mftf_Application_Config::GENERATION_PHASE) {
                        print $include_message;
                    }
                    Generation_Error_Handler::get_instance()->add_error('suite', $parsed_suite[self::NAME], self::class . ': ' . $include_message, true);
                }
            } catch (Fast_Fail_Exception $e) {
                throw $e;
            } catch (\Exception $e) {
                Logging_Util::get_instance()->get_logger(self::class)->error('Unable to parse suite ' . $parsed_suite[self::NAME] . "\n" . $e->get_message());
                if (Mftf_Application_Config::get_config()->verbose_enabled() && Mftf_Application_Config::get_config()->get_phase() === Mftf_Application_Config::GENERATION_PHASE) {
                    print 'ERROR: Unable to parse suite ' . $parsed_suite[self::NAME] . "\n";
                }
                Generation_Error_Handler::get_instance()->add_error('suite', $parsed_suite[self::NAME], self::class . ': Unable to parse suite ' . $e->get_message());
                continue;
            }
            // create the new suite object
            $suite_objects[$parsed_suite[self::NAME]] = new Suite_Object($parsed_suite[self::NAME], $include_tests, $exclude_tests, $suite_hooks);
        }
        return $suite_objects;
    }
    /**
     * Throws exception for suite names meeting the below conditions:
     * 1. the name used is using special char or the "default" reserved name
     * 2. collisions between suite name and existing group name
     *
     * @throws FastFailException
     */
    private function validate_suite_name(array $parsed_suite): void
    {
        //check if name used is using special char or the "default" reserved name
        Name_Validation_Util::validate_name($parsed_suite[self::NAME], 'Suite');
        if ($parsed_suite[self::NAME] === 'default') {
            throw new Fast_Fail_Exception('A Suite can not have the name "default"');
        }
        $suite_name = $parsed_suite[self::NAME];
        //check for collisions between suite and existing group names
        $test_group_conflicts = Test_Object_Handler::get_instance()->get_tests_by_group($suite_name);
        if (!empty($test_group_conflicts)) {
            $test_group_conflicts_file_names = '';
            foreach ($test_group_conflicts as $test) {
                $test_group_conflicts_file_names .= $test->get_filename() . "\n";
            }
            $exceptionmessage = "\"Suite names and Group names can not have the same value. \t\n" . "Suite: \"{$suite_name}\" also exists as a group annotation in: \n{$test_group_conflicts_file_names}";
            throw new Fast_Fail_Exception($exceptionmessage);
        }
    }
    /**
     * Parse object hooks
     *
     * @throws XmlException
     * @throws TestReferenceException
     */
    private function parse_object_hooks(array $parsed_suite): array
    {
        $suite_hooks = [];
        if (array_key_exists(Test_Object_Extractor::TEST_BEFORE_HOOK, $parsed_suite)) {
            $hook_object = $this->test_hook_object_extractor->extract_hook($parsed_suite[self::NAME], Test_Object_Extractor::TEST_BEFORE_HOOK, $parsed_suite[Test_Object_Extractor::TEST_BEFORE_HOOK]);
            // Validate hook actions
            $hook_object->get_actions();
            $suite_hooks[Test_Object_Extractor::TEST_BEFORE_HOOK] = $hook_object;
        }
        if (array_key_exists(Test_Object_Extractor::TEST_AFTER_HOOK, $parsed_suite)) {
            $hook_object = $this->test_hook_object_extractor->extract_hook($parsed_suite[self::NAME], Test_Object_Extractor::TEST_AFTER_HOOK, $parsed_suite[Test_Object_Extractor::TEST_AFTER_HOOK]);
            // Validate hook actions
            $hook_object->get_actions();
            $suite_hooks[Test_Object_Extractor::TEST_AFTER_HOOK] = $hook_object;
        }
        if (count($suite_hooks) === 1) {
            throw new Xml_Exception(sprintf("Suites that contain hooks must contain both a 'before' and an 'after' hook. Suite: \"%s\"", $parsed_suite[self::NAME]));
        }
        return $suite_hooks;
    }
    /**
     * Check if suite hooks are empty/not included and there are no included tests/groups/modules
     *
     * @param array $includeTests
     * @param array $excludeTests
     */
    private function is_suite_empty(array $suite_hooks, $include_tests, $exclude_tests): bool
    {
        $no_hooks = count($suite_hooks) === 0 || empty($suite_hooks['before']->get_actions()) && empty($suite_hooks['after']->get_actions());
        if ($no_hooks && empty($include_tests) && empty($exclude_tests)) {
            return true;
        }
        return false;
    }
    /**
     * Wrapper method for resolving suite reference data, checks type of suite reference and calls corresponding
     * resolver for each suite reference.
     *
     * @param array $suiteReferences
     * @throws FastFailException
     */
    private function extract_test_objects_from_suite_ref($suite_references): array
    {
        $test_object_list = [];
        $err_count = 0;
        foreach ($suite_references as $suite_ref_data) {
            if (!is_array($suite_ref_data)) {
                continue;
            }
            try {
                switch ($suite_ref_data[self::NODE_NAME]) {
                    case self::TEST_TAG_NAME:
                        $test_object = Test_Object_Handler::get_instance()->get_object($suite_ref_data[self::NAME]);
                        $test_object_list[$test_object->get_name()] = $test_object;
                        break;
                    case self::GROUP_TAG_NAME:
                        $test_object_list = $test_object_list + Test_Object_Handler::get_instance()->get_tests_by_group($suite_ref_data[self::NAME]);
                        break;
                    case self::MODULE_TAG_NAME:
                        $test_object_list = array_merge($test_object_list, $this->get_tests_by_module_name($suite_ref_data[self::NAME]));
                        break;
                }
            } catch (Fast_Fail_Exception $e) {
                throw $e;
            } catch (\Exception) {
                $err_count++;
                Logging_Util::get_instance()->get_logger(self::class)->error('Unable to find <' . $suite_ref_data[self::NODE_NAME] . '> reference ' . $suite_ref_data[self::NAME] . ' for suite ' . $suite_ref_data[self::NAME]);
            }
        }
        return ['status' => $err_count, 'objects' => $test_object_list];
    }
    /**
     * Return all test objects for a module
     *
     * @param string $moduleName
     * @return TestObject[]
     * @throws Exception
     */
    private function get_tests_by_module_name($module_name): array
    {
        $test_objects = [];
        $path_extractor = new Module_Path_Extractor();
        $all_test_objects = Test_Object_Handler::get_instance()->get_all_objects();
        foreach ($all_test_objects as $test_name => $test_object) {
            /** @var TestObject $testObject */
            $filename = $test_object->get_filename();
            if ($path_extractor->extract_module_name($filename) === $module_name) {
                $test_objects[$test_name] = $test_object;
            }
        }
        return $test_objects;
    }
}