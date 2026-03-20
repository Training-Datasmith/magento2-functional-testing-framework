<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Handlers;

use Magento\Functional_Testing_Framework\Data_Generator\Objects\Entity_Data_Object;
use Magento\Functional_Testing_Framework\Data_Generator\Parsers\Data_Profile_Schema_Parser;
use Magento\Functional_Testing_Framework\Data_Generator\Util\Data_Extension_Util;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Xml_Exception;
use Magento\Functional_Testing_Framework\Object_Manager\Object_Handler_Interface;
use Magento\Functional_Testing_Framework\Object_Manager_Factory;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
use Magento\Functional_Testing_Framework\Util\Validation\Name_Validation_Util;
class Data_Object_Handler implements Object_Handler_Interface
{
    public const _ENTITY = 'entity';
    public const _NAME = 'name';
    public const _TYPE = 'type';
    public const _EXTENDS = 'extends';
    public const _DATA = 'data';
    public const _KEY = 'key';
    public const _VALUE = 'value';
    public const _UNIQUE = 'unique';
    public const _PREFIX = 'prefix';
    public const _SUFFIX = 'suffix';
    public const _ARRAY = 'array';
    public const _ITEM = 'item';
    public const _VAR = 'var';
    public const _ENTITY_TYPE = 'entityType';
    public const _ENTITY_KEY = 'entityKey';
    public const _SEPARATOR = '->';
    public const _REQUIRED_ENTITY = 'requiredEntity';
    public const _FILENAME = 'filename';
    public const DATA_NAME_ERROR_MSG = "Entity names cannot contain non alphanumeric characters.\tData='%s'";
    /**
     * The singleton instance of this class
     */
    private static ?\Magento\Functional_Testing_Framework\Data_Generator\Handlers\Data_Object_Handler $INSTANCE = null;
    /**
     * A collection of entity data objects that were seen in XML files and the .env file
     *
     * @var EntityDataObject[] $entityDataObjects
     */
    private $entity_data_objects = [];
    /**
     * Instance of DataExtensionUtil class
     */
    private readonly \Magento\Functional_Testing_Framework\Data_Generator\Util\Data_Extension_Util $extend_util;
    /**
     * Validates and keeps track of entity name violations.
     */
    private readonly \Magento\Functional_Testing_Framework\Util\Validation\Name_Validation_Util $entity_name_validator;
    /**
     * Validates and keeps track of entity key violations.
     */
    private readonly \Magento\Functional_Testing_Framework\Util\Validation\Name_Validation_Util $entity_key_validator;
    /**
     * Constructor
     */
    private function __construct()
    {
        $parser = Object_Manager_Factory::get_object_manager()->create(Data_Profile_Schema_Parser::class);
        $parser_output = $parser->read_data_profiles();
        if (!$parser_output) {
            return;
        }
        $this->entity_name_validator = new Name_Validation_Util();
        $this->entity_key_validator = new Name_Validation_Util();
        $this->entity_data_objects = $this->process_parser_output($parser_output);
        $this->extend_util = new Data_Extension_Util();
    }
    /**
     * Return the singleton instance of this class. Initialize it if needed.
     *
     * @return DataObjectHandler
     * @throws \Exception
     */
    public static function get_instance()
    {
        if (!self::$INSTANCE) {
            self::$INSTANCE = new Data_Object_Handler();
        }
        return self::$INSTANCE;
    }
    /**
     * Get an EntityDataObject by name
     *
     * @param string $name The name of the entity you want. Comes from the name attribute in data xml.
     * @return EntityDataObject | null
     */
    public function get_object($name)
    {
        if (array_key_exists($name, $this->entity_data_objects)) {
            return $this->extend_data_object($this->entity_data_objects[$name]);
        }
        return null;
    }
    /**
     * Get all EntityDataObjects
     *
     * @return EntityDataObject[]
     */
    public function get_all_objects()
    {
        foreach ($this->entity_data_objects as $entity_name => $entity_object) {
            $this->entity_data_objects[$entity_name] = $this->extend_data_object($entity_object);
        }
        return $this->entity_data_objects;
    }
    /**
     * Convert the parser output into a collection of EntityDataObjects
     *
     * @param string[] $parserOutput Primitive array output from the Magento parser.
     * @return EntityDataObject[]
     * @throws XmlException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function process_parser_output(array $parser_output): array
    {
        $entity_data_objects = [];
        $raw_entities = $parser_output[self::_ENTITY];
        foreach ($raw_entities as $name => $raw_entity) {
            if (preg_match('/[^a-zA-Z0-9_]/', (string) $name)) {
                throw new Xml_Exception(sprintf(self::DATA_NAME_ERROR_MSG, $name));
            }
            $filename = $raw_entity[self::_FILENAME] ?? null;
            $this->entity_name_validator->validate_pascal_case($name, Name_Validation_Util::DATA_ENTITY_NAME, $filename);
            $type = $raw_entity[self::_TYPE] ?? null;
            $data = [];
            $deprecated = null;
            $linked_entities = [];
            $uniqueness_data = [];
            $vars = [];
            $parent_entity = null;
            if (array_key_exists(self::_DATA, $raw_entity)) {
                $data = $this->process_data_elements($raw_entity);
                $uniqueness_data = $this->process_uniqueness_data($raw_entity);
            }
            if (array_key_exists(self::_REQUIRED_ENTITY, $raw_entity)) {
                $linked_entities = $this->process_linked_entities($raw_entity);
            }
            if (array_key_exists(self::_ARRAY, $raw_entity)) {
                $arrays = $raw_entity[self::_ARRAY];
                foreach ($arrays as $array) {
                    $key = strtolower((string) $array[self::_KEY]);
                    $data[$key] = $this->process_array($array[self::_ITEM], $data, $key);
                }
            }
            if (array_key_exists(self::_VAR, $raw_entity)) {
                $vars = $this->process_var_elements($raw_entity);
            }
            if (array_key_exists(self::_EXTENDS, $raw_entity)) {
                $parent_entity = $raw_entity[self::_EXTENDS];
            }
            if (array_key_exists(self::OBJ_DEPRECATED, $raw_entity)) {
                $deprecated = $raw_entity[self::OBJ_DEPRECATED];
                Logging_Util::get_instance()->get_logger(self::class)->deprecation("The data entity '{$name}' is deprecated.", ['fileName' => $filename, 'deprecatedMessage' => $deprecated]);
            }
            $entity_data_object = new Entity_Data_Object($name, $type, $data, $linked_entities, $uniqueness_data, $vars, $parent_entity, $filename, $deprecated);
            $entity_data_objects[$entity_data_object->get_name()] = $entity_data_object;
        }
        $this->entity_name_validator->summarize(Name_Validation_Util::DATA_ENTITY_NAME);
        $this->entity_key_validator->summarize(Name_Validation_Util::DATA_ENTITY_KEY);
        return $entity_data_objects;
    }
    /**
     * Takes an array of items and a top level entity data array and merges in elements from parsed entity definitions.
     *
     * @param array  $arrayItems
     * @param string $key
     */
    private function process_array($array_items, array $data, $key): array
    {
        $items = [];
        foreach ($array_items as $key => $item) {
            $items[$key] = $item[self::_VALUE];
        }
        return array_merge($items, $data[$key] ?? []);
    }
    /**
     * Parses <data> elements in an entity, and returns them as an array of "lowerKey"=>value.
     *
     * @param string[] $entityData
     * @return string[]
     */
    private function process_data_elements(array $entity_data): array
    {
        $data_values = [];
        foreach ($entity_data[self::_DATA] as $data_element) {
            $original_data_element_key = $data_element[self::_KEY];
            $filename = $entity_data[self::_FILENAME] ?? null;
            $this->entity_key_validator->validate_camel_case($original_data_element_key, Name_Validation_Util::DATA_ENTITY_KEY, $filename);
            $data_element_key = strtolower((string) $original_data_element_key);
            $data_element_value = $data_element[self::_VALUE] ?? '';
            $data_values[$data_element_key] = $data_element_value;
        }
        return $data_values;
    }
    /**
     * Parses through <data> elements in an entity to return an array of "DataKey" => "UniquenessAttribute"
     *
     * @param string[] $entityData
     * @return string[]
     */
    private function process_uniqueness_data(array $entity_data): array
    {
        $uniqueness_values = [];
        foreach ($entity_data[self::_DATA] as $data_element) {
            if (array_key_exists(self::_UNIQUE, $data_element)) {
                $data_element_key = strtolower((string) $data_element[self::_KEY]);
                $uniqueness_values[$data_element_key] = $data_element[self::_UNIQUE];
            }
        }
        return $uniqueness_values;
    }
    /**
     * Parses <requiredEntity> elements given entity, and returns them as an array of "EntityValue"=>"EntityType"
     *
     * @param string[] $entityData
     * @return string[]
     */
    private function process_linked_entities(array $entity_data): array
    {
        $linked_entities = [];
        foreach ($entity_data[self::_REQUIRED_ENTITY] as $linked_entity) {
            $linked_entity_name = $linked_entity[self::_VALUE];
            $linked_entity_type = $linked_entity[self::_TYPE];
            $linked_entities[$linked_entity_name] = $linked_entity_type;
        }
        return $linked_entities;
    }
    /**
     * Parses <var> elements in given entity, and returns them as an array of "Key"=> entityType -> entityKey
     *
     * @param string[] $entityData
     * @return string[]
     */
    private function process_var_elements(array $entity_data): array
    {
        $vars = [];
        foreach ($entity_data[self::_VAR] as $var_element) {
            $var_key = $var_element[self::_KEY];
            $var_value = $var_element[self::_ENTITY_TYPE] . self::_SEPARATOR . $var_element[self::_ENTITY_KEY];
            $vars[$var_key] = $var_value;
        }
        return $vars;
    }
    /**
     * This method checks if the data object is extended and creates a new data object accordingly
     *
     * @param EntityDataObject $dataObject
     * @return EntityDataObject
     * @throws TestFrameworkException
     */
    private function extend_data_object($data_object)
    {
        if ($data_object->get_parent_name() !== null) {
            if ($data_object->get_parent_name() === $data_object->get_name()) {
                throw new Test_Framework_Exception('Mftf Data can not extend from itself: ' . $data_object->get_name());
            }
            return $this->extend_util->extend_entity($data_object);
        }
        return $data_object;
    }
}