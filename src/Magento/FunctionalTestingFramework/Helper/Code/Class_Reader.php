<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Helper\Code;

/**
 * Class ClassReader
 *
 * @internal
 */
class Class_Reader
{
    /**
     * Read class method signature
     *
     * @param string $className
     * @param string $method
     * @return array|null
     * @throws \ReflectionException
     */
    public function get_parameters($class_name, $method)
    {
        $class = new \ReflectionClass($class_name);
        $result = null;
        $method = $class->get_method($method);
        if ($method) {
            $result = [];
            /** @var $parameter \ReflectionParameter */
            foreach ($method->get_parameters() as $parameter) {
                try {
                    $result[$parameter->get_name()] = ['type' => $parameter->get_type() === null ? null : $parameter->get_type()->get_name(), 'variableName' => $parameter->get_name(), 'isOptional' => $parameter->is_optional(), 'optionalValue' => $parameter->is_optional() ? $parameter->is_default_value_available() ? $parameter->get_default_value() : null : null];
                } catch (\Reflection_Exception $e) {
                    $message = $e->get_message();
                    throw new \Reflection_Exception($message, 0, $e);
                }
            }
        }
        return $result;
    }
}