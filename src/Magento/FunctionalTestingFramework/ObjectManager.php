<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework;

/**
 * Class ObjectManager
 *
 * Responsible for instantiating objects taking into account:
 * - constructor arguments (using configured, and provided parameters)
 * - class instances life style (singleton, transient)
 * - interface preferences
 *
 * @api
 */
class Object_Manager extends \Magento\Functional_Testing_Framework\Object_Manager\Object_Manager
{
    /**
     * Object manager factory.
     *
     * @var \Magento\FunctionalTestingFramework\ObjectManager\Factory
     */
    protected $factory;
    /**
     * Object manager instance.
     *
     * @var ObjectManager
     */
    protected static $instance;
    /**
     * ObjectManager constructor.
     */
    public function __construct(?\Magento\Functional_Testing_Framework\Object_Manager\Factory $factory = null, ?\Magento\Functional_Testing_Framework\Object_Manager\Config_Interface $config = null, array $shared_instances = [])
    {
        parent::__construct($factory, $config, $shared_instances);
        $this->shared_instances[\Magento\Functional_Testing_Framework\Object_Manager::class] = $this;
    }
    /**
     * Get list of parameters for class method
     *
     * @param string $type
     * @param string $method
     * @return array|null
     */
    public function get_parameters($type, $method)
    {
        return $this->factory->get_parameters($type, $method);
    }
    /**
     * Resolve and prepare arguments for class method
     *
     * @param object $object
     * @param string $method
     * @return array
     */
    public function prepare_arguments($object, $method, array $arguments = [])
    {
        return $this->factory->prepare_arguments($object, $method, $arguments);
    }
    // @codingStandardsIgnoreStart
    /**
     * Invoke class method with prepared arguments
     *
     * @param object $object
     * @param string $method
     * @return mixed
     */
    public function invoke($object, $method, array $arguments = [])
    {
        return $this->factory->invoke($object, $method, $arguments);
    }
    // @codingStandardsIgnoreEnd
    /**
     * Set object manager instance
     */
    public static function set_instance(Object_Manager $object_manager): void
    {
        self::$instance = $object_manager;
    }
    /**
     * Retrieve object manager
     *
     * @throws \RuntimeException
     */
    public static function get_instance(): false|\Magento\Functional_Testing_Framework\Object_Manager
    {
        if (!self::$instance instanceof Object_Manager) {
            return false;
        }
        return self::$instance;
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