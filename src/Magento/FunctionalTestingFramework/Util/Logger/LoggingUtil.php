<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Logger;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
use Monolog\Handler\Stream_Handler;
class Logging_Util
{
    /**
     * Private Map of Logger instances, indexed by Class Name.
     */
    private array $loggers = [];
    /**
     * Singleton LoggingUtil Instance
     */
    private static ?\Magento\Functional_Testing_Framework\Util\Logger\Logging_Util $instance = null;
    /**
     * Singleton accessor for instance variable
     */
    public static function get_instance(): Logging_Util
    {
        if (self::$instance === null) {
            self::$instance = new Logging_Util();
        }
        return self::$instance;
    }
    /**
     * Avoids instantiation of LoggingUtil by new.
     */
    private function __construct()
    {
    }
    /**
     * Avoids instantiation of LoggingUtil by clone.
     */
    private function __clone()
    {
    }
    /**
     * Creates a new logger instances based on class name if it does not exist. If logger instance already exists, the
     * existing instance is simply returned.
     *
     * @param string $className
     * @throws TestFrameworkException
     */
    public function get_logger($class_name): Mftf_Logger
    {
        if ($class_name === null) {
            throw new Test_Framework_Exception('You must pass a class name to receive a logger');
        }
        if (!array_key_exists($class_name, $this->loggers)) {
            $logger = new Mftf_Logger($class_name);
            $logger->push_handler(new Stream_Handler($this->get_logging_path()));
            $this->loggers[$class_name] = $logger;
        }
        return $this->loggers[$class_name];
    }
    /**
     * Function which returns a static path to the the log file.
     *
     * @throws TestFrameworkException
     */
    public function get_logging_path(): string
    {
        return File_Path_Formatter::format(TESTS_BP) . 'mftf.log';
    }
}