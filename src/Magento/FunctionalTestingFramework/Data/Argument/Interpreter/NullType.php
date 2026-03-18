<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Data\Argument\Interpreter;

use Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface;

/**
 * Interpreter of NULL data type
 */
class NullType implements InterpreterInterface
{
    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function evaluate(array $data): null
    {
        return null;
    }
}
