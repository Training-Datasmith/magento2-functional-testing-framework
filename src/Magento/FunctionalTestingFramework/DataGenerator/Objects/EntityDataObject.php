<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Objects;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Data_Generator\Util\Generation_Data_Reference_Resolver;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Reference_Exception;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
/**
 * Class EntityDataObject
 */
class Entity_Data_Object
{
    public const NO_UNIQUE_PROCESS = 0;
    public const SUITE_UNIQUE_VALUE = 1;
    public const CEST_UNIQUE_VALUE = 2;
    public const SUITE_UNIQUE_NOTATION = 3;
    public const CEST_UNIQUE_NOTATION = 4;
    public const SUITE_UNIQUE_FUNCTION = 'msqs';
    public const CEST_UNIQUE_FUNCTION = 'msq';
    /**
     * Array of data name and its uniqueness attribute value.
     *
     * @var string[]
     */
    private $uniqueness_data = [];
    /**
     * Constructor
     *
     * @param string      $name
     * @param string      $type
     * @param string[]    $data
     * @param string[]    $linkedEntities
     * @param string[]    $uniquenessData
     * @param string[]    $vars
     * @param string      $parentEntity
     * @param string      $filename
     * @param string|null $deprecated
     */
    public function __construct(
        /**
         * Name of the entity
         */
        private $name,
        /**
         * Type of the entity
         */
        private $type,
        /**
         * An array of Data Name to Data Value
         */
        private $data,
        /**
         * An array of required entity name to corresponding type
         */
        private $linked_entities,
        $uniqueness_data,
        /**
         * An array of variable mappings for static data
         */
        private $vars = [],
        /**
         * String of parent Entity
         */
        private $parent_entity = null,
        /**
         * String of filename
         */
        private $filename = null,
        /**
         * Deprecated message.
         */
        private $deprecated = null
    )
    {
        if ($uniqueness_data) {
            $this->uniqueness_data = $uniqueness_data;
        }
    }
    /**
     * Getter for the deprecated attr of the section.
     *
     * @return string
     */
    public function get_deprecated()
    {
        return $this->deprecated;
    }
    /**
     * Get the name of this entity data object
     *
     * @return string
     */
    public function get_name()
    {
        return $this->name;
    }
    /**
     * Getter for the Entity Filename
     *
     * @return string
     */
    public function get_filename()
    {
        return $this->filename;
    }
    /**
     * Get the type of this entity data object
     *
     * @return string
     */
    public function get_type()
    {
        return $this->type;
    }
    /**
     * Getter for data array (field name to value)
     *
     * @return \string[]
     */
    public function get_all_data()
    {
        return $this->data;
    }
    /**
     * Get a piece of data by name and the desired uniqueness format.
     *
     * @param string  $name
     * @param integer $uniquenessFormat
     * @return string|null
     * @throws TestFrameworkException
     */
    public function get_data_by_name($name, $uniqueness_format)
    {
        if (Mftf_Application_Config::get_config()->verbose_enabled()) {
            Logging_Util::get_instance()->get_logger(Entity_Data_Object::class)->debug('Fetching data field from entity', ['entity' => $this->get_name(), 'field' => $name]);
        }
        if (!$this->is_valid_unique_data_format($uniqueness_format)) {
            $exception_message = sprintf("Invalid unique data format value: %s \n", $uniqueness_format);
            Logging_Util::get_instance()->get_logger(Entity_Data_Object::class)->error($exception_message, ['entity' => $this->get_name(), 'field' => $name]);
            throw new Test_Framework_Exception($exception_message);
        }
        if ($this->data === null) {
            return null;
        }
        return $this->resolve_data_references($name, $uniqueness_format);
    }
    /**
     * Resolves data references in entities while generating static test files.
     *
     * @param string  $name
     * @param integer $uniquenessFormat
     * @return string|null
     * @throws TestFrameworkException
     * @throws TestReferenceException
     */
    private function resolve_data_references($name, $uniqueness_format)
    {
        $name_lower = strtolower($name);
        $data_reference_resolver = new Generation_Data_Reference_Resolver();
        if (array_key_exists($name_lower, $this->data)) {
            if (is_array($this->data[$name_lower])) {
                return $this->data[$name_lower];
            }
            $uniqueness_data = $this->get_uniqueness_data_by_name($name_lower) ?? $data_reference_resolver->get_data_uniqueness($this->data[$name_lower], $this->name . '.' . $name);
            if ($uniqueness_data !== null) {
                $this->uniqueness_data[$name] = $uniqueness_data;
            }
            $this->data[$name_lower] = $data_reference_resolver->get_data_reference($this->data[$name_lower], $this->name . '.' . $name);
            if (null === $uniqueness_data || $uniqueness_format === self::NO_UNIQUE_PROCESS) {
                return $this->data[$name_lower];
            }
            return $this->format_unique_data($name_lower, $uniqueness_data, $uniqueness_format);
        }
        if (array_key_exists($name, $this->data)) {
            if (is_array($this->data[$name])) {
                return $this->data[$name];
            }
            $this->data[$name] = $data_reference_resolver->get_data_reference($this->data[$name], $this->name . '.' . $name);
            // Data returned by the API may be camelCase so we need to check for the original $name also.
            return $this->data[$name];
        }
        return null;
    }
    /**
     * Getter for data parent
     *
     * @return \string
     */
    public function get_parent_name()
    {
        return $this->parent_entity;
    }
    /**
     * Formats and returns data based on given uniqueDataFormat and prefix/suffix.
     *
     * @param string $name
     * @param string $uniqueData
     * @param string $uniqueDataFormat
     * @throws TestFrameworkException
     */
    private function format_unique_data($name, $unique_data, $unique_data_format): ?string
    {
        switch ($unique_data_format) {
            case self::SUITE_UNIQUE_VALUE:
                $this->check_uniqueness_function_exists(self::SUITE_UNIQUE_FUNCTION, $unique_data_format);
                if ($unique_data === 'prefix') {
                    return msqs($this->get_name()) . $this->data[$name];
                }
                // $uniData == 'suffix'
                return $this->data[$name] . msqs($this->get_name());
            case self::CEST_UNIQUE_VALUE:
                $this->check_uniqueness_function_exists(self::CEST_UNIQUE_FUNCTION, $unique_data_format);
                if ($unique_data === 'prefix') {
                    return msq($this->get_name()) . $this->data[$name];
                }
                // $uniqueData == 'suffix'
                return $this->data[$name] . msq($this->get_name());
            case self::SUITE_UNIQUE_NOTATION:
                if ($unique_data === 'prefix') {
                    return self::SUITE_UNIQUE_FUNCTION . '("' . $this->get_name() . '")' . $this->data[$name];
                }
                // $uniqueData == 'suffix'
                return $this->data[$name] . self::SUITE_UNIQUE_FUNCTION . '("' . $this->get_name() . '")';
            case self::CEST_UNIQUE_NOTATION:
                if ($unique_data === 'prefix') {
                    return self::CEST_UNIQUE_FUNCTION . '("' . $this->get_name() . '")' . $this->data[$name];
                }
                // $uniqueData == 'suffix'
                return $this->data[$name] . self::CEST_UNIQUE_FUNCTION . '("' . $this->get_name() . '")';
            default:
                break;
        }
        return null;
    }
    /**
     * Performs a check that the given uniqueness function exists, throws an exception if it doesn't.
     *
     * @throws TestFrameworkException
     */
    private function check_uniqueness_function_exists(string $function, string $unique_data_format): void
    {
        if (!function_exists($function)) {
            $exception_message = sprintf('Unique data format value: %s can only be used when running cests.\n', $unique_data_format);
            throw new Test_Framework_Exception($exception_message);
        }
    }
    /**
     * Function which returns a reference to another entity (e.g. a var with entity="category" field="id" returns as
     * category->id)
     *
     * @param string $key
     */
    public function get_var_reference($key): ?string
    {
        if (array_key_exists($key, $this->vars)) {
            return $this->vars[$key];
        }
        return null;
    }
    /**
     * This function takes an array of entityTypes indexed by name and a string that represents the type of interest.
     * The function returns an array of entityNames relevant to the specified type.
     *
     * @param string $type
     */
    public function get_linked_entities_of_type($type): array
    {
        $grouped_array = [];
        foreach ($this->linked_entities as $entity_name => $entity_type) {
            if ($entity_type === $type) {
                $grouped_array[] = $entity_name;
            }
        }
        return $grouped_array;
    }
    /**
     * Get array of entity names specified as associated to this entity.
     *
     * @return \string[]
     */
    public function get_linked_entities()
    {
        return $this->linked_entities;
    }
    /**
     * Get array of var based fields defined in this entity.
     *
     * @return \string[]
     */
    public function get_var_references()
    {
        return $this->vars;
    }
    /**
     * This function retrieves uniqueness data by its name.
     *
     * @param string $dataName
     */
    public function get_uniqueness_data_by_name($data_name): ?string
    {
        $name = strtolower($data_name);
        if (array_key_exists($name, $this->uniqueness_data)) {
            return $this->uniqueness_data[$name];
        }
        return null;
    }
    /**
     * This function retrieves uniqueness data.
     *
     * @return array|null
     */
    public function get_uniqueness_data()
    {
        return $this->uniqueness_data;
    }
    /**
     * Validate if input value is a valid unique data format.
     *
     * @param integer $uniDataFormat
     */
    private function is_valid_unique_data_format($uni_data_format): bool
    {
        return in_array($uni_data_format, [self::NO_UNIQUE_PROCESS, self::SUITE_UNIQUE_VALUE, self::CEST_UNIQUE_VALUE, self::SUITE_UNIQUE_NOTATION, self::CEST_UNIQUE_NOTATION], true);
    }
}