<?php

// @codingStandardsIgnoreFile
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Console;

use Magento\Functional_Testing_Framework\Upgrade\Upgrade_Script_List;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
class Upgrade_Tests_Command extends Command
{
    private const SUCCESS_EXIT_CODE = 0;
    /**
     * Pool of upgrade scripts to run
     */
    private ?\Magento\Functional_Testing_Framework\Upgrade\Upgrade_Script_List $upgrade_scripts_list = null;
    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $this->set_name('upgrade:tests')->set_description('This command will upgrade MFTF tests according to new MFTF Major version requirements. ' . 'It will upgrade MFTF tests in specific path when "path" argument is specified, otherwise it will ' . 'upgrade all MFTF tests installed.')->add_argument('path', Input_Argument::OPTIONAL, 'path to MFTF tests to upgrade');
        $this->upgrade_scripts_list = new Upgrade_Script_List();
    }
    /**
     *
     *
     * @throws \Exception
     */
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        /** @var \Magento\FunctionalTestingFramework\Upgrade\UpgradeInterface[] $upgradeScriptObjects */
        $upgrade_script_objects = $this->upgrade_scripts_list->get_upgrade_scripts();
        foreach ($upgrade_script_objects as $script_name => $upgrade_script_object) {
            $output->writeln('Running upgrade script: ' . $script_name . PHP_EOL);
            $upgrade_output = $upgrade_script_object->execute($input, $output);
            Logging_Util::get_instance()->get_logger($upgrade_script_object::class)->info($upgrade_output);
            $output->writeln($upgrade_output . PHP_EOL);
        }
        return self::SUCCESS_EXIT_CODE;
    }
}