<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data\Argument\Interpreter;

use Magento\Functional_Testing_Framework\Data\Argument\Interpreter_Interface;
/**
 * Interpreter of numeric data, such as integer, float, or numeric string
 */
class Number implements Interpreter_Interface
{
    /**
     * {@inheritdoc}
     * @return string|integer|float
     * @throws \InvalidArgumentException
     */
    public function evaluate(array $data)
    {
        if (!isset($data['value']) || !is_numeric($data['value'])) {
            throw new \InvalidArgumentException('Numeric value is expected.');
        }
        return $data['value'];
    }
}