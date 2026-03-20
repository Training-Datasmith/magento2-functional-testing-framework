<?php

declare (strict_types=1);
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util;

use Magento\Functional_Testing_Framework\Composer\Composer_Install;
use Magento\Functional_Testing_Framework\Composer\Composer_Package;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
/**
 * Composer Based Module Resolver
 */
class Composer_Module_Resolver
{
    /**
     * Code path array from composer json search
     */
    private ?array $searched_test_modules = null;
    /**
     * Code path array from composer installed test packages
     *
     * @var array
     */
    private $installed_test_modules;
    /**
     * Get code paths for installed test modules
     *
     * @param string $rootComposerFile
     * @return array
     * @throws TestFrameworkException
     */
    public function get_composer_installed_test_modules($root_composer_file)
    {
        if (null !== $this->installed_test_modules) {
            return $this->installed_test_modules;
        }
        if (!file_exists($root_composer_file) || basename($root_composer_file, '.json') !== 'composer') {
            throw new Test_Framework_Exception("Invalid root composer json file: {$root_composer_file}");
        }
        $this->installed_test_modules = [];
        $composer = new Composer_Install($root_composer_file);
        foreach ($composer->get_installed_test_packages() as $package_data) {
            $suggested_module_names = $package_data[Composer_Install::PACKAGE_SUGGESTED_MAGENTO_MODULES];
            $path = $package_data[Composer_Install::PACKAGE_INSTALLEDPATH];
            $this->installed_test_modules[$path] = $suggested_module_names;
        }
        return $this->installed_test_modules;
    }
    /**
     * Get code paths by searching test module composer json file from input directories
     *
     * @param array $directories
     * @return array
     * @throws TestFrameworkException
     */
    public function get_test_modules_from_paths($directories)
    {
        if (null !== $this->searched_test_modules) {
            return $this->searched_test_modules;
        }
        $this->searched_test_modules = [];
        foreach ($directories as $directory) {
            $this->searched_test_modules = array_merge_recursive($this->searched_test_modules, $this->get_test_modules($directory));
        }
        return $this->searched_test_modules;
    }
    /**
     * Get code paths by searching test module composer json file from input directory
     *
     * @param string $directory
     * @throws TestFrameworkException
     */
    private function get_test_modules($directory): array
    {
        $normalized_dir = realpath($directory);
        if (!is_dir($normalized_dir)) {
            throw new Test_Framework_Exception("Invalid directory: {$directory}");
        }
        // Find all composer json files under directory
        $modules = [];
        $file_list = $this->find_composer_json_files_at_depth($normalized_dir, 2);
        foreach ($file_list as $file) {
            // Parse composer json for test module name and path information
            $composer_info = new Composer_Package($file);
            if ($composer_info->is_mftf_test_package()) {
                $module_path = str_replace(DIRECTORY_SEPARATOR . 'composer.json', '', $file);
                $suggested_magento_module_names = $composer_info->get_suggested_magento_modules();
                if (array_key_exists($module_path, $modules)) {
                    $modules[$module_path] = array_merge($modules[$module_path], $suggested_magento_module_names);
                } else {
                    $modules[$module_path] = $suggested_magento_module_names;
                }
            }
        }
        return $modules;
    }
    /**
     * Find absolute paths of all composer json files in a given directory
     */
    private function find_all_composer_json_files(string $directory): array
    {
        $directory = realpath($directory);
        $json_pattern = DIRECTORY_SEPARATOR . 'composer.json';
        $sub_directory_pattern = DIRECTORY_SEPARATOR . '*';
        $json_file_list = [];
        foreach (glob($directory . $sub_directory_pattern, GLOB_ONLYDIR) as $dir) {
            $json_file_list = array_merge_recursive($json_file_list, self::find_all_composer_json_files($dir));
        }
        $cur_json_files = glob($directory . $json_pattern);
        if ($cur_json_files !== false && !empty($cur_json_files)) {
            return array_merge_recursive($json_file_list, $cur_json_files);
        }
        return $json_file_list;
    }
    /**
     * Find absolute paths of all composer json files in a given directory at certain depths
     *
     * @param string  $directory
     * @param integer $depth
     * @return array
     */
    private function find_composer_json_files_at_depth($directory, int|float $depth)
    {
        $directory = realpath($directory);
        $json_pattern = DIRECTORY_SEPARATOR . 'composer.json';
        $sub_directory_pattern = DIRECTORY_SEPARATOR . '*';
        $json_file_list = [];
        if ($depth > 0) {
            foreach (glob($directory . $sub_directory_pattern, GLOB_ONLYDIR) as $dir) {
                $json_file_list = array_merge_recursive($json_file_list, self::find_composer_json_files_at_depth($dir, $depth - 1));
            }
        } elseif ($depth === 0) {
            $json_file_list = glob($directory . $json_pattern);
            if ($json_file_list === false) {
                $json_file_list = [];
            }
        }
        return $json_file_list;
    }
}