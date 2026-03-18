<?php

declare(strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Config;

use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;
use Magento\FunctionalTestingFramework\Filter\FilterList;

class MftfApplicationConfig
{
    /**
     * MFTF Execution Phases
     */
    public const GENERATION_PHASE = 'generation';
    public const EXECUTION_PHASE = 'execution';
    public const UNIT_TEST_PHASE = 'testing';
    public const MFTF_PHASES = [self::GENERATION_PHASE, self::EXECUTION_PHASE, self::UNIT_TEST_PHASE];

    /**
     * Mftf debug levels
     */
    public const LEVEL_DEFAULT = 'default';
    public const LEVEL_DEVELOPER = 'developer';
    public const MFTF_DEBUG_LEVEL = [self::LEVEL_DEFAULT, self::LEVEL_DEVELOPER];

    /**
     * Contains object with test filters.
     */
    private readonly \Magento\FunctionalTestingFramework\Filter\FilterList $filterList;

    /**
     * String which identifies the current phase of mftf execution
     */
    private readonly string $phase;

    /**
     * String which identifies the current debug level of mftf execution
     */
    private ?string $debugLevel = null;

    /**
     * MftfApplicationConfig Singelton Instance
     */
    private static ?\Magento\FunctionalTestingFramework\Config\MftfApplicationConfig $MFTF_APPLICATION_CONTEXT = null;

    /**
     * MftfApplicationConfig constructor.
     *
     * @param boolean $forceGenerate
     * @param string  $phase
     * @param boolean $verboseEnabled
     * @param string  $debugLevel
     * @param boolean $allowSkipped
     * @param array   $filters
     * @throws TestFrameworkException
     */
    private function __construct(
        /**
         * Determines whether the user has specified a force option for generation
         */
        private $forceGenerate = false,
        $phase = self::EXECUTION_PHASE,
        /**
         * Determines whether the user would like to execute mftf in a verbose run.
         */
        private $verboseEnabled = null,
        $debugLevel = self::LEVEL_DEFAULT,
        /**
         * Boolean which allows MFTF to fully generate skipped tests
         */
        private $allowSkipped = false,
        $filters = []
    ) {
        if (!in_array($phase, self::MFTF_PHASES)) {
            throw new TestFrameworkException("{$phase} is not an mftf phase");
        }

        $this->phase = $phase;
        if (!in_array(strtolower($debugLevel), self::MFTF_DEBUG_LEVEL)) {
            throw new TestFrameworkException("{$debugLevel} is not a debug level. Use 'DEFAULT' or 'DEVELOPER'");
        }
        $this->debugLevel = match (strtolower($debugLevel)) {
            self::LEVEL_DEFAULT => self::LEVEL_DEFAULT,
            default => self::LEVEL_DEVELOPER,
        };
        $this->filterList = new FilterList($filters);
    }

    /**
     * Creates an instance of the configuration instance for reference once application has started. This function
     * returns void and is only run once during the lifetime of the application.
     *
     * @param boolean $forceGenerate
     * @param string  $phase
     * @param boolean $verboseEnabled
     * @param string  $debugLevel
     * @param boolean $allowSkipped
     * @param array   $filters
     * @throws TestFrameworkException
     */
    public static function create(
        $forceGenerate = false,
        $phase = self::EXECUTION_PHASE,
        $verboseEnabled = null,
        $debugLevel = self::LEVEL_DEFAULT,
        $allowSkipped = false,
        $filters = []
    ): void {
        if (self::$MFTF_APPLICATION_CONTEXT === null) {
            self::$MFTF_APPLICATION_CONTEXT =
                new MftfApplicationConfig(
                    $forceGenerate,
                    $phase,
                    $verboseEnabled,
                    $debugLevel,
                    $allowSkipped,
                    $filters
                );
        }
    }

    /**
     * This function returns an instance of the MftfApplicationConfig which is created once the application starts.
     *
     * @return MftfApplicationConfig
     * @throws TestFrameworkException
     */
    public static function getConfig()
    {
        // TODO explicitly set this with AcceptanceTester or MagentoWebDriver
        // during execution we cannot guarantee the use of the robofile so we return the default application config,
        // we don't want to set the application context in case the user explicitly does so at a later time.
        if (self::$MFTF_APPLICATION_CONTEXT === null) {
            return new MftfApplicationConfig();
        }

        return self::$MFTF_APPLICATION_CONTEXT;
    }

    /**
     * Returns a booelan indiciating whether or not the user has indicated a forced generation.
     *
     * @return boolean
     */
    public function forceGenerateEnabled()
    {
        return $this->forceGenerate;
    }

    /**
     * Returns a boolean indicating whether the user has indicated a verbose run, which will cause all applicable
     * text to print to the console.
     *
     * @return boolean
     */
    public function verboseEnabled()
    {
        return $this->verboseEnabled ?? getenv('MFTF_DEBUG');
    }

    /**
     * Returns a string which indicates the debug level of mftf execution.
     *
     * @return string
     */
    public function getDebugLevel()
    {
        return $this->debugLevel;
    }

    /**
     * Returns a boolean indicating whether mftf is generating skipped tests.
     *
     * @return boolean
     */
    public function allowSkipped()
    {
        return $this->allowSkipped ?? getenv('ALLOW_SKIPPED');
    }

    /**
     * Returns a string which indicates the phase of mftf execution.
     *
     * @return string
     */
    public function getPhase()
    {
        return $this->phase;
    }

    /**
     * Returns a class with registered filter list.
     *
     * @return FilterList
     */
    public function getFilterList()
    {
        return $this->filterList;
    }
}
