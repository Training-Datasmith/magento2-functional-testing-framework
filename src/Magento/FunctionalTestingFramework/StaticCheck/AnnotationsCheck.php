<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Static_Check;

use Exception;
use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Test\Handlers\Test_Object_Handler;
use Magento\Functional_Testing_Framework\Test\Objects\Test_Object;
use Magento\Functional_Testing_Framework\Util\Script\Script_Util;
use Symfony\Component\Console\Input\Input_Interface;
/**
 * Class AnnotationsCheck
 * @package Magento\FunctionalTestingFramework\StaticCheck
 */
class Annotations_Check implements Static_Check_Interface
{
    public const ERROR_LOG_FILENAME = 'mftf-annotations-static-check';
    public const ERROR_LOG_MESSAGE = 'MFTF Annotations Static Check';
    /**
     * Array containing all errors found after running the execute() function.
     */
    private array $errors = [];
    /**
     * String representing the output summary found after running the execute() function.
     */
    private ?string $output = null;
    /**
     * Array containing
     *   key = Story appended to Title
     *   value = test names that have that pair
     */
    private array $stories_title_pairs = [];
    /**
     * Array containing
     *   key = testCaseId appended to Title
     *   value = test names that have that pair
     */
    private array $test_case_id_title_pairs = [];
    /**
     * Validates test annotations
     *
     * @throws Exception
     */
    public function execute(Input_Interface $input): void
    {
        // Set MFTF to the UNIT_TEST_PHASE to mute the default DEPRECATION warnings from the TestObjectHandler.
        Mftf_Application_Config::create(true, Mftf_Application_Config::UNIT_TEST_PHASE, false, Mftf_Application_Config::LEVEL_DEFAULT, true);
        $all_tests = Test_Object_Handler::get_instance(false)->get_all_objects();
        foreach ($all_tests as $test) {
            if ($this->validate_skip_issue_id($test)) {
                //if test is skipped ignore other checks
                continue;
            }
            $this->validate_required_annotations($test);
            $this->aggregate_stories_title_pairs($test);
            $this->aggregate_test_case_id_title_pairs($test);
        }
        $this->validate_stories_title_pairs();
        $this->validate_test_case_id_title_pairs();
        $script_util = new Script_Util();
        $this->output = $script_util->print_errors_to_file($this->errors, Static_Checks_List::get_error_files_path() . DIRECTORY_SEPARATOR . self::ERROR_LOG_FILENAME . '.txt', self::ERROR_LOG_MESSAGE);
    }
    /**
     * Return array containing all errors found after running the execute() function.
     * @return array
     */
    public function get_errors()
    {
        return $this->errors;
    }
    /**
     * Return string of a short human readable result of the check. For example: "No Dependency errors found."
     * @return string
     */
    public function get_output()
    {
        return $this->output;
    }
    /**
     * Validates that the test has the following annotations:
     *   stories
     *   title
     *   description
     *   severity
     *
     * @param TestObject $test
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function validate_required_annotations($test): void
    {
        $annotations = $test->get_annotations();
        $missing = [];
        $stories = $annotations['stories'] ?? null;
        if ($stories === null || !isset($stories[0]) || empty(trim($stories[0]))) {
            $missing[] = 'stories';
        }
        $test_case_id = '[NO TESTCASEID]';
        if (isset($annotations['testCaseId'][0])) {
            $test_case_id = trim($annotations['testCaseId'][0]);
        }
        $title = $annotations['title'] ?? null;
        if ($title === null || !isset($title[0]) || empty(trim($title[0])) || empty(trim(substr(trim($title[0]), strlen($test_case_id . ': '))))) {
            $missing[] = 'title';
        }
        $description = $annotations['description']['main'] ?? null;
        if ($description === null || empty(trim($description))) {
            $missing[] = 'description';
        }
        $severity = $annotations['severity'] ?? null;
        if ($severity === null || !isset($severity[0]) || empty(trim($severity[0]))) {
            $missing[] = 'severity';
        }
        $all_missing = join(', ', $missing);
        if (strlen($all_missing) > 0) {
            $this->errors[][] = "Test {$test->get_name()} is missing the required annotations: " . $all_missing;
        }
    }
    /**
     * Validates that if the test is skipped, that it has an issueId value.
     *
     * @param TestObject $test
     * @return boolean
     */
    private function validate_skip_issue_id($test)
    {
        $validate_skipped = false;
        $annotations = $test->get_annotations();
        $skip = $annotations['skip'] ?? null;
        if ($skip !== null) {
            $validate_skipped = true;
            if ((!isset($skip[0]) || strlen($skip[0]) === 0) && (!isset($skip['issueId']) || strlen($skip['issueId']) === 0)) {
                $this->errors[][] = "Test {$test->get_name()} is skipped but the issueId is empty.";
            }
        }
        return $validate_skipped;
    }
    /**
     * Add the key = "stories appended to title", value = test name, to the class variable.
     *
     * @param TestObject $test
     */
    private function aggregate_stories_title_pairs($test): void
    {
        $annotations = $test->get_annotations();
        $stories = $annotations['stories'][0] ?? null;
        $title = $this->get_test_title_without_prefix($test);
        if ($stories !== null && $title !== null) {
            $this->stories_title_pairs[$stories . $title][] = $test->get_name();
        }
    }
    /**
     * Add the key = "testCaseId appended to title", value = test name, to the class variable.
     *
     * @param TestObject $test
     */
    private function aggregate_test_case_id_title_pairs($test): void
    {
        $annotations = $test->get_annotations();
        $test_case_id = $annotations['testCaseId'][0] ?? null;
        $title = $this->get_test_title_without_prefix($test);
        if ($test_case_id !== null && $title !== null) {
            $this->test_case_id_title_pairs[$test_case_id . $title][] = $test->get_name();
        }
    }
    /**
     * Strip away the testCaseId prefix that was automatically added to the test title
     * so that way we have just the raw title from the XML file.
     *
     * @param TestObject $test
     */
    private function get_test_title_without_prefix($test): ?string
    {
        $annotations = $test->get_annotations();
        $title = $annotations['title'][0] ?? null;
        if ($title === null) {
            return null;
        }
        $test_case_id = $annotations['testCaseId'][0] ?? '[NO TESTCASEID]';
        return substr($title, strlen($test_case_id . ': '));
    }
    /**
     * Adds an error if any story+title pairs are used by more than one test.
     */
    private function validate_stories_title_pairs(): void
    {
        foreach ($this->stories_title_pairs as $pair) {
            if (sizeof($pair) > 1) {
                $this->errors[][] = 'Stories + title combination must be unique: ' . join(', ', $pair);
            }
        }
    }
    /**
     * Adds an error if any testCaseId+title pairs are used by more than one test.
     */
    private function validate_test_case_id_title_pairs(): void
    {
        foreach ($this->test_case_id_title_pairs as $pair) {
            if (sizeof($pair) > 1) {
                $this->errors[][] = 'testCaseId + title combination must be unique: ' . join(', ', $pair);
            }
        }
    }
}