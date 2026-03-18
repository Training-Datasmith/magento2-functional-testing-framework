<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Util\Manifest;

use Magento\FunctionalTestingFramework\Suite\Handlers\SuiteObjectHandler;
use Magento\FunctionalTestingFramework\Suite\Objects\SuiteObject;
use Magento\FunctionalTestingFramework\Test\Objects\TestObject;

abstract class BaseTestManifest
{
    /**
     * Relative dir path from functional yml file. For devOps execution flexibility.
     */
    protected string $relativeDirPath;

    /**
     * TestManifest constructor.
     *
     * @param string $path
     * @param string $runTypeConfig
     * @param array  $suiteConfiguration
     */
    public function __construct($path, /**
     * Type of manifest to generate. (Currently describes whether to path to a dir or for each test).
     */
    protected $runTypeConfig, /**
     * Suite configuration in the format suite name to test name. Overwritten during a custom configuration.
     */
    protected $suiteConfiguration)
    {
        $relativeDirPath = substr($path, strlen(TESTS_BP));
        $this->relativeDirPath = ltrim($relativeDirPath, DIRECTORY_SEPARATOR);
    }

    /**
     * Returns a string indicating the generation config (e.g. singleRun).
     *
     * @return string
     */
    public function getManifestConfig()
    {
        return $this->runTypeConfig;
    }

    /**
     * Takes a test name and set of tests, records the names in a file for codeception to consume.
     *
     * @param TestObject $testObject
     * @return void
     */
    abstract public function addTest($testObject);

    /**
     * Function which generates the actual manifest(s) once the relevant tests have been added to the array.
     *
     * @return void
     */
    abstract public function generate();

    /**
     * Getter for the suite configuration.
     *
     * @return array
     */
    public function getSuiteConfig()
    {
        if ($this->suiteConfiguration === null) {
            return [];
        }

        $suiteToTestNames = [];
        if (empty($this->suiteConfiguration)) {
            // if there is no configuration passed we can assume the user wants all suites generated as specified.
            foreach (SuiteObjectHandler::getInstance()->getAllObjects() as $suite => $suiteObj) {
                $suiteToTestNames[$suite] = array_keys($suiteObj->getTests());
            }
        } else {
            // we need to loop through the configuration to make sure we capture suites with no specific config
            foreach ($this->suiteConfiguration as $suiteName => $test) {
                if (empty($test)) {
                    $suiteToTestNames[$suiteName] =
                        array_keys(SuiteObjectHandler::getInstance()->getObject($suiteName)->getTests());
                    continue;
                }

                $suiteToTestNames[$suiteName] = $test;
            }
        }

        return $suiteToTestNames;
    }
}
