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
use Symfony\Component\Console\Input\Array_Input;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Process\Process;
class Run_Test_Group_Command extends Base_Generate_Command
{
    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $this->set_name('run:group')->set_description('Execute a set of tests referenced via group annotations')->add_option('xml', 'xml', Input_Option::VALUE_NONE, 'creates xml report for executed group')->add_option('skip-generate', 'k', Input_Option::VALUE_NONE, 'only execute a group of tests without generating from source xml')->add_argument('groups', Input_Argument::IS_ARRAY | Input_Argument::REQUIRED, 'group names to be executed via codeception');
        parent::configure();
    }
    /**
     * Executes the current command.
     *
     * @throws \Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $xml = $input->get_option('xml') ? '--xml' : '';
        $skip_generation = $input->get_option('skip-generate');
        $force = $input->get_option('force');
        $groups = $input->get_argument('groups');
        $remove = $input->get_option('remove');
        $debug = $input->get_option('debug') ?? Mftf_Application_Config::LEVEL_DEVELOPER;
        // for backward compatibility
        $allow_skipped = $input->get_option('allow-skipped');
        $verbose = $output->is_verbose();
        if ($skip_generation and $remove) {
            // "skip-generate" and "remove" options cannot be used at the same time
            throw new Test_Framework_Exception('"skip-generate" and "remove" options can not be used at the same time.');
        }
        // Create Mftf Configuration
        Mftf_Application_Config::create($force, Mftf_Application_Config::EXECUTION_PHASE, $verbose, $debug, $allow_skipped);
        $generation_error_code = 0;
        if (!$skip_generation) {
            $test_configuration = $this->get_group_and_suite_configuration($groups);
            $command = $this->get_application()->find('generate:tests');
            $args = ['--tests' => $test_configuration, '--force' => $force, '--remove' => $remove, '--debug' => $debug, '--allow-skipped' => $allow_skipped, '-v' => $verbose];
            $command->run(new Array_Input($args), $output);
            if (!empty(Generation_Error_Handler::get_instance()->get_all_errors())) {
                $generation_error_code = 1;
            }
        }
        if ($this->pause_enabled()) {
            $command_string = self::CODECEPT_RUN_FUNCTIONAL . '--verbose --steps --debug ' . $xml;
        } else {
            $command_string = realpath(PROJECT_ROOT . '/vendor/bin/codecept') . ' run functional --verbose --steps ' . $xml;
        }
        $exit_code = -1;
        $return_codes = [];
        for ($i = 0; $i < count($groups); $i++) {
            $codeception_command_string = $command_string . ' -g ' . $groups[$i];
            if ($this->pause_enabled()) {
                if ($i !== count($groups) - 1) {
                    $codeception_command_string .= self::CODECEPT_RUN_OPTION_NO_EXIT;
                }
                $return_codes[] = $this->codecept_run_test($codeception_command_string, $output);
            } else {
                $process = Process::from_shell_commandline($codeception_command_string);
                $process->set_working_directory(TESTS_BP);
                $process->set_idle_timeout(600);
                $process->set_timeout(0);
                $return_codes[] = $process->run(function ($type, string|iterable $buffer) use ($output): void {
                    $output->write($buffer);
                });
            }
            if (!empty($xml)) {
                $this->moving_xml_file_from_source_to_destination($xml, $groups[$i] . '_' . 'group', $output);
            }
            // Save failed tests
            $this->append_run_failed();
        }
        // Add all failed tests in 'failed' file
        $this->apply_all_failed();
        foreach ($return_codes as $return_code) {
            if ($return_code !== 0) {
                return $return_code;
            }
            $exit_code = 0;
        }
        return max($exit_code, $generation_error_code);
    }
}