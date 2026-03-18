<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\FunctionalTestingFramework\Filter;

/**
 * Interface for future test filters
 * @api
 */
interface FilterInterface
{
    public function __construct(array $filterValues = []);

    /**
     * @return void
     */
    public function filter(array &$tests);
}
