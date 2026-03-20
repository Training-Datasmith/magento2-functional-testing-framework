<?php

declare (strict_types=1);
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Script;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Test\Handlers\Test_Object_Handler;
/**
 * TestDependencyUtil class that contains helper functions for static and upgrade scripts
 *
 * @package Magento\FunctionalTestingFramework\Util\Script
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Test_Dependency_Util
{
    /**
     * Array of FullModuleName => [dependencies]
     */
    private ?array $all_dependencies = null;
    /**
     * Transactional Array to keep track of what dependencies have already been extracted.
     */
    private ?array $already_extracted_dependencies = null;
    /**
     * Builds and returns array of FullModuleNae => composer name
     */
    public function build_module_name_to_composer_name(array $module_name_to_path): array
    {
        $module_name_to_composer_name = [];
        foreach ($module_name_to_path as $module_name => $path) {
            $composer_data = json_decode(file_get_contents($path . DIRECTORY_SEPARATOR . 'composer.json'));
            $module_name_to_composer_name[$module_name] = $composer_data->name;
        }
        return $module_name_to_composer_name;
    }
    /**
     * Builds and returns flattened dependency list based on composer dependencies
     */
    public function build_composer_dependency_list(array $module_name_to_path, array $module_name_to_composer_name): array
    {
        $flattened_dependencies = [];
        foreach ($module_name_to_path as $module_name => $path_to_module) {
            $composer_data = json_decode(file_get_contents($path_to_module . DIRECTORY_SEPARATOR . 'composer.json'), true);
            $this->all_dependencies[$module_name] = $composer_data['require'];
        }
        foreach ($this->all_dependencies as $module_name => $dependencies) {
            $this->already_extracted_dependencies = [];
            $flattened_dependencies[$module_name] = $this->extract_sub_dependencies($module_name, $module_name_to_composer_name);
        }
        return $flattened_dependencies;
    }
    /**
     * Recursive function to fetch dependencies of given dependency, and its child dependencies
     */
    private function extract_sub_dependencies(string $sub_dependency_name, array $module_name_to_composer_name): array
    {
        $flattened_array = [];
        if (in_array($sub_dependency_name, $this->already_extracted_dependencies)) {
            return $flattened_array;
        }
        if (isset($this->all_dependencies[$sub_dependency_name])) {
            $sub_dependency_array = $this->all_dependencies[$sub_dependency_name];
            $flattened_array = array_merge($flattened_array, $this->all_dependencies[$sub_dependency_name]);
            // Keep track of dependencies that have already been used, prevents circular dependency problems
            $this->already_extracted_dependencies[] = $sub_dependency_name;
            foreach ($sub_dependency_array as $composer_dependency_name => $version) {
                $sub_dependency_full_name = array_search($composer_dependency_name, $module_name_to_composer_name);
                $flattened_array = array_merge($flattened_array, $this->extract_sub_dependencies($sub_dependency_full_name, $module_name_to_composer_name));
            }
        }
        return $flattened_array;
    }
    /**
     * Finds unique array composer dependencies of given testObjects
     */
    public function get_module_dependencies_from_references(array $all_entities, array $module_composer_name, array $module_name_to_path): array
    {
        $filenames = [];
        foreach ($all_entities as $item) {
            // Should it append ALL filenames, including merges?
            $all_files = explode(',', (string) $item->get_filename());
            foreach ($all_files as $file) {
                $module_name = $this->get_module_name($file, $module_name_to_path);
                if (isset($module_composer_name[$module_name])) {
                    $composer_module_name = $module_composer_name[$module_name];
                    $filenames[$item->get_name()][] = $composer_module_name;
                }
            }
        }
        return $filenames;
    }
    /**
     * Return module name for a file path
     */
    public function get_module_name(string $file_path, array $module_name_to_path): ?string
    {
        $module_name = null;
        foreach ($module_name_to_path as $name => $path) {
            if (str_contains($file_path, $path . '/')) {
                $module_name = $name;
                break;
            }
        }
        return $module_name;
    }
    /**
     * Return array of merge test modules and file path with same test name.
     */
    public function merge_dependencies_for_extending_tests(array $test_dependencies, array $filter_list, array $extended_test_mapping = []): array
    {
        $test_objects = Test_Object_Handler::get_instance()->get_all_objects();
        $filters = Mftf_Application_Config::get_config()->get_filter_list()->get_filters();
        $filtered_test_names = count($filter_list) > 0 ? $this->get_filtered_test_names($test_objects, $filters) : [];
        $temp_array = array_reverse(array_column($test_dependencies, 'test_name'), true);
        foreach ($extended_test_mapping as $value) {
            $key = array_search($value['parent_test_name'], $temp_array);
            if ($key !== false) {
                #if parent test found merge this to child, for doing so just replace test name with child.
                $test_dependencies[$key]['test_name'] = $value['child_test_name'];
            }
        }
        $temp_array = [];
        foreach ($test_dependencies as $test_dependency) {
            $temp_array[$test_dependency['test_name']][] = $test_dependency;
        }
        $test_dependencies = [];
        foreach ($temp_array as $test_dependency_array) {
            if (empty($filter_list) || isset($filtered_test_names[$test_dependency_array[0]['test_name']])) {
                $test_dependencies[] = ['file_path' => array_column($test_dependency_array, 'file_path'), 'full_name' => $test_dependency_array[0]['full_name'], 'test_name' => $test_dependency_array[0]['test_name'], 'test_modules' => array_values(array_unique(call_user_func_array(array_merge(...), array_column($test_dependency_array, 'test_modules'))))];
            }
        }
        return $test_dependencies;
    }
    /**
     * Return array of merge test modules and file path with same test name.
     */
    public function get_filtered_test_names(array $test_objects, array $filters): array
    {
        foreach ($filters as $filter) {
            $filter->filter($test_objects);
        }
        return array_map(fn($test_objects) => $test_objects->get_name(), $test_objects);
    }
}