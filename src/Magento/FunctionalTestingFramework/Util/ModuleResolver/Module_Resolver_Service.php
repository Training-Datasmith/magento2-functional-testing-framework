<?php

/**
 * Copyright 2021 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Util\Module_Resolver;

use Magento\Framework\Component\Component_Registrar;
use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Data_Transport\Auth\Web_Api_Auth;
use Magento\Functional_Testing_Framework\Exceptions\Fast_Fail_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Composer_Module_Resolver;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
use Magento\Functional_Testing_Framework\Util\Module_Resolver;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
class Module_Resolver_Service
{
    /**
     * Singleton ModuleResolverCreator Instance.
     */
    private static ?\Magento\Functional_Testing_Framework\Util\Module_Resolver\Module_Resolver_Service $INSTANCE = null;
    /**
     * Composer json based test module paths.
     *
     * @var array
     */
    private $composer_json_module_paths;
    /**
     * Composer installed test module paths.
     *
     * @var array
     */
    private $composer_installed_module_paths;
    /**
     * ModuleResolverService constructor.
     */
    private function __construct()
    {
    }
    /**
     * Get ModuleResolverCreator instance.
     *
     * @return ModuleResolverService
     */
    public static function get_instance()
    {
        if (self::$INSTANCE === null) {
            self::$INSTANCE = new Module_Resolver_Service();
        }
        return self::$INSTANCE;
    }
    /**
     * Calls Magento method for determining registered modules.
     *
     * @return string[]
     * @throws TestFrameworkException
     */
    public function get_registered_module_list(): array
    {
        if (!empty($this->registered_module_list)) {
            return $this->registered_module_list;
        }
        if (array_key_exists('MAGENTO_BP', $_ENV)) {
            $autoload_path = realpath(MAGENTO_BP . '/app/autoload.php');
            if ($autoload_path) {
                require_once $autoload_path;
            } else {
                throw new Test_Framework_Exception('Magento app/autoload.php not found with given MAGENTO_BP:' . MAGENTO_BP);
            }
        }
        try {
            $all_components = [];
            if (!class_exists(Module_Resolver::REGISTRAR_CLASS)) {
                throw new Test_Framework_Exception("Magento Installation not found when loading registered modules.\n");
            }
            $components = new Component_Registrar();
            foreach (Module_Resolver::PATHS as $component_type) {
                $all_components = array_merge($all_components, $components->get_paths($component_type));
            }
            array_walk($all_components, function (&$value): void {
                // Magento stores component paths with unix DIRECTORY_SEPARATOR, need to stay uniform and convert
                $value = realpath($value);
                $value .= DIRECTORY_SEPARATOR . Module_Resolver::TEST_MFTF_PATTERN;
            });
            return $all_components;
        } catch (Test_Framework_Exception $exception) {
            Logging_Util::get_instance()->get_logger(Module_Resolver::class)->warning("{$exception}");
        }
        return [];
    }
    /**
     * Function which takes a code path and a pattern and determines if there are any matching subdir paths. Matches
     * are returned as an associative array keyed by basename (the last dir excluding pattern) to an array containing
     * the matching path.
     *
     *
     * @throws TestFrameworkException
     */
    public function glob_relevant_paths(string $test_path, string $pattern): array
    {
        $module_paths = [];
        $relevant_paths = [];
        if (file_exists($test_path)) {
            $relevant_paths = self::glob_relevant_wrapper($test_path, $pattern);
        }
        foreach ($relevant_paths as $code_path) {
            $potential_symlink = str_replace(DIRECTORY_SEPARATOR . $pattern, '', $code_path);
            if (is_link($potential_symlink)) {
                $code_path = realpath($potential_symlink) . DIRECTORY_SEPARATOR . $pattern;
            }
            $main_mod_name = basename(str_replace($pattern, '', $code_path));
            $module_paths[$code_path] = [$main_mod_name];
            if (Mftf_Application_Config::get_config()->verbose_enabled()) {
                Logging_Util::get_instance()->get_logger(Module_Resolver::class)->debug('including module', ['module' => $main_mod_name, 'path' => $code_path]);
            }
        }
        return $module_paths;
    }
    /**
     * Glob wrapper for globRelevantPaths function.
     *
     *
     */
    private static function glob_relevant_wrapper(string $test_path, string $pattern): array
    {
        if ($pattern === '') {
            return glob($test_path . '*' . DIRECTORY_SEPARATOR . '*' . $pattern);
        }
        $sub_directory = '*' . DIRECTORY_SEPARATOR;
        $directories = glob($test_path . $sub_directory . $pattern, GLOB_ONLYDIR);
        foreach (glob($test_path . $sub_directory, GLOB_ONLYDIR) as $dir) {
            $directories = array_merge_recursive($directories, self::glob_relevant_wrapper($dir, $pattern));
        }
        return $directories;
    }
    /**
     * Retrieve all module code paths that have test module composer json files.
     *
     *
     */
    public function get_composer_json_test_module_paths(array $code_paths): array
    {
        if (null !== $this->composer_json_module_paths) {
            return $this->composer_json_module_paths;
        }
        try {
            $this->composer_json_module_paths = [];
            $resolver = new Composer_Module_Resolver();
            $this->composer_json_module_paths = $resolver->get_test_modules_from_paths($code_paths);
        } catch (Test_Framework_Exception) {
        }
        return $this->composer_json_module_paths;
    }
    /**
     * Retrieve composer installed test module code paths.
     *
     *
     */
    public function get_composer_installed_test_module_paths(string $composer_file): array
    {
        if (null !== $this->composer_installed_module_paths) {
            return $this->composer_installed_module_paths;
        }
        try {
            $this->composer_installed_module_paths = [];
            $resolver = new Composer_Module_Resolver();
            $this->composer_installed_module_paths = $resolver->get_composer_installed_test_modules($composer_file);
        } catch (Test_Framework_Exception) {
        }
        return $this->composer_installed_module_paths;
    }
    /**
     * Retrieves all module directories which might contain pertinent test code.
     *
     * @throws TestFrameworkException
     */
    public function aggregate_test_module_paths(): array
    {
        $all_module_paths = [];
        // Define the Module paths from magento bp
        $magento_base_code_path = File_Path_Formatter::format(MAGENTO_BP, false);
        // Define the Module paths from default TESTS_MODULE_PATH
        $module_path = defined('TESTS_MODULE_PATH') ? TESTS_MODULE_PATH : TESTS_BP;
        $module_path = File_Path_Formatter::format($module_path, false);
        // If $modulePath is DEV_TESTS path, we don't need to search by pattern
        if (!str_contains($module_path, Module_Resolver::DEV_TESTS)) {
            $code_paths_to_pattern[$module_path] = '';
        }
        $vendor_code_path = DIRECTORY_SEPARATOR . Module_Resolver::VENDOR;
        $code_paths_to_pattern[$magento_base_code_path . $vendor_code_path] = Module_Resolver::TEST_MFTF_PATTERN;
        $app_code_path = DIRECTORY_SEPARATOR . Module_Resolver::APP_CODE;
        $code_paths_to_pattern[$magento_base_code_path . $app_code_path] = Module_Resolver::TEST_MFTF_PATTERN;
        foreach ($code_paths_to_pattern as $code_path => $pattern) {
            $all_module_paths = array_merge_recursive($all_module_paths, $this->glob_relevant_paths($code_path, $pattern));
        }
        return $all_module_paths;
    }
    /**
     * Returns an array of custom module paths defined by the user.
     *
     * @return string[]
     */
    public function get_custom_module_paths(): array
    {
        $custom_module_paths = [];
        $paths = getenv(Module_Resolver::CUSTOM_MODULE_PATHS);
        if (!$paths) {
            return $custom_module_paths;
        }
        foreach (explode(',', $paths) as $path) {
            $custom_module_paths[$this->find_vendor_and_module_name_from_path(trim($path))] = $path;
        }
        return $custom_module_paths;
    }
    /**
     * Find vendor and module name from path.
     *
     *
     */
    private function find_vendor_and_module_name_from_path(string $path): string
    {
        $path = str_replace(DIRECTORY_SEPARATOR . Module_Resolver::TEST_MFTF_PATTERN, '', $path);
        return $this->find_vendor_name_from_path($path) . '_' . basename($path);
    }
    /**
     * Find vendor name from path.
     *
     *
     */
    private function find_vendor_name_from_path(string $path): string
    {
        $possible_vendor_name = 'UnknownVendor';
        $dir_paths = [Module_Resolver::VENDOR, Module_Resolver::APP_CODE, Module_Resolver::DEV_TESTS];
        foreach ($dir_paths as $dir_path) {
            $regex = '~.+\/' . $dir_path . "\\/(?<" . Module_Resolver::VENDOR . ">[^\\/]+)\\/.+~";
            $match = [];
            preg_match($regex, $path, $match);
            if (isset($match[Module_Resolver::VENDOR])) {
                return ucfirst($match[Module_Resolver::VENDOR]);
            }
        }
        return $possible_vendor_name;
    }
    /**
     * Get admin token.
     *
     * @throws FastFailException
     */
    public function get_admin_token(): string
    {
        return Web_Api_Auth::get_admin_token();
    }
}