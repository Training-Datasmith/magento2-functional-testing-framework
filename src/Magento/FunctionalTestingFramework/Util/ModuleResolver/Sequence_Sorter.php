<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Module_Resolver;

/**
 * Module sequence sorter.
 */
class Sequence_Sorter implements Sequence_Sorter_Interface
{
    /**
     * Sort files according to specified sequence.
     */
    public function sort(array $paths): array
    {
        return $paths;
    }
}