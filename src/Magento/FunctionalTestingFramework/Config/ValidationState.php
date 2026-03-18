<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Config;

/**
 * Class ValidationState
 * Used for Object Manager.
 *
 * @internal
 */
class ValidationState implements ValidationStateInterface
{
    /**
     * ValidationState constructor.
     * @param string $appMode
     */
    public function __construct(
        /**
         * Application mode value.
         */
        protected $appMode
    ) {
    }

    /**
     * Retrieve current validation state
     */
    public function isValidationRequired(): bool
    {
        return $this->appMode === 'developer'; // @todo
    }
}
