<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\ObjectManager;

/**
 * Interface FactoryInterface
 */
interface FactoryInterface
{
    /**
     * Create instance with call time arguments
     *
     * @param string $requestedType
     * @return object
     * @throws \LogicException
     * @throws \BadMethodCallException
     */
    public function create($requestedType, array $arguments = []);
}
