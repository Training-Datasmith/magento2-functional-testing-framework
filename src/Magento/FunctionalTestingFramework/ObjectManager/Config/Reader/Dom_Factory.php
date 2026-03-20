<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager\Config\Reader;

/**
 * Factory class for \Magento\FunctionalTestingFramework\ObjectManager\Config\Reader\Dom
 */
class Dom_Factory
{
    /**
     * Factory constructor
     *
     * @param string                                                     $instanceName
     */
    public function __construct(
        /**
         * Object Manager instance
         */
        protected \Magento\Functional_Testing_Framework\Object_Manager_Interface $object_manager,
        /**
         * Instance name to create
         */
        protected $instance_name = \Magento\Functional_Testing_Framework\Object_Manager\Config\Reader\Dom::class
    )
    {
    }
    /**
     * Create class instance with specified parameters
     *
     * @return \Magento\FunctionalTestingFramework\ObjectManager\Config\Reader\Dom
     */
    public function create(array $data = [])
    {
        return $this->object_manager->create($this->instance_name, $data);
    }
}