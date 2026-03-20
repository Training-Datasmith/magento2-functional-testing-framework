<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Suite\Config;

use Magento\Functional_Testing_Framework\Exceptions\Collector\Exception_Collector;
use Magento\Functional_Testing_Framework\Util\Validation\Single_Node_Per_File_Validation_Util;
/**
 * MFTF suite.xml configuration XML DOM utility
 * @package Magento\FunctionalTestingFramework\Suite\Config
 */
class Suite_Dom extends \Magento\Functional_Testing_Framework\Config\Mftf_Dom
{
    public const SUITE_META_FILENAME_ATTRIBUTE = 'filename';
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
        if ($dom->get_elements_by_tag_name('suites')->length > 0) {
            // Validate single suite node per file
            $this->single_node_per_file_validation_util->validate_single_node_for_tag($dom, 'suite', $filename);
            if ($dom->get_elements_by_tag_name('suite')->length > 0) {
                /** @var \DOMElement $suiteNode */
                $suite_node = $dom->get_elements_by_tag_name('suite')[0];
                $suite_node->set_attribute(self::SUITE_META_FILENAME_ATTRIBUTE, $filename);
            }
        }
        return $dom;
    }
}