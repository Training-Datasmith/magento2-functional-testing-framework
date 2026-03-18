<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\DataGenerator\Util;

use Magento\FunctionalTestingFramework\DataGenerator\Handlers\OperationDefinitionObjectHandler;
use Magento\FunctionalTestingFramework\DataGenerator\Objects\OperationElement;

class OperationElementExtractor
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
    public function extractOperationElement(array $operationElementArray): \Magento\FunctionalTestingFramework\DataGenerator\Objects\OperationElement
    {
        // extract key
        $operationDefKey = $operationElementArray[OperationElementExtractor::OPERATION_OBJECT_KEY];

        // extract dataType
        $dataType = $operationElementArray[OperationElementExtractor::OPERATION_OBJECT_DATA_TYPE];

        $operationElements = [];
        $nestedOperationElements = [];

        // extract nested entries
        if (array_key_exists(OperationElementExtractor::OPERATION_OBJECT_ENTRY, $operationElementArray)) {
            $this->extractOperationField(
                $operationElements,
                $operationElementArray[OperationElementExtractor::OPERATION_OBJECT_ENTRY]
            );
        }

        // extract nested arrays
        if (array_key_exists(OperationElementExtractor::OPERATION_OBJECT_ARRAY, $operationElementArray)) {
            $this->extractOperationArray(
                $operationElements,
                $operationElementArray[OperationElementExtractor::OPERATION_OBJECT_ARRAY]
            );
        }

        // extract nested
        if (array_key_exists(OperationElementExtractor::OPERATION_OBJECT_OBJ_NAME, $operationElementArray)) {
            foreach ($operationElementArray[OperationElementExtractor::OPERATION_OBJECT_OBJ_NAME] as $operationObject) {
                $nestedOperationElement = $this->extractOperationElement($operationObject);
                $operationElements[] = $nestedOperationElement;
            }
        }

        // a dataObject specified in xml must contain corresponding metadata for the object
        if (empty($operationElements)) {
            throw new \Exception('must specify dataObject metadata if declaration is used');
        }

        return new OperationElement(
            $operationDefKey,
            $dataType,
            OperationElementExtractor::OPERATION_OBJECT_OBJ_NAME,
            $operationElementArray[OperationDefinitionObjectHandler::ENTITY_OPERATION_REQUIRED] ?? null,
            $nestedOperationElements,
            $operationElements
        );
    }

    /**
     * Creates and Adds relevant DataElements from data entries defined within dataObject array
     *
     * @param array $operationFieldArray
     */
    private function extractOperationField(array &$operationElements, $operationFieldArray): void
    {
        foreach ($operationFieldArray as $operationFieldType) {
            $operationElements[] = new OperationElement(
                $operationFieldType[OperationDefinitionObjectHandler::ENTITY_OPERATION_ENTRY_KEY],
                $operationFieldType[OperationDefinitionObjectHandler::ENTITY_OPERATION_ENTRY_VALUE],
                OperationDefinitionObjectHandler::ENTITY_OPERATION_ENTRY,
                $operationFieldType[OperationDefinitionObjectHandler::ENTITY_OPERATION_REQUIRED] ?? null
            );
        }
    }

    /**
     * Creates and Adds relevant DataElements from data arrays defined within dataObject array
     *
     * @param array $operationArrayArray
     */
    private function extractOperationArray(array &$operationArrayData, $operationArrayArray): void
    {
        foreach ($operationArrayArray as $operationFieldType) {
            $operationElementValue = [];
            $entityValueKey = OperationDefinitionObjectHandler::ENTITY_OPERATION_ARRAY_VALUE;
            if (isset($operationFieldType[$entityValueKey])) {
                foreach ($operationFieldType[$entityValueKey] as $operationFieldValue) {
                    $operationElementValue[] =
                        $operationFieldValue[OperationElementExtractor::OPERATION_OBJECT_ARRAY_VALUE] ?? null;
                }
            }

            if (count($operationElementValue) === 1) {
                $operationElementValue = array_pop($operationElementValue);
            }

            $nestedOperationElements = [];
            if (array_key_exists(OperationElementExtractor::OPERATION_OBJECT_OBJ_NAME, $operationFieldType)) {
                //add the key to reference this object later
                $operationObjectKeyedArray = $operationFieldType
                    [OperationElementExtractor::OPERATION_OBJECT_OBJ_NAME][0];
                $operationObjectKeyedArray[OperationElementExtractor::OPERATION_OBJECT_KEY] =
                    $operationFieldType[OperationDefinitionObjectHandler::ENTITY_OPERATION_ARRAY_KEY];
                $operationElement = $this->extractOperationElement($operationObjectKeyedArray);
                $operationElementValue = $operationElement->getValue();
                $nestedOperationElements[$operationElement->getValue()] = $operationElement;
            }
            $operationArrayData[] = new OperationElement(
                $operationFieldType[OperationDefinitionObjectHandler::ENTITY_OPERATION_ARRAY_KEY],
                $operationElementValue,
                OperationDefinitionObjectHandler::ENTITY_OPERATION_ARRAY,
                $operationFieldType[OperationDefinitionObjectHandler::ENTITY_OPERATION_REQUIRED] ?? null,
                $nestedOperationElements
            );
        }
    }
}
