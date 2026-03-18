<?php

declare(strict_types=1);
/**
 * Copyright 2021 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Util\Manifest;

use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;

class ParallelByGroupTestManifest extends BaseParallelTestManifest
{
    public const PARALLEL_CONFIG = 'parallelByGroup';

    /**
     * ParallelByGroupTestManifest constructor.
     *
     * @param array  $suiteConfiguration
     * @param string $testPath
     */
    public function __construct($suiteConfiguration, $testPath)
    {
        parent::__construct($suiteConfiguration, self::PARALLEL_CONFIG, $testPath);
    }

    /**
     * Function which generates test groups based on arg passed.
     *
     * @param integer $totalGroups
     * @throws TestFrameworkException
     */
    public function createTestGroups($totalGroups): void
    {
        $this->testGroups = $this->parallelGroupSorter->getTestsGroupedByFixedGroupCount(
            $this->getSuiteConfig(),
            $this->testNameToSize,
            $totalGroups
        );

        $this->suiteConfiguration = $this->parallelGroupSorter->getResultingSuiteConfig();
    }
}
