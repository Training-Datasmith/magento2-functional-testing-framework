<?php

/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Console;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Generation_Error_Handler;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
use Magento\Functional_Testing_Framework\Util\Test_Generator;
use Symfony\Component\Console\Input\Array_Input;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Process\Process;
/**
 * @SuppressWarnings(PHPMD)
 */
class Run_Test_Command extends Base_Generate_Command
{
    /**
     * The return code. Determined by all tests that run.
     */
    private int $return_code = 0;
    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $this->set_name('run:test')->set_description('generation and execution of test(s) defined in xml')->add_option('xml', 'xml', Input_Option::VALUE_NONE, 'creates xml report for executed test')->add_argument('name', Input_Argument::OPTIONAL | Input_Argument::IS_ARRAY, 'name of tests to generate and execute')->add_option('skip-generate', 'k', Input_Option::VALUE_NONE, 'skip generation and execute existing test')->add_option('tests', 't', Input_Option::VALUE_REQUIRED, 'A parameter accepting a JSON string or JSON file path used to determine the test configuration');
        parent::configure();
    }
    /**
     * Executes the current command.
     *
     * @throws \Exception
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $tests = $input->get_argument('name');
        $json = $input->get_option('tests');
        // for backward compatibility
        $skip_generation = $input->get_option('skip-generate');
        $force = $input->get_option('force');
        $remove = $input->get_option('remove');
        $debug = $input->get_option('debug') ?? Mftf_Application_Config::LEVEL_DEVELOPER;
        // for backward compatibility
        $allow_skipped = $input->get_option('allow-skipped');
        $verbose = $output->is_verbose();
        if ($skip_generation and $remove) {
            // "skip-generate" and "remove" options cannot be used at the same time
            throw new Test_Framework_Exception('"skip-generate" and "remove" options can not be used at the same time.');
        }
        // Set application configuration so we can references the user options in our framework
        Mftf_Application_Config::create($force, Mftf_Application_Config::EXECUTION_PHASE, $verbose, $debug, $allow_skipped);
        if ($json !== null) {
            if (is_file($json)) {
                $test_configuration = file_get_contents($json);
            } else {
                $test_configuration = $json;
            }
        }
        if (!empty($tests)) {
            $test_configuration = $this->get_test_and_suite_configuration($tests);
        }
        if ($test_configuration !== null && !json_decode((string) $test_configuration)) {
            // stop execution if we have failed to properly parse any json passed in by the user
            throw new Test_Framework_Exception('JSON could not be parsed: ' . json_last_error_msg());
        }
        $generation_error_code = 0;
        if (!$skip_generation) {
            $command = $this->get_application()->find('generate:tests');
            $args = ['--tests' => $test_configuration, '--force' => $force, '--remove' => $remove, '--debug' => $debug, '--allow-skipped' => $allow_skipped, '-v' => $verbose, ''];
            $command->run(new Array_Input($args), $output);
            if (!empty(Generation_Error_Handler::get_instance()->get_all_errors())) {
                $generation_error_code = 1;
            }
        }
        $test_config_array = json_decode((string) $test_configuration, true);
        if (isset($test_config_array['tests'])) {
            $this->run_tests($test_config_array['tests'], $output, $input);
        }
        if (isset($test_config_array['suites'])) {
            $this->run_tests_in_suite($test_config_array['suites'], $output, $input);
        }
        // Add all failed tests in 'failed' file
        $this->apply_all_failed();
        return max($this->return_code, $generation_error_code);
    }
    /**
     * Run tests not referenced in suites
     *
     * @throws TestFrameworkException
     * @throws \Exception
     */
    private function run_tests(array $tests, Output_Interface $output, Input_Interface $input): void
    {
        $xml = $input->get_option('xml') ? '--xml' : '';
        $no_ansi = $input->get_option('no-ansi') ? '--no-ansi' : '';
        if ($this->pause_enabled()) {
            $codeception_command = self::CODECEPT_RUN_FUNCTIONAL;
        } else {
            $codeception_command = realpath(PROJECT_ROOT . '/vendor/bin/codecept') . ' run functional ';
        }
        $tests_directory = File_Path_Formatter::format(TESTS_MODULE_PATH) . Test_Generator::GENERATED_DIR . DIRECTORY_SEPARATOR . Test_Generator::DEFAULT_DIR . DIRECTORY_SEPARATOR;
        for ($i = 0; $i < count($tests); $i++) {
            $test_name = $tests[$i] . 'Cest.php';
            if (!realpath($tests_directory . $test_name)) {
                throw new Test_Framework_Exception($test_name . ' is not available under ' . $tests_directory);
            }
            if ($this->pause_enabled()) {
                $full_command = $codeception_command . $tests_directory . $test_name . ' --verbose --steps --debug ' . $xml;
                if ($i !== count($tests) - 1) {
                    $full_command .= self::CODECEPT_RUN_OPTION_NO_EXIT;
                }
                $this->return_code = max($this->return_code, $this->codecept_run_test($full_command, $output));
            } else {
                $full_command = $codeception_command . $tests_directory . $test_name . ' --verbose --steps ' . $xml;
                $this->return_code = max($this->return_code, $this->execute_test_command($full_command, $output, $no_ansi));
            }
            if (!empty($xml)) {
                $this->moving_xml_file_from_source_to_destination($xml, $test_name, $output);
            }
            // Save failed tests
            $this->append_run_failed();
        }
    }
    /**
     * Run tests referenced in suites within suites' context.
     *
     * @throws \Exception
     */
    private function run_tests_in_suite(array $suites_config, Output_Interface $output, Input_Interface $input): void
    {
        $xml = $input->get_option('xml') ? '--xml' : '';
        $no_ansi = $input->get_option('no-ansi') ? '--no-ansi' : '';
        if ($this->pause_enabled()) {
            $codeception_command = self::CODECEPT_RUN_FUNCTIONAL . '--verbose --steps --debug ' . $xml;
        } else {
            $codeception_command = realpath(PROJECT_ROOT . '/vendor/bin/codecept') . ' run functional --verbose --steps ' . $xml;
        }
        $count = count($suites_config);
        $index = 0;
        //for tests in suites, run them as a group to run before and after block
        foreach (array_keys($suites_config) as $suite) {
            $full_command = $codeception_command . " -g {$suite}";
            $index += 1;
            if ($this->pause_enabled()) {
                if ($index !== $count) {
                    $full_command .= self::CODECEPT_RUN_OPTION_NO_EXIT;
                }
                $this->return_code = max($this->return_code, $this->codecept_run_test($full_command, $output));
            } else {
                $this->return_code = max($this->return_code, $this->execute_test_command($full_command, $output, $no_ansi));
            }
            if (!empty($xml)) {
                $this->moving_xml_file_from_source_to_destination($xml, $suite, $output);
            }
            // Save failed tests
            $this->append_run_failed();
        }
    }
    /**
     * Runs the codeception test command and returns exit code
     *
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function execute_test_command(string $command, Output_Interface $output, string $no_ansi): int
    {
        $process = Process::from_shell_commandline($command);
        $process->set_working_directory(TESTS_BP);
        $process->set_idle_timeout(600);
        $process->set_timeout(0);
        return $process->run(function ($type, $buffer) use ($output, $no_ansi): void {
            $buffer = $this->disable_ansi_color_codes($buffer, $no_ansi);
            $output->write($buffer);
        });
    }
    private function disable_ansi_color_codes(string $buffer, string $no_ansi): string
    {
        if (empty($no_ansi)) {
            return $buffer;
        }
        $pattern = "/\x1b\\[([0-9]{1,2}(;[0-9]{1,2})*)?[m|K]/";
        // Use preg_replace to remove ANSI escape codes from the  string
        return preg_replace($pattern, '', $buffer);
    }
}