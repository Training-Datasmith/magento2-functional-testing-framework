<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data\Argument\Interpreter;

use Magento\Functional_Testing_Framework\Data\Argument\Interpreter_Interface;
use Magento\Functional_Testing_Framework\Stdlib\Boolean_Utils;
/**
 * Interpreter of boolean data type, such as boolean itself or boolean string
 */
class Boolean implements Interpreter_Interface
{
    /**
     * Boolean constructor.
     */
    public function __construct(
        /**
         * Utility methods for the boolean data type
         */
        private readonly Boolean_Utils $boolean_utils
    )
    {
    }
    /**
     * {@inheritdoc}
     * @return boolean
     * @throws \InvalidArgumentException
     */
    public function evaluate(array $data)
    {
        if (!isset($data['value'])) {
            throw new \InvalidArgumentException('Boolean value is missing.');
        }
        $value = $data['value'];
        return $this->boolean_utils->to_boolean($value);
    }
}