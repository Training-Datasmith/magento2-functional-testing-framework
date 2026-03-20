<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Page\Objects;

use Magento\Functional_Testing_Framework\Exceptions\Xml_Exception;
use Magento\Functional_Testing_Framework\Page\Handlers\Section_Object_Handler;
/**
 * Class PageObject
 */
class Page_Object
{
    public const ADMIN_AREA = 'admin';
    /**
     * PageObject constructor.
     * @param string      $name
     * @param string      $url
     * @param string      $module
     * @param array $sectionNames
     * @param boolean     $parameterized
     * @param string      $area
     * @param string|null $filename
     * @param string|null $deprecated
     */
    public function __construct(
        /**
         * Page name
         */
        private $name,
        /**
         * Page url
         */
        private $url,
        /**
         * Page module
         */
        private $module,
        /**
         * Array of page section names
         */
        private $section_names,
        /**
         * Page url is parameterized
         */
        private $parameterized,
        /**
         * String identifying the area the page belongs to
         */
        private $area,
        /**
         * Filename of where the page came from
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
     * Getter for the deprecated attr of the section
     *
     * @return string
     */
    public function get_deprecated()
    {
        return $this->deprecated;
    }
    /**
     * Getter for Page Name
     *
     * @return string
     */
    public function get_name()
    {
        return $this->name;
    }
    /**
     * Getter for the Page Filename
     *
     * @return string
     */
    public function get_filename()
    {
        return $this->filename;
    }
    /**
     * Getter for Page URL
     *
     * @return string
     */
    public function get_url()
    {
        return $this->url;
    }
    /**
     * Getter for Page Module
     *
     * @return string
     */
    public function get_module()
    {
        return $this->module;
    }
    /**
     * Getter for Page Area
     *
     * @return string
     */
    public function get_area()
    {
        return $this->area;
    }
    /**
     * Getter for Section Names
     *
     * @return array
     */
    public function get_section_names()
    {
        return $this->section_names;
    }
    /**
     * Checks the section names in the page for existence of the section name passed into the method.
     *
     * @param string $sectionName
     */
    public function has_section($section_name): bool
    {
        return in_array($section_name, $this->section_names);
    }
    /**
     * Given a section name referenced by the page, returns the section object
     *
     * @param string $sectionName
     * @return SectionObject | null
     * @throws XmlException
     */
    public function get_section($section_name)
    {
        if ($this->has_section($section_name)) {
            return Section_Object_Handler::get_instance()->get_object($section_name);
        }
        return null;
    }
    /**
     * Determines if the page's url is parameterized. Based on $parameterized property.
     *
     * @return boolean
     */
    public function is_parameterized()
    {
        return $this->parameterized;
    }
}