<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager;

/**
 * Interface ConfigInterface
 */
interface Config_Interface
{
    /**
     * Retrieve list of arguments per type
     *
     * @param string $type
     * @return array
     */
    public function get_arguments($type);
    /**
     * Check whether type is shared
     *
     * @param string $type
     * @return boolean
     */
    public function is_shared($type);
    /**
     * Retrieve instance type
     *
     * @param string $instanceName
     * @return string
     */
    public function get_instance_type($instance_name);
    /**
     * Retrieve preference for type
     *
     * @param string $type
     * @return string
     * @throws \LogicException
     */
    public function get_preference($type);
    /**
     * Extend configuration
     *
     * @return void
     */
    public function extend(array $configuration);
}