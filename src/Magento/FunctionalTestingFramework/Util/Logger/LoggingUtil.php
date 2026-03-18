<?php

declare(strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Util\Logger;

use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;
use Magento\FunctionalTestingFramework\Util\Path\FilePathFormatter;
use Monolog\Handler\StreamHandler;

class LoggingUtil
{
    /**
     * Private Map of Logger instances, indexed by Class Name.
     */
    private array $loggers = [];

    /**
     * Singleton LoggingUtil Instance
     */
    private static ?\Magento\FunctionalTestingFramework\Util\Logger\LoggingUtil $instance = null;

    /**
     * Singleton accessor for instance variable
     */
    public static function getInstance(): LoggingUtil
    {
        if (self::$instance === null) {
            self::$instance = new LoggingUtil();
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
    public function getLogger($className): MftfLogger
    {
        if ($className === null) {
            throw new TestFrameworkException('You must pass a class name to receive a logger');
        }

        if (!array_key_exists($className, $this->loggers)) {
            $logger = new MftfLogger($className);
            $logger->pushHandler(new StreamHandler($this->getLoggingPath()));
            $this->loggers[$className] = $logger;
        }

        return $this->loggers[$className];
    }

    /**
     * Function which returns a static path to the the log file.
     *
     * @throws TestFrameworkException
     */
    public function getLoggingPath(): string
    {
        return FilePathFormatter::format(TESTS_BP) . 'mftf.log';
    }
}
