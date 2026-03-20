<?php

declare (strict_types=1);
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Util\Validation;

use Magento\Functional_Testing_Framework\Exceptions\Collector\Exception_Collector;
/**
 * Class DuplicateNodeValidationUtil
 * @package Magento\FunctionalTestingFramework\Util\Validation
 */
class Duplicate_Node_Validation_Util
{
    /**
     * DuplicateNodeValidationUtil constructor.
     * @param string             $uniqueKey
     * @param ExceptionCollector $exceptionCollector
     */
    public function __construct(
        /**
         * Key to use as unique identifier in validation
         */
        private $unique_key,
        /**
         * ExceptionColletor used to catch errors.
         */
        private $exception_collector
    )
    {
    }
    /**
     * Parses through parent's children to find and flag duplicate values in given uniqueKey.
     *
     * @param string      $filename
     */
    public function validate_child_uniqueness(\Dom_Element $parent_node, $filename, $parent_key): void
    {
        $child_nodes = $parent_node->child_nodes;
        $type = ucfirst($parent_node->tag_name);
        $key_values = [];
        for ($i = 0; $i < $child_nodes->length; $i++) {
            $current_node = $child_nodes->item($i);
            if (!is_a($current_node, \Dom_Element::class)) {
                continue;
            }
            if ($current_node->has_attribute($this->unique_key)) {
                $key_values[] = $current_node->get_attribute($this->unique_key);
            }
        }
        $without_duplicates = array_unique($key_values);
        if (count($without_duplicates) !== count($key_values)) {
            $duplicates = array_diff_assoc($key_values, $without_duplicates);
            $key_error = '';
            foreach ($duplicates as $duplicate_value) {
                $key_error .= "\t{$this->unique_key}: {$duplicate_value} is used more than once.";
                if ($parent_key !== null) {
                    $key_error .= " (Parent: {$parent_key})";
                }
                $key_error .= "\n";
            }
            $error_msg = "{$type} cannot use {$this->unique_key}s more than once.\t\n{$key_error}\tin file: {$filename}";
            $this->exception_collector->add_error($filename, $error_msg);
        }
    }
}