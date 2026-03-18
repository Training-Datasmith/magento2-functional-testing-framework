<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Util\Manifest;

use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;
use Magento\FunctionalTestingFramework\Test\Handlers\TestObjectHandler;
use Magento\FunctionalTestingFramework\Util\Path\FilePathFormatter;
use Magento\FunctionalTestingFramework\Util\TestGenerator;

class TestManifestFactory
{
    /**
     * TestManifestFactory constructor.
     */
    private function __construct()
    {
        // private constructor
    }

    /**
     * Static function which takes path and config to return the appropriate manifest output type.
     *
     * @param string $runConfig
     * @param array  $suiteConfiguration
     * @return BaseTestManifest
     * @throws TestFrameworkException
     */
    public static function makeManifest($runConfig, $suiteConfiguration, string $testPath = TestGenerator::DEFAULT_DIR): \Magento\FunctionalTestingFramework\Util\Manifest\SingleRunTestManifest|\Magento\FunctionalTestingFramework\Util\Manifest\ParallelByTimeTestManifest|\Magento\FunctionalTestingFramework\Util\Manifest\ParallelByGroupTestManifest|\Magento\FunctionalTestingFramework\Util\Manifest\DefaultTestManifest
    {
        $testDirFullPath = FilePathFormatter::format(TESTS_MODULE_PATH)
        . TestGenerator::GENERATED_DIR
        . DIRECTORY_SEPARATOR
        . $testPath;

        return match ($runConfig) {
            'singleRun' => new SingleRunTestManifest($suiteConfiguration, $testDirFullPath),
            'parallelByTime' => new ParallelByTimeTestManifest($suiteConfiguration, $testDirFullPath),
            'parallelByGroup' => new ParallelByGroupTestManifest($suiteConfiguration, $testDirFullPath),
            default => new DefaultTestManifest($suiteConfiguration, $testDirFullPath),
        };
    }
}
