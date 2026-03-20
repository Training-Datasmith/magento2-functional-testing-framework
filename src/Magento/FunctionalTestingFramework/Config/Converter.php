<?php

declare (strict_types=1);
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
namespace Magento\Functional_Testing_Framework\Config;

/**
 * Converter for configuration data.
 */
class Converter implements \Magento\Functional_Testing_Framework\Config\Converter_Interface
{
    /**
     * Unique identifier of node.
     */
    public const NAME_ATTRIBUTE = 'name';
    /**
     * Constructor for Converter object.
     *
     * @param string               $argumentNodeName
     */
    public function __construct(
        /**
         * Argument parser.
         */
        protected \Magento\Functional_Testing_Framework\Object_Manager\Config\Mapper\Argument_Parser $argument_parser,
        /**
         * Argument interpreter.
         */
        protected \Magento\Functional_Testing_Framework\Data\Argument\Interpreter_Interface $argument_interpreter,
        /**
         * Argument node name.
         */
        protected $argument_node_name,
        /**
         * Id attributes.
         *
         * @var string[]
         */
        protected array $id_attributes = []
    )
    {
    }
    /**
     * Convert XML to array.
     *
     * @param \DOMDocument $source
     * @return array
     */
    public function convert($source)
    {
        return $this->convert_xml($source->document_element->child_nodes);
    }
    /**
     * Convert XML node to array or string recursive.
     *
     * @param \DOMNodeList|array $elements
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @TODO ported magento code - to be refactored later
     */
    protected function convert_xml($elements): array
    {
        $result = [];
        foreach ($elements as $element) {
            if ($element instanceof \Dom_Element) {
                if ($element->get_attribute('remove') === 'true') {
                    // Remove element
                    continue;
                }
                if ($element->has_attribute('xsi:type')) {
                    if ($element->has_attribute('path')) {
                        $element_data = $this->get_attributes($element);
                        $element_data['value'] = $this->argument_interpreter->evaluate($this->argument_parser->parse($element));
                        unset($element_data['xsi:type'], $element_data['item']);
                    } else {
                        $element_data = $this->argument_interpreter->evaluate($this->argument_parser->parse($element));
                    }
                } else {
                    $element_data = array_merge($this->get_attributes($element), $this->get_child_nodes($element));
                }
                $key = $this->get_element_key($element);
                if ($key) {
                    $result[$element->node_name][$key] = $element_data;
                } elseif (!empty($element_data)) {
                    $result[$element->node_name][] = $element_data;
                }
            } elseif ($element->node_type === XML_TEXT_NODE && trim((string) $element->node_value) !== '') {
                return ['value' => $element->node_value];
            }
        }
        return $result;
    }
    /**
     * Get key for DOM element
     */
    protected function get_element_key(\Dom_Element $element): string|false
    {
        if (isset($this->id_attributes[$element->node_name])) {
            if ($element->has_attribute($this->id_attributes[$element->node_name])) {
                return $element->get_attribute($this->id_attributes[$element->node_name]);
            }
        }
        if ($element->has_attribute(self::NAME_ATTRIBUTE)) {
            return $element->get_attribute(self::NAME_ATTRIBUTE);
        }
        return false;
    }
    /**
     * Verify attribute is main key for element.
     */
    protected function is_key_attribute(\Dom_Element $element, \Dom_Attr $attribute): bool
    {
        if (isset($this->id_attributes[$element->node_name])) {
            return $attribute->name === $this->id_attributes[$element->node_name];
        }
        return $attribute->name === self::NAME_ATTRIBUTE;
    }
    /**
     * Get node attributes.
     */
    protected function get_attributes(\Dom_Element $element): array
    {
        $attributes = [];
        if ($element->has_attributes()) {
            /** @var \DomAttr $attribute */
            foreach ($element->attributes as $attribute) {
                if (trim((string) $attribute->node_value) !== '' && !$this->is_key_attribute($element, $attribute)) {
                    $attributes[$attribute->node_name] = $this->cast_numeric($attribute->node_value);
                }
            }
        }
        return $attributes;
    }
    /**
     * Get child nodes data.
     *
     * @return array
     */
    protected function get_child_nodes(\Dom_Element $element)
    {
        if ($element->has_child_nodes()) {
            return $this->convert_xml($element->child_nodes);
        }
        return [];
    }
    /**
     * Cast nodeValue to int or double.
     *
     * @param string $nodeValue
     * @return float|integer
     */
    protected function cast_numeric($node_value)
    {
        if (is_numeric($node_value)) {
            if (preg_match('/^\d+$/', $node_value)) {
                $node_value = (int) $node_value;
            } else {
                $node_value = (float) $node_value;
            }
        }
        return $node_value;
    }
}