<?php

/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Console;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Env\Env_Processor;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\Invalid_Option_Exception;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
class Setup_Env_Command extends Command
{
    private const SUCCESS_EXIT_CODE = 0;
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
        $this->set_name('setup:env')->set_description('Generate .env file.');
        $this->env_processor = new Env_Processor(File_Path_Formatter::format(TESTS_BP) . '.env');
        $env = $this->env_processor->get_env();
        foreach ($env as $key => $value) {
            $this->add_option($key, null, Input_Option::VALUE_REQUIRED, '', $value);
        }
    }
    /**
     * Executes the current command.
     *
     * @throws \Symfony\Component\Console\Exception\LogicException
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $config = $this->env_processor->get_env();
        $user_env = [];
        foreach ($config as $key => $value) {
            if ($input->get_option($key) === '') {
                throw new Invalid_Option_Exception(sprintf("Parameter {$key} cannot be empty.", $key));
            }
            $user_env[$key] = $input->get_option($key);
        }
        $this->env_processor->put_env_file($user_env);
        $output->writeln('.env configuration successfully applied.');
        return self::SUCCESS_EXIT_CODE;
    }
}