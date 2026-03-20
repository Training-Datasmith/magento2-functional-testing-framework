<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Script;

use Exception;
use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Data_Object_Handler;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Reference_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Xml_Exception;
use Magento\Functional_Testing_Framework\Page\Handlers\Page_Object_Handler;
use Magento\Functional_Testing_Framework\Page\Handlers\Section_Object_Handler;
use Magento\Functional_Testing_Framework\Page\Objects\Element_Object;
use Magento\Functional_Testing_Framework\Page\Objects\Section_Object;
use Magento\Functional_Testing_Framework\Test\Handlers\Action_Group_Object_Handler;
use Magento\Functional_Testing_Framework\Test\Handlers\Test_Object_Handler;
use Magento\Functional_Testing_Framework\Test\Objects\Action_Object;
use Magento\Functional_Testing_Framework\Util\Module_Resolver;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
use Magento\Functional_Testing_Framework\Util\Test_Generator;
use Symfony\Component\Finder\Finder;
/**
 * ScriptUtil class that contains helper functions for static and upgrade scripts
 *
 * @package Magento\FunctionalTestingFramework\Util\Script
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Script_Util
{
    public const ACTIONGROUP_ARGUMENT_REGEX_PATTERN = '/<argument[^\/>]*name="([^"\']*)/';
    public const ROOT_SUITE_DIR = 'tests/_suite';
    public const DEV_TESTS_DIR = 'dev/tests/acceptance/';
    /**
     * Return all installed Magento module paths
     *
     * @throws TestFrameworkException
     */
    public function get_all_module_paths(): array
    {
        Mftf_Application_Config::create(true, Mftf_Application_Config::UNIT_TEST_PHASE, false, Mftf_Application_Config::LEVEL_DEFAULT, true);
        return Module_Resolver::get_instance()->get_modules_path();
    }
    /**
     * Prints out given errors to file, and returns summary result string
     */
    public function print_errors_to_file(array $errors, string $file_path, string $message): string
    {
        if (empty($errors)) {
            return $message . ': No errors found.';
        }
        $this->print_tofile($errors, $file_path);
        $error_count = count($errors);
        return $message . ": Errors found across {$error_count} file(s). Error details output to {$file_path}";
    }
    /**
     * Prints out given warnings to file, and returns summary result string
     */
    public function print_warnings_to_file(array $warnings, string $file_path, string $message): string
    {
        if (empty($warnings)) {
            return $message . ': No warnings found.';
        }
        $this->print_tofile($warnings, $file_path);
        $error_count = count($warnings);
        return $message . ": Warnings found across {$error_count} file(s). Warning details output to {$file_path}";
    }
    /**
     * Writes contents to filePath
     */
    private function print_tofile(array $contents, string $file_path): void
    {
        $dirname = dirname($file_path);
        if (!file_exists($dirname)) {
            mkdir($dirname, 0777, true);
        }
        $file_resource = fopen($file_path, 'w');
        foreach ($contents as $error) {
            fwrite($file_resource, $error[0] . PHP_EOL);
        }
        fclose($file_resource);
    }
    /**
     * Return all XML files for $scope in given module paths, empty array if no path is valid
     */
    public function get_module_xml_files_by_scope(array $module_paths, string $scope): \Symfony\Component\Finder\Finder|array
    {
        $found = false;
        $scope_path = DIRECTORY_SEPARATOR . ucfirst($scope) . DIRECTORY_SEPARATOR;
        $finder = new Finder();
        foreach ($module_paths as $module_path) {
            if (!realpath($module_path . $scope_path)) {
                continue;
            }
            $finder->files()->follow_links()->in($module_path . $scope_path)->name('*.xml')->sort_by_name();
            $found = true;
        }
        return $found ? $finder->files() : [];
    }
    /**
     * Return suite XML files in TESTS_BP/ROOT_SUITE_DIR directory
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function get_root_suite_xml_files(): \Symfony\Component\Finder\Finder|array
    {
        $root_suite_paths = [];
        $default_test_path = null;
        $dev_tests_path = null;
        try {
            $default_test_path = File_Path_Formatter::format(TESTS_BP);
        } catch (Test_Framework_Exception) {
        }
        try {
            $dev_tests_path = File_Path_Formatter::format(MAGENTO_BP) . self::DEV_TESTS_DIR;
        } catch (Test_Framework_Exception) {
        }
        if ($default_test_path) {
            $root_suite_paths[] = $default_test_path . self::ROOT_SUITE_DIR;
        }
        if ($dev_tests_path && realpath($dev_tests_path) && $dev_tests_path !== $default_test_path) {
            $root_suite_paths[] = $dev_tests_path . self::ROOT_SUITE_DIR;
        }
        $found = false;
        $finder = new Finder();
        foreach ($root_suite_paths as $root_suite_path) {
            if (!realpath($root_suite_path)) {
                continue;
            }
            $finder->files()->follow_links()->in($root_suite_path)->name('*.xml');
            $found = true;
        }
        return $found ? $finder->files() : [];
    }
    /**
     * Resolve entity reference in {{entity.field}} or {{entity.field('param')}}
     *
     * @param array   $braceReferences
     * @param string  $contents
     * @param boolean $resolveSectionElement
     * @throws XmlException
     */
    public function resolve_entity_references($brace_references, $contents, $resolve_section_element = false): array
    {
        $entities = [];
        foreach ($brace_references as $reference) {
            // trim `{{data.field}}` to `data`
            preg_match('/{{([^.]+)/', (string) $reference, $entity_name);
            // Double check that {{data.field}} isn't an argument for an ActionGroup
            $entity = $this->find_entity($entity_name[1]);
            preg_match_all(self::ACTIONGROUP_ARGUMENT_REGEX_PATTERN, $contents, $possible_argument);
            if (array_search($entity_name[1], $possible_argument[1]) !== false) {
                continue;
            }
            if ($entity !== null) {
                $entities[$entity->get_name()] = $entity;
                if ($resolve_section_element) {
                    if ($entity::class === Section_Object::class) {
                        // trim `{{data.field}}` to `field`
                        preg_match('/.([^.]+)}}/', (string) $reference, $element_name);
                        /** @var ElementObject $element */
                        $element = $entity->get_element($element_name[1]);
                        if ($element) {
                            $entities[$entity->get_name() . '.' . $element_name[1]] = $element;
                        }
                    }
                }
            }
        }
        return $entities;
    }
    /**
     * Drill down into params in {{ref.params('string', $data.key$, entity.reference)}} to resolve entity reference
     *
     * @param array   $braceReferences
     * @param string  $contents
     * @param boolean $resolveSectionElement
     * @throws XmlException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function resolve_parametrized_references($brace_references, $contents, $resolve_section_element = false): array
    {
        $entities = [];
        foreach ($brace_references as $parameterized_reference) {
            preg_match(Action_Object::ACTION_ATTRIBUTE_VARIABLE_REGEX_PARAMETER, (string) $parameterized_reference, $arguments);
            $split_arguments = explode(',', ltrim(rtrim($arguments[0], ')'), '('));
            foreach ($split_arguments as $argument) {
                // Do nothing for 'string' or $persisted.data$
                if (preg_match(Action_Object::STRING_PARAMETER_REGEX, $argument)) {
                    continue;
                }
                // Do nothing for 'string' or $persisted.data$
                if (preg_match(Test_Generator::PERSISTED_OBJECT_NOTATION_REGEX, $argument)) {
                    continue;
                }
                // trim `data.field` to `data`
                preg_match('/([^.]+)/', $argument, $entity_name);
                // Double check that {{data.field}} isn't an argument for an ActionGroup
                $entity = $this->find_entity($entity_name[1]);
                preg_match_all(self::ACTIONGROUP_ARGUMENT_REGEX_PATTERN, $contents, $possible_argument);
                if (array_search($entity_name[1], $possible_argument[1]) !== false) {
                    continue;
                }
                if ($entity !== null) {
                    $entities[$entity->get_name()] = $entity;
                    if ($resolve_section_element) {
                        if ($entity::class === Section_Object::class) {
                            // trim `data.field` to `field`
                            preg_match('/.([^.]+)/', $argument, $element_name);
                            /** @var ElementObject $element */
                            $element = $entity->get_element($element_name[1]);
                            if ($element) {
                                $entities[$entity->get_name() . '.' . $element_name[1]] = $element;
                            }
                        }
                    }
                }
            }
        }
        return $entities;
    }
    /**
     * Resolve entity by names
     *
     * @throws XmlException
     */
    public function resolve_entity_by_names(array $references): array
    {
        $entities = [];
        foreach ($references as $reference) {
            $entity = $this->find_entity($reference);
            if ($entity !== null) {
                $entities[$entity->get_name()] = $entity;
            }
        }
        return $entities;
    }
    /**
     * Attempts to find any MFTF entity by its name. Returns null if none are found
     *
     * @return mixed
     * @throws XmlException
     * @throws Exception
     */
    public function find_entity(string $name)
    {
        if ($name === '_ENV' || $name === '_CREDS') {
            return null;
        }
        if (Data_Object_Handler::get_instance()->get_object($name)) {
            return Data_Object_Handler::get_instance()->get_object($name);
        }
        if (Page_Object_Handler::get_instance()->get_object($name)) {
            return Page_Object_Handler::get_instance()->get_object($name);
        }
        if (Section_Object_Handler::get_instance()->get_object($name)) {
            return Section_Object_Handler::get_instance()->get_object($name);
        }
        if (Action_Group_Object_Handler::get_instance()->get_object($name)) {
            return Action_Group_Object_Handler::get_instance()->get_object($name);
        }
        try {
            return Test_Object_Handler::get_instance()->get_object($name);
        } catch (Test_Reference_Exception) {
        }
        return null;
    }
    /**
     * Return all XML files in given test name, empty array if no path is valid
     * @return array|Finder
     */
    public function get_module_xml_files_by_test_names(array $test_names)
    {
        $finder = new Finder();
        array_walk($test_names, function (&$value): void {
            $value = $value . '.xml';
        });
        $finder->files()->follow_links()->in(MAGENTO_BP)->name($test_names)->sort_by_name();
        return $finder->files() ?? [];
    }
}