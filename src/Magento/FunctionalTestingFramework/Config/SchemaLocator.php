<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Config;

use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;
use Magento\FunctionalTestingFramework\Util\Path\FilePathFormatter;

/**
 * Configuration schema locator.
 */
class SchemaLocator implements \Magento\FunctionalTestingFramework\Config\SchemaLocatorInterface
{
    /**
     * Path to corresponding XSD file with validation rules for merged config.
     */
    private string $schemaPath;

    /**
     * Path to corresponding XSD file with validation rules for separate config files.
     */
    private ?string $perFileSchema;

    /**
     * Class constructor
     *
     * @param string|null $perFileSchema
     * @throws TestFrameworkException
     */
    public function __construct(string $schemaPath, $perFileSchema = null)
    {
        if (constant('FW_BP') && file_exists(FilePathFormatter::format(FW_BP) . $schemaPath)) {
            $this->schemaPath = FilePathFormatter::format(FW_BP) . $schemaPath;
            $this->perFileSchema = $perFileSchema === null ? null : FilePathFormatter::format(FW_BP)
                . $perFileSchema;
        } else {
            $path = dirname(__DIR__, 3);
            $path = str_replace('\\', DIRECTORY_SEPARATOR, $path);
            $this->schemaPath = $path . DIRECTORY_SEPARATOR . $schemaPath;
            $this->perFileSchema = $perFileSchema === null ? null : $path . DIRECTORY_SEPARATOR . $perFileSchema;
        }
    }

    /**
     * Get path to merged config schema
     *
     * @return string
     */
    public function getSchema()
    {
        return $this->schemaPath;
    }

    /**
     * Get path to pre file validation schema
     */
    public function getPerFileSchema()
    {
        return $this->perFileSchema;
    }
}
