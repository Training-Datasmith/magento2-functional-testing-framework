<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Util;

use Magento\Functional_Testing_Framework\Data_Generator\Handlers\Operation_Definition_Object_Handler;
use Magento\Functional_Testing_Framework\Data_Generator\Objects\Operation_Element;
class Operation_Element_Extractor
{
    public const OPERATION_OBJECT_KEY = 'key';
    public const OPERATION_OBJECT_DATA_TYPE = 'dataType';
    public const OPERATION_OBJECT_ARRAY = 'array';
    public const OPERATION_OBJECT_ENTRY = 'field';
    public const OPERATION_OBJECT_OBJ_NAME = 'object';
    public const OPERATION_OBJECT_ARRAY_VALUE = 'value';
    /**
     * OperationElementExtractor constructor.
     */
    public function __construct()
    {
        // public constructor
    }
    /**
     * Takes an array representative of a dataObject and converts the array into a OperationElement
     *
     * @throws \Exception
     */
    public function extract_operation_element(array $operation_element_array): \Magento\Functional_Testing_Framework\Data_Generator\Objects\Operation_Element
    {
        // extract key
        $operation_def_key = $operation_element_array[Operation_Element_Extractor::OPERATION_OBJECT_KEY];
        // extract dataType
        $data_type = $operation_element_array[Operation_Element_Extractor::OPERATION_OBJECT_DATA_TYPE];
        $operation_elements = [];
        $nested_operation_elements = [];
        // extract nested entries
        if (array_key_exists(Operation_Element_Extractor::OPERATION_OBJECT_ENTRY, $operation_element_array)) {
            $this->extract_operation_field($operation_elements, $operation_element_array[Operation_Element_Extractor::OPERATION_OBJECT_ENTRY]);
        }
        // extract nested arrays
        if (array_key_exists(Operation_Element_Extractor::OPERATION_OBJECT_ARRAY, $operation_element_array)) {
            $this->extract_operation_array($operation_elements, $operation_element_array[Operation_Element_Extractor::OPERATION_OBJECT_ARRAY]);
        }
        // extract nested
        if (array_key_exists(Operation_Element_Extractor::OPERATION_OBJECT_OBJ_NAME, $operation_element_array)) {
            foreach ($operation_element_array[Operation_Element_Extractor::OPERATION_OBJECT_OBJ_NAME] as $operation_object) {
                $nested_operation_element = $this->extract_operation_element($operation_object);
                $operation_elements[] = $nested_operation_element;
            }
        }
        // a dataObject specified in xml must contain corresponding metadata for the object
        if (empty($operation_elements)) {
            throw new \Exception('must specify dataObject metadata if declaration is used');
        }
        return new Operation_Element($operation_def_key, $data_type, Operation_Element_Extractor::OPERATION_OBJECT_OBJ_NAME, $operation_element_array[Operation_Definition_Object_Handler::ENTITY_OPERATION_REQUIRED] ?? null, $nested_operation_elements, $operation_elements);
    }
    /**
     * Creates and Adds relevant DataElements from data entries defined within dataObject array
     *
     * @param array $operationFieldArray
     */
    private function extract_operation_field(array &$operation_elements, $operation_field_array): void
    {
        foreach ($operation_field_array as $operation_field_type) {
            $operation_elements[] = new Operation_Element($operation_field_type[Operation_Definition_Object_Handler::ENTITY_OPERATION_ENTRY_KEY], $operation_field_type[Operation_Definition_Object_Handler::ENTITY_OPERATION_ENTRY_VALUE], Operation_Definition_Object_Handler::ENTITY_OPERATION_ENTRY, $operation_field_type[Operation_Definition_Object_Handler::ENTITY_OPERATION_REQUIRED] ?? null);
        }
    }
    /**
     * Creates and Adds relevant DataElements from data arrays defined within dataObject array
     *
     * @param array $operationArrayArray
     */
    private function extract_operation_array(array &$operation_array_data, $operation_array_array): void
    {
        foreach ($operation_array_array as $operation_field_type) {
            $operation_element_value = [];
            $entity_value_key = Operation_Definition_Object_Handler::ENTITY_OPERATION_ARRAY_VALUE;
            if (isset($operation_field_type[$entity_value_key])) {
                foreach ($operation_field_type[$entity_value_key] as $operation_field_value) {
                    $operation_element_value[] = $operation_field_value[Operation_Element_Extractor::OPERATION_OBJECT_ARRAY_VALUE] ?? null;
                }
            }
            if (count($operation_element_value) === 1) {
                $operation_element_value = array_pop($operation_element_value);
            }
            $nested_operation_elements = [];
            if (array_key_exists(Operation_Element_Extractor::OPERATION_OBJECT_OBJ_NAME, $operation_field_type)) {
                //add the key to reference this object later
                $operation_object_keyed_array = $operation_field_type[Operation_Element_Extractor::OPERATION_OBJECT_OBJ_NAME][0];
                $operation_object_keyed_array[Operation_Element_Extractor::OPERATION_OBJECT_KEY] = $operation_field_type[Operation_Definition_Object_Handler::ENTITY_OPERATION_ARRAY_KEY];
                $operation_element = $this->extract_operation_element($operation_object_keyed_array);
                $operation_element_value = $operation_element->get_value();
                $nested_operation_elements[$operation_element->get_value()] = $operation_element;
            }
            $operation_array_data[] = new Operation_Element($operation_field_type[Operation_Definition_Object_Handler::ENTITY_OPERATION_ARRAY_KEY], $operation_element_value, Operation_Definition_Object_Handler::ENTITY_OPERATION_ARRAY, $operation_field_type[Operation_Definition_Object_Handler::ENTITY_OPERATION_REQUIRED] ?? null, $nested_operation_elements);
        }
    }
}