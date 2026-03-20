<?php

/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Console;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Exceptions\Fast_Fail_Exception;
use Magento\Functional_Testing_Framework\Suite\Suite_Generator;
use Magento\Functional_Testing_Framework\Util\Generation_Error_Handler;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
class Generate_Suite_Command extends Base_Generate_Command
{
    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $this->set_name('generate:suite')->set_description('This command generates a single suite based on declaration in xml')->add_argument('suites', Input_Argument::IS_ARRAY | Input_Argument::REQUIRED, 'argument which indicates suite names for generation (separated by space)');
        parent::configure();
    }
    /**
     * Executes the current command.
     *
     * @return integer|null|void
     * @throws \Exception
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $force = $input->get_option('force');
        $debug = $input->get_option('debug') ?? Mftf_Application_Config::LEVEL_DEVELOPER;
        // for backward compatibility
        $remove = $input->get_option('remove');
        $verbose = $output->is_verbose();
        $allow_skipped = $input->get_option('allow-skipped');
        // Set application configuration so we can references the user options in our framework
        Mftf_Application_Config::create($force, Mftf_Application_Config::GENERATION_PHASE, $verbose, $debug, $allow_skipped);
        // Remove previous GENERATED_DIR if --remove option is used
        if ($remove) {
            $this->remove_generated_directory($output, $output->is_verbose());
        }
        $suites = $input->get_argument('suites');
        $generated = 0;
        foreach ($suites as $suite) {
            try {
                Suite_Generator::get_instance()->generate_suite($suite);
                if ($output->is_verbose()) {
                    $output->write_ln("suite {$suite} generated");
                }
                $generated++;
            } catch (Fast_Fail_Exception $e) {
                throw $e;
            } catch (\Exception) {
            }
        }
        if (empty(Generation_Error_Handler::get_instance()->get_all_errors())) {
            if ($generated > 0) {
                $output->writeln('Suites Generated' . PHP_EOL);
                return 0;
            }
        } else {
            Generation_Error_Handler::get_instance()->print_error_summary();
            if ($generated > 0) {
                $output->writeln('Suites Generated (with errors)' . PHP_EOL);
                return 1;
            }
        }
        $output->writeln('No Suite Generated' . PHP_EOL);
        return 1;
    }
}