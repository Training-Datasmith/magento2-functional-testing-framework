<?php

/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Util\Path;

use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
interface Formatter_Interface
{
    /**
     * Return formatted path (file path, url, etc) from input string, or false on error.
     *
     *
     * @throws TestFrameworkException
     */
    public static function format(string $input, bool $with_trailing_separator = true): string;
}