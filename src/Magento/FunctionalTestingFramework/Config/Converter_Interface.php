<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config;

/**
 * Interface ConverterInterface
 */
interface Converter_Interface
{
    /**
     * Convert config
     *
     * @param \DOMDocument $source
     * @return array
     */
    public function convert($source);
}