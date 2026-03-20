<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager\Config;

use Magento\Functional_Testing_Framework\Object_Manager\Definition\Runtime as DefinitionRuntime;
use Magento\Functional_Testing_Framework\Object_Manager\Definition_Interface;
use Magento\Functional_Testing_Framework\Object_Manager\Relations\Runtime as RelationsRuntime;
use Magento\Functional_Testing_Framework\Object_Manager\Relations_Interface;
/**
 * Class Config
 */
class Config implements \Magento\Functional_Testing_Framework\Object_Manager\Config_Interface
{
    /**
     * Class definitions
     */
    protected \Magento\Functional_Testing_Framework\Object_Manager\Definition_Interface $definitions;
    /**
     * Current cache key
     *
     * @var string
     */
    protected $current_cache_key;
    /**
     * Interface preferences
     *
     * @var array
     */
    protected $preferences = [];
    /**
     * Virtual types
     *
     * @var array
     */
    protected $virtual_types = [];
    /**
     * Instance arguments
     *
     * @var array
     */
    protected $arguments = [];
    /**
     * Type shareability
     *
     * @var array
     */
    protected $non_shared = [];
    /**
     * List of relations
     */
    protected \Magento\Functional_Testing_Framework\Object_Manager\Relations_Interface $relations;
    /**
     * List of merged arguments
     *
     * @var array
     */
    protected $merged_arguments;
    /**
     * Config constructor.
     */
    public function __construct(?Relations_Interface $relations = null, ?Definition_Interface $definitions = null)
    {
        $this->relations = $relations ?: new Relations_Runtime();
        $this->definitions = $definitions ?: new Definition_Runtime();
    }
    /**
     * Retrieve list of arguments per type
     *
     * @param string $type
     * @return array
     */
    public function get_arguments($type)
    {
        return $this->merged_arguments[$type] ?? $this->collect_configuration($type);
    }
    /**
     * Check whether type is shared
     *
     * @param string $type
     */
    public function is_shared($type): bool
    {
        return !isset($this->non_shared[$type]);
    }
    /**
     * Retrieve instance type
     *
     * @param string $instanceName
     * @return string
     */
    public function get_instance_type($instance_name)
    {
        while (isset($this->virtual_types[$instance_name])) {
            $instance_name = $this->virtual_types[$instance_name];
        }
        return $instance_name;
    }
    /**
     * Retrieve preference for type
     *
     * @param string $type
     * @return string
     * @throws \LogicException
     */
    public function get_preference($type)
    {
        $type = ltrim($type, '\\');
        $preference_path = [];
        while (isset($this->preferences[$type])) {
            if (isset($preference_path[$this->preferences[$type]])) {
                throw new \LogicException('Circular type preference: ' . $type . ' relates to ' . $this->preferences[$type] . ' and viceversa.');
            }
            $type = $this->preferences[$type];
            $preference_path[$type] = 1;
        }
        return $type;
    }
    /**
     * Collect parent types configuration for requested type
     *
     * @param string $type
     * @return array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * Revisited to reduce cyclomatic complexity, left unrefactored for readability
     */
    protected function collect_configuration($type)
    {
        if (!isset($this->merged_arguments[$type])) {
            if (isset($this->virtual_types[$type])) {
                $arguments = $this->collect_configuration($this->virtual_types[$type]);
            } else if ($this->relations->has($type)) {
                $relations = $this->relations->get_parents($type);
                $arguments = [];
                foreach ($relations as $relation) {
                    if ($relation) {
                        $relation_arguments = $this->collect_configuration($relation);
                        if ($relation_arguments) {
                            $arguments = array_replace($arguments, $relation_arguments);
                        }
                    }
                }
            } else {
                $arguments = [];
            }
            if (isset($this->arguments[$type])) {
                if ($arguments && count($arguments)) {
                    $arguments = array_replace_recursive($arguments, $this->arguments[$type]);
                } else {
                    $arguments = $this->arguments[$type];
                }
            }
            $this->merged_arguments[$type] = $arguments;
            return $arguments;
        }
        return $this->merged_arguments[$type];
    }
    /**
     * Merge configuration
     *
     * @return void
     */
    protected function merge_configuration(array $configuration)
    {
        foreach ($configuration as $key => $cur_config) {
            switch ($key) {
                case 'preferences':
                    foreach ($cur_config as $for => $to) {
                        $this->preferences[ltrim((string) $for, '\\')] = ltrim((string) $to, '\\');
                    }
                    break;
                default:
                    $this->set_configuration($key, $cur_config);
            }
        }
    }
    /**
     * Set configuration
     *
     * @param string $key
     */
    private function set_configuration(int|string $key, array $config): void
    {
        $key = ltrim((string) $key, '\\');
        if (isset($config['type'])) {
            $this->virtual_types[$key] = ltrim($config['type'], '\\');
        }
        if (isset($config['arguments'])) {
            if (!empty($this->merged_arguments)) {
                $this->merged_arguments = [];
            }
            if (isset($this->arguments[$key])) {
                $this->arguments[$key] = array_replace($this->arguments[$key], $config['arguments']);
            } else {
                $this->arguments[$key] = $config['arguments'];
            }
        }
        if (isset($config['shared'])) {
            if (!$config['shared']) {
                $this->non_shared[$key] = 1;
            } else {
                unset($this->non_shared[$key]);
            }
        }
    }
    /**
     * Extend configuration
     */
    public function extend(array $configuration): void
    {
        $this->merge_configuration($configuration);
    }
}