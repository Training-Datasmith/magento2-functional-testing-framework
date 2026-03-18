<?php

declare(strict_types=1);
/**
 * Copyright 2021 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Util\Manifest;

class ParallelByTimeTestManifest extends BaseParallelTestManifest
{
    public const PARALLEL_CONFIG = 'parallelByTime';

    /**
     * GroupBasedParallelTestManifest constructor.
     *
     * @param array  $suiteConfiguration
     * @param string $testPath
     */
    public function __construct($suiteConfiguration, $testPath)
    {
        parent::__construct($suiteConfiguration, self::PARALLEL_CONFIG, $testPath);
    }

    /**
     * Function which generates test groups based on arg passed. The function builds groups using the args as an upper
     * limit.
     *
     * @param integer $time
     */
    public function createTestGroups($time): void
    {
        $this->testGroups = $this->parallelGroupSorter->getTestsGroupedBySize(
            $this->getSuiteConfig(),
            $this->testNameToSize,
            $time
        );

        $this->suiteConfiguration = $this->parallelGroupSorter->getResultingSuiteConfig();
    }
}
