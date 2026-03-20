<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config;

/**
 * Config reader interface.
 */
interface Reader_Interface
{
    /**
     * Read configuration scope
     *
     * @return array
     */
    public function read(?string $scope = null);
}