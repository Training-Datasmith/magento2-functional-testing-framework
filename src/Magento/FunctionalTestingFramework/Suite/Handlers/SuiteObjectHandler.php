<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Suite\Handlers;

use Magento\FunctionalTestingFramework\Exceptions\FastFailException;
use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;
use Magento\FunctionalTestingFramework\Exceptions\TestReferenceException;
use Magento\FunctionalTestingFramework\ObjectManager\ObjectHandlerInterface;
use Magento\FunctionalTestingFramework\ObjectManagerFactory;
use Magento\FunctionalTestingFramework\Suite\Objects\SuiteObject;
use Magento\FunctionalTestingFramework\Suite\Parsers\SuiteDataParser;
use Magento\FunctionalTestingFramework\Suite\Util\SuiteObjectExtractor;

/**
 * Class SuiteObjectHandler
 */
class SuiteObjectHandler implements ObjectHandlerInterface
{
    /**
     * Singleton instance of suite object handler.
     */
    private static ?\Magento\FunctionalTestingFramework\Suite\Handlers\SuiteObjectHandler $instance = null;

    /**
     * Array of suite objects keyed by suite name.
     *
     * @var SuiteObject[]
     */
    private $suiteObjects;

    /**
     * Avoids instantiation of SuiteObjectHandler by new.
     */
    private function __construct()
    {
    }

    /**
     * Avoids instantiation of SuiteObjectHandler by clone.
     */
    private function __clone()
    {
    }

    /**
     * Function to enforce singleton design pattern
     *
     * @throws FastFailException
     */
    public static function getInstance(): ObjectHandlerInterface
    {
        if (self::$instance === null) {
            self::$instance = new SuiteObjectHandler();
            self::$instance->initSuiteData();
        }

        return self::$instance;
    }

    /**
     * Function to return a single suite object by name
     *
     * @param string $objectName
     */
    public function getObject($objectName): SuiteObject
    {
        if (!array_key_exists($objectName, $this->suiteObjects)) {
            throw new TestReferenceException(
                "Suite {$objectName} is not defined in xml or is invalid."
            );
        }
        return $this->suiteObjects[$objectName];
    }

    /**
     * Function to return all objects the handler is responsible for
     */
    public function getAllObjects(): array
    {
        return $this->suiteObjects;
    }

    /**
     * Function which return all tests referenced by suites.
     *
     * @throws TestFrameworkException
     */
    public function getAllTestReferences(): array
    {
        $testsReferencedInSuites = [];
        $suites = $this->getAllObjects();

        foreach ($suites as $suite) {
            /** @var SuiteObject $suite */
            $test_keys = array_keys($suite->getTests());
            $testToSuiteName = array_fill_keys($test_keys, [$suite->getName()]);
            $testsReferencedInSuites = array_merge_recursive($testsReferencedInSuites, $testToSuiteName);
        }

        return $testsReferencedInSuites;
    }

    /**
     * Method to parse all suite data xml into objects.
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     * @throws FastFailException
     */
    private function initSuiteData(): void
    {
        try {
            $suiteDataParser = ObjectManagerFactory::getObjectManager()->create(SuiteDataParser::class);
        } catch (\Exception $e) {
            throw new FastFailException('Suite Data Parser Error: ' . $e->getMessage());
        }

        $suiteObjectExtractor = new SuiteObjectExtractor();
        $this->suiteObjects = $suiteObjectExtractor->parseSuiteDataIntoObjects($suiteDataParser->readSuiteData());
    }
}
