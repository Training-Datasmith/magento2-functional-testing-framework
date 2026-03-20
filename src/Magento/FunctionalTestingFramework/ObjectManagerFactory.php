<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework;

use Magento\Functional_Testing_Framework\Object_Manager\Factory;
use Magento\Functional_Testing_Framework\Stdlib\Boolean_Utils;
/**
 * Object Manager Factory.
 *
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
// @codingStandardsIgnoreFile
class Object_Manager_Factory
{
    /**
     * Object Manager class name.
     *
     * @var string
     */
    protected $locator_class_name = \Magento\Functional_Testing_Framework\Object_Manager::class;
    /**
     * DI Config class name.
     *
     * @var string
     */
    protected $config_class_name = \Magento\Functional_Testing_Framework\Object_Manager\Config::class;
    /**
     * Create Object Manager.
     *
     * @return ObjectManager
     */
    public function create(array $shared_instances = [])
    {
        /** @var \Magento\FunctionalTestingFramework\ObjectManager\Config $diConfig */
        $di_config = new $this->config_class_name();
        $factory = new Factory($di_config);
        $arg_interpreter = $this->create_argument_interpreter(new Boolean_Utils());
        $argument_mapper = new \Magento\Functional_Testing_Framework\Object_Manager\Config\Mapper\Dom($arg_interpreter);
        $shared_instances[\Magento\Functional_Testing_Framework\Data\Argument\Interpreter_Interface::class] = $arg_interpreter;
        $shared_instances[\Magento\Functional_Testing_Framework\Object_Manager\Config\Mapper\Dom::class] = $argument_mapper;
        /** @var \Magento\FunctionalTestingFramework\ObjectManager $objectManager */
        $object_manager = new $this->locator_class_name($factory, $di_config, $shared_instances);
        $factory->set_object_manager($object_manager);
        Object_Manager::set_instance($object_manager);
        self::configure($object_manager);
        return $object_manager;
    }
    /**
     * Return newly created instance on an argument interpreter, suitable for processing DI arguments.
     *
     * @return \Magento\FunctionalTestingFramework\Data\Argument\InterpreterInterface
     */
    protected function create_argument_interpreter(\Magento\Functional_Testing_Framework\Stdlib\Boolean_Utils $boolean_utils): \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Composite
    {
        $const_interpreter = new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Constant();
        $result = new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Composite(['boolean' => new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Boolean($boolean_utils), 'string' => new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\String_Utils($boolean_utils), 'number' => new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Number(), 'null' => new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Null_Type(), 'const' => $const_interpreter, 'object' => new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Data_Object($boolean_utils), 'init_parameter' => new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Argument($const_interpreter)], \Magento\Functional_Testing_Framework\Object_Manager\Config\Reader\Dom::TYPE_ATTRIBUTE);
        // Add interpreters that reference the composite
        $result->add_interpreter('array', new \Magento\Functional_Testing_Framework\Data\Argument\Interpreter\Array_Type($result));
        return $result;
    }
    /**
     * Get Object Manager instance.
     *
     * @return ObjectManager
     */
    public static function get_object_manager()
    {
        if (!$object_manager = Object_Manager::get_instance()) {
            $object_manager_factory = new self();
            $object_manager = $object_manager_factory->create();
        }
        return $object_manager;
    }
    /**
     * Configure Object Manager.
     * This method is static to have the ability to configure multiple instances of Object manager when needed.
     */
    public static function configure(\Magento\Functional_Testing_Framework\Object_Manager_Interface $object_manager): void
    {
        $object_manager->configure($object_manager->get(\Magento\Functional_Testing_Framework\Object_Manager\Config_Loader\Primary::class)->load());
    }
}