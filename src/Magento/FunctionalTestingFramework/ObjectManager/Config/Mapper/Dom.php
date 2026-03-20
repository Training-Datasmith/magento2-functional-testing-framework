<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Object_Manager\Config\Mapper;

use Magento\Functional_Testing_Framework\Data\Argument\Interpreter_Interface;
use Magento\Functional_Testing_Framework\Stdlib\Boolean_Utils;
// @codingStandardsIgnoreFile
class Dom implements \Magento\Functional_Testing_Framework\Config\Converter_Interface
{
    private readonly \Magento\Functional_Testing_Framework\Stdlib\Boolean_Utils $boolean_utils;
    private readonly \Magento\Functional_Testing_Framework\Object_Manager\Config\Mapper\Argument_Parser $argument_parser;
    /**
     * @param BooleanUtils $booleanUtils
     * @param ArgumentParser $argumentParser
     */
    public function __construct(private readonly Interpreter_Interface $argument_interpreter, ?Boolean_Utils $boolean_utils = null, ?Argument_Parser $argument_parser = null)
    {
        $this->boolean_utils = $boolean_utils ?: new Boolean_Utils();
        $this->argument_parser = $argument_parser ?: new Argument_Parser();
    }
    /**
     * Convert configuration in DOM format to assoc array that can be used by object manager
     *
     * @param \DOMDocument $config
     * @throws \Exception
     * @todo this method has high cyclomatic complexity in order to avoid performance issues
     */
    public function convert($config): array
    {
        $output = [];
        /** @var \DOMNode $node */
        foreach ($config->document_element->child_nodes as $node) {
            if ($node->node_type !== XML_ELEMENT_NODE) {
                continue;
            }
            switch ($node->node_name) {
                case 'preference':
                    $output['preferences'][$node->attributes->get_named_item('for')->node_value] = $node->attributes->get_named_item('type')->node_value;
                    break;
                case 'type':
                case 'virtualType':
                    $type_data = [];
                    $type_node_attributes = $node->attributes;
                    $type_node_shared = $type_node_attributes->get_named_item('shared');
                    if ($type_node_shared) {
                        $type_data['shared'] = $this->boolean_utils->to_boolean($type_node_shared->node_value);
                    }
                    if ($node->node_name === 'virtualType') {
                        $attribute_type = $type_node_attributes->get_named_item('type');
                        // attribute type is required for virtual type only in merged configuration
                        if ($attribute_type) {
                            $type_data['type'] = $attribute_type->node_value;
                        }
                    }
                    $type_data['arguments'] = $this->set_type_arguments($node);
                    $output[$type_node_attributes->get_named_item('name')->node_value] = $type_data;
                    break;
                default:
                    throw new \Exception("Invalid application config. Unknown node: {$node->node_name}.");
            }
        }
        return $output;
    }
    /** Read typeChildNodes and set typeArguments
     * @param DOMNode $node
     * @return mixed[]
     * @throws \Exception
     */
    private function set_type_arguments($node): array
    {
        $type_arguments = [];
        foreach ($node->child_nodes as $type_child_node) {
            /** @var \DOMNode $typeChildNode */
            if ($type_child_node->node_type !== XML_ELEMENT_NODE) {
                continue;
            }
            switch ($type_child_node->node_name) {
                case 'arguments':
                    /** @var \DOMNode $argumentNode */
                    foreach ($type_child_node->child_nodes as $argument_node) {
                        if ($argument_node->node_type !== XML_ELEMENT_NODE) {
                            continue;
                        }
                        $argument_name = $argument_node->attributes->get_named_item('name')->node_value;
                        $argument_data = $this->argument_parser->parse($argument_node);
                        $type_arguments[$argument_name] = $this->argument_interpreter->evaluate($argument_data);
                    }
                    break;
                default:
                    throw new \Exception("Invalid application config. Unknown node: {$type_child_node->node_name}.");
            }
        }
        return $type_arguments;
    }
}