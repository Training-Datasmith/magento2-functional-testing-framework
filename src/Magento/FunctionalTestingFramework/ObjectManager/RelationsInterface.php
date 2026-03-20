<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager;

/**
 * Interface RelationsInterface
 */
interface Relations_Interface
{
    /**
     * Check whether requested type is available for read
     *
     * @param string $type
     * @return boolean
     */
    public function has($type);
    /**
     * Retrieve list of parents
     *
     * @param string $type
     * @return array
     */
    public function get_parents($type);
}