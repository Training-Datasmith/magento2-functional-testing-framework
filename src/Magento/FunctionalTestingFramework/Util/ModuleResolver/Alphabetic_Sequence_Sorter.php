<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Module_Resolver;

/**
 * Alphabetic sequence sorter.
 */
class Alphabetic_Sequence_Sorter implements Sequence_Sorter_Interface
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