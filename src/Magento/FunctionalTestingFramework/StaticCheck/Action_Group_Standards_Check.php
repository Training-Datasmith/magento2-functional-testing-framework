<?php

declare (strict_types=1);
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Static_Check;

use Dom_Element;
use Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Test\Handlers\Action_Group_Object_Handler;
use Magento\Functional_Testing_Framework\Test\Objects\Action_Group_Object;
use Magento\Functional_Testing_Framework\Util\Script\Script_Util;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Spl_File_Info;
/**
 * Class ActionGroupArgumentsCheck
 * @package Magento\FunctionalTestingFramework\StaticCheck
 */
class Action_Group_Standards_Check implements Static_Check_Interface
{
    public const ACTIONGROUP_NAME_REGEX_PATTERN = '/<actionGroup name=["\']([^\'"]*)/';
    public const ERROR_LOG_FILENAME = 'mftf-standards-checks';
    public const ERROR_LOG_MESSAGE = 'MFTF Action Group Unused Arguments Check';
    public const STEP_KEY_REGEX_PATTERN = '/stepKey=["\']([^\'"]*)/';
    /**
     * Array containing all errors found after running the execute() function.
     * @var array
     */
    private $errors = [];
    /**
     * String representing the output summary found after running the execute() function.
     */
    private ?string $output = null;
    /**
     * ScriptUtil instance
     */
    private ?\Magento\Functional_Testing_Framework\Util\Script\Script_Util $script_util = null;
    /**
     * Checks unused arguments in action groups and prints out error to file.
     *
     * @throws Exception
     */
    public function execute(Input_Interface $input): void
    {
        $this->script_util = new Script_Util();
        $all_modules = $this->script_util->get_all_module_paths();
        $action_group_xml_files = $this->script_util->get_module_xml_files_by_scope($all_modules, DIRECTORY_SEPARATOR . 'ActionGroup' . DIRECTORY_SEPARATOR);
        $this->errors = $this->find_errors_in_file_set($action_group_xml_files);
        $this->output = $this->script_util->print_errors_to_file($this->errors, Static_Checks_List::get_error_files_path() . DIRECTORY_SEPARATOR . self::ERROR_LOG_FILENAME . '.txt', self::ERROR_LOG_MESSAGE);
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
     * Return string of a short human readable result of the check. For example: "No unused arguments found."
     * @return string
     */
    public function get_output()
    {
        return $this->output;
    }
    /**
     * Finds all unused arguments in given set of actionGroup files
     * @param Finder $files
     * @return array $testErrors
     */
    private function find_errors_in_file_set($files): array
    {
        $action_group_errors = [];
        /** @var SplFileInfo $filePath */
        foreach ($files as $file_path) {
            $action_group_references_data_array = [];
            $action_group_to_arguments = [];
            $contents = $file_path->get_contents();
            preg_match_all(self::STEP_KEY_REGEX_PATTERN, (string) preg_replace('/<!--(.|\s)*?-->/', '', $contents), $action_group_references);
            foreach ($action_group_references[0] as $action_group_references_data) {
                $action_group_references_data_array[] = trim(str_replace(['stepKey', '='], [''], $action_group_references_data)) . '"';
            }
            $duplicate_step_keys = array_unique(array_diff_assoc($action_group_references_data_array, array_unique($action_group_references_data_array)));
            unset($action_group_references_data_array);
            if (count($duplicate_step_keys) > 0) {
                throw new Test_Framework_Exception('Action group has duplicate step keys ' . implode(',', array_unique($duplicate_step_keys)) . ' File Path ' . $file_path);
            }
            /** @var DOMElement $actionGroup */
            $action_group = $this->get_action_group_dom_element($contents);
            $arguments = $this->extract_action_group_arguments($action_group);
            $unused_arguments = $this->find_unused_arguments($arguments, $contents);
            if (!empty($unused_arguments)) {
                $action_group_to_arguments[$action_group->get_attribute('name')] = $unused_arguments;
                $action_group_errors += $this->set_error_output($action_group_to_arguments, $file_path);
            }
        }
        return $action_group_errors;
    }
    /**
     * Extract actionGroup DomElement from xml file
     * @param string $contents
     * @return \DOMElement
     */
    public function get_action_group_dom_element($contents)
    {
        $dom_document = new \Dom_Document();
        $dom_document->load_xml($contents);
        return $dom_document->get_elements_by_tag_name('actionGroup')[0];
    }
    /**
     * Get list of action group arguments declared in an action group
     * @param \DOMElement $actionGroup
     * @return array $arguments
     */
    public function extract_action_group_arguments($action_group): array
    {
        $arguments = [];
        $arguments_nodes = $action_group->get_elements_by_tag_name('arguments');
        if ($arguments_nodes->length > 0) {
            $argument_nodes = $arguments_nodes[0]->get_elements_by_tag_name('argument');
            foreach ($argument_nodes as $argument_node) {
                $arguments[] = $argument_node->get_attribute('name');
            }
        }
        return $arguments;
    }
    /**
     * Returns unused arguments in an action group
     * @param array  $arguments
     * @param string $contents
     */
    public function find_unused_arguments($arguments, $contents): array
    {
        $unused_arguments = [];
        preg_match(self::ACTIONGROUP_NAME_REGEX_PATTERN, $contents, $action_group_name);
        $valid_action_group = false;
        try {
            $action_group = Action_Group_Object_Handler::get_instance()->get_object($action_group_name[1]);
            if ($action_group) {
                $valid_action_group = true;
            }
        } catch (Exception) {
        }
        if (!$valid_action_group) {
            return $unused_arguments;
        }
        foreach ($arguments as $argument) {
            //pattern to match all argument references
            $patterns = ['(\{{2}' . $argument . '(\.[a-zA-Z0-9_\[\]\(\).,\'\/ ]+)?}{2})', '([(,\s\'$$]' . $argument . '(\.[a-zA-Z0-9_$\[\]]+)?[),\s\'])'];
            // matches entity references
            if (preg_match($patterns[0], $contents)) {
                continue;
            }
            //matches parametrized references
            if (preg_match($patterns[1], $contents)) {
                continue;
            }
            //for extending action groups, exclude arguments that are also defined in parent action group
            if ($this->is_parent_action_group_argument($argument, $action_group)) {
                continue;
            }
            $unused_arguments[] = $argument;
        }
        return $unused_arguments;
    }
    /**
     * Checks if the argument is also defined in the parent for extending action groups.
     * @param string            $argument
     * @param ActionGroupObject $actionGroup
     */
    private function is_parent_action_group_argument($argument, $action_group): bool
    {
        $parent_action_group_name = $action_group->get_parent_name();
        if ($parent_action_group_name !== null) {
            $parent_action_group = Action_Group_Object_Handler::get_instance()->get_object($parent_action_group_name);
            $parent_arguments = $parent_action_group->get_arguments();
            foreach ($parent_arguments as $parent_argument) {
                if ($argument === $parent_argument->get_name()) {
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Builds and returns error output for violating references
     *
     * @param SplFileInfo $path
     * @return array{non-falsy-string}[]
     */
    private function set_error_output(array $action_group_to_arguments, $path): array
    {
        $action_group_errors = [];
        if (!empty($action_group_to_arguments)) {
            // Build error output
            $error_output = "\nFile \"{$path->get_real_path()}\"";
            $error_output .= "\ncontains action group(s) with unused arguments.\n\t\t";
            foreach ($action_group_to_arguments as $action_group => $arguments) {
                $error_output .= "\n\t {$action_group} has unused argument(s): " . implode(', ', $arguments);
            }
            $action_group_errors[$path->get_real_path()][] = $error_output;
        }
        return $action_group_errors;
    }
}