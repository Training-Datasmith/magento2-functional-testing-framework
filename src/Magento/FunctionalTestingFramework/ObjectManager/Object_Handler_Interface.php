<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager;

/**
 * Interface ObjectHandlerInterface
 */
interface Object_Handler_Interface
{
    public const OBJ_DEPRECATED = 'deprecated';
    /**
     * Function to enforce singleton design pattern
     *
     * @return ObjectHandlerInterface
     */
    public static function get_instance();
    /**
     * Function to return a single object by name
     *
     * @param string $objectName
     * @return mixed
     */
    public function get_object($object_name);
    /**
     * Function to return all objects the handler is responsible for
     *
     * @return array
     */
    public function get_all_objects();
}