<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Data\Argument\Interpreter;

use Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface;
use Magento\FunctionalTestingFramework\Stdlib\BooleanUtils;

/**
 * Interpreter of boolean data type, such as boolean itself or boolean string
 */
class Boolean implements InterpreterInterface
{
    /**
     * Boolean constructor.
     */
    public function __construct(
        /**
         * Utility methods for the boolean data type
         */
        private readonly BooleanUtils $booleanUtils
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
        return $this->booleanUtils->toBoolean($value);
    }
}
