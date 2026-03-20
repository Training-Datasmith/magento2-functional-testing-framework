<?php

declare (strict_types=1);
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Static_Check;

use Exception;
use Magento\Functional_Testing_Framework\Util\Script\Script_Util;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Finder\Spl_File_Info;
/**
 * Class ClassFileNamingCheck
 * @package Magento\FunctionalTestingFramework\StaticCheck
 */
class Class_File_Naming_Check implements Static_Check_Interface
{
    public const ERROR_LOG_FILENAME = 'mftf-class-file-naming-check';
    public const ERROR_LOG_MESSAGE = 'MFTF Class File Naming Check';
    public const ALLOW_LIST_FILENAME = 'class-file-naming-allowlist';
    public const WARNING_LOG_FILENAME = 'mftf-class-file-naming-warnings';
    /**
     * Array containing all warnings found after running the execute() function.
     */
    private array $warnings = [];
    /**
     * Array containing all errors found after running the execute() function.
     */
    private array $errors = [];
    /**
     * String representing the output summary found after running the execute() function.
     */
    private ?string $output = null;
    private array $allow_failure_entities = [];
    /**
     * ScriptUtil instance
     */
    private ?\Magento\Functional_Testing_Framework\Util\Script\Script_Util $script_util = null;
    /**
     * Checks usage of pause action in action groups, tests and suites and prints out error to file.
     *
     * @throws Exception
     */
    public function execute(Input_Interface $input): void
    {
        $this->script_util = new Script_Util();
        $module_paths = [];
        $path = $input->get_option('path');
        if ($path) {
            if (!realpath($path)) {
                throw new \InvalidArgumentException('Invalid --path option: ' . $path);
            }
            $module_paths[] = realpath($path);
        } else {
            $module_paths = $this->script_util->get_all_module_paths();
        }
        foreach ($module_paths as $module_path) {
            if (file_exists($module_path . DIRECTORY_SEPARATOR . self::ALLOW_LIST_FILENAME)) {
                $contents = file_get_contents($module_path . DIRECTORY_SEPARATOR . self::ALLOW_LIST_FILENAME);
                foreach (explode("\n", $contents) as $entity) {
                    $this->allow_failure_entities[$entity] = true;
                }
            }
        }
        $test_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'Test');
        $action_group_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'ActionGroup');
        $page_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'Page');
        $section_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'Section');
        $suite_xml_files = $this->script_util->get_module_xml_files_by_scope($module_paths, 'Suite');
        $this->errors = [];
        $this->errors += $this->find_errors_in_file_set($test_xml_files, 'test');
        $this->errors += $this->find_errors_in_file_set($action_group_xml_files, 'actionGroup');
        $this->errors += $this->find_errors_in_file_set($page_xml_files, 'page');
        $this->errors += $this->find_errors_in_file_set($section_xml_files, 'section');
        $this->errors += $this->find_errors_in_file_set($suite_xml_files, 'suite');
        // hold on to the output and print any errors to a file
        $this->output = $this->script_util->print_errors_to_file($this->errors, Static_Checks_List::get_error_files_path() . DIRECTORY_SEPARATOR . self::ERROR_LOG_FILENAME . '.txt', self::ERROR_LOG_MESSAGE);
        if (!empty($this->warnings) && !empty($this->errors)) {
            $this->output .= "\n " . $this->script_util->print_warnings_to_file($this->warnings, Static_Checks_List::get_error_files_path() . DIRECTORY_SEPARATOR . self::WARNING_LOG_FILENAME . '.txt', self::ERROR_LOG_MESSAGE);
        }
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
     * Returns Violations if found
     * @param  SplFileInfo $files
     * @param  string      $fileType
     */
    public function find_errors_in_file_set($files, $file_type): array
    {
        $errors = [];
        /** @var SplFileInfo $filePath */
        foreach ($files as $file_path) {
            $file_name_without_extension = pathinfo($file_path->get_filename(), PATHINFO_FILENAME);
            $dom_document = new \Dom_Document();
            $dom_document->load($file_path);
            $test_result = $this->get_attributes_from_dom_node_list($dom_document->get_elements_by_tag_name($file_type), ['type' => 'name']);
            if ($file_name_without_extension != array_values($test_result[0])[0]) {
                $is_in_allow_list = array_key_exists(array_values($test_result[0])[0], $this->allow_failure_entities);
                if ($is_in_allow_list) {
                    $error_output = ucfirst($file_type) . " name does not match with file name \n                    {$file_path->get_real_path()}. " . ucfirst($file_type) . ' ' . array_values($test_result[0])[0];
                    $this->warnings[$file_path->get_filename()][] = $error_output;
                    continue;
                }
                $error_output = ucfirst($file_type) . " name does not match with file name \n                    {$file_path->get_real_path()}. " . ucfirst($file_type) . ' ' . array_values($test_result[0])[0];
                $errors[$file_path->get_filename()][] = $error_output;
            }
        }
        return $errors;
    }
    /**
     * Return attribute value for each node in DOMNodeList as an array
     *
     * @param  DOMNodeList $nodes
     * @param  string      $attributeName
     */
    public function get_attributes_from_dom_node_list($nodes, $attribute_name): array
    {
        $attributes = [];
        foreach ($nodes as $node) {
            if (is_string($attribute_name)) {
                $attribute_value = $node->get_attribute($attribute_name);
            } else {
                $attribute_value = [$node->get_attribute(key($attribute_name)) => $node->get_attribute($attribute_name[key($attribute_name)])];
            }
            if (!empty($attribute_value)) {
                $attributes[] = $attribute_value;
            }
        }
        return $attributes;
    }
}