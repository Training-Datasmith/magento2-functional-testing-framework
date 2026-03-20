<?php

// @codingStandardsIgnoreFile
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Console;

use Exception;
use Magento\Functional_Testing_Framework\Static_Check\Static_Check_Interface;
use Magento\Functional_Testing_Framework\Static_Check\Static_Checks_List;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
class Static_Checks_Command extends Command
{
    /**
     * Associative array containing static ruleset properties.
     *
     * @var array
     */
    private $rule_set;
    /**
     * Pool of all existing static check objects
     *
     * @var StaticCheckInterface[]
     */
    private $all_static_check_objects;
    /**
     * Static checks to run
     *
     * @var StaticCheckInterface[]
     */
    private $static_check_objects;
    /**
     * Console output style
     *
     * @var SymfonyStyle
     */
    protected $io_style;
    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $list = new Static_Checks_List();
        $this->all_static_check_objects = $list->get_static_checks();
        $static_check_names = implode(', ', array_keys($this->all_static_check_objects));
        $description = 'This command will run all static checks on xml test materials. ' . 'Available static check scripts are:' . PHP_EOL . $static_check_names;
        $this->set_name('static-checks')->set_description($description)->add_argument('names', Input_Argument::OPTIONAL | Input_Argument::IS_ARRAY, 'name(s) of specific static check script(s) to run')->add_option('path', 'p', Input_Option::VALUE_OPTIONAL, 'Path to a MFTF test module to run "deprecatedEntityUsage" static check script. ' . PHP_EOL . 'Option is ignored by other static check scripts.' . PHP_EOL);
    }
    /**
     * Run required static check scripts
     *
     * @throws Exception
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $this->io_style = new Symfony_Style($input, $output);
        try {
            $this->validate_input($input);
        } catch (InvalidArgumentException $e) {
            Logging_Util::get_instance()->get_logger(Static_Checks_Command::class)->error($e->get_message());
            $this->io_style->error($e->get_message() . ' Please fix input argument(s) or option(s) and rerun.');
            return 1;
        }
        $cmd_failed = false;
        $errors = [];
        foreach ($this->static_check_objects as $name => $static_check) {
            Logging_Util::get_instance()->get_logger($static_check::class)->info('Running static check script for: ' . $name . PHP_EOL);
            $this->io_style->text(PHP_EOL . 'Running static check script for: ' . $name . PHP_EOL);
            $start = microtime(true);
            try {
                $static_check->execute($input);
            } catch (Exception $e) {
                $cmd_failed = true;
                Logging_Util::get_instance()->get_logger($static_check::class)->error($e->get_message() . PHP_EOL);
                $this->io_style->error($e->get_message());
            }
            $end = microtime(true);
            $errors += $static_check->get_errors();
            $static_output = $static_check->get_output();
            Logging_Util::get_instance()->get_logger($static_check::class)->info($static_output);
            $this->io_style->text($static_output ?? '');
            $this->io_style->text('Total execution time is ' . ($end - $start) . ' seconds.' . PHP_EOL);
        }
        if (!$cmd_failed && empty($errors)) {
            return 0;
        }
        return 1;
    }
    /**
     * Validate input arguments
     *
     * @throws InvalidArgumentException
     */
    private function validate_input(Input_Interface $input): void
    {
        $this->static_check_objects = [];
        $required_checks_names = $input->get_argument('names');
        // Build list of static check names to run.
        if (empty($required_checks_names)) {
            $this->parse_ruleset_json();
            $required_checks_names = $this->rule_set['tests'] ?? null;
        }
        if (empty($required_checks_names)) {
            $this->static_check_objects = $this->all_static_check_objects;
        } else {
            $this->validate_test_names($required_checks_names);
        }
        if ($input->get_option('path')) {
            if (count($this->static_check_objects) !== 1 || !in_array(array_keys($this->static_check_objects)[0], [Static_Checks_List::DEPRECATED_ENTITY_USAGE_CHECK_NAME, Static_Checks_List::PAUSE_ACTION_USAGE_CHECK_NAME])) {
                throw new InvalidArgumentException('--path option is not supported for the command."');
            }
        }
    }
    /**
     * Validates that all passed in static-check names match an existing static check
     * @param string[] $requiredChecksNames
     */
    private function validate_test_names(array $required_checks_names): void
    {
        $invalid_check_names = [];
        for ($index = 0; $index < count($required_checks_names); $index++) {
            if (in_array($required_checks_names[$index], array_keys($this->all_static_check_objects))) {
                $this->static_check_objects[$required_checks_names[$index]] = $this->all_static_check_objects[$required_checks_names[$index]];
            } else {
                $invalid_check_names[] = $required_checks_names[$index];
            }
        }
        if (!empty($invalid_check_names)) {
            throw new InvalidArgumentException('Invalid static check script(s): ' . implode(', ', $invalid_check_names) . '.');
        }
    }
    /**
     * Parses and sets local ruleSet. If not found, simply returns and lets script continue.
     * @return void;
     */
    private function parse_ruleset_json(): void
    {
        $path_addition = '/dev/tests/acceptance/';
        // MFTF is both NOT attached and no MAGENTO_BP defined in .env
        if (MAGENTO_BP === FW_BP) {
            $path_addition = '/dev/';
        }
        $path_to_ruleset = MAGENTO_BP . $path_addition . 'staticRuleset.json';
        if (!file_exists($path_to_ruleset)) {
            $this->io_style->text("No ruleset under {$path_to_ruleset}" . PHP_EOL);
            return;
        }
        $this->io_style->text("Using ruleset under {$path_to_ruleset}" . PHP_EOL);
        $this->rule_set = json_decode(file_get_contents($path_to_ruleset), true);
    }
}