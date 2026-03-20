<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config;

use Magento\Functional_Testing_Framework\Config\Dom\Node_Merging_Config;
use Magento\Functional_Testing_Framework\Config\Dom\Node_Path_Matcher;
use Magento\Functional_Testing_Framework\Exceptions\Collector\Exception_Collector;
/**
 * Class MftfDom
 * @package Magento\FunctionalTestingFramework\Config
 */
class Mftf_Dom extends \Magento\Functional_Testing_Framework\Config\Dom
{
    /**
     * MftfDom constructor.
     * @param string             $xml
     * @param string             $filename
     * @param ExceptionCollector $exceptionCollector
     * @param string             $typeAttributeName
     * @param string             $schemaFile
     * @param string             $errorFormat
     */
    public function __construct($xml, $filename, $exception_collector, array $id_attributes = [], $type_attribute_name = null, $schema_file = null, $error_format = self::ERROR_FORMAT_DEFAULT)
    {
        $this->schema_file = $schema_file;
        $this->node_merging_config = new Node_Merging_Config(new Node_Path_Matcher(), $id_attributes);
        $this->type_attribute_name = $type_attribute_name;
        $this->error_format = $error_format;
        $this->dom = $this->init_dom($xml, $filename);
        $this->root_namespace = $this->dom->lookup_namespace_uri($this->dom->namespace_uri);
    }
    /**
     * Redirects any merges into the init method for appending xml filename
     *
     * @param string             $xml
     * @param string|null        $filename
     * @param ExceptionCollector $exceptionCollector
     */
    public function merge($xml, $filename = null, $exception_collector = null): void
    {
        $dom = $this->init_dom($xml, $filename);
        $this->merge_node($dom->document_element, '');
    }
    /**
     * Checks if the filename given ends with the correct suffix.
     * @param string $filename
     * @param string $suffix
     */
    public function check_filename_suffix($filename, $suffix): bool
    {
        if (str_ends_with($filename, $suffix)) {
            return true;
        }
        return false;
    }
}