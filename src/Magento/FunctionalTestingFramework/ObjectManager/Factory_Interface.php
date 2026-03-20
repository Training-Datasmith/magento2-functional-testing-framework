<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager;

/**
 * Interface FactoryInterface
 */
interface Factory_Interface
{
    /**
     * Create instance with call time arguments
     *
     * @param string $requestedType
     * @return object
     * @throws \LogicException
     * @throws \BadMethodCallException
     */
    public function create($requested_type, array $arguments = []);
}