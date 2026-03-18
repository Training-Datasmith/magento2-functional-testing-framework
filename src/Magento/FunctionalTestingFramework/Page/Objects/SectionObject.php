<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */

namespace Magento\FunctionalTestingFramework\Page\Objects;

/**
 * Class SectionObject
 */
class SectionObject
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
    public function getName()
    {
        return $this->name;
    }

    /**
     * Getter for the deprecated attr of the section
     *
     * @return string
     */
    public function getDeprecated()
    {
        return $this->deprecated;
    }

    /**
     * Getter for the Section Filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }
    
    /**
     * Getter for an array containing all of a section's elements.
     *
     * @return array
     */
    public function getElements()
    {
        return $this->elements;
    }

    /**
     * Checks to see if this section contains any element by the name of elementName
     * @param string $elementName
     */
    public function hasElement($elementName): bool
    {
        return array_key_exists($elementName, $this->elements);
    }

    /**
     * Given the name of an element, returns the element object
     *
     * @param string $elementName
     * @return ElementObject | null
     */
    public function getElement($elementName)
    {
        if ($this->hasElement($elementName)) {
            return $this->elements[$elementName];
        }

        return null;
    }
}
