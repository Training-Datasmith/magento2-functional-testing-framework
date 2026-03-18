<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types = 1);

namespace Magento\FunctionalTestingFramework\Console\Codecept;

use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;
use Magento\FunctionalTestingFramework\Util\Path\FilePathFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArgvInput;

class CodeceptCommandUtil
{
    const CODECEPTION_AUTOLOAD_FILE = PROJECT_ROOT . '/vendor/codeception/codeception/autoload.php';

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
    public function setup(InputInterface $input): void
    {
        require_once realpath(self::CODECEPTION_AUTOLOAD_FILE);

        $tokens = preg_split('{\\s+}', $input->__toString());
        $tokens[0] = str_replace('codecept:', '', $tokens[0]);
        \Closure::bind(fn&(ArgvInput $input) => $input->setTokens($tokens), null, ArgvInput::class);
    }
    // @codingStandardsIgnoreEnd
    /**
     * Save Codeception working directory
     *
     * @throws TestFrameworkException
     */
    public function setCodeceptCwd(): void
    {
        $this->cwd = getcwd();
        chdir(FilePathFormatter::format(TESTS_BP, false));
    }

    /**
     * Restore current working directory
     */
    public function restoreCwd(): void
    {
        if ($this->cwd) {
            chdir($this->cwd);
        }
    }
}
