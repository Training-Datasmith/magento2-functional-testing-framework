<?php

/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Console\Codecept;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
use Symfony\Component\Console\Input\Argv_Input;
use Symfony\Component\Console\Input\Input_Interface;
class Codecept_Command_Util
{
    public const CODECEPTION_AUTOLOAD_FILE = PROJECT_ROOT . '/vendor/codeception/codeception/autoload.php';
    /**
     * Current working directory
     *
     * @var string
     */
    private $cwd;
    // @codingStandardsIgnoreStart
    /**
     * Setup Codeception
     */
    public function setup(Input_Interface $input): void
    {
        require_once realpath(self::CODECEPTION_AUTOLOAD_FILE);
        $tokens = preg_split('{\s+}', $input->__toString());
        $tokens[0] = str_replace('codecept:', '', $tokens[0]);
        \Closure::bind(fn&(Argv_Input $input) => $input->set_tokens($tokens), null, Argv_Input::class);
    }
    // @codingStandardsIgnoreEnd
    /**
     * Save Codeception working directory
     *
     * @throws TestFrameworkException
     */
    public function set_codecept_cwd(): void
    {
        $this->cwd = getcwd();
        chdir(File_Path_Formatter::format(TESTS_BP, false));
    }
    /**
     * Restore current working directory
     */
    public function restore_cwd(): void
    {
        if ($this->cwd) {
            chdir($this->cwd);
        }
    }
}