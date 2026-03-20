<?php

/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Console;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\String_Input;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Process\Process;
class Run_Manifest_Command extends Command
{
    /**
     * The return code. Determined by all tests that run.
     */
    private int $return_code = 0;
    /**
     * A list of tests that failed.
     * Eg: "tests/functional/tests/MFTF/_generated/default/AdminLoginTestCest.php:AdminLoginTest"
     *
     * @var string[]
     */
    private array $failed_tests = [];
    /**
     * Path for a failed test
     */
    private ?string $tests_failed_file = null;
    /**
     * Configure the run:manifest command.
     */
    protected function configure(): void
    {
        $this->set_name('run:manifest')->set_description('runs a manifest file')->add_argument('path', Input_Argument::REQUIRED, 'path to a manifest file');
    }
    /**
     * Executes the run:manifest command.
     *
     * @throws TestFrameworkException
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $tests_output_dir = File_Path_Formatter::format(TESTS_BP) . 'tests' . DIRECTORY_SEPARATOR . '_output' . DIRECTORY_SEPARATOR;
        $this->tests_failed_file = $tests_output_dir . 'failed';
        $path = $input->get_argument('path');
        if (!file_exists($path)) {
            throw new Test_Framework_Exception("Could not find file {$path}. Check the path and try again.");
        }
        $manifest_file = file($path, FILE_IGNORE_NEW_LINES);
        // Delete the Codeception failed file just in case it exists from any previous test runs
        $this->delete_failed_file();
        for ($line = 0; $line < count($manifest_file); $line++) {
            if (empty($manifest_file[$line])) {
                continue;
            }
            if ($line === count($manifest_file) - 1) {
                $this->run_manifest_line($manifest_file[$line], $output, true);
            } else {
                $this->run_manifest_line($manifest_file[$line], $output);
            }
            $this->aggregate_failed();
        }
        if (!empty($this->failed_tests)) {
            $this->delete_failed_file();
            $this->write_failed_file();
        }
        return $this->return_code;
    }
    /**
     * Runs a test (or group) line from the manifest file
     *
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter) Need this because of the unused $type variable in the closure
     */
    private function run_manifest_line(string $manifest_line, \Symfony\Component\Console\Output\Output_Interface $output, bool $exit = false): void
    {
        if (getenv('ENABLE_PAUSE') === 'true') {
            $codeception_command = Base_Generate_Command::CODECEPT_RUN_FUNCTIONAL . '--verbose --steps --debug ';
            if (!$exit) {
                $codeception_command .= Base_Generate_Command::CODECEPT_RUN_OPTION_NO_EXIT;
            }
            $codeception_command .= $manifest_line;
            $input = new String_Input($codeception_command);
            $command = $this->get_application()->find(Base_Generate_Command::CODECEPT_RUN);
            $sub_return_code = $command->run($input, $output);
        } else {
            $codeception_command = realpath(PROJECT_ROOT . '/vendor/bin/codecept') . ' run functional --verbose --steps ' . $manifest_line;
            // run the codecept command in a sub process
            $process = Process::from_shell_commandline($codeception_command);
            $process->set_working_directory(TESTS_BP);
            $process->set_idle_timeout(600);
            $process->set_timeout(0);
            $sub_return_code = $process->run(function ($type, string|iterable $buffer) use ($output): void {
                $output->write($buffer);
            });
        }
        $this->return_code = max($this->return_code, $sub_return_code);
    }
    /**
     * Keeps track of any tests that failed while running the manifest file.
     *
     * Each codecept command executions overwrites the failed file. Since we are running multiple codecept commands,
     * we need to hold on to any failures in order to write a final failed file containing all tests.
     */
    private function aggregate_failed(): void
    {
        if (file_exists($this->tests_failed_file)) {
            $current_file = file($this->tests_failed_file, FILE_IGNORE_NEW_LINES);
            $this->failed_tests = array_merge($this->failed_tests, $current_file);
        }
    }
    /**
     * Delete the Codeception failed file.
     */
    private function delete_failed_file(): void
    {
        if (file_exists($this->tests_failed_file)) {
            unlink($this->tests_failed_file);
        }
    }
    /**
     * Writes any tests that failed to the Codeception failed file.
     */
    private function write_failed_file(): void
    {
        foreach ($this->failed_tests as $test) {
            file_put_contents($this->tests_failed_file, $test . "\n", FILE_APPEND);
        }
    }
}