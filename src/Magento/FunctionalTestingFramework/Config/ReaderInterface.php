<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Config;

/**
 * Config reader interface.
 */
interface ReaderInterface
{
    /**
     * Read configuration scope
     *
     * @return array
     */
    public function read(?string $scope = null);
}
