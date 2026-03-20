<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager\Definition;

/**
 * Class Runtime
 */
class Runtime implements \Magento\Functional_Testing_Framework\Object_Manager\Definition_Interface
{
    /**
     * Definitions.
     *
     * @var array
     */
    protected $definitions = [];
    /**
     * Reader.
     */
    private readonly \Magento\Functional_Testing_Framework\Code\Reader\Class_Reader $reader;
    /**
     * Runtime constructor.
     */
    public function __construct(?\Magento\Functional_Testing_Framework\Code\Reader\Class_Reader $reader = null)
    {
        $this->reader = $reader ?: new \Magento\Functional_Testing_Framework\Code\Reader\Class_Reader();
    }
    /**
     * Get list of method parameters
     *
     * Retrieve an ordered list of constructor parameters.
     * Each value is an array with following entries:
     *
     * array(
     *     0, // string: Parameter name
     *     1, // string|null: Parameter type
     *     2, // bool: whether this param is required
     *     3, // mixed: default value
     * );
     *
     * @param string $className
     * @return array|null
     */
    public function get_parameters($class_name)
    {
        if (!array_key_exists($class_name, $this->definitions)) {
            $this->definitions[$class_name] = $this->reader->get_constructor($class_name);
        }
        return $this->definitions[$class_name];
    }
    /**
     * Retrieve list of all classes covered with definitions
     */
    public function get_classes(): array
    {
        return [];
    }
}