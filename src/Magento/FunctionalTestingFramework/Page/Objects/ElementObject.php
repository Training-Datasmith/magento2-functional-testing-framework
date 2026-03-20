<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Page\Objects;

use Magento\Functional_Testing_Framework\Exceptions\Xml_Exception;
/**
 * Class ElementObject
 */
class Element_Object
{
    public const DEFAULT_TIMEOUT_SYMBOL = '-';
    /**
     * Section element name
     *
     * @var string
     */
    private $name;
    /**
     * Section element locator
     *
     * @var string
     */
    private $selector;
    /**
     * Section element locatorFunction
     */
    private string $locator_function;
    /**
     * ElementObject constructor.
     * @param string  $name
     * @param string  $type
     * @param string  $selector
     * @param string  $timeout
     * @param boolean $parameterized
     * @throws XmlException
     * @param string $deprecated
     */
    public function __construct(
        $name,
        /**
         * Section element type
         */
        private $type,
        $selector,
        string $locator_function,
        /**
         * Section element timeout
         */
        private $timeout,
        /**
         * Section element locator is parameterized
         */
        private $parameterized,
        /**
         * Deprecated message.
         */
        private $deprecated = null
    )
    {
        if ($selector !== null && $locator_function !== null) {
            throw new Xml_Exception("Element '{$name}' cannot have both a selector and a locatorFunction.");
        }
        if ($selector === null && $locator_function === null) {
            throw new Xml_Exception("Element '{$name}' must have either a selector or a locatorFunction.'");
        }
        $this->name = $name;
        $this->selector = $selector;
        $this->locator_function = $locator_function;
        if (!str_contains($locator_function, 'Locator::')) {
            $this->locator_function = 'Locator::' . $locator_function;
        }
    }
    /**
     * Getter for the deprecated attr
     *
     * @return string
     */
    public function get_deprecated()
    {
        return $this->deprecated;
    }
    /**
     * Getter for the name of the element
     *
     * @return string
     */
    public function get_name()
    {
        return $this->name;
    }
    /**
     * Getter for the name of the element type
     *
     * @return string
     */
    public function get_type()
    {
        return $this->type;
    }
    /**
     * Getter for the selector of an element
     *
     * @return string
     */
    public function get_selector()
    {
        return $this->selector;
    }
    /**
     * Getter for the locatorFunction of an element
     *
     * @return string
     */
    public function get_locator_function()
    {
        return $this->locator_function;
    }
    /**
     * Returns selector if not null, otherwise returns locatorFunction
     *
     * @return string
     */
    public function get_prioritized_selector()
    {
        return $this->selector ?: $this->locator_function;
    }
    /**
     * Returns an integer representing an element's timeout
     */
    public function get_timeout(): ?int
    {
        if ($this->timeout === Element_Object::DEFAULT_TIMEOUT_SYMBOL) {
            return null;
        }
        return (int) $this->timeout;
    }
    /**
     * Determines if the element's selector is parameterized. Based on $parameterized property.
     *
     * @return boolean
     */
    public function is_parameterized()
    {
        return $this->parameterized;
    }
}