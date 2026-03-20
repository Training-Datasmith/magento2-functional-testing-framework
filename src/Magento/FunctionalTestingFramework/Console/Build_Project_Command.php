<?php

/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Console;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Env\Env_Processor;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Array_Input;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
/**
 * Class BuildProjectCommand
 * @package Magento\FunctionalTestingFramework\Console
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Build_Project_Command extends Command
{
    private const SUCCESS_EXIT_CODE = 0;
    public const DEFAULT_YAML_INLINE_DEPTH = 10;
    /**
     * Env processor manages .env files.
     */
    private ?\Magento\Functional_Testing_Framework\Util\Env\Env_Processor $env_processor = null;
    /**
     * Configures the current command.
     *
     * @throws TestFrameworkException
     */
    protected function configure(): void
    {
        $this->set_name('build:project')->set_description('Generate configuration files for the project. Build the Codeception project.')->add_option('upgrade', 'u', Input_Option::VALUE_NONE, 'upgrade existing MFTF tests according to last major release requirements');
        $this->env_processor = new Env_Processor(File_Path_Formatter::format(TESTS_BP) . '.env');
        $env = $this->env_processor->get_env();
        foreach ($env as $key => $value) {
            $this->add_option($key, null, Input_Option::VALUE_REQUIRED, '', $value);
        }
    }
    /**
     * Executes the current command.
     *
     * @throws \Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $reset_command = new Clean_Project_Command();
        $reset_options = new Array_Input([]);
        $reset_command->run($reset_options, $output);
        $this->generate_config_files($output);
        $setup_env_command = new Setup_Env_Command();
        $command_input = [];
        $options = $input->get_options();
        $env = array_keys($this->env_processor->get_env());
        foreach ($options as $key => $value) {
            if (in_array($key, $env)) {
                $command_input['--' . $key] = $value;
            }
        }
        $command_input = new Array_Input($command_input);
        $setup_env_command->run($command_input, $output);
        // TODO can we just import the codecept symfony command?
        $codecept_build_command = realpath(PROJECT_ROOT . '/vendor/bin/codecept') . ' build';
        $process = Process::from_shell_commandline($codecept_build_command);
        $process->set_working_directory(TESTS_BP);
        $process->set_idle_timeout(600);
        $process->set_timeout(0);
        $codecept_return_code = $process->run(function ($type, string|iterable $buffer) use ($output): void {
            $output->write($buffer);
        });
        if ($codecept_return_code !== 0) {
            throw new Test_Framework_Exception('The codecept build command failed unexpectedly. Please see the above output for more details.');
        }
        if ($input->get_option('upgrade')) {
            $upgrade_command = new Upgrade_Tests_Command();
            $upgrade_options = new Array_Input([]);
            $upgrade_command->run($upgrade_options, $output);
        }
        return self::SUCCESS_EXIT_CODE;
    }
    /**
     * Generates needed codeception configuration files to the TEST_BP directory
     *
     * @throws TestFrameworkException
     */
    private function generate_config_files(Output_Interface $output): void
    {
        $file_system = new Filesystem();
        //Find travel path from codeception.yml to FW_BP
        $relative_path = $file_system->make_path_relative(FW_BP, TESTS_BP);
        if (!$file_system->exists(File_Path_Formatter::format(TESTS_BP) . 'codeception.yml')) {
            // read in the codeception.yml file
            $config_dist_yml = Yaml::parse(file_get_contents(realpath(File_Path_Formatter::format(FW_BP) . 'etc/config/codeception.dist.yml')));
            $config_dist_yml['paths']['support'] = $relative_path . 'src/Magento/FunctionalTestingFramework';
            $config_dist_yml['paths']['envs'] = $relative_path . 'etc/_envs';
            $config_yml_text = Yaml::dump($config_dist_yml, self::DEFAULT_YAML_INLINE_DEPTH);
            // dump output to new codeception.yml file
            file_put_contents(File_Path_Formatter::format(TESTS_BP) . 'codeception.yml', $config_yml_text);
            $output->writeln('codeception.yml configuration successfully applied.');
        }
        $output->writeln('codeception.yml applied to ' . File_Path_Formatter::format(TESTS_BP) . 'codeception.yml');
        // copy the functional suite yml, will only copy if there are differences between the template the destination
        $file_system->copy(realpath(File_Path_Formatter::format(FW_BP) . 'etc/config/functional.suite.dist.yml'), File_Path_Formatter::format(TESTS_BP) . 'tests' . DIRECTORY_SEPARATOR . 'functional.suite.yml');
        $output->writeln('functional.suite.yml configuration successfully applied.');
        $output->writeln('functional.suite.yml applied to ' . File_Path_Formatter::format(TESTS_BP) . 'tests' . DIRECTORY_SEPARATOR . 'functional.suite.yml');
        $file_system->copy(File_Path_Formatter::format(FW_BP) . 'etc/config/.credentials.example', File_Path_Formatter::format(TESTS_BP) . '.credentials.example');
        // copy command.php into magento instance
        if (File_Path_Formatter::format(MAGENTO_BP, false) === File_Path_Formatter::format(FW_BP, false)) {
            $output->writeln('MFTF standalone detected, command.php copy not applied.');
        } else {
            $file_system->copy(realpath(File_Path_Formatter::format(FW_BP) . 'etc/config/command.php'), File_Path_Formatter::format(TESTS_BP) . 'utils' . DIRECTORY_SEPARATOR . 'command.php');
            $output->writeln('command.php copied to ' . File_Path_Formatter::format(TESTS_BP) . 'utils' . DIRECTORY_SEPARATOR . 'command.php');
        }
        // Remove and Create Log File
        $log_path = Logging_Util::get_instance()->get_logging_path();
        $file_system->remove($log_path);
        $file_system->touch($log_path);
        $file_system->chmod($log_path, 0777);
        $output->writeln('.credentials.example successfully applied.');
    }
}