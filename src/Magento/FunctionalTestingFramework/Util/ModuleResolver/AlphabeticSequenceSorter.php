<?php

declare(strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Util\ModuleResolver;

/**
 * Alphabetic sequence sorter.
 */
class AlphabeticSequenceSorter implements SequenceSorterInterface
{
    /**
     * Sort files alphabetically.
     */
    public function sort(array $paths): array
    {
        asort($paths);
        return $paths;
    }
}
