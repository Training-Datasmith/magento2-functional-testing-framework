<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Page\Handlers;

use Magento\Functional_Testing_Framework\Exceptions\Xml_Exception;
use Magento\Functional_Testing_Framework\Object_Manager\Object_Handler_Interface;
use Magento\Functional_Testing_Framework\Object_Manager_Factory;
use Magento\Functional_Testing_Framework\Page\Objects\Element_Object;
use Magento\Functional_Testing_Framework\Page\Objects\Section_Object;
use Magento\Functional_Testing_Framework\Util\Logger\Logging_Util;
use Magento\Functional_Testing_Framework\Util\Validation\Name_Validation_Util;
use Magento\Functional_Testing_Framework\Xml_Parser\Section_Parser;
class Section_Object_Handler implements Object_Handler_Interface
{
    public const SECTION = 'section';
    public const ELEMENT = 'element';
    public const TYPE = 'type';
    public const SELECTOR = 'selector';
    public const LOCATOR_FUNCTION = 'locatorFunction';
    public const TIMEOUT = 'timeout';
    public const PARAMETERIZED = 'parameterized';
    public const FILENAME = 'filename';
    public const SECTION_NAME_ERROR_MSG = "Section names cannot contain non alphanumeric characters.\tSection='%s'";
    public const ELEMENT_NAME_ERROR_MSG = "Element names cannot contain non alphanumeric characters.\tElement='%s'";
    /**
     * Singleton instance of this class
     */
    private static ?\Magento\Functional_Testing_Framework\Page\Handlers\Section_Object_Handler $INSTANCE = null;
    /**
     * All section objects. Set during initialize().
     *
     * @var SectionObject[]
     */
    private array $section_objects = [];
    /**
     * Constructor
     *
     * @constructor
     * @throws XmlException
     */
    private function __construct()
    {
        $object_manager = Object_Manager_Factory::get_object_manager();
        $parser = $object_manager->get(Section_Parser::class);
        $parser_output = $parser->get_data(self::SECTION);
        if (!$parser_output) {
            return;
        }
        $section_name_validator = new Name_Validation_Util();
        $element_name_validator = new Name_Validation_Util();
        foreach ($parser_output as $section_name => $section_data) {
            $elements = [];
            if (preg_match('/[^a-zA-Z0-9_]/', (string) $section_name)) {
                throw new Xml_Exception(sprintf(self::SECTION_NAME_ERROR_MSG, $section_name));
            }
            $filename = $section_data[self::FILENAME] ?? null;
            $section_name_validator->validate_affixes($section_name, Name_Validation_Util::SECTION, $filename);
            try {
                foreach ($section_data[Section_Object_Handler::ELEMENT] as $element_name => $element_data) {
                    if (preg_match('/[^a-zA-Z0-9_]/', (string) $element_name)) {
                        throw new Xml_Exception(sprintf(self::ELEMENT_NAME_ERROR_MSG, $element_name, $section_name));
                    }
                    $element_name_validator->validate_camel_case($element_name, Name_Validation_Util::SECTION_ELEMENT_NAME, $filename);
                    $element_type = $element_data[Section_Object_Handler::TYPE] ?? null;
                    $element_selector = $element_data[Section_Object_Handler::SELECTOR] ?? null;
                    $element_locator_func = $element_data[Section_Object_Handler::LOCATOR_FUNCTION] ?? null;
                    $element_timeout = $element_data[Section_Object_Handler::TIMEOUT] ?? null;
                    $element_parameterized = $element_data[Section_Object_Handler::PARAMETERIZED] ?? false;
                    $element_deprecated = $element_data[self::OBJ_DEPRECATED] ?? null;
                    if ($element_deprecated !== null) {
                        Logging_Util::get_instance()->get_logger(Element_Object::class)->deprecation("The element '{$element_name}' is deprecated.", ['fileName' => $filename, 'deprecatedMessage' => $element_deprecated]);
                    }
                    $elements[$element_name] = new Element_Object($element_name, $element_type, $element_selector, $element_locator_func, $element_timeout, $element_parameterized, $element_deprecated);
                }
            } catch (Xml_Exception $exception) {
                throw new Xml_Exception($exception->get_message() . " in Section '{$section_name}'");
            }
            $section_deprecated = $section_data[self::OBJ_DEPRECATED] ?? null;
            if ($section_deprecated !== null) {
                Logging_Util::get_instance()->get_logger(Section_Object::class)->deprecation($section_deprecated, ['sectionName' => $filename, 'deprecatedSection' => $section_deprecated]);
            }
            $this->section_objects[$section_name] = new Section_Object($section_name, $elements, $filename, $section_deprecated);
        }
        $section_name_validator->summarize(Name_Validation_Util::SECTION . ' name');
        $element_name_validator->summarize(Name_Validation_Util::SECTION_ELEMENT_NAME);
    }
    /**
     * Initialize and/or return the singleton instance of this class
     *
     * @return SectionObjectHandler
     * @throws XmlException
     */
    public static function get_instance()
    {
        if (!self::$INSTANCE) {
            self::$INSTANCE = new Section_Object_Handler();
        }
        return self::$INSTANCE;
    }
    /**
     * Get a SectionObject by name
     *
     * @param string $name The section name.
     */
    public function get_object($name): ?\Magento\Functional_Testing_Framework\Page\Objects\Section_Object
    {
        if (array_key_exists($name, $this->get_all_objects())) {
            return $this->get_all_objects()[$name];
        }
        return null;
    }
    /**
     * Get all SectionObjects
     *
     * @return SectionObject[]
     */
    public function get_all_objects()
    {
        return $this->section_objects;
    }
}