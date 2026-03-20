<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\System\Code;

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
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
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
                    $param_type = $parameter->get_type();
                    $name = $param_type && method_exists($param_type, 'isBuiltin') && !$param_type->is_builtin() ? new \ReflectionClass($param_type->get_name()) : null;
                    $result[$parameter->get_name()] = [$parameter->get_name(), $name !== null ? $name->get_name() : null, !$parameter->is_optional(), $parameter->is_optional() ? $parameter->is_default_value_available() ? $parameter->get_default_value() : null : null];
                } catch (\Reflection_Exception $e) {
                    $message = $e->get_message();
                    throw new \Reflection_Exception($message, 0, $e);
                }
            }
        }
        return $result;
    }
}