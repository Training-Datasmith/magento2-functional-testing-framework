<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Credential_Store;
use Magento\Functional_Testing_Framework\Exceptions\Fast_Fail_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
use Magento\Functional_Testing_Framework\Util\Module_Resolver\Alphabetic_Sequence_Sorter;
use Magento\Functional_Testing_Framework\Util\Module_Resolver\Module_Resolver_Service;
use Magento\Functional_Testing_Framework\Util\Module_Resolver\Sequence_Sorter_Interface;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
use Magento\Functional_Testing_Framework\Util\Path\Url_Formatter;
/**
 * Class ModuleResolver, resolve module path based on enabled modules of target Magento instance.
 *
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Module_Resolver
{
    /**
     * Environment field name for module allowlist.
     */
    public const MODULE_ALLOWLIST = 'MODULE_ALLOWLIST';
    /**
     * Environment field name for custom module paths.
     */
    public const CUSTOM_MODULE_PATHS = 'CUSTOM_MODULE_PATHS';
    /**
     * List of path types present in Magento Component Registrar
     */
    public const PATHS = ['module', 'library', 'theme', 'language'];
    /**
     * Magento Registrar Class
     */
    public const REGISTRAR_CLASS = "\\Magento\\Framework\\Component\\ComponentRegistrar";
    public const TEST_MFTF_PATTERN = 'Test' . DIRECTORY_SEPARATOR . 'Mftf';
    public const VENDOR = 'vendor';
    public const APP_CODE = 'app' . DIRECTORY_SEPARATOR . 'code';
    public const DEV_TESTS = 'dev' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'acceptance' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'functional';
    /**
     * Enabled modules.
     *
     * @var array|null
     */
    protected $enabled_modules;
    /**
     * Paths for enabled modules.
     *
     * @var array|null
     */
    protected $enabled_module_paths;
    /**
     * Name and path for enabled modules
     *
     * @var array|null
     */
    protected $enabled_module_name_and_paths;
    /**
     * Configuration instance.
     *
     * @var \Magento\FunctionalTestingFramework\Config\DataInterface
     */
    protected $configuration;
    /**
     * Admin url for integration token.
     *
     * @var string
     */
    protected $admin_token_url = 'rest/V1/integration/admin/token';
    /**
     * Url for with module list.
     *
     * @var string
     */
    protected $module_url = 'rest/V1/modules';
    /**
     * Url for magento version information.
     *
     * @var string
     */
    protected $version_url = 'magento_version';
    /**
     * List of known directory that does not map to a Magento module.
     *
     * @var array
     */
    protected $known_directories = ['SampleData' => 1];
    /**
     * ModuleResolver instance.
     */
    private static ?\Magento\Functional_Testing_Framework\Util\Module_Resolver $instance = null;
    /**
     * SequenceSorter instance.
     *
     * @var ModuleResolver\SequenceSorterInterface
     */
    protected $sequence_sorter;
    /**
     * List of module names that will be ignored.
     *
     * @var array
     */
    protected $module_blocklist = ['SampleTests', 'SampleTemplates'];
    /**
     * Get ModuleResolver instance.
     *
     * @return ModuleResolver
     */
    public static function get_instance()
    {
        if (!self::$instance) {
            self::$instance = new Module_Resolver();
        }
        return self::$instance;
    }
    /**
     * ModuleResolver constructor.
     */
    private function __construct()
    {
        $object_manager = \Magento\Functional_Testing_Framework\Object_Manager_Factory::get_object_manager();
        if (Mftf_Application_Config::get_config()->get_phase() === Mftf_Application_Config::UNIT_TEST_PHASE) {
            $this->sequence_sorter = $object_manager->get(Alphabetic_Sequence_Sorter::class);
        } else {
            $this->sequence_sorter = $object_manager->get(Sequence_Sorter_Interface::class);
        }
    }
    /**
     * Return an array of enabled modules of target Magento instance.
     *
     * @return array
     * @throws TestFrameworkException
     * @throws FastFailException
     */
    public function get_enabled_modules()
    {
        if (isset($this->enabled_modules)) {
            return $this->enabled_modules;
        }
        if (Mftf_Application_Config::get_config()->get_phase() === Mftf_Application_Config::GENERATION_PHASE) {
            $this->print_magento_version_info();
        }
        $token = Module_Resolver_Service::get_instance()->get_admin_token();
        $url = Url_Formatter::format(getenv('MAGENTO_BASE_URL')) . $this->module_url;
        $headers = ['Authorization: Bearer ' . $token];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        if (!$response) {
            $message = 'Could not retrieve Modules from Magento Instance.';
            $encrypted_secret = Credential_Store::get_instance()->get_secret('magento/MAGENTO_ADMIN_PASSWORD');
            $secret = Credential_Store::get_instance()->decrypt_secret_value($encrypted_secret);
            $context = ['Admin Module List Url' => $url, 'MAGENTO_ADMIN_USERNAME' => getenv('MAGENTO_ADMIN_USERNAME'), 'MAGENTO_ADMIN_PASSWORD' => $secret];
            throw new Fast_Fail_Exception($message, $context);
        }
        $this->enabled_modules = json_decode($response);
        return $this->enabled_modules;
    }
    /**
     * Return the modules path based on which modules are enabled in the target Magento instance.
     *
     * @param boolean $verbosePath
     * @return array
     * @throws TestFrameworkException
     * @throws FastFailException
     */
    public function get_modules_path($verbose_path = false)
    {
        if (isset($this->enabled_module_paths) && !$verbose_path) {
            return $this->enabled_module_paths;
        }
        if (isset($this->enabled_module_name_and_paths) && $verbose_path) {
            return $this->enabled_module_name_and_paths;
        }
        // Find test modules paths by searching patterns (Test/Mftf, etc)
        $all_module_paths = Module_Resolver_Service::get_instance()->aggregate_test_module_paths();
        // Find test modules paths by searching test composer.json files
        $composer_based_module_paths = $this->aggregate_test_module_paths_from_composer_json();
        // Find test modules paths by querying composer installed packages
        $composer_based_module_paths = array_merge($composer_based_module_paths, $this->aggregate_test_module_paths_from_composer_installer());
        // Merge test module paths altogether
        $all_module_paths = $this->merge_module_paths($all_module_paths, $composer_based_module_paths);
        // Normalize module names if we get registered module names from Magento system
        $all_module_paths = $this->normalize_module_names($all_module_paths);
        if (Mftf_Application_Config::get_config()->force_generate_enabled()) {
            $all_module_paths = $this->flip_and_sort_module_paths_array($all_module_paths, true);
            $this->enabled_module_paths = $this->apply_custom_module_methods($all_module_paths);
            return $this->enabled_module_paths;
        }
        $enabled_modules = array_merge($this->get_enabled_modules(), $this->get_module_allowlist());
        $enabled_directory_paths = $this->flip_and_filter_module_paths_array($all_module_paths, $enabled_modules);
        $this->enabled_module_paths = $this->apply_custom_module_methods($enabled_directory_paths);
        return $this->enabled_module_paths;
    }
    /**
     * Sort files according module sequence.
     *
     * @return array
     */
    public function sort_files_by_module_sequence(array $files)
    {
        return $this->sequence_sorter->sort($files);
    }
    /**
     * Return an array of module allowlist that not exist in target Magento instance.
     */
    protected function get_module_allowlist(): array
    {
        $module_allowlist = getenv(self::MODULE_ALLOWLIST);
        if (empty($module_allowlist)) {
            return [];
        }
        return array_map(trim(...), explode(',', $module_allowlist));
    }
    /**
     * Aggregate all code paths with test module composer json files
     *
     * @throws TestFrameworkException
     */
    private function aggregate_test_module_paths_from_composer_json(): array
    {
        // Define the module paths
        $magento_base_code_path = File_Path_Formatter::format(MAGENTO_BP, false);
        // Define the module paths from default TESTS_MODULE_PATH
        $module_path = defined('TESTS_MODULE_PATH') ? TESTS_MODULE_PATH : TESTS_BP;
        $module_path = File_Path_Formatter::format($module_path, false);
        $search_code_paths = [$magento_base_code_path . DIRECTORY_SEPARATOR . self::DEV_TESTS];
        // Add TESTS_MODULE_PATH if it's not included
        if (array_search($module_path, $search_code_paths) === false) {
            $search_code_paths[] = $module_path;
        }
        return Module_Resolver_Service::get_instance()->get_composer_json_test_module_paths($search_code_paths);
    }
    /**
     * Aggregate all code paths with composer installed test modules
     */
    private function aggregate_test_module_paths_from_composer_installer(): array
    {
        // Define the module paths
        $magento_base_code_path = MAGENTO_BP;
        $composer_file = $magento_base_code_path . DIRECTORY_SEPARATOR . 'composer.json';
        return Module_Resolver_Service::get_instance()->get_composer_installed_test_module_paths($composer_file);
    }
    /**
     * Flip and filter module code paths
     *
     * @param array $objectArray
     * @return array
     */
    private function flip_and_filter_module_paths_array($object_array, array $filter_array)
    {
        $one_to_one_array = [];
        $one_to_many_array = [];
        // Filter array by enabled modules
        foreach ($object_array as $path => $modules) {
            if (!array_diff($modules, $filter_array) || count($modules) === 1 && isset($this->known_directories[$modules[0]])) {
                if (count($modules) === 1) {
                    $one_to_one_array[$path] = $modules[0];
                } else {
                    $one_to_many_array[$path] = $modules;
                }
            }
        }
        $flipped_array = [];
        // Set flipped array for "one path => one module" case first to maintain module sequencing
        foreach ($filter_array as $module_name) {
            $path = array_search($module_name, $one_to_one_array);
            if ($path !== false) {
                if (!str_contains((string) $module_name, '_')) {
                    $module_name = $this->find_vendor_name_from_path($path) . '_' . $module_name;
                }
                $flipped_array = $this->set_array_value_with_logging($flipped_array, $module_name, $path);
                unset($one_to_one_array[$path]);
            }
        }
        // Set flipped array for everything else
        return $this->flip_and_sort_module_paths_array(array_merge($one_to_one_array, $one_to_many_array), false, $flipped_array);
    }
    /**
     * Flip module code paths and optionally sort in alphabetical order
     *
     * @param array   $objectArray
     * @param array   $inFlippedArray
     * @return array
     */
    private function flip_and_sort_module_paths_array($object_array, bool $sort, $in_flipped_array = [])
    {
        $flipped_array = $in_flipped_array;
        // Set flipped array from object array
        foreach ($object_array as $path => $modules) {
            if (is_array($modules) && count($modules) > 1) {
                // The "one path => many module names" case is designed to be strictly used when it's
                // impossible to write tests in dedicated modules.
                // For now we will set module name based on path.
                // TODO: Consider saving all module names if this information is needed in the future.
                $module = $this->find_vendor_and_module_name_from_path($path);
            } elseif (is_array($modules)) {
                if (!str_contains((string) $modules[0], '_')) {
                    $module = $this->find_vendor_name_from_path($path) . '_' . $modules[0];
                } else {
                    $module = $modules[0];
                }
            } else if (!str_contains((string) $modules, '_')) {
                $module = $this->find_vendor_name_from_path($path) . '_' . $modules;
            } else {
                $module = $modules;
            }
            $flipped_array = $this->set_array_value_with_logging($flipped_array, $module, $path);
        }
        // Sort array in alphabetical order
        if ($sort) {
            ksort($flipped_array);
        }
        return $flipped_array;
    }
    /**
     * Set array value at index only if array value at index is not yet set, skip otherwise and log warning message
     *
     * @param string $index
     *
     */
    private function set_array_value_with_logging(array $in_array, $index, string $value): array
    {
        $out_array = $in_array;
        if (!isset($in_array[$index])) {
            $out_array[$index] = $value;
        } else {
            $warn_msg = 'Path: ' . $value . ' is ignored by ModuleResolver. ' . PHP_EOL . 'Path: ';
            $warn_msg .= $in_array[$index] . ' is set for Module: ' . $index . PHP_EOL;
            Logging_Util::get_instance()->get_logger(Module_Resolver::class)->warning($warn_msg);
        }
        return $out_array;
    }
    /**
     * Merge code paths
     *
     * @param array $oneToOneArray
     * @return array
     */
    private function merge_module_paths($one_to_one_array, array $one_to_many_array)
    {
        $merged_array = $one_to_one_array;
        foreach ($one_to_many_array as $path => $modules) {
            // Do nothing when array_key_exists
            if (!array_key_exists($path, $one_to_one_array)) {
                $merged_array[$path] = $modules;
            }
        }
        return $merged_array;
    }
    /**
     * Normalize module name if registered module list is available
     *
     * @param array $codePaths
     *
     * @return array
     */
    private function normalize_module_names($code_paths)
    {
        $all_components = Module_Resolver_Service::get_instance()->get_registered_module_list();
        if (empty($all_components)) {
            return $code_paths;
        }
        $normalized_code_paths = [];
        foreach ($code_paths as $path => $module_names) {
            $main_mod_name = array_search($path, $all_components);
            if ($main_mod_name) {
                $normalized_code_paths[$path] = [$main_mod_name];
            } else {
                $normalized_code_paths[$path] = $module_names;
            }
        }
        return $normalized_code_paths;
    }
    /**
     * Takes a multidimensional array of module paths and flattens to return a one dimensional array of test paths
     */
    private function flatten_all_module_paths(array $module_paths): array
    {
        $it = new \Recursive_Iterator_Iterator(new \Recursive_Array_Iterator($module_paths));
        $result_array = [];
        foreach ($it as $value) {
            $result_array[] = $value;
        }
        return $result_array;
    }
    /**
     * Executes a REST call to the supplied Magento Base Url for version information to display during generation
     */
    private function print_magento_version_info(): void
    {
        if (Mftf_Application_Config::get_config()->force_generate_enabled()) {
            return;
        }
        $url = Url_Formatter::format(getenv('MAGENTO_BASE_URL')) . $this->version_url;
        Logging_Util::get_instance()->get_logger(Module_Resolver::class)->info('Fetching version information.', ['url' => $url]);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        if (!$response) {
            $response = 'No version information available.';
        }
        Logging_Util::get_instance()->get_logger(Module_Resolver::class)->info('version information', ['version' => $response]);
    }
    /**
     * A wrapping method for any custom logic which needs to be applied to the module list
     *
     * @param array $modulesPath
     * @return string[]
     */
    protected function apply_custom_module_methods($modules_path)
    {
        $module_paths_result = $this->remove_blocklist_modules($modules_path);
        $custom_module_paths = Module_Resolver_Service::get_instance()->get_custom_module_paths();
        array_map(function (int|string $key, int|string $value): void {
            Logging_Util::get_instance()->get_logger(Module_Resolver::class)->info('including custom module', [$key => $value]);
        }, array_keys($custom_module_paths), $custom_module_paths);
        if (!isset($this->enabled_module_name_and_paths)) {
            $this->enabled_module_name_and_paths = array_merge($module_paths_result, $custom_module_paths);
        }
        return $this->flatten_all_module_paths(array_merge($module_paths_result, $custom_module_paths));
    }
    /**
     * Remove blocklist modules from input module paths.
     *
     * @param array $modulePaths
     * @return string[]
     */
    private function remove_blocklist_modules($module_paths)
    {
        $module_paths_result = $module_paths;
        foreach ($module_paths_result as $module_name => $module_path) {
            // Remove module if it is in blocklist
            if (in_array($module_name, $this->get_module_blocklist())) {
                unset($module_paths_result[$module_name]);
                Logging_Util::get_instance()->get_logger(Module_Resolver::class)->info('excluding module', ['module' => $module_name]);
            }
        }
        return $module_paths_result;
    }
    /**
     * Getter for moduleBlocklist.
     *
     * @return string[]
     */
    private function get_module_blocklist()
    {
        return $this->module_blocklist;
    }
    /**
     * Find vendor and module name from path
     *
     * @param string $path
     */
    private function find_vendor_and_module_name_from_path($path): string
    {
        $path = str_replace(DIRECTORY_SEPARATOR . self::TEST_MFTF_PATTERN, '', $path);
        return $this->find_vendor_name_from_path($path) . '_' . basename($path);
    }
    /**
     * Find vendor name from path
     *
     * @param string $path
     */
    private function find_vendor_name_from_path($path): string
    {
        $possible_vendor_name = 'UnknownVendor';
        $dir_paths = [self::VENDOR, self::APP_CODE, self::DEV_TESTS];
        foreach ($dir_paths as $dir_path) {
            $regex = '~.+\/' . $dir_path . "\\/(?<" . self::VENDOR . ">[^\\/]+)\\/.+~";
            $match = [];
            preg_match($regex, $path, $match);
            if (isset($match[self::VENDOR])) {
                return ucfirst($match[self::VENDOR]);
            }
        }
        return $possible_vendor_name;
    }
}