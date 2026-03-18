<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Data\Argument\Interpreter;

use Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface;

/**
 * Interpreter that returns value of a constant by its name
 */
class Constant implements InterpreterInterface
{
    /**
     * {@inheritdoc}
     * @throws \InvalidArgumentException
     */
    public function evaluate(array $data): mixed
    {
        if (!isset($data['value']) || !defined($data['value'])) {
            throw new \InvalidArgumentException('Constant name is expected.');
        }
        return constant($data['value']);
    }
}
