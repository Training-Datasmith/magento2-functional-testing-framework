<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config;

/**
 * Config replacer interface.
 */
interface Replacer_Interface
{
    /**
     * Apply specified node in 'replace' attribute instead of original.
     *
     * @return array
     */
    public function apply(array &$output);
}