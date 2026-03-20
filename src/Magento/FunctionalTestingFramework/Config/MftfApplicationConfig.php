<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Filter\Filter_List;
class Mftf_Application_Config
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
    private readonly \Magento\Functional_Testing_Framework\Filter\Filter_List $filter_list;
    /**
     * String which identifies the current phase of mftf execution
     */
    private readonly string $phase;
    /**
     * String which identifies the current debug level of mftf execution
     */
    private ?string $debug_level = null;
    /**
     * MftfApplicationConfig Singelton Instance
     */
    private static ?\Magento\Functional_Testing_Framework\Config\Mftf_Application_Config $MFTF_APPLICATION_CONTEXT = null;
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
        private $force_generate = false,
        $phase = self::EXECUTION_PHASE,
        /**
         * Determines whether the user would like to execute mftf in a verbose run.
         */
        private $verbose_enabled = null,
        $debug_level = self::LEVEL_DEFAULT,
        /**
         * Boolean which allows MFTF to fully generate skipped tests
         */
        private $allow_skipped = false,
        $filters = []
    )
    {
        if (!in_array($phase, self::MFTF_PHASES)) {
            throw new Test_Framework_Exception("{$phase} is not an mftf phase");
        }
        $this->phase = $phase;
        if (!in_array(strtolower($debug_level), self::MFTF_DEBUG_LEVEL)) {
            throw new Test_Framework_Exception("{$debug_level} is not a debug level. Use 'DEFAULT' or 'DEVELOPER'");
        }
        $this->debug_level = match (strtolower($debug_level)) {
            self::LEVEL_DEFAULT => self::LEVEL_DEFAULT,
            default => self::LEVEL_DEVELOPER,
        };
        $this->filter_list = new Filter_List($filters);
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
    public static function create($force_generate = false, $phase = self::EXECUTION_PHASE, $verbose_enabled = null, $debug_level = self::LEVEL_DEFAULT, $allow_skipped = false, $filters = []): void
    {
        if (self::$MFTF_APPLICATION_CONTEXT === null) {
            self::$MFTF_APPLICATION_CONTEXT = new Mftf_Application_Config($force_generate, $phase, $verbose_enabled, $debug_level, $allow_skipped, $filters);
        }
    }
    /**
     * This function returns an instance of the MftfApplicationConfig which is created once the application starts.
     *
     * @return MftfApplicationConfig
     * @throws TestFrameworkException
     */
    public static function get_config()
    {
        // TODO explicitly set this with AcceptanceTester or MagentoWebDriver
        // during execution we cannot guarantee the use of the robofile so we return the default application config,
        // we don't want to set the application context in case the user explicitly does so at a later time.
        if (self::$MFTF_APPLICATION_CONTEXT === null) {
            return new Mftf_Application_Config();
        }
        return self::$MFTF_APPLICATION_CONTEXT;
    }
    /**
     * Returns a booelan indiciating whether or not the user has indicated a forced generation.
     *
     * @return boolean
     */
    public function force_generate_enabled()
    {
        return $this->force_generate;
    }
    /**
     * Returns a boolean indicating whether the user has indicated a verbose run, which will cause all applicable
     * text to print to the console.
     *
     * @return boolean
     */
    public function verbose_enabled()
    {
        return $this->verbose_enabled ?? getenv('MFTF_DEBUG');
    }
    /**
     * Returns a string which indicates the debug level of mftf execution.
     *
     * @return string
     */
    public function get_debug_level()
    {
        return $this->debug_level;
    }
    /**
     * Returns a boolean indicating whether mftf is generating skipped tests.
     *
     * @return boolean
     */
    public function allow_skipped()
    {
        return $this->allow_skipped ?? getenv('ALLOW_SKIPPED');
    }
    /**
     * Returns a string which indicates the phase of mftf execution.
     *
     * @return string
     */
    public function get_phase()
    {
        return $this->phase;
    }
    /**
     * Returns a class with registered filter list.
     *
     * @return FilterList
     */
    public function get_filter_list()
    {
        return $this->filter_list;
    }
}