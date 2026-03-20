<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework;

/**
 * Interface ObjectManagerInterface
 */
interface Object_Manager_Interface
{
    /**
     * Create new object instance
     *
     * @param string $type
     * @return object
     */
    public function create($type, array $arguments = []);
    /**
     * Retrieve cached object instance
     *
     * @param string $type
     * @return object
     */
    public function get($type);
    /**
     * Configure object manager
     *
     * @return void
     */
    public function configure(array $configuration);
}