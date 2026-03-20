<?php

/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Util\Path;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
class File_Path_Formatter implements Formatter_Interface
{
    /**
     * Return formatted full file path from input string, or false on error.
     *
     *
     * @throws TestFrameworkException
     */
    public static function format(string $path, bool $with_trailing_separator = true): string
    {
        $valid_path = realpath($path);
        if ($valid_path) {
            return $with_trailing_separator ? $valid_path . DIRECTORY_SEPARATOR : $valid_path;
        }
        throw new Test_Framework_Exception("Invalid or non-existing file: {$path}\n");
    }
}