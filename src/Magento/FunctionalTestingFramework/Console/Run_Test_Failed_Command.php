<?php

/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Console;

use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Process\Process;
class Run_Test_Failed_Command extends Base_Generate_Command
{
    public const DEFAULT_TEST_GROUP = 'default';
    private string $tests_re_run_file = 'rerun_tests';
    private array $failed_list = [];
    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $this->set_name('run:failed')->set_description('Execute a set of tests referenced via failed file');
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
        $this->tests_failed_file = $this->get_tests_output_dir() . self::FAILED_FILE;
        $this->tests_re_run_file = $this->get_tests_output_dir() . 'rerun_tests';
        $failed_tests = $this->read_failed_test_file($this->tests_failed_file);
        $test_manifest_list = $this->filter_tests_for_execution($failed_tests);
        if (empty($test_manifest_list)) {
            // If there is no tests in manifest then we have nothing to execute.
            return 0;
        }
        $return_code = 0;
        for ($i = 0; $i < count($test_manifest_list); $i++) {
            if ($this->pause_enabled()) {
                $codeception_command = self::CODECEPT_RUN_FUNCTIONAL . $test_manifest_list[$i] . ' --debug ';
                if ($i !== count($test_manifest_list) - 1) {
                    $codeception_command .= self::CODECEPT_RUN_OPTION_NO_EXIT;
                }
                $return_code = $this->codecept_run_test($codeception_command, $output);
            } else {
                $codeception_command = realpath(PROJECT_ROOT . '/vendor/bin/codecept') . ' run functional ';
                $codeception_command .= $test_manifest_list[$i];
                $process = Process::from_shell_commandline($codeception_command);
                $process->set_working_directory(TESTS_BP);
                $process->set_idle_timeout(600);
                $process->set_timeout(0);
                $return_code = max($return_code, $process->run(function ($type, string|iterable $buffer) use ($output): void {
                    $output->write($buffer);
                }));
                $process->__destruct();
                unset($process);
            }
            if (file_exists($this->tests_failed_file)) {
                $this->failed_list = array_merge($this->failed_list, $this->read_failed_test_file($this->tests_failed_file));
            }
        }
        foreach ($this->failed_list as $test) {
            $this->write_failed_test_to_file($test, $this->tests_failed_file);
        }
        return $return_code;
    }
    /**
     * Returns a list of tests/suites which should have an additional run.
     */
    private function filter_tests_for_execution(array $failed_tests): array
    {
        $tests_or_groups_to_rerun = [];
        foreach ($failed_tests as $test) {
            if (!empty($test)) {
                $this->write_failed_test_to_file($test, $this->tests_re_run_file);
                $test_info = explode(DIRECTORY_SEPARATOR, (string) $test);
                $suite_name = $test_info[count($test_info) - 2];
                [$test_path] = explode(':', (string) $test);
                if ($suite_name === self::DEFAULT_TEST_GROUP) {
                    $tests_or_groups_to_rerun[] = $test_path;
                } else {
                    $group = '-g ' . $suite_name;
                    if (!in_array($group, $tests_or_groups_to_rerun)) {
                        $tests_or_groups_to_rerun[] = $group;
                    }
                }
            }
        }
        return $tests_or_groups_to_rerun;
    }
    /**
     * Returns an array of tests read from the failed test file in _output
     */
    private function read_failed_test_file(string $file_path): array
    {
        $data = [];
        if (file_exists($file_path)) {
            $file = file($file_path, FILE_IGNORE_NEW_LINES);
            $data = $file === false ? [] : $file;
        }
        return $data;
    }
    /**
     * Writes the test name to a file if it does not already exist
     *
     * @param string $filePath
     */
    private function write_failed_test_to_file(string $test, $file_path): void
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