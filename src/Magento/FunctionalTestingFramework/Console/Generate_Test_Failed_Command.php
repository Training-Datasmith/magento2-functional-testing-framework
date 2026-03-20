<?php

/**
 * Copyright 2021 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Console;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Symfony\Component\Console\Input\Array_Input;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
class Generate_Test_Failed_Command extends Base_Generate_Command
{
    /**
     * Default Test group to signify not in suite
     */
    public const DEFAULT_TEST_GROUP = 'default';
    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $this->set_name('generate:failed')->set_description('Generate a set of tests failed');
        parent::configure();
    }
    /**
     * Executes the current command.
     *
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $force = $input->get_option('force');
        $debug = $input->get_option('debug') ?? Mftf_Application_Config::LEVEL_DEVELOPER;
        // for backward compatibility
        $allow_skipped = $input->get_option('allow-skipped');
        $verbose = $output->is_verbose();
        // Create Mftf Configuration
        Mftf_Application_Config::create($force, Mftf_Application_Config::EXECUTION_PHASE, $verbose, $debug, $allow_skipped);
        $tests_failed_file = $this->get_tests_output_dir() . self::FAILED_FILE;
        $tests_re_run_file = $this->get_tests_output_dir() . 'rerun_tests';
        $test_configuration = $this->get_failed_test_list($tests_failed_file, $tests_re_run_file);
        if ($test_configuration === null) {
            // No failed tests found, no tests generated
            $this->remove_generated_directory($output, $verbose);
            return 0;
        }
        $command = $this->get_application()->find('generate:tests');
        $args = ['--tests' => $test_configuration, '--force' => $force, '--remove' => true, '--debug' => $debug, '--allow-skipped' => $allow_skipped, '-v' => $verbose];
        $command->run(new Array_Input($args), $output);
        $output->writeln('Test Failed Generated, now run:failed command');
        return 0;
    }
    /**
     * Returns a json string of tests that failed on the last run
     *
     * @return string
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function get_failed_test_list($tests_failed_file, $tests_re_run_file)
    {
        $failed_test_details = ['tests' => [], 'suites' => []];
        $test_list = $this->read_failed_test_file($tests_failed_file);
        if (!empty($test_list)) {
            foreach ($test_list as $test) {
                if (!empty($test)) {
                    $this->write_failed_test_to_file($test, $tests_re_run_file);
                    $test_info = explode(DIRECTORY_SEPARATOR, (string) $test);
                    $test_name = isset($test_info[count($test_info) - 1][1]) ? explode(':', $test_info[count($test_info) - 1])[1] : [];
                    $suite_name = $test_info[count($test_info) - 2] ?? [];
                    if ($suite_name === self::DEFAULT_TEST_GROUP) {
                        array_push($failed_test_details['tests'], $test_name);
                    } else {
                        $suite_name = $this->sanitize_suite_name($suite_name);
                        $failed_test_details['suites'] = array_merge_recursive($failed_test_details['suites'], [$suite_name => [$test_name]]);
                    }
                }
            }
        }
        if (empty($failed_test_details['tests']) & empty($failed_test_details['suites'])) {
            return null;
        }
        if (empty($failed_test_details['tests'])) {
            $failed_test_details['tests'] = null;
        }
        if (empty($failed_test_details['suites'])) {
            $failed_test_details['suites'] = null;
        }
        return json_encode($failed_test_details);
    }
    /**
     * Trim potential suite_parallel_0_G to suite_parallel
     *
     * @param string $suiteName
     * @return string
     */
    private function sanitize_suite_name(string|array $suite_name): string|array
    {
        $suite_name_array = explode('_', $suite_name);
        if (array_pop($suite_name_array) === 'G') {
            if (is_numeric(array_pop($suite_name_array))) {
                $suite_name = implode('_', $suite_name_array);
            }
        }
        return $suite_name;
    }
    /**
     * Returns an array of tests read from the failed test file in _output
     *
     * @param string $filePath
     * @return array|boolean
     */
    public function read_failed_test_file($file_path)
    {
        if (realpath($file_path)) {
            return file($file_path, FILE_IGNORE_NEW_LINES);
        }
        return '';
    }
    /**
     * Writes the test name to a file if it does not already exist
     *
     * @param string $filePath
     */
    public function write_failed_test_to_file(string $test, $file_path): void
    {
        if (file_exists($file_path)) {
            if (!str_contains(file_get_contents($file_path), $test)) {
                file_put_contents($file_path, "\n" . $test, FILE_APPEND);
            }
        } else {
            file_put_contents($file_path, $test . "\n");
        }
    }
}