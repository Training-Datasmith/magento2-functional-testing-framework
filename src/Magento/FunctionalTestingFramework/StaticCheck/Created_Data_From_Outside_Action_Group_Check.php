<?php

declare (strict_types=1);
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Static_Check;

use Exception;
use InvalidArgumentException;
use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Xml_Exception;
use Magento\Functional_Testing_Framework\Util\Script\Script_Util;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Spl_File_Info;
/**
 * Class CreatedDataFromOutsideActionGroupCheck
 * @package Magento\FunctionalTestingFramework\StaticCheck
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Created_Data_From_Outside_Action_Group_Check implements Static_Check_Interface
{
    public const ACTIONGROUP_REGEX_PATTERN = '/\$(\$)*([\w.]+)(\$)*\$/';
    public const ERROR_LOG_FILENAME = 'create-data-from-outside-action-group';
    public const ERROR_MESSAGE = 'Created Data From Outside Action Group';
    /**
     * Array containing all errors found after running the execute() function
     */
    private array $errors = [];
    /**
     * String representing the output summary found after running the execute() function
     *
     * @var string
     */
    private $output;
    /**
     * ScriptUtil instance
     */
    private ?\Magento\Functional_Testing_Framework\Util\Script\Script_Util $script_util = null;
    /**
     * @var array
     */
    private $action_group_xml_file = [];
    /**
     * Checks test dependencies, determined by references in tests versus the dependencies listed in the Magento module
     *
     * @throws Exception
     */
    public function execute(Input_Interface $input): void
    {
        $this->script_util = new Script_Util();
        $this->load_all_xml_files($input);
        $this->errors = [];
        $this->errors += $this->find_reference_errors_in_action_files($this->action_group_xml_file);
        // hold on to the output and print any errors to a file
        $this->output = $this->script_util->print_errors_to_file($this->errors, Static_Checks_List::get_error_files_path() . DIRECTORY_SEPARATOR . self::ERROR_LOG_FILENAME . '.txt', self::ERROR_MESSAGE);
    }
    /**
     * Return array containing all errors found after running the execute() function
     *
     * @return array
     */
    public function get_errors()
    {
        return $this->errors;
    }
    /**
     * Return string of a short human readable result of the check. For example: "No Dependency errors found."
     *
     * @return string
     */
    public function get_output()
    {
        return $this->output;
    }
    /**
     * Read all XML files for scanning
     *
     * @throws Exception
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function load_all_xml_files(\Symfony\Component\Console\Input\Input_Interface $input): void
    {
        $module_paths = [];
        $path = $input->get_option('path');
        if ($path) {
            if (!realpath($path)) {
                throw new InvalidArgumentException('Invalid --path option: ' . $path);
            }
            Mftf_Application_Config::create(true, Mftf_Application_Config::UNIT_TEST_PHASE, false, Mftf_Application_Config::LEVEL_DEFAULT, true);
            $module_paths[] = realpath($path);
        } else {
            $module_paths = $this->script_util->get_all_module_paths();
        }
        // These files can contain references to other entities
        $this->action_group_xml_file = $this->script_util->get_module_xml_files_by_scope($module_paths, 'ActionGroup');
        if (empty($this->action_group_xml_file)) {
            if ($path) {
                throw new InvalidArgumentException('Invalid --path option: ' . $path . PHP_EOL . 'Please make sure --path points to a valid MFTF Test Module.');
            }
            if (empty($this->root_suite_xml_files)) {
                throw new Test_Framework_Exception('No xml file to scan.');
            }
        }
    }
    /**
     * Find reference errors in set of action files
     *
     * @param Finder $files
     * @throws XmlException
     */
    private function find_reference_errors_in_action_files($files): array
    {
        $test_errors = [];
        /** @var SplFileInfo $filePath */
        foreach ($files as $file_path) {
            $contents = file_get_contents($file_path);
            preg_match_all(self::ACTIONGROUP_REGEX_PATTERN, $contents, $action_group_references);
            if (count($action_group_references) > 0) {
                $test_errors = array_merge($test_errors, $this->set_error_output($action_group_references, $file_path));
            }
        }
        return $test_errors;
    }
    /**
     * Build and return error output for violating references
     *
     * @param SplFileInfo $path
     * @return \non-empty-list<\non-falsy-string>[]
     */
    private function set_error_output(array $action_group_references, $path): array
    {
        $test_errors = [];
        $error_output = '';
        $file_path = Static_Checks_List::get_file_path($path->get_real_path());
        foreach ($action_group_references as $action_group_references_data) {
            foreach ($action_group_references_data as $action_group_references_data_result) {
                $error_output .= "\nFile \"{$file_path}\" contains: " . "\n\t \n                {$action_group_references_data_result}  in {$file_path}";
                $test_errors[$file_path][] = $error_output;
            }
        }
        return $test_errors;
    }
}