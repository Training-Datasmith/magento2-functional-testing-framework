<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Data\Argument\Interpreter;

use Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface;
use Magento\FunctionalTestingFramework\Stdlib\BooleanUtils;

class DataObject implements InterpreterInterface
{
    /**
     * DataObject constructor.
     */
    public function __construct(
        /**
         * Utility methods for the boolean data type.
         */
        protected \Magento\FunctionalTestingFramework\Stdlib\BooleanUtils $booleanUtils
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
            $result['shared'] = $this->booleanUtils->toBoolean($data['shared']);
        }
        return $result;
    }
}
