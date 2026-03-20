<?php

/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Console;

use Codeception\Command\Run;
use Magento\Functional_Testing_Framework\Console\Codecept\Codecept_Command_Util;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
class Codecept_Run_Command extends Run
{
    /**
     * Configures the current command
     */
    protected function configure(): void
    {
        $this->set_name('codecept:run')->set_description('Wrapper command to vendor/bin/codecept:run. See https://codeception.com/docs/reference/Commands#Run');
        parent::configure();
    }
    /**
     * Executes the current command
     *
     * @throws \Exception
     */
    public function execute(Input_Interface $input, Output_Interface $output): int
    {
        $command_util = new Codecept_Command_Util();
        $command_util->setup($input);
        $command_util->set_codecept_cwd();
        try {
            $exit_code = parent::execute($input, $output);
        } catch (\Exception $e) {
            throw new Test_Framework_Exception('Make sure cest files are generated before running bin/mftf ' . $this->get_name() . PHP_EOL . $e->get_message());
        }
        $command_util->restore_cwd();
        return $exit_code;
    }
}