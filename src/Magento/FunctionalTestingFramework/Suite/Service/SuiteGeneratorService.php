<?php

/**
 * Copyright 2021 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Suite\Service;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Reference_Exception;
use Magento\Functional_Testing_Framework\Suite\Suite_Generator;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
use Magento\Functional_Testing_Framework\Util\Test_Generator;
use Symfony\Component\Yaml\Yaml;
/**
 * Class SuiteGeneratorService
 */
class Suite_Generator_Service
{
    /**
     * Singleton SuiteGeneratorService Instance.
     */
    private static ?\Magento\Functional_Testing_Framework\Suite\Service\Suite_Generator_Service $INSTANCE = null;
    /**
     * SuiteGeneratorService constructor.
     */
    private function __construct()
    {
    }
    /**
     * Get CestFileCreatorUtil instance.
     */
    public static function get_instance(): Suite_Generator_Service
    {
        if (!self::$INSTANCE) {
            self::$INSTANCE = new Suite_Generator_Service();
        }
        return self::$INSTANCE;
    }
    /**
     * Function which takes the current config.yml array and clears any previous configuration for suite group object
     * files.
     *
     * @throws TestFrameworkException
     */
    public function clear_previous_session_config_entries(): void
    {
        $yml_array = self::get_yaml_file_contents();
        $new_yml_array = $yml_array;
        // if the yaml entries haven't already been cleared
        if (array_key_exists(Suite_Generator::YAML_EXTENSIONS_TAG, $yml_array)) {
            $yml_entries = $yml_array[Suite_Generator::YAML_EXTENSIONS_TAG][Suite_Generator::YAML_ENABLED_TAG];
            foreach ($yml_entries as $key => $entry) {
                if (preg_match('/(Group\\\\.*)/', (string) $entry)) {
                    unset($new_yml_array[Suite_Generator::YAML_EXTENSIONS_TAG][Suite_Generator::YAML_ENABLED_TAG][$key]);
                }
            }
            // needed for proper yml file generation based on indices
            $new_yml_array[Suite_Generator::YAML_EXTENSIONS_TAG][Suite_Generator::YAML_ENABLED_TAG] = array_values($new_yml_array[Suite_Generator::YAML_EXTENSIONS_TAG][Suite_Generator::YAML_ENABLED_TAG]);
        }
        if (array_key_exists(Suite_Generator::YAML_GROUPS_TAG, $new_yml_array)) {
            unset($new_yml_array[Suite_Generator::YAML_GROUPS_TAG]);
        }
        $yml_text = Suite_Generator::YAML_COPYRIGHT_TEXT . Yaml::dump($new_yml_array, 10);
        file_put_contents(self::get_yaml_config_file_path() . Suite_Generator::YAML_CODECEPTION_CONFIG_FILENAME, $yml_text);
    }
    /**
     * Function which accepts a suite name and suite path and appends a new group entry to the codeception.yml.dist
     * file in order to register the set of tests as a new group. Also appends group object location if required
     * by suite.
     *
     *
     * @throws TestFrameworkException
     */
    public function append_entries_to_config(string $suite_name, string $suite_path, ?string $group_namespace): void
    {
        $relative_suite_path = substr($suite_path, strlen(TESTS_BP));
        $relative_suite_path = ltrim($relative_suite_path, DIRECTORY_SEPARATOR);
        $yml_array = self::get_yaml_file_contents();
        if (!array_key_exists(Suite_Generator::YAML_GROUPS_TAG, $yml_array)) {
            $yml_array[Suite_Generator::YAML_GROUPS_TAG] = [];
        }
        if ($group_namespace) {
            $yml_array[Suite_Generator::YAML_EXTENSIONS_TAG][Suite_Generator::YAML_ENABLED_TAG][] = $group_namespace;
        }
        $yml_array[Suite_Generator::YAML_GROUPS_TAG][$suite_name] = [$relative_suite_path];
        $yml_text = Suite_Generator::YAML_COPYRIGHT_TEXT . Yaml::dump($yml_array, 10);
        file_put_contents(self::get_yaml_config_file_path() . Suite_Generator::YAML_CODECEPTION_CONFIG_FILENAME, $yml_text);
    }
    /**
     * Function which takes a string which is the desired output directory (under _generated) and an array of tests
     * relevant to the suite to be generated. The function takes this information and creates a new instance of the
     * test generator which is then called to create all the test files for the suite.
     *
     *
     * @throws TestReferenceException
     */
    public function generate_relevant_group_tests(string $path, array $tests): void
    {
        $test_generator = Test_Generator::get_instance($path, $tests);
        $test_generator->create_all_test_files(null, []);
    }
    /**
     * Function to return contents of codeception.yml file for config changes.
     *
     * @throws TestFrameworkException
     */
    private static function get_yaml_file_contents(): array
    {
        $config_yml_file = self::get_yaml_config_file_path() . Suite_Generator::YAML_CODECEPTION_CONFIG_FILENAME;
        $default_config_yml_file = self::get_yaml_config_file_path() . Suite_Generator::YAML_CODECEPTION_DIST_FILENAME;
        if (file_exists($config_yml_file)) {
            $yml_contents = file_get_contents($config_yml_file);
        } else {
            $yml_contents = file_get_contents($default_config_yml_file);
        }
        return Yaml::parse($yml_contents) ?? [];
    }
    /**
     * Static getter for the Config yml filepath (as path cannot be stored in a const).
     *
     * @throws TestFrameworkException
     */
    private static function get_yaml_config_file_path(): string
    {
        return File_Path_Formatter::format(TESTS_BP);
    }
}