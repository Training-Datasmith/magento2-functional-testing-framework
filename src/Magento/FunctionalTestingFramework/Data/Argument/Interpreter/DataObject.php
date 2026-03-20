<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data\Argument\Interpreter;

use Magento\Functional_Testing_Framework\Data\Argument\Interpreter_Interface;
class Data_Object implements Interpreter_Interface
{
    /**
     * DataObject constructor.
     */
    public function __construct(
        /**
         * Utility methods for the boolean data type.
         */
        protected \Magento\Functional_Testing_Framework\Stdlib\Boolean_Utils $boolean_utils
    )
    {
    }
    /**
     * Compute and return effective value of an argument
     *
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function evaluate(array $data): array
    {
        $result = ['instance' => $data['value']];
        if (isset($data['shared'])) {
            $result['shared'] = $this->boolean_utils->to_boolean($data['shared']);
        }
        return $result;
    }
}