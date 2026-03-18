<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\ObjectManager;

/**
 * Class ObjectManager
 */
class ObjectManager implements \Magento\FunctionalTestingFramework\ObjectManagerInterface
{
    /**
     * List of shared instances
     *
     * @var array
     */
    protected $sharedInstances = [];

    /**
     * ObjectManager constructor.
     */
    public function __construct(/**
     * Create instance with call time arguments.
     */
    protected \Magento\FunctionalTestingFramework\ObjectManager\FactoryInterface $factory, /**
     * Class config.
     *
     * @var Config\Config
     */
    protected \Magento\FunctionalTestingFramework\ObjectManager\ConfigInterface $config, array $sharedInstances = [])
    {
        $this->sharedInstances = $sharedInstances;
        $this->sharedInstances[\Magento\FunctionalTestingFramework\ObjectManagerInterface::class] = $this;
    }

    /**
     * Create new object instance
     *
     * @param string $type
     * @return object
     */
    public function create($type, array $arguments = [])
    {
        return $this->factory->create($this->config->getPreference($type), $arguments);
    }

    /**
     * Retrieve cached object instance
     *
     * @param string $type
     * @return object
     */
    public function get($type)
    {
        $type = $this->config->getPreference($type);
        if (!isset($this->sharedInstances[$type])) {
            $this->sharedInstances[$type] = $this->factory->create($type);
        }
        return $this->sharedInstances[$type];
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
