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
 * MFTF actionGroup.xml configuration XML DOM utility
 * @package Magento\FunctionalTestingFramework\DataGenerator\Config
 */
class Dom extends \Magento\Functional_Testing_Framework\Config\Mftf_Dom
{
    public const DATA_FILE_NAME_ENDING = 'Data';
    public const DATA_META_FILENAME_ATTRIBUTE = 'filename';
    public const DATA_META_NAME_ATTRIBUTE = 'name';
    /**
     * NodeValidationUtil
     */
    private readonly \Magento\Functional_Testing_Framework\Util\Validation\Duplicate_Node_Validation_Util $validation_util;
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
        if (strpos((string) $filename, self::DATA_FILE_NAME_ENDING)) {
            $entity_nodes = $dom->get_elements_by_tag_name('entity');
            foreach ($entity_nodes as $entity_node) {
                /** @var \DOMElement $entityNode */
                $entity_node->set_attribute(self::DATA_META_FILENAME_ATTRIBUTE, $filename);
                $this->validation_util->validate_child_uniqueness($entity_node, $filename, $entity_node->get_attribute(self::DATA_META_NAME_ATTRIBUTE));
            }
        }
        $item_nodes = $dom->get_elements_by_tag_name('item');
        /** @var \DOMElement $itemNode */
        foreach ($item_nodes as $item_key => $item_node) {
            if ($item_node->has_attribute('name') === false) {
                $item_node->set_attribute('name', (string) $item_key);
            }
        }
        return $dom;
    }
}