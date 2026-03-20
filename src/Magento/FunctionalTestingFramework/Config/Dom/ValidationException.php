<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config\Dom;

/**
 * \Exception that should be thrown by DOM model when incoming xml is not valid.
 */
class Validation_Exception extends \InvalidArgumentException
{
}