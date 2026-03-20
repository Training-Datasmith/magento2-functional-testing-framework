<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Handlers;

use Magento\Functional_Testing_Framework\Data_Generator\Objects\Operation_Definition_Object;
use Magento\Functional_Testing_Framework\Data_Generator\Objects\Operation_Element;
use Magento\Functional_Testing_Framework\Data_Generator\Parsers\Operation_Definition_Parser;
use Magento\Functional_Testing_Framework\Data_Generator\Util\Operation_Element_Extractor;
use Magento\Functional_Testing_Framework\Object_Manager\Object_Handler_Interface;
use Magento\Functional_Testing_Framework\Object_Manager_Factory;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
use Magento\Functional_Testing_Framework\Util\Validation\Name_Validation_Util;
class Operation_Definition_Object_Handler implements Object_Handler_Interface
{
    public const ENTITY_OPERATION_ROOT_TAG = 'operation';
    public const ENTITY_OPERATION_TYPE = 'type';
    public const ENTITY_OPERATION_DATA_TYPE = 'dataType';
    public const ENTITY_OPERATION_URL = 'url';
    public const ENTITY_OPERATION_METHOD = 'method';
    public const ENTITY_OPERATION_AUTH = 'auth';
    public const ENTITY_OPERATION_URL_AREA = 'area';
    public const ENTITY_OPERATION_STORE_CODE = 'storeCode';
    public const ENTITY_OPERATION_SUCCESS_REGEX = 'successRegex';
    public const ENTITY_OPERATION_RETURN_REGEX = 'returnRegex';
    public const ENTITY_OPERATION_RETURN_INDEX = 'returnIndex';
    public const ENTITY_OPERATION_HEADER = 'header';
    public const ENTITY_OPERATION_CONTENT_TYPE = 'contentType';
    public const ENTITY_OPERATION_HEADER_PARAM = 'param';
    public const ENTITY_OPERATION_HEADER_VALUE = 'value';
    public const ENTITY_OPERATION_URL_PARAM = 'param';
    public const ENTITY_OPERATION_URL_PARAM_KEY = 'key';
    public const ENTITY_OPERATION_URL_PARAM_VALUE = 'value';
    public const ENTITY_OPERATION_ENTRY = 'field';
    public const ENTITY_OPERATION_ENTRY_KEY = 'key';
    public const ENTITY_OPERATION_ENTRY_VALUE = 'value';
    public const ENTITY_OPERATION_ARRAY = 'array';
    public const ENTITY_OPERATION_ARRAY_KEY = 'key';
    public const ENTITY_OPERATION_ARRAY_VALUE = 'value';
    public const ENTITY_OPERATION_OBJECT = 'object';
    public const ENTITY_OPERATION_OBJECT_KEY = 'key';
    public const ENTITY_OPERATION_OBJECT_VALUE = 'value';
    public const ENTITY_OPERATION_REQUIRED = 'required';
    public const ENTITY_OPERATION_BACKEND_REMOVE = 'removeBackend';
    /**
     * The singleton instance of this class
     */
    private static ?\Magento\Functional_Testing_Framework\Data_Generator\Handlers\Operation_Definition_Object_Handler $INSTANCE = null;
    /**
     * An array containing all <operation>
     *
     * @var OperationDefinitionObject[]
     */
    private array $operation_definition_objects = [];
    /**
     * A helper used to convert the primitive array parser output into objects.
     */
    private readonly \Magento\Functional_Testing_Framework\Data_Generator\Util\Operation_Element_Extractor $operation_element_extractor;
    /**
     * The constructor
     */
    private function __construct()
    {
        $this->operation_element_extractor = new Operation_Element_Extractor();
    }
    /**
     * Return the singleton instance of this class
     *
     * @return OperationDefinitionObjectHandler
     */
    public static function get_instance()
    {
        if (!self::$INSTANCE) {
            self::$INSTANCE = new Operation_Definition_Object_Handler();
            self::$INSTANCE->initialize();
        }
        return self::$INSTANCE;
    }
    /**
     * Return an <operation> by the "name" attribute
     *
     * @param string $name
     * @return OperationDefinitionObject
     */
    public function get_object($name)
    {
        return $this->get_all_objects()[$name];
    }
    /**
     * Return all <operation>
     *
     * @return OperationDefinitionObject[]
     */
    public function get_all_objects()
    {
        return $this->operation_definition_objects;
    }
    /**
     * Return an <operation> by operation and type. Eg. "create" and "address"
     *
     * @return OperationDefinitionObject
     */
    public function get_operation_definition(string $operation, string $data_type)
    {
        return $this->get_object($operation . $data_type);
    }
    /**
     * Read metadata xml via the Magento parser and then convert the primitive array output
     * into an array of objects.
     *
     * @throws \Exception
     * @SuppressWarnings(PHPMD)
     */
    private function initialize(): void
    {
        $object_manager = Object_Manager_Factory::get_object_manager();
        $parser = $object_manager->create(Operation_Definition_Parser::class);
        $parser_output = $parser->read_operation_metadata()[Operation_Definition_Object_Handler::ENTITY_OPERATION_ROOT_TAG];
        $operation_name_validator = new Name_Validation_Util();
        foreach ($parser_output as $data_def_name => $op_def_array) {
            $operation_name_validator->validate_pascal_case($data_def_name, Name_Validation_Util::METADATA_OPERATION_NAME);
            $operation = $op_def_array[Operation_Definition_Object_Handler::ENTITY_OPERATION_TYPE];
            $data_type = $op_def_array[Operation_Definition_Object_Handler::ENTITY_OPERATION_DATA_TYPE];
            $url = $op_def_array[Operation_Definition_Object_Handler::ENTITY_OPERATION_URL] ?? null;
            $method = $op_def_array[Operation_Definition_Object_Handler::ENTITY_OPERATION_METHOD] ?? null;
            $auth = $op_def_array[Operation_Definition_Object_Handler::ENTITY_OPERATION_AUTH] ?? null;
            $success_regex = $op_def_array[Operation_Definition_Object_Handler::ENTITY_OPERATION_SUCCESS_REGEX] ?? null;
            $return_regex = $op_def_array[Operation_Definition_Object_Handler::ENTITY_OPERATION_RETURN_REGEX] ?? null;
            $deprecated = $op_def_array[Object_Handler_Interface::OBJ_DEPRECATED] ?? null;
            $return_index = $op_def_array[Operation_Definition_Object_Handler::ENTITY_OPERATION_RETURN_INDEX] ?? 0;
            $content_type = $op_def_array[Operation_Definition_Object_Handler::ENTITY_OPERATION_CONTENT_TYPE][0]['value'] ?? null;
            $headers = $this->initialize_headers($op_def_array);
            $params = $this->initialize_params($op_def_array);
            $operation_elements = [];
            $remove_backend = $op_def_array[Operation_Definition_Object_Handler::ENTITY_OPERATION_BACKEND_REMOVE] ?? false;
            // extract relevant OperationObjects as OperationElements
            if (array_key_exists(Operation_Definition_Object_Handler::ENTITY_OPERATION_OBJECT, $op_def_array)) {
                foreach ($op_def_array[Operation_Definition_Object_Handler::ENTITY_OPERATION_OBJECT] as $op_element_array) {
                    $operation_elements[] = $this->operation_element_extractor->extract_operation_element($op_element_array);
                }
            }
            //handle loose operation fields
            if (array_key_exists(Operation_Definition_Object_Handler::ENTITY_OPERATION_ENTRY, $op_def_array)) {
                foreach ($op_def_array[Operation_Definition_Object_Handler::ENTITY_OPERATION_ENTRY] as $operation_field) {
                    $operation_elements[] = new Operation_Element($operation_field[Operation_Definition_Object_Handler::ENTITY_OPERATION_ENTRY_KEY], $operation_field[Operation_Definition_Object_Handler::ENTITY_OPERATION_ENTRY_VALUE], Operation_Definition_Object_Handler::ENTITY_OPERATION_ENTRY, $operation_field[Operation_Definition_Object_Handler::ENTITY_OPERATION_REQUIRED] ?? null);
                }
            }
            // handle loose json arrays
            if (array_key_exists(Operation_Definition_Object_Handler::ENTITY_OPERATION_ARRAY, $op_def_array)) {
                foreach ($op_def_array[Operation_Definition_Object_Handler::ENTITY_OPERATION_ARRAY] as $operation_field) {
                    $sub_operation_elements = [];
                    $value = null;
                    $type = null;
                    if (array_key_exists(Operation_Definition_Object_Handler::ENTITY_OPERATION_OBJECT, $operation_field)) {
                        $nested_data_element = $this->operation_element_extractor->extract_operation_element($operation_field[Operation_Definition_Object_Handler::ENTITY_OPERATION_OBJECT][0]);
                        $sub_operation_elements[$nested_data_element->get_key()] = $nested_data_element;
                        $value = $nested_data_element->get_value();
                        $type = $nested_data_element->get_key();
                    } else {
                        $value = $operation_field[Operation_Definition_Object_Handler::ENTITY_OPERATION_ARRAY_VALUE][0][Operation_Definition_Object_Handler::ENTITY_OPERATION_ARRAY_VALUE];
                        $type = Operation_Definition_Object_Handler::ENTITY_OPERATION_ARRAY;
                    }
                    $operation_elements[] = new Operation_Element($operation_field[Operation_Definition_Object_Handler::ENTITY_OPERATION_ARRAY_KEY], $value, $type, $operation_field[Operation_Definition_Object_Handler::ENTITY_OPERATION_REQUIRED] ?? null, $sub_operation_elements);
                }
            }
            if ($deprecated !== null) {
                Logging_Util::get_instance()->get_logger(self::class)->deprecation($message = "The operation {$data_def_name} is deprecated.", ['operationType' => $operation, 'deprecatedMessage' => $deprecated]);
            }
            $this->operation_definition_objects[$operation . $data_type] = new Operation_Definition_Object($data_def_name, $operation, $data_type, $method, $url, $auth, $headers, $params, $operation_elements, $content_type, $remove_backend, $success_regex, $return_regex, $return_index, $deprecated);
        }
        $operation_name_validator->summarize(Name_Validation_Util::METADATA_OPERATION_NAME);
    }
    /**
     * Convert headers metadata into an array of objects for further use in.
     */
    private function initialize_headers(array $op_def_array): array
    {
        $headers = [];
        if (array_key_exists(Operation_Definition_Object_Handler::ENTITY_OPERATION_HEADER, $op_def_array)) {
            foreach ($op_def_array[Operation_Definition_Object_Handler::ENTITY_OPERATION_HEADER] as $header_entry) {
                if (isset($header_entry[Operation_Definition_Object_Handler::ENTITY_OPERATION_HEADER_VALUE]) && $header_entry[Operation_Definition_Object_Handler::ENTITY_OPERATION_HEADER_VALUE] !== 'none') {
                    $headers[] = $header_entry[Operation_Definition_Object_Handler::ENTITY_OPERATION_HEADER_PARAM] . ': ' . $header_entry[Operation_Definition_Object_Handler::ENTITY_OPERATION_HEADER_VALUE];
                }
            }
        }
        return $headers;
    }
    /**
     * Convert params metadata into an array of objects.
     */
    private function initialize_params(array $op_def_array): array
    {
        $params = [];
        if (array_key_exists(Operation_Definition_Object_Handler::ENTITY_OPERATION_URL_PARAM, $op_def_array)) {
            foreach ($op_def_array[Operation_Definition_Object_Handler::ENTITY_OPERATION_URL_PARAM] as $param_entry) {
                $params[$param_entry[Operation_Definition_Object_Handler::ENTITY_OPERATION_URL_PARAM_KEY]] = $param_entry[Operation_Definition_Object_Handler::ENTITY_OPERATION_URL_PARAM_VALUE];
            }
        }
        return $params;
    }
}