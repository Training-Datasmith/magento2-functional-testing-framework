<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Page\Config;

use Magento\Functional_Testing_Framework\Exceptions\Collector\Exception_Collector;
use Magento\Functional_Testing_Framework\Util\Validation\Duplicate_Node_Validation_Util;
use Magento\Functional_Testing_Framework\Util\Validation\Single_Node_Per_File_Validation_Util;
/**
 * MFTF section.xml configuration XML DOM utility
 * @package Magento\FunctionalTestingFramework\Page\Config
 */
class Section_Dom extends \Magento\Functional_Testing_Framework\Config\Mftf_Dom
{
    public const SECTION_META_FILENAME_ATTRIBUTE = 'filename';
    public const SECTION_META_NAME_ATTRIBUTE = 'name';
    /**
     * NodeValidationUtil
     */
    private readonly \Magento\Functional_Testing_Framework\Util\Validation\Duplicate_Node_Validation_Util $validation_util;
    /** SingleNodePerFileValidationUtil
     */
    private readonly \Magento\Functional_Testing_Framework\Util\Validation\Single_Node_Per_File_Validation_Util $single_node_per_file_validation_util;
    /**
     * Entity Dom constructor.
     * @param string             $xml
     * @param string             $filename
     * @param ExceptionCollector $exceptionCollector
     * @param string             $typeAttributeName
     * @param string             $schemaFile
     * @param string             $errorFormat
     */
    public function __construct($xml, $filename, $exception_collector, array $id_attributes = [], $type_attribute_name = null, $schema_file = null, $error_format = self::ERROR_FORMAT_DEFAULT)
    {
        $this->validation_util = new Duplicate_Node_Validation_Util('name', $exception_collector);
        $this->single_node_per_file_validation_util = new Single_Node_Per_File_Validation_Util($exception_collector);
        parent::__construct($xml, $filename, $exception_collector, $id_attributes, $type_attribute_name, $schema_file, $error_format);
    }
    /**
     * Takes a dom element from xml and appends the filename based on location
     *
     * @param string      $xml
     * @param string|null $filename
     * @return \DOMDocument
     */
    public function init_dom($xml, $filename = null)
    {
        $dom = parent::init_dom($xml, $filename);
        if ($dom->get_elements_by_tag_name('sections')->length > 0) {
            // Validate single section node per file
            $this->single_node_per_file_validation_util->validate_single_node_for_tag($dom, 'section', $filename);
            if ($dom->get_elements_by_tag_name('section')->length > 0) {
                /** @var \DOMElement $sectionNode */
                $section_node = $dom->get_elements_by_tag_name('section')[0];
                $section_node->set_attribute(self::SECTION_META_FILENAME_ATTRIBUTE, $filename);
                $this->validation_util->validate_child_uniqueness($section_node, $filename, $section_node->get_attribute(self::SECTION_META_NAME_ATTRIBUTE));
            }
        }
        return $dom;
    }
}