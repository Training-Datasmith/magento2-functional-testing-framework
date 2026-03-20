<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager\Config\Mapper;

use Magento\Functional_Testing_Framework\Config\Converter\Dom\Flat as FlatConverter;
use Magento\Functional_Testing_Framework\Config\Dom\Array_Node_Config;
use Magento\Functional_Testing_Framework\Config\Dom\Node_Path_Matcher;
/**
 * Parser of a DI argument node that returns its array representation with no data loss
 */
class Argument_Parser
{
    /**
     * Converter.
     */
    private ?\Magento\Functional_Testing_Framework\Config\Converter\Dom\Flat $converter = null;
    /**
     * Build and return array representation of DI argument node
     *
     * @return array|string
     */
    public function parse(\Dom_Node $argument_node)
    {
        // Base path is specified to use more meaningful XPaths in config
        return $this->get_converter()->convert($argument_node, 'argument');
    }
    /**
     * Retrieve instance of XML converter, suitable for DI argument nodes
     *
     * @return FlatConverter
     */
    protected function get_converter()
    {
        if (!$this->converter) {
            $array_node_config = new Array_Node_Config(new Node_Path_Matcher(), ['argument(/item)+' => 'name']);
            $this->converter = new Flat_Converter($array_node_config);
        }
        return $this->converter;
    }
}