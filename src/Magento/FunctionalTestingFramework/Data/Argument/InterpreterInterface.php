<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data\Argument;

/**
 * Interface that encapsulates complexity of expression computation
 */
interface Interpreter_Interface
{
    /**
     * Compute and return effective value of an argument
     *
     * @return mixed
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function evaluate(array $data);
}