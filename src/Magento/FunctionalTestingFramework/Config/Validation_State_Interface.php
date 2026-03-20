<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config;

/**
 * Config validation state interface.
 */
interface Validation_State_Interface
{
    /**
     * Retrieve current validation state
     *
     * @return boolean
     */
    public function is_validation_required();
}