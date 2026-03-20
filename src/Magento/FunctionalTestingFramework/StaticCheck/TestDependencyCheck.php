<?php

declare (strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Static_Check;

use Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Xml_Exception;
use Magento\Functional_Testing_Framework\Test\Objects\Action_Object;
use Magento\Functional_Testing_Framework\Util\Script\Script_Util;
use Magento\Functional_Testing_Framework\Util\Script\Test_Dependency_Util;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Finder\Finder;
/**
 * Class TestDependencyCheck
 * @package Magento\FunctionalTestingFramework\StaticCheck
 */
class Test_Dependency_Check implements Static_Check_Interface
{
    public const EXTENDS_REGEX_PATTERN = '/extends=["\']([^\'"]*)/';
    public const ACTIONGROUP_REGEX_PATTERN = '/ref=["\']([^\'"]*)/';
    public const ERROR_LOG_FILENAME = 'mftf-dependency-checks-errors';
    public const ERROR_LOG_MESSAGE = 'MFTF File Dependency Check';
    public const WARNING_LOG_FILENAME = 'mftf-dependency-checks-warnings';
    public const ALLOW_LIST_FILENAME = 'test-dependency-allowlist';
    /**
     * Array of FullModuleName => [dependencies], including flattened dependency tree
     */
    private ?array $flattened_dependencies = null;
    /**
     * Array of FullModuleName => PathToModule
     * @var array
     */
    private $module_name_to_path;
    /**
     * Array of FullModuleName => ComposerModuleName
     */
    private ?array $module_name_to_composer_name = null;
    /**
     * Array containing all errors found after running the execute() function.
     */
    private array $errors = [];
    /**
     * Array containing all warnings found after running the execute() function.
     */
    private array $warnings = [];
    /**
     * Array containing warnings found while iterating through files
     * @var array
     */
    private $temp_warnings = [];
    /**
     * String representing the output summary found after running the execute() function.
     */
    private ?string $output = null;
    /**
     * Array containing all entities after resolving references.
     */
    private array $all_entities = [];
    /**
     * ScriptUtil instance
     */
    private ?\Magento\Functional_Testing_Framework\Util\Script\Script_Util $script_util = null;
    private ?\Magento\Functional_Testing_Framework\Util\Script\Test_Dependency_Util $test_dependency_util = null;
    private array $allow_failure_entities = [];
    /**
     * Checks test dependencies, determined by references in tests versus the dependencies listed in the Magento module
     *
     * @throws Exception
     */
    public function execute(Input_Interface $input): void
    {
        $this->script_util = new Script_Util();
        $this->test_dependency_util = new Test_Dependency_Util();
        $all_modules = $this->script_util->get_all_module_paths();
        if (!class_exists('\Magento\Framework\Component\ComponentRegistrar')) {
            throw new Test_Framework_Exception('TEST DEPENDENCY CHECK ABORTED: MFTF must be attached or pointing to Magento codebase.');
        }
        // Build array of entities found in allow-list files
        // Expect one entity per file line, no commas or anything else
        foreach ($all_modules as $module_path) {
            if (file_exists($module_path . DIRECTORY_SEPARATOR . self::ALLOW_LIST_FILENAME)) {
                $contents = file_get_contents($module_path . DIRECTORY_SEPARATOR . self::ALLOW_LIST_FILENAME);
                foreach (explode("\n", $contents) as $entity) {
                    $this->allow_failure_entities[$entity] = true;
                }
            }
        }
        $registrar = new \Magento\Framework\Component\Component_Registrar();
        $this->module_name_to_path = $registrar->get_paths(\Magento\Framework\Component\Component_Registrar::MODULE);
        $this->module_name_to_composer_name = $this->test_dependency_util->build_module_name_to_composer_name($this->module_name_to_path);
        $this->flattened_dependencies = $this->test_dependency_util->build_composer_dependency_list($this->module_name_to_path, $this->module_name_to_composer_name);
        $file_paths = [DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR . 'ActionGroup' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR . 'Data' . DIRECTORY_SEPARATOR];
        // These files can contain references to other modules.
        $test_xml_files = $this->script_util->get_module_xml_files_by_scope($all_modules, $file_paths[0]);
        $action_group_xml_files = $this->script_util->get_module_xml_files_by_scope($all_modules, $file_paths[1]);
        $data_xml_files = $this->script_util->get_module_xml_files_by_scope($all_modules, $file_paths[2]);
        $this->errors = [];
        $this->errors += $this->find_errors_in_file_set($test_xml_files);
        $this->errors += $this->find_errors_in_file_set($action_group_xml_files);
        $this->errors += $this->find_errors_in_file_set($data_xml_files);
        // hold on to the output and print any errors to a file
        $this->output = $this->script_util->print_errors_to_file($this->errors, Static_Checks_List::get_error_files_path() . DIRECTORY_SEPARATOR . self::ERROR_LOG_FILENAME . '.txt', self::ERROR_LOG_MESSAGE);
        if (!empty($this->warnings) && !empty($this->errors)) {
            $this->output .= "\n " . $this->script_util->print_warnings_to_file($this->warnings, Static_Checks_List::get_error_files_path() . DIRECTORY_SEPARATOR . self::WARNING_LOG_FILENAME . '.txt', self::ERROR_LOG_MESSAGE);
        }
    }
    /**
     * Return array containing all errors found after running the execute() function.
     */
    public function get_errors(): array
    {
        return $this->errors;
    }
    /**
     * Return string of a short human readable result of the check. For example: "No Dependency errors found."
     */
    public function get_output(): string
    {
        return $this->output ?? '';
    }
    /**
     * Finds all reference errors in given set of files
     * @throws XmlException
     */
    private function find_errors_in_file_set(Finder $files): array
    {
        $test_errors = [];
        foreach ($files as $file_path) {
            $this->all_entities = [];
            $module_name = $this->test_dependency_util->get_module_name($file_path, $this->module_name_to_path);
            // Not a module, is either dev/tests/acceptance or loose folder with test materials
            if ($module_name === null) {
                continue;
            }
            $contents = file_get_contents($file_path);
            preg_match_all(Action_Object::ACTION_ATTRIBUTE_VARIABLE_REGEX_PATTERN, $contents, $brace_references);
            preg_match_all(self::ACTIONGROUP_REGEX_PATTERN, $contents, $action_group_references);
            preg_match_all(self::EXTENDS_REGEX_PATTERN, $contents, $extend_references);
            // Remove Duplicates
            $brace_references[0] = array_unique($brace_references[0]);
            $action_group_references[1] = array_unique($action_group_references[1]);
            $brace_references[1] = array_unique($brace_references[1]);
            $brace_references[2] = array_filter(array_unique($brace_references[2]));
            // resolve entity references
            $this->all_entities = array_merge($this->all_entities, $this->script_util->resolve_entity_references($brace_references[0], $contents));
            // resolve parameterized references
            $this->all_entities = array_merge($this->all_entities, $this->script_util->resolve_parametrized_references($brace_references[2], $contents));
            // resolve entity by names
            $this->all_entities = array_merge($this->all_entities, $this->script_util->resolve_entity_by_names($action_group_references[1]));
            // resolve entity by names
            $this->all_entities = array_merge($this->all_entities, $this->script_util->resolve_entity_by_names($extend_references[1]));
            // Find violating references and set error output
            $violating_references = $this->find_violating_references($module_name);
            $test_errors = array_merge($test_errors, $this->set_error_output($violating_references, $file_path));
            $this->warnings = array_merge($this->warnings, $this->set_error_output($this->temp_warnings, $file_path));
        }
        return $test_errors;
    }
    /**
     * Find violating references
     */
    private function find_violating_references(string $module_name): array
    {
        // Find Violations
        $violating_references = [];
        $current_module = $this->module_name_to_composer_name[$module_name];
        $modules_referenced_in_test = $this->test_dependency_util->get_module_dependencies_from_references($this->all_entities, $this->module_name_to_composer_name, $this->module_name_to_path);
        $module_dependencies = $this->flattened_dependencies[$module_name];
        foreach ($modules_referenced_in_test as $entity_name => $files) {
            $is_in_allow_list = array_key_exists($entity_name, $this->allow_failure_entities);
            $valid = false;
            foreach ($files as $module) {
                if (array_key_exists($module, $module_dependencies) || $module === $current_module) {
                    $valid = true;
                    break;
                }
            }
            if (!$valid) {
                if ($is_in_allow_list) {
                    $this->temp_warnings[$entity_name] = $files;
                    continue;
                }
                $violating_references[$entity_name] = $files;
            }
        }
        return $violating_references;
    }
    /**
     * Builds and returns error output for violating references
     */
    private function set_error_output(array $violating_references, \Symfony\Component\Finder\Spl_File_Info $path): array
    {
        $test_errors = [];
        if (!empty($violating_references)) {
            // Build error output
            $error_output = "\nFile \"{$path->get_real_path()}\"";
            $error_output .= "\ncontains entity references that violate dependency constraints:\n\t\t";
            foreach ($violating_references as $entity_name => $files) {
                $error_output .= "\n\t {$entity_name} from module(s): " . implode(', ', $files);
            }
            $test_errors[$path->get_real_path()][] = $error_output;
        }
        return $test_errors;
    }
}