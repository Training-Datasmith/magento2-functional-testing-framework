<?php

/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare (strict_types=1);
namespace Magento\Functional_Testing_Framework\Data_Transport\Auth\Tfa;

use DateTimeImmutable;
use Psr\Clock\Clock_Interface;
class Clock implements Clock_Interface
{
    /**
     * Return DateTimeImmutable class object
     */
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}