<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Exceptions;

use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
/**
 * Class TestReferenceException
 */
class Test_Reference_Exception extends \Exception
{
    /**
     * TestReferenceException constructor.
     * @param string $message
     * @param array  $context
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function __construct($message, $context = [])
    {
        [$child_class, $calling_class] = debug_backtrace(false, 2);
        Logging_Util::get_instance()->get_logger($calling_class['class'])->error("Line {$calling_class['line']}: {$message}", $context);
        parent::__construct($message);
    }
}