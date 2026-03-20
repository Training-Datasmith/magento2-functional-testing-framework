<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager\Relations;

/**
 * Class Runtime
 */
class Runtime implements \Magento\Functional_Testing_Framework\Object_Manager\Relations_Interface
{
    /**
     * Class reader.
     */
    protected \Magento\Functional_Testing_Framework\Code\Reader\Class_Reader $class_reader;
    /**
     * Default behavior
     *
     * @var array
     */
    protected $default = [];
    /**
     * Runtime constructor.
     */
    public function __construct(?\Magento\Functional_Testing_Framework\Code\Reader\Class_Reader $class_reader = null)
    {
        $this->class_reader = $class_reader ?: new \Magento\Functional_Testing_Framework\Code\Reader\Class_Reader();
    }
    /**
     * Check whether requested type is available for read
     *
     * @param string $type
     */
    public function has($type): bool
    {
        return class_exists($type) || interface_exists($type);
    }
    /**
     * Retrieve list of parents
     *
     * @param string $type
     * @return array
     */
    public function get_parents($type)
    {
        if (!class_exists($type)) {
            return $this->default;
        }
        return $this->class_reader->get_parents($type) ?: $this->default;
    }
}