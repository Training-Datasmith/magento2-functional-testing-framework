<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Util\Path\File_Path_Formatter;
/**
 * Configuration schema locator.
 */
class Schema_Locator implements \Magento\Functional_Testing_Framework\Config\Schema_Locator_Interface
{
    /**
     * Path to corresponding XSD file with validation rules for merged config.
     */
    private string $schema_path;
    /**
     * Path to corresponding XSD file with validation rules for separate config files.
     */
    private ?string $per_file_schema;
    /**
     * Class constructor
     *
     * @param string|null $perFileSchema
     * @throws TestFrameworkException
     */
    public function __construct(string $schema_path, $per_file_schema = null)
    {
        if (constant('FW_BP') && file_exists(File_Path_Formatter::format(FW_BP) . $schema_path)) {
            $this->schema_path = File_Path_Formatter::format(FW_BP) . $schema_path;
            $this->per_file_schema = $per_file_schema === null ? null : File_Path_Formatter::format(FW_BP) . $per_file_schema;
        } else {
            $path = dirname(__DIR__, 3);
            $path = str_replace('\\', DIRECTORY_SEPARATOR, $path);
            $this->schema_path = $path . DIRECTORY_SEPARATOR . $schema_path;
            $this->per_file_schema = $per_file_schema === null ? null : $path . DIRECTORY_SEPARATOR . $per_file_schema;
        }
    }
    /**
     * Get path to merged config schema
     *
     * @return string
     */
    public function get_schema()
    {
        return $this->schema_path;
    }
    /**
     * Get path to pre file validation schema
     */
    public function get_per_file_schema()
    {
        return $this->per_file_schema;
    }
}