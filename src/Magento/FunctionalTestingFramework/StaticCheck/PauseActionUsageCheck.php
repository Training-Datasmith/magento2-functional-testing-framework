<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Static_Check;

use Exception;
use Magento\Functional_Testing_Framework\Util\Script\Script_Util;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Spl_File_Info;
/**
 * Class PauseActionUsageCheck
 * @package Magento\FunctionalTestingFramework\StaticCheck
 */
class Pause_Action_Usage_Check implements Static_Check_Interface
{
    public const ERROR_LOG_FILENAME = 'mftf-pause-action-usage-checks';
    public const ERROR_LOG_MESSAGE = 'MFTF Pause Action Usage Check';
    /**
     * Array containing all errors found after running the execute() function.
     */
    private array $errors = [];
    /**
     * String representing the output summary found after running the execute() function.
     */
    private ?string $output = null;
    /**
     * ScriptUtil instance
     */
    private ?\Magento\Functional_Testing_Framework\Util\Script\Script_Util $script_util = null;
    /**
     * Test xml files to scan
     *
     * @var Finder|array
     */
    private $test_xml_files = [];
    /**
     * Action group xml files to scan
     *
     * @var Finder|array
     */
    private $action_group_xml_files = [];
    /**
     * Suite xml files to scan
     *
     * @var Finder|array
     */
    private $suite_xml_files = [];
    /**
     * Root suite xml files to scan
     *
     * @var Finder|array
     */
    private $root_suite_xml_files = [];
    /**
     * Checks usage of pause action in action groups, tests and suites and prints out error to file.
     *
     * @throws Exception
     */
    public function execute(Input_Interface $input): void
    {
        $this->script_util = new Script_Util();
        $module_paths = [];
        $include_root_path = true;
        $path = $input->get_option('path');
        if ($path) {
            if (!realpath($path)) {
                throw new \InvalidArgumentException('Invalid --path option: ' . $path);
            }
            $module_paths[] = realpath($path);
            $include_root_path = false;
        } else {
            $module_paths = $this->script_util->get_all_module_paths();
        }
        $this->test_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'Test');
        $this->action_group_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'ActionGroup');
        $this->suite_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'Suite');
        if ($include_root_path) {
            $this->root_suite_xml_files = $this->script_util->get_root_suite_xml_files();
        }
        $this->errors = [];
        $this->errors += $this->validate_pause_action_usage_in_action_groups($this->action_group_xml_files);
        $this->errors += $this->validate_pause_action_usage_in_tests($this->test_xml_files);
        $this->errors += $this->validate_pause_action_usage_in_suites($this->suite_xml_files);
        $this->errors += $this->validate_pause_action_usage_in_suites($this->root_suite_xml_files);
        $this->output = $this->script_util->print_errors_to_file($this->errors, Static_Checks_List::get_error_files_path() . DIRECTORY_SEPARATOR . self::ERROR_LOG_FILENAME . '.txt', self::ERROR_LOG_MESSAGE);
    }
    /**
     * Finds usages of pause action in action group files
     * @param array $actionGroupXmlFiles
     */
    private function validate_pause_action_usage_in_action_groups($action_group_xml_files): array
    {
        $action_group_errors = [];
        foreach ($action_group_xml_files as $file_path) {
            $dom_document = new \Dom_Document();
            $dom_document->load($file_path);
            $action_group = $dom_document->get_elements_by_tag_name('actionGroup')->item(0);
            $violating_step_keys = $this->find_violating_pause_step_keys($action_group);
            $action_group_errors = array_merge($action_group_errors, $this->set_error_output($violating_step_keys, $file_path));
        }
        return $action_group_errors;
    }
    /**
     * Finds usages of pause action in test files
     * @param array $testXmlFiles
     */
    private function validate_pause_action_usage_in_tests($test_xml_files): array
    {
        $test_errors = [];
        foreach ($test_xml_files as $file_path) {
            $dom_document = new \Dom_Document();
            $dom_document->load($file_path);
            $test = $dom_document->get_elements_by_tag_name('test')->item(0);
            $violating_step_keys = $this->find_violating_pause_step_keys($test);
            $test_errors = array_merge($test_errors, $this->set_error_output($violating_step_keys, $file_path));
        }
        return $test_errors;
    }
    /**
     * Finds usages of pause action in suite files
     * @param array $suiteXmlFiles
     */
    private function validate_pause_action_usage_in_suites($suite_xml_files): array
    {
        $suite_errors = [];
        foreach ($suite_xml_files as $file_path) {
            $dom_document = new \Dom_Document();
            $dom_document->load($file_path);
            $suite = $dom_document->get_elements_by_tag_name('suite')->item(0);
            $violating_step_keys = $this->find_violating_pause_step_keys($suite);
            $suite_errors = array_merge($suite_errors, $this->set_error_output($violating_step_keys, $file_path));
        }
        return $suite_errors;
    }
    /**
     * Finds violating pause action step keys
     * @param \DomNode $entity
     */
    private function find_violating_pause_step_keys($entity): array
    {
        $violating_step_keys = [];
        $entity_name = $entity->get_attribute('name');
        $references = $entity->get_elements_by_tag_name('pause');
        foreach ($references as $reference) {
            $pause_step_key = $reference->get_attribute('stepKey');
            $violating_step_keys[$entity_name][] = $pause_step_key;
        }
        return $violating_step_keys;
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
     * Return string of a short human readable result of the check. For example: "No errors found."
     * @return string
     */
    public function get_output()
    {
        return $this->output;
    }
    /**
     * Build and return error output for pause action usages
     *
     * @param array       $violatingReferences
     * @param SplFileInfo $path
     * @return array{non-falsy-string}[]
     */
    private function set_error_output($violating_references, $path): array
    {
        $test_errors = [];
        $file_path = Static_Checks_List::get_file_path($path->get_real_path());
        if (!empty($violating_references)) {
            // Build error output
            $error_output = "\nFile \"{$file_path}\"";
            $error_output .= "\ncontains pause action(s):\n\t\t";
            foreach ($violating_references as $entity_name => $step_key) {
                $error_output .= "\n\t {$entity_name} has pause action at stepKey(s): " . implode(', ', $step_key);
            }
            $test_errors[$file_path][] = $error_output;
        }
        return $test_errors;
    }
}