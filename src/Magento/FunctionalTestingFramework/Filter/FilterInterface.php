<?php

/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Filter;

/**
 * Interface for future test filters
 * @api
 */
interface Filter_Interface
{
    public function __construct(array $filter_values = []);
    /**
     * @return void
     */
    public function filter(array &$tests);
}