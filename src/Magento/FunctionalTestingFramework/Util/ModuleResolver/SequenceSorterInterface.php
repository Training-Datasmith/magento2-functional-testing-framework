<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Module_Resolver;

/**
 * Sequence sorter interface.
 */
interface Sequence_Sorter_Interface
{
    /**
     * Sort files according to specified sequence.
     *
     * @return array
     */
    public function sort(array $paths);
}