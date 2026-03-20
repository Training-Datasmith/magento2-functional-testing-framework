<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager;

/**
 * Interface DefinitionInterface
 */
interface Definition_Interface
{
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
    public function get_parameters($class_name);
    /**
     * Retrieve list of all classes covered with definitions
     *
     * @return array
     */
    public function get_classes();
}