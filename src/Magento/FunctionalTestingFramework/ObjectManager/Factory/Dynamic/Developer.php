<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager\Factory\Dynamic;

/**
 * Class Developer
 */
class Developer implements \Magento\Functional_Testing_Framework\Object_Manager\Factory_Interface
{
    /**
     * Definition list
     */
    protected \Magento\Functional_Testing_Framework\Object_Manager\Definition_Interface $definitions;
    /**
     * Object creation stack
     *
     * @var array
     */
    protected $creation_stack = [];
    /**
     * Developer constructor.
     * @param array                                                                      $globalArguments
     */
    public function __construct(
        /**
         * Object manager config
         */
        protected \Magento\Functional_Testing_Framework\Object_Manager\Config_Interface $config,
        /**
         * Object manager
         */
        protected ?\Magento\Functional_Testing_Framework\Object_Manager_Interface $object_manager = null,
        ?\Magento\Functional_Testing_Framework\Object_Manager\Definition_Interface $definitions = null,
        /**
         * Global arguments.
         */
        protected $global_arguments = []
    )
    {
        $this->definitions = $definitions ?: new \Magento\Functional_Testing_Framework\Object_Manager\Definition\Runtime();
    }
    /**
     * Set object manager
     */
    public function set_object_manager(\Magento\Functional_Testing_Framework\Object_Manager_Interface $object_manager): void
    {
        $this->object_manager = $object_manager;
    }
    /**
     * Resolve constructor arguments
     *
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * Revisited to reduce cyclomatic complexity, left unrefactored for readability
     */
    protected function resolve_arguments(string $requested_type, array $parameters, array $arguments = []): array
    {
        $resolved_arguments = [];
        $arguments = count($arguments) ? array_replace($this->config->get_arguments($requested_type), $arguments) : $this->config->get_arguments($requested_type);
        foreach ($parameters as $parameter) {
            [$param_name, $param_type, $param_required, $param_default] = $parameter;
            $argument = null;
            if (!empty($arguments) && (isset($arguments[$param_name]) || array_key_exists($param_name, $arguments))) {
                $argument = $arguments[$param_name];
            } elseif ($param_required) {
                if ($param_type) {
                    $argument = ['instance' => $param_type];
                } else {
                    $this->creation_stack = [];
                    throw new \BadMethodCallException('Missing required argument $' . $param_name . ' of ' . $requested_type . '.');
                }
            } else {
                $argument = $param_default;
            }
            if ($param_type && $argument !== $param_default && !is_object($argument)) {
                if (!isset($argument['instance']) || !is_array($argument)) {
                    throw new \UnexpectedValueException('Invalid parameter configuration provided for $' . $param_name . ' argument of ' . $requested_type);
                }
                $argument_type = $argument['instance'];
                $is_shared = $argument['shared'] ?? $this->config->is_shared($argument_type);
                $argument = $is_shared ? $this->object_manager->get($argument_type) : $this->object_manager->create($argument_type);
            } elseif (is_array($argument)) {
                if (isset($argument['argument'])) {
                    $argument = $this->global_arguments[$argument['argument']] ?? $param_default;
                } elseif (!empty($argument)) {
                    $this->parse_array($argument);
                }
            }
            $resolved_arguments[] = $argument;
        }
        return $resolved_arguments;
    }
    /**
     * Parse array argument
     *
     * @return void
     */
    protected function parse_array(array &$array)
    {
        foreach ($array as $key => $item) {
            if (is_array($item)) {
                if (isset($item['instance'])) {
                    $item_type = $item['instance'];
                    $is_shared = $item['shared'] ?? $this->config->is_shared($item_type);
                    $array[$key] = $is_shared ? $this->object_manager->get($item_type) : $this->object_manager->create($item_type);
                } elseif (isset($item['argument'])) {
                    $array[$key] = $this->global_arguments[$item['argument']] ?? null;
                } else {
                    $this->parse_array($array[$key]);
                }
            }
        }
    }
    /**
     * Create instance with call time arguments
     *
     * @param string $requestedType
     * @return object
     * @throws \Exception
     * @SuppressWarnings(PHPCPD)
     */
    public function create($requested_type, array $arguments = [])
    {
        $type = $this->config->get_instance_type($requested_type);
        $parameters = $this->definitions->get_parameters($type);
        if ($parameters === null) {
            return new $type();
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
        $reflection = new \ReflectionClass($type);
        return $reflection->new_instance_args($args);
    }
    /**
     * Set global arguments
     *
     * @param array $arguments
     */
    public function set_arguments($arguments): void
    {
        $this->global_arguments = $arguments;
    }
}