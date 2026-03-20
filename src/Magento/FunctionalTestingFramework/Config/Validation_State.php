<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config;

/**
 * Class ValidationState
 * Used for Object Manager.
 *
 * @internal
 */
class Validation_State implements Validation_State_Interface
{
    /**
     * ValidationState constructor.
     * @param string $appMode
     */
    public function __construct(
        /**
         * Application mode value.
         */
        protected $app_mode
    )
    {
    }
    /**
     * Retrieve current validation state
     */
    public function is_validation_required(): bool
    {
        return $this->app_mode === 'developer';
        // @todo
    }
}