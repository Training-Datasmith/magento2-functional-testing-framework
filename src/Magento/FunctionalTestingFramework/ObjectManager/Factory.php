<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager;

use Magento\Functional_Testing_Framework\System\Code\Class_Reader;
/**
 * Class Factory
 *
 * @internal
 */
class Factory extends \Magento\Functional_Testing_Framework\Object_Manager\Factory\Dynamic\Developer
{
    /**
     * Class reader.
     */
    protected \Magento\Functional_Testing_Framework\System\Code\Class_Reader $class_reader;
    /**
     * Factory constructor.
     * @param array                                                           $globalArguments
     */
    public function __construct(Config_Interface $config, ?\Magento\Functional_Testing_Framework\Object_Manager_Interface $object_manager = null, ?Definition_Interface $definitions = null, $global_arguments = [])
    {
        parent::__construct($config, $object_manager, $definitions, $global_arguments);
        $this->class_reader = new Class_Reader();
    }
    // @codingStandardsIgnoreStart
    /**
     * Invoke class method and prepared arguments
     *
     * @param mixed $object
     * @param string $method
     */
    public function invoke($object, $method, array $args = []): mixed
    {
        $args = $this->prepare_arguments($object, $method, $args);
        $type = $object::class;
        $class = new \ReflectionClass($type);
        $method = $class->get_method($method);
        return $method->invoke_args($object, $args);
    }
    // @codingStandardsIgnoreEnd
    /**
     * Get list of parameters for class method
     *
     * @param string $type
     * @param string $method
     * @return array|null
     */
    public function get_parameters($type, $method)
    {
        return $this->class_reader->get_parameters($type, $method);
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
        $type = $object::class;
        $parameters = $this->class_reader->get_parameters($type, $method);
        if ($parameters === null) {
            return [];
        }
        return $this->resolve_arguments($type, $parameters, $arguments);
    }
    /**
     * Resolve constructor arguments
     *
     * @param string $requestedType
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * Revisited to reduce cyclomatic complexity, left unrefactored for readability
     */
    protected function resolve_arguments($requested_type, array $parameters, array $arguments = []): array
    {
        $resolved_arguments = [];
        $arguments = count($arguments) ? array_replace($this->config->get_arguments($requested_type), $arguments) : $this->config->get_arguments($requested_type);
        foreach ($parameters as $parameter) {
            [$param_name, $param_type, $param_required, $param_default] = $parameter;
            $argument = null;
            if (array_key_exists($param_name, $arguments)) {
                $argument = $arguments[$param_name];
            } elseif (array_key_exists('options', $arguments) && array_key_exists($param_name, $arguments['options'])) {
                // The parameter name doesn't exist in the arguments, but it is contained in the 'options' argument.
                $argument = $arguments['options'][$param_name];
            } else if ($param_required) {
                if ($param_type) {
                    $argument = ['instance' => $param_type];
                } else {
                    $this->creation_stack = [];
                    throw new \BadMethodCallException('Missing required argument $' . $param_name . ' of ' . $requested_type . '.');
                }
            } else {
                $argument = $param_default;
            }
            if ($param_type && !is_object($argument) && $argument !== $param_default) {
                if (!is_array($argument)) {
                    throw new \UnexpectedValueException('Invalid parameter configuration provided for $' . $param_name . ' argument of ' . $requested_type);
                }
                if (isset($argument['instance']) && !empty($argument['instance'])) {
                    $argument_type = $argument['instance'];
                    unset($argument['instance']);
                    if (array_key_exists('shared', $argument)) {
                        $is_shared = $argument['shared'];
                        unset($argument['shared']);
                    } else {
                        $is_shared = $this->config->is_shared($argument_type);
                    }
                } else {
                    $argument_type = $param_type;
                    $is_shared = $this->config->is_shared($argument_type);
                }
                $_arguments = !empty($argument) ? $argument : [];
                $argument = $is_shared ? $this->object_manager->get($argument_type) : $this->object_manager->create($argument_type, $_arguments);
            } else if (is_array($argument)) {
                if (isset($argument['argument'])) {
                    $arg_key = $argument['argument'];
                    $argument = $this->global_arguments[$arg_key] ?? $param_default;
                } else {
                    $this->parse_array($argument);
                }
            }
            $resolved_arguments[$param_name] = $argument;
        }
        return $resolved_arguments;
    }
    /**
     * Parse array argument
     *
     * @param array $array
     * @return void
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * Revisited to reduce cyclomatic complexity, left unrefactored for readability
     */
    protected function parse_array(&$array)
    {
        foreach ($array as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            if (isset($item['instance'])) {
                $item_type = $item['instance'];
                $is_shared = $item['shared'] ?? $this->config->is_shared($item_type);
                unset($item['instance']);
                if (array_key_exists('shared', $item)) {
                    unset($item['shared']);
                }
                $_arguments = !empty($item) ? $item : [];
                $array[$key] = $is_shared ? $this->object_manager->get($item_type) : $this->object_manager->create($item_type, $_arguments);
            } elseif (isset($item['argument'])) {
                $array[$key] = $this->global_arguments[$item['argument']] ?? null;
            } else {
                $this->parse_array($item);
            }
        }
    }
    /**
     * Create instance with call time arguments
     *
     * @param string $requestedType
     * @return object
     * @throws \Exception
     */
    public function create($requested_type, array $arguments = [])
    {
        $instance_type = $this->config->get_instance_type($requested_type);
        $parameters = $this->definitions->get_parameters($instance_type);
        if ($parameters === null) {
            return new $instance_type();
        }
        if (isset($this->creation_stack[$requested_type])) {
            $last_found = end($this->creation_stack);
            $this->creation_stack = [];
            throw new \LogicException("Circular dependency: {$requested_type} depends on {$last_found} and vice versa.");
        }
        $this->creation_stack[$requested_type] = $requested_type;
        try {
            $args = $this->resolve_arguments($requested_type, $parameters, $arguments);
            unset($this->creation_stack[$requested_type]);
        } catch (\Exception $e) {
            unset($this->creation_stack[$requested_type]);
            throw $e;
        }
        $reflection = new \ReflectionClass($instance_type);
        return $reflection->new_instance_args($args);
    }
}