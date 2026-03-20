<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Logger;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Monolog\Handler\Handler_Interface;
use Monolog\Logger;
class Mftf_Logger extends Logger
{
    /**
     * MFTF execution phase
     *
     * @var string
     */
    private $phase;
    /**
     * MftfLogger constructor.
     *
     * @param string             $name
     * @param HandlerInterface[] $handlers
     * @param callable[]         $processors
     * @throws TestFrameworkException
     */
    public function __construct($name, array $handlers = [], array $processors = [])
    {
        parent::__construct($name, $handlers, $processors);
        $this->phase = Mftf_Application_Config::get_config()->get_phase();
    }
    /**
     * Prints a deprecation warning, as well as adds a log at the WARNING level.
     * Suppresses logging during execution phase.
     *
     * @param string  $message The log message.
     * @param array   $context The log context.
     * @param boolean $verbose
     */
    public function deprecation($message, array $context = [], $verbose = false): void
    {
        $message = 'DEPRECATION: ' . $message;
        // print during test generation including metadata
        if ((array_key_exists('operationType', $context) || $this->phase === Mftf_Application_Config::GENERATION_PHASE) && $verbose) {
            print $message . json_encode($context) . "\n";
        }
        // suppress logging during test execution except metadata
        if (array_key_exists('operationType', $context) || $this->phase !== Mftf_Application_Config::EXECUTION_PHASE) {
            parent::warning($message, $context);
        }
    }
    /**
     * Prints a critical failure, as well as adds a log at the CRITICAL level.
     *
     * @param string  $message The log message.
     * @param array   $context The log context.
     * @param boolean $verbose
     */
    public function critical_failure($message, array $context = [], $verbose = false): void
    {
        $message = 'FAILURE: ' . $message;
        // Suppress print during unit testing
        if ($this->phase !== Mftf_Application_Config::UNIT_TEST_PHASE && $verbose) {
            print $message . implode("\n", $context) . "\n";
        }
        parent::critical($message, $context);
    }
    /**
     * Adds a log record at the NOTICE level.
     * Suppresses logging during execution phase.
     *
     * @param string  $message
     * @param boolean $verbose
     */
    public function notification($message, array $context = [], $verbose = false): void
    {
        $message = 'NOTICE: ' . $message;
        // print during test generation
        if ($this->phase === Mftf_Application_Config::GENERATION_PHASE && $verbose) {
            print $message . json_encode($context) . "\n";
        }
        // suppress logging during test execution
        if ($this->phase !== Mftf_Application_Config::EXECUTION_PHASE) {
            parent::notice($message, $context);
        }
    }
}