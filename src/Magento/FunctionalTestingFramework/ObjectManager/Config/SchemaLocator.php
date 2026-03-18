<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\ObjectManager\Config;

use Magento\FunctionalTestingFramework\Config\SchemaLocatorInterface;

/**
 * Class SchemaLocator
 *
 * @internal
 */
class SchemaLocator implements SchemaLocatorInterface
{
    /**
     * Get path to merged config schema
     */
    public function getSchema(): string
    {
        return realpath(__DIR__ .  DIRECTORY_SEPARATOR . '..'  . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'config.xsd';
    }

    /**
     * Get path to pre file validation schema
     */
    public function getPerFileSchema(): null
    {
        return null;
    }
}
