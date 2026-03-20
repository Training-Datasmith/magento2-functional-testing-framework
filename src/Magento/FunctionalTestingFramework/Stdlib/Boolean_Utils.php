<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Stdlib;

/**
 * Utility methods for the boolean data type
 */
// @codingStandardsIgnoreFile
class Boolean_Utils
{
    /**
     * BooleanUtils constructor.
     */
    public function __construct(
        /**
         * Expressions that mean boolean TRUE
         */
        private readonly array $true_values = [true, 1, 'true', '1'],
        /**
         * Expressions that mean boolean FALSE
         */
        private readonly array $false_values = [false, 0, 'false', '0']
    )
    {
    }
    /**
     * Retrieve boolean value for an expression
     *
     * @param mixed $value Boolean expression
     * @throws \InvalidArgumentException
     */
    public function to_boolean($value): bool
    {
        /**
         * Built-in function filter_var() is not used, because such values as on/off are irrelevant in some contexts
         * @link http://www.php.net/manual/en/filter.filters.validate.php
         */
        if (in_array($value, $this->true_values, true)) {
            return true;
        }
        if (in_array($value, $this->false_values, true)) {
            return false;
        }
        $allowed_values = array_merge($this->true_values, $this->false_values);
        throw new \InvalidArgumentException('Boolean value is expected, supported values: ' . var_export($allowed_values, true));
    }
}