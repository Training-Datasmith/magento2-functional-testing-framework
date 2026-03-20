<?php

// @codingStandardsIgnoreFile
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Console;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Exceptions\Fast_Fail_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Suite\Handlers\Suite_Object_Handler;
use Magento\Functional_Testing_Framework\Test\Handlers\Test_Object_Handler;
use Magento\Functional_Testing_Framework\Util\Filesystem\Dir_Setup_Util;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
use Magento\Functional_Testing_Framework\Util\Test_Generator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Input\String_Input;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
/**
 * Class BaseGenerateCommand
 * @package Magento\FunctionalTestingFramework\Console
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Base_Generate_Command extends Command
{
    public const MFTF_NOTICES = "Placeholder text for MFTF notices\n";
    public const CODECEPT_RUN = 'codecept:run';
    public const CODECEPT_RUN_FUNCTIONAL = self::CODECEPT_RUN . ' functional ';
    public const CODECEPT_RUN_OPTION_NO_EXIT = ' --no-exit ';
    public const FAILED_FILE = 'failed';
    /**
     * Enable pause()
     */
    private ?bool $enable_pause = null;
    /**
     * Full path to '_output' dir
     */
    private ?string $tests_output_dir = null;
    /**
     *  String contains all 'failed' tests
     *
     * @var string
     */
    private $all_failed;
    /**
     * Console output style
     *
     * @var SymfonyStyle
     */
    protected $io_style;
    /**
     * Full path to 'failed' file
     *
     * @var string
     */
    protected $tests_failed_file;
    /**
     * Configures the base command.
     */
    protected function configure(): void
    {
        $this->add_option('remove', 'r', Input_Option::VALUE_NONE, 'remove previous generated suites and tests')->add_option('force', 'f', Input_Option::VALUE_NONE, 'force generation and running of tests regardless of Magento Instance Configuration')->add_option('allow-skipped', 'a', Input_Option::VALUE_NONE, 'Allows MFTF to generate and run skipped tests.')->add_option('debug', 'd', Input_Option::VALUE_OPTIONAL, 'Run extra validation when generating and running tests.', Mftf_Application_Config::LEVEL_DEFAULT);
    }
    /**
     * Remove GENERATED_DIR if exists when running generate:tests.
     *
     * @return void
     * @throws TestFrameworkException
     */
    protected function remove_generated_directory(Output_Interface $output, bool $verbose)
    {
        $generated_directory = File_Path_Formatter::format(TESTS_MODULE_PATH) . Test_Generator::GENERATED_DIR;
        if (file_exists($generated_directory)) {
            Dir_Setup_Util::rmdir_recursive($generated_directory);
            if ($verbose) {
                $output->writeln("removed files and directory {$generated_directory}");
            }
        }
    }
    /**
     * Returns an array of test configuration to be used as an argument for generation of tests
     * @return false|string
     * @throws FastFailException
     */
    protected function get_test_and_suite_configuration(array $tests)
    {
        $test_configuration['tests'] = null;
        $test_configuration['suites'] = null;
        $tests_referenced_in_suites = Suite_Object_Handler::get_instance()->get_all_test_references();
        $suite_to_test_pair = [];
        foreach ($tests as $test) {
            if (str_contains((string) $test, ':')) {
                $suite_to_test_pair[] = $test;
                continue;
            }
            if (array_key_exists($test, $tests_referenced_in_suites)) {
                $suites = $tests_referenced_in_suites[$test];
                foreach ($suites as $suite) {
                    $suite_to_test_pair[] = "{$suite}:{$test}";
                }
            } else {
                $test_configuration['tests'][] = $test;
            }
        }
        // configuration for suites
        foreach ($suite_to_test_pair as $pair) {
            [$suite, $test] = explode(':', (string) $pair);
            $test_configuration['suites'][$suite][] = $test;
        }
        return json_encode($test_configuration);
    }
    /**
     * Returns an array of test configuration to be used as an argument for generation of tests
     * This function uses group or suite names for generation
     * @return false|string
     * @throws FastFailException
     * @throws TestFrameworkException
     */
    protected function get_group_and_suite_configuration(array $group_or_suite_names)
    {
        $result['tests'] = [];
        $result['suites'] = [];
        $groups = [];
        $suites = [];
        $all_suites = Suite_Object_Handler::get_instance()->get_all_objects();
        $tests_in_suites = Suite_Object_Handler::get_instance()->get_all_test_references();
        foreach ($group_or_suite_names as $group_or_suite_name) {
            if (array_key_exists($group_or_suite_name, $all_suites)) {
                $suites[] = $group_or_suite_name;
            } else {
                $groups[] = $group_or_suite_name;
            }
        }
        foreach ($suites as $suite) {
            $result['suites'][$suite] = [];
        }
        foreach ($groups as $group) {
            $tests_in_group = Test_Object_Handler::get_instance()->get_tests_by_group($group);
            $tests_in_group_and_not_in_any_suite = array_diff(array_keys($tests_in_group), array_keys($tests_in_suites));
            $tests_in_group_and_in_any_suite = array_diff(array_keys($tests_in_group), $tests_in_group_and_not_in_any_suite);
            foreach ($tests_in_group_and_in_any_suite as $test_in_group_and_in_any_suite) {
                $suite_name = $tests_in_suites[$test_in_group_and_in_any_suite][0];
                if (array_search($suite_name, $suites) !== false) {
                    // Suite is already being called to run in its entirety, do not filter list
                    continue;
                }
                $result['suites'][$suite_name][] = $test_in_group_and_in_any_suite;
            }
            $result['tests'] = array_merge($result['tests'], $tests_in_group_and_not_in_any_suite);
        }
        if (empty($result['tests'])) {
            $result['tests'] = null;
        }
        if (empty($result['suites'])) {
            $result['suites'] = null;
        }
        return json_encode($result);
    }
    /**
     * Set Symfony IO Style
     *
     * @return void
     */
    protected function set_io_style(Input_Interface $input, Output_Interface $output)
    {
        // For IO style
        if (null === $this->io_style) {
            $this->io_style = new Symfony_Style($input, $output);
        }
    }
    /**
     * Show predefined global notice messages
     *
     * @return void
     */
    protected function show_mftf_notices(Output_Interface $output)
    {
        if (null !== $this->io_style) {
            $this->io_style->note(self::MFTF_NOTICES);
        } else {
            $output->writeln(self::MFTF_NOTICES);
        }
    }
    /**
     * Return if pause() is enabled
     *
     * @return boolean
     */
    protected function pause_enabled()
    {
        if (null === $this->enable_pause) {
            if (getenv('ENABLE_PAUSE') === 'true') {
                $this->enable_pause = true;
            } else {
                $this->enable_pause = false;
            }
        }
        return $this->enable_pause;
    }
    /**
     * Runs the bin/mftf codecept:run command and returns exit code
     *
     * @throws \Exception
     */
    protected function codecept_run_test(string $command_str, Output_Interface $output): int
    {
        $input = new String_Input($command_str);
        $command = $this->get_application()->find(self::CODECEPT_RUN);
        return $command->run($input, $output);
    }
    /**
     * Return tests _output directory
     *
     * @return string
     * @throws TestFrameworkException
     */
    protected function get_tests_output_dir()
    {
        if (!$this->tests_output_dir) {
            $this->tests_output_dir = File_Path_Formatter::format(TESTS_BP) . 'tests' . DIRECTORY_SEPARATOR . '_output' . DIRECTORY_SEPARATOR;
        }
        return $this->tests_output_dir;
    }
    /**
     * Save 'failed' tests
     *
     * @return void
     */
    protected function append_run_failed()
    {
        try {
            if (!$this->tests_failed_file) {
                $this->tests_failed_file = $this->get_tests_output_dir() . self::FAILED_FILE;
            }
            if (file_exists($this->tests_failed_file)) {
                // Save 'failed' tests
                $contents = file_get_contents($this->tests_failed_file);
                if ($contents !== false && !empty($contents)) {
                    $this->all_failed .= trim($contents) . PHP_EOL;
                }
            }
        } catch (Test_Framework_Exception) {
        }
    }
    /**
     * Apply 'allFailed' in 'failed' file
     *
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function apply_all_failed()
    {
        try {
            if (!$this->tests_failed_file) {
                $this->tests_failed_file = $this->get_tests_output_dir() . self::FAILED_FILE;
            }
            if (!empty($this->all_failed)) {
                // Update 'failed' with content from 'allFailed'
                if (file_exists($this->tests_failed_file)) {
                    rename($this->tests_failed_file, $this->tests_failed_file . '.copy');
                }
                if (file_put_contents($this->tests_failed_file, $this->all_failed) === false && file_exists($this->tests_failed_file . '.copy')) {
                    rename($this->tests_failed_file . '.copy', $this->tests_failed_file);
                }
                if (file_exists($this->tests_failed_file . '.copy')) {
                    unlink($this->tests_failed_file . '.copy');
                }
            }
        } catch (Test_Framework_Exception) {
        }
    }
    /**
     * Codeception creates default xml file with name report.xml .
     * This function renames default file name with name of the test.
     *
     * @param string $xml
     * @param string $fileName
     * @param OutputInterface $output
     * @throws \Exception
     */
    public function moving_xml_file_from_source_to_destination($xml, $file_name, $output): void
    {
        if (!empty($xml) && file_exists($this->get_tests_output_dir() . 'report.xml')) {
            if (!file_exists($this->get_tests_output_dir() . 'xml')) {
                mkdir($this->get_tests_output_dir() . 'xml', 0777, true);
            }
            $file_name = str_replace('Cest.php', '', $file_name);
            $existing_file_name = $this->get_tests_output_dir() . 'report.xml';
            $new_file_name = $this->get_tests_output_dir() . 'xml/' . $file_name . '_report.xml';
            $output->writeln('<info>' . sprintf(' report.xml file is moved to  ' . $this->get_tests_output_dir() . 'xml/' . ' location with the new name ' . $file_name . '_report.xml') . '</info>');
            rename($existing_file_name, $new_file_name);
        }
    }
}