<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config;

/**
 * Interface SchemaLocatorInterface
 */
interface Schema_Locator_Interface
{
    /**
     * Get path to merged config schema
     *
     * @return string|null
     */
    public function get_schema();
    /**
     * Get path to per file validation schema
     *
     * @return string|null
     */
    public function get_per_file_schema();
}