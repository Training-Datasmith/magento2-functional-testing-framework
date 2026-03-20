<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Page\Config;

use Magento\Functional_Testing_Framework\Config\Mftf_Application_Config;
use Magento\Functional_Testing_Framework\Exceptions\Collector\Exception_Collector;
use Magento\Functional_Testing_Framework\Util\Module_Path_Extractor;
use Magento\Functional_Testing_Framework\Util\Validation\Duplicate_Node_Validation_Util;
use Magento\Functional_Testing_Framework\Util\Validation\Single_Node_Per_File_Validation_Util;
/**
 * MFTF page.xml configuration XML DOM utility
 * @package Magento\FunctionalTestingFramework\Page\Config
 */
class Dom extends \Magento\Functional_Testing_Framework\Config\Mftf_Dom
{
    public const PAGE_META_FILENAME_ATTRIBUTE = 'filename';
    public const PAGE_META_NAME_ATTRIBUTE = 'name';
    /**
     * Module Path extractor
     */
    private readonly \Magento\Functional_Testing_Framework\Util\Module_Path_Extractor $module_path_extractor;
    /**
     * NodeValidationUtil
     */
    private readonly \Magento\Functional_Testing_Framework\Util\Validation\Duplicate_Node_Validation_Util $validation_util;
    /** SingleNodePerFileValidationUtil
     */
    private readonly \Magento\Functional_Testing_Framework\Util\Validation\Single_Node_Per_File_Validation_Util $single_node_per_file_validation_util;
    /**
     * Page Dom constructor.
     * @param string             $xml
     * @param string             $filename
     * @param ExceptionCollector $exceptionCollector
     * @param string             $typeAttributeName
     * @param string             $schemaFile
     * @param string             $errorFormat
     */
    public function __construct($xml, $filename, $exception_collector, array $id_attributes = [], $type_attribute_name = null, $schema_file = null, $error_format = self::ERROR_FORMAT_DEFAULT)
    {
        $this->module_path_extractor = new Module_Path_Extractor();
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
        if ($dom->get_elements_by_tag_name('pages')->length > 0) {
            /** @var \DOMElement $pagesNode */
            $pages_node = $dom->get_elements_by_tag_name('pages')[0];
            $this->validation_util->validate_child_uniqueness($pages_node, $filename, $pages_node->get_attribute(self::PAGE_META_NAME_ATTRIBUTE));
            // Validate single page node per file
            $this->single_node_per_file_validation_util->validate_single_node_for_tag($dom, 'page', $filename);
            if ($dom->get_elements_by_tag_name('page')->length > 0) {
                /** @var \DOMElement $pageNode */
                $page_node = $dom->get_elements_by_tag_name('page')[0];
                $current_module = $this->module_path_extractor->get_extension_path($filename) . '_' . $this->module_path_extractor->extract_module_name($filename);
                $page_module = $page_node->get_attribute('module');
                $page_name = $page_node->get_attribute('name');
                if ($page_module !== $current_module) {
                    if (Mftf_Application_Config::get_config()->verbose_enabled()) {
                        print 'Page Module does not match path Module. ' . "(Page, Module): ({$page_name}, {$page_module}) - Path Module: {$current_module}" . PHP_EOL;
                    }
                }
                $page_node->set_attribute(self::PAGE_META_FILENAME_ATTRIBUTE, $filename);
            }
        }
        return $dom;
    }
}