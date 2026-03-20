<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Data_Generator\Config;

use Magento\Functional_Testing_Framework\Exceptions\Collector\Exception_Collector;
use Magento\Functional_Testing_Framework\Util\Validation\Duplicate_Node_Validation_Util;
/**
 * MFTF metadata.xml configuration XML DOM utility
 * @package Magento\FunctionalTestingFramework\DataGenerator\Config
 */
class Operation_Dom extends \Magento\Functional_Testing_Framework\Config\Mftf_Dom
{
    public const METADATA_FILE_NAME_ENDING = 'meta';
    public const METADATA_META_FILENAME_ATTRIBUTE = 'filename';
    public const METADATA_META_NAME_ATTRIBUTE = 'name';
    /**
     * NodeValidationUtil
     */
    private readonly \Magento\Functional_Testing_Framework\Util\Validation\Duplicate_Node_Validation_Util $validation_util;
    /**
     * Metadata Dom constructor.
     * @param string             $xml
     * @param string             $filename
     * @param ExceptionCollector $exceptionCollector
     * @param string             $typeAttributeName
     * @param string             $schemaFile
     * @param string             $errorFormat
     */
    public function __construct($xml, $filename, $exception_collector, array $id_attributes = [], $type_attribute_name = null, $schema_file = null, $error_format = self::ERROR_FORMAT_DEFAULT)
    {
        $this->validation_util = new Duplicate_Node_Validation_Util('key', $exception_collector);
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
        if (strpos((string) $filename, self::METADATA_FILE_NAME_ENDING)) {
            $operation_nodes = $dom->get_elements_by_tag_name('operation');
            foreach ($operation_nodes as $operation_node) {
                /** @var \DOMElement $operationNode */
                $operation_node->set_attribute(self::METADATA_META_FILENAME_ATTRIBUTE, $filename);
                $this->validate_operation_elements($operation_node, $filename, $operation_node->get_attribute(self::METADATA_META_NAME_ATTRIBUTE));
            }
        }
        return $dom;
    }
    /**
     * Recurse through child elements and validate uniqueKeys
     * @param string      $filename
     * @param string      $topParent
     */
    public function validate_operation_elements(\Dom_Element $parent_node, $filename, $top_parent): void
    {
        $this->validation_util->validate_child_uniqueness($parent_node, $filename, $top_parent);
        $child_nodes = $parent_node->child_nodes;
        for ($i = 0; $i < $child_nodes->length; $i++) {
            $current_node = $child_nodes->item($i);
            if (!is_a($current_node, \Dom_Element::class)) {
                continue;
            }
            $this->validate_operation_elements($current_node, $filename, $top_parent);
        }
    }
}