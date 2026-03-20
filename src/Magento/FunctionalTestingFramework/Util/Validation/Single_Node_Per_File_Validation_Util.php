<?php

declare (strict_types=1);
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Validation;

use Magento\Functional_Testing_Framework\Exceptions\Collector\Exception_Collector;
/**
 * Class SingleNodePerDocumentValidationUtil
 * @package Magento\FunctionalTestingFramework\Util\Validation
 */
class Single_Node_Per_File_Validation_Util
{
    /**
     * SingleNodePerDocumentValidationUtil constructor
     *
     * @param ExceptionCollector $exceptionCollector
     */
    public function __construct(
        /**
         * ExceptionColletor used to catch errors
         */
        private $exception_collector
    )
    {
    }
    /**
     * Validate single node per dom document for a given tag name
     *
     * @param \DOMDocument $dom
     * @param string       $tag
     * @param string       $filename
     */
    public function validate_single_node_for_tag($dom, $tag, $filename = ''): void
    {
        $tag_nodes = $dom->get_elements_by_tag_name($tag);
        $count = $tag_nodes->length;
        if ($count === 1) {
            return;
        }
        $error_msg = "Single <{$tag}> node per xml file. {$count} found in file: {$filename}\n";
        $this->exception_collector->add_error($filename, $error_msg);
    }
}