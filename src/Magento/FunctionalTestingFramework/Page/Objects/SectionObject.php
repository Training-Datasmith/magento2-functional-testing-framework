<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Page\Objects;

/**
 * Class SectionObject
 */
class Section_Object
{
    /**
     * SectionObject constructor.
     * @param string      $name
     * @param array       $elements
     * @param string|null $filename
     * @param string|null $deprecated
     */
    public function __construct(
        /**
         * Section name.
         */
        private $name,
        /**
         * Section elements.
         */
        private $elements,
        /**
         * Filename of where the section came from
         */
        private $filename = null,
        /**
         * Deprecated message.
         */
        private $deprecated = null
    )
    {
    }
    /**
     * Getter for the name of the section
     *
     * @return string
     */
    public function get_name()
    {
        return $this->name;
    }
    /**
     * Getter for the deprecated attr of the section
     *
     * @return string
     */
    public function get_deprecated()
    {
        return $this->deprecated;
    }
    /**
     * Getter for the Section Filename
     *
     * @return string
     */
    public function get_filename()
    {
        return $this->filename;
    }
    /**
     * Getter for an array containing all of a section's elements.
     *
     * @return array
     */
    public function get_elements()
    {
        return $this->elements;
    }
    /**
     * Checks to see if this section contains any element by the name of elementName
     * @param string $elementName
     */
    public function has_element($element_name): bool
    {
        return array_key_exists($element_name, $this->elements);
    }
    /**
     * Given the name of an element, returns the element object
     *
     * @param string $elementName
     * @return ElementObject | null
     */
    public function get_element($element_name)
    {
        if ($this->has_element($element_name)) {
            return $this->elements[$element_name];
        }
        return null;
    }
}