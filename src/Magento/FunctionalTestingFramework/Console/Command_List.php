<?php

/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Console;

/**
 * Class CommandList has a list of commands.
 * @codingStandardsIgnoreFile
 * @SuppressWarnings(PHPMD)
 */
class Command_List implements Command_List_Interface
{
    /**
     * List of Commands
     * @var \Symfony\Component\Console\Command\Command[]
     */
    private readonly array $commands;
    /**
     * Constructor
     */
    public function __construct(array $commands = [])
    {
        $this->commands = ['build:project' => new Build_Project_Command(), 'codecept:run' => new Codecept_Run_Command(), 'doctor' => new Doctor_Command(), 'generate:suite' => new Generate_Suite_Command(), 'generate:tests' => new Generate_Tests_Command(), 'generate:urn-catalog' => new Generate_Dev_Urn_Command(), 'reset' => new Clean_Project_Command(), 'generate:failed' => new Generate_Test_Failed_Command(), 'run:failed' => new Run_Test_Failed_Command(), 'run:group' => new Run_Test_Group_Command(), 'run:manifest' => new Run_Manifest_Command(), 'run:test' => new Run_Test_Command(), 'setup:env' => new Setup_Env_Command(), 'static-checks' => new Static_Checks_Command(), 'upgrade:tests' => new Upgrade_Tests_Command()] + $commands;
    }
    /**
     * {@inheritdoc}
     */
    public function get_commands()
    {
        return $this->commands;
    }
}