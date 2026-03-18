<?php

declare(strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\DataGenerator\Objects;

class OperationElement
{
    /**
     * Required attribute, used to determine if values need to be cast before insertion.
     */
    private readonly bool $required;

    /**
     * OperationElement constructor.
     * @param string     $key
     * @param string     $value
     * @param string     $type
     * @param boolean    $required
     * @param array      $nestedElements
     * @param null|array $nestedMetadata
     */
    public function __construct(/**
     * Data parameter name
     */
        private $key, /**
     * Data parameter metadata value (e.g. string, bool)
     */
        private $value, /**
     * Data type such as array or entry
     */
        private $type,
        $required, /**
     * Nested data Objects defined within the same operation.xml file
     */
        private $nestedElements = [], /**
     * Nested Metadata which must be included for a dataElement of type dataObject
     */
        private $nestedMetadata = null
    ) {
        $this->required = filter_var($required, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Getter for data parameter name
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Getter for parameter metadata value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Getter for parameter value type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Accessor for required attribute
     *
     * @return boolean
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * Returns the nested data element based on the type of entity passed
     *
     * @param string $type
     * @return array
     */
    public function getNestedOperationElement($type)
    {
        if (array_key_exists($type, $this->nestedElements)) {
            return $this->nestedElements[$type];
        }

        return [];
    }

    /**
     * Returns relevant nested data metadata for a data element which is a data object
     *
     * @return array|null
     */
    public function getNestedMetadata()
    {
        return $this->nestedMetadata;
    }
}
