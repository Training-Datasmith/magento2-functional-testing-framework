<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data\Argument\Interpreter;

use Magento\Functional_Testing_Framework\Data\Argument\Interpreter_Interface;
/**
 * Interpreter that returns value of an application argument, retrieving its name from a constant
 */
class Argument implements Interpreter_Interface
{
    /**
     * Argument constructor.
     */
    public function __construct(
        /**
         * Interpreter that returns value of a constant by its name.
         */
        private readonly Constant $const_interpreter
    )
    {
    }
    /**
     * Compute and return effective value of an argument.
     */
    public function evaluate(array $data): array
    {
        return ['argument' => $this->const_interpreter->evaluate($data)];
    }
}