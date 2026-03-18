<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Data\Argument\Interpreter;

use Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface;

/**
 * Interpreter that returns value of an application argument, retrieving its name from a constant
 */
class Argument implements InterpreterInterface
{
    /**
     * Argument constructor.
     */
    public function __construct(
        /**
         * Interpreter that returns value of a constant by its name.
         */
        private readonly Constant $constInterpreter
    ) {
    }

    /**
     * Compute and return effective value of an argument.
     */
    public function evaluate(array $data): array
    {
        return ['argument' => $this->constInterpreter->evaluate($data)];
    }
}
