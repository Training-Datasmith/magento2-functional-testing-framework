<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Code\Reader;

class Class_Reader implements Class_Reader_Interface
{
    /**
     * Read class constructor signature
     *
     * @param string $className
     * @return array|null
     * @throws \ReflectionException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function get_constructor($class_name)
    {
        $class = new \ReflectionClass($class_name);
        $result = null;
        $constructor = $class->get_constructor();
        if ($constructor) {
            $result = [];
            /** @var $parameter \ReflectionParameter */
            foreach ($constructor->get_parameters() as $parameter) {
                try {
                    $param_type = $parameter->get_type();
                    $name = $param_type && method_exists($param_type, 'isBuiltin') && !$param_type->is_builtin() ? new \ReflectionClass($param_type->get_name()) : null;
                    $result[] = [$parameter->get_name(), $name !== null ? $name->get_name() : null, !$parameter->is_optional(), $parameter->is_optional() ? $parameter->is_default_value_available() ? $parameter->get_default_value() : null : null];
                } catch (\Reflection_Exception $e) {
                    $message = $e->get_message();
                    throw new \Reflection_Exception($message, 0, $e);
                }
            }
        }
        return $result;
    }
    /**
     * Retrieve parent relation information for type in a following format
     * array(
     *     'Parent_Class_Name',
     *     'Interface_1',
     *     'Interface_2',
     *     ...
     * )
     *
     * @param string $className
     * @return string[]
     */
    public function get_parents($class_name)
    {
        $parent_class = get_parent_class($class_name);
        if ($parent_class) {
            $result = [];
            $interfaces = class_implements($class_name);
            if ($interfaces) {
                $parent_interfaces = class_implements($parent_class);
                if ($parent_interfaces) {
                    $result = array_values(array_diff($interfaces, $parent_interfaces));
                } else {
                    $result = array_values($interfaces);
                }
            }
            array_unshift($result, $parent_class);
        } else {
            $result = array_values(class_implements($class_name));
            if ($result) {
                array_unshift($result, null);
            } else {
                $result = [];
            }
        }
        return $result;
    }
}