<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager;

/**
 * Class ObjectManager
 */
class Object_Manager implements \Magento\Functional_Testing_Framework\Object_Manager_Interface
{
    /**
     * List of shared instances
     *
     * @var array
     */
    protected $shared_instances = [];
    /**
     * ObjectManager constructor.
     */
    public function __construct(
        /**
         * Create instance with call time arguments.
         */
        protected \Magento\Functional_Testing_Framework\Object_Manager\Factory_Interface $factory,
        /**
         * Class config.
         *
         * @var Config\Config
         */
        protected \Magento\Functional_Testing_Framework\Object_Manager\Config_Interface $config,
        array $shared_instances = []
    )
    {
        $this->shared_instances = $shared_instances;
        $this->shared_instances[\Magento\Functional_Testing_Framework\Object_Manager_Interface::class] = $this;
    }
    /**
     * Create new object instance
     *
     * @param string $type
     * @return object
     */
    public function create($type, array $arguments = [])
    {
        return $this->factory->create($this->config->get_preference($type), $arguments);
    }
    /**
     * Retrieve cached object instance
     *
     * @param string $type
     * @return object
     */
    public function get($type)
    {
        $type = $this->config->get_preference($type);
        if (!isset($this->shared_instances[$type])) {
            $this->shared_instances[$type] = $this->factory->create($type);
        }
        return $this->shared_instances[$type];
    }
    /**
     * Configure di instance
     */
    public function configure(array $configuration): void
    {
        $this->config->extend($configuration);
    }
    /**
     * Avoid to serialize Closure properties
     *
     * @return array
     */
    public function __sleep()
    {
        return [];
    }
}