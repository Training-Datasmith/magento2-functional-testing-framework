<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Persist;

use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Data_Object_Handler;
use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Operation_Definition_Object_Handler;
use Magento\Functional_Testing_Framework\Data_Generator\Objects\Entity_Data_Object;
use Magento\Functional_Testing_Framework\Data_Generator\Objects\Operation_Element;
use Magento\Functional_Testing_Framework\Data_Generator\Util\Operation_Element_Extractor;
use Magento\Functional_Testing_Framework\Data_Generator\Util\Runtime_Data_Reference_Resolver;
use Magento\Functional_Testing_Framework\Exceptions\Test_Framework_Exception;
use Magento\Functional_Testing_Framework\Exceptions\Test_Reference_Exception;
class Operation_Data_Array_Resolver
{
    public const PRIMITIVE_TYPES = ['string', 'boolean', 'integer', 'number'];
    public const EXCEPTION_REQUIRED_DATA = '%s of key " %s" in "%s" is required by metadata, but was not provided.';
    /**
     * The array of entity name and number of objects being created,
     * we don't need to track objects in update and delete operations.
     */
    private static array $entity_sequences = [];
    /**
     * The array of dependentEntities this class can be given. When finding linked entities, APIExecutor
     * uses this repository before looking for static data.
     *
     * @var array
     */
    private $dependent_entities = [];
    /**
     * OperationDataArrayResolver constructor.
     *
     * @param array $dependentEntities
     */
    public function __construct(?array $dependent_entities = null)
    {
        if ($dependent_entities !== null) {
            foreach ($dependent_entities as $entity) {
                $this->dependent_entities[$entity->get_name()] = $entity;
            }
        }
    }
    /**
     * This function returns an array which is structurally equal to the data which is needed by the magento web api,
     * magento backend / frontend requests for entity creation. The function retrieves an array describing the entity's
     * operation metadata and traverses any dependencies recursively forming an array which represents the data
     * structure for the request of the desired entity type.
     *
     * @param EntityDataObject $entityObject
     * @param array            $operationMetadata
     * @param string           $operation
     * @param boolean          $fromArray
     * @return array
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * I suppressed this warning because I was in a hurry to deliver a community PR. That PR modified this function and
     * introduced a new conditional, bumping the complexity to 11.
     */
    public function resolve_operation_data_array($entity_object, $operation_metadata, $operation, $from_array = false)
    {
        $operation_data_array = [];
        self::increment_sequence($entity_object->get_name());
        foreach ($operation_metadata as $operation_element) {
            if ($operation_element->get_type() === Operation_Element_Extractor::OPERATION_OBJECT_OBJ_NAME) {
                $entity_obj = $this->resolve_operation_object_and_entity_data($entity_object, $operation_element->get_value());
                if (null === $entity_obj && $operation_element->is_required()) {
                    throw new \Exception(sprintf(self::EXCEPTION_REQUIRED_DATA, $operation_element->get_type(), $operation_element->get_key(), $entity_object->get_name()));
                }
                if (null === $entity_obj) {
                    continue;
                }
                $operation_data = $this->resolve_operation_data_array($entity_obj, $operation_element->get_nested_metadata(), $operation, $from_array);
                if (!$from_array) {
                    $operation_data_array[$operation_element->get_key()] = $operation_data;
                } else {
                    $operation_data_array = $operation_data;
                }
                continue;
            }
            $operation_element_type = $operation_element->get_value();
            if (in_array($operation_element_type, self::PRIMITIVE_TYPES)) {
                $this->resolve_primitive_reference_element($entity_object, $operation_element, $operation_element_type, $operation_data_array);
            } elseif (is_array($operation_element_type)) {
                foreach ($operation_element_type as $current_element_type) {
                    if (in_array($current_element_type, self::PRIMITIVE_TYPES)) {
                        $this->resolve_primitive_reference_element($entity_object, $operation_element, $current_element_type, $operation_data_array);
                    } else {
                        $this->resolve_non_primitive_reference_element($entity_object, $operation, $from_array, $current_element_type, $operation_element, $operation_data_array);
                    }
                }
            } else {
                $this->resolve_non_primitive_reference_element($entity_object, $operation, $from_array, $operation_element_type, $operation_element, $operation_data_array);
            }
        }
        return $this->resolve_run_time_data_references($operation_data_array, $entity_object);
    }
    /**
     * Resolve data references at run time.
     * @param EntityDataObject $entityObject
     * @throws TestFrameworkException
     * @throws TestReferenceException
     */
    private function resolve_run_time_data_references(array $operation_data_array, $entity_object): array
    {
        $data_reference_resolver = new Runtime_Data_Reference_Resolver();
        foreach ($operation_data_array as $key => $operation_data_value) {
            if (is_array($operation_data_value)) {
                continue;
            }
            $operation_data_array[$key] = $data_reference_resolver->get_data_reference($operation_data_value, $entity_object->get_name());
        }
        return $operation_data_array;
    }
    /**
     * Resolves a reference for a primitive piece of data, if the data cannot be found as a defined field, the method
     * looks to see if any vars have been declared with the same operationKey and resolves based on defined dependent
     * entities.
     *
     * @param EntityDataObject $entityObject
     * @param string           $operationKey
     * @param string           $operationElementType
     * @return array|string
     * @throws TestFrameworkException
     */
    private function resolve_primitive_reference($entity_object, $operation_key, $operation_element_type)
    {
        $element_data = $entity_object->get_data_by_name($operation_key, Entity_Data_Object::CEST_UNIQUE_VALUE);
        if ($element_data === null && $entity_object->get_var_reference($operation_key) !== null) {
            [$type, $field] = explode(Data_Object_Handler::_SEPARATOR, $entity_object->get_var_reference($operation_key));
            if ($operation_element_type === Operation_Definition_Object_Handler::ENTITY_OPERATION_ARRAY) {
                $element_datas = [];
                $entities = $this->get_dependent_entities_of_type($type);
                foreach ($entities as $entity) {
                    $element_datas[] = $entity->get_data_by_name($field, Entity_Data_Object::CEST_UNIQUE_VALUE);
                }
                return $element_datas;
            }
            $entity = $this->get_dependent_entities_of_type($type)[0];
            $element_data = $entity->get_data_by_name($field, Entity_Data_Object::CEST_UNIQUE_VALUE);
        }
        return $element_data;
    }
    /**
     * Returns all dependent entities of the type passed in as an arg (the dependent entities are given at runtime,
     * and are not statically defined).
     */
    private function get_dependent_entities_of_type(string $type): array
    {
        $entities_of_type = [];
        foreach ($this->dependent_entities as $dependent_entity) {
            if ($dependent_entity->get_type() === $type) {
                $entities_of_type[] = $dependent_entity;
            }
        }
        return $entities_of_type;
    }
    /**
     * This function does a comparison of the entity object being matched to the operation element. If there is a
     * mismatch in type we attempt to use a nested entity, if the entities are properly matched, we simply return
     * the object.
     *
     * @param EntityDataObject $entityObject
     * @param string           $operationElementValue
     * @return EntityDataObject|null
     * @throws \Exception
     */
    private function resolve_operation_object_and_entity_data($entity_object, $operation_element_value)
    {
        if ($operation_element_value !== $entity_object->get_type()) {
            // if we have a mismatch attempt to retrieve linked data and return just the last linkage
            // this enables overwriting of required entity fields
            $link_name = $entity_object->get_linked_entities_of_type($operation_element_value);
            if (!empty($link_name)) {
                $link_name = array_pop($link_name);
                return Data_Object_Handler::get_instance()->get_object($link_name);
            }
            return null;
        }
        return $entity_object;
    }
    /**
     * Resolves DataObjects and pre-defined metadata (in other operation.xml file) referenced by the operation
     *
     * @param string           $entityName
     * @param OperationElement $operationElement
     * @param string           $operation
     * @param boolean          $fromArray
     * @return array
     * @throws \Exception
     */
    private function resolve_non_primitive_element($entity_name, $operation_element, $operation, $from_array = false)
    {
        $linked_entity_obj = $this->resolve_linked_entity_object($entity_name);
        // in array case
        if (!is_array($operation_element->get_value()) && !empty($operation_element->get_nested_operation_element($operation_element->get_value())) && $operation_element->get_type() === Operation_Definition_Object_Handler::ENTITY_OPERATION_ARRAY) {
            return $this->resolve_operation_data_array($linked_entity_obj, [$operation_element->get_nested_operation_element($operation_element->get_value())], $operation, true);
        }
        $operation_metadata = Operation_Definition_Object_Handler::get_instance()->get_operation_definition($operation, $linked_entity_obj->get_type())->get_operation_metadata();
        return $this->resolve_operation_data_array($linked_entity_obj, $operation_metadata, $operation, $from_array);
    }
    /**
     * Method to wrap entity resolution, checks locally defined dependent entities first
     *
     * @param string $entityName
     * @return EntityDataObject
     * @throws \Exception
     */
    private function resolve_linked_entity_object($entity_name)
    {
        // check our dependent entity list to see if we have this defined
        if (array_key_exists($entity_name, $this->dependent_entities)) {
            return $this->dependent_entities[$entity_name];
        }
        return Data_Object_Handler::get_instance()->get_object($entity_name);
    }
    /**
     * Increment an entity's sequence number by 1.
     *
     * @param string $entityName
     */
    private static function increment_sequence($entity_name): void
    {
        if (array_key_exists($entity_name, self::$entity_sequences)) {
            self::$entity_sequences[$entity_name]++;
        } else {
            self::$entity_sequences[$entity_name] = 1;
        }
    }
    /**
     * Get the current sequence number for an entity.
     *
     * @param string $entityName
     * @return integer
     */
    private static function get_sequence($entity_name)
    {
        if (array_key_exists($entity_name, self::$entity_sequences)) {
            return self::$entity_sequences[$entity_name];
        }
        return 0;
    }
    // @codingStandardsIgnoreStart
    /**
     * This function takes a string value and its corresponding type and returns the string cast
     * into its the type passed.
     *
     * @param string $type
     * @param string $value
     * @return mixed
     */
    private function cast_value($type, $value)
    {
        $new_val = $value;
        if (is_array($value)) {
            $new_vals = [];
            foreach ($value as $val) {
                $new_vals[] = $this->cast_value($type, $val);
            }
            return $new_vals;
        }
        switch ($type) {
            case 'string':
                break;
            case 'integer':
                $new_val = (int) $value;
                break;
            case 'boolean':
                if (strtolower($new_val) === 'false') {
                    return false;
                }
                $new_val = (bool) $value;
                break;
            case 'number':
                $new_val = (float) $value;
                break;
        }
        return $new_val;
    }
    /**
     * Resolve a reference for a primitive piece of data
     *
     * @param EntityDataObject $entityObject
     * @param $operationElement
     * @param $operationElementType
     * @throws TestFrameworkException
     */
    private function resolve_primitive_reference_element($entity_object, $operation_element, $operation_element_type, array &$operation_data_array): void
    {
        $element_data = $this->resolve_primitive_reference($entity_object, $operation_element->get_key(), $operation_element->get_type());
        // If data was defined at all, attempt to put it into operation data array
        // If data was not defined, and element is required, throw exception
        // If no data is defined, don't input defaults per primitive into operation data array
        if ($element_data !== null && $element_data !== '') {
            if (array_key_exists($operation_element->get_key(), $entity_object->get_uniqueness_data())) {
                $unique_data = $entity_object->get_uniqueness_data_by_name($operation_element->get_key());
                if ($unique_data === 'suffix') {
                    $element_data .= (string) self::get_sequence($entity_object->get_name());
                } else {
                    $element_data = self::get_sequence($entity_object->get_name()) . $element_data;
                }
            }
            $operation_data_array[$operation_element->get_key()] = $this->cast_value($operation_element_type, $element_data);
        } elseif ($operation_element->is_required()) {
            throw new \Exception(sprintf(self::EXCEPTION_REQUIRED_DATA, $operation_element->get_type(), $operation_element->get_key(), $entity_object->get_name()));
        }
    }
    /**
     * Resolves DataObjects referenced by the operation
     *
     * @param $entityObject
     * @param $operation
     * @param $fromArray
     * @param $operationElementType
     * @param $operationElement
     * @throws TestFrameworkException
     */
    private function resolve_non_primitive_reference_element($entity_object, $operation, $from_array, &$operation_element_type, $operation_element, array &$operation_data_array): void
    {
        $operation_element_property = null;
        if (str_contains((string) $operation_element_type, '.')) {
            $operation_element_components = explode('.', (string) $operation_element_type);
            $operation_element_type = $operation_element_components[0];
            $operation_element_property = $operation_element_components[1];
        }
        $entity_names_of_type = $entity_object->get_linked_entities_of_type($operation_element_type);
        // If an element is required by metadata, but was not provided in the entity, throw an exception
        if ($operation_element->is_required() && $entity_names_of_type === null) {
            throw new \Exception(sprintf(self::EXCEPTION_REQUIRED_DATA, $operation_element->get_type(), $operation_element->get_key(), $entity_object->get_name()));
        }
        foreach ($entity_names_of_type as $entity_name) {
            if ($operation_element_property === null) {
                $operation_data_sub_array = $this->resolve_non_primitive_element($entity_name, $operation_element, $operation, $from_array);
            } else {
                $linked_entity_obj = $this->resolve_linked_entity_object($entity_name);
                $operation_data_sub_array = $linked_entity_obj->get_data_by_name($operation_element_property, 0);
                if ($operation_data_sub_array === null) {
                    throw new \Exception(sprintf('Property %s not found in entity %s \n', $operation_element_property, $entity_name));
                }
            }
            if ($operation_element->get_type() === Operation_Definition_Object_Handler::ENTITY_OPERATION_ARRAY) {
                $operation_data_array[$operation_element->get_key()][] = $operation_data_sub_array;
            } else {
                $operation_data_array[$operation_element->get_key()] = $operation_data_sub_array;
            }
        }
    }
    // @codingStandardsIgnoreEnd
}