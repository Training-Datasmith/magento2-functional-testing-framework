<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager\Config;

use Magento\Functional_Testing_Framework\Config\Schema_Locator_Interface;
/**
 * Class SchemaLocator
 *
 * @internal
 */
class Schema_Locator implements Schema_Locator_Interface
{
    /**
     * Get path to merged config schema
     */
    public function get_schema(): string
    {
        return realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'config.xsd';
    }
    /**
     * Get path to pre file validation schema
     */
    public function get_per_file_schema(): null
    {
        return null;
    }
}