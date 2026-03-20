<?php

/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Console;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Exceptions\Fast_Fail_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Xml_Exception;
use Magento\Functional_Testing_Framework\Suite\Suite_Generator;
use Magento\Functional_Testing_Framework\Test\Handlers\Test_Object_Handler;
use Magento\Functional_Testing_Framework\Test\Objects\Action_Object;
use Magento\Functional_Testing_Framework\Util\Generation_Error_Handler;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
use Magento\Functional_Testing_Framework\Util\Manifest\Test_Manifest_Factory;
use Magento\Functional_Testing_Framework\Util\Script\Script_Util;
use Magento\Functional_Testing_Framework\Util\Script\Test_Dependency_Util;
use Magento\Functional_Testing_Framework\Util\Test_Generator;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Finder\Finder;
/**
 * @SuppressWarnings(PHPMD)
 */
class Generate_Tests_Command extends Base_Generate_Command
{
    public const PARALLEL_DEFAULT_TIME = 10;
    public const EXTENDS_REGEX_PATTERN = '/extends=["\']([^\'"]*)/';
    public const ACTIONGROUP_REGEX_PATTERN = '/ref=["\']([^\'"]*)/';
    public const TEST_DEPENDENCY_FILE_LOCATION_STANDALONE = 'dev/tests/_output/test-dependencies.json';
    public const TEST_DEPENDENCY_FILE_LOCATION_EMBEDDED = 'dev/tests/acceptance/tests/_output/test-dependencies.json';
    private ?\Magento\Functional_Testing_Framework\Util\Script\Script_Util $script_util = null;
    private ?\Magento\Functional_Testing_Framework\Util\Script\Test_Dependency_Util $test_dependency_util = null;
    /**
     * @var array
     */
    private $module_name_to_path;
    private ?array $module_name_to_composer_name = null;
    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $this->set_name('generate:tests')->set_description('Run validation and generate all test files and suites based on xml declarations')->add_usage('AdminLoginTest')->add_argument('name', Input_Argument::OPTIONAL | Input_Argument::IS_ARRAY, 'name(s) of specific tests to generate')->add_option('config', 'c', Input_Option::VALUE_REQUIRED, 'default, singleRun, or parallel', 'default')->add_option('time', 'i', Input_Option::VALUE_REQUIRED, 'Used in combination with a parallel configuration, determines desired group size (in minutes)' . PHP_EOL . 'Option "--time" will be the default and the default value is ' . self::PARALLEL_DEFAULT_TIME . ' when neither "--time" nor "--groups" is specified')->add_option('groups', 'g', Input_Option::VALUE_REQUIRED, 'Used in combination with a parallel configuration, determines desired number of groups' . PHP_EOL . 'Options "--time" and "--groups" are mutually exclusive and only one should be used')->add_option('tests', 't', Input_Option::VALUE_REQUIRED, 'A parameter accepting a JSON string or JSON file path used to determine the test configuration')->add_option('filter', null, Input_Option::VALUE_IS_ARRAY | Input_Option::VALUE_OPTIONAL, 'Option to filter tests to be generated.' . PHP_EOL . '<info>Template:</info> <filterName>:<filterValue>' . PHP_EOL . '<info>Existing filter types:</info> severity.' . PHP_EOL . '<info>Existing severity values:</info> BLOCKER, CRITICAL, MAJOR, AVERAGE, MINOR.' . PHP_EOL . '<info>Example:</info> --filter=severity:CRITICAL' . ' --filter=includeGroup:customer --filter=excludeGroup:customerAnalytics' . PHP_EOL)->add_option('path', 'p', Input_Option::VALUE_REQUIRED, 'path to a test names file.')->add_option('log', 'l', Input_Option::VALUE_REQUIRED, 'Generate metadata files during test generation.');
        parent::configure();
    }
    /**
     * Executes the current command.
     *
     * @return void|integer
     * @throws TestFrameworkException
     * @throws FastFailException
     * @throws XmlException
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $this->set_io_style($input, $output);
        $tests = $input->get_argument('name');
        $config = $input->get_option('config');
        $json = $input->get_option('tests');
        // for backward compatibility
        $force = $input->get_option('force');
        $time = $input->get_option('time');
        //$time = $input->getOption('time') * 60 * 1000; // convert from minutes to milliseconds
        $groups = $input->get_option('groups');
        $debug = $input->get_option('debug') ?? Mftf_Application_Config::LEVEL_DEVELOPER;
        // for backward compatibility
        $remove = $input->get_option('remove');
        $verbose = $output->is_verbose();
        $allow_skipped = $input->get_option('allow-skipped');
        $log = $input->get_option('log');
        $filters = $input->get_option('filter');
        foreach ($filters as $filter) {
            [$filter_type, $filter_value] = explode(':', (string) $filter);
            $filter_list[$filter_type][] = $filter_value;
        }
        $path = $input->get_option('path');
        // check filepath is given for generate test file
        if (!empty($path)) {
            $tests = $this->generate_test_file_from_path($path);
        }
        // Set application configuration so we can references the user options in our framework
        try {
            Mftf_Application_Config::create($force, Mftf_Application_Config::GENERATION_PHASE, $verbose, $debug, $allow_skipped, $filter_list ?? []);
        } catch (\Exception $exception) {
            $this->io_style->error('Test generation failed.' . PHP_EOL . $exception->get_message());
            return 1;
        }
        if ($json !== null && is_file($json)) {
            $json = file_get_contents($json);
        }
        if (!empty($tests)) {
            $json = $this->get_test_and_suite_configuration($tests);
        }
        if ($json !== null && !json_decode((string) $json)) {
            // stop execution if we have failed to properly parse any json passed in by the user
            throw new Test_Framework_Exception('JSON could not be parsed: ' . json_last_error_msg());
        }
        if ($config === 'parallel') {
            [$config, $config_number] = $this->parse_config_parallel_options($time, $groups);
        }
        // Remove previous GENERATED_DIR if --remove option is used
        if ($remove) {
            $this->remove_generated_directory($output, $verbose);
        }
        try {
            $test_configuration = $this->create_test_configuration($json, $tests);
            // create our manifest file here
            $test_manifest = Test_Manifest_Factory::make_manifest($config, $test_configuration['suites']);
            try {
                if (empty($tests) || !empty($test_configuration['tests'])) {
                    // $testConfiguration['tests'] cannot be empty if $tests is not empty
                    Test_Generator::get_instance(null, $test_configuration['tests'])->create_all_test_files($test_manifest);
                } elseif (empty($test_configuration['suites'])) {
                    throw new Fast_Fail_Exception(!empty(Generation_Error_Handler::get_instance()->get_all_errors()) ? Generation_Error_Handler::get_instance()->get_all_error_messages() : 'Invalid input');
                }
            } catch (Fast_Fail_Exception $e) {
                throw $e;
            } catch (\Exception $e) {
            }
            if (str_contains((string) $config, 'parallel')) {
                $test_manifest->create_test_groups($config_number);
            }
            Suite_Generator::get_instance()->generate_all_suites($test_manifest);
            $test_manifest->generate();
            Suite_Generator::get_instance()->generate_testgroupmembership($test_manifest);
        } catch (\Exception $e) {
            if (!empty(Generation_Error_Handler::get_instance()->get_all_errors())) {
                Generation_Error_Handler::get_instance()->print_error_summary();
            }
            $message = $e->get_message() . PHP_EOL;
            $message .= !empty($filters) ? 'Filter(s): ' . implode(', ', $filters) . PHP_EOL : '';
            $message .= !empty($tests) ? 'Test name(s): ' . implode(', ', $tests) . PHP_EOL : '';
            $message .= !empty($json) && empty($tests) ? 'Test configuration: ' . $json . PHP_EOL : '';
            $this->io_style->note($message);
            return 1;
        }
        // check test dependencies log command
        if (!empty($log)) {
            if ($log === 'testEntityJson') {
                $this->get_test_entity_json($filter_list ?? [], $tests);
                $test_dependency_file_location = self::TEST_DEPENDENCY_FILE_LOCATION_EMBEDDED;
                if (isset($_ENV['MAGENTO_BP'])) {
                    $test_dependency_file_location = self::TEST_DEPENDENCY_FILE_LOCATION_STANDALONE;
                }
                $output->writeln('Test dependencies file created, Located in: ' . $test_dependency_file_location);
            } else {
                $output->writeln('Wrong parameter for log (-l) option, accepted parameter are: testEntityJson' . PHP_EOL);
            }
        }
        if (empty(Generation_Error_Handler::get_instance()->get_all_errors())) {
            $output->writeln('Generate Tests Command Run' . PHP_EOL);
            return 0;
        }
        Generation_Error_Handler::get_instance()->print_error_summary();
        $output->writeln('Generate Tests Command Run (with errors)' . PHP_EOL);
        return 1;
    }
    /**
     * Function which builds up a configuration including test and suites for consumption of Magento generation methods.
     *
     * @param string $json
     * @return array
     * @throws FastFailException
     * @throws TestFrameworkException
     */
    private function create_test_configuration($json, array $tests)
    {
        $test_configuration = [];
        $test_configuration['tests'] = $tests;
        $test_configuration['suites'] = [];
        $test_configuration = $this->parse_tests_config_json($json, $test_configuration);
        // if we have references to specific tests, we resolve the test objects and pass them to the config
        if (!empty($test_configuration['tests'])) {
            $test_objects = [];
            foreach ($test_configuration['tests'] as $test) {
                try {
                    $test_objects[$test] = Test_Object_Handler::get_instance()->get_object($test);
                } catch (Fast_Fail_Exception $e) {
                    throw $e;
                } catch (\Exception $e) {
                    $message = "Unable to create test object {$test} from test configuration. " . $e->get_message();
                    Logging_Util::get_instance()->get_logger(self::class)->error($message);
                    if (Mftf_Application_Config::get_config()->verbose_enabled() && Mftf_Application_Config::get_config()->get_phase() === Mftf_Application_Config::GENERATION_PHASE) {
                        print $message;
                    }
                    Generation_Error_Handler::get_instance()->add_error('test', $test, $message);
                }
            }
            $test_configuration['tests'] = $test_objects;
        }
        return $test_configuration;
    }
    /**
     * Function which takes a json string of potential custom configuration and parses/validates the resulting json
     * passed in by the user. The result is a testConfiguration array.
     *
     * @param string $json
     */
    private function parse_tests_config_json($json, array $test_configuration): array
    {
        if ($json === null) {
            return $test_configuration;
        }
        $json_test_configuration = [];
        $test_config_array = json_decode($json, true);
        $json_test_configuration['tests'] = $test_config_array['tests'] ?? null;
        $json_test_configuration['suites'] = $test_config_array['suites'] ?? null;
        return $json_test_configuration;
    }
    /**
     * Parse console command options --time and/or --groups and return config type and config number in an array
     *
     * @param mixed $time
     * @param mixed $groups
     * @throws FastFailException
     */
    private function parse_config_parallel_options($time, $groups): array
    {
        $config = null;
        $config_number = null;
        if ($time !== null && $groups !== null) {
            throw new Fast_Fail_Exception("'time' and 'groups' options are mutually exclusive. " . "Only one can be specified for 'config parallel'");
        }
        if ($time === null && $groups === null) {
            $config = 'parallelByTime';
            $config_number = self::PARALLEL_DEFAULT_TIME * 60 * 1000;
            // convert from minutes to milliseconds
        } elseif ($time !== null && is_numeric($time)) {
            $time = $time * 60 * 1000;
            // convert from minutes to milliseconds
            if (is_int($time) && $time > 0) {
                $config = 'parallelByTime';
                $config_number = $time;
            }
        } elseif ($groups !== null && is_numeric($groups)) {
            $groups = $groups * 1;
            if (is_int($groups) && $groups > 0) {
                $config = 'parallelByGroup';
                $config_number = $groups;
            }
        }
        if ($config && $config_number) {
            return [$config, $config_number];
        }
        if ($time !== null) {
            throw new Fast_Fail_Exception("'time' option must be an integer and greater than 0");
        }
        throw new Fast_Fail_Exception("'groups' option must be an integer and greater than 0");
    }
    /**
     * console command options --log and create test dependencies in json file
     * @throws TestFrameworkException
     * @throws XmlException|FastFailException
     */
    private function get_test_entity_json(array $filter_list, array $tests = []): void
    {
        $test_dependencies = $this->get_test_dependencies($filter_list, $tests);
        $this->array2Json($test_dependencies);
    }
    /**
     * Function responsible for getting test dependencies in array
     * @throws FastFailException
     * @throws TestFrameworkException
     * @throws XmlException
     */
    public function get_test_dependencies(array $filter_list, array $tests = []): array
    {
        $this->script_util = new Script_Util();
        $this->test_dependency_util = new Test_Dependency_Util();
        $all_modules = $this->script_util->get_all_module_paths();
        if (!class_exists('\Magento\Framework\Component\ComponentRegistrar')) {
            throw new Test_Framework_Exception('TEST DEPENDENCY CHECK ABORTED: MFTF must be attached or pointing to Magento codebase.');
        }
        $registrar = new \Magento\Framework\Component\Component_Registrar();
        $this->module_name_to_path = $registrar->get_paths(\Magento\Framework\Component\Component_Registrar::MODULE);
        $this->module_name_to_composer_name = $this->test_dependency_util->build_module_name_to_composer_name($this->module_name_to_path);
        if (!empty($tests)) {
            # specific test dependencies will be generate.
            $test_xml_files = $this->script_util->get_module_xml_files_by_test_names($tests);
        } else {
            $file_paths = [DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR];
            // These files can contain references to other modules.
            $test_xml_files = $this->script_util->get_module_xml_files_by_scope($all_modules, $file_paths[0]);
        }
        [$test_dependencies, $extended_test_mapping] = $this->find_test_dependent_module($test_xml_files);
        return $this->test_dependency_util->merge_dependencies_for_extending_tests($test_dependencies, $filter_list, $extended_test_mapping);
    }
    /**
     * Finds all test dependencies in given set of files
     * @throws FastFailException
     * @throws XmlException
     */
    private function find_test_dependent_module(Finder $files): array
    {
        $test_dependencies = [];
        $extended_tests = [];
        $extended_test_mapping = [];
        foreach ($files as $file_path) {
            $all_entities = [];
            $file_path = $file_path->get_pathname();
            $module_name = $this->test_dependency_util->get_module_name($file_path, $this->module_name_to_path);
            // Not a module, is either dev/tests/acceptance or loose folder with test materials
            if ($module_name == null) {
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
            $all_entities = array_merge($all_entities, $this->script_util->resolve_entity_references($brace_references[0], $contents));
            // resolve parameterized references
            $all_entities = array_merge($all_entities, $this->script_util->resolve_parametrized_references($brace_references[2], $contents));
            // resolve entity by names
            $all_entities = array_merge($all_entities, $this->script_util->resolve_entity_by_names($action_group_references[1]));
            // resolve entity by names
            $all_entities = array_merge($all_entities, $this->script_util->resolve_entity_by_names($extend_references[1]));
            $modules_referenced_in_test = $this->test_dependency_util->get_module_dependencies_from_references($all_entities, $this->module_name_to_composer_name, $this->module_name_to_path);
            if (!empty($modules_referenced_in_test)) {
                $document = new \Dom_Document();
                $document->load_xml($contents);
                $test_file = $document->get_elements_by_tag_name('test')->item(0);
                $test_name = $test_file->get_attribute('name');
                # check any test extends on with this test.
                $extended_test = $test_file->get_attribute('extends') ?? '';
                if (!empty($extended_test)) {
                    $extended_tests[] = $extended_test;
                    $extended_test_mapping[] = ['child_test_name' => $test_name, 'parent_test_name' => $extended_test];
                }
                $flattened_dependency_map = array_values(array_unique(call_user_func_array(array_merge(...), array_values($modules_referenced_in_test))));
                $suite_name = $this->get_suite_name($test_name);
                $full_name = "Magento\\AcceptanceTest\\_" . $suite_name . "\\Backend\\" . $test_name . 'Cest.' . $test_name;
                $dependency_map = ['file_path' => $file_path, 'full_name' => $full_name, 'test_name' => $test_name, 'test_modules' => $flattened_dependency_map];
                $test_dependencies[] = $dependency_map;
            }
        }
        if (!empty($extended_tests)) {
            [$extended_dependencies, $temp_extended_test_mapping] = $this->get_extended_test_dependencies($extended_tests);
            $test_dependencies = array_merge($test_dependencies, $extended_dependencies);
            $extended_test_mapping = array_merge($extended_test_mapping, $temp_extended_test_mapping);
        }
        return [$test_dependencies, $extended_test_mapping];
    }
    /**
     * Finds all extended test dependencies in given set of files
     * @throws FastFailException
     * @throws XmlException
     */
    private function get_extended_test_dependencies(array $extended_tests): array
    {
        $test_xml_files = $this->script_util->get_module_xml_files_by_test_names($extended_tests);
        return $this->find_test_dependent_module($test_xml_files);
    }
    /**
     * Create json file of test dependencies
     */
    private function array2Json(array $array): void
    {
        $test_dependency_file_location = self::TEST_DEPENDENCY_FILE_LOCATION_EMBEDDED;
        if (isset($_ENV['MAGENTO_BP'])) {
            $test_dependency_file_location = self::TEST_DEPENDENCY_FILE_LOCATION_STANDALONE;
        }
        $test_dependency_file_location_dir = dirname($test_dependency_file_location);
        if (!is_dir($test_dependency_file_location_dir)) {
            mkdir($test_dependency_file_location_dir, 0777, true);
        }
        $file = fopen($test_dependency_file_location, 'w');
        $json = json_encode($array, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        fwrite($file, $json);
        fclose($file);
    }
    /**
     * Get suite name.
     * @return integer|mixed|string
     * @throws FastFailException
     */
    private function get_suite_name(string $test_name)
    {
        $suite_name = json_decode($this->get_test_and_suite_configuration([$test_name]), true)['suites'] ?? 'default';
        if (is_array($suite_name)) {
            return array_keys($suite_name)[0];
        }
        return $suite_name;
    }
    /**
     * @throws TestFrameworkException
     */
    private function generate_test_file_from_path(string $path): array
    {
        if (!file_exists($path)) {
            throw new Test_Framework_Exception("Could not find file {$path}. Check the path and try again.");
        }
        $test_names = file($path, FILE_IGNORE_NEW_LINES);
        $tests = [];
        foreach ($test_names as $test_name) {
            if (empty(trim($test_name))) {
                continue;
            }
            $test_name_array = explode(' ', trim($test_name));
            $tests = array_merge($tests, $test_name_array);
        }
        return $tests;
    }
}