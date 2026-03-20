<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Exceptions;

use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
/**
 * Class TestFrameworkException
 */
class Test_Framework_Exception extends \Exception
{
    /**
     * Exception context
     *
     * @var array
     */
    protected $context;
    /**
     * TestFrameworkException constructor.
     * @param string $message
     * @param array  $context
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function __construct($message, $context = [])
    {
        [$child_class, $calling_class] = debug_backtrace(false, 2);
        Logging_Util::get_instance()->get_logger($calling_class['class'])->error($message, $context);
        $this->context = $context;
        parent::__construct($message);
    }
    /**
     * Return exception context
     *
     * @return array
     */
    public function get_context()
    {
        return $this->context;
    }
}