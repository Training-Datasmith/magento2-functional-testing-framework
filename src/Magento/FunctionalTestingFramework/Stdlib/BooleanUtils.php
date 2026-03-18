<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Stdlib;

/**
 * Utility methods for the boolean data type
 */
// @codingStandardsIgnoreFile
class BooleanUtils
{
    /**
     * BooleanUtils constructor.
     */
    public function __construct(
        /**
         * Expressions that mean boolean TRUE
         */
        private readonly array $trueValues = [true, 1, 'true', '1'],
        /**
         * Expressions that mean boolean FALSE
         */
        private readonly array $falseValues = [false, 0, 'false', '0']
    )
    {
    }

    /**
     * Retrieve boolean value for an expression
     *
     * @param mixed $value Boolean expression
     * @throws \InvalidArgumentException
     */
    public function toBoolean($value): bool
    {
        /**
         * Built-in function filter_var() is not used, because such values as on/off are irrelevant in some contexts
         * @link http://www.php.net/manual/en/filter.filters.validate.php
         */
        if (in_array($value, $this->trueValues, true)) {
            return true;
        }
        if (in_array($value, $this->falseValues, true)) {
            return false;
        }
        $allowedValues = array_merge($this->trueValues, $this->falseValues);
        throw new \InvalidArgumentException(
            'Boolean value is expected, supported values: ' . var_export($allowedValues, true)
        );
    }
}
